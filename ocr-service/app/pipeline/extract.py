"""Gemini-based OCR + field mapping + signature detection for Vietnamese proposal forms."""

import base64
import json
import os

import numpy as np
from google import genai
from google.genai import types

from ..schemas import ExtractedField, SignatureRegion
from .preprocess import crop_signature, encode_jpeg

GEMINI_MODEL = os.environ.get("GEMINI_MODEL", "gemini-2.0-flash")

FIELD_KEYS = [
    "proposal_code",
    "proposal_date",
    "proposer_name",
    "proposer_position",
    "proposer_department",
    "send_to",
    "subject_about",
    "proposal_content",
    "justification",
    "cost_amount",
    "cost_unit",
    "quantity",
    "notes",
]

SIGNATURE_ROLES = ["proposer", "department_head", "board_of_directors", "accountant", "other"]

_RESPONSE_SCHEMA = {
    "type": "object",
    "properties": {
        "raw_text": {"type": "string"},
        "fields": {
            "type": "array",
            "items": {
                "type": "object",
                "properties": {
                    "key": {"type": "string", "enum": FIELD_KEYS},
                    "value": {"type": "string"},
                    "confidence": {"type": "number"},
                },
                "required": ["key", "value", "confidence"],
            },
        },
        "signatures": {
            "type": "array",
            "items": {
                "type": "object",
                "properties": {
                    "role": {"type": "string", "enum": SIGNATURE_ROLES},
                    "signed": {"type": "boolean"},
                    "signer_name": {"type": "string"},
                    "confidence": {"type": "number"},
                    "page": {"type": "integer"},
                    "box_2d": {
                        "type": "array",
                        "items": {"type": "integer"},
                        "minItems": 4,
                        "maxItems": 4,
                    },
                },
                "required": ["role", "signed", "confidence", "page", "box_2d"],
            },
        },
    },
    "required": ["raw_text", "fields", "signatures"],
}

_PROMPT = """Bạn là hệ thống OCR chuyên xử lý «Phiếu Đề Xuất» (giấy đề xuất, tờ trình) tiếng Việt của trường học / doanh nghiệp.
Ảnh đính kèm là các trang của MỘT tài liệu, theo đúng thứ tự trang 1..N.

Nhiệm vụ:
1. raw_text: chép lại toàn bộ văn bản trên tài liệu (giữ dấu tiếng Việt, xuống dòng theo bố cục).
2. fields: trích xuất các trường sau nếu xuất hiện trên phiếu (bỏ qua trường không có):
   - proposal_code: số/mã phiếu (vd. "Số: 15/PĐX-2026")
   - proposal_date: ngày lập phiếu, chuẩn hóa YYYY-MM-DD
   - proposer_name: họ tên người đề xuất
   - proposer_position: chức vụ người đề xuất
   - proposer_department: phòng ban / bộ phận đề xuất
   - send_to: kính gửi / nơi nhận
   - subject_about: trích yếu / về việc (V/v)
   - proposal_content: nội dung đề xuất chính (tóm tắt trung thực, có thể nhiều dòng)
   - justification: lý do / mục đích đề xuất
   - cost_amount: tổng chi phí, CHỈ chữ số (vd. "15000000"), không dấu phân cách
   - cost_unit: đơn vị tiền tệ (VND, USD...)
   - quantity: số lượng (chỉ chữ số)
   - notes: ghi chú khác
   Mỗi trường kèm confidence 0..1 (độ tin cậy đọc đúng; chữ viết tay mờ → thấp).
3. signatures: tìm TẤT CẢ các ô/vùng chữ ký trên tài liệu. Phân vai trò theo nhãn in trên form:
   - proposer: "Người đề xuất", "Người lập phiếu", "Người làm đơn"
   - department_head: "Trưởng bộ phận", "Trưởng phòng", "Trưởng đơn vị", "Tổ trưởng"
   - board_of_directors: "Ban Giám hiệu", "Hiệu trưởng", "Ban Giám đốc", "Giám đốc", "Người duyệt"
   - accountant: "Kế toán", "Kế toán trưởng", "Phòng Tài chính"
   - other: nhãn khác
   Với mỗi vùng: signed = true nếu có nét chữ ký thật (mực), false nếu ô còn trống;
   signer_name = tên ghi dưới/cạnh chữ ký nếu đọc được; page = số trang (bắt đầu từ 1);
   box_2d = [ymin, xmin, ymax, xmax] theo thang 0-1000 trên trang đó, bao trọn cả nét ký và tên,
   confidence 0..1 cho việc nhận diện đúng vai trò + trạng thái ký.
Chỉ trả về JSON đúng schema."""


