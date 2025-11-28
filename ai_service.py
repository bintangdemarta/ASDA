import os
import json
import logging
from flask import Flask, request, jsonify
from flask_cors import CORS
import redis
import nltk
from nltk.corpus import stopwords
from nltk.tokenize import word_tokenize
from nltk.stem import WordNetLemmatizer
import spacy
import numpy as np
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.naive_bayes import MultinomialNB
from sklearn.pipeline import Pipeline
import pickle
import re
import mysql.connector
from datetime import datetime

# Download required NLTK data
try:
    nltk.data.find('tokenizers/punkt')
except LookupError:
    nltk.download('punkt')

try:
    nltk.data.find('corpora/stopwords')
except LookupError:
    nltk.download('stopwords')

try:
    nltk.data.find('corpora/wordnet')
except LookupError:
    nltk.download('wordnet')

try:
    nltk.data.find('corpora/omw-1.4')
except LookupError:
    nltk.download('omw-1.4')

# Initialize spaCy model - but handle cases where models aren't installed yet
try:
    nlp = spacy.load("en_core_web_sm")  # English model
except OSError:
    try:
        nlp = spacy.load("id_core_news_sm")  # Indonesian model
    except OSError:
        # If no models are available, we'll use simpler NLP approaches
        print("INFO: No spaCy models found, using basic NLP approach")
        nlp = None

app = Flask(__name__)
CORS(app)

# Configure logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

# Initialize Redis connection
redis_host = os.getenv('REDIS_HOST', 'redis')
redis_client = redis.Redis(host=redis_host, port=6379, db=0, decode_responses=True)

# Database connection
db_host = os.getenv('DB_HOST', 'mysql')
db_user = 'admin_panel'
db_password = 'admin_panel'
db_name = 'admin_panel'

# Initialize NLP components
lemmatizer = WordNetLemmatizer()
indonesian_stopwords = set(stopwords.words('indonesian')) if hasattr(stopwords, 'words') and 'indonesian' in stopwords.fileids() else set(stopwords.words('english'))

# Intent classification model (will be trained later)
intent_classifier = None

# FAQ database as mentioned in app_summary.md
FAQ_DATABASE = {
    'status_klaim': {
        'patterns': ['status klaim', 'cek klaim', 'klaim saya', 'progres klaim', 'klaim mana', 'cek status'],
        'response': 'Anda dapat melihat status klaim Anda di dashboard. Silakan login dan pilih menu "Status Klaim" untuk informasi terbaru.'
    },
    'dana_cair': {
        'patterns': ['dana cair', 'pencairan dana', 'uang cair', 'dana sudah cair', 'transfer dana', 'dana disetujui'],
        'response': 'Proses pencairan dana biasanya memakan waktu 1-3 hari kerja setelah klaim disetujui. Anda akan menerima notifikasi email dan SMS jika dana sudah cair.'
    },
    'syarat_klaim': {
        'patterns': ['syarat klaim', 'persyaratan klaim', 'klaim butuh', 'dokumen klaim', 'dokumen apa', 'prosedur klaim'],
        'response': 'Untuk pengajuan klaim, Anda perlu menyediakan: 1) Formulir klaim yang telah diisi, 2) Fotokopi KTP/SIM, 3) Fotokopi bukti kejadian, 4) Dokumen pendukung lainnya sesuai jenis klaim Anda.'
    },
    'biaya_asuransi': {
        'patterns': ['biaya asuransi', 'harga polis', 'harga asuransi', 'berapa biaya', 'harga perlindungan', 'pembayaran asuransi'],
        'response': 'Biaya asuransi bervariasi tergantung jenis perlindungan dan manfaat yang dipilih. Silakan hubungi admin untuk informasi lebih lanjut sesuai kebutuhan Anda.'
    },
    'kontak_admin': {
        'patterns': ['hubungi admin', 'admin', 'cs', 'customer service', 'bantuan', 'telp admin'],
        'response': 'Anda dapat menghubungi admin melalui menu "Hubungi Admin" di dashboard, atau dengan membuat jadwal konsultasi langsung.'
    },
    'ganti_password': {
        'patterns': ['ganti password', 'ubah password', 'lupa password', 'reset password', 'ganti sandi'],
        'response': 'Untuk mengganti password, silakan masuk ke menu profil Anda dan pilih "Ganti Password". Jika lupa password, gunakan fitur "Lupa Password" di halaman login.'
    }
}

