"""
APLIKASI_LAYANAN.PY
Layanan Flask - Mesin Rekomendasi Desain Undangan Rotama Jaya
Dua jalur terpisah total (non-hybrid): CNN MobileNetV2 dan TF-IDF + Kesamaan Kosinus.
"""

from __future__ import annotations

import os
import json
import hashlib
import time
import uuid
import tempfile
import gzip
import io
import numpy as np
import mysql.connector
from flask import Flask, request, jsonify
from werkzeug.utils import secure_filename

from proses_ekstraksi_fitur import (
    ekstrak_fitur_gambar,
    kesamaan_kosinus,
    bangun_matriks_vektor,
    kesamaan_kosinus_massal,
    latih_ulang_dan_simpan_tfidf,
    hitung_vektor_kueri_teks,
    hitung_vektor_teks_tunggal,
    muat_vectorizer_tfidf,
    pastikan_model_cnn_siaga,
    pastikan_vectorizer_tfidf_tersedia,
)

# Muat file .env (satu folder di atas python_service/) kalau tersedia, supaya
# konfigurasi database sinkron dengan yang dipakai koneksi_database.php di PHP.
try:
    from dotenv import load_dotenv
    load_dotenv(os.path.join(os.path.dirname(__file__), '..', '.env'))
except ImportError:
    pass  # python-dotenv opsional; kalau tidak terpasang, pakai default/env OS saja

aplikasi = Flask(__name__)

# Pastikan model siap saat layanan baru dimulai, supaya pencarian pertama tidak
# terasa lambat atau gagal karena TensorFlow/TF-IDF belum siap dipakai.
pastikan_model_cnn_siaga()

KONFIGURASI_DB = {
    'host': os.environ.get('DB_HOST', 'localhost'),
    'user': os.environ.get('DB_USER', 'root'),
    'password': os.environ.get('DB_PASSWORD', ''),
    'database': os.environ.get('DB_NAME', 'rotama_jaya'),
}

JUMLAH_HASIL_DITAMPILKAN = 10
LOKASI_GAMBAR_DESAIN = os.path.join(os.path.dirname(__file__), '..', 'uploads', 'desain')

_cache_vektor_cnn = None
_cache_vektor_tfidf = None
_cache_baris_cnn = None
_cache_baris_tfidf = None
_cache_korpus_tfidf = None
_cache_signature_desain = None


@aplikasi.after_request
def tambah_header_cors(respon):
    respon.headers['Access-Control-Allow-Origin'] = '*'
    respon.headers['Access-Control-Allow-Methods'] = 'GET, POST, OPTIONS'
    respon.headers['Access-Control-Allow-Headers'] = 'Content-Type'
    return respon


@aplikasi.after_request
def kompres_respons(respon):
    if respon.status_code >= 400 or respon.direct_passthrough:
        return respon
    tipe_konten = respon.content_type or ''
    if 'application/json' not in tipe_konten and 'text/' not in tipe_konten:
        return respon
    header_terima = request.headers.get('Accept-Encoding', '')
    if 'gzip' not in header_terima.lower():
        return respon
    data_lengkap = respon.get_data()
    if len(data_lengkap) < 200:
        return respon
    buffer = io.BytesIO()
    with gzip.GzipFile(fileobj=buffer, mode='wb', compresslevel=5) as f:
        f.write(data_lengkap)
    respon.set_data(buffer.getvalue())
    respon.headers['Content-Encoding'] = 'gzip'
    respon.headers['Content-Length'] = str(len(respon.get_data()))
    return respon


def Bersihkan_cache():
    global _cache_vektor_cnn, _cache_vektor_tfidf, _cache_baris_cnn, _cache_baris_tfidf, _cache_korpus_tfidf, _cache_signature_desain
    _cache_vektor_cnn = None
    _cache_vektor_tfidf = None
    _cache_baris_cnn = None
    _cache_baris_tfidf = None
    _cache_korpus_tfidf = None
    _cache_signature_desain = None