class GeminiExtractor:
    def __init__(self, api_key: str | None = None):
        self._api_key = api_key or os.environ.get("GEMINI_API_KEY", "")
        self._client: genai.Client | None = None

    @property
    def configured(self) -> bool:
        return bool(self._api_key)

    def _get_client(self) -> genai.Client:
        if self._client is None:
            self._client = genai.Client(api_key=self._api_key)
        return self._client

    def extract(self, pages: list[np.ndarray]) -> tuple[str, dict[str, ExtractedField], list[SignatureRegion]]:
        parts: list[types.Part] = [
            types.Part.from_bytes(data=encode_jpeg(page), mime_type="image/jpeg")
            for page in pages
        ]
        parts.append(types.Part.from_text(text=_PROMPT))

        response = self._get_client().models.generate_content(
            model=GEMINI_MODEL,
            contents=[types.Content(role="user", parts=parts)],
            config=types.GenerateContentConfig(
                response_mime_type="application/json",
                response_schema=_RESPONSE_SCHEMA,
                temperature=0.0,
            ),
        )

        payload = json.loads(response.text or "{}")
        fields = self._parse_fields(payload.get("fields", []))
        signatures = self._parse_signatures(payload.get("signatures", []), pages)
        return payload.get("raw_text", ""), fields, signatures

    @staticmethod
    def _parse_fields(items: list[dict]) -> dict[str, ExtractedField]:
        fields: dict[str, ExtractedField] = {}
        for item in items:
            key = item.get("key")
            value = (item.get("value") or "").strip()
            if key not in FIELD_KEYS or not value:
                continue
            confidence = min(max(float(item.get("confidence", 0)), 0.0), 1.0)
            existing = fields.get(key)
            if existing is None or confidence > existing.confidence:
                fields[key] = ExtractedField(value=value, confidence=confidence)
        return fields

    @staticmethod
    def _parse_signatures(items: list[dict], pages: list[np.ndarray]) -> list[SignatureRegion]:
        signatures: list[SignatureRegion] = []
        for item in items:
            role = item.get("role")
            if role not in SIGNATURE_ROLES:
                role = "other"
            page_number = int(item.get("page", 1))
            page_index = min(max(page_number - 1, 0), len(pages) - 1)

            box = item.get("box_2d") or []
            bbox = None
            image_base64 = None
            if len(box) == 4:
                ymin, xmin, ymax, xmax = (min(max(float(v), 0.0), 1000.0) / 1000.0 for v in box)
                if xmax > xmin and ymax > ymin:
                    bbox = [xmin, ymin, xmax, ymax]
                    png = crop_signature(pages[page_index], bbox)
                    if png:
                        image_base64 = base64.b64encode(png).decode("ascii")

            signer_name = (item.get("signer_name") or "").strip() or None
            signatures.append(SignatureRegion(
                role=role,
                signed=bool(item.get("signed", False)),
                signer_name=signer_name,
                confidence=min(max(float(item.get("confidence", 0)), 0.0), 1.0),
                bbox=bbox,
                page=page_index + 1,
                image_base64=image_base64,
            ))
        return signatures
