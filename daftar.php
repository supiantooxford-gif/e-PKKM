<?php
/**
 * e-PKKM - PENDAFTARAN MADRASAH (halaman publik).
 *
 * Permintaan pemilik: mengganti tulisan "Akun dibuat oleh Admin aplikasi..." pada panel masuk
 * dengan FITUR PENDAFTARAN SEKOLAH. Pendaftar mengisi identitas lembaga + akun kepala madrasah
 * (untuk dia masuk). Pendaftaran berstatus MENUNGGU dan akun belum dapat dipakai masuk sampai
 * Super Admin menyetujui/mengaktifkannya di halaman `sekolah.php`.
 *
 * Gaya mengikuti halaman depan (hijau tua + emas, kartu 3D) dengan formulir BERTAHAP.
 * Tanpa JavaScript seluruh langkah tetap tampil pada satu halaman (formulir tetap bisa dikirim).
 */
require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/landing.php';

if (current_user()) {
    redirect('index.php');
}

$galat = [];
$sukses = isset($_GET['ok']);
$nilai = [];
foreach (array_keys(daftar_medan_lembaga()) as $k) {
    $nilai[$k] = '';
}
$nilaiAkun = ['nama' => '', 'username' => ''];
$cap = daftar_captcha_baru();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) {
        tolak_csrf();
    }
    $aksi = (string)($_POST['aksi'] ?? 'daftar');
    if ($aksi === 'daftar') {
        foreach (array_keys(daftar_medan_lembaga()) as $k) {
            $nilai[$k] = bersih_teks((string)($_POST[$k] ?? ''), PKKM_TEKS_PENDEK);
        }
        $nilaiAkun['nama'] = bersih_teks((string)($_POST['akun_nama'] ?? ''), PKKM_TEKS_PENDEK);
        $nilaiAkun['username'] = trim((string)($_POST['akun_username'] ?? ''));
        $sandi = (string)($_POST['akun_sandi'] ?? '');
        $sandi2 = (string)($_POST['akun_sandi2'] ?? '');
        $jawab = trim((string)($_POST['captcha'] ?? ''));
        // WAJIB bernilai '1' (isset() saja tidak cukup: isian kosong juga dianggap terisi)
        $setuju = ((string)($_POST['setuju'] ?? '') === '1');

        if (trim($nilai['nama']) === '') {
            $galat[] = 'Nama madrasah wajib diisi.';
        }
        if ($nilaiAkun['username'] === '') {
            $galat[] = 'Nama pengguna akun kepala madrasah wajib diisi.';
        } elseif (!daftar_username_sah($nilaiAkun['username'])) {
            $galat[] = 'Nama pengguna 4-40 karakter: huruf, angka, titik, garis bawah, atau garis hubung.';
        }
        if (strlen($sandi) < 6) {
            $galat[] = 'Kata sandi minimal 6 karakter.';
        } elseif ($sandi !== $sandi2) {
            $galat[] = 'Ulangi kata sandi tidak sama.';
        }
        if ($jawab === '' || (int)$jawab !== ((int)$cap['a'] + (int)$cap['b'])) {
            $galat[] = 'Jawaban pengaman (captcha) salah.';
        }
        if (!$setuju) {
            $galat[] = 'Centang pernyataan bahwa data yang diisi benar.';
        }
        if ((int)($_SESSION['daftar_jumlah'] ?? 0) >= daftar_batas_sesi()) {
            $galat[] = 'Pendaftaran dari sesi ini sudah mencapai batas (' . daftar_batas_sesi()
                . ' kali). Hubungi Admin aplikasi bila perlu mendaftarkan madrasah lain.';
        }

        if (!$galat) {
            $hasil = daftar_simpan($nilai, [
                'nama' => $nilaiAkun['nama'],
                'username' => $nilaiAkun['username'],
                'sandi' => $sandi,
            ]);
            if ($hasil['ok']) {
                $_SESSION['daftar_jumlah'] = (int)($_SESSION['daftar_jumlah'] ?? 0) + 1;
                $_SESSION['daftar_terakhir'] = [
                    'nama' => $nilai['nama'],
                    'akun' => $nilaiAkun['username'],
                ];
                daftar_captcha_ganti();
                redirect('daftar.php?ok=1');
            }
            $galat[] = $hasil['pesan'];
        }
        $cap = daftar_captcha_ganti();
    }
}