def Ambil_dan_cache_vektor(koneksi):
    global _cache_vektor_cnn, _cache_vektor_tfidf, _cache_baris_cnn, _cache_baris_tfidf, _cache_korpus_tfidf, _cache_signature_desain

    # Cek perubahan memakai metadata/hash ringan terlebih dahulu. Jangan mengambil
    # seluruh LONGTEXT vektor dari MySQL pada setiap request kalau cache masih valid.
    kursor = koneksi.cursor(dictionary=True)
    kursor.execute(
        "SELECT id_desain, judul_desain, nama_file_gambar, "
        "MD5(COALESCE(vektor_fitur_cnn, '')) AS hash_cnn, "
        "MD5(COALESCE(vektor_fitur_tfidf, '')) AS hash_tfidf "
        "FROM desain_undangan ORDER BY id_desain"
    )
    sidik_jari = kursor.fetchall()
    kursor.close()

    signature_desain = hashlib.sha256(json.dumps([
        (d['id_desain'], d['judul_desain'], d['nama_file_gambar'], d['hash_cnn'], d['hash_tfidf'])
        for d in sidik_jari
    ], ensure_ascii=False, separators=(',', ':')).encode('utf-8')).hexdigest()

    if (_cache_baris_cnn is not None and _cache_vektor_cnn is not None
            and signature_desain == _cache_signature_desain):
        return _cache_baris_cnn, _cache_vektor_cnn[0], _cache_baris_tfidf, _cache_vektor_tfidf

    # Cache kosong/kadaluarsa: hanya pada titik ini ambil JSON vektor lengkap.
    kursor = koneksi.cursor(dictionary=True)
    kursor.execute(
        "SELECT id_desain, judul_desain, nama_file_gambar, "
        "vektor_fitur_cnn, vektor_fitur_tfidf "
        "FROM desain_undangan ORDER BY id_desain"
    )
    baris = kursor.fetchall()
    kursor.close()

    baris_cnn_valid = []
    daftar_vektor_cnn = []
    for d in baris:
        v = _parse_vektor(d.get('vektor_fitur_cnn'))
        if v is not None:
            baris_cnn_valid.append(d)
            daftar_vektor_cnn.append(v)
    matriks_cnn, norma_cnn = bangun_matriks_vektor(daftar_vektor_cnn)

    baris_tfidf_valid = []
    daftar_vektor_tfidf = []
    for d in baris:
        v = _parse_vektor(d.get('vektor_fitur_tfidf'))
        if v is not None:
            baris_tfidf_valid.append(d)
            daftar_vektor_tfidf.append(v)
    matriks_tfidf, norma_tfidf = bangun_matriks_vektor(daftar_vektor_tfidf)

    _cache_baris_cnn = baris_cnn_valid
    _cache_vektor_cnn = (matriks_cnn, norma_cnn)
    _cache_baris_tfidf = baris_tfidf_valid
    _cache_vektor_tfidf = (matriks_tfidf, norma_tfidf)
    _cache_korpus_tfidf = None
    _cache_signature_desain = signature_desain

    return baris_cnn_valid, matriks_cnn, baris_tfidf_valid, (matriks_tfidf, norma_tfidf)


def kompres_dan_simpan_webp(path_sumber: str, direktori_tujuan: str) -> str | None:
    """
    Kompres gambar dari path_sumber dan simpan sebagai WebP di direktori_tujuan.
    Mengembalikan nama file WebP yang disimpan, atau None kalau gagal.
    """
    try:
        from PIL import Image
        nama = f"webp_{uuid.uuid4().hex}.webp"
        path_tujuan = os.path.join(direktori_tujuan, nama)
        with Image.open(path_sumber) as img:
            if img.mode in ('RGBA', 'P'):
                img = img.convert('RGB')
            lebar, tinggi = img.size
            if max(lebar, tinggi) > 1200:
                skala = 1200 / max(lebar, tinggi)
                img = img.resize((int(lebar * skala), int(tinggi * skala)), Image.Resampling.LANCZOS)
            img.save(path_tujuan, 'WEBP', quality=82, method=6)
        return nama
    except Exception as e:
        print(f'[Kompres] Gagal: {e}')
        return None


def _parse_vektor(teks):
    if not teks:
        return None
    try:
        return json.loads(teks)
    except (TypeError, ValueError):
        return None


def invalidate_cache_design():
    Bersihkan_cache()


