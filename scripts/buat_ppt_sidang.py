from pathlib import Path

from pptx import Presentation
from pptx.dml.color import RGBColor
from pptx.enum.shapes import MSO_SHAPE
from pptx.enum.text import MSO_ANCHOR, PP_ALIGN
from pptx.util import Inches, Pt

ROOT = Path(__file__).resolve().parents[1]
OUTPUT = ROOT / "presentasi_sidang_rotama_jaya.pptx"
prs = Presentation()
prs.slide_width, prs.slide_height = Inches(13.333), Inches(7.5)
prs.core_properties.title = "Sistem Rekomendasi Desain Undangan Rotama Jaya"
prs.core_properties.subject = "Presentasi sidang skripsi"

INK = RGBColor(25, 43, 48)
MUTED = RGBColor(91, 108, 108)
PAPER = RGBColor(248, 247, 241)
WHITE = RGBColor(255, 255, 255)
GREEN = RGBColor(32, 112, 91)
LIME = RGBColor(206, 222, 130)
CORAL = RGBColor(218, 111, 86)
GOLD = RGBColor(218, 174, 89)
PALE_GREEN = RGBColor(225, 237, 224)
PALE_CORAL = RGBColor(246, 228, 219)


def shape(slide, x, y, w, h, color, rounded=False):
    kind = MSO_SHAPE.ROUNDED_RECTANGLE if rounded else MSO_SHAPE.RECTANGLE
    item = slide.shapes.add_shape(kind, Inches(x), Inches(y), Inches(w), Inches(h))
    item.fill.solid()
    item.fill.fore_color.rgb = color
    item.line.fill.background()
    if rounded:
        item.adjustments[0] = 0.08
    return item


def text(slide, x, y, w, h, value, size=16, color=INK, bold=False,
         align=PP_ALIGN.LEFT, valign=MSO_ANCHOR.TOP):
    item = slide.shapes.add_textbox(Inches(x), Inches(y), Inches(w), Inches(h))
    frame = item.text_frame
    frame.clear()
    frame.word_wrap = True
    frame.margin_left = Inches(0.08)
    frame.margin_right = Inches(0.08)
    frame.margin_top = Inches(0.04)
    frame.vertical_anchor = valign
    for i, line in enumerate(value.split("\n")):
        paragraph = frame.paragraphs[0] if i == 0 else frame.add_paragraph()
        paragraph.text = line
        paragraph.alignment = align
        paragraph.space_after = Pt(6)
        for run in paragraph.runs:
            run.font.name = "Aptos"
            run.font.size = Pt(size)
            run.font.bold = bold
            run.font.color.rgb = color
    return item


def card(slide, x, y, w, h, heading, body, accent=GREEN):
    shape(slide, x, y, w, h, WHITE, True)
    shape(slide, x, y, 0.08, h, accent)
    text(slide, x + 0.22, y + 0.14, w - 0.36, 0.34, heading, 15, INK, True)
    text(slide, x + 0.22, y + 0.55, w - 0.36, h - 0.62, body, 12, MUTED)


def standard(title, section, number):
    slide = prs.slides.add_slide(prs.slide_layouts[6])
    slide.background.fill.solid()
    slide.background.fill.fore_color.rgb = PAPER
    shape(slide, 0, 0, 13.333, 0.12, GREEN)
    text(slide, 0.65, 0.34, 8, 0.25, section.upper(), 10, GREEN, True)
    text(slide, 0.65, 0.73, 12, 0.62, title, 27, INK, True)
    shape(slide, 0.65, 7.08, 12, 0.015, RGBColor(219, 221, 211))
    text(slide, 0.7, 7.12, 9.5, 0.2, "ROTAMA JAYA  |  PRESENTASI SIDANG", 8, MUTED, True)
    text(slide, 12.05, 7.1, 0.55, 0.22, f"{number:02d}", 9, MUTED, True, PP_ALIGN.RIGHT)
    return slide


def notes(slide, value):
    slide.notes_slide.notes_text_frame.text = value


