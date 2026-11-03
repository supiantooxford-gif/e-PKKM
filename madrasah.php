<?php
/**
 * e-PKKM - Input Data Madrasah: identitas madrasah, kepala madrasah, tahun & tanggal
 * penilaian tiap tahun, tempat penilaian, serta data Pengawas I dan Pengawas II.
 *
 * Boleh mengisi: kepala madrasah (admin) dan Super Admin dalam konteks.
 * Pengawas hanya membaca (datanya dipakai pada lembar cetak).
 */
require_once __DIR__ . '/inc/init.php';
$u = require_login();
$s = require_sekolah();
$sid = (int)$s['id'];
$bolehUbah = in_array($u['role'], ['admin', 'super'], true);
// Tahun & tanggal penilaian serta data penilai 1 & 2 DIHAPUS dari akun kepala madrasah
// (permintaan pemilik): tahun/tanggal ditetapkan Admin aplikasi, sedangkan data penilai diisi
// oleh penilai masing-masing lewat halaman Dashboard Penilai.
$bolehUbahPenilai = ($u['role'] === 'super');

$medanMadrasah = ['nama', 'status_lembaga', 'npsn', 'nsm', 'alamat', 'desa', 'kecamatan', 'kabupaten', 'provinsi',
    'telepon', 'email', 'website', 'kepala_nama', 'kepala_nip', 'kepala_gender', 'kepala_lahir',
    'kepala_pangkat', 'kepala_tmt', 'kepala_masa_kerja', 'kepala_nuptk', 'kepala_pendidikan',
    'tahun_1', 'tahun_2', 'tahun_3', 'tahun_4', 'tempat'];
if ($u['role'] !== 'super') {
    // kepala madrasah tidak lagi mengirim tahun penilaian
    $medanMadrasah = array_values(array_diff($medanMadrasah,
        ['tahun_1', 'tahun_2', 'tahun_3', 'tahun_4']));
}
$medanPenilai = ['nama', 'nip', 'jabatan', 'pangkat', 'unit_kerja', 'kabupaten', 'provinsi', 'tempat'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$bolehUbah) {
        http_response_code(403);
        page_ditolak('Hanya kepala madrasah atau Super Admin yang dapat mengubah data madrasah.');
    }
    if (!csrf_ok()) {
        tolak_csrf();
    }
    $aksi = (string)($_POST['aksi'] ?? '');

    if ($aksi === 'simpan_penilai' && !$bolehUbahPenilai) {
        http_response_code(403);
        page_ditolak('Data penilai tidak lagi diisi dari akun kepala madrasah. Penilai mengisi sendiri '
            . 'melalui halaman Dashboard Penilai setelah mendaftar.');
    }

    if ($aksi === 'simpan_madrasah') {
        $nilai = [];
        foreach ($medanMadrasah as $m) {
            $v = trim((string)($_POST[$m] ?? ''));
            if ($m === 'status_lembaga') {
                // Negeri / Swasta — menentukan susunan 7 penilai Tahun Keempat
                $nilai[$m] = status_lembaga_sah($v);
            } elseif (in_array($m, ['tahun_1', 'tahun_2', 'tahun_3', 'tahun_4'], true)) {
                $v = preg_replace('/\D/', '', $v);
                $nilai[$m] = $v === '' ? null : (int)$v;
            } elseif (in_array($m, ['tanggal_1', 'tanggal_2', 'tanggal_3', 'tanggal_4'], true)) {
                $nilai[$m] = $v;
            } else {
                $nilai[$m] = bersih_teks($v, $m === 'alamat' ? 200 : PKKM_TEKS_PENDEK);
            }
        }
        if ($bolehUbahPenilai) {
            foreach (['tanggal_1', 'tanggal_2', 'tanggal_3', 'tanggal_4'] as $t) {
                $iso = tanggal_iso((string)($_POST[$t] ?? ''));
                $nilai[$t] = $iso;
            }
        }
        if ($nilai['nama'] === '') {
            flash('Nama madrasah wajib diisi.', 'err');
            redirect('madrasah.php');
        }
        $set = [];
        $args = [];
        foreach ($nilai as $k => $v) {
            $set[] = $k . ' = ?';
            $args[] = $v;
        }
        $set[] = 'diubah = ?';
        $args[] = date('Y-m-d H:i:s');
        $args[] = $sid;
        q('UPDATE sekolah SET ' . implode(', ', $set) . ' WHERE id = ?', $args);
        flash('Data madrasah tersimpan.');
        redirect('madrasah.php');
    }

    if ($aksi === 'simpan_penilai') {
        $posisi = (string)(int)($_POST['posisi'] ?? 0);
        if (!array_key_exists($posisi, PKKM_PENILAI_PENGAWAS)) {
            flash('Posisi penilai tidak dikenal.', 'err');
            redirect('madrasah.php');
        }
        $isi = [];
        foreach ($medanPenilai as $m) {
            $isi[$m] = bersih_teks((string)($_POST[$m] ?? ''), PKKM_TEKS_PENDEK);
        }
        // tanggal penilaian tiap tahun
        foreach ([1, 2, 3, 4] as $t) {
            $isi['tanggal_' . $t] = tanggal_iso((string)($_POST['tanggal_' . $t] ?? ''));
        }
        $ada = one('SELECT id FROM penilai WHERE sekolah_id = ? AND kategori = ? AND kode = ?', [$sid, 'pengawas', $posisi]);
        if ($ada) {
            $set = [];
            $args = [];
            foreach ($isi as $k => $v) {
                $set[] = $k . ' = ?';
                $args[] = $v;
            }
            $set[] = 'diubah = ?';
            $args[] = date('Y-m-d H:i:s');
            $args[] = (int)$ada['id'];
            q('UPDATE penilai SET ' . implode(', ', $set) . ' WHERE id = ?', $args);
        } else {
            $kolom = array_keys($isi);
            q('INSERT INTO penilai (sekolah_id, kategori, kode, ' . implode(', ', $kolom) . ', dibuat, diubah)
               VALUES (?, ?, ?, ' . implode(', ', array_fill(0, count($kolom), '?')) . ', ?, ?)',
                array_merge([$sid, 'pengawas', $posisi], array_values($isi), [date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]));
        }
        flash('Data ' . PKKM_PENILAI_PENGAWAS[$posisi] . ' tersimpan.');
        redirect('madrasah.php');
    }

    flash('Aksi tidak dikenal.', 'err');
    redirect('madrasah.php');
}

