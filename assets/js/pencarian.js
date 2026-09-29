/**
 * PENCARIAN.JS
 * Menampilkan overlay loading saat form pencarian (kata kunci atau gambar)
 * dikirim, karena proses TF-IDF / CNN MobileNetV2 di layanan Python bisa
 * makan waktu beberapa detik dan sebelumnya halaman tidak memberi umpan
 * balik apa pun (terlihat seperti macet).
 *
 * Dipakai bersama oleh beranda.php, cari_teks.php, dan cari_gambar.php.
 * Aman dipasang di halaman mana pun — kalau id form yang dicari tidak ada
 * di halaman itu, fungsi ini diam saja (tidak melakukan apa-apa).
 */
(function () {
    function buatOverlay() {
        var overlayAda = document.getElementById('overlayMemuat');
        if (overlayAda) return overlayAda;

        var overlay = document.createElement('div');
        overlay.id = 'overlayMemuat';
        overlay.className = 'overlay-memuat';
        overlay.innerHTML =
            '<div class="kotak-muat">' +
                '<div class="spinner-muat"></div>' +
                '<p class="teks-muat-utama">Mencari desain yang paling cocok...</p>' +
                '<p class="teks-muat-sub" id="teksMuatSub"></p>' +
            '</div>';
        document.body.appendChild(overlay);
        return overlay;
    }

    function tampilkanOverlay(teksSub) {
        var overlay = buatOverlay();
        var elTeksSub = document.getElementById('teksMuatSub');
        if (elTeksSub) elTeksSub.textContent = teksSub || '';
        // requestAnimationFrame supaya transisi opacity CSS sempat "kepakai"
        // (elemen baru dibuat lalu langsung diberi class dalam 1 frame yang
        // sama tidak akan ter-animasi oleh sebagian browser).
        requestAnimationFrame(function () {
            overlay.classList.add('tampil');
        });
    }

    function pasangFormPencarian(idForm, teksSub) {
        var form = document.getElementById(idForm);
        if (!form) return;

        form.addEventListener('submit', function () {
            // Submit event HTML5 hanya terpicu setelah validasi bawaan browser
            // (mis. atribut "required") lolos, jadi di titik ini form memang
            // benar-benar akan dikirim ke server.
            var tombolKirim = form.querySelector('button[type="submit"]');
            if (tombolKirim) tombolKirim.disabled = true; // cegah klik ganda / kirim dobel
            tampilkanOverlay(teksSub);
        });
    }

    pasangFormPencarian('formTeks', 'Menganalisis kata kunci dengan TF-IDF + Kesamaan Kosinus...');
    pasangFormPencarian('formGambar', 'Menganalisis gambar dengan CNN MobileNetV2...');
})();