# 1 Sampul
s = prs.slides.add_slide(prs.slide_layouts[6])
s.background.fill.solid()
s.background.fill.fore_color.rgb = INK
shape(s, 8.4, 0, 4.933, 7.5, GREEN)
shape(s, 9.05, 0.72, 3.3, 2.1, LIME, True)
shape(s, 9.55, 1.15, 2.3, 1.2, PAPER, True)
shape(s, 10.08, 1.5, 1.25, 0.48, CORAL, True)
text(s, 9.08, 3.2, 3.2, 1.2, "CNN + TF-IDF\nDUA JALUR TERPISAH", 14, INK, True, PP_ALIGN.CENTER, MSO_ANCHOR.MIDDLE)
text(s, 0.85, 0.72, 7.1, 0.3, "SIDANG SKRIPSI  /  ROTAMA JAYA", 11, LIME, True)
text(s, 0.85, 1.38, 7.1, 2.0, "Sistem Rekomendasi\nDesain Undangan Berbasis Web", 34, WHITE, True)
text(s, 0.88, 3.72, 6.9, 0.9, "MobileNetV2 untuk gambar referensi dan TF-IDF untuk kata kunci", 17, RGBColor(218, 228, 222))
text(s, 0.88, 5.35, 6.9, 0.8, "Nama: [Nama Mahasiswa]\nNIM: [NIM] | Program Studi: [Program Studi]", 13, WHITE)
text(s, 0.88, 6.55, 6.9, 0.3, "[Nama Universitas] · [Tahun]", 11, LIME, True)
notes(s, "Sesuaikan judul dengan naskah dan isi identitas dalam tanda kurung siku.")

# 2 Latar belakang
s = standard("Latar Belakang", "Konteks Penelitian", 2)
card(s, 0.8, 1.8, 5.55, 1.45, "Kebutuhan pelanggan", "Referensi visual atau tema dan kata kunci perlu dicocokkan dengan katalog.")
card(s, 0.8, 3.55, 5.55, 1.45, "Tantangan operasional", "Koleksi bertambah; pencarian manual membuat admin menelusuri desain satu per satu.", CORAL)
card(s, 6.8, 1.8, 5.55, 1.45, "Solusi", "Dua jalur rekomendasi terpisah: gambar referensi dan kata kunci.", GOLD)
card(s, 6.8, 3.55, 5.55, 1.45, "Lingkup", "Sistem membantu admin Percetakan Rotama Jaya, bukan checkout mandiri pelanggan.")
shape(s, 1, 5.6, 11.3, 0.8, PALE_GREEN, True)
text(s, 1.2, 5.78, 10.9, 0.4, "Fokus: menemukan desain relevan dan mendukung katalog serta pesanan.", 16, INK, True, PP_ALIGN.CENTER, MSO_ANCHOR.MIDDLE)

# 3 Rumusan masalah
s = standard("Rumusan Masalah dan Tujuan", "Fokus Penelitian", 3)
card(s, 0.8, 1.75, 5.55, 1.55, "01  Gambar referensi", "MobileNetV2 pralatih mengekstrak fitur untuk mengurutkan desain serupa.")
card(s, 6.8, 1.75, 5.55, 1.55, "02  Kata kunci", "TF-IDF mencocokkan kata kunci pelanggan dengan kata kunci desain.", CORAL)
card(s, 0.8, 3.55, 5.55, 1.55, "03  Sistem web admin", "Membangun jalur rekomendasi terpisah dan mengintegrasikan proses operasional.", GOLD)
card(s, 6.8, 3.55, 5.55, 1.55, "04  Evaluasi", "Mengukur Precision, Recall, dan F1-Score pada K=5 dan K=10.")
shape(s, 0.9, 5.6, 11.4, 0.8, PALE_CORAL, True)
text(s, 1.05, 5.78, 11.1, 0.4, "Catatan: implementasi TF-IDF menggunakan kolom kata_kunci, bukan deskripsi_desain.", 12, CORAL, True, PP_ALIGN.CENTER, MSO_ANCHOR.MIDDLE)

# 4 Alur metode
s = standard("Metode Penelitian", "Rancangan", 4)
stages = [("01", "Data", "Gambar, kata kunci, label", GREEN), ("02", "Ekstraksi", "MobileNetV2 / TF-IDF", CORAL), ("03", "Pencarian", "Cosine similarity", GOLD), ("04", "Evaluasi", "P/R/F1 pada K=5,10", GREEN)]
for i, (num, title, body, accent) in enumerate(stages):
    x = 0.78 + i * 3.12
    shape(s, x, 2.45, 2.5, 2.15, WHITE, True)
    shape(s, x, 2.45, 2.5, 0.12, accent)
    text(s, x + 0.18, 2.8, 2.1, 0.35, num, 16, accent, True)
    text(s, x + 0.18, 3.28, 2.12, 0.42, title, 17, INK, True)
    text(s, x + 0.18, 3.82, 2.12, 0.65, body, 12, MUTED)
    if i < 3:
        text(s, x + 2.58, 3.2, 0.42, 0.45, ">", 20, CORAL, True, PP_ALIGN.CENTER)
