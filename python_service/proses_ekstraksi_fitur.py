"""
PROSES_EKSTRAKSI_FITUR.PY
Fungsi bantu ekstraksi fitur CNN MobileNetV2 dan TF-IDF.
Sistem Rekomendasi Desain Undangan - Rotama Jaya
"""

import os
import re
import pickle
import tempfile
import numpy as np
from PIL import Image
from tensorflow.keras.applications import MobileNetV2
from tensorflow.keras.applications.mobilenet_v2 import preprocess_input
from sklearn.feature_extraction.text import TfidfVectorizer

# Muat model MobileNetV2 tanpa lapisan klasifikasi akhir (feature extractor)
_model_cnn = MobileNetV2(weights='imagenet', include_top=False, pooling='avg')
_cnn_siaga = False

# "Pemanasan" (warm-up) model: panggilan predict() PERTAMA di TensorFlow selalu jauh
# lebih lambat daripada panggilan-panggilan berikutnya (bisa belasan detik), karena
# TensorFlow baru MEMBANGUN & mengoptimalkan graf komputasinya saat itu, bukan saat
# model dimuat. Tanpa baris ini, kelambatan itu akan dirasakan admin persis saat
# pertama kali klik tombol "Unggah Desain" atau "Uji Skenario Otomatis" setelah
# layanan Python baru dinyalakan. Dengan memanggil predict() sekali di sini
# (memakai gambar kosong, tanpa perlu file di disk), biaya pemanasan itu terjadi
# SEKALI saat startup layanan — bukan saat admin sedang menunggu di depan browser.
_model_cnn.predict(np.zeros((1, 128, 128, 3), dtype=np.float32), verbose=0)
_cnn_siaga = True

LOKASI_MODEL_TFIDF = os.path.join(os.path.dirname(__file__), 'model_tfidf.pkl')


def pastikan_model_cnn_siaga() -> bool:
    """Memastikan model CNN sudah siap dipakai sebelum pencarian gambar."""
    global _cnn_siaga
    if _cnn_siaga:
        return True
    try:
        _model_cnn.predict(np.zeros((1, 128, 128, 3), dtype=np.float32), verbose=0)
        _cnn_siaga = True
        return True
    except Exception:
        return False


# Jaga agar model tetap siap pakai saat pencarian dimulai, bukan cuma saat startup.
pastikan_model_cnn_siaga()


def pastikan_vectorizer_tfidf_tersedia(daftar_kata_kunci=None) -> bool:
    """Membuat model TF-IDF dari korpus yang ada bila file model belum tersedia."""
    if muat_vectorizer_tfidf() is not None:
        return True
    if not daftar_kata_kunci:
        return False
    latih_ulang_dan_simpan_tfidf(daftar_kata_kunci)
    return muat_vectorizer_tfidf() is not None


def praproses_teks(teks: str) -> str:
    """
    Preprocessing teks SEBELUM dihitung TF-IDF, sesuai jalur rekomendasi berbasis
    teks pada sistem: hanya case folding dan tokenizing, TANPA stopword removal
    maupun stemming bahasa Indonesia secara terpisah (sesuai sub-bab 3.6.1 skripsi).
    Dipakai baik saat melatih ulang vectorizer (kata_kunci desain) maupun saat
    mengubah kueri pencarian pelanggan menjadi vektor.
    """
    teks = str(teks or '').lower()

    # Tokenizing: pecah teks jadi token kata, buang tanda baca/angka
    token = re.findall(r'[a-z]+', teks)

    return ' '.join(token)


def ekstrak_fitur_gambar(path_gambar: str) -> list:
    """Mengembalikan vektor fitur CNN MobileNetV2 dari sebuah gambar dengan ukuran lebih kecil agar lebih cepat."""
    try:
        gambar = Image.open(path_gambar)
        gambar = gambar.convert('RGB')
        gambar = gambar.resize((128, 128), Image.Resampling.LANCZOS)
        larik = np.array(gambar, dtype=np.float32)
        larik = np.expand_dims(larik, axis=0)
        larik = preprocess_input(larik)
        fitur = _model_cnn.predict(larik, verbose=0)
        return fitur.flatten().tolist()
    except Exception as e:
        pesan_error = str(e).lower()
        if 'does not support image input' in pesan_error or 'cannot read' in pesan_error:
            raise ValueError('Format gambar tidak didukung. Gunakan file gambar berformat JPG, PNG, atau WEBP yang valid.')
        raise


def kesamaan_kosinus(vektor_a, vektor_b) -> float:
    """Menghitung kesamaan kosinus antara dua vektor."""
    a = np.array(vektor_a)
    b = np.array(vektor_b)
    if np.linalg.norm(a) == 0 or np.linalg.norm(b) == 0:
        return 0.0
    return float(np.dot(a, b) / (np.linalg.norm(a) * np.linalg.norm(b)))


