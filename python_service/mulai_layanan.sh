#!/bin/bash
# =====================================================
# MULAI_LAYANAN.SH - Helper untuk memulai layanan Python
# (Linux/macOS) Dipanggil oleh admin/mulai_layanan.php
# =====================================================
cd "$(dirname "$0")"

# Coba python3 dulu, lalu python
python3 aplikasi_layanan.py > layanan.log 2>&1 || python aplikasi_layanan.py > layanan.log 2>&1