shape(s, 1, 5.25, 11.2, 0.85, PALE_GREEN, True)
text(s, 1.15, 5.45, 10.9, 0.4, "Relevansi tersimpan: kategori DAN tema sama dengan label skenario.", 14, INK, True, PP_ALIGN.CENTER, MSO_ANCHOR.MIDDLE)

# 5 Arsitektur
s = standard("Arsitektur Sistem", "Implementasi", 5)
for y, title, desc, accent in [(1.7, "Antarmuka Admin", "Pencarian gambar/teks · katalog · kategori · pesanan", GREEN), (3.05, "Aplikasi PHP", "Validasi dan penghubung ke Python serta MySQL", CORAL), (4.4, "Python Flask", "MobileNetV2 · TF-IDF · cosine similarity · evaluasi", GOLD)]:
    shape(s, 0.95, y, 8.6, 1, WHITE, True)
    shape(s, 0.95, y, 0.12, 1, accent)
    text(s, 1.3, y + 0.15, 2.5, 0.65, title, 16, INK, True, valign=MSO_ANCHOR.MIDDLE)
    text(s, 4.0, y + 0.15, 5.2, 0.65, desc, 12, MUTED, valign=MSO_ANCHOR.MIDDLE)
shape(s, 10, 1.9, 2.45, 2, PALE_GREEN, True)
text(s, 10.1, 2.1, 2.25, 1.6, "MySQL\n30 desain\n3 kategori\n2 pesanan", 15, INK, True, PP_ALIGN.CENTER, MSO_ANCHOR.MIDDLE)
shape(s, 10, 4.25, 2.45, 1.35, PALE_CORAL, True)
text(s, 10.1, 4.45, 2.25, 0.9, "30 skenario\nper metode", 16, INK, True, PP_ALIGN.CENTER, MSO_ANCHOR.MIDDLE)
text(s, 1, 6.1, 11.1, 0.35, "Operasional admin-only; tidak tersedia checkout atau akun pelanggan.", 12, MUTED)

# 6 Metode rekomendasi
s = standard("Dua Jalur Rekomendasi", "Metode Sistem", 6)
shape(s, 0.85, 1.75, 5.65, 4.55, WHITE, True)
shape(s, 0.85, 1.75, 5.65, 0.15, GREEN)
text(s, 1.22, 2.1, 4.8, 0.35, "A / GAMBAR REFERENSI", 13, GREEN, True)
text(s, 1.22, 2.65, 4.9, 0.55, "CNN MobileNetV2", 24, INK, True)
text(s, 1.22, 3.4, 4.85, 1.25, "Gambar dipraproses ke 128×128, diekstrak menjadi vektor, lalu dibandingkan dengan gambar desain menggunakan cosine similarity.", 13, MUTED)
shape(s, 1.2, 5.25, 4.85, 0.58, PALE_GREEN, True)
text(s, 1.3, 5.4, 4.65, 0.28, "30/30 vektor valid · 1280 fitur", 11, GREEN, True, PP_ALIGN.CENTER, MSO_ANCHOR.MIDDLE)
shape(s, 6.82, 1.75, 5.65, 4.55, WHITE, True)
shape(s, 6.82, 1.75, 5.65, 0.15, CORAL)
text(s, 7.2, 2.1, 4.8, 0.35, "B / KATA KUNCI", 13, CORAL, True)
text(s, 7.2, 2.65, 4.9, 0.55, "TF-IDF + Cosine", 24, INK, True)
text(s, 7.2, 3.4, 4.85, 1.25, "Kata kunci dipraproses, diubah menjadi bobot TF-IDF, lalu dicocokkan dengan vektor kata kunci tiap desain.", 13, MUTED)
shape(s, 7.18, 5.25, 4.85, 0.58, PALE_CORAL, True)
text(s, 7.28, 5.4, 4.65, 0.28, "30/30 vektor valid · 64 fitur", 11, CORAL, True, PP_ALIGN.CENTER, MSO_ANCHOR.MIDDLE)
text(s, 1, 6.48, 11.3, 0.25, "Pencarian TF-IDF menggunakan kata_kunci desain, bukan deskripsi_desain.", 11, CORAL, True)

