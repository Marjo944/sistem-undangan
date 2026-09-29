<?php
/**
 * PDF_SEDERHANA.PHP
 * Pembuat berkas PDF minimal TANPA dependensi/pustaka pihak ketiga (tanpa composer),
 * supaya fitur "Ekspor PDF" pada Laporan Pengujian tetap bisa berjalan di hosting mana
 * pun tanpa perlu instalasi tambahan. Mendukung: teks (font Helvetica biasa/bold),
 * garis, dan penataan otomatis ke halaman baru saat konten sudah penuh.
 *
 * Cara pakai singkat:
 *   $pdf = new PdfSederhana();
 *   $pdf->tambahHalaman();
 *   $pdf->setFont(true, 16);
 *   $pdf->teks(50, 800, 'Judul Laporan');
 *   ... 
 *   header('Content-Type: application/pdf');
 *   header('Content-Disposition: attachment; filename="laporan.pdf"');
 *   echo $pdf->keluarkan();
 */
class PdfSederhana
{
    private array $objek = [];          // isi tiap objek PDF (indeks 0 => objek nomor 1, dst.)
    private array $idKontenHalaman = []; // nomor objek stream konten tiap halaman
    private string $kontenSaatIni = '';
    private float $lebarHalaman = 595.28;  // A4 dalam poin
    private float $tinggiHalaman = 841.89;
    private string $fontAktif = 'F1';
    private int|float $ukuranFont = 10;

    /** Batas margin bawah yang aman sebelum harus pindah ke halaman baru. */
    public float $batasBawah = 50;
    public float $mulaiY = 800;

    private function objekBaru(string $isi): int
    {
        $this->objek[] = $isi;
        return count($this->objek); // nomor objek 1-based
    }

    private function escape(string $s): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $s);
    }

    public function tambahHalaman(): void
    {
        if ($this->kontenSaatIni !== '') {
            $this->simpanHalamanSaatIni();
        }
        $this->kontenSaatIni = '';
    }

    private function simpanHalamanSaatIni(): void
    {
        $panjang = strlen($this->kontenSaatIni);
        $isi = "<< /Length $panjang >>\nstream\n" . $this->kontenSaatIni . "endstream";
        $this->idKontenHalaman[] = $this->objekBaru($isi);
    }

    public function setFont(bool $tebal = false, int|float $ukuran = 10): void
    {
        $this->fontAktif = $tebal ? 'F2' : 'F1';
        $this->ukuranFont = $ukuran;
    }

    /** Font monospace (Courier) — dipakai supaya kolom tabel teks tetap rata sejajar. */
    public function setFontMono(int|float $ukuran = 8): void
    {
        $this->fontAktif = 'F3';
        $this->ukuranFont = $ukuran;
    }

    public function teks(float $x, float $y, string $s): void
    {
        $this->kontenSaatIni .= sprintf(
            "BT /%s %d Tf %F %F Td (%s) Tj ET\n",
            $this->fontAktif, $this->ukuranFont, $x, $y, $this->escape($s)
        );
    }

    public function garis(float $x1, float $y1, float $x2, float $y2, float $lebar = 0.5): void
    {
        $this->kontenSaatIni .= sprintf("%F w %F %F m %F %F l S\n", $lebar, $x1, $y1, $x2, $y2);
    }

    public function persegiIsi(float $x, float $y, float $lebar, float $tinggi, string $warnaHex = 'F2ECDF'): void
    {
        [$r, $g, $b] = $this->hexKeRgb($warnaHex);
        $this->kontenSaatIni .= sprintf("%F %F %F rg %F %F %F %F re f\n", $r, $g, $b, $x, $y, $lebar, $tinggi);
    }

    private function hexKeRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        return [
            hexdec(substr($hex, 0, 2)) / 255,
            hexdec(substr($hex, 2, 2)) / 255,
            hexdec(substr($hex, 4, 2)) / 255,
        ];
    }

    /**
     * Menulis satu baris teks pada posisi Y berjalan ($y dilewatkan dengan referensi),
     * otomatis pindah ke halaman baru kalau sudah mendekati batas bawah.
     */
    public function tulisBaris(float $x, float &$y, string $s, bool $tebal = false, int|float $ukuran = 10): void
    {
        if ($y < $this->batasBawah) {
            $this->tambahHalaman();
            $y = $this->mulaiY;
        }
        $this->setFont($tebal, $ukuran);
        $this->teks($x, $y, $s);
        $y -= ($ukuran + 6);
    }

    /** Sama seperti tulisBaris(), tapi memakai font monospace (Courier) agar kolom tabel rata. */
    public function tulisBarisMono(float $x, float &$y, string $s, int|float $ukuran = 8): void
    {
        if ($y < $this->batasBawah) {
            $this->tambahHalaman();
            $y = $this->mulaiY;
        }
        $this->setFontMono($ukuran);
        $this->teks($x, $y, $s);
        $y -= ($ukuran + 5);
    }

    public function keluarkan(): string
    {
        if ($this->kontenSaatIni !== '' || empty($this->idKontenHalaman)) {
            $this->simpanHalamanSaatIni();
        }

        $font1 = $this->objekBaru('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>');
        $font2 = $this->objekBaru('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>');
        $font3 = $this->objekBaru('<< /Type /Font /Subtype /Type1 /BaseFont /Courier >>');

        // Nomor objek Pages dipesan LEBIH DULU (isi kosong sementara) supaya objek
        // Page bisa langsung menunjuk ke nomor yang benar tanpa perlu placeholder teks.
        $nomorPages = $this->objekBaru('');

        $idHalaman = [];
        foreach ($this->idKontenHalaman as $idKonten) {
            $isi = sprintf(
                '<< /Type /Page /Parent %d 0 R /MediaBox [0 0 %F %F] /Resources << /Font << /F1 %d 0 R /F2 %d 0 R /F3 %d 0 R >> >> /Contents %d 0 R >>',
                $nomorPages, $this->lebarHalaman, $this->tinggiHalaman, $font1, $font2, $font3, $idKonten
            );
            $idHalaman[] = $this->objekBaru($isi);
        }

        $kids = implode(' ', array_map(fn($n) => "$n 0 R", $idHalaman));
        $this->objek[$nomorPages - 1] = sprintf('<< /Type /Pages /Kids [ %s ] /Count %d >>', $kids, count($idHalaman));

        $nomorKatalog = $this->objekBaru(sprintf('<< /Type /Catalog /Pages %d 0 R >>', $nomorPages));

        $keluaran = "%PDF-1.4\n";
        $offset = [0];

        foreach ($this->objek as $i => $isi) {
            $offset[] = strlen($keluaran);
            $keluaran .= ($i + 1) . " 0 obj\n$isi\nendobj\n";
        }

        $offsetXref = strlen($keluaran);
        $jumlah = count($this->objek) + 1;
        $xref = "xref\n0 $jumlah\n0000000000 65535 f \n";
        for ($i = 1; $i < $jumlah; $i++) {
            $xref .= sprintf("%010d 00000 n \n", $offset[$i]);
        }
        $keluaran .= $xref;
        $keluaran .= "trailer\n<< /Size $jumlah /Root $nomorKatalog 0 R >>\nstartxref\n$offsetXref\n%%EOF";

        return $keluaran;
    }
}
