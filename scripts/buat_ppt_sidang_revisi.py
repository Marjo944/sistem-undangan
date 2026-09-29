from pathlib import Path

from PIL import Image
from pptx import Presentation
from pptx.dml.color import RGBColor
from pptx.enum.shapes import MSO_SHAPE
from pptx.enum.text import MSO_ANCHOR, PP_ALIGN
from pptx.util import Inches, Pt

ROOT = Path(__file__).resolve().parents[1]
OUT = ROOT / "presentasi_sidang_rotama_jaya.pptx"
ASSETS = ROOT / "assets" / "img"
prs = Presentation()
prs.slide_width, prs.slide_height = Inches(13.333), Inches(7.5)
prs.core_properties.title = "Sistem Rekomendasi Desain Undangan Berbasis Web"
prs.core_properties.subject = "Presentasi sidang skripsi Rotama Jaya"

INK = RGBColor(27, 42, 45)
MUTED = RGBColor(86, 103, 103)
PAPER = RGBColor(249, 248, 244)
WHITE = RGBColor(255, 255, 255)
GREEN = RGBColor(31, 108, 87)
GREEN_DARK = RGBColor(20, 58, 49)
LIME = RGBColor(206, 222, 130)
MINT = RGBColor(220, 235, 224)
CORAL = RGBColor(203, 100, 77)
BLUSH = RGBColor(245, 229, 220)
GOLD = RGBColor(191, 151, 75)
LINE = RGBColor(222, 225, 216)
FONT = "Aptos"


def rect(slide, x, y, w, h, color, rounded=False, transparency=0):
    kind = MSO_SHAPE.ROUNDED_RECTANGLE if rounded else MSO_SHAPE.RECTANGLE
    item = slide.shapes.add_shape(kind, Inches(x), Inches(y), Inches(w), Inches(h))
    item.fill.solid()
    item.fill.fore_color.rgb = color
    item.fill.transparency = transparency
    item.line.fill.background()
    if rounded:
        item.adjustments[0] = 0.06
    return item


def txt(slide, x, y, w, h, value, size=16, color=INK, bold=False,
    align=PP_ALIGN.LEFT, valign=MSO_ANCHOR.TOP, font=FONT, italic=False):
    item = slide.shapes.add_textbox(Inches(x), Inches(y), Inches(w), Inches(h))
    frame = item.text_frame
    frame.clear()
    frame.word_wrap = True
    frame.margin_left = Inches(0.02)
    frame.margin_right = Inches(0.02)
    frame.margin_top = Inches(0.02)
    frame.margin_bottom = Inches(0.02)
    frame.vertical_anchor = valign
    for i, line in enumerate(value.split("\n")):
        p = frame.paragraphs[0] if i == 0 else frame.add_paragraph()
        p.text = line
        p.alignment = align
        p.space_after = Pt(5)
        for run in p.runs:
            run.font.name = font
            run.font.size = Pt(size)
            run.font.bold = bold
            run.font.italic = italic
            run.font.color.rgb = color
    return item


def paragraph_runs(slide, x, y, w, h, entries, size=15):
    item = slide.shapes.add_textbox(Inches(x), Inches(y), Inches(w), Inches(h))
    frame = item.text_frame
    frame.clear()
    frame.word_wrap = True
    frame.margin_left = Inches(0.02)
    frame.margin_right = Inches(0.02)
    for i, (lead, body) in enumerate(entries):
        p = frame.paragraphs[0] if i == 0 else frame.add_paragraph()
        p.text = f"{lead}  {body}"
        p.space_after = Pt(13)
        p.font.name = FONT
        p.font.size = Pt(size)
        p.font.color.rgb = INK
    return item


def fit_picture(slide, path, x, y, w, h):
    path = Path(path)
    if not path.is_file():
        return False
    with Image.open(path) as image:
        iw, ih = image.size
    scale = min(w / iw, h / ih)
    rw, rh = iw * scale, ih * scale
    slide.shapes.add_picture(str(path), Inches(x + (w - rw) / 2), Inches(y + (h - rh) / 2), Inches(rw), Inches(rh))
    return True