# 7 Fitur admin
s = standard("Fitur Operasional Admin", "Implementasi Sistem", 7)
for i, (title, desc) in enumerate([("Cari dan pilih desain", "Jalankan salah satu jalur dan pilih hasil rekomendasi."), ("Kelola katalog", "Atur gambar, kata kunci, kategori, tema, file produksi."), ("Catat pesanan", "Simpan data pelanggan, jumlah, personalisasi, DP, status."), ("Pantau produksi", "Admin mengakses file produksi dan memperbarui status pesanan.")]):
    y = 1.75 + i * 1.08
    shape(s, 0.9, y, 11.55, 0.82, WHITE, True)
    shape(s, 1.15, y + 0.2, 0.42, 0.42, [GREEN, CORAL, GOLD, GREEN][i], True)
    text(s, 1.85, y + 0.12, 3, 0.55, title, 15, INK, True, valign=MSO_ANCHOR.MIDDLE)
    text(s, 4.75, y + 0.12, 7.2, 0.55, desc, 12, MUTED, valign=MSO_ANCHOR.MIDDLE)
text(s, 1, 6.4, 11.2, 0.28, "Database: 2 pesanan tercatat; keduanya terkait dengan desain.", 11, GREEN, True)

# 8 Evaluasi
s = standard("Rancangan Evaluasi", "Pengujian", 8)
shape(s, 0.85, 1.8, 5.65, 3.95, WHITE, True)
text(s, 1.2, 2.12, 4.9, 0.35, "METRIK TOP-K", 12, GREEN, True)
text(s, 1.2, 2.68, 4.85, 2.65, "Precision@K = relevan di Top-K / K\n\nRecall@K = relevan di Top-K / seluruh relevan\n\nF1@K = rata-rata harmonik Precision dan Recall", 14, INK)
shape(s, 6.82, 1.8, 5.65, 3.95, WHITE, True)
text(s, 7.18, 2.12, 4.9, 0.35, "PROTOKOL TERSIMPAN", 12, CORAL, True)
text(s, 7.18, 2.68, 4.8, 2.65, "CNN dan TF-IDF diuji terpisah\nK = 5 serta K = 10\nRelevansi: kategori dan tema sama\n30 skenario per metode", 14, INK)
shape(s, 0.85, 6.05, 11.62, 0.7, PALE_CORAL, True)
text(s, 1.05, 6.22, 11.2, 0.38, "Validasi: pastikan item kueri dikecualikan dan penyebut Recall konsisten.", 12, INK, True, PP_ALIGN.CENTER, MSO_ANCHOR.MIDDLE)

# 9 Hasil aktual
s = standard("Hasil Evaluasi yang Tersimpan", "Hasil dan Pembahasan", 9)
rows = [["Metode", "P@5", "P@10", "R@5", "R@10", "F1@5", "F1@10"], ["CNN MobileNetV2", "0.4067", "0.3133", "0.2646", "0.4306", "0.3126", "0.3490"], ["TF-IDF", "0.6200", "0.4467", "0.4717", "0.6417", "0.5056", "0.5010"]]
widths = [3.0, 1.27, 1.27, 1.27, 1.27, 1.27, 1.27]
for ri, row in enumerate(rows):
    x = 0.82
    for ci, value in enumerate(row):
        bg = GREEN if ri == 0 else (WHITE if ri == 1 else PALE_CORAL)
        fg = WHITE if ri == 0 else INK
        item = shape(s, x, 2.05 + ri * 0.78, widths[ci], 0.68, bg, True)
        frame = item.text_frame
        frame.clear()
        frame.vertical_anchor = MSO_ANCHOR.MIDDLE
        frame.paragraphs[0].text = value
        frame.paragraphs[0].alignment = PP_ALIGN.CENTER
        for run in frame.paragraphs[0].runs:
            run.font.name = "Aptos"
            run.font.size = Pt(12 if ci else 13)
            run.font.bold = ri == 0 or ci == 0
            run.font.color.rgb = fg
        x += widths[ci] + 0.06
