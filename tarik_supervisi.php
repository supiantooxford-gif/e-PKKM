<?php
/**
 * e-PKKM - TARIK LEMBAGA, KEPALA MADRASAH & PENGAWAS DARI APLIKASI SUPERVISI (Super Admin).
 *
 * Permintaan pemilik (v1.11.0): "Data sekolah, pengawas dan kepala sekolah akan ditarik oleh super
 * admin melalui cek lembaga yang status aktif di aplikasi supervisi. Yang mendaftar hanya yang
 * jabatan selain pengawas. Lembaga dan pengawas akan ditarik dari aplikasi supervisi".
 *
 * Hanya lembaga berstatus AKTIF pada aplikasi Supervisi yang ditampilkan (mode terbatas / masa uji
 * tidak ikut). Super Admin mencentang lembaga lalu menekan "Tarik yang Dicentang"; penarikan ulang
 * aman (tidak menggandakan madrasah, akun, atau penugasan).
 *
 * v1.12.0 (permintaan pemilik): PETA PENGAWAS BINAAN menentukan Ketua Tim — setiap pengawas binaan
 * lembaga langsung ditugaskan sebagai PENILAI 1 (Ketua Tim). PENILAI 2 tidak lagi diisi otomatis
 * (dulu pengawas kedua mendapat posisi 2); Ketua Tim yang menentukannya di Dashboard Penilai.
 */
require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/supervisi.php';

$u = require_super();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) {
        tolak_csrf();
    }
    $aksi = (string)($_POST['aksi'] ?? '');
    if ($aksi === 'tarik') {
        $ids = $_POST['lembaga'] ?? [];
        $ids = is_array($ids) ? array_map('intval', $ids) : [];
        if (!$ids) {
            flash('Centang dahulu lembaga yang ingin ditarik.', 'err');
            redirect('tarik_supervisi.php');
        }
        $laporan = tarik_lembaga_supervisi_banyak($ids);
        mulai_sesi();
        $_SESSION['tarik_hasil'] = $laporan;
        $berhasil = count(array_filter($laporan, fn($l) => !empty($l['ok'])));
        flash($berhasil . ' dari ' . count($laporan) . ' lembaga berhasil ditarik dari aplikasi Supervisi.',
            $berhasil === count($laporan) ? 'ok' : 'err');
        redirect('tarik_supervisi.php#hasilTarik');
    }
}

mulai_sesi();
$laporan = is_array($_SESSION['tarik_hasil'] ?? null) ? $_SESSION['tarik_hasil'] : [];
unset($_SESSION['tarik_hasil']);

$tersedia = supervisi_tersedia();
$lembaga = $tersedia ? supervisi_lembaga_aktif() : [];
$jumlahSudah = 0;
foreach ($lembaga as $l) {
    if (!empty($l['sudah'])) {
        $jumlahSudah++;
    }
}

page_top('Tarik dari Supervisi', 'Menarik lembaga aktif, kepala madrasah, dan pengawas dari aplikasi Supervisi Guru', true);
?>

<div class="card" id="cardInfoTarik">
    <div class="card-kepala">
        <h2><?= ikon('unduh', 18) ?> Sumber data: Aplikasi Supervisi Guru</h2>
        <div class="aksi"><a class="btn btn-ghost btn-kecil" href="sekolah.php">Kelola Madrasah</a></div>
    </div>
    <?php if (!$tersedia): ?>
        <div class="alert alert-err">
            <?= e(supervisi_pesan_galat()) ?>
        </div>
    <?php else: ?>
        <ul class="mb0 kecil tarik-aturan">
            <li>Yang ditampilkan <b>hanya lembaga berstatus AKTIF</b> (sudah berlangganan) pada aplikasi
                Supervisi. Lembaga yang masih <i>dibatasi</i>/masa uji coba tidak ikut ditarik.</li>
            <li>Yang ditarik: <b>identitas madrasah</b>, <b>akun kepala madrasah</b>, dan <b>akun pengawas
                binaan</b>. Pengawas binaan otomatis menjadi <b>Penilai 1 (Ketua Tim)</b> pada madrasah
                binaannya; <b>Penilai 2</b> ditentukan sendiri oleh Ketua Tim dari daftar pengawas yang ada
                (halaman <b>Dashboard Penilai</b>).</li>
            <li>Nama pengguna dan <b>kata sandi</b> akun mengikuti akun Supervisi. Setelah masuk, pemilik akun
                bebas mengganti kata sandinya di menu <b>Akun Saya</b>; penarikan ulang tidak menimpa sandi
                yang sudah diganti.</li>
            <li>Menarik ulang <b>tidak menggandakan data</b>: madrasah dicocokkan lewat NPSN, akun lewat nama
                penggunanya.</li>
        </ul>
        <p class="kecil mut mb0 mt8">
            Terbaca: <b><?= count($lembaga) ?></b> lembaga aktif &middot;
            <b><?= $jumlahSudah ?></b> sudah ada di e-PKKM &middot;
            <b><?= count($lembaga) - $jumlahSudah ?></b> belum ditarik.
        </p>
    <?php endif; ?>
</div>