def base(title, section, number):
    s = prs.slides.add_slide(prs.slide_layouts[6])
    s.background.fill.solid()
    s.background.fill.fore_color.rgb = PAPER
    rect(s, 0, 0, 13.333, 0.1, GREEN)
    txt(s, 0.68, 0.34, 8.2, 0.24, section.upper(), 9, GREEN, True)
    txt(s, 0.68, 0.72, 12, 0.58, title, 26, INK, True)
    rect(s, 0.68, 7.08, 12, 0.012, LINE)
    txt(s, 0.7, 7.14, 8.6, 0.18, "ROTAMA JAYA  ·  PRESENTASI SIDANG", 8, MUTED, True)
    txt(s, 12.1, 7.12, 0.45, 0.2, f"{number:02}", 9, MUTED, True, PP_ALIGN.RIGHT)
    return s


def callout(slide, x, y, w, h, heading, body, accent=GREEN):
    rect(slide, x, y, w, h, WHITE, True)
    rect(slide, x, y, 0.07, h, accent)
    txt(slide, x + 0.22, y + 0.16, w - 0.38, 0.32, heading, 15, INK, True)
    txt(slide, x + 0.22, y + 0.58, w - 0.4, h - 0.68, body, 12, MUTED)


def notes(slide, content):
    slide.notes_slide.notes_text_frame.text = content


# 1. Cover
s = prs.slides.add_slide(prs.slide_layouts[6])
s.background.fill.solid()
s.background.fill.fore_color.rgb = GREEN_DARK
rect(s, 8.55, 0, 4.783, 7.5, GREEN)
rect(s, 9.1, 0.65, 3.35, 4.5, PAPER, True)
fit_picture(s, ASSETS / "contoh_desain_2.png", 9.28, 0.82, 2.98, 4.1)
txt(s, 0.82, 0.7, 7.15, 0.28, "SIDANG SKRIPSI  /  PERCETAKAN ROTAMA JAYA", 10, RGBColor(222, 235, 215), True)
txt(s, 0.82, 1.45, 7.2, 2.15, "Sistem Rekomendasi\nDesain Undangan Berbasis Web", 34, WHITE, True)
txt(s, 0.84, 3.9, 6.95, 0.75, "Menggunakan CNN MobileNetV2 untuk gambar referensi dan TF-IDF untuk kata kunci", 17, RGBColor(222, 235, 228))
rect(s, 0.84, 5.15, 6.9, 0.015, RGBColor(79, 126, 108))
txt(s, 0.84, 5.45, 6.9, 0.8, "[Nama Mahasiswa]  ·  [NIM]\n[Program Studi]  ·  [Nama Universitas]", 12, WHITE)
txt(s, 0.84, 6.65, 6.9, 0.22, "[Bulan dan Tahun Sidang]", 10, RGBColor(222, 235, 215), True)
notes(s, "Judul pada sampul masih judul kerja berdasarkan sistem. Samakan dengan judul persis pada naskah final dan isi identitas.")

# 2. Alur presentasi
s = base("Alur Presentasi", "Pembukaan", 2)
agenda = ["Latar belakang dan fokus masalah", "Landasan teori dan metode", "Perancangan serta implementasi", "Pengujian dan hasil", "Kesimpulan dan saran"]
for i, item in enumerate(agenda):
    x = 0.9 + i * 2.48
    rect(s, x, 2.25, 2.15, 2.4, WHITE, True)
    rect(s, x, 2.25, 2.15, 0.16, [GREEN, CORAL, GOLD, GREEN, CORAL][i])
    txt(s, x + 0.18, 2.68, 1.8, 0.42, f"0{i + 1}", 19, GREEN, True)
    txt(s, x + 0.18, 3.3, 1.8, 1.05, item, 15, INK, True)
