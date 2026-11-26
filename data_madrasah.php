<?php
/**
 * e-PKKM - DATA MADRASAH (khusus Super Admin).
 *
 * Permintaan pemilik: menu halaman admin dirapikan menjadi
 *   Dashboard, Persetujuan Madrasah, Layanan, **Data Madrasah** (daftar madrasah dalam bentuk tabel),
 *   Daftar Penilai, Penilaian Kepala Madrasah, Laporan, Rekap.
 *
 * Halaman ini menampilkan SELURUH madrasah dalam SATU TABEL lengkap (identitas, kepala madrasah,
 * penilai 1 & 2, progres penilaian, status) beserta aksi cepat. Peninjauan/persetujuan pendaftaran
 * tetap di halaman "Persetujuan Madrasah" (sekolah.php).
 */
require_once __DIR__ . '/inc/init.php';
$u = require_super();

// ------------------------------------------------------------ saringan -----
$cari = trim((string)($_GET['cari'] ?? ''));
$status = (string)($_GET['status'] ?? 'semua');
if (!in_array($status, ['semua', 'aktif', 'nonaktif', 'menunggu', 'ditolak'], true)) {
    $status = 'semua';
}

// ------------------------------------------------------------- data --------
$semua = all('SELECT * FROM sekolah ORDER BY aktif DESC, nama');
$baris = [];
foreach ($semua as $d) {
    if ($cari !== '') {
        $teks = strtolower(implode(' ', [
            (string)$d['nama'], (string)$d['npsn'], (string)$d['nsm'], (string)$d['kepala_nama'],
            (string)$d['kecamatan'], (string)$d['kabupaten'],
        ]));
        if (!str_contains($teks, strtolower($cari))) {
            continue;
        }
    }
    $stDaftar = daftar_status_sekolah($d);
    if ($status === 'aktif' && !(int)$d['aktif']) {
        continue;
    }
    if ($status === 'nonaktif' && (int)$d['aktif']) {
        continue;
    }
    if (in_array($status, ['menunggu', 'ditolak'], true) && $stDaftar !== $status) {
        continue;
    }
    $baris[] = $d;
}

// ringkasan (dihitung dari SELURUH madrasah, bukan hasil saringan)
$jmlAktif = 0;
$jmlNonaktif = 0;
$jmlLengkap = 0;
foreach ($semua as $d) {
    if ((int)$d['aktif']) {
        $jmlAktif++;
    } else {
        $jmlNonaktif++;
    }
    $pr = progres_sekolah((int)$d['id']);
    if ($pr['total'] > 0 && $pr['selesai'] >= $pr['total']) {
        $jmlLengkap++;
    }
}

$GLOBALS['__vs_istilah_nonaktif'] = true;   // halaman lintas madrasah → istilah generik

page_top('Data Madrasah', 'Super Admin &middot; seluruh madrasah dalam satu tabel.');
?>
<div class="grid g4">
    <div class="card stat"><?= ikon('madrasah', 20) ?><div>
        <div class="stat-angka"><?= count($semua) ?></div><div class="stat-label">Madrasah terdaftar</div></div></div>
    <div class="card stat"><?= ikon('centang', 20) ?><div>
        <div class="stat-angka"><?= $jmlAktif ?></div><div class="stat-label">Aktif</div></div></div>
    <div class="card stat"><?= ikon('kunci', 20) ?><div>
        <div class="stat-angka"><?= $jmlNonaktif ?></div><div class="stat-label">Nonaktif</div></div></div>
    <div class="card stat"><?= ikon('nilai', 20) ?><div>
        <div class="stat-angka"><?= $jmlLengkap ?></div><div class="stat-label">Penilaian lengkap</div></div></div>
</div>