$pen = [];
foreach (['1' => 1, '2' => 2] as $posisi => $urut) {
    $pen[$posisi] = one('SELECT * FROM penilai WHERE sekolah_id = ? AND kategori = ? AND kode = ?', [$sid, 'pengawas', (string)$posisi]) ?: [];
}

page_top('Input Data Madrasah', $bolehUbahPenilai
    ? 'Identitas madrasah, kepala madrasah, waktu penilaian, dan data penilai.'
    : 'Identitas madrasah dan data kepala madrasah.');
if (!$bolehUbah) {
    echo '<div class="alert alert-info">Anda membuka halaman ini dalam mode hanya-lihat.</div>';
}
?>
<form method="post" action="madrasah.php">
    <?= csrf_field() ?>
    <input type="hidden" name="aksi" value="simpan_madrasah">

    <div class="card">
        <div class="card-kepala"><h2>A. Data Madrasah</h2></div>
        <div class="form-2">
            <div><label for="nama">Nama Madrasah</label>
                <input type="text" id="nama" name="nama" required <?= $bolehUbah ? '' : 'disabled' ?>
                       value="<?= e($s['nama']) ?>" placeholder="mis. MA. Sirojuth Tholibiin Mootilango"></div>
            <div><label for="status_lembaga">Status Lembaga</label>
                <?php $statusSekarang = status_lembaga_sekolah($s); ?>
                <select id="status_lembaga" name="status_lembaga" <?= $bolehUbah ? '' : 'disabled' ?>>
                    <?php foreach (PKKM_STATUS_LEMBAGA as $sKunci => $sLabel): ?>
                        <option value="<?= e($sKunci) ?>" <?= $statusSekarang === $sKunci ? 'selected' : '' ?>>
                            <?= e($sLabel) ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="ket mb0">Negeri: penilai Tahun Keempat = Kabid, Kasie, Pengawas 1, Pengawas 2,
                    Guru, Tendik, Komite. Swasta: Yayasan, Kasie, Pengawas 1, Pengawas 2, Guru, Tendik, Komite.</p></div>
            <div><label for="npsn">NPSN</label>
                <input type="text" id="npsn" name="npsn" <?= $bolehUbah ? '' : 'disabled' ?> value="<?= e($s['npsn']) ?>"></div>
            <div><label for="nsm">NSM</label>
                <input type="text" id="nsm" name="nsm" <?= $bolehUbah ? '' : 'disabled' ?> value="<?= e($s['nsm']) ?>"></div>
            <div><label for="alamat">Alamat (Nama Jalan, Dusun)</label>
                <input type="text" id="alamat" name="alamat" <?= $bolehUbah ? '' : 'disabled' ?> value="<?= e($s['alamat']) ?>"></div>
            <div><label for="desa">Desa/Kelurahan</label>
                <input type="text" id="desa" name="desa" <?= $bolehUbah ? '' : 'disabled' ?> value="<?= e($s['desa']) ?>"></div>
            <div><label for="kecamatan">Kecamatan</label>
                <input type="text" id="kecamatan" name="kecamatan" <?= $bolehUbah ? '' : 'disabled' ?> value="<?= e($s['kecamatan']) ?>"></div>
            <div><label for="kabupaten">Kabupaten/Kota</label>
                <input type="text" id="kabupaten" name="kabupaten" <?= $bolehUbah ? '' : 'disabled' ?> value="<?= e($s['kabupaten']) ?>"></div>
            <div><label for="provinsi">Provinsi</label>
                <input type="text" id="provinsi" name="provinsi" <?= $bolehUbah ? '' : 'disabled' ?> value="<?= e($s['provinsi']) ?>"></div>
            <div><label for="telepon">Telp/HP</label>
                <input type="text" id="telepon" name="telepon" <?= $bolehUbah ? '' : 'disabled' ?> value="<?= e($s['telepon']) ?>"></div>
            <div><label for="email">Email</label>
                <input type="email" id="email" name="email" <?= $bolehUbah ? '' : 'disabled' ?> value="<?= e($s['email']) ?>"></div>
            <div><label for="website">Situs/Web</label>
                <input type="text" id="website" name="website" <?= $bolehUbah ? '' : 'disabled' ?> value="<?= e($s['website']) ?>"></div>
            <div><label for="tempat">Tempat Penilaian</label>
                <input type="text" id="tempat" name="tempat" <?= $bolehUbah ? '' : 'disabled' ?> value="<?= e($s['tempat']) ?>"
                       placeholder="mis. Gorontalo"></div>
        </div>
    </div>

    <div class="card">
        <div class="card-kepala"><h2>B. Data Kepala Madrasah</h2></div>
        <div class="form-2">
            <div><label for="kepala_nama">Nama</label>
                <input type="text" id="kepala_nama" name="kepala_nama" <?= $bolehUbah ? '' : 'disabled' ?> value="<?= e($s['kepala_nama']) ?>"></div>
            <div><label for="kepala_nip">NIP</label>
                <input type="text" id="kepala_nip" name="kepala_nip" <?= $bolehUbah ? '' : 'disabled' ?> value="<?= e($s['kepala_nip']) ?>"></div>
            <div><label for="kepala_gender">Jenis Kelamin</label>
                <select id="kepala_gender" name="kepala_gender" <?= $bolehUbah ? '' : 'disabled' ?>>
                    <option value="">-</option>
                    <?php foreach (['Laki-laki', 'Perempuan'] as $g): ?>
                        <option value="<?= e($g) ?>" <?= $s['kepala_gender'] === $g ? 'selected' : '' ?>><?= e($g) ?></option>
                    <?php endforeach; ?>
                </select></div>
            <div><label for="kepala_lahir">Tempat/Tanggal Lahir</label>
                <input type="text" id="kepala_lahir" name="kepala_lahir" <?= $bolehUbah ? '' : 'disabled' ?> value="<?= e($s['kepala_lahir']) ?>"
                       placeholder="mis. Limboto, 23 Agustus 1977"></div>
            <div><label for="kepala_pangkat">Pangkat/Golongan</label>
                <input type="text" id="kepala_pangkat" name="kepala_pangkat" <?= $bolehUbah ? '' : 'disabled' ?> value="<?= e($s['kepala_pangkat']) ?>"></div>
            <div><label for="kepala_tmt">TMT Kepala Madrasah</label>
                <input type="text" id="kepala_tmt" name="kepala_tmt" <?= $bolehUbah ? '' : 'disabled' ?> value="<?= e($s['kepala_tmt']) ?>"></div>
            <div><label for="kepala_masa_kerja">Masa Kerja (tahun)</label>
                <input type="text" id="kepala_masa_kerja" name="kepala_masa_kerja" <?= $bolehUbah ? '' : 'disabled' ?> value="<?= e($s['kepala_masa_kerja']) ?>"></div>
            <div><label for="kepala_nuptk">NUPTK</label>
                <input type="text" id="kepala_nuptk" name="kepala_nuptk" <?= $bolehUbah ? '' : 'disabled' ?> value="<?= e($s['kepala_nuptk']) ?>"></div>
            <div><label for="kepala_pendidikan">Pendidikan Terakhir</label>
                <input type="text" id="kepala_pendidikan" name="kepala_pendidikan" <?= $bolehUbah ? '' : 'disabled' ?> value="<?= e($s['kepala_pendidikan']) ?>"></div>
        </div>
    </div>

    <?php if ($bolehUbahPenilai): ?>
    <div class="card">
        <div class="card-kepala"><h2>C. Tahun &amp; Tanggal Penilaian</h2></div>
        <p class="ket">Empat tahun penilaian PKKM. Tanggal penilaian dipakai pada lembar cetak laporan/rekap.</p>
        <div class="geser"><table class="tbl">
            <thead><tr><th>Tahun Ke-</th><th>Tahun</th><th>Tanggal Penilaian</th></tr></thead>
            <tbody>
            <?php foreach (PKKM_TAHUN_LIST as $no => $label): ?>
                <tr>
                    <td><b><?= e($label) ?></b></td>
                    <td style="max-width:140px"><input type="number" min="2000" max="2100" name="tahun_<?= $no ?>"
                        value="<?= e((string)($s['tahun_' . $no] ?? '')) ?>"
                        placeholder="mis. <?= 2024 + $no - 1 ?>"></td>
                    <td style="max-width:240px"><input type="date" name="tanggal_<?= $no ?>"
                        value="<?= e((string)($s['tanggal_' . $no] ?? '')) ?>"></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    </div>
    <?php endif; ?>

    <?php if ($bolehUbah): ?>
        <div class="form-aksi"><button class="btn" type="submit"><?= ikon('simpan', 16) ?> Simpan Data Madrasah</button></div>
    <?php endif; ?>