txt(s, 0.92, 5.45, 11.3, 0.55, "Presentasi mengikuti alur penelitian: masalah → rancangan → implementasi → evaluasi → simpulan.", 14, MUTED)

# 3. Latar belakang
s = base("Latar Belakang", "Bab I  /  Pendahuluan", 3)
txt(s, 0.9, 1.7, 5.35, 0.5, "KONTEKS", 11, GREEN, True)
paragraph_runs(s, 0.9, 2.12, 5.15, 3.45, [
    ("Koleksi", "desain undangan perlu ditelusuri berdasarkan preferensi pelanggan."),
    ("Input beragam", "preferensi dapat diberikan sebagai contoh gambar atau kata kunci."),
    ("Kebutuhan", "admin memerlukan pencarian terarah sekaligus pengelolaan katalog dan pesanan."),
])
rect(s, 6.7, 1.82, 5.65, 4.15, GREEN_DARK, True)
txt(s, 7.12, 2.25, 4.8, 0.35, "MASALAH OPERASIONAL", 11, RGBColor(222, 235, 215), True)
txt(s, 7.12, 2.95, 4.72, 1.5, "Bagaimana admin menemukan desain yang relevan secara lebih terstruktur?", 23, WHITE, True)
txt(s, 7.12, 4.85, 4.72, 0.62, "Solusi penelitian: dua jalur rekomendasi yang berjalan terpisah.", 14, RGBColor(222, 235, 228))

# 4. Rumusan masalah dan tujuan
s = base("Rumusan Masalah dan Tujuan", "Bab I  /  Fokus Penelitian", 4)
questions = [
    ("01", "CNN", "Bagaimana MobileNetV2 berbasis transfer learning memberi rekomendasi dari gambar referensi?"),
    ("02", "TF-IDF", "Bagaimana TF-IDF mencocokkan kata kunci pelanggan dengan informasi teks desain?"),
    ("03", "Sistem", "Bagaimana membangun dua jalur rekomendasi terpisah dalam web admin?"),
    ("04", "Operasional", "Bagaimana mengintegrasikan pesanan, katalog, kategori, dan file produksi?"),
    ("05", "Evaluasi", "Bagaimana performa CNN dan TF-IDF pada P@K, R@K, F1@K untuk K=5,10?"),
]
for i, (n, heading, body) in enumerate(questions):
    y = 1.55 + i * 0.93
    rect(s, 0.9, y, 11.5, 0.76, WHITE, True)
    txt(s, 1.1, y + 0.13, 0.5, 0.36, n, 12, GREEN, True, valign=MSO_ANCHOR.MIDDLE)
    txt(s, 1.8, y + 0.11, 1.55, 0.4, heading, 13, CORAL if n == "05" else INK, True, valign=MSO_ANCHOR.MIDDLE)
    txt(s, 3.35, y + 0.1, 8.7, 0.5, body, 11, MUTED, valign=MSO_ANCHOR.MIDDLE)
txt(s, 0.95, 6.35, 11.3, 0.3, "Tujuan penelitian diselaraskan dengan lima fokus tersebut.", 12, GREEN, True)

# 5. Ruang lingkup dan kontribusi
s = base("Ruang Lingkup dan Kontribusi", "Bab I  /  Batas Penelitian", 5)
callout(s, 0.9, 1.8, 5.55, 1.55, "Pengguna", "Admin Percetakan Rotama Jaya memasukkan input dan melayani pelanggan.")
callout(s, 0.9, 3.7, 5.55, 1.55, "Dua jalur", "CNN menerima gambar; TF-IDF menerima kata kunci. Sistem tidak menggabungkan skornya.", CORAL)
callout(s, 6.85, 1.8, 5.55, 1.55, "Sumber teks", "Implementasi TF-IDF saat ini memakai kolom kata_kunci pada data desain.", GOLD)
callout(s, 6.85, 3.7, 5.55, 1.55, "Kontribusi sistem", "Rekomendasi terhubung dengan katalog dan pencatatan pesanan admin.")
rect(s, 0.95, 5.75, 11.35, 0.7, BLUSH, True)
txt(s, 1.14, 5.93, 10.95, 0.32, "Batasan penting: bukan aplikasi pemesanan pelanggan secara mandiri.", 13, INK, True, PP_ALIGN.CENTER, MSO_ANCHOR.MIDDLE)

