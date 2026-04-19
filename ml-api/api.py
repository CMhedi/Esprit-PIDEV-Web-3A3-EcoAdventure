from fastapi import FastAPI, HTTPException
from pydantic import BaseModel
from typing import List
import pandas as pd
import joblib
import logging
from datetime import datetime
import os
import random

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

app = FastAPI()

# =========================
# MODELE (SAFE LOAD)
# =========================

model = None
feature_names = [
    "nb_reservations_7j",
    "nb_absences_30j",
    "taux_absence",
    "nb_jours_actifs_30j",
    "trend_reservations",
    "trend_absence",
    "avg_calories_7j",
    "avg_protein_7j",
    "avg_carbs_7j",
    "days_since_signup"
]

try:
    if os.path.exists("xgboost_churn_production_final.pkl"):
        model = joblib.load("xgboost_churn_production_final.pkl")
        logger.info("✅ Model loaded")
    else:
        logger.warning("⚠️ Model file not found")
except Exception as e:
    logger.error(f"❌ Model load failed: {e}")
    model = None

# =========================
# SCHEMA
# =========================

class UserFeatures(BaseModel):
    nb_reservations_7j: int
    nb_absences_30j: int
    taux_absence: float
    nb_jours_actifs_30j: int
    trend_reservations: float
    trend_absence: float
    avg_calories_7j: float
    avg_protein_7j: float
    avg_carbs_7j: float
    days_since_signup: int

class BatchRequest(BaseModel):
    users: List[UserFeatures]

# =========================
# HEALTH
# =========================

@app.get("/health")
def health():
    return {
        "status": "ok",
        "model_loaded": model is not None,
        "timestamp": datetime.now().isoformat()
    }

# =========================
# PREDICT
# =========================

@app.post("/predict")
def predict(features: UserFeatures):
    try:
        data = pd.DataFrame([features.dict()])

        # 🔥 SI modèle OK
        if model:
            proba = model.predict_proba(data)[0][1]
        else:
            # ⚠️ fallback random (debug seulement)
            proba = random.uniform(0.2, 0.8)

        churn = 1 if proba > 0.5 else 0

        return {
            "churn": churn,
            "probability": float(proba),
            "timestamp": datetime.now().isoformat()
        }

    except Exception as e:
        raise HTTPException(status_code=400, detail=str(e))

# =========================
# BATCH
# =========================

@app.post("/predict/batch")
def predict_batch(request: BatchRequest):
    results = []

    for user in request.users:
        data = pd.DataFrame([user.dict()])

        if model:
            proba = model.predict_proba(data)[0][1]
        else:
            proba = random.uniform(0.2, 0.8)

        results.append({
            "churn": int(proba > 0.5),
            "probability": float(proba),
            "timestamp": datetime.now().isoformat()
        })

    return {
        "predictions": results,
        "count": len(results)
    }