/* e-PKKM - skrip pelengkap (halaman tetap bekerja tanpa JavaScript) */
(function () {
    'use strict';

    // ---- menu HP ----
    function initMenu() {
        var t = document.querySelector('[data-menu]');
        if (!t) { return; }
        t.addEventListener('click', function () {
            document.body.classList.toggle('menu-on');
        });
        document.addEventListener('click', function (ev) {
            if (!document.body.classList.contains('menu-on')) { return; }
            var s = document.getElementById('side');
            if (s && (s.contains(ev.target) || t.contains(ev.target))) { return; }
            document.body.classList.remove('menu-on');
        });
        document.addEventListener('keydown', function (ev) {
            if (ev.key === 'Escape' && document.body.classList.contains('menu-on')) {
                document.body.classList.remove('menu-on');
            }
        });
    }

    // ---- konfirmasi aksi berbahaya (data-confirm) ----
    function initConfirm() {
        document.querySelectorAll('[data-confirm]').forEach(function (el) {
            el.addEventListener('click', function (ev) {
                if (!window.confirm(el.getAttribute('data-confirm'))) {
                    ev.preventDefault();
                    ev.stopPropagation();
                }
            });
        });
    }

    // ---- pilih madrasah otomatis (data-auto pada <select>) ----
    function initAuto() {
        document.querySelectorAll('select[data-auto]').forEach(function (sel) {
            sel.addEventListener('change', function () {
                var f = sel.form;
                if (f) { f.submit(); }
            });
        });
    }

    // ---- skor hidup pada lembar penilaian ----
    function initSkorHidup() {
        var area = document.querySelector('[data-skor]');
        if (!area) { return; }
        var jf = parseInt(area.getAttribute('data-maks') || '4', 10);
        var bagEl = document.querySelectorAll('[data-bagian]');

        function hitungBagian(bagian) {
            var jml = 0, n = 0;
            bagian.querySelectorAll('input[type=radio]:checked').forEach(function (r) {
                jml += parseInt(r.value, 10) || 0;
                n++;
            });
            var total = bagian.querySelectorAll('input[type=radio]').length;
            var butir = total / jf;
            if (n === 0) { return null; }
            return jml / (jf * n) * 100;
        }

        function segarkan() {
            var semua = [];
            bagEl.forEach(function (bagian) {
                var v = hitungBagian(bagian);
                var out = document.querySelector('[data-nilai="' + bagian.getAttribute('data-bagian') + '"]');
                if (out) {
                    out.textContent = v === null ? '-' : v.toFixed(2).replace('.', ',');
                }
                if (v !== null) { semua.push(v); }
            });
            var el = document.querySelector('[data-nilai-total]');
            if (el) {
                el.textContent = semua.length ? (semua.reduce(function (a, b) { return a + b; }, 0) / semua.length).toFixed(2).replace('.', ',') : '-';
            }
            var pred = document.querySelector('[data-predikat]');
            if (pred) {
                var n = semua.length ? semua.reduce(function (a, b) { return a + b; }, 0) / semua.length : null;
                var label = '-', kelas = 'pil-kosong';
                if (n !== null) {
                    if (n >= 91) { label = 'Amat Baik'; kelas = 'pil-unggul'; }
                    else if (n >= 81) { label = 'Baik'; kelas = 'pil-baik'; }
                    else if (n >= 71) { label = 'Cukup'; kelas = 'pil-cukup'; }
                    else { label = 'Kurang'; kelas = 'pil-kurang'; }
                }
                pred.textContent = label;
                pred.className = 'pil ' + kelas;
            }
            var maju = document.querySelector('[data-terisi]');
            if (maju) {
                var n2 = area.querySelectorAll('input[type=radio]:checked').length;
                var t2 = area.querySelectorAll('input[type=radio]').length / jf;
                maju.textContent = n2 + ' dari ' + t2 + ' butir terisi';
            }
        }

        area.addEventListener('change', function (ev) {
            if (ev.target && ev.target.type === 'radio') { segarkan(); }
        });
        segarkan();
    }

    // ---- halaman depan (landing page): menu HP + slider beranda + slider testimoni ----
    function pasangSlider(wadah, opt) {
        if (!wadah) { return null; }
        var gulir = (opt && opt.gulir) ? opt.gulir : wadah;
        var anak = gulir.children;
        if (!anak || anak.length < 2) { return null; }
        var titikWadah = wadah.querySelector('[data-lp-titik]');
        var nav = wadah.querySelector('.lp-slider-nav, .lp-testi-nav');
        var jeda = parseInt(wadah.getAttribute('data-otomatis') || '0', 10);
        var halaman = function () {
            var langkah = (opt && opt.langkah) ? opt.langkah() : 1;
            return Math.max(1, Math.ceil(anak.length / langkah));
        };
        var posisi = function () {
            var lebar = anak[0].getBoundingClientRect().width + 16;
            var langkah = (opt && opt.langkah) ? opt.langkah() : 1;
            var perHalaman = lebar * langkah;
            return perHalaman > 0 ? Math.round(gulir.scrollLeft / perHalaman) : 0;
        };
        var ke = function (i) {
            var lebar = anak[0].getBoundingClientRect().width + 16;
            var langkah = (opt && opt.langkah) ? opt.langkah() : 1;
            var n = halaman();
            if (i < 0) { i = n - 1; }
            if (i >= n) { i = 0; }
            gulir.scrollTo({ left: i * lebar * langkah, behavior: 'smooth' });
        };
        var gambarTitik = function () {
            if (!titikWadah) { return; }
            var n = halaman();
            if (titikWadah.children.length !== n) {
                titikWadah.innerHTML = '';
                for (var i = 0; i < n; i++) {
                    (function (idx) {
                        var s = document.createElement('span');
                        s.addEventListener('click', function () { ke(idx); });
                        titikWadah.appendChild(s);
                    })(i);
                }
            }
            var aktif = Math.min(posisi(), n - 1);
            Array.prototype.forEach.call(titikWadah.children, function (s, i) {
                s.className = (i === aktif) ? 'on' : '';
            });
        };
        if (nav) { nav.hidden = false; }
        var prev = wadah.querySelector('[data-lp-prev]');
        var next = wadah.querySelector('[data-lp-next]');
        if (prev) { prev.addEventListener('click', function () { ke(posisi() - 1); }); }
        if (next) { next.addEventListener('click', function () { ke(posisi() + 1); }); }
        gulir.addEventListener('scroll', function () { window.requestAnimationFrame(gambarTitik); });
        window.addEventListener('resize', gambarTitik);
        gambarTitik();

        var timer = null;
        var salah = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (jeda > 0 && !salah) {
            var jalan = function () {
                if (timer) { return; }
                timer = window.setInterval(function () {
                    if (document.hidden) { return; }
                    ke(posisi() + 1);
                }, jeda);
            };
            var henti = function () {
                if (timer) { window.clearInterval(timer); timer = null; }
            };
            wadah.addEventListener('mouseenter', henti);
            wadah.addEventListener('mouseleave', jalan);
            wadah.addEventListener('focusin', henti);
            wadah.addEventListener('focusout', jalan);
            wadah.addEventListener('touchstart', henti, { passive: true });
            document.addEventListener('visibilitychange', function () { document.hidden ? henti() : jalan(); });
            jalan();
        }
        return { ke: ke, segarkan: gambarTitik };
    }

    function initLanding() {
        var nav = document.getElementById('lpNav');
        var tombol = document.querySelector('[data-lp-menu]');
        if (nav && tombol) {
            tombol.addEventListener('click', function () { nav.classList.toggle('lp-on'); });
            nav.querySelectorAll('.lp-nav-menu a').forEach(function (a) {
                a.addEventListener('click', function () { nav.classList.remove('lp-on'); });
            });
        }
        document.querySelectorAll('[data-lp-slider]').forEach(function (w) {
            pasangSlider(w, { gulir: w.querySelector('.lp-slider-track') });
        });
        document.querySelectorAll('[data-lp-testi]').forEach(function (w) {
            pasangSlider(w, {
                gulir: w.querySelector('.lp-testi-track'),
                langkah: function () { return window.innerWidth > 760 ? 2 : 1; }
            });
        });
    }

    /**
     * Kartu halaman depan dibuat 3 dimensi (permintaan pemilik).
     * - kartu miring mengikuti kursor (maks 8°) lewat variabel --lp-rx/--lp-ry,
     *   sorotan --lp-sx/--lp-sy mengikuti posisi kursor;
     * - klik → kelas .lp-klik (animasi memantul) lalu dilepas setelah 520 ms;
     * - masuk berurutan → kelas .lp-masuk dengan jeda bertingkat, DILEPAS saat animationend
     *   (kalau tidak, animasi "masuk" ikut terulang setiap .lp-klik dilepas).
     * CATATAN: pelurusan memakai `pointerleave`, BUKAN `pointerout`, karena `pointerout`
     * juga menyala saat kursor berpindah antar-anak di dalam kartu sehingga kemiringan
     * hilang di tengah jalan.
     */
    function initKartu3D() {
        var kartu = document.querySelectorAll('.lp-3d');
        if (!kartu.length) { return; }
        var bisaHover = window.matchMedia && window.matchMedia('(hover: hover)').matches;
        var hematGerak = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        var MAKS = 8; // derajat kemiringan maksimum

        // animasi masuk berurutan
        if (!hematGerak) {
            kartu.forEach(function (k, i) {
                k.style.setProperty('--lp-d', (i * 55) + 'ms');
                k.classList.add('lp-masuk');
                k.addEventListener('animationend', function lepas(ev) {
                    if (ev.animationName === 'lpMuncul') {
                        k.classList.remove('lp-masuk');
                        k.removeEventListener('animationend', lepas);
                    }
                });
            });
        }

        if (bisaHover && !hematGerak) {
            kartu.forEach(function (k) {
                k.addEventListener('pointermove', function (ev) {
                    var r = k.getBoundingClientRect();
                    if (!r.width || !r.height) { return; }
                    var px = (ev.clientX - r.left) / r.width;   // 0..1
                    var py = (ev.clientY - r.top) / r.height;   // 0..1
                    var ry = (px - .5) * 2 * MAKS;              // kiri/kanan
                    var rx = (.5 - py) * 2 * MAKS;              // atas/bawah
                    k.style.setProperty('--lp-ry', ry.toFixed(2) + 'deg');
                    k.style.setProperty('--lp-rx', rx.toFixed(2) + 'deg');
                    k.style.setProperty('--lp-sx', (px * 100).toFixed(1) + '%');
                    k.style.setProperty('--lp-sy', (py * 100).toFixed(1) + '%');
                });
                k.addEventListener('pointerleave', function () {
                    k.style.setProperty('--lp-ry', '0deg');
                    k.style.setProperty('--lp-rx', '0deg');
                    k.style.setProperty('--lp-sx', '50%');
                    k.style.setProperty('--lp-sy', '0%');
                });
            });
        }

        // efek klik (didelegasikan supaya tetap bekerja untuk kartu yang isinya tautan/tombol).
        // Klik pada ISIAN FORMULIR tidak memantulkan kartu - kalau ikut memantul, kartu Kontak
        // akan terasa goyang setiap kali pengunjung mengklik kolom isian untuk mengetik.
        document.addEventListener('click', function (ev) {
            var t = ev.target && ev.target.closest ? ev.target.closest('.lp-3d') : null;
            if (!t || hematGerak) { return; }
            if (ev.target.closest('input, textarea, select, label')) { return; }
            t.classList.remove('lp-klik');
            // paksa reflow supaya animasi bisa dijalankan ulang
            void t.offsetWidth;
            t.classList.add('lp-klik');
            window.setTimeout(function () { t.classList.remove('lp-klik'); }, 520);
        });
    }


    /**
     * Formulir PENDAFTARAN MADRASAH bertahap (halaman `daftar.php`).
     * Hanya PELENGKAP: tanpa JavaScript seluruh langkah tetap tampil & formulir tetap bisa dikirim
     * (atribut `hidden` pada pengalih langkah dihapus skrip ini saat aktif).
     * Aturan: langkah berikutnya hanya bisa dibuka bila isian wajib pada langkah sekarang terisi,
     * sehingga pendaftar tidak melewati bagian penting.
     */
    function initDaftar() {
        var form = document.querySelector('[data-daftar]');
        if (!form) { return; }
        var nav = form.querySelector('[data-df-nav]');
        var langkah = Array.prototype.slice.call(form.querySelectorAll('[data-df-langkah]'));
        var tombol = Array.prototype.slice.call(form.querySelectorAll('[data-df-ke]'));
        var maju = form.querySelector('[data-df-maju]');
        var mundur = form.querySelector('[data-df-mundur]');
        if (!nav || !langkah.length || !tombol.length || !maju || !mundur) { return; }

        var kini = 0;
        nav.hidden = false;
        maju.hidden = false;
        mundur.hidden = false;

        var tampil = function (i) {
            kini = Math.max(0, Math.min(langkah.length - 1, i));
            langkah.forEach(function (l, n) { l.hidden = n !== kini; });
            tombol.forEach(function (t, n) {
                t.classList.toggle('on', n === kini);
                t.classList.toggle('selesai', n < kini);
            });
            mundur.hidden = kini === 0;
            maju.hidden = kini === langkah.length - 1;
            // ringkasan pada langkah terakhir
            if (kini === langkah.length - 1) {
                form.querySelectorAll('[data-df-r]').forEach(function (el) {
                    var nama = el.getAttribute('data-df-r');
                    var isi = form.querySelector('[name="' + nama + '"]');
                    if (!isi) { el.textContent = '—'; return; }
                    // daftar pilihan (mis. Status Lembaga) -> tampilkan LABEL pilihannya
                    if (isi.tagName === 'SELECT') {
                        var opt = isi.options[isi.selectedIndex];
                        el.textContent = opt && opt.text.trim() !== '' ? opt.text.trim() : '—';
                        return;
                    }
                    el.textContent = isi.value.trim() !== '' ? isi.value.trim() : '—';
                });
            }
        };

        var wajibLangkah = function (i) {
            var ok = true;
            langkah[i].querySelectorAll('input[required]').forEach(function (el) {
                if (!el.checkValidity()) {
                    ok = false;
                    el.reportValidity();
                }
            });
            return ok;
        };

        maju.addEventListener('click', function () { if (wajibLangkah(kini)) { tampil(kini + 1); } });
        mundur.addEventListener('click', function () { tampil(kini - 1); });
        tombol.forEach(function (t, n) {
            t.addEventListener('click', function () {
                if (n > kini && !wajibLangkah(kini)) { return; }
                tampil(n);
            });
        });
        form.addEventListener('keydown', function (ev) {
            if (ev.key === 'Enter' && ev.target && ev.target.tagName === 'INPUT' && kini < langkah.length - 1) {
                ev.preventDefault();
                if (wajibLangkah(kini)) { tampil(kini + 1); }
            }
        });

        tampil(0);
    }

    /**
     * Halaman PENILAI: centang madrasah + pilihan posisi (Penilai 1 / 2).
     * Hanya PELENGKAP: tanpa JavaScript formulir tetap bisa dikirim (server mengabaikan centang
     * tanpa posisi). Di sini posisi hanya dapat dipilih setelah madrasahnya dicentang, dan posisi
     * yang sudah dipegang penilai lain (data-terkunci) tetap tidak dapat dipilih.
     */
    function initPilihPenilai() {
        var form = document.getElementById('formPilihMadrasah');
        if (!form) { return; }
        var baris = Array.prototype.slice.call(form.querySelectorAll('[data-pilih-baris]'));
        if (!baris.length) { return; }

        var sinkron = function (tr) {
            var cek = tr.querySelector('[data-pilih-cek]');
            var radio = Array.prototype.slice.call(tr.querySelectorAll('[data-pilih-radio]'));
            if (!cek || !radio.length) { return; }
            radio.forEach(function (r) {
                var terkunci = r.getAttribute('data-terkunci') === '1';
                if (!cek.checked || terkunci) {
                    r.disabled = true;
                } else {
                    r.disabled = false;
                }
            });
            if (cek.checked && !radio.some(function (r) { return r.checked && !r.disabled; })) {
                var bebas = radio.filter(function (r) { return !r.disabled; });
                if (bebas.length) { bebas[0].checked = true; }
            }
        };

        baris.forEach(function (tr) {
            var cek = tr.querySelector('[data-pilih-cek]');
            sinkron(tr);
            if (cek) {
                cek.addEventListener('change', function () { sinkron(tr); });
            }
            Array.prototype.slice.call(tr.querySelectorAll('[data-pilih-radio]')).forEach(function (r) {
                r.addEventListener('change', function () { sinkron(tr); });
            });
        });
    }

    /**
     * GRAFIK DASHBOARD KEPALA MADRASAH (permintaan pemilik):
     *  - grafik capaian nilai setiap tahun (batang + garis NKKM), dan
     *  - grafik nilai berdasarkan tugas utama (batang berkelompok per tahun).
     * Data dibaca dari <script type="application/json" id="dataGrafikPKKM">, jadi angka tetap
     * dihitung di server. Tanpa JavaScript (atau bila Chart.js gagal dimuat) tabel angka di
     * halaman tetap terbaca — grafiknya hanya pelengkap.
     */
    function initGrafikPKKM() {
        var data = document.getElementById('dataGrafikPKKM');
        if (!data || typeof window.Chart === 'undefined') { return; }
        var isi;
        try { isi = JSON.parse(data.textContent || '{}'); } catch (e) { return; }

        var cs = window.getComputedStyle(document.body);
        var warna = function (nama, cadangan) {
            var v = (cs.getPropertyValue(nama) || '').trim();
            return v === '' ? cadangan : v;
        };
        var hijau = warna('--hijau-tua', '#0b4a33');
        var hijau2 = warna('--hijau', '#14764f');
        var emas = warna('--emas', '#c9a227');
        var garis = 'rgba(11,74,51,.12)';

        window.Chart.defaults.font.family = '"Segoe UI", system-ui, -apple-system, Arial, sans-serif';
        window.Chart.defaults.font.size = 12;
        window.Chart.defaults.color = '#17251f';

        var elTahun = document.getElementById('chNilaiTahun');
        if (elTahun && isi.tahun) {
            new window.Chart(elTahun, {
                type: 'bar',
                data: {
                    labels: isi.tahun.labels || [],
                    datasets: [
                        {
                            label: 'Nilai kinerja per tahun',
                            data: isi.tahun.nilai || [],
                            backgroundColor: 'rgba(11,74,51,.82)',
                            borderColor: hijau,
                            borderWidth: 1,
                            borderRadius: 6,
                            maxBarThickness: 56,
                            order: 2
                        },
                        {
                            label: 'NKKM (empat tahunan)',
                            type: 'line',
                            data: isi.tahun.nkkn_garis || [],
                            borderColor: emas,
                            backgroundColor: emas,
                            borderWidth: 2,
                            borderDash: [6, 4],
                            pointRadius: 0,
                            pointHoverRadius: 4,
                            fill: false,
                            order: 1
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom', labels: { boxWidth: 12, usePointStyle: true } },
                        tooltip: {
                            callbacks: {
                                label: function (ctx) {
                                    var v = ctx.parsed.y;
                                    if (v === null || typeof v === 'undefined') { return ctx.dataset.label + ': -'; }
                                    return ctx.dataset.label + ': ' + v.toFixed(2).replace('.', ',');
                                }
                            }
                        }
                    },
                    scales: {
                        y: { beginAtZero: true, max: 100, ticks: { stepSize: 20 }, grid: { color: garis },
                             title: { display: true, text: 'Nilai (0-100)' } },
                        x: { grid: { display: false } }
                    }
                }
            });
        }

        var elTugas = document.getElementById('chTugasUtama');
        if (elTugas && isi.tugas) {
            var palet = [
                'rgba(11,74,51,.85)', 'rgba(20,118,79,.85)',
                'rgba(201,162,39,.9)', 'rgba(109,27,37,.8)'
            ];
            var set = (isi.tugas.datasets || []).map(function (d, i) {
                return {
                    label: d.label,
                    data: d.data,
                    backgroundColor: palet[i % palet.length],
                    borderRadius: 4,
                    maxBarThickness: 34
                };
            });
            new window.Chart(elTugas, {
                type: 'bar',
                data: { labels: isi.tugas.labels || [], datasets: set },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom', labels: { boxWidth: 12, usePointStyle: true } },
                        tooltip: {
                            callbacks: {
                                label: function (ctx) {
                                    var v = ctx.parsed.y;
                                    if (v === null || typeof v === 'undefined') { return ctx.dataset.label + ': -'; }
                                    return ctx.dataset.label + ': ' + v.toFixed(2).replace('.', ',');
                                }
                            }
                        }
                    },
                    scales: {
                        y: { beginAtZero: true, max: 100, ticks: { stepSize: 20 }, grid: { color: garis },
                             title: { display: true, text: 'Nilai (0-100)' } },
                        x: { grid: { display: false } }
                    }
                }
            });
        }
    }

    // ---- tandai baris tabel yang bisa diklik ----
    document.addEventListener('DOMContentLoaded', function () {
        initMenu();
        initConfirm();
        initAuto();
        initSkorHidup();
        initLanding();
        initKartu3D();
        initDaftar();
        initPilihPenilai();
        initGrafikPKKM();
    });
})();