@aplikasi.route('/health', methods=['GET'])
def health_check():
    """
    Health check + warmup endpoint. Dipanggil oleh admin panel setelah layanan
    Python dijalankan supaya model dan cache siap sebelum admin melakukan uji.
    """
    waktu_mulai = time.time()
    try:
        pastikan_model_cnn_siaga()
        koneksi = ambil_koneksi()
        try:
            kursor = koneksi.cursor(dictionary=True)
            kursor.execute("SELECT COUNT(*) as total FROM desain_undangan")
            total_desain = kursor.fetchone()['total']
            kursor.close()
            Ambil_dan_cache_vektor(koneksi)
        finally:
            koneksi.close()

        waktu_proses_ms = (time.time() - waktu_mulai) * 1000
        print(f'[Health] Selesai: {time.strftime("%H:%M:%S")}, total: {waktu_proses_ms:.0f}ms, desain: {total_desain}')
        return jsonify({
            'status': 'siap',
            'model_cnn': 'siaga',
            'total_desain': total_desain,
            'cache_cnn': _cache_baris_cnn is not None,
            'cache_tfidf': _cache_baris_tfidf is not None,
        })
    except Exception as e:
        print(f'[Health] Error: {str(e)}')
        return jsonify({'status': 'gagal', 'kesalahan': str(e)}), 500


def simpan_unggahan_sementara(berkas_gambar) -> str:
    """
    Menyimpan berkas unggahan ke folder temp dengan nama acak yang aman
    (bukan nama file asli dari pengguna), supaya tidak bisa dipakai untuk
    menimpa/mengarahkan ke path lain (path traversal) dan tidak bentrok
    antar permintaan yang datang bersamaan.
    """
    ekstensi = os.path.splitext(secure_filename(berkas_gambar.filename or ''))[1]
    nama_aman = f"{uuid.uuid4().hex}{ekstensi}"
    path_sementara = os.path.join(tempfile.gettempdir(), nama_aman)
    berkas_gambar.save(path_sementara)
    return path_sementara


def ambil_koneksi():
    return mysql.connector.connect(**KONFIGURASI_DB)


@aplikasi.route('/cari_berdasarkan_gambar', methods=['POST'])
def cari_berdasarkan_gambar():
    waktu_mulai = time.time()
    print(f'[CNN] Request dimulai: {time.strftime("%H:%M:%S")}')

    try:
        berkas_gambar = request.files.get('gambar')
        if not berkas_gambar:
            return jsonify({'kesalahan': 'Gambar tidak ditemukan'}), 400

        path_sementara = simpan_unggahan_sementara(berkas_gambar)
        print(f'[CNN] File disimpan: {time.strftime("%H:%M:%S")}')

        if not pastikan_model_cnn_siaga():
            os.remove(path_sementara)
            return jsonify({'kesalahan': 'Model CNN belum siap, silakan coba beberapa saat lagi.'}), 503

        try:
            fitur_kueri = ekstrak_fitur_gambar(path_sementara)
        except Exception as e:
            os.remove(path_sementara)
            return jsonify({'kesalahan': f'Gagal memproses gambar: {str(e)}'}), 400
        finally:
            if os.path.exists(path_sementara):
                os.remove(path_sementara)
        print(f'[CNN] Fitur diekstrak: {time.strftime("%H:%M:%S")}')

        koneksi = ambil_koneksi()
        try:
            baris_cnn_valid, matriks_cnn, _, _ = Ambil_dan_cache_vektor(koneksi)
            print(f'[CNN] Cache/DB diambil: {time.strftime("%H:%M:%S")}, total desain: {len(baris_cnn_valid)}')
            if not baris_cnn_valid:
                return jsonify({'kesalahan': 'Belum ada desain dengan vektor fitur CNN. Unggah minimal satu desain terlebih dahulu.'}), 400

            skor_semua = kesamaan_kosinus_massal(fitur_kueri, matriks_cnn, _cache_vektor_cnn[1])
            print(f'[CNN] Skor dihitung: {time.strftime("%H:%M:%S")}')

            hasil = [
                {
                    'id_desain': b['id_desain'],
                    'judul_desain': b['judul_desain'],
                    'nama_file_gambar': b['nama_file_gambar'],
                    'skor_kemiripan': float(skor),
                }
                for b, skor in zip(baris_cnn_valid, skor_semua)
            ]

            hasil.sort(key=lambda x: x['skor_kemiripan'], reverse=True)
            hasil = hasil[:JUMLAH_HASIL_DITAMPILKAN]

            waktu_proses_ms = (time.time() - waktu_mulai) * 1000
            print(f'[CNN] Selesai: {time.strftime("%H:%M:%S")}, total: {waktu_proses_ms:.0f}ms')
            return jsonify({'hasil': hasil, 'waktu_proses_ms': waktu_proses_ms})
        finally:
            koneksi.close()

    except Exception as e:
        print(f'[CNN] Error: {str(e)}')
        return jsonify({'kesalahan': f'Terjadi kesalahan: {str(e)}'}), 500


