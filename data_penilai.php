<?php
/**
 * e-PKKM - DAFTAR PENILAI (khusus Super Admin).
 *
 * Permintaan pemilik: menu halaman admin memuat **Daftar Penilai** — "Daftar Penilai dalam bentuk
 * tabel lengkap dengan jabatannya".
 *
 * Tabel ini memuat seluruh akun penilai (peran `pengawas`): nama, JABATAN, NIP, unit kerja,
 * kabupaten/kota, tahun penilaian yang boleh diisi (sesuai aturan: jabatan selain Pengawas hanya
 * Tahun Keempat), madrasah yang dinilai beserta posisinya, status akun, dan aksi.
 * Penambahan/pengubahan akun tetap dilakukan di halaman **Akun Penilai** (pengawas.php).
 */
require_once __DIR__ . '/inc/init.php';
$u = require_super();

// ------------------------------------------------------------ saringan -----
$cari = trim((string)($_GET['cari'] ?? ''));
$jabatanSaring = trim((string)($_GET['jabatan'] ?? ''));
$statusSaring = (string)($_GET['status'] ?? 'semua');
if (!in_array($statusSaring, ['semua', 'belum', 'aktif', 'nonaktif'], true)) {
    $statusSaring = 'semua';
}

// ------------------------------------------------------------- data --------
$semuaPenilai = all("SELECT * FROM users WHERE role = 'pengawas' ORDER BY nama");
$baris = [];
foreach ($semuaPenilai as $p) {
    $tugas = all('SELECT ps.posisi, s.nama, s.status_lembaga
                   FROM pengawas_sekolah ps JOIN sekolah s ON s.id = ps.sekolah_id
                   WHERE ps.user_id = ? ORDER BY s.nama', [(int)$p['id']]);
    if ($cari !== '') {
        $teks = strtolower(implode(' ', [
            (string)$p['nama'], (string)$p['username'], (string)$p['nip'],
            (string)$p['jabatan'], (string)$p['instansi'], (string)$p['kab_kota'],
        ]));
        foreach ($tugas as $t) {
            $teks .= ' ' . strtolower((string)$t['nama']);
        }
        if (!str_contains($teks, strtolower($cari))) {
            continue;
        }
    }
    $jab = trim((string)($p['jabatan'] ?? ''));
    if ($jabatanSaring === '__kosong' && $jab !== '') {
        continue;
    }
    if ($jabatanSaring !== '' && $jabatanSaring !== '__kosong'
        && strcasecmp($jab, (string)$jabatanSaring) !== 0) {
        continue;
    }
    if ($statusSaring === 'belum' && $tugas) {
        continue;
    }
    if ($statusSaring === 'aktif' && !(int)$p['aktif']) {
        continue;
    }
    if ($statusSaring === 'nonaktif' && (int)$p['aktif']) {
        continue;
    }
    $p['__tugas'] = $tugas;
    $baris[] = $p;
}

$daftarJabatan = jabatan_penilai_list(false);
$jmlPengawas = 0;
$jmlBelumTugas = 0;
$jmlNonaktif = 0;
foreach ($semuaPenilai as $p) {
    if (penilai_jabatan_pengawas($p)) {
        $jmlPengawas++;
    }
    if (!(int)$p['aktif']) {
        $jmlNonaktif++;
    }
    $ada = (int)val('SELECT COUNT(*) FROM pengawas_sekolah WHERE user_id = ?', [(int)$p['id']]);
    if ($ada === 0) {
        $jmlBelumTugas++;
    }
}

$GLOBALS['__vs_istilah_nonaktif'] = true;   // halaman lintas madrasah → istilah generik

page_top('Daftar Penilai', 'Super Admin &middot; seluruh akun penilai beserta jabatannya.');
?>
<div class="grid g4">
    <div class="card stat"><?= ikon('guru', 20) ?><div>
        <div class="stat-angka"><?= count($semuaPenilai) ?></div><div class="stat-label">Akun penilai</div></div></div>
    <div class="card stat"><?= ikon('pengawas', 20) ?><div>
        <div class="stat-angka"><?= $jmlPengawas ?></div><div class="stat-label">Berjabatan pengawas (Tahun 1–4)</div></div></div>
    <div class="card stat"><?= ikon('nilai', 20) ?><div>
        <div class="stat-angka"><?= count($semuaPenilai) - $jmlPengawas ?></div>
        <div class="stat-label">Jabatan lain (hanya Tahun Keempat)</div></div></div>
    <div class="card stat"><?= ikon('madrasah', 20) ?><div>
        <div class="stat-angka"><?= $jmlBelumTugas ?></div><div class="stat-label">Belum memilih madrasah</div></div></div>
</div>

<div class="card">
    <div class="card-kepala"><h2>Daftar Penilai</h2>
        <div class="aksi">
            <a class="btn btn-kecil btn-emas" href="pengawas.php"><?= ikon('tambah', 15) ?> Tambah / Ubah Akun Penilai</a>
            <a class="btn btn-kecil btn-ghost" href="jabatan.php"><?= ikon('nilai', 15) ?> Daftar Jabatan Penilai</a>
        </div>
    </div>
    <p class="ket"><b>Aturan tahun penilaian</b> (permintaan pemilik): penilai berjabatan <b>Pengawas</b>
        boleh menilai <b>Tahun Pertama s.d. Keempat</b> pada posisi <b>Penilai 1 / Penilai 2</b>;
        jabatan lain menempati <b>satu slot Penilai Tahun Keempat</b> sesuai <b>status lembaga</b> madrasah
        &mdash; <b>Negeri</b>: Kabid, Kasie, Pengawas 1, Pengawas 2, Guru, Tendik, Komite;
        <b>Swasta</b>: Yayasan, Kasie, Pengawas 1, Pengawas 2, Guru, Tendik, Komite (total <b>7 penilai</b>).
        Madrasah itu harus <b>Tahun Pertama s.d. Ketiga-nya sudah lengkap seluruh butir</b>. Akun penilai lama
        yang jabatannya belum diisi dianggap pengawas &mdash; lengkapi jabatannya lewat tombol <b>Ubah</b>.</p>

    <form method="get" action="data_penilai.php" class="saring" id="formSaringPenilai">
        <div class="saring-item">
            <label for="saringCari">Cari</label>
            <input type="text" id="saringCari" name="cari" value="<?= e($cari) ?>"
                   placeholder="nama / nama pengguna / NIP / madrasah">
        </div>
        <div class="saring-item">
            <label for="saringJabatan">Jabatan</label>
            <select id="saringJabatan" name="jabatan" data-auto>
                <option value="">Semua jabatan</option>
                <?php foreach ($daftarJabatan as $j): ?>
                    <option value="<?= e($j['nama']) ?>" <?= $jabatanSaring === (string)$j['nama'] ? 'selected' : '' ?>>
                        <?= e($j['nama']) ?></option>
                <?php endforeach; ?>
                <option value="__kosong" <?= $jabatanSaring === '__kosong' ? 'selected' : '' ?>>— jabatan belum diisi —</option>
            </select>
        </div>
        <div class="saring-item">
            <label for="saringStatus">Penugasan / status</label>
            <select id="saringStatus" name="status" data-auto>
                <?php foreach (['semua' => 'Semua penilai', 'belum' => 'Belum memilih madrasah',
                               'aktif' => 'Akun aktif', 'nonaktif' => 'Akun nonaktif'] as $k => $lb): ?>
                    <option value="<?= e($k) ?>" <?= $statusSaring === $k ? 'selected' : '' ?>><?= e($lb) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="saring-aksi">
            <button class="btn btn-kecil" type="submit"><?= ikon('panah', 15) ?> Terapkan</button>
            <?php if ($cari !== '' || $jabatanSaring !== '' || $statusSaring !== 'semua'): ?>
                <a class="btn btn-kecil btn-ghost" href="data_penilai.php">Reset</a>
            <?php endif; ?>
        </div>
    </form>

    <?php if (!$baris): ?>
        <p class="mut mb0">Tidak ada penilai yang cocok dengan saringan.
            <?php if (!$semuaPenilai): ?>
                Penilai mendaftar sendiri lewat <a href="daftar_penilai.php" target="_blank" rel="noopener">halaman
                Pendaftaran Penilai</a>, atau ditambahkan lewat menu <b>Akun Penilai</b>.
            <?php endif; ?>
        </p>
    <?php else: ?>
    <p class="ket">Menampilkan <b><?= count($baris) ?></b> dari <?= count($semuaPenilai) ?> akun penilai
        (<?= $jmlNonaktif ?> nonaktif).</p>
    <div class="geser"><table class="tbl" id="tblDaftarPenilai">
        <thead><tr>
            <th class="tengah">No</th>
            <th>Nama Penilai</th>
            <th>Jabatan</th>
            <th>NIP</th>
            <th>Unit Kerja / Instansi</th>
            <th class="tengah">Tahun yang Dinilai</th>
            <th>Madrasah &amp; Posisi</th>
            <th class="tengah">Status</th>
            <th class="tengah">Aksi</th>
        </tr></thead>
        <tbody>
        <?php foreach ($baris as $i => $p):
            $isPengawas = penilai_jabatan_pengawas($p);
            $jab = trim((string)($p['jabatan'] ?? '')); ?>
            <tr>
                <td class="tengah"><?= $i + 1 ?></td>
                <td><b><?= e($p['nama']) ?></b>
                    <div class="kecil mut"><?= e($p['username']) ?>
                        <?= (string)($p['asal'] ?? '') === 'pendaftaran' ? ' &middot; dari pendaftaran' : '' ?></div></td>
                <td>
                    <?php if ($jab !== ''): ?>
                        <span class="penilai-jabatan-chip<?= $isPengawas ? ' jabatan-pengawas' : '' ?>"><?= e($jab) ?></span>
                    <?php else: ?>
                        <span class="pil pil-kosong">belum diisi</span>
                    <?php endif; ?>
                </td>
                <td class="kecil"><?= trim((string)($p['nip'] ?? '')) !== '' ? e($p['nip']) : '<span class="mut">-</span>' ?></td>
                <td class="kecil"><?= trim((string)($p['instansi'] ?? '')) !== '' ? e($p['instansi']) : '<span class="mut">-</span>' ?>
                    <?php if (trim((string)($p['kab_kota'] ?? '')) !== ''): ?>
                        <div class="mut"><?= e($p['kab_kota']) ?></div>
                    <?php endif; ?></td>
                <td class="tengah">
                    <?php if ($isPengawas): ?>
                        <span class="pil pil-unggul">Tahun 1 – 4</span>
                    <?php else: ?>
                        <span class="pil pil-cukup">Hanya Tahun 4</span>
                    <?php endif; ?>
                </td>
                <td class="kecil">
                    <?php if (!$p['__tugas']): ?>
                        <span class="mut">belum memilih madrasah</span>
                    <?php else: ?>
                        <?php foreach ($p['__tugas'] as $t): ?>
                            <div><?= e($t['nama']) ?> &mdash; <b><?= e(penilai_posisi_teks((string)(int)$t['posisi'],
                                status_lembaga_sah($t['status_lembaga'] ?? ''))) ?></b>
                                <?= status_lembaga_pil($t['status_lembaga'] ?? null) ?></div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </td>
                <td class="tengah">
                    <?= (int)$p['aktif']
                        ? '<span class="pil pil-unggul">Aktif</span>'
                        : '<span class="pil pil-kurang">Nonaktif</span>' ?>
                </td>
                <td class="tengah nowrap">
                    <a class="btn btn-kecil btn-ghost" href="pengawas.php?edit=<?= (int)$p['id'] ?>">Ubah</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr>
            <td colspan="6">Jumlah penilai ditampilkan</td>
            <td colspan="3"><b><?= count($baris) ?></b> akun</td>
        </tr></tfoot>
    </table></div>
    <?php endif; ?>
</div>
<?php
page_bottom();
