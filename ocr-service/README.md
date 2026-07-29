# OCR Service — Số hóa Phiếu Đề Xuất (VA-Workspace)

Python microservice nhận file Phiếu Đề Xuất (PDF/JPG/PNG), tiền xử lý ảnh (xoay, khử nhiễu, cân bằng sáng) rồi dùng **Gemini Flash** để:

- OCR toàn bộ văn bản tiếng Việt (`raw_text`).
- Ánh xạ dữ liệu vào các trường chuẩn (`fields`, mỗi trường kèm `confidence` 0–1).
- Phát hiện vùng chữ ký theo vai trò (Người đề xuất, Trưởng bộ phận, Ban Giám hiệu, Kế toán…), cắt thành ảnh PNG riêng (`signatures[].image_base64`).

Laravel gọi service này qua `App\Services\AiAccount\ProposalOcrClient` (xem `docs/AI_ACCOUNTS.md`).

## Chạy local

```bash
cd ocr-service
python -m venv .venv
.venv\Scripts\activate          # Windows (Linux/macOS: source .venv/bin/activate)
pip install -r requirements.txt

set GEMINI_API_KEY=your-key     # Linux/macOS: export
set OCR_SERVICE_TOKEN=local-secret
uvicorn app.main:app --port 8100
```

Phía Laravel (`.env`):

```env
PROPOSAL_OCR_URL=http://127.0.0.1:8100
PROPOSAL_OCR_TOKEN=local-secret
```

## Chạy bằng Docker

```bash
docker build -t va-ocr-service ocr-service
docker run -p 8100:8100 -e GEMINI_API_KEY=... -e OCR_SERVICE_TOKEN=... va-ocr-service
```

## Env

| Biến | Bắt buộc | Mô tả |
|---|---|---|
| `GEMINI_API_KEY` | Có | API key Google AI Studio |
| `OCR_SERVICE_TOKEN` | Có | Shared secret — Laravel gửi qua header `X-OCR-Token` |
| `GEMINI_MODEL` | Không | Mặc định `gemini-2.0-flash` |

## API

### `POST /v1/extract`

- Header: `X-OCR-Token: <token>`
- Body: `multipart/form-data`, field `file` (PDF/JPG/PNG, ≤10MB, PDF tối đa 5 trang).
- Response 200:

```json
{
  "raw_text": "...",
  "fields": {
    "proposal_code": { "value": "15/PĐX-2026", "confidence": 0.95 },
    "proposer_name": { "value": "Nguyễn Văn A", "confidence": 0.9 },
    "cost_amount": { "value": "15000000", "confidence": 0.85 }
  },
  "signatures": [
    {
      "role": "department_head",
      "signed": true,
      "signer_name": "Trần Thị B",
      "confidence": 0.88,
      "bbox": [0.1, 0.72, 0.35, 0.85],
      "page": 1,
      "image_base64": "iVBORw0..."
    }
  ],
  "pages": 1,
  "duration_ms": 3200
}
```

- Lỗi: `401` sai token · `422` file không hợp lệ · `502` Gemini lỗi · `503` chưa cấu hình `GEMINI_API_KEY`.

### `GET /healthz`

`{ "status": "ok", "gemini_configured": true }`

## Field keys

`proposal_code`, `proposal_date` (YYYY-MM-DD), `proposer_name`, `proposer_position`, `proposer_department`, `send_to`, `subject_about`, `proposal_content`, `justification`, `cost_amount` (chỉ chữ số), `cost_unit`, `quantity`, `notes`.

Vai trò chữ ký: `proposer` · `department_head` · `board_of_directors` · `accountant` · `other`.
