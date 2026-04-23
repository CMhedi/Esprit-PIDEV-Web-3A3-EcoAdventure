# EcoAdventure Card OCR API

API Python locale pour le mode demo de l'inscription pack.

Cette API lit une image de carte bancaire avec OCR, mais ne retourne jamais le numero complet de carte ni le CVC. Elle renvoie uniquement :

- marque detectee (`Visa`, `Mastercard`, etc.)
- numero masque
- 4 derniers chiffres
- date d'expiration
- nom detecte si l'OCR le lit clairement

## Installation

```powershell
cd tools/card_ocr_api
python -m venv .venv
.\.venv\Scripts\Activate.ps1
pip install -r requirements.txt
```

Installez aussi le moteur Tesseract OCR sur Windows, puis ajoutez son dossier dans le `PATH`.

## Lancement

```powershell
uvicorn main:app --host 127.0.0.1 --port 5055
```

L'application Symfony utilise par defaut :

```env
CARD_OCR_API_URL="http://127.0.0.1:5055/ocr/card"
```

## Endpoints

```text
GET  /health
POST /ocr/card
```

Le endpoint `/ocr/card` attend un champ multipart `file`.

## Securite

Ce service est fait pour une demonstration locale. Pour un vrai paiement carte en production, utilisez la page ou le SDK d'un prestataire PCI-compliant. Ne stockez pas les images de cartes, les numeros complets ou les CVC.
