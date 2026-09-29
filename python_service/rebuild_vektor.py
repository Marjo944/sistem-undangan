"""Bangun ulang vektor CNN dan TF-IDF untuk seluruh desain Rotama Jaya."""

import argparse
import json
import os
import pickle
import re
import sys
import tempfile
from pathlib import Path

import mysql.connector
import numpy as np
from PIL import Image
from dotenv import load_dotenv
from sklearn.feature_extraction.text import TfidfVectorizer
from tensorflow.keras.applications import MobileNetV2
from tensorflow.keras.applications.mobilenet_v2 import preprocess_input


DIREKTORI_SCRIPT = Path(__file__).resolve().parent
DIREKTORI_PROYEK = DIREKTORI_SCRIPT.parent
DIREKTORI_UPLOADS = DIREKTORI_PROYEK / "uploads"
LOKASI_MODEL_TFIDF = DIREKTORI_SCRIPT / "model_tfidf.pkl"
DIMENSI_GAMBAR = (128, 128)

load_dotenv(DIREKTORI_PROYEK / ".env")


def konfigurasi_database():
    return {
        "host": os.environ.get("DB_HOST", "localhost"),
        "user": os.environ.get("DB_USER", "root"),
        "password": os.environ.get("DB_PASSWORD", ""),
        "database": os.environ.get("DB_NAME", "rotama_jaya"),
    }


def praproses_teks(teks):
    token = re.findall(r"[a-z]+", str(teks or "").lower())
    return " ".join(token)


def cari_gambar(nama_file):
    nama_file = str(nama_file or "").strip()
    if not nama_file:
        return None

    nama_path = Path(nama_file)
    kandidat_langsung = []
    if not nama_path.is_absolute():
        kandidat_langsung.extend([
            DIREKTORI_UPLOADS / nama_path,
            DIREKTORI_UPLOADS / "desain" / nama_path.name,
        ])
    for kandidat in kandidat_langsung:
        if kandidat.is_file():
            return kandidat.resolve()

    nama_dasar = nama_path.name
    hasil = sorted(
        path.resolve()
        for path in DIREKTORI_UPLOADS.rglob(nama_dasar)
        if path.is_file()
    )
    if len(hasil) == 1:
        return hasil[0]
    if len(hasil) > 1:
        raise RuntimeError(
            f"Gambar '{nama_file}' ditemukan di beberapa lokasi; nama file tidak unik."
        )
    return None


def ekstrak_fitur_gambar(model, path_gambar):
    gambar = Image.open(path_gambar)
    gambar = gambar.convert('RGB')
    gambar = gambar.resize(DIMENSI_GAMBAR, Image.Resampling.LANCZOS)
    larik = np.array(gambar, dtype=np.float32)
    larik = preprocess_input(np.expand_dims(larik, axis=0))
    fitur = model.predict(larik, verbose=0).flatten().tolist()
    return fitur


def baca_desain(koneksi):
    kursor = koneksi.cursor(dictionary=True)
    try:
        kursor.execute(
            "SELECT id_desain, nama_file_gambar, kata_kunci "
            "FROM desain_undangan ORDER BY id_desain"
        )
        return kursor.fetchall()
    finally:
        kursor.close()


def simpan_model_sementara(vectorizer):
    fd, nama_sementara = tempfile.mkstemp(
        prefix="model_tfidf.", suffix=".tmp", dir=DIREKTORI_SCRIPT
    )
    try:
        with os.fdopen(fd, "wb") as berkas:
            pickle.dump(vectorizer, berkas)
        return Path(nama_sementara)
    except Exception:
        os.close(fd)
        Path(nama_sementara).unlink(missing_ok=True)
        raise


