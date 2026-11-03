<?php
/**
 * e-PKKM - HALAMAN DEPAN (LANDING PAGE) + PANEL MASUK.
 *
 * Permintaan pemilik: "Halaman depan buat landing page seperti Aplikasi Supervisi".
 * Halaman ini memuat: bilah navigasi, beranda (slider + panel masuk + empat kotak statistik),
 * fitur aplikasi, keunggulan, testimoni, tentang aplikasi, dan kontak (info + tombol WhatsApp +
 * formulir pesan). Seluruh isinya diisi Super Admin lewat menu "Halaman Depan (Landing)"
 * (berkas `landing.php`); nilai bawaannya ada di `inc/landing.php`.
 *
 * Halaman ini sekaligus tempat MASUK (login) dan menerima PESAN KONTAK pengunjung.
 */

require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/landing.php';

if (current_user()) {
    redirect('index.php');
}

$galat = '';
$pesanGalat = [];
$nilaiPesan = ['nama' => '', 'kontak' => '', 'pesan' => ''];

// pengaman sederhana formulir kontak
if (empty($_SESSION['landing_captcha'])) {
    $_SESSION['landing_captcha'] = ['a' => random_int(2, 9), 'b' => random_int(2, 9)];
}
$cap = $_SESSION['landing_captcha'];
$terkirim = isset($_GET['terkirim']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) {
        tolak_csrf();
    }
    $aksi = (string)($_POST['aksi'] ?? 'masuk');

    // ----------------------------------------------------------------- kirim pesan kontak
    if ($aksi === 'pesan') {
        $nilaiPesan['nama'] = landing_potong((string)($_POST['nama'] ?? ''), LANDING_NAMA_MAKS);
        $nilaiPesan['kontak'] = landing_potong((string)($_POST['kontak'] ?? ''), 120);
        $nilaiPesan['pesan'] = landing_potong((string)($_POST['pesan'] ?? ''), LANDING_PESAN_MAKS);
        $jawab = trim((string)($_POST['captcha'] ?? ''));

        if ((int)($_SESSION['landing_pesan_jumlah'] ?? 0) >= LANDING_MAKS_SESI) {
            $pesanGalat[] = 'Pengiriman pesan dari sesi ini sudah mencapai batas (' . LANDING_MAKS_SESI . ' kali).';
        }
        if ($nilaiPesan['nama'] === '') {
            $pesanGalat[] = 'Nama wajib diisi.';
        }
        if ($nilaiPesan['kontak'] === '') {
            $pesanGalat[] = 'Email atau nomor WhatsApp wajib diisi.';
        }
        if (potong_teks($nilaiPesan['pesan'], 1000) === '' || strlen($nilaiPesan['pesan']) < 5) {
            $pesanGalat[] = 'Tuliskan pesan Anda (minimal 5 karakter).';
        }
        if ($jawab === '' || (int)$jawab !== ((int)$cap['a'] + (int)$cap['b'])) {
            $pesanGalat[] = 'Jawaban pengaman (captcha) salah.';
        }
        if (!$pesanGalat) {
            try {
                q('INSERT INTO landing_pesan (nama, kontak, pesan, ip, dibaca, dibuat) VALUES (?,?,?,?,0,?)',
                    [$nilaiPesan['nama'], $nilaiPesan['kontak'], $nilaiPesan['pesan'],
                        permintaan_ip(), date('Y-m-d H:i:s')]);
                $_SESSION['landing_pesan_jumlah'] = (int)($_SESSION['landing_pesan_jumlah'] ?? 0) + 1;
                unset($_SESSION['landing_captcha']);
                flash('Terima kasih, ' . $nilaiPesan['nama'] . '. Pesan Anda sudah terkirim dan akan dibalas Admin aplikasi.');
                redirect('login.php?terkirim=1#kontak');
            } catch (Throwable $t) {
                $pesanGalat[] = 'Pesan gagal disimpan. Coba lagi sebentar lagi.';
            }
        }
        $_SESSION['landing_captcha'] = ['a' => random_int(2, 9), 'b' => random_int(2, 9)];
        $cap = $_SESSION['landing_captcha'];
    } else {
        // --------------------------------------------------------------------- masuk aplikasi
        $username = trim((string)($_POST['username'] ?? ''));
        $sandi = (string)($_POST['password'] ?? '');
        if ($username === '' || $sandi === '') {
            $galat = 'Nama pengguna dan kata sandi wajib diisi.';
        } else {
            $u = one('SELECT * FROM users WHERE username = ?', [$username]);
            if (!$u || !password_verify($sandi, $u['sandi'])) {
                $galat = 'Nama pengguna atau kata sandi salah.';
            } elseif (!(int)$u['aktif']) {
                $galat = 'Akun ini dinonaktifkan. Hubungi Super Admin.';
            } else {
                $s = null;
                if ($u['role'] === 'admin') {
                    $s = one('SELECT * FROM sekolah WHERE id = ?', [(int)$u['sekolah_id']]);
                    if (!$s) {
                        $galat = 'Akun Anda belum ditautkan ke madrasah. Hubungi Super Admin.';
                    } elseif (daftar_status_sekolah($s) !== 'disetujui') {
                        // pendaftar baru: belum boleh masuk sebelum disetujui/diaktifkan Admin aplikasi
                        $galat = daftar_pesan_masuk($s);
                    } elseif (!(int)$s['aktif']) {
                        $galat = 'Madrasah Anda sedang dinonaktifkan. Hubungi Super Admin.';
                    }
                }
                if ($galat === '') {
                    mulai_sesi();
                    session_regenerate_id(true);
                    $_SESSION['uid'] = (int)$u['id'];
                    unset($_SESSION['ctx_sekolah_id']);
                    redirect('index.php');
                }
            }
        }
    }
}