</form>

<?php if (!$bolehUbahPenilai):
    $penSekarang = penilai_daftar_sekolah($sid); ?>
<div class="card" id="cardPenilaiMadrasah">
    <div class="card-kepala"><h2>C. Data Penilai 1 &amp; 2</h2>
        <span class="kecil mut">Diisi oleh penilai masing-masing</span>
    </div>
    <p class="ket">Data penilai tidak lagi diisi dari akun kepala madrasah. Penilai mendaftar sendiri lewat
        halaman <b>Pendaftaran Penilai</b>, lalu memilih madrasah ini beserta posisinya
        (Penilai 1 atau Penilai 2) pada <b>Dashboard Penilai</b>.
        Lima penilai lainnya (Kabid/Yayasan, Kasie, Guru, Tendik, Komite) juga memilih madrasah ini
        pada <b>Dashboard Penilai</b> &mdash; jumlah penilai Tahun Keempat seluruhnya <b>7 orang</b>,
        susunannya mengikuti <b>status lembaga</b>.</p>
    <?php $slotMadrasahIni = penilai_sekolah_slots($sid); ?>
    <p class="ket">Penilai Tahun Keempat (7 penilai, mengikuti status lembaga
        <b><?= e(status_lembaga_label(status_lembaga_sekolah($sid))) ?></b>):
        <?php $namaSlot = [];
        foreach ($slotMadrasahIni as $sl) {
            $namaSlot[] = $sl['label'] . ' = ' . ($sl['akun'] ? (string)$sl['akun']['nama'] : 'belum diisi');
        }
        echo e(implode('; ', $namaSlot)); ?>.</p>
    <div class="grid g2">
        <?php foreach (['1', '2'] as $pk): $pd = $penSekarang[$pk]; ?>
            <div class="penilai-slot">
                <div class="penilai-slot-kepala">
                    <b><?= e(penilai_posisi_teks($pk)) ?></b>
                    <span class="kecil mut"><?= e(PKKM_PENILAI_PENGAWAS[$pk]) ?></span>
                </div>
                <?php if ($pd['nama'] !== ''): ?>
                    <div class="penilai-slot-nama"><?= e($pd['nama']) ?></div>
                    <div class="kecil mut">
                        <?= e($pd['jabatan'] !== '' ? $pd['jabatan'] : 'jabatan belum diisi') ?>
                        <?= $pd['nip'] !== '' ? ' &middot; NIP ' . e($pd['nip']) : '' ?>
                        <?= $pd['unit_kerja'] !== '' ? ' &middot; ' . e($pd['unit_kerja']) : '' ?>
                    </div>
                <?php else: ?>
                    <p class="mut mb0 kecil">Belum ada penilai pada posisi ini.</p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php else: ?>
