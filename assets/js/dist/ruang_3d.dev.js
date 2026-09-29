"use strict";

(function () {
  'use strict';

  function mulaiPanggung(panggung) {
    if (!window.THREE) return;
    var gambar = [];

    try {
      gambar = JSON.parse(panggung.dataset.threeImages || '[]');
    } catch (error) {
      return;
    }

    if (!gambar.length) return;
    var canvas = document.createElement('canvas');
    panggung.appendChild(canvas);
    var renderer;

    try {
      renderer = new THREE.WebGLRenderer({
        canvas: canvas,
        antialias: true,
        alpha: true
      });
    } catch (error) {
      return;
    }

    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 1.5));
    renderer.outputColorSpace = THREE.SRGBColorSpace;
    var scene = new THREE.Scene();
    var camera = new THREE.PerspectiveCamera(28, 1, 0.1, 100);
    camera.position.set(0, 0.15, 7.4);
    scene.add(new THREE.HemisphereLight(0xfff8ed, 0x6b1e2b, 2.2));
    var lampu = new THREE.DirectionalLight(0xffffff, 2.6);
    lampu.position.set(-3, 4, 5);
    scene.add(lampu);
    var kelompok = new THREE.Group();
    scene.add(kelompok);
    var pemuat = new THREE.TextureLoader();
    var kartu = [];
    gambar.forEach(function (url, index) {
      var tekstur = pemuat.load(url);
      tekstur.colorSpace = THREE.SRGBColorSpace;
      var material = new THREE.MeshStandardMaterial({
        map: tekstur,
        roughness: 0.5,
        metalness: 0.04
      });
      var bidang = new THREE.Mesh(new THREE.PlaneGeometry(2.15, 2.9), material);
      bidang.position.set((index - 1) * 1.32, index === 1 ? 0.12 : -0.08, index === 1 ? 0.45 : 0);
      bidang.rotation.y = (index - 1) * -0.22;
      bidang.rotation.z = (index - 1) * -0.035;
      kelompok.add(bidang);
      kartu.push(bidang);
    });
    var lebar = 0;
    var tinggi = 0;
    var pointerX = 0;
    var pointerY = 0;
    var targetX = 0;
    var targetY = 0;
    var diam = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function ubahUkuran() {
      lebar = panggung.clientWidth;
      tinggi = panggung.clientHeight;
      if (!lebar || !tinggi) return;
      renderer.setSize(lebar, tinggi, false);
      camera.aspect = lebar / tinggi;
      camera.updateProjectionMatrix();
    }

    panggung.addEventListener('pointermove', function (event) {
      var kotak = panggung.getBoundingClientRect();
      pointerX = ((event.clientX - kotak.left) / kotak.width - 0.5) * 2;
      pointerY = ((event.clientY - kotak.top) / kotak.height - 0.5) * 2;
    });
    panggung.addEventListener('pointerleave', function () {
      pointerX = 0;
      pointerY = 0;
    });
    window.addEventListener('resize', ubahUkuran, {
      passive: true
    });
    ubahUkuran();

    function gambarFrame(waktu) {
      targetX += (pointerX - targetX) * 0.045;
      targetY += (pointerY - targetY) * 0.045;
      kelompok.rotation.y = targetX * 0.16 + (diam ? 0 : Math.sin(waktu * 0.00035) * 0.06);
      kelompok.rotation.x = targetY * -0.07;
      kartu.forEach(function (bidang, index) {
        bidang.position.y += ((index === 1 ? 0.12 : -0.08) + (diam ? 0 : Math.sin(waktu * 0.001 + index) * 0.035) - bidang.position.y) * 0.04;
      });
      renderer.render(scene, camera);
      window.requestAnimationFrame(gambarFrame);
    }

    window.requestAnimationFrame(gambarFrame);
  }

  function mulai() {
    document.querySelectorAll('[data-three-showcase]').forEach(mulaiPanggung);
    document.querySelectorAll('[data-tilt-card]').forEach(function (kartu) {
      if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
      kartu.addEventListener('pointermove', function (event) {
        var kotak = kartu.getBoundingClientRect();
        var x = (event.clientX - kotak.left) / kotak.width - 0.5;
        var y = (event.clientY - kotak.top) / kotak.height - 0.5;
        kartu.style.transform = 'perspective(900px) rotateX(' + (-y * 5).toFixed(2) + 'deg) rotateY(' + (x * 5).toFixed(2) + 'deg) translateY(-6px)';
      });
      kartu.addEventListener('pointerleave', function () {
        kartu.style.transform = '';
      });
    });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', mulai);else mulai();
})();
//# sourceMappingURL=ruang_3d.dev.js.map