<?php if ($laporan): ?>
    <div class="card" id="hasilTarik">
        <div class="card-kepala"><h2><?= ikon('centang', 18) ?> Hasil Penarikan</h2></div>
        <div class="tarik-hasil">
            <?php foreach ($laporan as $l): ?>
                <div class="tarik-hasil-item <?= !empty($l['ok']) ? 'ok' : 'gagal' ?>">
                    <?php if (empty($l['ok'])): ?>
                        <b><?= e($l['pesan']) ?></b>
                    <?php else: ?>
                        <b><?= e($l['sekolah']['nama']) ?></b>
                        <span class="chip"><?= !empty($l['sekolah']['baru']) ? 'madrasah BARU' : 'madrasah diperbarui' ?></span>
                        <?php if (!empty($l['kepala'])): ?>
                            <p class="mb0 kecil">Kepala madrasah:
                                <b><?= e($l['kepala']['nama']) ?></b>
                                (<?= e($l['kepala']['username']) ?>)
                                &middot; <?= !empty($l['kepala']['baru']) ? 'akun dibuat' : 'akun diperbarui' ?></p>
                        <?php else: ?>
                            <p class="mb0 kecil mut">Akun kepala madrasah tidak ditemukan pada Supervisi.</p>
                        <?php endif; ?>
                        <?php if (!empty($l['pengawas'])): ?>
                            <p class="mb0 kecil">Pengawas:
                                <?php foreach ($l['pengawas'] as $i => $p): ?>
                                    <?= $i > 0 ? ' &middot; ' : '' ?><b><?= e($p['nama']) ?></b>
                                    (<?= e($p['username']) ?>)
                                    <?php if ((int)$p['posisi'] > 0): ?>
                                        &rarr; <?= e(penilai_posisi_teks((int)$p['posisi'])) ?>
                                    <?php else: ?>
                                        <span class="mut">&rarr; belum ditugaskan</span>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </p>
                        <?php endif; ?>
                        <?php if (!empty($l['lewat'])): ?>
                            <ul class="kecil mut mb0">
                                <?php foreach ($l['lewat'] as $c): ?><li><?= e($c) ?></li><?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-kepala">
        <h2>Lembaga Aktif di Aplikasi Supervisi</h2>
        <?php if ($tersedia && $lembaga): ?>
            <div class="aksi">
                <label class="kecil nowrap tarik-pilih-semua">
                    <input type="checkbox" data-tarik-semua id="tarikSemua"> Pilih semua
                </label>
            </div>
        <?php endif; ?>
    </div>

    <?php if (!$tersedia): ?>
        <p class="mut mb0">Daftar lembaga tidak dapat dimuat.</p>
    <?php elseif (!$lembaga): ?>
        <p class="mut mb0">Belum ada lembaga berstatus aktif pada aplikasi Supervisi.</p>
    <?php else: ?>
        <form method="post" action="tarik_supervisi.php" id="formTarik">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="tarik">
            <div class="geser">
                <table class="tbl" id="tblTarik">
                    <thead>
                        <tr>
                            <th class="tarik-cek">Pilih</th>
                            <th>Lembaga (NPSN / NSM)</th>
                            <th>Kepala Madrasah</th>
                            <th>Pengawas di Supervisi</th>
                            <th>Status di e-PKKM</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($lembaga as $l): ?>
                        <tr>
                            <td class="tarik-cek">
                                <input type="checkbox" name="lembaga[]" value="<?= (int)$l['id'] ?>"
                                       id="lm<?= (int)$l['id'] ?>"
                                       aria-label="Tarik <?= e($l['nama']) ?>">
                            </td>
                            <td>
                                <label for="lm<?= (int)$l['id'] ?>" class="tarik-nama"><?= e($l['nama']) ?></label>
                                <span class="kecil mut">
                                    NPSN <?= e(trim((string)($l['npsn'] ?? '')) !== '' ? $l['npsn'] : '-') ?>
                                    <?php if (trim((string)($l['nsm'] ?? '')) !== ''): ?>
                                        &middot; NSM <?= e($l['nsm']) ?>
                                    <?php endif; ?>
                                    <?php if (trim((string)($l['kecamatan'] ?? '')) !== ''): ?>
                                        &middot; <?= e($l['kecamatan']) ?>
                                    <?php endif; ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($l['kepala'])): ?>
                                    <?= e($l['kepala']['nama']) ?><br>
                                    <span class="kecil mut"><?= e($l['kepala']['username']) ?></span>
                                <?php else: ?>
                                    <span class="mut"><?= e(trim((string)($l['kepala_sekolah'] ?? '')) !== ''
                                        ? $l['kepala_sekolah'] : 'belum ada akun kepala') ?></span>
                                    <br><span class="kecil mut">akun kepala belum tersedia</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($l['pengawas'])): ?>
                                    <?php foreach (array_slice($l['pengawas'], 0, 2) as $i => $p): ?>
                                        <?= $i > 0 ? '<br>' : '' ?><?= e($p['nama']) ?>
                                        <span class="kecil mut">(<?= e($p['username']) ?>)</span>
                                    <?php endforeach; ?>
                                    <?php if (count($l['pengawas']) > 2): ?>
                                        <br><span class="kecil mut">+<?= count($l['pengawas']) - 2 ?> pengawas lain</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="mut">belum ada pengawas</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($l['sudah'])): ?>
                                    <span class="chip chip-ok">Sudah ada</span>
                                    <?php if (!(int)$l['lokal']['aktif']): ?>
                                        <span class="chip">nonaktif</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="chip">Belum ditarik</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="form-aksi mt16">
                <button class="btn btn-emas" type="submit" id="btnTarik">
                    <?= ikon('unduh', 16) ?> Tarik yang Dicentang
                </button>
                <span class="kecil mut">Madrasah, kepala, dan pengawas yang sudah ada akan diperbarui, bukan digandakan.</span>
            </div>
        </form>
    <?php endif; ?>
</div>

<?php page_bottom(); ?>