<?php foreach (['1' => 1, '2' => 2] as $posisi => $urut): $p = $pen[$posisi]; ?>
<form method="post" action="madrasah.php">
    <?= csrf_field() ?>
    <input type="hidden" name="aksi" value="simpan_penilai">
    <input type="hidden" name="posisi" value="<?= $posisi ?>">
    <div class="card">
        <div class="card-kepala"><h2>D<?= $urut ?>. Data Penilai <?= $posisi ?> (<?= e(PKKM_PENILAI_PENGAWAS[$posisi]) ?>)</h2></div>
        <div class="form-2">
            <div><label for="pnama<?= $posisi ?>">Nama</label>
                <input type="text" id="pnama<?= $posisi ?>" name="nama" <?= $bolehUbah ? '' : 'disabled' ?>
                       value="<?= e($p['nama'] ?? '') ?>"></div>
            <div><label for="pnip<?= $posisi ?>">NIP</label>
                <input type="text" id="pnip<?= $posisi ?>" name="nip" <?= $bolehUbah ? '' : 'disabled' ?>
                       value="<?= e($p['nip'] ?? '') ?>"></div>
            <div><label for="pjab<?= $posisi ?>">Jabatan</label>
                <input type="text" id="pjab<?= $posisi ?>" name="jabatan" <?= $bolehUbah ? '' : 'disabled' ?>
                       value="<?= e($p['jabatan'] ?? '') ?>" placeholder="mis. Pengawas Madya"></div>
            <div><label for="ppang<?= $posisi ?>">Pangkat/Golongan</label>
                <input type="text" id="ppang<?= $posisi ?>" name="pangkat" <?= $bolehUbah ? '' : 'disabled' ?>
                       value="<?= e($p['pangkat'] ?? '') ?>"></div>
            <div><label for="punit<?= $posisi ?>">Unit Kerja</label>
                <input type="text" id="punit<?= $posisi ?>" name="unit_kerja" <?= $bolehUbah ? '' : 'disabled' ?>
                       value="<?= e($p['unit_kerja'] ?? '') ?>" placeholder="mis. Kantor Kemenag Kab. Gorontalo"></div>
            <div><label for="pkab<?= $posisi ?>">Kabupaten/Kota</label>
                <input type="text" id="pkab<?= $posisi ?>" name="kabupaten" <?= $bolehUbah ? '' : 'disabled' ?>
                       value="<?= e($p['kabupaten'] ?? '') ?>"></div>
            <div><label for="pprov<?= $posisi ?>">Provinsi</label>
                <input type="text" id="pprov<?= $posisi ?>" name="provinsi" <?= $bolehUbah ? '' : 'disabled' ?>
                       value="<?= e($p['provinsi'] ?? '') ?>"></div>
            <div><label for="ptmp<?= $posisi ?>">Tempat Penilaian</label>
                <input type="text" id="ptmp<?= $posisi ?>" name="tempat" <?= $bolehUbah ? '' : 'disabled' ?>
                       value="<?= e($p['tempat'] ?? '') ?>"></div>
        </div>
        <h4 class="mt16">Tanggal Penilaian per Tahun</h4>
        <div class="geser"><table class="tbl tbl-padat">
            <thead><tr><?php foreach (PKKM_TAHUN_LIST as $label): ?><th><?= e($label) ?></th><?php endforeach; ?></tr></thead>
            <tbody><tr>
                <?php foreach (array_keys(PKKM_TAHUN_LIST) as $t): ?>
                    <td><input type="date" name="tanggal_<?= $t ?>" <?= $bolehUbah ? '' : 'disabled' ?>
                               value="<?= e((string)($p['tanggal_' . $t] ?? '')) ?>"></td>
                <?php endforeach; ?>
            </tr></tbody>
        </table></div>
        <?php if ($bolehUbah): ?>
            <div class="form-aksi"><button class="btn btn-emas" type="submit"><?= ikon('simpan', 16) ?> Simpan Penilai <?= $posisi ?></button></div>
        <?php endif; ?>
    </div>
</form>
<?php endforeach; ?>
<?php endif; ?>
<?php
page_bottom();
