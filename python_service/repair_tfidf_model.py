import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))

from rebuild_vektor import jalankan


if __name__ == '__main__':
    try:
        jalankan()
    except Exception as kesalahan:
        print(f'Status akhir: gagal: {kesalahan}', file=sys.stderr)
        raise SystemExit(1)