# Training data for intent classification
TRAINING_DATA = []
TRAINING_LABELS = []

# Initialize training data from FAQ
for intent, data in FAQ_DATABASE.items():
    for pattern in data['patterns']:
        TRAINING_DATA.append(pattern.lower())
        TRAINING_LABELS.append(intent)

def preprocess_text(text):
    """Preprocess the input text for NLP processing"""
    # Convert to lowercase
    text = text.lower()

    # Remove special characters and digits
    text = re.sub(r'[^a-zA-Z\u00C0-\u017F\s]', ' ', text)

    # Tokenize using NLTK
    tokens = word_tokenize(text)

    # Remove stopwords and lemmatize
    filtered_tokens = [lemmatizer.lemmatize(token) for token in tokens if token not in indonesian_stopwords and len(token) > 2]

    return ' '.join(filtered_tokens)


def extract_keywords_simple(text):
    """Extract keywords from text using simple approach without spaCy"""
    # Simple keyword extraction based on common words in the text
    words = word_tokenize(text.lower())
    # Remove common Indonesian/English words, keep meaningful ones
    meaningful_words = [word for word in words
                       if word not in indonesian_stopwords and
                       len(word) > 3 and
                       word.isalpha()]
    return meaningful_words

def train_intent_classifier():
    """Train the intent classification model"""
    global intent_classifier
    
    # Preprocess training data
    processed_data = [preprocess_text(text) for text in TRAINING_DATA]
    
    # Create and train pipeline
    intent_classifier = Pipeline([
        ('tfidf', TfidfVectorizer(ngram_range=(1, 2), max_features=5000)),
        ('clf', MultinomialNB())
    ])
    
    intent_classifier.fit(processed_data, TRAINING_LABELS)
    logger.info("Intent classifier trained successfully")

def get_db_connection():
    """Get database connection"""
    try:
        connection = mysql.connector.connect(
            host=db_host,
            user=db_user,
            password=db_password,
            database=db_name
        )
        return connection
    except Exception as e:
        logger.error(f"Database connection error: {e}")
        return None

def get_claim_status(user_id):
    """Get claim status for a specific user from database"""
    conn = get_db_connection()
    if not conn:
        return "Maaf, saat ini kami tidak dapat mengakses status klaim. Silakan coba lagi nanti."
    
    try:
        cursor = conn.cursor(dictionary=True)
        query = """
            SELECT id, policy_number, status, created_at, updated_at 
            FROM claim_requests 
            WHERE user_id = %s 
            ORDER BY created_at DESC 
            LIMIT 1
        """
        cursor.execute(query, (user_id,))
        result = cursor.fetchone()
        
        if result:
            status = result['status']
            created_at = result['created_at'].strftime('%d %B %Y') if result['created_at'] else 'N/A'
            return f"Status klaim terakhir Anda: {status} (Dibuat pada: {created_at}). Silakan cek dashboard untuk detail lebih lengkap."
        else:
            return "Anda belum memiliki permintaan klaim. Silakan ajukan klaim terlebih dahulu."
    except Exception as e:
        logger.error(f"Error fetching claim status: {e}")
        return "Terjadi kesalahan saat mengambil data status klaim."
    finally:
        if conn.is_connected():
            cursor.close()
            conn.close()

def get_fund_status(user_id):
    """Get fund disbursement status for a specific user"""
    conn = get_db_connection()
    if not conn:
        return "Maaf, saat ini kami tidak dapat mengakses status pencairan dana. Silakan coba lagi nanti."
    
    try:
        cursor = conn.cursor(dictionary=True)
        query = """
            SELECT id, policy_number, amount, status, disbursement_date 
            FROM transaction_history 
            WHERE user_id = %s AND transaction_type = 'disbursement'
            ORDER BY created_at DESC 
            LIMIT 1
        """
        cursor.execute(query, (user_id,))
        result = cursor.fetchone()
        
        if result:
            status = result['status']
            amount = result['amount']
            disbursement_date = result['disbursement_date'].strftime('%d %B %Y') if result['disbursement_date'] else 'N/A'
            return f"Dana sebesar Rp {amount:,} dengan status: {status}. Rencana pencairan: {disbursement_date}."
        else:
            return "Tidak ada riwayat pencairan dana. Silakan cek dashboard untuk informasi lebih lengkap."
    except Exception as e:
        logger.error(f"Error fetching fund status: {e}")
        return "Terjadi kesalahan saat mengambil data status pencairan dana."
    finally:
        if conn.is_connected():
            cursor.close()
            conn.close()

