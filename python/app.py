import os
import sys
import joblib
from flask import Flask, request, jsonify
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.tree import DecisionTreeClassifier

# Download NLTK VADER Lexicon if not present
import nltk
try:
    nltk.data.find('sentiment/vader_lexicon.zip')
except LookupError:
    nltk.download('vader_lexicon')

from nltk.sentiment.vader import SentimentIntensityAnalyzer, VaderConstants

app = Flask(__name__)

# Update VADER negations list with Tagalog negations
VaderConstants.NEGATE.update({"hindi", "di", "wala", "huwag"})

# Initialize VADER Sentiment Intensity Analyzer
sia = SentimentIntensityAnalyzer()

# Custom Tagalog / Taglish lexicon
TAGALOG_LEXICON = {
    # Positive words
    "magaling": 2.0,
    "mahusay": 2.0,
    "mabait": 1.5,
    "maganda": 1.5,
    "masaya": 1.5,
    "salamat": 1.5,
    "gusto": 1.0,
    "lodi": 1.5,
    "petmalu": 1.5,
    "paborito": 1.5,
    "napakahusay": 2.5,
    "napakabait": 2.0,
    "maayos": 1.5,
    "maintindihan": 1.0,
    "malinaw": 1.5,
    "nakakatulong": 1.5,
    "naitutulong": 1.5,
    "natutunan": 1.0,
    "marami": 0.5,
    "madali": 1.0,
    "clear": 1.5,
    "helpful": 2.0,
    "love": 2.0,
    "galing": 1.5,
    "husay": 1.5,
    "bait": 1.5,

    # Negative words
    "galit": -2.0,
    "pangit": -2.0,
    "masungit": -2.0,
    "mahirap": -1.0,
    "mabagal": -1.5,
    "boring": -2.0,
    "ayaw": -1.5,
    "bagsak": -1.5,
    "gulo": -1.5,
    "maingay": -1.0,
    "terror": -2.0,
    "late": -1.0,
    "absent": -1.5,
    "magulo": -1.5,
    "walang": -1.0,
    "hirap": -1.0,
    "bad": -2.0,
    "worst": -3.0,
    "slow": -1.5,
    "worst": -2.0,
    "toxic": -2.5
}

# Update VADER lexicon
sia.lexicon.update(TAGALOG_LEXICON)

# Paths for saving models
MODEL_DIR = os.path.join(os.path.dirname(os.path.abspath(__file__)), "model")
os.makedirs(MODEL_DIR, exist_ok=True)
VECTORIZER_PATH = os.path.join(MODEL_DIR, "vectorizer.pkl")
CLASSIFIER_PATH = os.path.join(MODEL_DIR, "classifier.pkl")

# Seed training dataset (Tagalog, Taglish, English) to ensure robust initial fitting
SEED_DATA = [
    ("Mahusay magturo at mabait si ma'am.", "positive"),
    ("Masungit at laging galit, hindi ko nagustuhan ang klase.", "negative"),
    ("Boring ang lessons at sobrang bagal mag-discuss.", "negative"),
    ("Very helpful and clear in explaining topics.", "positive"),
    ("Ok naman ang klase, sakto lang.", "neutral"),
    ("Hindi nagtuturo nang maayos, laging absent.", "negative"),
    ("Salamat sa magandang sem, marami akong natutunan.", "positive"),
    ("Magulo ang scheduling at hindi malinaw ang grading.", "negative"),
    ("Sakto lang magturo, katamtaman.", "neutral"),
    ("Napakahusay na professor, idol ko po siya!", "positive"),
    ("Terror teacher, mahirap kausapin at laging galit.", "negative"),
    ("Explanation was easy to understand, thank you sir.", "positive"),
    ("Average class experience.", "neutral"),
    ("Pangit ng service, laging late pumasok.", "negative"),
    ("Mabait si sir, binibigyan kami ng sapat na oras.", "positive"),
    ("Hindi ko maintindihan ang lesson.", "negative"),
    ("Napakalaki ng naitulong sa aming program.", "positive"),
    ("Walang kwenta magturo, wala akong natutunan.", "negative"),
    ("Normal naman ang takbo ng klase.", "neutral"),
    ("Napakahusay at magaling magpaliwanag ng code.", "positive"),
    ("Okay lang naman.", "neutral"),
    ("Hindi malinaw magsalita si sir.", "negative")
]

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
    single_mode = False

    if comments is None:
        comment = data.get("comment")
        if comment is not None:
            comments = [comment]
            single_mode = True
        else:
            return jsonify({"error": "No comment or comments provided."}), 400

    vectorizer, classifier = load_models()
    results = []

    for text in comments:
        vader_score, vader_label = get_vader_sentiment(text)
        
        # Predict using Decision Tree if available
        if vectorizer and classifier and text and text.strip():
            try:
                features = vectorizer.transform([text])
                dt_label = classifier.predict(features)[0]
            except Exception as e:
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
    db_comments = data.get("comments", [])

    # Filter out empty comments
    db_comments = [c for c in db_comments if c and c.strip()]

    # Label comments from DB using VADER
    labeled_db = []
    for c in db_comments:
        _, label = get_vader_sentiment(c)
        labeled_db.append((c, label))

    # Combine with seed data
    training_data = SEED_DATA + labeled_db
    X = [item[0] for item in training_data]
    y = [item[1] for item in training_data]

    try:
        # Fit TF-IDF Vectorizer
        vectorizer = TfidfVectorizer(ngram_range=(1, 2))
        X_features = vectorizer.fit_transform(X)

        # Fit Decision Tree Classifier
        classifier = DecisionTreeClassifier(random_state=42)
        classifier.fit(X_features, y)

        # Save models
        joblib.dump(vectorizer, VECTORIZER_PATH)
        joblib.dump(classifier, CLASSIFIER_PATH)

        return jsonify({
            "status": "success",
            "samples_trained": len(training_data),
            "db_samples": len(db_comments),
            "seed_samples": len(SEED_DATA)
        })
    except Exception as e:
        return jsonify({"status": "error", "message": str(e)}), 500

if __name__ == "__main__":
    # Run server locally on port 5001
    app.run(host="127.0.0.1", port=5001, debug=True)
