"""FastAPI entrypoint for the VA-QLDA proposal OCR service."""

import logging
import os
import time

from fastapi import Depends, FastAPI, File, Header, HTTPException, UploadFile

from .pipeline.extract import GeminiExtractor
from .pipeline.preprocess import load_document
from .schemas import ExtractResponse, HealthResponse

logger = logging.getLogger("ocr-service")
logging.basicConfig(level=logging.INFO)

ALLOWED_MIME_TYPES = {"application/pdf", "image/jpeg", "image/png"}
MAX_FILE_BYTES = 10 * 1024 * 1024

app = FastAPI(title="VA-QLDA Proposal OCR", version="1.0.0")
extractor = GeminiExtractor()


def verify_token(x_ocr_token: str = Header(default="")) -> None:
    expected = os.environ.get("OCR_SERVICE_TOKEN", "")
    if not expected or x_ocr_token != expected:
        raise HTTPException(status_code=401, detail="Invalid or missing X-OCR-Token")


@app.get("/healthz", response_model=HealthResponse)
def healthz() -> HealthResponse:
    return HealthResponse(status="ok", gemini_configured=extractor.configured)


@app.post("/v1/extract", response_model=ExtractResponse, dependencies=[Depends(verify_token)])
async def extract(file: UploadFile = File(...)) -> ExtractResponse:
    started = time.perf_counter()

    mime_type = (file.content_type or "").lower()
    if mime_type not in ALLOWED_MIME_TYPES:
        raise HTTPException(status_code=422, detail=f"Unsupported file type: {mime_type}")

    data = await file.read()
    if len(data) > MAX_FILE_BYTES:
        raise HTTPException(status_code=422, detail="File exceeds 10MB limit")
    if not data:
        raise HTTPException(status_code=422, detail="Empty file")

    if not extractor.configured:
        raise HTTPException(status_code=503, detail="GEMINI_API_KEY is not configured")

    try:
        pages = load_document(data, mime_type)
    except Exception as exc:
        logger.exception("Failed to load document")
        raise HTTPException(status_code=422, detail=f"Cannot read document: {exc}") from exc

    try:
        raw_text, fields, signatures = extractor.extract(pages)
    except HTTPException:
        raise
    except Exception as exc:
        logger.exception("Gemini extraction failed")
        raise HTTPException(status_code=502, detail=f"AI extraction failed: {exc}") from exc

    duration_ms = int((time.perf_counter() - started) * 1000)
    logger.info(
        "Extracted %d fields, %d signatures from %d page(s) in %dms",
        len(fields), len(signatures), len(pages), duration_ms,
    )

    return ExtractResponse(
        raw_text=raw_text,
        fields=fields,
        signatures=signatures,
        pages=len(pages),
        duration_ms=duration_ms,
    )
