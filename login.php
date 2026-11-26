<?php
/**
 * e-PKKM - HALAMAN DEPAN (PANEL MASUK).
 *
 * Permintaan pemilik (v1.11.0): "Halaman depan rubah tampilannya (tidak perlu landing page).
 * Contoh seperti gambar." Halaman ini sekarang berupa SATU layar dua kolom seperti contoh:
 *   - kiri  : identitas aplikasi, judul besar, empat kartu keunggulan, ilustrasi madrasah, dan
 *             baris manfaat di bagian bawah;
 *   - kanan : panel masuk putih (nama pengguna, kata sandi, tombol Masuk) + catatan versi.
 * Seluruh isi LANDING PAGE (slider, fitur, keunggulan, testimoni, kontak, formulir pesan) DIHAPUS.
 *
 * Halaman ini sekaligus tempat MASUK (login). Pendaftaran madrasah mandiri juga DIHAPUS
 * (permintaan pemilik v1.11.0): lembaga, kepala madrasah, dan pengawas ditarik Super Admin dari
 * aplikasi Supervisi Guru lewat menu "Tarik dari Supervisi" (`tarik_supervisi.php`). Halaman
 * pendaftaran yang tersisa hanya `daftar_penilai.php` — khusus jabatan SELAIN pengawas.
 */
require_once __DIR__ . '/inc/init.php';

if (current_user()) {
    redirect('index.php');
}

$galat = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) {
        tolak_csrf();
    }
    $username = trim((string)($_POST['username'] ?? ''));
    $sandi = (string)($_POST['password'] ?? '');
    if ($username === '' || $sandi === '') {
        $galat = 'Nama pengguna dan kata sandi wajib diisi.';
    } else {
        $u = one('SELECT * FROM users WHERE username = ?', [$username]);
        if (!$u || !password_verify($sandi, $u['sandi'])) {
            $galat = 'Nama pengguna atau kata sandi salah.';
            // v1.15.0 (Log Aktivitas, permintaan pemilik): percobaan masuk gagal ikut dicatat
            // supaya percobaan mencurigakan terpantau di menu Log Aktivitas Super Admin.
            log_gagal(null, $username, 'Nama pengguna atau kata sandi salah');
        } elseif (!(int)$u['aktif']) {
            $galat = 'Akun ini dinonaktifkan. Hubungi Super Admin.';
            log_gagal($u, $username, 'Akun dinonaktifkan');
        } else {
            $s = null;
            if ($u['role'] === 'admin') {
                $s = one('SELECT * FROM sekolah WHERE id = ?', [(int)$u['sekolah_id']]);
                if (!$s) {
                    $galat = 'Akun Anda belum ditautkan ke madrasah. Hubungi Super Admin.';
                    log_gagal($u, $username, 'Akun belum ditautkan ke madrasah');
                } elseif (daftar_status_sekolah($s) !== 'disetujui') {
                    // sisa data pendaftaran lama: belum boleh masuk sebelum disetujui Admin aplikasi
                    $galat = daftar_pesan_masuk($s);
                    log_gagal($u, $username, 'Madrasah belum disetujui');
                } elseif (!(int)$s['aktif']) {
                    $galat = 'Madrasah Anda sedang dinonaktifkan. Hubungi Super Admin.';
                    log_gagal($u, $username, 'Madrasah dinonaktifkan');
                }
            }
            if ($galat === '') {
                mulai_sesi();
                session_regenerate_id(true);
                $_SESSION['uid'] = (int)$u['id'];
                unset($_SESSION['ctx_sekolah_id']);
                // v1.15.0: daftarkan sebagai pengguna ONLINE + catat aktivitas "masuk".
                log_sesi_mulai($u);
                redirect('index.php');
            }
        }
    }
}

?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Masuk - <?= e(APP_NAME) ?> | <?= e(APP_SUB) ?></title>
<meta name="description" content="<?= e(APP_NAME . ' - ' . APP_SUB) ?>">
<link rel="stylesheet" href="assets/style.css?v=<?= e(APP_VERSION) ?>">
<link rel="icon" href="data:image/svg+xml,<?= rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><rect width="24" height="24" fill="#0b4a33"/><path d="M12 4l8 5v11H4V9z" fill="#c9a227"/></svg>') ?>">
</head>
<body class="dp">

