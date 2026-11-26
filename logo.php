<?php
/**
 * e-PKKM - LOGO APLIKASI (khusus Super Admin) — v1.17.0.
 *
 * Permintaan pemilik: tempat logo aplikasi pada panel masuk dibuat **bulat** dan disesuaikan
 * ukurannya. Logo diunggah di sini (bentuk bundar paling pas), disimpan di media proxy platform —
 * basis data hanya menyimpan URL-nya (`app_pengaturan.logo_app`).
 *
 * ⚠️ Halaman MASUK tidak mencetak alamat CDN logo: logo dialirkan `gambar.php?j=logo` (server-side)
 * sehingga tetap sejalan dengan aturan "gambar halaman depan tidak bisa dibuka sumbernya"
 * (v1.14.0). Pratinjau pada halaman ini (khusus Super Admin) boleh memakai alamat aslinya.
 */
require_once __DIR__ . '/inc/init.php';
$u = require_super();

$aksi = (string)($_POST['aksi'] ?? '');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) {
        tolak_csrf();
    }
    // PHP membuang seluruh $_POST/$_FILES bila kiriman melebihi post_max_size.
    if (!$_POST && !$_FILES && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        flash('Ukuran kiriman melebihi batas server (' . ini_get('post_max_size')
            . '). Perkecil gambar lalu ulangi.', 'err');
        redirect('logo.php');
    }

    if ($aksi === 'unggah') {
        $berkas = is_array($_FILES['logo'] ?? null) ? $_FILES['logo'] : [];
        if ((int)($berkas['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            flash('Pilih berkas logo terlebih dahulu.', 'err');
            redirect('logo.php');
        }
        if ((int)($berkas['error'] ?? 0) !== UPLOAD_ERR_OK) {
            flash('Berkas gagal diunggah (kode ' . (int)($berkas['error'] ?? 0)
                . '). Perkecil berkasnya lalu ulangi.', 'err');
            redirect('logo.php');
        }
        $nama = (string)($berkas['name'] ?? 'logo');
        $tmp = (string)($berkas['tmp_name'] ?? '');
        $cek = validasi_foto($nama, (int)($berkas['size'] ?? 0), $tmp);
        if (!$cek['ok']) {
            flash($cek['pesan'], 'err');
            redirect('logo.php');
        }
        $bytes = @file_get_contents($tmp);
        if ($bytes === false || $bytes === '') {
            flash('Berkas logo tidak dapat dibaca.', 'err');
            redirect('logo.php');
        }
        $hasil = media_upload((string)$bytes, $nama);
        if (empty($hasil['ok'])) {
            flash((string)($hasil['pesan'] ?? 'Unggahan gagal.'), 'err');
            redirect('logo.php');
        }
        app_setting_set('logo_app', (string)$hasil['url']);
        app_setting_set('logo_app_dibuat', date('Y-m-d H:i:s'));
        flash('Logo aplikasi tersimpan dan langsung tampil bulat pada panel masuk.');
        redirect('logo.php');
    }

    if ($aksi === 'hapus') {
        $lama = logo_app_url();
        app_setting_set('logo_app', '');
        flash($lama !== ''
            ? 'Logo aplikasi dihapus. Panel masuk kembali memakai lencana bawaan.'
            : 'Belum ada logo unggahan yang perlu dihapus.');
        redirect('logo.php');
    }

    flash('Aksi tidak dikenal.', 'err');
    redirect('logo.php');
}

$logo = logo_app_url();
$dibuat = app_setting('logo_app_dibuat');
$maksFoto = FOTO_MAKS_BYTE;

page_top('Logo Aplikasi', 'Logo bulat pada panel masuk halaman depan aplikasi.', true);
?>
<div class="card" id="cardLogo">
    <div class="card-kepala"><h2><?= ikon('buka', 18) ?> Logo Aplikasi</h2>
        <div class="aksi">
            <a class="btn btn-ghost btn-kecil" href="login.php" target="_blank" rel="noopener">
                <?= ikon('buka', 15) ?> Lihat Halaman Masuk</a>
        </div>
    </div>
    <p class="ket">Logo tampil <b>bulat</b> di atas judul pada panel masuk. Unggah gambar
        <b>berbentuk bundar</b> (PNG/JPG/WEBP/SVG, maks
        <?= e(number_format($maksFoto / 1048576, 0, ',', '.')) ?> MB) — gambar persegi akan dipotong
        mengikuti lingkaran, jadi bagian tengah gambar yang paling penting. Logo dipanggil lewat
        aplikasi sendiri sehingga alamat sumbernya tidak terlihat pengunjung.</p>
</div>

<div class="grid g2">
    <div class="card" id="formLogo">
        <div class="card-kepala"><h2><?= $logo !== '' ? 'Ganti Logo' : 'Unggah Logo' ?></h2></div>
        <form method="post" action="logo.php" enctype="multipart/form-data" class="form-slider">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="unggah">
            <div>
                <label for="berkasLogo">Berkas logo</label>
                <input type="file" id="berkasLogo" name="logo" required
                       accept="image/jpeg,image/png,image/webp,image/svg+xml,image/gif">
                <p class="ket mb0">Ukuran disarankan 512 &times; 512 piksel (persegi). Maks
                    <?= e(number_format($maksFoto / 1048576, 0, ',', '.')) ?> MB.</p>
            </div>
            <div class="form-aksi">
                <button class="btn" type="submit"><?= ikon('unggah', 16) ?>
                    <?= $logo !== '' ? 'Ganti Logo' : 'Unggah Logo' ?></button>
            </div>
        </form>
        <?php if ($logo !== ''): ?>
            <form method="post" action="logo.php" class="mt8"
                  data-confirm="Hapus logo aplikasi? Panel masuk akan memakai lencana bawaan.">
                <?= csrf_field() ?>
                <input type="hidden" name="aksi" value="hapus">
                <button class="btn btn-kecil btn-err" type="submit">Hapus Logo</button>
            </form>
        <?php endif; ?>
    </div>

    <div class="card" id="cardPratinjauLogo">
        <div class="card-kepala"><h2>Pratinjau</h2>
            <span class="pil <?= $logo !== '' ? 'pil-unggul' : 'pil-kosong' ?>">
                <?= $logo !== '' ? 'logo unggahan' : 'lencana bawaan' ?></span>
        </div>
        <div class="logo-pratinjau">
            <?php if ($logo !== ''): ?>
                <img src="<?= e($logo) ?>" alt="Logo aplikasi" class="logo-bulat">
            <?php else: ?>
                <?= logo_app_bawaan() ?>
            <?php endif; ?>
        </div>
        <p class="ket mb0"><?= $logo !== ''
            ? 'Logo ini yang tampil pada panel masuk' . ($dibuat !== '' ? ' (diunggah ' . e(waktu_id($dibuat)) . ')' : '') . '.'
            : 'Belum ada logo unggahan — panel masuk memakai lencana bawaan di atas. Unggah logo agar tampil sesuai keinginan Anda.' ?></p>
    </div>
</div>
<?php
page_bottom();