# 6. Landasan teori: CNN
s = base("Landasan Teori: CNN dan MobileNetV2", "Bab II  /  Metode Gambar", 6)
callout(s, 0.9, 1.8, 3.55, 1.45, "CNN", "Mempelajari representasi visual melalui lapisan konvolusi.", GREEN)
callout(s, 4.88, 1.8, 3.55, 1.45, "Transfer learning", "Memanfaatkan bobot model yang telah dilatih pada ImageNet.", GOLD)
callout(s, 8.85, 1.8, 3.55, 1.45, "MobileNetV2", "Arsitektur efisien digunakan sebagai ekstraktor fitur.", CORAL)
txt(s, 1.0, 3.75, 5.3, 0.35, "IMPLEMENTASI PADA SISTEM", 11, GREEN, True)
paragraph_runs(s, 1.0, 4.2, 5.3, 1.75, [
    ("Input", "gambar diubah ukurannya menjadi 128×128 piksel."),
    ("Ekstraksi", "MobileNetV2 tanpa classifier akhir menghasilkan vektor 1280 fitur."),
    ("Peringkat", "vektor kueri dibandingkan dengan katalog memakai cosine similarity."),
], 13)
rect(s, 7.0, 3.85, 5.2, 1.75, GREEN_DARK, True)
txt(s, 7.35, 4.22, 4.5, 0.35, "cos(θ) = (A · B) / (||A|| ||B||)", 22, WHITE, True, PP_ALIGN.CENTER)
txt(s, 7.4, 4.92, 4.4, 0.38, "Semakin besar skor, semakin dekat arah vektornya.", 12, RGBColor(222, 235, 228), align=PP_ALIGN.CENTER)
txt(s, 1.0, 6.35, 11.2, 0.28, "Catatan istilah: model memakai bobot pralatih sebagai ekstraktor fitur; kode tidak melakukan fine-tuning pada dataset lokal.", 10, CORAL, True)

# 7. Landasan teori: TF-IDF
s = base("Landasan Teori: TF-IDF", "Bab II  /  Metode Teks", 7)
txt(s, 0.95, 1.8, 5.2, 0.38, "PEMBOBOTAN KATA", 11, CORAL, True)
txt(s, 0.95, 2.3, 5.2, 0.8, "TF-IDF = TF × IDF", 29, INK, True)
txt(s, 0.95, 3.2, 5.1, 1.05, "TF merepresentasikan kemunculan istilah pada dokumen. IDF memberi bobot lebih besar pada istilah yang lebih jarang di seluruh korpus.", 15, MUTED)
rect(s, 0.95, 4.7, 5.15, 0.95, MINT, True)
txt(s, 1.17, 4.93, 4.7, 0.48, "IDF(t) ≈ log(N / df(t))", 19, GREEN, True, PP_ALIGN.CENTER, MSO_ANCHOR.MIDDLE)
txt(s, 6.85, 1.8, 5.2, 0.38, "PRA-PEMROSESAN DI KODE", 11, GREEN, True)
paragraph_runs(s, 6.85, 2.3, 5.3, 2.65, [
    ("Case folding", "seluruh teks dibuat huruf kecil."),
    ("Tokenisasi", "token alfabet dipisahkan dengan pola [a-z]+."),
    ("Tanpa", "stemming dan stopword removal terpisah."),
    ("Representasi", "vektor kueri dibandingkan dengan vektor TF-IDF desain."),
], 13)
txt(s, 6.85, 5.45, 5.3, 0.55, "Korpus saat dicek: 30 desain · 64 fitur TF-IDF.", 12, GREEN, True)
txt(s, 0.95, 6.35, 11.2, 0.28, "Penyesuaian redaksi yang disarankan: sebut pencocokan dengan kata kunci desain, bukan deskripsi desain.", 10, CORAL, True)

