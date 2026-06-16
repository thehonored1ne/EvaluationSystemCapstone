import os
import sys
import joblib
import pandas as pd
import numpy as np
from scipy.sparse import hstack
from flask import Flask, request, jsonify
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.tree import DecisionTreeClassifier
from sklearn.model_selection import train_test_split

# Download NLTK VADER Lexicon if not present
import nltk
try:
    nltk.data.find('sentiment/vader_lexicon.zip')
except LookupError:
    nltk.download('vader_lexicon')

from nltk.sentiment.vader import SentimentIntensityAnalyzer, VaderConstants

app = Flask(__name__)

# Manual dotenv loader to read root .env file without external package dependency
def load_dotenv():
    env_path = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), '.env')
    if os.path.exists(env_path):
        with open(env_path, 'r') as f:
            for line in f:
                line = line.strip()
                if not line or line.startswith('#') or '=' not in line:
                    continue
                key, val = line.split('=', 1)
                val = val.strip().strip('"').strip("'")
                os.environ[key.strip()] = val

load_dotenv()

# API Key Authorization Middleware
@app.before_request
def check_api_key():
    if request.path in ['/analyze', '/train']:
        expected_key = os.environ.get("AI_API_KEY", "default_secret_key_123")
        provided_key = request.headers.get("X-API-KEY")
        if not provided_key or provided_key != expected_key:
            return jsonify({"error": "Unauthorized. Invalid or missing X-API-KEY header."}), 401

# Update VADER negations list with Tagalog negations
VaderConstants.NEGATE.update({"hindi", "di", "wala", "huwag"})

# Initialize VADER Sentiment Intensity Analyzer
sia = SentimentIntensityAnalyzer()

# Helper: Load custom lexicon from Excel sheet
def load_custom_lexicon():
    excel_path = os.path.join(os.path.dirname(os.path.abspath(__file__)), "ai_data.xlsx")
    lexicon = {}
    if os.path.exists(excel_path):
        try:
            df = pd.read_excel(excel_path, sheet_name="Lexicon")
            for _, row in df.iterrows():
                word = str(row['Word']).strip().lower()
                score = float(row['Score'])
                lexicon[word] = score
        except Exception as e:
            print(f"Error loading lexicon from Excel: {e}", file=sys.stderr)
    return lexicon

# Helper: Load seed training dataset from Excel sheet
def load_seed_data():
    excel_path = os.path.join(os.path.dirname(os.path.abspath(__file__)), "ai_data.xlsx")
    seed_data = []
    if os.path.exists(excel_path):
        try:
            df = pd.read_excel(excel_path, sheet_name="SeedData")
            for _, row in df.iterrows():
                text = str(row['Text']).strip()
                label = str(row['Label']).strip().lower()
                rating = float(row['Rating'])
                seed_data.append((text, label, rating))
        except Exception as e:
            print(f"Error loading seed data from Excel: {e}", file=sys.stderr)
    return seed_data

# Update VADER lexicon with custom Excel data
sia.lexicon.update(load_custom_lexicon())

# Paths for saving models
MODEL_DIR = os.path.join(os.path.dirname(os.path.abspath(__file__)), "model")
os.makedirs(MODEL_DIR, exist_ok=True)
VECTORIZER_PATH = os.path.join(MODEL_DIR, "vectorizer.pkl")
CLASSIFIER_PATH = os.path.join(MODEL_DIR, "classifier.pkl")

# Helper: get VADER compound score and sentiment label
def get_vader_sentiment(text):
    if not text or not text.strip():
        return 0.0, "neutral"
    scores = sia.polarity_scores(text)
    compound = scores['compound']
    if compound >= 0.05:
        label = "positive"
    elif compound <= -0.05:
        label = "negative"
    else:
        label = "neutral"
    return compound, label

# Helper: Load Decision Tree and TF-IDF models
def load_models():
    if os.path.exists(VECTORIZER_PATH) and os.path.exists(CLASSIFIER_PATH):
        try:
            vectorizer = joblib.load(VECTORIZER_PATH)
            classifier = joblib.load(CLASSIFIER_PATH)
            return vectorizer, classifier
        except Exception as e:
            print(f"Error loading models: {e}", file=sys.stderr)
    return None, None

