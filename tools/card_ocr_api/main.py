from __future__ import annotations

import io
import os
import re
from dataclasses import dataclass
from typing import Any

from fastapi import FastAPI, File, HTTPException, UploadFile
from fastapi.middleware.cors import CORSMiddleware

try:
    from PIL import Image, ImageOps
except ImportError:
    Image = None
    ImageOps = None

try:
    import pytesseract

    TESSERACT_CMD = r"C:\Program Files\Tesseract-OCR\tesseract.exe"

    if os.path.exists(TESSERACT_CMD):
        pytesseract.pytesseract.tesseract_cmd = TESSERACT_CMD
        os.environ["TESSERACT_CMD"] = TESSERACT_CMD
        tesseract_dir = os.path.dirname(TESSERACT_CMD)
        os.environ["PATH"] = tesseract_dir + os.pathsep + os.environ.get("PATH", "")
    else:
        raise FileNotFoundError(f"Tesseract introuvable: {TESSERACT_CMD}")
except ImportError:
    pytesseract = None


MAX_IMAGE_BYTES = 5 * 1024 * 1024
CARD_NUMBER_RE = re.compile(r"(?:\d[\s-]?){13,19}")
EXPIRY_RE = re.compile(r"\b(0[1-9]|1[0-2])\s*[\/\-]\s*(\d{2}|\d{4})\b")
IGNORED_NAME_LINES = {
    "VISA",
    "MASTERCARD",
    "MASTER CARD",
    "CARTE",
    "BANK",
    "DEBIT",
    "CREDIT",
    "VALID",
    "VALID THRU",
    "VALID THROUGH",
    "CARDHOLDER",
    "CARD HOLDER",
}


app = FastAPI(
    title="EcoAdventure Card OCR API",
    version="1.2.0",
    description=(
        "OCR de carte bancaire pour mode demo. "
        "Cette version peut retourner le numero detecte pour pre-remplir un formulaire."
    ),
)

allowed_origins = [
    origin.strip()
    for origin in os.getenv(
        "OCR_ALLOWED_ORIGINS",
        ",".join([
            "http://127.0.0.1",
            "http://localhost",
            "http://127.0.0.1:80",
            "http://localhost:80",
            "http://127.0.0.1:8000",
            "http://localhost:8000",
            "http://127.0.0.1:8010",
            "http://localhost:8010",
        ]),
    ).split(",")
    if origin.strip()
]

app.add_middleware(
    CORSMiddleware,
    allow_origins=allowed_origins,
    allow_credentials=False,
    allow_methods=["GET", "POST", "OPTIONS"],
    allow_headers=["*"],
)


@dataclass(frozen=True)
class CardOcrResult:
    brand: str | None
    card_number: str | None
    pan_masked: str | None
    last4: str | None
    expiry: str | None
    holder_name: str | None
    warnings: list[str]

    def as_response(self) -> dict[str, Any]:
        confidence = 0.0
        for value in [self.brand, self.card_number, self.expiry, self.holder_name]:
            if value:
                confidence += 0.25

        return {
            "ok": True,
            "brand": self.brand,
            "card_number": self.card_number,
            "pan_masked": self.pan_masked,
            "last4": self.last4,
            "expiry": self.expiry,
            "holder_name": self.holder_name,
            "confidence": round(confidence, 2),
            "warnings": self.warnings,
        }


@app.get("/")
def root() -> dict[str, Any]:
    return {
        "ok": True,
        "message": "EcoAdventure OCR API is running",
        "endpoints": {
            "health": "/health",
            "ocr_card": "/ocr/card",
        },
    }


@app.get("/health")
def health() -> dict[str, Any]:
    tesseract_binary_available = False

    if pytesseract is not None:
        try:
            pytesseract.get_tesseract_version()
            tesseract_binary_available = True
        except Exception:
            tesseract_binary_available = False

    return {
        "ok": True,
        "ocr_engine": "pytesseract",
        "pillow_loaded": Image is not None,
        "pytesseract_loaded": pytesseract is not None,
        "tesseract_binary_available": tesseract_binary_available,
        "allowed_origins": allowed_origins,
    }