# 8. Metode dan data penelitian
s = base("Metode dan Data Penelitian", "Bab III  /  Rancangan", 8)
stages = [("DATA", "30 desain", "Seluruh data memiliki kategori dan tema."), ("CITRA", "30 vektor valid", "Dimensi vektor CNN konsisten: 1280."), ("TEKS", "30 vektor valid", "Dimensi vektor TF-IDF konsisten: 64."), ("LABEL", "3 kategori", "Relevansi evaluasi berdasar kategori dan tema.")]
for i, (tag, title, body) in enumerate(stages):
    x = 0.85 + i * 3.12
    rect(s, x, 2.0, 2.55, 2.15, WHITE, True)
    txt(s, x + 0.22, 2.28, 2.1, 0.25, tag, 10, GREEN if i % 2 == 0 else CORAL, True)
    txt(s, x + 0.22, 2.75, 2.15, 0.42, title, 17, INK, True)
    txt(s, x + 0.22, 3.35, 2.15, 0.6, body, 11, MUTED)
txt(s, 0.95, 4.75, 11.2, 0.35, "Alur eksperimen", 12, GREEN, True)
rect(s, 0.95, 5.18, 11.2, 0.015, LINE)
steps = ["Tentukan input uji", "Ambil kandidat tanpa item kueri", "Urutkan skor", "Hitung P/R/F1"]
for i, label in enumerate(steps):
    x = 1.0 + i * 2.9
    rect(s, x, 5.02, 0.32, 0.32, [GREEN, CORAL, GOLD, GREEN][i], True)
    txt(s, x + 0.42, 4.98, 2.4, 0.65, label, 11, INK, True, valign=MSO_ANCHOR.MIDDLE)
txt(s, 0.95, 6.35, 11.2, 0.28, "Data dan ringkasan angka bersumber dari database lokal yang diperiksa pada 28 September 2026.", 10, MUTED)

# 9. Arsitektur sistem
s = base("Arsitektur Sistem", "Bab III  /  Perancangan", 9)
layers = [(1.75, "Antarmuka Admin", "Cari gambar/teks · kelola desain · pesanan", GREEN), (3.15, "PHP", "Validasi formulir dan penghubung ke layanan rekomendasi", CORAL), (4.55, "Python Flask", "Ekstraksi fitur · cosine similarity · evaluasi", GOLD)]
for y, head, body, accent in layers:
    rect(s, 0.95, y, 7.6, 0.9, WHITE, True)
    rect(s, 0.95, y, 0.09, 0.9, accent)
    txt(s, 1.27, y + 0.14, 2.5, 0.58, head, 15, INK, True, valign=MSO_ANCHOR.MIDDLE)
    txt(s, 3.9, y + 0.14, 4.35, 0.58, body, 11, MUTED, valign=MSO_ANCHOR.MIDDLE)
    if y < 4:
        txt(s, 4.5, y + 0.91, 0.5, 0.34, "↓", 17, GREEN, True, PP_ALIGN.CENTER)
rect(s, 9.55, 2.05, 2.75, 1.55, MINT, True)
txt(s, 9.8, 2.34, 2.25, 0.85, "MySQL\nDesain · kategori · pesanan", 14, INK, True, PP_ALIGN.CENTER, MSO_ANCHOR.MIDDLE)
rect(s, 9.55, 4.35, 2.75, 1.25, BLUSH, True)
txt(s, 9.8, 4.58, 2.25, 0.75, "Vektor CNN\nVektor TF-IDF", 13, INK, True, PP_ALIGN.CENTER, MSO_ANCHOR.MIDDLE)
txt(s, 0.95, 6.2, 11.1, 0.4, "PHP berkomunikasi server-to-server dengan Flask; MySQL menyimpan metadata desain, vektor, dan transaksi.", 11, MUTED)

