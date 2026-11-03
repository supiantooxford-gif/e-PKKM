<?php
/**
 * e-PKKM - PENDAFTARAN PENILAI (halaman publik).
 *
 * Permintaan pemilik: pada kartu login di samping "Pendaftaran Madrasah" ditambahkan
 * "Pendaftaran Penilai" dengan formulir pendaftaran + daftar akun & sandinya.
 *
 * Penilai (Kabag, Kasie, Pengawas, Guru, Komite — daftar jabatan diisi Super Admin) mendaftar
 * sendiri: akun dibuat berperan `pengawas` (status tetap pengawas) dan LANGSUNG dapat dipakai
 * masuk. Setelah masuk, penilai memilih sendiri madrasah yang dinilai beserta posisinya
 * (Penilai 1 / Penilai 2) pada halaman Dashboard Penilai.
 *
 * Halaman ini menampilkan kembali AKUN + KATA SANDI yang baru didaftarkan pada sesi ini
 * (kata sandi tidak pernah bisa ditampilkan lagi setelah sesi berakhir).
 */
require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/landing.php';

if (current_user()) {
    redirect('index.php');
}

$galat = [];
$sukses = isset($_GET['ok']);
$nilai = ['nama' => '', 'nip' => '', 'jabatan' => '', 'instansi' => '', 'kab_kota' => '', 'hp' => ''];
$nilaiAkun = ['username' => ''];
$cap = penilai_captcha_baru();
$jabatanList = jabatan_penilai_list(true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) {
        tolak_csrf();
    }
    if ((string)($_POST['aksi'] ?? 'daftar_penilai') === 'daftar_penilai') {
        foreach (array_keys(penilai_medan_daftar()) as $k) {
            $nilai[$k] = bersih_teks((string)($_POST[$k] ?? ''), PKKM_TEKS_PENDEK);
        }
        $nilaiAkun['username'] = trim((string)($_POST['pakun_username'] ?? ''));
        $sandi = (string)($_POST['pakun_sandi'] ?? '');
        $sandi2 = (string)($_POST['pakun_sandi2'] ?? '');
        $jawab = trim((string)($_POST['captcha'] ?? ''));
        $setuju = ((string)($_POST['setuju'] ?? '') === '1');

        if ($nilai['nama'] === '') {
            $galat[] = 'Nama lengkap wajib diisi.';
        }
        if ($nilai['jabatan'] === '') {
            $galat[] = 'Jabatan wajib dipilih.';
        } elseif (!jabatan_penilai_sah($nilai['jabatan'])) {
            $galat[] = 'Jabatan yang dipilih tidak tersedia. Muat ulang halaman lalu pilih dari daftar.';
        }
        if ($nilaiAkun['username'] === '') {
            $galat[] = 'Nama pengguna wajib diisi.';
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
        if ((int)($_SESSION['penilai_jumlah'] ?? 0) >= penilai_batas_sesi()) {
            $galat[] = 'Pendaftaran penilai dari sesi ini sudah mencapai batas (' . penilai_batas_sesi()
                . ' kali). Hubungi Admin aplikasi bila perlu mendaftarkan penilai lain.';
        }

        if (!$galat) {
            $hasil = penilai_simpan_daftar($nilai, ['username' => $nilaiAkun['username'], 'sandi' => $sandi]);
            if ($hasil['ok']) {
                $_SESSION['penilai_jumlah'] = (int)($_SESSION['penilai_jumlah'] ?? 0) + 1;
                // daftar akun & sandi yang dibuat pada sesi ini (hanya ditampilkan pada sesi ini)
                $daftarAkun = is_array($_SESSION['penilai_akun_baru'] ?? null) ? $_SESSION['penilai_akun_baru'] : [];
                $daftarAkun[] = [
                    'nama' => $nilai['nama'],
                    'jabatan' => $nilai['jabatan'],
                    'username' => $nilaiAkun['username'],
                    'sandi' => $sandi,
                ];
                $_SESSION['penilai_akun_baru'] = $daftarAkun;
                penilai_captcha_ganti();
                unset($_SESSION['penilai_captcha']);
                redirect('daftar_penilai.php?ok=1');
            }
            $galat[] = $hasil['pesan'];
        }
        $cap = penilai_captcha_ganti();
    }
}