@aplikasi.route('/cari_berdasarkan_teks', methods=['POST'])
def cari_berdasarkan_teks():
    """
    Pencarian jalur TF-IDF murni. Vektor tiap desain SUDAH dihitung & disimpan sebelumnya
    saat admin mengunggah desain (lihat endpoint /ekstrak_fitur_unggahan) — di sini kueri
    hanya diubah ke vektor memakai vectorizer yang sudah tersimpan, TIDAK menghitung ulang
    korpus/TF-IDF saat pencarian, sesuai ketentuan sistem.
    """
    waktu_mulai = time.time()
    print(f'[TF-IDF] Request dimulai: {time.strftime("%H:%M:%S")}')

    try:
        data_masuk = request.get_json()
        kueri = (data_masuk or {}).get('kata_kunci', '').strip()
        if not kueri:
            return jsonify({'kesalahan': 'Kata kunci kosong'}), 400

        koneksi = ambil_koneksi()
        try:
            _, _, baris_tfidf_valid, (matriks_tfidf, norma_tfidf) = Ambil_dan_cache_vektor(koneksi)
            print(f'[TF-IDF] Cache/DB diambil: {time.strftime("%H:%M:%S")}, total desain: {len(baris_tfidf_valid)}')
            if not baris_tfidf_valid:
                return jsonify({'kesalahan': 'Belum ada desain yang diunggah, vectorizer TF-IDF belum tersedia'}), 400

            if not pastikan_vectorizer_tfidf_tersedia(_cache_korpus_tfidf):
                return jsonify({'kesalahan': 'Model TF-IDF belum siap. Unggah minimal satu desain agar kata kunci bisa dilatih.'}), 400

            vektor_kueri = hitung_vektor_kueri_teks(kueri)
            if vektor_kueri is None:
                return jsonify({'kesalahan': 'Belum ada desain yang diunggah, vectorizer TF-IDF belum tersedia'}), 400
            if not np.any(vektor_kueri):
                return jsonify({'kesalahan': 'Kata kunci tidak ditemukan dalam kosakata desain. Gunakan kata seperti pernikahan, ulang tahun, elegan, floral, atau adat.'}), 400

            skor_semua = kesamaan_kosinus_massal(vektor_kueri, matriks_tfidf, norma_tfidf)
            print(f'[TF-IDF] Skor dihitung: {time.strftime("%H:%M:%S")}')

            hasil = [
                {
                    'id_desain': b['id_desain'],
                    'judul_desain': b['judul_desain'],
                    'nama_file_gambar': b['nama_file_gambar'],
                    'skor_kemiripan': float(skor),
                }
                for b, skor in zip(baris_tfidf_valid, skor_semua)
            ]

            hasil.sort(key=lambda x: x['skor_kemiripan'], reverse=True)
            hasil = hasil[:JUMLAH_HASIL_DITAMPILKAN]

            waktu_proses_ms = (time.time() - waktu_mulai) * 1000
            print(f'[TF-IDF] Selesai: {time.strftime("%H:%M:%S")}, total: {waktu_proses_ms:.0f}ms')
            return jsonify({'hasil': hasil, 'waktu_proses_ms': waktu_proses_ms})
        finally:
            koneksi.close()

    except Exception as e:
        print(f'[TF-IDF] Error: {str(e)}')
        return jsonify({'kesalahan': f'Terjadi kesalahan: {str(e)}'}), 500