$terakhir = $_SESSION['daftar_terakhir'] ?? ['nama' => '', 'akun' => ''];
$medan = daftar_medan_lembaga();
$bagianLembaga = [
    // 'status_lembaga' diletakkan TEPAT setelah nama (permintaan pemilik: "disebelah nama madrasah")
    'Identitas Lembaga' => ['nama', 'status_lembaga', 'npsn', 'nsm'],
    'Alamat' => ['alamat', 'desa', 'kecamatan', 'kabupaten', 'provinsi'],
    'Kontak' => ['telepon', 'email', 'website'],
    'Kepala Madrasah' => ['kepala_nama', 'kepala_nip', 'tempat'],
];
$petunjuk = [
    'nama' => 'mis. MA. Sirojuth Tholibiin Mootilango',
    'status_lembaga' => 'Negeri atau Swasta',
    'npsn' => '8 digit NPSN',
    'nsm' => 'Nomor Statistik Madrasah',
    'alamat' => 'Jalan, dusun, nomor',
    'desa' => 'Desa/Kelurahan',
    'kecamatan' => 'Kecamatan',
    'kabupaten' => 'Kabupaten/Kota',
    'provinsi' => 'Provinsi',
    'telepon' => 'mis. 085394726089',
    'email' => 'mis. madrasah@contoh.sch.id',
    'website' => 'mis. www.ma.contoh.sch.id',
    'kepala_nama' => 'Nama lengkap kepala madrasah',
    'kepala_nip' => 'NIP/NUPTK kepala madrasah',
    'tempat' => 'Kota/Kabupaten tempat penilaian',
];
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Pendaftaran Madrasah - <?= e(APP_NAME) ?></title>
<meta name="description" content="Pendaftaran madrasah untuk menggunakan aplikasi <?= e(APP_NAME) ?> - <?= e(APP_SUB) ?>.">
<link rel="stylesheet" href="assets/style.css?v=<?= e(APP_VERSION) ?>">
<link rel="icon" href="data:image/svg+xml,<?= rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><rect width="24" height="24" fill="#0b4a33"/><path d="M12 4l8 5v11H4V9z" fill="#c9a227"/></svg>') ?>">
</head>
<body class="bg-deco lp">

<!-- ============================ bilah navigasi ============================ -->
<header class="lp-nav" id="lpNav">
    <div class="lp-nav-inner">
        <a class="lp-brand" href="login.php">
            <span class="lp-brand-ikon"><?= landing_ikon('school', 22) ?></span>
            <span><b><?= e(APP_NAME) ?></b><i><?= e(APP_SUB) ?></i></span>
        </a>
        <button class="lp-nav-tombol" type="button" data-lp-menu aria-label="Buka menu"><?= ikon('menu', 20) ?></button>
        <nav class="lp-nav-menu" id="lpMenu">
            <a href="login.php">Beranda</a>
            <a href="login.php#fitur">Fitur</a>
            <a href="login.php#keunggulan">Keunggulan</a>
            <a href="login.php#kontak">Kontak</a>
            <a class="lp-nav-masuk" href="login.php#masuk">Masuk</a>
        </nav>
    </div>
</header>