def classify_intent(text):
    """Classify the intent of the user question"""
    if intent_classifier is None:
        # If classifier is not trained, use simple pattern matching
        processed_text = preprocess_text(text)
        for intent, data in FAQ_DATABASE.items():
            for pattern in data['patterns']:
                if pattern.lower() in processed_text:
                    return intent
        return 'unknown'
    
    processed_text = preprocess_text(text)
    predicted_intent = intent_classifier.predict([processed_text])[0]
    
    # Get confidence score
    confidence = max(intent_classifier.predict_proba([processed_text])[0])
    
    # Return unknown if confidence is too low
    if confidence < 0.3:
        return 'unknown'
    
    return predicted_intent

def get_answer(question, user_id=None):
    """Get answer for the user question"""
    # First, try to classify the intent
    intent = classify_intent(question)

    # If it's a known FAQ intent, return the predefined response
    if intent in FAQ_DATABASE:
        response = FAQ_DATABASE[intent]['response']

        # For specific intents that require user data, fetch from database
        if intent == 'status_klaim' and user_id:
            return get_claim_status(user_id)
        elif intent == 'dana_cair' and user_id:
            return get_fund_status(user_id)

        return response

    # If intent is unknown, return default response
    return "Mohon maaf, saya belum dapat memahami pertanyaan Anda. Silakan hubungi admin untuk bantuan lebih lanjut atau gunakan fitur 'Hubungi Admin' untuk konsultasi langsung."

@app.route('/health', methods=['GET'])
def health_check():
    """Health check endpoint"""
    return jsonify({"status": "healthy", "service": "ASDA Internal AI Service"}), 200

@app.route('/chat', methods=['POST'])
def chat_endpoint():
    """Chat endpoint to process user questions"""
    try:
        data = request.get_json()
        
        if not data or 'question' not in data:
            return jsonify({"error": "Question is required"}), 400
        
        question = data['question']
        user_id = data.get('user_id')  # Optional user ID for personalized responses
        
        # Get answer
        answer = get_answer(question, user_id)
        
        # Log the interaction
        timestamp = datetime.now().isoformat()
        interaction_data = {
            "question": question,
            "answer": answer,
            "user_id": user_id,
            "timestamp": timestamp
        }
        
        # Store interaction in Redis for analytics
        redis_client.lpush("ai_interactions", json.dumps(interaction_data))
        
        # Store in database as well
        conn = get_db_connection()
        if conn:
            try:
                cursor = conn.cursor()
                query = """
                    INSERT INTO ai_conversations (user_id, question, response, created_at) 
                    VALUES (%s, %s, %s, %s)
                """
                cursor.execute(query, (user_id, question, answer, timestamp))
                conn.commit()
            except Exception as e:
                logger.error(f"Error storing conversation: {e}")
            finally:
                if conn.is_connected():
                    cursor.close()
                    conn.close()
        
        return jsonify({
            "answer": answer,
            "intent": classify_intent(question),
            "timestamp": timestamp
        }), 200
        
    except Exception as e:
        logger.error(f"Error processing chat request: {e}")
        return jsonify({"error": "Terjadi kesalahan saat memproses pertanyaan Anda"}), 500

@app.route('/train', methods=['POST'])
def train_endpoint():
    """Endpoint to retrain the model with new data"""
    try:
        train_intent_classifier()
        return jsonify({"message": "Model berhasil ditraining ulang"}), 200
    except Exception as e:
        logger.error(f"Error during training: {e}")
        return jsonify({"error": "Terjadi kesalahan saat training model"}), 500

if __name__ == '__main__':
    # Train the classifier on startup
    train_intent_classifier()
    
    app.run(host='0.0.0.0', port=5000, debug=False)