@aplikasi.route('/ekstrak_fitur_unggahan', methods=['POST'])
def ekstrak_fitur_unggahan():
    """
    Dipanggil SATU KALI oleh admin/kelola_desain.php saat admin mengunggah desain baru.
    - Menghitung vektor fitur CNN MobileNetV2 untuk gambar yang baru diunggah.
    - Selalu melatih ULANG TfidfVectorizer dari seluruh kata_kunci desain lama di DB
      ditambah kata_kunci desain baru, lalu memperbarui vektor_fitur_tfidf SEMUA desain
      lama di DB agar kosakata TF-IDF seluruh koleksi tetap konsisten.
    - Mengembalikan vektor CNN dan vektor TF-IDF untuk desain baru yang akan disimpan
      oleh PHP ke baris desain yang baru saja di-INSERT.

    Mengapa selalu latih ulang (bukan pakai vectorizer lama)?
    Karena TF-IDF adalah model berbasis korpus: bobot tiap kata bergantung pada seberapa
    sering kata itu muncul di SELURUH koleksi (IDF). Jika desain baru membawa kata kunci
    yang belum pernah ada sebelumnya, kata itu tidak ada dalam kosakata vectorizer lama
    sehingga nilainya selalu nol -- desain baru tidak akan pernah ditemukan lewat kata
    tersebut. Dengan melatih ulang dari korpus lengkap setiap kali ada upload baru,
    kosakata selalu mutakhir dan semua vektor (lama maupun baru) berada dalam ruang
    fitur yang sama. Ini tetap sejalan dengan aturan "tidak dihitung ulang saat pencarian":
    perhitungan ulang terjadi saat UNGGAH, bukan saat pencarian.
    """
    waktu_mulai = time.time()
    print(f'[Upload] Mulai ekstraksi: {time.strftime("%H:%M:%S")}')

    berkas_gambar = request.files.get('gambar')
    kata_kunci_baru = request.form.get('kata_kunci', '').strip()

    if not berkas_gambar:
        return jsonify({'kesalahan': 'Gambar tidak ditemukan'}), 400

    # 1) Vektor CNN untuk gambar baru
    path_sementara = simpan_unggahan_sementara(berkas_gambar)
    vektor_cnn = ekstrak_fitur_gambar(path_sementara)
    
    # 1b) Kompres gambar dan simpan versi WebP untuk performa web
    path_webp = kompres_dan_simpan_webp(path_sementara, LOKASI_GAMBAR_DESAIN)
    
    os.remove(path_sementara)
    print(f'[Upload] CNN selesai: {time.strftime("%H:%M:%S")}')

    # 2) Ambil semua desain lama dari DB (desain baru belum ada di DB saat ini)
    koneksi = ambil_koneksi()
    kursor = koneksi.cursor(dictionary=True)
    kursor.execute("SELECT id_desain, kata_kunci FROM desain_undangan ORDER BY id_desain")
    desain_lama = kursor.fetchall()

    # 3) Bangun korpus lengkap: semua kata_kunci lama + kata_kunci desain baru (di akhir).
    #    Posisi terakhir (indeks -1) akan menjadi vektor untuk desain yang baru diunggah.
    korpus_lengkap = [d['kata_kunci'] or '' for d in desain_lama] + [kata_kunci_baru]

    # 4) Latih ulang TF-IDF dari korpus lengkap, simpan vectorizer ke disk (.pkl),
    #    dan dapatkan vektor untuk SETIAP entri korpus dalam satu operasi fit_transform.
    semua_vektor = latih_ulang_dan_simpan_tfidf(korpus_lengkap)
    print(f'[Upload] TF-IDF selesai: {time.strftime("%H:%M:%S")}')

    # 5) Perbarui vektor_fitur_tfidf semua desain LAMA di DB agar ruang fitur konsisten.
    #    Desain baru (indeks terakhir) belum ada di DB -- vektornya dikembalikan ke PHP.
    if desain_lama:
        kursor_update = koneksi.cursor()
        for desain, vektor in zip(desain_lama, semua_vektor[:-1]):
            kursor_update.execute(
                "UPDATE desain_undangan SET vektor_fitur_tfidf = %s WHERE id_desain = %s",
                (json.dumps(vektor), desain['id_desain'])
            )
        koneksi.commit()

    koneksi.close()
    invalidate_cache_design()

    # 6) Vektor terakhir adalah milik desain baru -- dikembalikan ke PHP untuk di-INSERT
    vektor_tfidf_baru = semua_vektor[-1]

    waktu_proses_ms = (time.time() - waktu_mulai) * 1000
    print(f'[Upload] Total: {waktu_proses_ms:.0f}ms')

    return jsonify({
        'vektor_cnn': vektor_cnn,
        'vektor_tfidf': vektor_tfidf_baru,
        'nama_file_webp': path_webp,
    })


