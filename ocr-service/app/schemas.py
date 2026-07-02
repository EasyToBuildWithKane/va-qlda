"""Pydantic response models for the proposal OCR service."""

from typing import Optional

from pydantic import BaseModel, Field


class ExtractedField(BaseModel):
    value: Optional[str] = None
    confidence: float = Field(0.0, ge=0.0, le=1.0)


class SignatureRegion(BaseModel):
    # proposer | department_head | board_of_directors | accountant | other
    role: str
    signed: bool = False
    signer_name: Optional[str] = None
    confidence: float = Field(0.0, ge=0.0, le=1.0)
    # bbox normalized to the page: [x0, y0, x1, y1] in 0..1
    bbox: Optional[list[float]] = None
    page: int = 1
    image_base64: Optional[str] = None


class ExtractResponse(BaseModel):
    raw_text: str = ""
    fields: dict[str, ExtractedField] = {}
    signatures: list[SignatureRegion] = []
    pages: int = 1
    duration_ms: int = 0


class HealthResponse(BaseModel):
    status: str = "ok"
    gemini_configured: bool = False
