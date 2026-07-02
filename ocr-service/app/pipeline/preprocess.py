"""Document loading and image cleanup before OCR.

PDF pages are rendered with PyMuPDF; photos/scans go through EXIF rotation,
deskew, denoise and contrast normalisation so Gemini receives a clean input.
"""

import io

import cv2
import fitz  # PyMuPDF
import numpy as np
from PIL import Image, ImageOps

PDF_RENDER_DPI = 200
MAX_PAGES = 5
MAX_DIMENSION = 2200


def load_document(data: bytes, mime_type: str) -> list[np.ndarray]:
    """Return one preprocessed BGR image per page."""
    if mime_type == "application/pdf":
        pages = _render_pdf(data)
    else:
        pages = [_decode_image(data)]

    return [preprocess_page(page) for page in pages]


def _render_pdf(data: bytes) -> list[np.ndarray]:
    doc = fitz.open(stream=data, filetype="pdf")
    try:
        zoom = PDF_RENDER_DPI / 72
        matrix = fitz.Matrix(zoom, zoom)
        images = []
        for page in doc.pages(0, min(doc.page_count, MAX_PAGES)):
            pix = page.get_pixmap(matrix=matrix, colorspace=fitz.csRGB)
            arr = np.frombuffer(pix.samples, dtype=np.uint8).reshape(pix.height, pix.width, 3)
            images.append(cv2.cvtColor(arr, cv2.COLOR_RGB2BGR))
        if not images:
            raise ValueError("PDF has no pages")
        return images
    finally:
        doc.close()


def _decode_image(data: bytes) -> np.ndarray:
    # Pillow first: honours EXIF orientation from phone cameras.
    pil = Image.open(io.BytesIO(data))
    pil = ImageOps.exif_transpose(pil).convert("RGB")
    return cv2.cvtColor(np.asarray(pil), cv2.COLOR_RGB2BGR)


def preprocess_page(image: np.ndarray) -> np.ndarray:
    image = _limit_size(image)
    image = _deskew(image)
    image = _denoise_and_balance(image)
    return image


def _limit_size(image: np.ndarray) -> np.ndarray:
    height, width = image.shape[:2]
    longest = max(height, width)
    if longest <= MAX_DIMENSION:
        return image
    scale = MAX_DIMENSION / longest
    return cv2.resize(image, (int(width * scale), int(height * scale)), interpolation=cv2.INTER_AREA)


def _deskew(image: np.ndarray) -> np.ndarray:
    """Estimate small skew (±15°) from text lines and rotate to correct it."""
    gray = cv2.cvtColor(image, cv2.COLOR_BGR2GRAY)
    edges = cv2.Canny(gray, 60, 180)
    lines = cv2.HoughLinesP(
        edges, 1, np.pi / 180, threshold=120,
        minLineLength=image.shape[1] // 4, maxLineGap=20,
    )
    if lines is None:
        return image

    angles = []
    for x1, y1, x2, y2 in lines[:, 0]:
        angle = np.degrees(np.arctan2(y2 - y1, x2 - x1))
        if abs(angle) <= 15:
            angles.append(angle)
    if len(angles) < 5:
        return image

    skew = float(np.median(angles))
    if abs(skew) < 0.3:
        return image

    height, width = image.shape[:2]
    matrix = cv2.getRotationMatrix2D((width / 2, height / 2), skew, 1.0)
    return cv2.warpAffine(
        image, matrix, (width, height),
        flags=cv2.INTER_LINEAR, borderMode=cv2.BORDER_REPLICATE,
    )


def _denoise_and_balance(image: np.ndarray) -> np.ndarray:
    denoised = cv2.fastNlMeansDenoisingColored(image, None, 5, 5, 7, 21)
    lab = cv2.cvtColor(denoised, cv2.COLOR_BGR2LAB)
    l_channel, a_channel, b_channel = cv2.split(lab)
    clahe = cv2.createCLAHE(clipLimit=2.0, tileGridSize=(8, 8))
    merged = cv2.merge((clahe.apply(l_channel), a_channel, b_channel))
    return cv2.cvtColor(merged, cv2.COLOR_LAB2BGR)


def encode_jpeg(image: np.ndarray, quality: int = 90) -> bytes:
    ok, buffer = cv2.imencode(".jpg", image, [cv2.IMWRITE_JPEG_QUALITY, quality])
    if not ok:
        raise ValueError("Failed to encode page image")
    return buffer.tobytes()


def crop_signature(image: np.ndarray, bbox: list[float], padding: int = 8) -> bytes | None:
    """Crop a normalized bbox [x0, y0, x1, y1] from a page and return PNG bytes."""
    height, width = image.shape[:2]
    x0 = max(0, int(bbox[0] * width) - padding)
    y0 = max(0, int(bbox[1] * height) - padding)
    x1 = min(width, int(bbox[2] * width) + padding)
    y1 = min(height, int(bbox[3] * height) + padding)
    if x1 - x0 < 10 or y1 - y0 < 10:
        return None
    crop = image[y0:y1, x0:x1]
    ok, buffer = cv2.imencode(".png", crop)
    return buffer.tobytes() if ok else None