// ---------------------------------------------------------------- isi halaman depan
$slides = landing_list('slider');
$fitur = landing_list('fitur');
$unggulan = landing_list('unggulan');
$stats = landing_stat_list();
$waTautan = landing_wa_tautan('Assalamualaikum, saya ingin bertanya tentang aplikasi ' . APP_NAME . '.');
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e(APP_NAME) ?> - <?= e(APP_SUB) ?></title>
<meta name="description" content="<?= e(APP_NAME . ' - ' . APP_SUB) ?>">
<link rel="stylesheet" href="assets/style.css?v=<?= e(APP_VERSION) ?>">
<link rel="icon" href="data:image/svg+xml,<?= rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><rect width="24" height="24" fill="#0b4a33"/><path d="M12 4l8 5v11H4V9z" fill="#c9a227"/></svg>') ?>">
</head>
<body class="bg-deco lp">

<!-- ============================ bilah navigasi ============================ -->
<header class="lp-nav" id="lpNav">
    <div class="lp-nav-inner">
        <a class="lp-brand" href="#beranda">
            <span class="lp-brand-ikon"><?= landing_ikon('school', 22) ?></span>
            <span><b><?= e(APP_NAME) ?></b><i><?= e(APP_SUB) ?></i></span>
        </a>
        <button class="lp-nav-tombol" type="button" data-lp-menu aria-label="Buka menu"><?= ikon('menu', 20) ?></button>
        <nav class="lp-nav-menu" id="lpMenu">
            <a href="#beranda">Beranda</a>
            <a href="#fitur">Fitur</a>
            <a href="#keunggulan">Keunggulan</a>
            <a href="daftar.php">Pendaftaran</a>
            <a href="daftar_penilai.php">Pendaftaran Penilai</a>
            <a href="#kontak">Kontak</a>
            <a class="lp-nav-masuk" href="#masuk">Masuk</a>
        </nav>
    </div>
</header>