@aplikasi.route('/hitung_vektor_gambar_tunggal', methods=['POST'])
def hitung_vektor_gambar_tunggal():
    """
    Dipanggil oleh admin/kelola_desain.php saat admin MENGEDIT desain dan mengganti
    gambar pratinjaunya. Hanya menghitung & mengembalikan vektor CNN gambar yang baru
    (tidak menyentuh TF-IDF maupun desain lain), karena mengganti gambar tidak mengubah
    kosakata kata_kunci sehingga vectorizer TF-IDF tidak perlu dilatih ulang.
    """
    berkas_gambar = request.files.get('gambar')
    if not berkas_gambar:
        return jsonify({'kesalahan': 'Gambar tidak ditemukan'}), 400

    path_sementara = simpan_unggahan_sementara(berkas_gambar)
    vektor_cnn = ekstrak_fitur_gambar(path_sementara)
    path_webp = kompres_dan_simpan_webp(path_sementara, LOKASI_GAMBAR_DESAIN)
    os.remove(path_sementara)

    return jsonify({'vektor_cnn': vektor_cnn, 'nama_file_webp': path_webp})


def hitung_metrik_evaluasi_python(hasil_urut, id_kategori_uji, tema_uji, desain_db, id_desain_query=None):
    """
    Hitung metrik Precision, Recall, F1 untuk evaluasi skenario pengujian.
    Query SUDAH dikeluarkan dari hasil_urut sebelum fungsi dipanggil.
    id_desain_query parameter hanya opsional untuk kompatibilitas backward, tidak digunakan lagi.

    CATATAN PENTING:
    - Hasil pencarian hanya berisi {'id_desain', 'skor_kemiripan'}.
    - Label kategori & tema harus dicari dari desain_db berdasarkan id_desain.
    """
    # Map id_desain -> metadata desain untuk pengecekan relevansi
    label_per_desain = {
        int(d['id_desain']): {
            'id_kategori': int(d['id_kategori']) if d.get('id_kategori') is not None else None,
            'tema': d.get('tema') or ''
        }
        for d in desain_db
    }

    # Hitung jumlah SELURUH desain relevan di database (dengan kategori & tema sesuai query)
    jumlah_relevan_total = sum(
        1 for d in desain_db
        if (int(d['id_kategori']) if d.get('id_kategori') is not None else None) == int(id_kategori_uji)
        and (d.get('tema') or '') == tema_uji
    )

    # Hitung jumlah yang relevan dalam Top-5 dan Top-10
    relevan_5 = 0
    for item in hasil_urut[:5]:
        label = label_per_desain.get(int(item['id_desain']))
        if label and label['id_kategori'] == int(id_kategori_uji) and label['tema'] == tema_uji:
            relevan_5 += 1

    relevan_10 = 0
    for item in hasil_urut[:10]:
        label = label_per_desain.get(int(item['id_desain']))
        if label and label['id_kategori'] == int(id_kategori_uji) and label['tema'] == tema_uji:
            relevan_10 += 1

    # Hitung Precision@5, Precision@10
    presisi_5 = relevan_5 / 5
    presisi_10 = relevan_10 / 10

    # Hitung Recall@5, Recall@10
    ingatan_5 = relevan_5 / jumlah_relevan_total if jumlah_relevan_total > 0 else 0
    ingatan_10 = relevan_10 / jumlah_relevan_total if jumlah_relevan_total > 0 else 0

    # Hitung F1-Score@5, F1-Score@10
    f1_5 = (2 * presisi_5 * ingatan_5 / (presisi_5 + ingatan_5)) if (presisi_5 + ingatan_5) > 0 else 0
    f1_10 = (2 * presisi_10 * ingatan_10 / (presisi_10 + ingatan_10)) if (presisi_10 + ingatan_10) > 0 else 0

    return [presisi_5, presisi_10, ingatan_5, ingatan_10, f1_5, f1_10]