@app.post("/ocr/card")
async def ocr_card(file: UploadFile = File(...)) -> dict[str, Any]:
    if Image is None or ImageOps is None or pytesseract is None:
        raise HTTPException(
            status_code=503,
            detail=(
                "Dependances OCR manquantes. Installez Pillow, pytesseract et le moteur Tesseract OCR."
            ),
        )

    if file.content_type and not file.content_type.startswith("image/"):
        raise HTTPException(status_code=400, detail="Le fichier doit etre une image.")

    image_bytes = await file.read()

    if not image_bytes:
        raise HTTPException(status_code=400, detail="Aucun fichier envoye.")

    if len(image_bytes) > MAX_IMAGE_BYTES:
        raise HTTPException(status_code=413, detail="Image trop volumineuse. Maximum: 5 Mo.")

    try:
        image = Image.open(io.BytesIO(image_bytes))
    except Exception as exc:
        raise HTTPException(status_code=400, detail="Image illisible.") from exc

    try:
        text = extract_text(image)
    except Exception as exc:
        raise HTTPException(
            status_code=503,
            detail="Moteur Tesseract OCR indisponible. Installez tesseract.exe et ajoutez-le au PATH.",
        ) from exc

    result = parse_card_text(text)
    return result.as_response()


def extract_text(image: Any) -> str:
    prepared = ImageOps.exif_transpose(image).convert("L")
    width, height = prepared.size

    if width < 1200:
        scale = 1200 / max(width, 1)
        prepared = prepared.resize((int(width * scale), int(height * scale)))

    prepared = ImageOps.autocontrast(prepared)
    prepared = prepared.point(lambda pixel: 255 if pixel > 150 else 0)

    return pytesseract.image_to_string(prepared, config="--psm 6")


def parse_card_text(text: str) -> CardOcrResult:
    normalized = normalize_text(text)
    candidates = extract_pan_candidates(normalized)
    pan = candidates[0] if candidates else None
    expiry = extract_expiry(normalized)
    holder_name = extract_holder_name(normalized)
    brand = detect_brand(pan, normalized)

    warnings: list[str] = []

    if pan is None:
        warnings.append("Numero carte non detecte ou invalide.")

    if expiry is None:
        warnings.append("Date expiration non detectee.")

    if holder_name is None:
        warnings.append("Nom du titulaire non detecte.")

    return CardOcrResult(
        brand=brand,
        card_number=pan if pan else None,
        pan_masked=mask_pan(pan) if pan else None,
        last4=pan[-4:] if pan else None,
        expiry=expiry,
        holder_name=holder_name,
        warnings=warnings,
    )


def normalize_text(text: str) -> str:
    return "\n".join(line.strip() for line in text.upper().splitlines() if line.strip())


def extract_pan_candidates(text: str) -> list[str]:
    candidates: list[str] = []

    for match in CARD_NUMBER_RE.finditer(text):
        digits = re.sub(r"\D", "", match.group(0))
        if 13 <= len(digits) <= 19 and luhn_valid(digits):
            candidates.append(digits)

    return candidates


def extract_expiry(text: str) -> str | None:
    match = EXPIRY_RE.search(text)
    if not match:
        return None

    month = match.group(1)
    year = match.group(2)
    return f"{month}/{year[-2:]}"


def extract_holder_name(text: str) -> str | None:
    for line in text.splitlines():
        cleaned = re.sub(r"[^A-Z\s'-]", " ", line)
        cleaned = re.sub(r"\s+", " ", cleaned).strip()

        if len(cleaned) < 5 or cleaned in IGNORED_NAME_LINES:
            continue

        if any(token in cleaned for token in IGNORED_NAME_LINES):
            continue

        words = cleaned.split()
        if 2 <= len(words) <= 4 and all(len(word) >= 2 for word in words):
            return cleaned.title()

    return None


def detect_brand(pan: str | None, text: str) -> str | None:
    if pan:
        if pan.startswith("4"):
            return "Visa"

        first_two = int(pan[:2])
        first_four = int(pan[:4])

        if (51 <= first_two <= 55) or (2221 <= first_four <= 2720):
            return "Mastercard"

    if "VISA" in text:
        return "Visa"

    if "MASTERCARD" in text or "MASTER CARD" in text:
        return "Mastercard"

    return None


def mask_pan(pan: str) -> str:
    return f"{'*' * max(len(pan) - 4, 0)}{pan[-4:]}"


def luhn_valid(digits: str) -> bool:
    total = 0
    should_double = False

    for char in reversed(digits):
        digit = int(char)

        if should_double:
            digit *= 2
            if digit > 9:
                digit -= 9

        total += digit
        should_double = not should_double

    return total > 0 and total % 10 == 0


if __name__ == "__main__":
    import uvicorn
    uvicorn.run("main:app", host="127.0.0.1", port=8010, reload=True)