<!-- ================================ beranda =============================== -->
<section class="lp-sec lp-hero" id="beranda">
    <div class="lp-wrap">
        <p class="lp-label lp-hero-head"><?= e(landing_teks('label')) ?></p>

        <div class="lp-hero-grid">
            <div class="lp-hero-kiri">

                <?php if ($slides): ?>
                    <div class="lp-slider" data-lp-slider data-otomatis="6000">
                        <div class="lp-slider-track">
                            <?php foreach ($slides as $i => $s):
                                $gambar = landing_slider_gambar($s); ?>
                                <figure class="lp-slide">
                                    <div class="lp-slide-gambar<?= $gambar === '' ? ' lp-slide-kosong' : '' ?>">
                                        <?php if ($gambar !== ''): ?>
                                            <img src="<?= e($gambar) ?>" alt="<?= e($s['judul']) ?>" loading="lazy">
                                        <?php else: ?>
                                            <?= landing_ikon('medal', 60) ?>
                                        <?php endif; ?>
                                        <figcaption class="lp-slide-teks">
                                            <?php if ($i === 0): ?>
                                                <h1><?= e($s['judul']) ?></h1>
                                            <?php else: ?>
                                                <h2><?= e($s['judul']) ?></h2>
                                            <?php endif; ?>
                                            <p><?= nl2br(e($s['keterangan'])) ?></p>
                                        </figcaption>
                                    </div>
                                </figure>
                            <?php endforeach; ?>
                        </div>
                        <?php if (count($slides) > 1): ?>
                            <div class="lp-slider-nav" hidden>
                                <button type="button" data-lp-prev aria-label="Slide sebelumnya">&#8249;</button>
                                <span class="lp-slider-titik" data-lp-titik></span>
                                <button type="button" data-lp-next aria-label="Slide berikutnya">&#8250;</button>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- ------------------------ PANEL MASUK ------------------------ -->
            <div class="lp-hero-kanan">
                <div class="card lp-login" id="masuk">
                    <h2>Masuk Aplikasi</h2>
                    <p class="ket">Khusus Super Admin, kepala madrasah, dan penilai (pengawas, Kabag,
                        Kasie, Guru, Komite).</p>
                    <?php if ($galat !== ''): ?>
                        <div class="alert alert-err"><?= e($galat) ?></div>
                    <?php endif; ?>
                    <form method="post" action="login.php#masuk">
                        <?= csrf_field() ?>
                        <input type="hidden" name="aksi" value="masuk">
                        <div class="form-baris">
                            <label for="username">Nama Pengguna</label>
                            <input type="text" id="username" name="username" required
                                   value="<?= e($_POST['username'] ?? '') ?>">
                        </div>
                        <div class="form-baris">
                            <label for="password">Kata Sandi</label>
                            <input type="password" id="password" name="password" required>
                        </div>
                        <button class="btn btn-lebar" type="submit">Masuk</button>
                    </form>
                    <!-- FITUR PENDAFTARAN: MADRASAH + PENILAI (menggantikan keterangan lama pada panel masuk) -->
                    <div class="lp-daftar-dua">
                        <div class="lp-daftar-kotak lp-login-catatan" id="kotakPendaftaran">
                            <div class="lp-daftar-kotak-kepala"><?= landing_ikon('school', 20) ?>
                                <b>Pendaftaran Madrasah</b></div>
                            <p class="mb0 kecil">Belum punya akun? Daftarkan madrasah Anda &mdash; isi identitas lembaga dan
                                akun kepala madrasah, lalu tunggu persetujuan Admin aplikasi.</p>
                            <a class="btn btn-emas btn-kecil btn-lebar mt8" href="daftar.php"
                               id="btnPendaftaran"><?= landing_ikon('user', 16) ?> Pendaftaran Madrasah</a>
                        </div>
                        <div class="lp-daftar-kotak lp-login-catatan" id="kotakPendaftaranPenilai">
                            <div class="lp-daftar-kotak-kepala"><?= landing_ikon('user', 20) ?>
                                <b>Pendaftaran Penilai</b></div>
                            <p class="mb0 kecil">Pengawas, Kabag, Kasie, Guru, atau Komite yang bertugas menilai?
                                Daftarkan akun penilai &mdash; nama pengguna dan kata sandi tampil setelah mendaftar.</p>
                            <a class="btn btn-emas btn-kecil btn-lebar mt8" href="daftar_penilai.php"
                               id="btnPendaftaranPenilai"><?= landing_ikon('user', 16) ?> Pendaftaran Penilai</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($stats): ?>
            <div class="lp-stat">
                <?php foreach ($stats as $st): ?>
                    <div class="lp-stat-kotak lp-3d">
                        <b><?= e($st['angka']) ?></b>
                        <span><?= e($st['label']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- ================================ fitur ================================= -->
<?php if ($fitur): ?>
<section class="lp-sec" id="fitur">
    <div class="lp-wrap">
        <h2 class="lp-judul"><?= e(landing_teks('fitur_judul')) ?></h2>
        <p class="lp-sub"><?= e(landing_teks('fitur_sub')) ?></p>
        <div class="lp-grid lp-grid-4">
            <?php foreach ($fitur as $f): ?>
                <article class="card lp-kartu lp-3d">
                    <span class="lp-kartu-ikon"><?= landing_ikon(landing_ikon_sah((string)$f['ikon']), 26) ?></span>
                    <h3><?= e($f['judul']) ?></h3>
                    <p><?= e($f['teks']) ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ============================== keunggulan ============================== -->
<?php if ($unggulan): ?>
<section class="lp-sec lp-alt" id="keunggulan">
    <div class="lp-wrap">
        <h2 class="lp-judul"><?= e(landing_teks('unggulan_judul')) ?></h2>
        <p class="lp-sub"><?= e(landing_teks('unggulan_sub')) ?></p>
        <div class="lp-grid lp-grid-3">
            <?php foreach ($unggulan as $g): ?>
                <article class="card lp-kartu lp-3d">
                    <span class="lp-kartu-ikon"><?= landing_ikon(landing_ikon_sah((string)$g['ikon']), 26) ?></span>
                    <h3><?= e($g['judul']) ?></h3>
                    <p><?= e($g['teks']) ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ================================ kontak ================================ -->
<section class="lp-sec" id="kontak">
    <div class="lp-wrap">
        <h2 class="lp-judul"><?= e(landing_teks('kontak_judul')) ?></h2>
        <p class="lp-sub"><?= e(landing_teks('kontak_sub')) ?></p>

        <?php if ($terkirim): ?>
            <div class="alert alert-ok">Pesan Anda sudah terkirim. Terima kasih.</div>
        <?php endif; ?>

        <div class="lp-kontak-grid">
            <div class="card lp-kontak lp-3d">
                <h3>Informasi Kontak</h3>
                <?php if (trim(landing_teks('kontak_alamat')) !== ''): ?>
                    <p class="lp-kontak-baris"><?= landing_ikon('lokasi', 18) ?><span><?= e(landing_teks('kontak_alamat')) ?></span></p>
                <?php endif; ?>
                <?php if (trim(landing_teks('kontak_telepon')) !== ''): ?>
                    <p class="lp-kontak-baris"><?= landing_ikon('telepon', 18) ?><span><?= e(landing_teks('kontak_telepon')) ?></span></p>
                <?php endif; ?>
                <?php if (trim(landing_teks('kontak_email')) !== ''): ?>
                    <p class="lp-kontak-baris"><?= landing_ikon('email', 18) ?><span><?= e(landing_teks('kontak_email')) ?></span></p>
                <?php endif; ?>
                <?php if (trim(landing_teks('kontak_jam')) !== ''): ?>
                    <p class="lp-kontak-baris"><?= landing_ikon('clock', 18) ?><span><?= e(landing_teks('kontak_jam')) ?></span></p>
                <?php endif; ?>
                <p><?= e(landing_teks('kontak_ajakan')) ?></p>
                <?php if ($waTautan !== ''): ?>
                    <a class="btn btn-emas" href="<?= e($waTautan) ?>" target="_blank" rel="noopener noreferrer">
                        <?= landing_ikon('chat', 18) ?> Hubungi lewat WhatsApp
                    </a>
                <?php endif; ?>
            </div>

            <div class="card lp-kontak-pesan lp-3d">
                <h3>Kirim Pesan</h3>
                <?php if ($pesanGalat): ?>
                    <div class="alert alert-err">
                        <ul class="mb0"><?php foreach ($pesanGalat as $p): ?><li><?= e($p) ?></li><?php endforeach; ?></ul>
                    </div>
                <?php endif; ?>
                <form method="post" action="login.php#kontak">
                    <?= csrf_field() ?>
                    <input type="hidden" name="aksi" value="pesan">
                    <div class="form-2">
                        <div><label for="pesan_nama">Nama</label>
                            <input type="text" id="pesan_nama" name="nama" required value="<?= e($nilaiPesan['nama']) ?>"></div>
                        <div><label for="pesan_kontak">Email / Nomor WhatsApp</label>
                            <input type="text" id="pesan_kontak" name="kontak" required value="<?= e($nilaiPesan['kontak']) ?>"></div>
                    </div>
                    <div class="form-baris mt8">
                        <label for="isiPesan">Pesan</label>
                        <textarea id="isiPesan" name="pesan" required><?= e($nilaiPesan['pesan']) ?></textarea>
                    </div>
                    <div class="form-baris">
                        <label for="captchaPesan">Pengaman: berapa <?= (int)$cap['a'] ?> + <?= (int)$cap['b'] ?> ?</label>
                        <input type="text" id="captchaPesan" name="captcha" required inputmode="numeric" autocomplete="off">
                    </div>
                    <button class="btn" type="submit">Kirim Pesan</button>
                    <p class="kecil mut mt8 mb0">Maksimal <?= LANDING_MAKS_SESI ?> pesan per sesi.</p>
                </form>
            </div>
        </div>
    </div>
</section>

<!-- ================================ footer =============================== -->
<footer class="lp-foot">
    <div class="lp-wrap lp-foot-isi">
        <p class="mb0"><?= e(APP_NAME) ?> Versi <?= e(APP_VERSION) ?> &middot; <?= e(APP_SUB) ?></p>
        <p class="mb0 kecil mut">Dikembangkan oleh <?= e(APP_AUTHOR) ?> (<?= e(APP_AUTHOR_JABATAN) ?>)</p>
    </div>
</footer>

<script src="assets/app.js?v=<?= e(APP_VERSION) ?>"></script>
</body>
</html>