shape(s, 1, 4.65, 11, 0.78, PALE_GREEN, True)
text(s, 1.15, 4.83, 10.7, 0.4, "30 skenario per metode; semua kolom metrik terisi.", 15, INK, True, PP_ALIGN.CENTER, MSO_ANCHOR.MIDDLE)
shape(s, 1, 5.65, 11, 0.8, PALE_CORAL, True)
text(s, 1.15, 5.82, 10.7, 0.45, "TF-IDF lebih tinggi pada enam rata-rata metrik di dataset/protokol ini.", 14, CORAL, True, PP_ALIGN.CENTER, MSO_ANCHOR.MIDDLE)
text(s, 0.95, 6.65, 11.4, 0.18, "Nilai 0–1; agregat database lokal saat diperiksa, dengan batasan evaluasi.", 9, MUTED)
notes(s, "CNN: P@5 0.4067, P@10 0.3133, R@5 0.2646, R@10 0.4306, F1@5 0.3126, F1@10 0.3490. TF-IDF: P@5 0.6200, P@10 0.4467, R@5 0.4717, R@10 0.6417, F1@5 0.5056, F1@10 0.5010. Batasi kesimpulan pada dataset dan prosedur ini.")

# 10 Jawaban rumusan
s = standard("Jawaban atas Rumusan Masalah", "Kesimpulan", 10)
answers = [("01", "Terimplementasi", "CNN MobileNetV2 mengekstrak fitur gambar."), ("02", "Dengan penyesuaian redaksi", "TF-IDF mencocokkan kueri dengan kata_kunci desain."), ("03", "Terimplementasi", "Web admin punya jalur gambar dan teks terpisah."), ("04", "Terintegrasi", "Katalog, kategori, pesanan, file produksi dikelola admin."), ("05", "Hasil tersedia", "30 skenario/metode; metrik P/R/F1 pada K=5 dan K=10.")]
for i, (num, status, body) in enumerate(answers):
    y = 1.7 + i * 0.94
    shape(s, 0.9, y, 11.5, 0.72, WHITE, True)
    text(s, 1.08, y + 0.12, 0.5, 0.4, num, 13, GREEN, True, valign=MSO_ANCHOR.MIDDLE)
    text(s, 1.75, y + 0.09, 3.2, 0.48, status, 13, INK, True, valign=MSO_ANCHOR.MIDDLE)
    text(s, 5, y + 0.08, 7.1, 0.5, body, 11, MUTED, valign=MSO_ANCHOR.MIDDLE)

# 11 Batasan
s = standard("Batasan dan Validasi Lanjutan", "Pembahasan Kritis", 11)
card(s, 0.85, 1.8, 5.55, 1.5, "A  Sumber teks", "Naskah menyebut deskripsi; kode memakai kata kunci.", CORAL)
card(s, 6.82, 1.8, 5.55, 1.5, "B  Ground truth", "Relevansi ditentukan kategori dan tema, bukan penilaian pengguna.", GOLD)
card(s, 0.85, 3.6, 5.55, 1.5, "C  Pengecualian kueri", "Pastikan kueri bukan hasil dan penyebut Recall konsisten.")
card(s, 6.82, 3.6, 5.55, 1.5, "D  Klaim hasil", "Angka tersimpan harus dibaca bersama protokol evaluasi.", CORAL)
shape(s, 1, 5.65, 11.2, 0.9, PALE_GREEN, True)
text(s, 1.15, 5.85, 10.9, 0.45, "Performa yang dilaporkan berlaku pada dataset dan definisi relevansi yang digunakan.", 14, INK, True, PP_ALIGN.CENTER, MSO_ANCHOR.MIDDLE)

# 12 Penutup
s = prs.slides.add_slide(prs.slide_layouts[6])
s.background.fill.solid()
s.background.fill.fore_color.rgb = GREEN
shape(s, 9.35, 0, 3.983, 7.5, INK)
shape(s, 9.85, 1.2, 2.95, 1.9, LIME, True)
shape(s, 10.32, 1.65, 2, 0.98, PAPER, True)
shape(s, 10.75, 1.95, 1.15, 0.4, CORAL, True)
text(s, 0.95, 1.6, 7.7, 1, "Terima Kasih", 40, WHITE, True)
text(s, 0.98, 3, 7, 0.65, "Diskusi dan pertanyaan", 21, LIME, True)
text(s, 0.98, 5.85, 7.5, 0.45, "[Nama Mahasiswa] · [Program Studi] · [Nama Universitas]", 13, WHITE)
notes(s, "Sampaikan kontribusi sistem ringkas, lalu persilakan penguji bertanya. Jelaskan batasan secara terbuka.")

prs.save(OUTPUT)
print(f"PPTX dibuat: {OUTPUT}")
print(f"Jumlah slide: {len(prs.slides)}")