<section class="lp-sec lp-hero" id="daftar">
    <div class="lp-wrap">
        <p class="lp-label lp-hero-head">Pendaftaran Madrasah</p>

        <?php if ($sukses): ?>
            <!-- ============================ kartu sukses ============================ -->
            <div class="card lp-daftar-kartu df-sukses">
                <div class="df-sukses-ikon"><?= landing_ikon('check', 34) ?></div>
                <h1>Pendaftaran Berhasil Dikirim</h1>
                <p class="df-sukses-lead">
                    Terima kasih<?= trim((string)($terakhir['nama'] ?? '')) !== '' ? ', <b>' . e((string)$terakhir['nama']) . '</b>' : '' ?>.
                    Pendaftaran madrasah Anda sudah kami terima dan <b>menunggu persetujuan Admin aplikasi</b>.
                </p>
                <?php if (trim((string)($terakhir['akun'] ?? '')) !== ''): ?>
                    <p class="df-sukses-akun">Akun kepala madrasah Anda: <b><?= e((string)$terakhir['akun']) ?></b>
                        <span class="kecil mut">(kata sandi sesuai yang Anda isi)</span></p>
                <?php endif; ?>
                <ol class="df-langkah-berikut">
                    <li>Admin aplikasi meninjau data lembaga dan akun kepala madrasah Anda.</li>
                    <li>Setelah <b>disetujui &amp; diaktifkan</b>, akun kepala madrasah baru bisa dipakai masuk.</li>
                    <li>Silakan masuk lewat halaman masuk, lalu lengkapi tahun &amp; tanggal penilaian pada menu
                        <b>Input Data Madrasah</b>.</li>
                </ol>
                <div class="df-sukses-aksi">
                    <a class="btn btn-emas" href="login.php#masuk">Ke Halaman Masuk</a>
                    <a class="btn btn-ghost" href="daftar.php">Daftarkan Madrasah Lain</a>
                </div>
            </div>
        <?php else: ?>

        <div class="df-kepala">
            <h1 class="lp-judul kiri">Daftarkan Madrasah Anda</h1>
            <p class="df-kepala-sub">Isi identitas lembaga dan akun kepala madrasah pada formulir bertahap berikut.
                Pendaftaran <b>tidak langsung aktif</b> &mdash; Admin aplikasi akan meninjau dan mengaktifkannya.</p>
        </div>

        <div class="df-grid">
            <!-- =============================== formulir =============================== -->
            <form method="post" action="daftar.php" class="card lp-daftar-kartu df-form" id="formDaftar" data-daftar>
                <?= csrf_field() ?>
                <input type="hidden" name="aksi" value="daftar">

                <?php if ($galat): ?>
                    <div class="alert alert-err">
                        <ul class="mb0"><?php foreach ($galat as $g): ?><li><?= e($g) ?></li><?php endforeach; ?></ul>
                    </div>
                <?php endif; ?>

                <!-- pengalih langkah (tanpa JavaScript semua langkah tampil) -->
                <div class="df-langkah-nav" data-df-nav hidden>
                    <button type="button" class="df-langkah-tombol on" data-df-ke="1">
                        <b>1</b><span>Identitas Lembaga</span></button>
                    <button type="button" class="df-langkah-tombol" data-df-ke="2">
                        <b>2</b><span>Akun Kepala</span></button>
                    <button type="button" class="df-langkah-tombol" data-df-ke="3">
                        <b>3</b><span>Konfirmasi</span></button>
                </div>

                <!-- ---------------------------- langkah 1 ---------------------------- -->
                <fieldset class="df-langkah" data-df-langkah="1">
                    <legend><b>Langkah 1</b> &middot; Identitas Lembaga</legend>
                    <?php foreach ($bagianLembaga as $judulBagian => $daftarMedan): ?>
                        <h4 class="df-sub"><?= e($judulBagian) ?></h4>
                        <div class="form-2">
                            <?php foreach ($daftarMedan as $k): ?>
                                <div>
                                    <label for="d<?= e($k) ?>"><?= e($medan[$k]) ?><?= $k === 'nama' ? ' *' : '' ?></label>
                                    <?php if ($k === 'status_lembaga'): ?>
                                        <?php $statusNilai = status_lembaga_sah($nilai[$k] ?? ''); ?>
                                        <select id="dstatus_lembaga" name="status_lembaga" required>
                                            <?php foreach (PKKM_STATUS_LEMBAGA as $sKunci => $sLabel): ?>
                                                <option value="<?= e($sKunci) ?>" <?= $statusNilai === $sKunci ? 'selected' : '' ?>>
                                                    <?= e($sLabel) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php else: ?>
                                    <input type="text" id="d<?= e($k) ?>" name="<?= e($k) ?>"
                                           value="<?= e($nilai[$k] ?? '') ?>"
                                           placeholder="<?= e($petunjuk[$k] ?? '') ?>"
                                           <?= $k === 'nama' ? 'required' : '' ?>>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                    <p class="ket mb0">Tahun &amp; tanggal penilaian <b>tidak</b> diisi di sini &mdash; dilengkapi nanti
                        pada menu <b>Input Data Madrasah</b> setelah akun aktif.</p>
                    <p class="ket mb0"><b>Status Lembaga</b> menentukan susunan <b>7 penilai Tahun Keempat</b>:
                        <b>Negeri</b> = Kabid, Kasie, Pengawas 1, Pengawas 2, Guru, Tendik, Komite;
                        <b>Swasta</b> = Yayasan, Kasie, Pengawas 1, Pengawas 2, Guru, Tendik, Komite.</p>
                </fieldset>

                <!-- ---------------------------- langkah 2 ---------------------------- -->
                <fieldset class="df-langkah" data-df-langkah="2">
                    <legend><b>Langkah 2</b> &middot; Akun Kepala Madrasah</legend>
                    <p class="ket">Akun ini dipakai kepala madrasah untuk masuk ke aplikasi setelah pendaftaran
                        disetujui Admin aplikasi.</p>
                    <div class="form-2">
                        <div>
                            <label for="dakun_nama">Nama Kepala Madrasah</label>
                            <input type="text" id="dakun_nama" name="akun_nama" value="<?= e($nilaiAkun['nama']) ?>"
                                   placeholder="Nama yang tampil pada aplikasi">
                        </div>
                        <div>
                            <label for="dakun_username">Nama Pengguna *</label>
                            <input type="text" id="dakun_username" name="akun_username" required
                                   value="<?= e($nilaiAkun['username']) ?>" placeholder="mis. kepalamadrasah"
                                   autocomplete="username">
                        </div>
                        <div>
                            <label for="dakun_sandi">Kata Sandi * (min 6 karakter)</label>
                            <input type="password" id="dakun_sandi" name="akun_sandi" required minlength="6"
                                   autocomplete="new-password">
                        </div>
                        <div>
                            <label for="dakun_sandi2">Ulangi Kata Sandi *</label>
                            <input type="password" id="dakun_sandi2" name="akun_sandi2" required minlength="6"
                                   autocomplete="new-password">
                        </div>
                    </div>
                    <p class="ket mb0">Nama pengguna harus unik (tidak boleh sama dengan akun yang sudah ada).</p>
                </fieldset>

                <!-- ---------------------------- langkah 3 ---------------------------- -->
                <fieldset class="df-langkah" data-df-langkah="3">
                    <legend><b>Langkah 3</b> &middot; Konfirmasi &amp; Kirim</legend>
                    <div class="df-ringkas" data-df-ringkas>
                        <div><span>Madrasah</span><b data-df-r="nama">&mdash;</b></div>
                        <div><span>Status Lembaga</span><b data-df-r="status_lembaga">&mdash;</b></div>
                        <div><span>Kecamatan</span><b data-df-r="kecamatan">&mdash;</b></div>
                        <div><span>Kabupaten/Kota</span><b data-df-r="kabupaten">&mdash;</b></div>
                        <div><span>Nama Pengguna</span><b data-df-r="akun_username">&mdash;</b></div>
                    </div>
                    <div class="form-baris">
                        <label for="dcaptcha">Pengaman: berapa <?= (int)$cap['a'] ?> + <?= (int)$cap['b'] ?> ?</label>
                        <input type="text" id="dcaptcha" name="captcha" required inputmode="numeric"
                               autocomplete="off" placeholder="Tulis jawabannya">
                    </div>
                    <label class="df-setuju">
                        <input type="checkbox" name="setuju" value="1" required>
                        <span>Saya menyatakan data yang saya isi benar dan berhak mendaftarkan madrasah ini.
                            Saya memahami pendaftaran <b>menunggu persetujuan Admin aplikasi</b> sebelum akun
                            dapat dipakai masuk.</span>
                    </label>
                </fieldset>

                <div class="df-aksi">
                    <button type="button" class="btn btn-ghost" data-df-mundur hidden>Sebelumnya</button>
                    <button type="button" class="btn btn-emas" data-df-maju hidden>Lanjut</button>
                    <button class="btn" type="submit" id="dfKirim"><?= ikon('simpan', 16) ?> Kirim Pendaftaran</button>
                </div>
                <p class="ket mt8 mb0">Sudah punya akun yang disetujui?
                    <a href="login.php#masuk">Masuk di sini</a>.</p>
            </form>

            <!-- ============================== panel bantuan ============================== -->
            <aside class="df-sisi">
                <div class="card lp-daftar-kartu">
                    <h2>Bagaimana Alurnya?</h2>
                    <ol class="df-alur">
                        <li><b>Isi formulir</b><span>Identitas lembaga + akun kepala madrasah.</span></li>
                        <li><b>Ditinjau Admin</b><span>Data lembaga dan akun diperiksa Admin aplikasi.</span></li>
                        <li><b>Disetujui &amp; aktif</b><span>Akun kepala madrasah bisa dipakai masuk.</span></li>
                        <li><b>Lengkapi data</b><span>Tahun &amp; tanggal penilaian diisi pada menu Input Data Madrasah.</span></li>
                    </ol>
                </div>
                <div class="card lp-daftar-kartu">
                    <h2>Yang Perlu Disiapkan</h2>
                    <ul class="df-cek">
                        <li>Nama madrasah, NPSN, dan NSM (bila ada)</li>
                        <li>Alamat lengkap: desa, kecamatan, kabupaten, provinsi</li>
                        <li>Kontak: telepon/HP, email, situs (bila ada)</li>
                        <li>Nama &amp; NIP kepala madrasah</li>
                        <li>Nama pengguna + kata sandi untuk akun kepala madrasah</li>
                    </ul>
                </div>
            </aside>
        </div>
        <?php endif; ?>
    </div>
</section>

<footer class="lp-foot">
    <div class="lp-wrap lp-foot-isi">
        <p class="mb0"><?= e(APP_NAME) ?> Versi <?= e(APP_VERSION) ?> &middot; <?= e(APP_SUB) ?></p>
        <p class="mb0 kecil mut">Dikembangkan oleh <?= e(APP_AUTHOR) ?> (<?= e(APP_AUTHOR_JABATAN) ?>)</p>
    </div>
</footer>

<script src="assets/app.js?v=<?= e(APP_VERSION) ?>"></script>
</body>
</html>