# 10. Input dan hasil visual
s = base("Contoh Input Visual dan Katalog", "Bab IV  /  Implementasi", 10)
rect(s, 0.85, 1.7, 5.65, 4.7, WHITE, True)
txt(s, 1.2, 1.98, 4.9, 0.3, "CONTOH DESAIN DI ASET PROYEK", 10, GREEN, True)
fit_picture(s, ASSETS / "contoh_desain_1.jpg", 1.15, 2.45, 2.2, 2.8)
fit_picture(s, ASSETS / "contoh_desain_3.jpg", 3.85, 2.45, 2.2, 2.8)
txt(s, 1.15, 5.55, 4.95, 0.45, "Aset contoh untuk ilustrasi; bukan bukti hasil pencarian.", 10, MUTED, italic=True)
rect(s, 6.85, 1.7, 5.55, 4.7, GREEN_DARK, True)
txt(s, 7.25, 2.05, 4.8, 0.3, "ALUR PENCARIAN GAMBAR", 10, RGBColor(222, 235, 215), True)
flow = [("1", "Admin unggah gambar referensi"), ("2", "MobileNetV2 ubah gambar menjadi vektor"), ("3", "Sistem hitung kemiripan ke katalog"), ("4", "Hasil diurutkan dan ditampilkan")]
for i, (num, label) in enumerate(flow):
    y = 2.7 + i * 0.78
    rect(s, 7.25, y, 0.42, 0.42, [LIME, CORAL, GOLD, LIME][i], True)
    txt(s, 7.25, y + 0.06, 0.42, 0.28, num, 11, INK, True, PP_ALIGN.CENTER)
    txt(s, 7.92, y - 0.01, 4.0, 0.5, label, 13, WHITE, valign=MSO_ANCHOR.MIDDLE)

# 11. Modul administrasi
s = base("Integrasi Modul Admin", "Bab IV  /  Implementasi", 11)
modules = [("Pencarian", "CNN gambar / TF-IDF teks", GREEN), ("Katalog", "Desain dan metadata", CORAL), ("Kategori", "Pengelompokan desain", GOLD), ("Pesanan", "Status dan personalisasi", GREEN), ("Produksi", "File produksi khusus admin", CORAL)]
for i, (head, body, accent) in enumerate(modules):
    x = 0.85 + i * 2.48
    rect(s, x, 2.15, 2.15, 2.25, WHITE, True)
    rect(s, x, 2.15, 2.15, 0.13, accent)
    txt(s, x + 0.18, 2.65, 1.8, 0.45, head, 15, INK, True)
    txt(s, x + 0.18, 3.28, 1.8, 0.82, body, 12, MUTED)
    if i < len(modules) - 1:
        txt(s, x + 2.18, 3.0, 0.28, 0.4, "→", 16, GREEN, True, PP_ALIGN.CENTER)
rect(s, 1.0, 5.15, 11.1, 0.92, MINT, True)
txt(s, 1.2, 5.38, 10.7, 0.42, "Kondisi database saat diperiksa: 30 desain · 3 kategori · 2 pesanan terhubung ke desain.", 14, INK, True, PP_ALIGN.CENTER, MSO_ANCHOR.MIDDLE)