<div class="dp-layar">

    <!-- ============================ kolom kiri: identitas ============================ -->
    <section class="dp-kiri" id="beranda">
        <header class="dp-merek">
            <span class="dp-lambang" aria-hidden="true">
                <svg viewBox="0 0 48 48" width="46" height="46" role="img">
                    <circle cx="24" cy="24" r="23" fill="#ffffff" stroke="#0b4a33" stroke-width="2"/>
                    <path d="M24 9l13 8v17H11V17z" fill="#0b4a33"/>
                    <path d="M24 15c3 2 5 3 8 4v11h-5v-6h-6v6h-5V19c3-1 5-2 8-4z" fill="#c9a227"/>
                    <circle cx="24" cy="12" r="2" fill="#c9a227"/>
                </svg>
            </span>
            <span class="dp-merek-teks">
                <b><?= e(APP_NAME) ?></b>
                <i><?= e(APP_SUB) ?></i>
            </span>
        </header>

        <h1 class="dp-judul">Sistem Penilaian Kinerja<br><span>Kepala Madrasah yang Modern</span></h1>
        <?php /* v1.13.2 (permintaan pemilik): kalimat slogan di bawah judul DIHAPUS. */ ?>

        <div class="dp-gambar"><?= depan_slider_html() ?></div>

        <div class="dp-kaki">
            <ul class="dp-manfaat" aria-label="Manfaat aplikasi">
                <li><span><?= ikon('dokumen', 18) ?></span><b>Terintegrasi</b><i>Data lembaga ditarik dari aplikasi Supervisi</i></li>
                <li><span><?= ikon('clock', 18) ?></span><b>Efisien</b><i>Proses penilaian cepat menghemat waktu</i></li>
                <li><span><?= ikon('buka', 18) ?></span><b>Akses Mudah</b><i>Dibuka kapan saja dan di mana saja</i></li>
                <li><span><?= ikon('centang', 18) ?></span><b>Transparan</b><i>Nilai jelas bagi madrasah dan penilai</i></li>
            </ul>

            <div class="dp-lencana" aria-hidden="true">
                <span class="dp-lencana-ikon">
                    <svg viewBox="0 0 48 48" width="34" height="34">
                        <path d="M24 7l14 9v20H10V16z" fill="#0b4a33"/>
                        <path d="M16 22h16v3H16zM16 28h16v3H16z" fill="#c9a227"/>
                    </svg>
                </span>
                <span class="dp-lencana-teks"><b><?= e(APP_NAME) ?></b><i><?= e(APP_SUB) ?></i></span>
            </div>
        </div>

        <?php /* v1.13.1 (permintaan pemilik): empat kartu keunggulan dipindah ke BAWAH baris
                 Terintegrasi / Efisien / Akses Mudah / Transparan, tepat setelah slider gambar. */ ?>
        <ul class="dp-ciri" aria-label="Keunggulan aplikasi">
            <li>
                <span class="dp-ciri-ikon"><?= ikon('nilai', 24) ?></span>
                <b>Instrumen Lengkap</b>
                <i>109 butir penilaian<br>Tahun Pertama–Keempat</i>
            </li>
            <li>
                <span class="dp-ciri-ikon"><?= ikon('guru', 24) ?></span>
                <b>7 Penilai Tahun Keempat</b>
                <i>Pengawas, Guru, Tendik,<br>Komite, Yayasan/Kabid</i>
            </li>
            <li>
                <span class="dp-ciri-ikon"><?= ikon('rekap', 24) ?></span>
                <b>Rekap &amp; Cetak</b>
                <i>NKKM, rekap resmi,<br>unduh Word siap cetak</i>
            </li>
            <li>
                <span class="dp-ciri-ikon"><?= ikon('kunci', 24) ?></span>
                <b>Aman &amp; Terpercaya</b>
                <i>Data tiap madrasah<br>terpisah dan terkunci</i>
            </li>
        </ul>
    </section>

    <!-- ============================ kolom kanan: panel masuk ============================ -->
    <section class="dp-kanan">
        <div class="dp-panel" id="masuk">
            <?php /* v1.17.0 (permintaan pemilik): (a) tempat logo aplikasi BULAT di atas judul —
                     logo diunggah Super Admin lewat menu Layanan → Logo Aplikasi, dan alamat aslinya
                     TIDAK dicetak ke HTML (dialirkan gambar.php?j=logo, sama seperti gambar halaman
                     depan); (b) judul + sub judul + kalimat "Silakan login" dibuat RATA TENGAH. */ ?>
            <?= logo_app_tag() ?>
            <div class="dp-panel-kepala">
                <h2 class="dp-panel-judul"><?= e(APP_NAME) ?></h2>
                <p class="dp-panel-sub"><?= e(APP_SUB) ?></p>
                <p class="dp-panel-ket">Silakan login menggunakan akun Anda</p>
            </div>

            <?php if ($galat !== ''): ?>
                <div class="alert alert-err" id="galatMasuk"><?= e($galat) ?></div>
            <?php endif; ?>

            <form method="post" action="login.php#masuk" class="dp-form" autocomplete="on">
                <?= csrf_field() ?>
                <div class="dp-medan">
                    <span class="dp-medan-ikon" aria-hidden="true"><?= ikon('guru', 18) ?></span>
                    <input type="text" id="username" name="username" required autocomplete="username"
                           placeholder="Nama pengguna" aria-label="Nama pengguna"
                           value="<?= e($_POST['username'] ?? '') ?>">
                </div>
                <div class="dp-medan">
                    <span class="dp-medan-ikon" aria-hidden="true"><?= ikon('kunci', 18) ?></span>
                    <input type="password" id="password" name="password" required autocomplete="current-password"
                           placeholder="Kata sandi" aria-label="Kata sandi">
                    <button class="dp-mata" type="button" data-sandi aria-label="Tampilkan kata sandi" aria-pressed="false">
                        <svg class="dp-mata-buka" viewBox="0 0 24 24" width="18" height="18" fill="none"
                             stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M2 12s3.6-6 10-6 10 6 10 6-3.6 6-10 6-10-6-10-6z"/>
                            <circle cx="12" cy="12" r="2.6"/>
                        </svg>
                        <svg class="dp-mata-tutup" viewBox="0 0 24 24" width="18" height="18" fill="none"
                             stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" hidden>
                            <path d="M3 3l18 18"/>
                            <path d="M10.6 6.2A10.9 10.9 0 0 1 12 6c6.4 0 10 6 10 6a17 17 0 0 1-3.1 3.7"/>
                            <path d="M6.3 7.9A16.6 16.6 0 0 0 2 12s3.6 6 10 6a10.6 10.6 0 0 0 4.2-.8"/>
                        </svg>
                    </button>
                </div>

                <button class="btn btn-lebar dp-tombol" type="submit">Masuk</button>
            </form>

            <p class="dp-lupa">Lupa kata sandi? Hubungi <b>Super Admin</b> aplikasi.</p>

            <div class="dp-pendaftaran">
                <div class="dp-pendaftaran-kepala"><?= ikon('user', 16) ?> <b>Belum punya akun?</b></div>
                <p>Hanya jabatan <b>selain pengawas</b> (Guru, Tendik, Komite, Kasie, Kabid/Yayasan) yang
                    mendaftar sendiri. Akun <b>kepala madrasah</b> dan <b>pengawas</b> otomatis diambil dari
                    data aplikasi Supervisi Guru.</p>
                <a class="btn btn-ghost btn-kecil btn-lebar" href="daftar_penilai.php" id="btnPendaftaranPenilai">
                    Pendaftaran Penilai
                </a>
            </div>

            <p class="dp-versi"><?= e(APP_NAME) ?> Versi <?= e(APP_VERSION) ?> &middot; <?= e(APP_SUB) ?></p>
            <p class="dp-penulis">Dikembangkan oleh <?= e(APP_AUTHOR) ?> (<?= e(APP_AUTHOR_JABATAN) ?>)</p>
        </div>
    </section>
</div>

<script src="assets/app.js?v=<?= e(APP_VERSION) ?>"></script>
</body>
</html>