@aplikasi.route('/evaluasi_skenario_massal', methods=['POST'])
def evaluasi_skenario_massal():
    data_masuk = request.get_json() or {}
    daftar_desain = data_masuk.get('daftar_desain', [])
    if not daftar_desain:
        return jsonify({'cnn': [], 'tfidf': []})

    try:
        koneksi = ambil_koneksi()
        kursor = koneksi.cursor(dictionary=True)
        kursor.execute(
            "SELECT id_desain, nama_file_gambar, kata_kunci, id_kategori, tema, vektor_fitur_cnn, vektor_fitur_tfidf "
            "FROM desain_undangan WHERE id_kategori IS NOT NULL AND tema IS NOT NULL ORDER BY id_desain"
        )
        desain_db = kursor.fetchall()
        koneksi.close()
    except Exception as e:
        return jsonify({'cnn': [], 'tfidf': [], 'error': str(e)})

    vectorizer = muat_vectorizer_tfidf()
    if vectorizer is None:
        korpus = [d['kata_kunci'] or '' for d in desain_db]
        pastikan_vectorizer_tfidf_tersedia(korpus)
        vectorizer = muat_vectorizer_tfidf()
    hasil_cnn = []
    hasil_tfidf = []

    # ---- Pra-hitung SEKALI di luar loop skenario (perbaikan performa) ----
    # Sebelumnya, matriks vektor CNN & TF-IDF di-parse ulang dari JSON dan norma-nya
    # dihitung ulang untuk SETIAP skenario (bisa 30x lipat kerja yang sama-sama saja
    # hasilnya). Di sini di-parse & dibentuk jadi matriks numpy SATU KALI saja,
    # dipakai ulang untuk seluruh skenario lewat kesamaan_kosinus_massal().
    id_ke_desain = {int(d['id_desain']): d for d in desain_db}

    def _parse_vektor(teks):
        if not teks:
            return None
        try:
            return json.loads(teks)
        except (TypeError, ValueError):
            return None

    baris_cnn_valid = []
    daftar_vektor_cnn = []
    for d in desain_db:
        v = _parse_vektor(d.get('vektor_fitur_cnn'))
        if v is not None:
            baris_cnn_valid.append(d)
            daftar_vektor_cnn.append(v)
    matriks_cnn, norma_cnn = bangun_matriks_vektor(daftar_vektor_cnn)

    baris_tfidf_valid = []
    daftar_vektor_tfidf = []
    for d in desain_db:
        v = _parse_vektor(d.get('vektor_fitur_tfidf'))
        if v is not None:
            baris_tfidf_valid.append(d)
            daftar_vektor_tfidf.append(v)
    matriks_tfidf, norma_tfidf = bangun_matriks_vektor(daftar_vektor_tfidf)

    for item in daftar_desain:
        nama_gambar = (item.get('nama_file_gambar') or '').strip()
        kata_kunci = (item.get('kata_kunci') or '').strip()
        id_kategori = int(item.get('id_kategori') or 0)
        tema = item.get('tema') or ''
        id_desain_uji = int(item.get('id_desain') or 0)

        query_desain = id_ke_desain.get(id_desain_uji)
        if not query_desain:
            continue

        vektor_kueri_cnn = None
        if query_desain.get('vektor_fitur_cnn'):
            try:
                vektor_kueri_cnn = json.loads(query_desain['vektor_fitur_cnn'])
            except (TypeError, ValueError):
                vektor_kueri_cnn = None

        if vektor_kueri_cnn is not None:
            skor_semua = kesamaan_kosinus_massal(vektor_kueri_cnn, matriks_cnn, norma_cnn)
            hasil = [
                {'id_desain': desain['id_desain'], 'skor_kemiripan': float(skor)}
                for desain, skor in zip(baris_cnn_valid, skor_semua)
            ]
            # EXCLUDE QUERY SEBELUM SORT & TOP-10 (bukan sesudah)
            hasil = [item for item in hasil if int(item['id_desain']) != id_desain_uji]
            hasil.sort(key=lambda x: x['skor_kemiripan'], reverse=True)
            hasil = hasil[:JUMLAH_HASIL_DITAMPILKAN]
            metrik = hitung_metrik_evaluasi_python(hasil, id_kategori, tema, desain_db, None)
            hasil_cnn.append({
                'nomor_skenario': 0,
                'nama_gambar_uji': nama_gambar,
                'presisi_5': metrik[0],
                'presisi_10': metrik[1],
                'ingatan_5': metrik[2],
                'ingatan_10': metrik[3],
                'skor_f1_5': metrik[4],
                'skor_f1_10': metrik[5],
            })
        else:
            hasil_cnn.append({
                'nomor_skenario': 0,
                'nama_gambar_uji': nama_gambar,
                'presisi_5': 0,
                'presisi_10': 0,
                'ingatan_5': 0,
                'ingatan_10': 0,
                'skor_f1_5': 0,
                'skor_f1_10': 0,
            })

        vektor_kueri_tfidf = None
        if kata_kunci:
            if query_desain.get('vektor_fitur_tfidf'):
                try:
                    vektor_kueri_tfidf = json.loads(query_desain['vektor_fitur_tfidf'])
                except (TypeError, ValueError):
                    vektor_kueri_tfidf = None
            if vektor_kueri_tfidf is None:
                if vectorizer is None:
                    korpus = [d['kata_kunci'] or '' for d in desain_db]
                    latih_ulang_dan_simpan_tfidf(korpus)
                    vectorizer = muat_vectorizer_tfidf()
                if vectorizer is not None:
                    vektor_kueri_tfidf = hitung_vektor_teks_tunggal(kata_kunci, vectorizer)

        if vektor_kueri_tfidf is not None:
            skor_semua = kesamaan_kosinus_massal(vektor_kueri_tfidf, matriks_tfidf, norma_tfidf)
            hasil = [
                {'id_desain': desain['id_desain'], 'skor_kemiripan': float(skor)}
                for desain, skor in zip(baris_tfidf_valid, skor_semua)
            ]
            # EXCLUDE QUERY SEBELUM SORT & TOP-10 (bukan sesudah)
            hasil = [item for item in hasil if int(item['id_desain']) != id_desain_uji]
            hasil.sort(key=lambda x: x['skor_kemiripan'], reverse=True)
            hasil = hasil[:JUMLAH_HASIL_DITAMPILKAN]
            metrik = hitung_metrik_evaluasi_python(hasil, id_kategori, tema, desain_db, None)
            hasil_tfidf.append({
                'nomor_skenario': 0,
                'kata_kunci_uji': kata_kunci,
                'presisi_5': metrik[0],
                'presisi_10': metrik[1],
                'ingatan_5': metrik[2],
                'ingatan_10': metrik[3],
                'skor_f1_5': metrik[4],
                'skor_f1_10': metrik[5],
            })
        elif kata_kunci:
            hasil_tfidf.append({
                'nomor_skenario': 0,
                'kata_kunci_uji': kata_kunci,
                'presisi_5': 0,
                'presisi_10': 0,
                'ingatan_5': 0,
                'ingatan_10': 0,
                'skor_f1_5': 0,
                'skor_f1_10': 0,
            })

    return jsonify({'cnn': hasil_cnn, 'tfidf': hasil_tfidf})