def bangun_matriks_vektor(daftar_vektor: list):
    """
    Mengubah daftar vektor (list of list, hasil json.loads) jadi SATU matriks numpy
    (N baris x D kolom) beserta norma tiap barisnya, dihitung SEKALI saja.
    Dipakai bareng kesamaan_kosinus_massal() supaya saat membandingkan satu vektor
    kueri ke banyak desain (atau banyak skenario uji ke desain yang sama), norma
    tiap desain tidak perlu dihitung ulang setiap kali — beda dengan memanggil
    kesamaan_kosinus() satu per satu di dalam loop Python.
    """
    if not daftar_vektor:
        return np.empty((0, 0)), np.empty((0,))
    matriks = np.array(daftar_vektor, dtype=np.float64)
    norma = np.linalg.norm(matriks, axis=1)
    return matriks, norma


def kesamaan_kosinus_massal(vektor_kueri, matriks_vektor, norma_vektor):
    """
    Menghitung kesamaan kosinus antara SATU vektor kueri dan BANYAK vektor sekaligus
    (matriks_vektor, norma_vektor dari bangun_matriks_vektor()), memakai satu kali
    perkalian matriks-vektor (numpy/BLAS) alih-alih loop Python yang memanggil
    kesamaan_kosinus() satu per satu untuk tiap desain. Hasilnya array skor dengan
    urutan yang sama seperti baris di matriks_vektor.
    """
    if matriks_vektor.shape[0] == 0:
        return np.empty((0,))
    q = np.array(vektor_kueri, dtype=np.float64)
    norma_q = np.linalg.norm(q)
    if norma_q == 0:
        return np.zeros(matriks_vektor.shape[0])
    penyebut = norma_vektor * norma_q
    with np.errstate(invalid='ignore', divide='ignore'):
        skor = (matriks_vektor @ q) / penyebut
    return np.nan_to_num(skor, nan=0.0, posinf=0.0, neginf=0.0)


def latih_ulang_dan_simpan_tfidf(daftar_kata_kunci: list) -> list:
    """
    Dipanggil HANYA saat admin mengunggah desain baru (bukan saat pencarian).
    Melatih ulang TfidfVectorizer dari seluruh kata_kunci desain yang ada di database
    (termasuk desain baru), menyimpan vectorizer terlatih ke disk agar bisa dipakai
    ulang saat pencarian tanpa menghitung ulang korpus, dan mengembalikan daftar
    vektor TF-IDF untuk SETIAP desain sesuai urutan `daftar_kata_kunci` (untuk
    disimpan kembali ke kolom vektor_fitur_tfidf masing-masing desain).
    """
    korpus_praproses = [praproses_teks(teks) for teks in daftar_kata_kunci]

    vektorizer = TfidfVectorizer()
    matriks = vektorizer.fit_transform(korpus_praproses)

    fd, lokasi_sementara = tempfile.mkstemp(
        prefix='model_tfidf.', suffix='.tmp', dir=os.path.dirname(LOKASI_MODEL_TFIDF)
    )
    try:
        with os.fdopen(fd, 'wb') as berkas:
            pickle.dump(vektorizer, berkas)
        os.replace(lokasi_sementara, LOKASI_MODEL_TFIDF)
    except Exception:
        try:
            os.close(fd)
        except OSError:
            pass
        try:
            os.unlink(lokasi_sementara)
        except FileNotFoundError:
            pass
        raise

    return [matriks[i].toarray().flatten().tolist() for i in range(matriks.shape[0])]


def muat_vectorizer_tfidf() -> TfidfVectorizer:
    """Memuat vectorizer TF-IDF yang sudah dilatih & disimpan sebelumnya (saat unggah)."""
    if not os.path.exists(LOKASI_MODEL_TFIDF):
        return None
    with open(LOKASI_MODEL_TFIDF, 'rb') as berkas:
        return pickle.load(berkas)


def hitung_vektor_kueri_teks(kueri: str):
    """
    Mengubah teks kueri pencarian menjadi vektor memakai vectorizer yang SUDAH tersimpan
    (dilatih sebelumnya saat unggah), tanpa melatih ulang korpus saat pencarian.
    """
    vektorizer = muat_vectorizer_tfidf()
    if vektorizer is None:
        return None
    return hitung_vektor_teks_tunggal(kueri, vektorizer)


def hitung_vektor_teks_tunggal(kueri: str, vectorizer=None):
    """Mengubah satu teks menjadi vektor memakai vectorizer yang sudah dimuat."""
    if vectorizer is None:
        vectorizer = muat_vectorizer_tfidf()
    if vectorizer is None:
        return None
    kueri_praproses = praproses_teks(kueri)
    return vectorizer.transform([kueri_praproses]).toarray().flatten().tolist()
