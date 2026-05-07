from flask import Flask, request, jsonify
from flask_cors import CORS
import pandas as pd
import numpy as np
from sklearn.ensemble import RandomForestRegressor
import joblib
import os

app = Flask(__name__)
CORS(app)

# ------------------------------------------------------------------------------
# 1. INITIALISATION DU MODÈLE IA (REAL MACHINE LEARNING)
# ------------------------------------------------------------------------------
MODEL_PATH = 'financial_model.pkl'

def train_initial_model():
    """Entraîne un modèle réel sur des données synthétiques initiales"""
    print("🧠 Entraînement du modèle de prédiction financière...")
    
    # Simulation de données historiques (Catégorie, Taux, Prix, Places -> Score, Tickets, CA)
    # Catégories mappées : NAUTIQUE=0, AVENTURE=1, NATURE=2, STAGE=3, COMPETITION=4, MARATHON=5, TOURNOI=6
    data = {
        'cat_code': [0, 0, 1, 1, 2, 2, 3, 4, 5, 6, 0, 1, 2],
        'taux_remplissage': [0.8, 0.4, 0.9, 0.3, 0.7, 0.2, 0.5, 0.6, 0.8, 0.9, 0.95, 0.1, 0.5],
        'prix_unitaire': [50, 50, 80, 80, 30, 30, 100, 150, 40, 60, 55, 90, 35],
        'nb_places': [20, 20, 15, 15, 50, 50, 10, 5, 100, 200, 25, 10, 40],
        # Cibles (Labels)
        'score_ia': [85, 45, 92, 35, 75, 25, 55, 65, 82, 95, 98, 15, 50],
        'predicted_tickets': [170, 90, 138, 52, 375, 125, 55, 32, 820, 1900, 245, 15, 200]
    }
    
    df = pd.DataFrame(data)
    X = df[['cat_code', 'taux_remplissage', 'prix_unitaire', 'nb_places']]
    y = df[['score_ia', 'predicted_tickets']]
    
    model = RandomForestRegressor(n_estimators=100, random_state=42)
    model.fit(X, y)
    
    joblib.dump(model, MODEL_PATH)
    print("✅ Modèle entraîné et sauvegardé.")
    return model

if not os.path.exists(MODEL_PATH):
    financial_model = train_initial_model()
else:
    financial_model = joblib.load(MODEL_PATH)

# Mapping des catégories pour le modèle
CAT_MAPPING = {
    'NAUTIQUE': 0, 'AVENTURE': 1, 'NATURE': 2, 'STAGE': 3,
    'COMPETITION': 4, 'MARATHON': 5, 'TOURNOI': 6
}

# ------------------------------------------------------------------------------
# 2. ENDPOINTS API
# ------------------------------------------------------------------------------

@app.route('/api/ai/predict-financials', methods=['POST'])
def predict_financials():
    """Endpoint de prédiction financière utilisant le modèle RandomForest"""
    data = request.json
    
    cat_name = data.get('categorie', 'AVENTURE')
    cat_code = CAT_MAPPING.get(cat_name, 1)
    taux = float(data.get('taux_remplissage', 0))
    prix = float(data.get('prix_unitaire', 0))
    places = int(data.get('nb_places', 0))
    
    # Prédiction via le modèle
    input_data = np.array([[cat_code, taux, prix, places]])
    prediction = financial_model.predict(input_data)[0]
    
    score_ia = float(prediction[0])
    predicted_tickets = int(prediction[1])
    predicted_ca = float(predicted_tickets * prix)
    
    return jsonify({
        'score_ia': round(score_ia, 1),
        'predicted_tickets': predicted_tickets,
        'predicted_ca': round(predicted_ca, 2)
    })

@app.route('/api/ai/predict-waitlist-limit', methods=['POST'])
def predict_waitlist():
    data = request.json
    places = int(data.get('places_total', 0))
    # Logique IA simplifiée pour l'exemple
    suggested = int(places * 0.5) if data.get('categorie') == 'NAUTIQUE' else int(places * 0.3)
    return jsonify({'suggested_limit': max(5, suggested)})

@app.route('/api/ai/yield-management', methods=['POST'])
def yield_management():
    data = request.json
    current_price = float(data.get('current_price', 0))
    fill_speed = float(data.get('fill_speed_hours', 0))
    
    if fill_speed < 2:
        return jsonify({
            'suggested_price': round(current_price * 1.2, 2),
            'admin_alert': 'DEMANDE FORTE : Augmentation suggérée'
        })
    return jsonify({
        'suggested_price': current_price,
        'admin_alert': 'Demande normale'
    })

@app.route('/api/ai/weather-alert', methods=['POST'])
def weather_alert():
    data = request.json
    temp = float(data.get('temp', 0))
    is_bad = data.get('is_bad_weather', False)
    
    msg = "Conditions optimales."
    if is_bad:
        msg = "Attention : Conditions météorologiques défavorables détectées par l'IA."
    if temp > 35:
        msg = "Alerte IA : Risque de canicule extrême."
        
    return jsonify({'alert_message': msg})

@app.route('/api/ai/recommend-alternative', methods=['POST'])
def recommend():
    return jsonify({
        'trigger_recommendation': True,
        'ai_message': "L'IA vous suggère une alternative disponible immédiatement :"
    })

if __name__ == '__main__':
    app.run(port=5000, debug=True)