def jalankan(dry_run=False):
    if not DIREKTORI_UPLOADS.is_dir():
        raise RuntimeError(f"Folder uploads tidak ditemukan: {DIREKTORI_UPLOADS}")

    koneksi = None
    model_sementara = None
    cadangan_model = None
    try:
        koneksi = mysql.connector.connect(**konfigurasi_database())
        baris = baca_desain(koneksi)
        koneksi.commit()
        jumlah_desain = len(baris)
        print(f"Jumlah data desain: {jumlah_desain}")
        if jumlah_desain == 0:
            raise RuntimeError("Tabel desain_undangan kosong; proses dihentikan.")

        semua_kata_kunci = [baris_desain.get("kata_kunci") or "" for baris_desain in baris]
        korpus = [praproses_teks(teks) for teks in semua_kata_kunci]
        if not any(korpus):
            raise RuntimeError("Kata kunci seluruh desain kosong; proses dihentikan.")

        model_cnn = MobileNetV2(weights="imagenet", include_top=False, pooling="avg")
        daftar_path_gambar = []
        for baris_desain in baris:
            path_gambar = cari_gambar(baris_desain.get("nama_file_gambar"))
            if path_gambar is None:
                raise RuntimeError(
                    f"Gambar untuk id_desain {baris_desain['id_desain']} tidak ditemukan: "
                    f"{baris_desain.get('nama_file_gambar')!r}"
                )
            daftar_path_gambar.append(path_gambar)

        print(f"Jumlah gambar yang ditemukan: {len(daftar_path_gambar)}")
        daftar_vektor_cnn = []
        dimensi_cnn = None
        for nomor, (baris_desain, path_gambar) in enumerate(
            zip(baris, daftar_path_gambar), start=1
        ):
            try:
                vektor = ekstrak_fitur_gambar(model_cnn, path_gambar)
            except Exception as kesalahan:
                raise RuntimeError(
                    f"Gagal mengekstrak gambar id_desain {baris_desain['id_desain']} "
                    f"({path_gambar}): {kesalahan}"
                ) from kesalahan
            if dimensi_cnn is None:
                dimensi_cnn = len(vektor)
            elif len(vektor) != dimensi_cnn:
                raise RuntimeError("Dimensi vektor CNN tidak konsisten.")
            daftar_vektor_cnn.append(vektor)
            print(f"Progres ekstraksi CNN: {nomor}/{jumlah_desain}")

        vectorizer = TfidfVectorizer()
        matriks_tfidf = vectorizer.fit_transform(korpus)
        daftar_vektor_tfidf = [
            matriks_tfidf[nomor].toarray().flatten().tolist()
            for nomor in range(jumlah_desain)
        ]
        print(f"Dimensi vektor CNN: {dimensi_cnn}")
        print(f"Jumlah fitur TF-IDF: {len(vectorizer.vocabulary_)}")
        print(f"Lokasi file model TF-IDF: {LOKASI_MODEL_TFIDF}")

        if dry_run:
            print("Status akhir: DRY-RUN berhasil; tidak ada perubahan database atau file model.")
            return

        model_sementara = simpan_model_sementara(vectorizer)
        koneksi.start_transaction()
        kursor = koneksi.cursor()
        try:
            for baris_desain, vektor_cnn, vektor_tfidf in zip(
                baris, daftar_vektor_cnn, daftar_vektor_tfidf
            ):
                kursor.execute(
                    "UPDATE desain_undangan SET vektor_fitur_cnn = %s, "
                    "vektor_fitur_tfidf = %s WHERE id_desain = %s",
                    (
                        json.dumps(vektor_cnn, separators=(",", ":")),
                        json.dumps(vektor_tfidf, separators=(",", ":")),
                        baris_desain["id_desain"],
                    ),
                )
            os.replace(model_sementara, LOKASI_MODEL_TFIDF)
            model_sementara = None
            koneksi.commit()
        finally:
            kursor.close()

        print("Status akhir: berhasil; seluruh vektor disimpan dalam satu transaksi database.")
        print("Jalankan ulang layanan Flask atau panggil endpoint /health agar cache vektor diperbarui.")
    except Exception:
        if koneksi is not None and koneksi.in_transaction:
            koneksi.rollback()
        if model_sementara is not None:
            model_sementara.unlink(missing_ok=True)
        raise
    finally:
        if koneksi is not None and koneksi.is_connected():
            koneksi.close()


def main():
    parser = argparse.ArgumentParser(
        description="Bangun ulang vektor CNN dan TF-IDF desain Rotama Jaya."
    )
    parser.add_argument(
        "--dry-run",
        action="store_true",
        help="Hitung dan validasi vektor tanpa mengubah database atau file model.",
    )
    args = parser.parse_args()
    try:
        jalankan(dry_run=args.dry_run)
    except Exception as kesalahan:
        print(f"Status akhir: gagal: {kesalahan}", file=sys.stderr)
        return 1
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