@aplikasi.route('/latih_ulang_tfidf_semua', methods=['POST'])
def latih_ulang_tfidf_semua():
    """
    Dipanggil oleh admin/kelola_desain.php setelah admin MENGEDIT kata_kunci sebuah
    desain (bukan saat unggah desain baru). Melatih ulang TfidfVectorizer dari seluruh
    kata_kunci desain yang ada SAAT INI di database (termasuk yang baru saja diedit),
    lalu memperbarui kolom vektor_fitur_tfidf setiap desain. Proses ini dijalankan lewat
    HTTP dari PHP supaya admin tidak perlu membuka terminal atau menjalankan skrip manual
    untuk memperbarui vektor TF-IDF setelah perubahan kata kunci.
    """
    koneksi = ambil_koneksi()
    kursor = koneksi.cursor(dictionary=True)
    kursor.execute("SELECT id_desain, kata_kunci FROM desain_undangan ORDER BY id_desain")
    daftar_desain = kursor.fetchall()

    if not daftar_desain:
        koneksi.close()
        return jsonify({'jumlah_diperbarui': 0})

    korpus = [d['kata_kunci'] or '' for d in daftar_desain]
    semua_vektor = latih_ulang_dan_simpan_tfidf(korpus)

    kursor_update = koneksi.cursor()
    for d, vektor in zip(daftar_desain, semua_vektor):
        kursor_update.execute(
            "UPDATE desain_undangan SET vektor_fitur_tfidf = %s WHERE id_desain = %s",
            (json.dumps(vektor), d['id_desain'])
        )
    koneksi.commit()
    koneksi.close()
    invalidate_cache_design()

    return jsonify({'jumlah_diperbarui': len(daftar_desain)})


if __name__ == '__main__':
    # Untuk pengembangan lokal saja (php -S / XAMPP). Di server produksi,
    # jalankan lewat Gunicorn + systemd (lihat PANDUAN_HOSTING_VPS.md),
    # JANGAN pakai server bawaan Flask ini karena tidak dirancang untuk
    # menangani banyak permintaan sekaligus dan debug=True bisa membocorkan
    # informasi sensitif kalau tidak sengaja aktif di server publik.
    host = os.environ.get('FLASK_HOST', '127.0.0.1')
    port = int(os.environ.get('FLASK_PORT', 5000))
    debug = os.environ.get('FLASK_DEBUG', 'false').lower() == 'true'
    aplikasi.run(host=host, port=port, debug=debug)