<div class="card">
    <div class="card-kepala"><h2>Daftar Madrasah</h2>
        <div class="aksi">
            <a class="btn btn-kecil btn-emas" href="sekolah.php"><?= ikon('madrasah', 15) ?> Kelola Madrasah<?php
                $dm = daftar_menunggu_jumlah();
                echo $dm > 0 ? ' (' . $dm . ')' : ''; ?></a>
            <a class="btn btn-kecil btn-ghost" href="data_penilai.php"><?= ikon('guru', 15) ?> Daftar Penilai</a>
        </div>
    </div>

    <form method="get" action="data_madrasah.php" class="saring" id="formSaringMadrasah">
        <div class="saring-item">
            <label for="saringCari">Cari</label>
            <input type="text" id="saringCari" name="cari" value="<?= e($cari) ?>"
                   placeholder="nama madrasah / NPSN / kepala madrasah">
        </div>
        <div class="saring-item">
            <label for="saringStatus">Status</label>
            <select id="saringStatus" name="status" data-auto>
                <?php foreach (['semua' => 'Semua status', 'aktif' => 'Aktif', 'nonaktif' => 'Nonaktif',
                               'menunggu' => 'Menunggu persetujuan', 'ditolak' => 'Ditolak'] as $k => $lb): ?>
                    <option value="<?= e($k) ?>" <?= $status === $k ? 'selected' : '' ?>><?= e($lb) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="saring-aksi">
            <button class="btn btn-kecil" type="submit"><?= ikon('panah', 15) ?> Terapkan</button>
            <?php if ($cari !== '' || $status !== 'semua'): ?>
                <a class="btn btn-kecil btn-ghost" href="data_madrasah.php">Reset</a>
            <?php endif; ?>
        </div>
    </form>

    <?php if (!$baris): ?>
        <p class="mut mb0">Tidak ada madrasah yang cocok dengan saringan.
            <?php if ($cari === '' && $status === 'semua'): ?>
                Lembaga ditarik dari aplikasi Supervisi pada menu
                <a href="tarik_supervisi.php">Tarik dari Supervisi</a>.
            <?php endif; ?>
        </p>
    <?php else: ?>
    <p class="ket">Menampilkan <b><?= count($baris) ?></b> dari <?= count($semua) ?> madrasah.
        Klik <b>Buka</b> untuk masuk ke dashboard madrasah tersebut.</p>
    <div class="geser"><table class="tbl" id="tblDataMadrasah">
        <thead><tr>
            <th class="tengah">No</th>
            <th>Nama Madrasah</th>
            <th>NPSN / NSM</th>
            <th>Kepala Madrasah</th>
            <th>Kecamatan</th>
            <th class="tengah">Penilai 1</th>
            <th class="tengah">Penilai 2</th>
            <th class="tengah">Penilai Tahun Keempat</th>
            <th class="tengah">Progres</th>
            <th class="tengah">Status</th>
            <th class="tengah">Aksi</th>
        </tr></thead>
        <tbody>
        <?php foreach ($baris as $i => $d):
            $did = (int)$d['id'];
            $pr = progres_sekolah($did);
            $pn = penilai_daftar_sekolah($did);
            $slotJml = penilai_sekolah_slot_jumlah($did); ?>
            <tr>
                <td class="tengah"><?= $i + 1 ?></td>
                <td><b><?= e($d['nama']) ?></b> <?= status_lembaga_pil($d['status_lembaga'] ?? null) ?>
                    <?php $tglDaftar = substr((string)($d['daftar_pada'] ?? ''), 0, 10); ?>
                    <?php if ($tglDaftar !== '' && tanggal_id($tglDaftar) !== ''): ?>
                        <div class="kecil mut">mendaftar <?= e(tanggal_id($tglDaftar)) ?></div>
                    <?php endif; ?></td>
                <td class="kecil"><?= e($d['npsn'] ?: '-') ?><div class="mut"><?= e($d['nsm'] ?: 'NSM -') ?></div></td>
                <td><?= e($d['kepala_nama'] ?: '-') ?>
                    <?php if (trim((string)($d['kepala_nip'] ?? '')) !== ''): ?>
                        <div class="kecil mut">NIP <?= e($d['kepala_nip']) ?></div>
                    <?php endif; ?></td>
                <td class="kecil"><?= e($d['kecamatan'] ?: '-') ?></td>
                <td class="tengah kecil"><?= $pn['1']['nama'] !== ''
                    ? e($pn['1']['nama']) . '<div class="mut">' . e($pn['1']['jabatan'] ?: 'jabatan -') . '</div>'
                    : '<span class="mut">belum ada</span>' ?></td>
                <td class="tengah kecil"><?= $pn['2']['nama'] !== ''
                    ? e($pn['2']['nama']) . '<div class="mut">' . e($pn['2']['jabatan'] ?: 'jabatan -') . '</div>'
                    : '<span class="mut">belum ada</span>' ?></td>
                <td class="tengah"><?= (int)$slotJml['isi'] ?>/<?= (int)$slotJml['total'] ?> terisi
                    <div class="kecil mut"><?= (int)$slotJml['nilai'] ?> bernilai</div></td>
                <td class="tengah"><?= (int)$pr['selesai'] ?>/<?= (int)$pr['total'] ?>
                    <div class="kecil mut"><?= (int)$pr['persen'] ?>%</div></td>
                <td class="tengah">
                    <?= (int)$d['aktif']
                        ? '<span class="pil pil-unggul">Aktif</span>'
                        : '<span class="pil pil-kurang">Nonaktif</span>' ?>
                    <div class="mt8"><?= daftar_pil($d) ?></div>
                </td>
                <td class="tengah nowrap">
                    <?php if ((int)$d['aktif']): ?>
                        <a class="btn btn-kecil" href="index.php?masuk=<?= $did ?>">Buka</a>
                    <?php endif; ?>
                    <a class="btn btn-kecil btn-ghost" href="sekolah.php?edit=<?= $did ?>">Ubah</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr>
            <td colspan="2">Jumlah madrasah ditampilkan</td>
            <td colspan="2" class="tengah"><?= count($baris) ?> madrasah</td>
            <td colspan="7"></td>
        </tr></tfoot>
    </table></div>
    <?php endif; ?>
</div>
<?php
page_bottom();