@app.route("/analyze", methods=["POST"])
def analyze():
    data = request.get_json() or {}
    comments = data.get("comments")
    ratings = data.get("ratings")
    single_mode = False

    if comments is None:
        comment = data.get("comment")
        rating = data.get("rating", 3.0)  # default to neutral 3.0 if missing
        if comment is not None:
            comments = [comment]
            ratings = [float(rating)]
            single_mode = True
        else:
            return jsonify({"error": "No comment or comments provided."}), 400

    if ratings is None:
        ratings = [3.0] * len(comments)
    else:
        ratings = [float(r) for r in ratings]

    vectorizer, classifier = load_models()
    results = []

    for text, rating in zip(comments, ratings):
        vader_score, vader_label = get_vader_sentiment(text)
        
        # Predict using Decision Tree if available (features: TF-IDF of text + Rating value)
        if vectorizer and classifier and text and text.strip():
            try:
                text_feat = vectorizer.transform([text])
                rating_feat = np.array([[rating]])
                combined_feat = hstack([text_feat, rating_feat])
                dt_label = classifier.predict(combined_feat)[0]
            except Exception as e:
                print(f"Prediction error: {e}", file=sys.stderr)
                dt_label = vader_label
        else:
            dt_label = vader_label

        results.append({
            "comment": text,
            "vader_score": vader_score,
            "vader_label": vader_label,
            "dt_label": dt_label
        })

    if single_mode:
        return jsonify(results[0])
    
    return jsonify({"results": results})

@app.route("/train", methods=["POST"])
def train():
    data = request.get_json() or {}
    samples = data.get("samples")
    legacy_comments = data.get("comments", [])
    
    db_training_samples = []

    # Handle standard "samples" payload
    if samples is not None:
        for s in samples:
            comment = s.get("comment", "").strip()
            if not comment:
                continue
            rating = float(s.get("rating", 3.0))
            manual_label = s.get("manual_label")
            
            if manual_label in ["positive", "neutral", "negative"]:
                db_training_samples.append((comment, manual_label, rating))
            else:
                # Run VADER
                _, vader_label = get_vader_sentiment(comment)
                # Agreement Gate
                if rating >= 4.2 and vader_label == "negative":
                    # Discard conflict
                    continue
                if rating <= 2.5 and vader_label == "positive":
                    # Discard conflict
                    continue
                db_training_samples.append((comment, vader_label, rating))

    # Handle legacy "comments" format (fallback for unit tests)
    elif legacy_comments:
        for c in legacy_comments:
            comment = c.strip()
            if not comment:
                continue
            _, vader_label = get_vader_sentiment(comment)
            db_training_samples.append((comment, vader_label, 3.0))

    # Load seed data from Excel
    seed_samples = load_seed_data()
    
    # Combine datasets
    all_samples = seed_samples + db_training_samples
    if not all_samples:
        return jsonify({"error": "No training samples available."}), 400

    # Separate inputs and outputs
    texts = [item[0] for item in all_samples]
    labels = [item[1] for item in all_samples]
    ratings = [item[2] for item in all_samples]

    try:
        # Fit TF-IDF Vectorizer
        vectorizer = TfidfVectorizer(ngram_range=(1, 2))
        X_text = vectorizer.fit_transform(texts)
        X_ratings = np.array(ratings).reshape(-1, 1)
        X_combined = hstack([X_text, X_ratings])

        # Train-Test Split (80% train, 20% test) to compute Confusion Matrix and Accuracy
        if len(all_samples) >= 5:
            X_train, X_test, y_train, y_test = train_test_split(
                X_combined, labels, test_size=0.2, random_state=42, stratify=labels if len(set(labels)) > 1 else None
            )
            # Train model on training split
            classifier_eval = DecisionTreeClassifier(random_state=42)
            classifier_eval.fit(X_train, y_train)
            
            # Predict on test split
            predictions = classifier_eval.predict(X_test)
            
            # Calculate accuracy
            accuracy = float(np.mean(predictions == y_test))
            
            # Build Confusion Matrix
            classes = ["positive", "neutral", "negative"]
            confusion = {c_actual: {c_pred: 0 for c_pred in classes} for c_actual in classes}
            for act, pred in zip(y_test, predictions):
                if act in classes and pred in classes:
                    confusion[act][pred] += 1
        else:
            accuracy = 1.0
            confusion = {
                "positive": {"positive": 0, "neutral": 0, "negative": 0},
                "neutral": {"positive": 0, "neutral": 0, "negative": 0},
                "negative": {"positive": 0, "neutral": 0, "negative": 0}
            }

        # Fit final model on all data
        classifier_final = DecisionTreeClassifier(random_state=42)
        classifier_final.fit(X_combined, labels)

        # Save models
        joblib.dump(vectorizer, VECTORIZER_PATH)
        joblib.dump(classifier_final, CLASSIFIER_PATH)

        return jsonify({
            "status": "success",
            "samples_trained": len(all_samples),
            "db_samples": len(db_training_samples),
            "seed_samples": len(seed_samples),
            "metrics": {
                "accuracy": accuracy,
                "confusion_matrix": confusion
            }
        })
    except Exception as e:
        import traceback
        traceback.print_exc()
        return jsonify({"status": "error", "message": str(e)}), 500

if __name__ == "__main__":
    # Run server locally on port 5001
    app.run(host="127.0.0.1", port=5001, debug=True)