$daftarAkunBaru = is_array($_SESSION['penilai_akun_baru'] ?? null) ? $_SESSION['penilai_akun_baru'] : [];
$terakhir = $daftarAkunBaru ? $daftarAkunBaru[count($daftarAkunBaru) - 1] : null;
$medan = penilai_medan_daftar();
$petunjuk = [
    'nama' => 'mis. Herianto Dalanggo, M.Pd.I',
    'nip' => 'NIP/NUPTK (boleh dikosongkan)',
    'instansi' => 'mis. Kantor Kemenag Kab. Gorontalo',
    'kab_kota' => 'mis. Gorontalo',
    'hp' => 'mis. 085394726089',
];
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Pendaftaran Penilai - <?= e(APP_NAME) ?></title>
<meta name="description" content="Pendaftaran penilai (pengawas, Kabag, Kasie, Guru, Komite) aplikasi <?= e(APP_NAME) ?>.">
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
            <a href="daftar.php">Pendaftaran Madrasah</a>
            <a href="login.php#kontak">Kontak</a>
            <a class="lp-nav-masuk" href="login.php#masuk">Masuk</a>
        </nav>
    </div>
</header>

<section class="lp-sec lp-hero" id="daftarPenilai">
    <div class="lp-wrap">
        <p class="lp-label lp-hero-head">Pendaftaran Penilai</p>

        <?php if ($sukses): ?>
            <!-- ============================ kartu sukses ============================ -->
            <div class="card lp-daftar-kartu df-sukses" id="kartuSuksesPenilai">
                <div class="df-sukses-ikon"><?= landing_ikon('check', 34) ?></div>
                <h1>Pendaftaran Penilai Berhasil</h1>
                <p class="df-sukses-lead">
                    Terima kasih<?= $terakhir && trim((string)$terakhir['nama']) !== '' ? ', <b>' . e((string)$terakhir['nama']) . '</b>' : '' ?>.
                    Akun penilai Anda sudah dibuat dan <b>langsung dapat dipakai masuk</b>.
                </p>

                <?php if ($daftarAkunBaru): ?>
                    <h2 class="df-sukses-sub">Daftar Akun &amp; Sandi Penilai</h2>
                    <p class="ket">Catat atau simpan kata sandi berikut sekarang &mdash; kata sandi
                        <b>tidak dapat ditampilkan lagi</b> setelah Anda menutup/menyegarkan halaman ini.</p>
                    <div class="geser">
                        <table class="tbl tbl-padat df-sukses-tbl" id="tblAkunPenilai">
                            <thead><tr><th>No</th><th>Nama Penilai</th><th>Jabatan</th>
                                <th>Nama Pengguna</th><th>Kata Sandi</th></tr></thead>
                            <tbody>
                            <?php foreach ($daftarAkunBaru as $i => $a): ?>
                                <tr>
                                    <td><?= $i + 1 ?></td>
                                    <td><?= e($a['nama']) ?></td>
                                    <td><?= e($a['jabatan']) ?></td>
                                    <td><b><?= e($a['username']) ?></b></td>
                                    <td><b><?= e($a['sandi']) ?></b></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <ol class="df-langkah-berikut">
                    <li>Masuk lewat halaman masuk memakai nama pengguna &amp; kata sandi di atas.</li>
                    <li>Pada halaman <b>Dashboard Penilai</b>, centang madrasah yang akan Anda nilai dan
                        tentukan posisi Anda sebagai <b>Penilai 1</b> atau <b>Penilai 2</b>.</li>
                    <li>Setelah memilih madrasah, menu penilaian kepala madrasah dapat dibuka.</li>
                </ol>
                <div class="df-sukses-aksi">
                    <a class="btn btn-emas" href="login.php#masuk">Ke Halaman Masuk</a>
                    <a class="btn btn-ghost" href="daftar_penilai.php">Daftarkan Penilai Lain</a>
                </div>
            </div>
        <?php else: ?>

        <div class="df-kepala">
            <h1 class="lp-judul kiri">Daftarkan Akun Penilai</h1>
            <p class="df-kepala-sub">Formulir untuk <b>pengawas / Kabag / Kasie / Guru / Komite</b> yang bertugas
                menilai kinerja kepala madrasah. Akun penilai <b>langsung aktif</b>; setelah masuk, pilih
                madrasah yang Anda nilai beserta posisinya.</p>
        </div>

        <div class="df-grid">
            <!-- =============================== formulir =============================== -->
            <form method="post" action="daftar_penilai.php" class="card lp-daftar-kartu df-form" id="formDaftarPenilai" data-daftar>
                <?= csrf_field() ?>
                <input type="hidden" name="aksi" value="daftar_penilai">

                <?php if ($galat): ?>
                    <div class="alert alert-err">
                        <ul class="mb0"><?php foreach ($galat as $g): ?><li><?= e($g) ?></li><?php endforeach; ?></ul>
                    </div>
                <?php endif; ?>

                <div class="df-langkah-nav" data-df-nav hidden>
                    <button type="button" class="df-langkah-tombol on" data-df-ke="1">
                        <b>1</b><span>Data Penilai</span></button>
                    <button type="button" class="df-langkah-tombol" data-df-ke="2">
                        <b>2</b><span>Akun &amp; Konfirmasi</span></button>
                </div>

                <!-- ---------------------------- langkah 1 ---------------------------- -->
                <fieldset class="df-langkah" data-df-langkah="1">
                    <legend><b>Langkah 1</b> &middot; Data Penilai</legend>
                    <div class="form-2">
                        <div>
                            <label for="p_nama">Nama Lengkap (beserta gelar) *</label>
                            <input type="text" id="p_nama" name="nama" required maxlength="<?= PKKM_TEKS_PENDEK ?>"
                                   value="<?= e($nilai['nama']) ?>" placeholder="<?= e($petunjuk['nama']) ?>">
                        </div>
                        <div>
                            <label for="p_nip">NIP</label>
                            <input type="text" id="p_nip" name="nip" maxlength="<?= PKKM_TEKS_PENDEK ?>"
                                   value="<?= e($nilai['nip']) ?>" placeholder="<?= e($petunjuk['nip']) ?>">
                        </div>
                        <div>
                            <label for="p_jabatan">Jabatan *</label>
                            <select id="p_jabatan" name="jabatan" required>
                                <option value="">— pilih jabatan —</option>
                                <?php foreach ($jabatanList as $j): ?>
                                    <option value="<?= e($j['nama']) ?>" <?= $nilai['jabatan'] === (string)$j['nama'] ? 'selected' : '' ?>>
                                        <?= e($j['nama']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (!$jabatanList): ?>
                                <p class="ket mb0">Daftar jabatan belum diisi Admin aplikasi.</p>
                            <?php endif; ?>
                        </div>
                        <div>
                            <label for="p_instansi">Unit Kerja / Instansi</label>
                            <input type="text" id="p_instansi" name="instansi" maxlength="<?= PKKM_TEKS_PENDEK ?>"
                                   value="<?= e($nilai['instansi']) ?>" placeholder="<?= e($petunjuk['instansi']) ?>">
                        </div>
                        <div>
                            <label for="p_kabkota">Kabupaten/Kota</label>
                            <input type="text" id="p_kabkota" name="kab_kota" maxlength="<?= PKKM_TEKS_PENDEK ?>"
                                   value="<?= e($nilai['kab_kota']) ?>" placeholder="<?= e($petunjuk['kab_kota']) ?>">
                        </div>
                        <div>
                            <label for="p_hp">No. HP / WhatsApp</label>
                            <input type="text" id="p_hp" name="hp" maxlength="<?= PKKM_TEKS_PENDEK ?>"
                                   value="<?= e($nilai['hp']) ?>" placeholder="<?= e($petunjuk['hp']) ?>">
                        </div>
                    </div>
                    <p class="ket mb0">Jabatan diambil dari daftar yang diisi Admin aplikasi
                        (mis. Kabag, Kasie, Pengawas, Guru, Komite).</p>
                </fieldset>

                <!-- ---------------------------- langkah 2 ---------------------------- -->
                <fieldset class="df-langkah" data-df-langkah="2">
                    <legend><b>Langkah 2</b> &middot; Akun &amp; Konfirmasi</legend>
                    <div class="df-ringkas" data-df-ringkas>
                        <div><span>Nama Penilai</span><b data-df-r="nama">&mdash;</b></div>
                        <div><span>Jabatan</span><b data-df-r="jabatan">&mdash;</b></div>
                        <div><span>Kabupaten/Kota</span><b data-df-r="kab_kota">&mdash;</b></div>
                        <div><span>Nama Pengguna</span><b data-df-r="pakun_username">&mdash;</b></div>
                    </div>
                    <div class="form-2">
                        <div>
                            <label for="pakun_username">Nama Pengguna *</label>
                            <input type="text" id="pakun_username" name="pakun_username" required
                                   value="<?= e($nilaiAkun['username']) ?>" placeholder="mis. herianto"
                                   autocomplete="username">
                        </div>
                        <div>
                            <label for="pakun_sandi">Kata Sandi * (min 6 karakter)</label>
                            <input type="password" id="pakun_sandi" name="pakun_sandi" required minlength="6"
                                   autocomplete="new-password">
                        </div>
                        <div>
                            <label for="pakun_sandi2">Ulangi Kata Sandi *</label>
                            <input type="password" id="pakun_sandi2" name="pakun_sandi2" required minlength="6"
                                   autocomplete="new-password">
                        </div>
                        <div>
                            <label for="pcaptcha">Pengaman: berapa <?= (int)$cap['a'] ?> + <?= (int)$cap['b'] ?> ?</label>
                            <input type="text" id="pcaptcha" name="captcha" required inputmode="numeric"
                                   autocomplete="off" placeholder="Tulis jawabannya">
                        </div>
                    </div>
                    <label class="df-setuju">
                        <input type="checkbox" name="setuju" value="1" required>
                        <span>Saya menyatakan data yang saya isi benar dan saya berhak menjadi penilai PKKM.
                            Saya memahami akun ini <b>langsung aktif</b> dan saya sendiri yang memilih madrasah
                            yang akan dinilai.</span>
                    </label>
                </fieldset>

                <div class="df-aksi">
                    <button type="button" class="btn btn-ghost" data-df-mundur hidden>Sebelumnya</button>
                    <button type="button" class="btn btn-emas" data-df-maju hidden>Lanjut</button>
                    <button class="btn" type="submit" id="pKirim"><?= ikon('simpan', 16) ?> Kirim Pendaftaran</button>
                </div>
                <p class="ket mt8 mb0">Madrasah yang belum terdaftar? Daftarkan lewat
                    <a href="daftar.php">Pendaftaran Madrasah</a>. Sudah punya akun?
                    <a href="login.php#masuk">Masuk di sini</a>.</p>
            </form>

            <!-- ============================== panel bantuan ============================== -->
            <aside class="df-sisi">
                <div class="card lp-daftar-kartu">
                    <h2>Untuk Siapa?</h2>
                    <ul class="df-cek">
                        <li>Pengawas madrasah (Pengawas I / II)</li>
                        <li>Kepala Bagian &amp; Kepala Seksi (Kabag / Kasie)</li>
                        <li>Guru dan Komite Madrasah sebagai penilai</li>
                        <li>Satu akun untuk satu penilai; jabatan dipilih saat mendaftar</li>
                    </ul>
                </div>
                <div class="card lp-daftar-kartu">
                    <h2>Bagaimana Alurnya?</h2>
                    <ol class="df-alur">
                        <li><b>Isi formulir</b><span>Data penilai + akun (nama pengguna &amp; kata sandi).</span></li>
                        <li><b>Akun langsung aktif</b><span>Nama pengguna &amp; kata sandi tampil setelah mendaftar.</span></li>
                        <li><b>Pilih madrasah</b><span>Centang madrasah yang dinilai &amp; tentukan posisi Penilai 1/2.</span></li>
                        <li><b>Mulai menilai</b><span>Isi penilaian kepala madrasah pada menu penilaian.</span></li>
                    </ol>
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