# 12. Protokol evaluasi
s = base("Protokol Pengujian", "Bab IV  /  Evaluasi", 12)
callout(s, 0.9, 1.75, 3.55, 1.4, "Relevansi", "Desain relevan bila kategori DAN tema sama.", GREEN)
callout(s, 4.88, 1.75, 3.55, 1.4, "Jalur", "CNN dan TF-IDF dievaluasi secara terpisah.", CORAL)
callout(s, 8.85, 1.75, 3.55, 1.4, "Top-K", "Nilai K yang dilaporkan: 5 dan 10.", GOLD)
txt(s, 0.98, 3.65, 5.3, 0.35, "DEFINISI METRIK", 11, GREEN, True)
paragraph_runs(s, 0.98, 4.1, 5.25, 1.75, [
    ("Precision@K", "jumlah relevan pada Top-K dibagi K."),
    ("Recall@K", "jumlah relevan pada Top-K dibagi total relevan."),
    ("F1@K", "rata-rata harmonik Precision dan Recall."),
], 13)
rect(s, 6.9, 3.8, 5.25, 1.75, BLUSH, True)
txt(s, 7.23, 4.13, 4.6, 0.35, "KUALITAS PROTOKOL", 11, CORAL, True)
txt(s, 7.23, 4.6, 4.55, 0.72, "Item kueri dikeluarkan dari daftar rekomendasi. Pastikan juga penyebut Recall mengikuti definisi evaluasi yang ditetapkan.", 12, INK)
txt(s, 0.98, 6.35, 11.3, 0.28, "Database menyimpan 30 skenario dan enam nilai metrik per metode; seluruh nilai tidak kosong.", 10, MUTED)

# 13. Hasil kuantitatif
s = base("Hasil Pengujian", "Bab IV  /  Hasil", 13)
metrics = [("Precision@5", 0.4067, 0.6200), ("Precision@10", 0.3133, 0.4467), ("Recall@5", 0.2646, 0.4717), ("Recall@10", 0.4306, 0.6417), ("F1-Score@5", 0.3126, 0.5056), ("F1-Score@10", 0.3490, 0.5010)]
txt(s, 0.98, 1.52, 5.5, 0.3, "METRIK", 10, MUTED, True)
txt(s, 2.65, 1.52, 3.8, 0.3, "CNN MobileNetV2", 12, GREEN, True)
txt(s, 7.65, 1.52, 3.8, 0.3, "TF-IDF", 12, CORAL, True)
txt(s, 10.55, 1.52, 1.45, 0.3, "SKALA 0–1", 9, MUTED, True, PP_ALIGN.RIGHT)
for i, (label, cnn, tfidf) in enumerate(metrics):
    y = 1.98 + i * 0.62
    txt(s, 0.98, y - 0.02, 1.55, 0.34, label, 11, INK, True, valign=MSO_ANCHOR.MIDDLE)
    rect(s, 2.65, y + 0.05, 3.55, 0.22, LINE, True)
    rect(s, 2.65, y + 0.05, 3.55 * cnn, 0.22, GREEN, True)
    txt(s, 6.3, y - 0.02, 0.72, 0.34, f"{cnn:.4f}", 10, GREEN, True, align=PP_ALIGN.RIGHT, valign=MSO_ANCHOR.MIDDLE)
    rect(s, 7.65, y + 0.05, 3.55, 0.22, LINE, True)
    rect(s, 7.65, y + 0.05, 3.55 * tfidf, 0.22, CORAL, True)
    txt(s, 11.3, y - 0.02, 0.72, 0.34, f"{tfidf:.4f}", 10, CORAL, True, align=PP_ALIGN.RIGHT, valign=MSO_ANCHOR.MIDDLE)
rect(s, 0.95, 6.02, 11.4, 0.72, MINT, True)
txt(s, 1.18, 6.18, 10.95, 0.38, "Pada 30 skenario uji ini, rerata TF-IDF lebih tinggi pada keenam metrik.", 14, INK, True, PP_ALIGN.CENTER, MSO_ANCHOR.MIDDLE)
notes(s, "CNN: P@5 0.4067, P@10 0.3133, R@5 0.2646, R@10 0.4306, F1@5 0.3126, F1@10 0.3490. TF-IDF: P@5 0.6200, P@10 0.4467, R@5 0.4717, R@10 0.6417, F1@5 0.5056, F1@10 0.5010. Angka ini adalah agregat yang tersimpan dan belum divalidasi ulang setelah catatan protokol.")

# 14. Pembahasan
s = base("Pembahasan Hasil", "Bab IV  /  Interpretasi", 14)
rect(s, 0.9, 1.8, 5.5, 3.8, GREEN_DARK, True)
txt(s, 1.28, 2.18, 4.7, 0.35, "TEMUAN PADA DATA UJI", 11, RGBColor(222, 235, 215), True)
txt(s, 1.28, 2.85, 4.65, 1.4, "Rerata TF-IDF lebih tinggi daripada CNN pada P@5, P@10, R@5, R@10, F1@5, dan F1@10.", 21, WHITE, True)
txt(s, 1.28, 4.7, 4.6, 0.55, "Kesimpulan terbatas pada 30 desain dan label kategori-tema yang tersedia.", 12, RGBColor(222, 235, 228))
callout(s, 6.85, 1.8, 5.5, 1.55, "Mengapa hasil berbeda?", "TF-IDF mengevaluasi kata kunci yang berasosiasi dengan kategori/tema; CNN menilai representasi visual.\nIni interpretasi, bukan sebab kausal yang dibuktikan.", GOLD)
callout(s, 6.85, 3.75, 5.5, 1.55, "Makna praktis", "Kedua jalur menjawab bentuk input berbeda; skor tidak digabungkan dan tidak langsung mengukur kepuasan pengguna.", CORAL)

# 15. Kesimpulan dan saran
s = base("Kesimpulan dan Saran", "Bab V  /  Penutup", 15)
conclusions = [
    ("01", "CNN MobileNetV2", "Jalur gambar referensi menggunakan vektor fitur dan cosine similarity."),
    ("02", "TF-IDF", "Jalur teks mencocokkan kata kunci; redaksi rumusan perlu menyebut kata kunci desain."),
    ("03–04", "Sistem admin", "Dua jalur terpisah serta modul katalog, kategori, pesanan, dan file produksi tersedia."),
    ("05", "Evaluasi", "30 skenario/metode menghasilkan rerata P/R/F1 pada K=5 dan K=10; TF-IDF lebih tinggi pada data ini."),
]
for i, (num, head, body) in enumerate(conclusions):
    y = 1.62 + i * 0.87
    txt(s, 1.0, y + 0.08, 0.72, 0.34, num, 12, GREEN, True)
    txt(s, 1.85, y + 0.05, 2.5, 0.38, head, 13, INK, True)
    txt(s, 4.35, y + 0.03, 7.6, 0.5, body, 11, MUTED)
rect(s, 1, 5.35, 11.1, 0.95, MINT, True)
txt(s, 1.24, 5.52, 10.6, 0.58, "Saran: selaraskan rumusan dengan field yang digunakan; validasi ulang pengecualian kueri dan Recall; perluas data serta evaluasi relevansi dengan penilaian pengguna.", 12, INK, True, PP_ALIGN.CENTER, MSO_ANCHOR.MIDDLE)

# 16. Diskusi
s = prs.slides.add_slide(prs.slide_layouts[6])
s.background.fill.solid()
s.background.fill.fore_color.rgb = GREEN_DARK
rect(s, 9.5, 0, 3.833, 7.5, GREEN)
fit_picture(s, ASSETS / "contoh_desain_3.jpg", 10.0, 1.2, 2.85, 4.8)
txt(s, 0.95, 1.45, 7.9, 1.0, "Terima Kasih", 40, WHITE, True)
txt(s, 1.0, 2.9, 7.4, 0.62, "Diskusi dan pertanyaan", 22, RGBColor(222, 235, 215), True)
rect(s, 1.0, 4.0, 6.7, 0.015, RGBColor(79, 126, 108))
txt(s, 1.0, 4.35, 7.1, 0.82, "[Nama Mahasiswa]  ·  [NIM]\n[Program Studi]  ·  [Nama Universitas]", 13, WHITE)
notes(s, "Jawab pertanyaan dengan merujuk pada lingkup, dataset, definisi relevansi, dan batasan yang disampaikan.")

prs.save(OUT)
print(f"PPTX dibuat: {OUT}")
print(f"Jumlah slide: {len(prs.slides)}")