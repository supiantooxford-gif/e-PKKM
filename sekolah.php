<?php
/**
 * e-PKKM - PERSETUJUAN MADRASAH (khusus Super Admin).
 *
 * Tambah madrasah manual DIHAPUS (permintaan pemilik): madrasah mendaftar sendiri lewat halaman
 * publik `daftar.php` dengan mengisi identitas lembaga + akun kepala madrasah. Di halaman ini
 * Super Admin menyetujui/mengaktifkan, menolak (ber-alasan), mengubah data, mengelola akun,
 * menonaktifkan, menghapus, dan masuk konteks madrasah.
 */
require_once __DIR__ . '/inc/init.php';
$u = require_super();

$medan = ['nama', 'status_lembaga', 'npsn', 'nsm', 'alamat', 'desa', 'kecamatan', 'kabupaten', 'provinsi',
    'telepon', 'email', 'website', 'kepala_nama', 'kepala_nip', 'tempat'];
$medanTahun = ['tahun_1', 'tahun_2', 'tahun_3', 'tahun_4'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) {
        tolak_csrf();
    }
    $aksi = (string)($_POST['aksi'] ?? '');
    $id = (int)($_POST['id'] ?? 0);

    // ---------------- PERSETUJUAN / PENGAKTIFAN PENDAFTAR ----------------
    // Madrasah TIDAK lagi ditambah manual oleh Super Admin: pendaftar mengisi sendiri lewat
    // halaman publik `daftar.php` (identitas lembaga + akun kepala madrasah). Di sini Super Admin
    // menyetujui/mengaktifkan atau menolak pendaftaran tersebut.
    if ($aksi === 'setujui' || $aksi === 'aktifkan') {
        $s2 = $id > 0 ? one('SELECT * FROM sekolah WHERE id = ?', [$id]) : null;
        if (!$s2) {
            flash('Pendaftar tidak ditemukan.', 'err');
            redirect('sekolah.php');
        }
        if (daftar_setujui($id)) {
            flash('Pendaftaran "' . $s2['nama'] . '" DISETUJUI & diaktifkan. '
                . 'Akun kepala madrasah sekarang sudah bisa dipakai masuk.');
        } else {
            flash('Gagal menyetujui pendaftaran. Coba lagi.', 'err');
        }
        redirect('sekolah.php');
    }

    if ($aksi === 'tolak') {
        $s2 = $id > 0 ? one('SELECT * FROM sekolah WHERE id = ?', [$id]) : null;
        if (!$s2) {
            flash('Pendaftar tidak ditemukan.', 'err');
            redirect('sekolah.php');
        }
        $alasan = trim((string)($_POST['alasan'] ?? ''));
        if ($alasan === '') {
            flash('Alasan penolakan wajib diisi agar pendaftar tahu sebabnya.', 'err');
            redirect('sekolah.php#pendaftar');
        }
        if (daftar_tolak($id, $alasan)) {
            flash('Pendaftaran "' . $s2['nama'] . '" ditolak. Akun kepala madrasah tidak dapat masuk.');
        } else {
            flash('Gagal menolak pendaftaran. Coba lagi.', 'err');
        }
        redirect('sekolah.php');
    }

    if ($aksi === 'ubah' && $id > 0) {
        $set = [];
        $args = [];
        foreach ($medan as $m) {
            if ($m === 'nama') {
                $v = bersih_teks((string)($_POST['nama'] ?? ''), PKKM_TEKS_PENDEK);
                if ($v === '') {
                    flash('Nama madrasah wajib diisi.', 'err');
                    redirect('sekolah.php?edit=' . $id);
                }
            } elseif ($m === 'status_lembaga') {
                $v = status_lembaga_sah((string)($_POST['status_lembaga'] ?? ''));
            } else {
                $v = bersih_teks((string)($_POST[$m] ?? ''), PKKM_TEKS_PENDEK);
            }
            $set[] = $m . ' = ?';
            $args[] = $v;
        }
        foreach ($medanTahun as $m) {
            $v = preg_replace('/\D/', '', (string)($_POST[$m] ?? ''));
            $set[] = $m . ' = ?';
            $args[] = $v === '' ? null : (int)$v;
        }
        foreach ([1, 2, 3, 4] as $t) {
            $set[] = 'tanggal_' . $t . ' = ?';
            $args[] = tanggal_iso((string)($_POST['tanggal_' . $t] ?? ''));
        }
        $set[] = 'diubah = ?';
        $args[] = date('Y-m-d H:i:s');
        $args[] = $id;
        q('UPDATE sekolah SET ' . implode(', ', $set) . ' WHERE id = ?', $args);
        flash('Data madrasah tersimpan.');
        redirect('sekolah.php');
    }

    if ($aksi === 'tambah_akun' && $id > 0) {
        $username = trim((string)($_POST['username'] ?? ''));
        $sandi = (string)($_POST['sandi'] ?? '');
        $nama = bersih_teks((string)($_POST['nama'] ?? ''), PKKM_TEKS_PENDEK);
        $peran = (string)($_POST['role'] ?? 'admin');
        if (!in_array($peran, ['admin', 'pengawas'], true)) {
            $peran = 'admin';
        }
        if ($username === '' || strlen($sandi) < 6) {
            flash('Nama pengguna wajib diisi dan kata sandi minimal 6 karakter.', 'err');
            redirect('sekolah.php');
        }
        if (val('SELECT id FROM users WHERE username = ?', [$username])) {
            flash('Nama pengguna "' . $username . '" sudah dipakai.', 'err');
            redirect('sekolah.php');
        }
        q('INSERT INTO users (sekolah_id, nama, username, sandi, role, aktif, dibuat) VALUES (?, ?, ?, ?, ?, 1, ?)',
            [$peran === 'admin' ? $id : null, $nama !== '' ? $nama : $username, $username,
             password_hash($sandi, PASSWORD_DEFAULT), $peran, date('Y-m-d H:i:s')]);
        flash('Akun "' . $username . '" dibuat.');
        redirect('sekolah.php');
    }

    if ($aksi === 'reset_sandi') {
        $akun = (int)($_POST['user_id'] ?? 0);
        $baru = (string)($_POST['sandi_baru'] ?? '');
        if (strlen($baru) < 6) {
            flash('Kata sandi baru minimal 6 karakter.', 'err');
            redirect('sekolah.php');
        }
        $target = one('SELECT * FROM users WHERE id = ?', [$akun]);
        if (!$target || $target['role'] === 'super') {
            flash('Akun tidak ditemukan.', 'err');
            redirect('sekolah.php');
        }
        q('UPDATE users SET sandi = ? WHERE id = ?', [password_hash($baru, PASSWORD_DEFAULT), $akun]);
        flash('Kata sandi akun "' . $target['username'] . '" diperbarui.');
        redirect('sekolah.php');
    }

    if ($aksi === 'toggle' && $id > 0) {
        $s2 = one('SELECT * FROM sekolah WHERE id = ?', [$id]);
        if ($s2) {
            $baru = (int)$s2['aktif'] ? 0 : 1;
            if ($baru === 0 && (int)val('SELECT COUNT(*) FROM sekolah WHERE aktif = 1') <= 1) {
                flash('Tidak dapat menonaktifkan madrasah terakhir yang aktif.', 'err');
            } else {
                q('UPDATE sekolah SET aktif = ?, diubah = ? WHERE id = ?', [$baru, date('Y-m-d H:i:s'), $id]);
                flash($baru ? 'Madrasah diaktifkan kembali.' : 'Madrasah dinonaktifkan (data tetap tersimpan).');
            }
        }
        redirect('sekolah.php');
    }

    if ($aksi === 'hapus' && $id > 0) {
        $s2 = one('SELECT * FROM sekolah WHERE id = ?', [$id]);
        if (!$s2) {
            flash('Madrasah tidak ditemukan.', 'err');
            redirect('sekolah.php');
        }
        if (trim((string)($_POST['konfirmasi'] ?? '')) !== 'HAPUS') {
            flash('Penghapusan dibatalkan: ketik HAPUS untuk menyetujui.', 'err');
            redirect('sekolah.php');
        }
        if ((int)val('SELECT COUNT(*) FROM sekolah') <= 1) {
            flash('Tidak dapat menghapus madrasah terakhir.', 'err');
            redirect('sekolah.php');
        }
        $dampak = sekolah_dampak($id);
        mulai_transaksi();
        try {
            q('DELETE FROM sekolah WHERE id = ?', [$id]);
            selesai_transaksi(true);
            flash('Madrasah "' . $s2['nama'] . '" dihapus permanen (' . $dampak . ').');
        } catch (Throwable $t) {
            selesai_transaksi(false);
            flash('Gagal menghapus: ' . $t->getMessage(), 'err');
        }
        if (sekolah_id() === $id) {
            mulai_sesi();
            unset($_SESSION['ctx_sekolah_id']);
        }
        redirect('sekolah.php');
    }

    flash('Aksi tidak dikenal.', 'err');
    redirect('sekolah.php');
}

/** Ringkasan dampak penghapusan (dihitung SEBELUM data dihapus). */
function sekolah_dampak(int $id): string
{
    $bagian = [
        'akun' => (int)val('SELECT COUNT(*) FROM users WHERE sekolah_id = ?', [$id]),
        'penilaian' => (int)val('SELECT COUNT(*) FROM penilaian WHERE sekolah_id = ?', [$id]),
        'baris skor' => (int)val('SELECT COUNT(*) FROM penilaian_butir WHERE penilaian_id IN (SELECT id FROM penilaian WHERE sekolah_id = ?)', [$id]),
        'data penilai' => (int)val('SELECT COUNT(*) FROM penilai WHERE sekolah_id = ?', [$id]),
        'penugasan pengawas' => (int)val('SELECT COUNT(*) FROM pengawas_sekolah WHERE sekolah_id = ?', [$id]),
    ];
    return implode(', ', array_map(fn($k, $v) => $v . ' ' . $k, array_keys($bagian), array_values($bagian)));
}

$editId = (int)($_GET['edit'] ?? 0);
$editS = $editId > 0 ? one('SELECT * FROM sekolah WHERE id = ?', [$editId]) : null;
$daftar = all('SELECT * FROM sekolah ORDER BY aktif DESC, nama');
$pendaftar = daftar_pendaftar_list('menunggu');
$ditolak = daftar_pendaftar_list('ditolak');

page_top('Persetujuan Madrasah',
    'Super Admin &middot; tinjau pendaftaran madrasah (identitas lembaga + akun kepala madrasah), '
    . 'lalu setujui/aktifkan atau tolak.');
?>
<!-- ============================ PENDAFTAR MENUNGGU PERSETUJUAN ============================ -->
<div class="card" id="pendaftar">
    <div class="card-kepala">
        <h2>Pendaftar Menunggu Persetujuan
            <?php if ($pendaftar): ?><span class="pil pil-cukup"><?= count($pendaftar) ?> baru</span><?php endif; ?>
        </h2>
        <div class="aksi"><a class="btn btn-kecil btn-ghost" href="daftar.php" target="_blank" rel="noopener">
            <?= ikon('buka', 15) ?> Halaman Pendaftaran</a></div>
    </div>
    <?php if (!$pendaftar): ?>
        <p class="mut mb0">Belum ada pendaftar baru. Madrasah mendaftar sendiri lewat halaman
            <b>Pendaftaran Madrasah</b>; pendaftarannya muncul di sini untuk ditinjau.</p>
    <?php else: ?>
        <div class="df-tinjau-grid">
        <?php foreach ($pendaftar as $p): $pid = (int)$p['id']; $akun = daftar_akun_kepala($pid); ?>
            <div class="df-tinjau">
                <div class="df-tinjau-kepala">
                    <b><?= e($p['nama']) ?></b>
                    <?= daftar_pil($p) ?>
                </div>
                <div class="kecil mut mb0">Mendaftar: <?= e($p['daftar_pada'] ? waktu_id($p['daftar_pada']) : '-') ?></div>
                <table class="tbl tbl-padat df-tinjau-tbl">
                    <tbody>
                        <tr><th>Status Lembaga</th><td><?= status_lembaga_pil($p['status_lembaga'] ?? null) ?></td></tr>
                        <tr><th>NPSN / NSM</th><td><?= e(($p['npsn'] ?: '-') . ' / ' . ($p['nsm'] ?: '-')) ?></td></tr>
                        <tr><th>Alamat</th><td><?= e($p['alamat'] ?: '-') ?></td></tr>
                        <tr><th>Desa/Kecamatan</th><td><?= e(($p['desa'] ?: '-') . ' / ' . ($p['kecamatan'] ?: '-')) ?></td></tr>
                        <tr><th>Kabupaten/Provinsi</th><td><?= e(($p['kabupaten'] ?: '-') . ' / ' . ($p['provinsi'] ?: '-')) ?></td></tr>
                        <tr><th>Kontak</th><td><?= e(($p['telepon'] ?: '-') . ' &middot; ' . ($p['email'] ?: '-')) ?></td></tr>
                        <tr><th>Kepala Madrasah</th><td><?= e(($p['kepala_nama'] ?: '-') . ' &middot; NIP ' . ($p['kepala_nip'] ?: '-')) ?></td></tr>
                        <tr><th>Akun kepala</th><td>
                            <?php if ($akun): ?>
                                <b><?= e($akun['username']) ?></b> <span class="kecil mut">(<?= e($akun['nama']) ?>)</span>
                            <?php else: ?>
                                <span class="mut">belum ada akun</span>
                            <?php endif; ?>
                        </td></tr>
                    </tbody>
                </table>
                <div class="df-tinjau-aksi">
                    <form method="post" action="sekolah.php" style="display:inline">
                        <?= csrf_field() ?><input type="hidden" name="aksi" value="setujui">
                        <input type="hidden" name="id" value="<?= $pid ?>">
                        <button class="btn btn-ok btn-kecil" type="submit"
                            data-confirm="Setujui &amp; aktifkan pendaftaran <?= e($p['nama']) ?>? Akun kepala madrasah akan langsung bisa masuk.">
                            <?= ikon('check', 15) ?> Setujui &amp; Aktifkan</button>
                    </form>
                    <a class="btn btn-kecil btn-ghost" href="sekolah.php?edit=<?= $pid ?>">Lihat / Ubah Data</a>
                </div>
                <form method="post" action="sekolah.php" class="df-tolak">
                    <?= csrf_field() ?><input type="hidden" name="aksi" value="tolak">
                    <input type="hidden" name="id" value="<?= $pid ?>">
                    <label for="tolak<?= $pid ?>">Tolak pendaftaran &mdash; alasan</label>
                    <div class="df-tolak-baris">
                        <input type="text" id="tolak<?= $pid ?>" name="alasan" required maxlength="500"
                               placeholder="mis. data lembaga belum lengkap">
                        <button class="btn btn-kecil btn-err" type="submit"
                            data-confirm="Tolak pendaftaran <?= e($p['nama']) ?>?">Tolak</button>
                    </div>
                </form>
            </div>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php if ($ditolak): ?>
<div class="card">
    <div class="card-kepala"><h2>Pendaftar Ditolak</h2></div>
    <div class="geser"><table class="tbl tbl-padat">
        <thead><tr><th>Madrasah</th><th>Akun Kepala</th><th>Alasan Penolakan</th><th>Aksi</th></tr></thead>
        <tbody>
        <?php foreach ($ditolak as $d): $did2 = (int)$d['id']; $ak = daftar_akun_kepala($did2); ?>
            <tr>
                <td><b><?= e($d['nama']) ?></b>
                    <div class="kecil mut"><?= e($d['kecamatan'] ?: '-') ?></div></td>
                <td><?= e($ak['username'] ?? '-') ?></td>
                <td class="kecil"><?= e($d['daftar_alasan'] ?: '-') ?></td>
                <td class="nowrap">
                    <form method="post" action="sekolah.php" style="display:inline">
                        <?= csrf_field() ?><input type="hidden" name="aksi" value="setujui">
                        <input type="hidden" name="id" value="<?= $did2 ?>">
                        <button class="btn btn-kecil btn-ok" type="submit"
                            data-confirm="Setujui ulang pendaftaran <?= e($d['nama']) ?>?">Setujui &amp; Aktifkan</button>
                    </form>
                    <a class="btn btn-kecil btn-ghost" href="sekolah.php?edit=<?= $did2 ?>">Ubah</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</div>
<?php endif; ?>

<?php if ($editS): ?>
<div class="grid g2">
    <div class="card">
        <div class="card-kepala"><h2>Ubah Madrasah</h2>
            <div class="aksi"><a class="btn btn-ghost btn-kecil" href="sekolah.php">Selesai</a></div>
        </div>
        <form method="post" action="sekolah.php">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="ubah">
            <input type="hidden" name="id" value="<?= (int)$editS['id'] ?>">
            <div class="form-2">
                <div><label for="snama">Nama Madrasah</label>
                    <input type="text" id="snama" name="nama" required value="<?= e($editS['nama'] ?? '') ?>"
                           placeholder="mis. MA. Sirojuth Tholibiin Mootilango"></div>
                <div><label for="sstatus">Status Lembaga</label>
                    <?php $statusEdit = status_lembaga_sekolah($editS); ?>
                    <select id="sstatus" name="status_lembaga">
                        <?php foreach (PKKM_STATUS_LEMBAGA as $sKunci => $sLabel): ?>
                            <option value="<?= e($sKunci) ?>" <?= $statusEdit === $sKunci ? 'selected' : '' ?>>
                                <?= e($sLabel) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="ket mb0">Menentukan 7 penilai Tahun Keempat (Negeri: Kabid…, Swasta: Yayasan…).</p></div>
                <div><label for="snpsn">NPSN</label>
                    <input type="text" id="snpsn" name="npsn" value="<?= e($editS['npsn'] ?? '') ?>"></div>
                <div><label for="snsm">NSM</label>
                    <input type="text" id="snsm" name="nsm" value="<?= e($editS['nsm'] ?? '') ?>"></div>
                <div><label for="salamat">Alamat</label>
                    <input type="text" id="salamat" name="alamat" value="<?= e($editS['alamat'] ?? '') ?>"></div>
                <div><label for="sdesa">Desa/Kelurahan</label>
                    <input type="text" id="sdesa" name="desa" value="<?= e($editS['desa'] ?? '') ?>"></div>
                <div><label for="skec">Kecamatan</label>
                    <input type="text" id="skec" name="kecamatan" value="<?= e($editS['kecamatan'] ?? '') ?>"></div>
                <div><label for="skab">Kabupaten/Kota</label>
                    <input type="text" id="skab" name="kabupaten" value="<?= e($editS['kabupaten'] ?? '') ?>"></div>
                <div><label for="sprov">Provinsi</label>
                    <input type="text" id="sprov" name="provinsi" value="<?= e($editS['provinsi'] ?? '') ?>"></div>
                <div><label for="stelp">Telp/HP</label>
                    <input type="text" id="stelp" name="telepon" value="<?= e($editS['telepon'] ?? '') ?>"></div>
                <div><label for="semail">Email</label>
                    <input type="email" id="semail" name="email" value="<?= e($editS['email'] ?? '') ?>"></div>
                <div><label for="sweb">Situs/Web</label>
                    <input type="text" id="sweb" name="website" value="<?= e($editS['website'] ?? '') ?>"></div>
                <div><label for="skep">Nama Kepala Madrasah</label>
                    <input type="text" id="skep" name="kepala_nama" value="<?= e($editS['kepala_nama'] ?? '') ?>"></div>
                <div><label for="skepnip">NIP Kepala Madrasah</label>
                    <input type="text" id="skepnip" name="kepala_nip" value="<?= e($editS['kepala_nip'] ?? '') ?>"></div>
                <div><label for="stempat">Tempat Penilaian</label>
                    <input type="text" id="stempat" name="tempat" value="<?= e($editS['tempat'] ?? '') ?>"></div>
            </div>
            <h4 class="mt16">Tahun &amp; Tanggal Penilaian</h4>
            <div class="form-2">
                <?php foreach (PKKM_TAHUN_LIST as $no => $label): ?>
                    <div><label for="st<?= $no ?>">Tahun <?= e($label) ?></label>
                        <input type="number" id="st<?= $no ?>" name="tahun_<?= $no ?>" min="2000" max="2100"
                               value="<?= e((string)($editS['tahun_' . $no] ?? '')) ?>"></div>
                    <div><label for="sg<?= $no ?>">Tanggal Penilaian <?= e($label) ?></label>
                        <input type="date" id="sg<?= $no ?>" name="tanggal_<?= $no ?>"
                               value="<?= e((string)($editS['tanggal_' . $no] ?? '')) ?>"></div>
                <?php endforeach; ?>
            </div>
            <div class="form-aksi"><button class="btn" type="submit"><?= ikon('simpan', 16) ?> Simpan</button></div>
        </form>
    </div>

    <div class="card">
        <div class="card-kepala"><h2>Akun Madrasah</h2></div>
        <div class="geser"><table class="tbl tbl-padat">
            <thead><tr><th>Nama</th><th>Nama Pengguna</th><th class="tengah">Status</th></tr></thead>
            <tbody>
            <?php foreach (all('SELECT * FROM users WHERE sekolah_id = ? ORDER BY role, nama', [(int)$editS['id']]) as $a): ?>
                <tr><td><?= e($a['nama']) ?></td><td><?= e($a['username']) ?></td>
                    <td class="tengah"><?= (int)$a['aktif'] ? '<span class="pil pil-unggul">Aktif</span>' : '<span class="pil pil-kurang">Nonaktif</span>' ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <form method="post" action="sekolah.php" class="mt16">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="tambah_akun">
            <input type="hidden" name="id" value="<?= (int)$editS['id'] ?>">
            <div class="form-2">
                <div><label for="bnama">Nama</label><input type="text" id="bnama" name="nama"></div>
                <div><label for="buser">Nama Pengguna</label><input type="text" id="buser" name="username" required></div>
                <div><label for="bsandi">Kata Sandi (min 6)</label><input type="text" id="bsandi" name="sandi" required></div>
                <div><label for="bperan">Peran</label>
                    <select id="bperan" name="role"><option value="admin">Kepala Madrasah</option></select></div>
            </div>
            <div class="form-aksi"><button class="btn btn-emas" type="submit"><?= ikon('tambah', 16) ?> Tambah Akun</button></div>
        </form>
        <form method="post" action="sekolah.php" class="mt16">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="reset_sandi">
            <div class="form-2">
                <div><label for="rakun">Akun</label>
                    <select id="rakun" name="user_id">
                        <?php foreach (all('SELECT id, nama, username FROM users WHERE sekolah_id = ? ORDER BY nama', [(int)$editS['id']]) as $a): ?>
                            <option value="<?= (int)$a['id'] ?>"><?= e($a['nama'] . ' (' . $a['username'] . ')') ?></option>
                        <?php endforeach; ?>
                    </select></div>
                <div><label for="rsandi">Kata Sandi Baru (min 6)</label><input type="text" id="rsandi" name="sandi_baru" required></div>
            </div>
            <div class="form-aksi"><button class="btn btn-ghost" type="submit"><?= ikon('kunci', 16) ?> Tetapkan Sandi Baru</button></div>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="card" id="daftarMadrasah">
    <div class="card-kepala"><h2>Daftar Madrasah</h2>
        <div class="aksi">
            <a class="btn btn-kecil btn-ghost" href="pengawas.php"><?= ikon('pengawas', 15) ?> Akun Penilai</a>
        </div>
    </div>
    <p class="ket">Madrasah <b>tidak lagi ditambahkan manual</b> di sini. Pendaftar mengisi sendiri identitas
        lembaga + akun kepala madrasah pada <a href="daftar.php" target="_blank" rel="noopener">halaman
        Pendaftaran Madrasah</a>, lalu Super Admin menyetujui/mengaktifkan pada bagian
        <b>Pendaftar Menunggu Persetujuan</b> di atas.</p>
    <div class="geser"><table class="tbl">
        <thead><tr><th>No</th><th>Madrasah</th><th>Kepala Madrasah</th><th class="tengah">Penilai 1 &amp; 2</th>
            <th class="tengah">Progres</th><th class="tengah">Status</th><th>Aksi</th></tr></thead>
        <tbody>
        <?php foreach ($daftar as $i => $d): $did = (int)$d['id']; $pr = progres_sekolah($did);
            $penSek = penilai_daftar_sekolah($did); ?>
            <tr>
                <td><?= $i + 1 ?></td>
                <td><b><?= e($d['nama']) ?></b> <?= status_lembaga_pil($d['status_lembaga'] ?? null) ?>
                    <div class="kecil mut"><?= e($d['kecamatan'] ?: '-') ?><?= $d['npsn'] ? ' &middot; NPSN ' . e($d['npsn']) : '' ?></div></td>
                <td><?= e($d['kepala_nama'] ?: '-') ?></td>
                <td class="tengah kecil">
                    <?php foreach (['1', '2'] as $p): ?>
                        <div>P<?= $p ?>: <?= e($penSek[$p]['nama'] !== '' ? $penSek[$p]['nama'] : 'belum ditugaskan') ?></div>
                    <?php endforeach; ?>
                </td>
                <td class="tengah"><?= (int)$pr['selesai'] ?>/<?= (int)$pr['total'] ?></td>
                <td class="tengah">
                    <?= (int)$d['aktif'] ? '<span class="pil pil-unggul">Aktif</span>' : '<span class="pil pil-kurang">Nonaktif</span>' ?>
                    <div class="mt8"><?= daftar_pil($d) ?></div>
                </td>
                <td class="nowrap">
                    <?php if (daftar_status_sekolah($d) !== 'disetujui'): ?>
                        <form method="post" action="sekolah.php" style="display:inline">
                            <?= csrf_field() ?><input type="hidden" name="aksi" value="setujui">
                            <input type="hidden" name="id" value="<?= $did ?>">
                            <button class="btn btn-kecil btn-ok" type="submit"
                                data-confirm="Setujui &amp; aktifkan <?= e($d['nama']) ?>?">Setujui</button>
                        </form>
                    <?php else: ?>
                        <?php if ((int)$d['aktif']): ?>
                            <a class="btn btn-kecil" href="index.php?masuk=<?= $did ?>">Buka</a>
                        <?php endif; ?>
                        <form method="post" action="sekolah.php" style="display:inline">
                            <?= csrf_field() ?><input type="hidden" name="aksi" value="toggle">
                            <input type="hidden" name="id" value="<?= $did ?>">
                            <button class="btn btn-kecil btn-ghost" type="submit"><?= (int)$d['aktif'] ? 'Nonaktifkan' : 'Aktifkan' ?></button>
                        </form>
                    <?php endif; ?>
                    <a class="btn btn-kecil btn-ghost" href="sekolah.php?edit=<?= $did ?>">Ubah</a>
                </td>
            </tr>
            <tr>
                <td colspan="7" class="kecil">
                    <form method="post" action="sekolah.php" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                        <?= csrf_field() ?>
                        <input type="hidden" name="aksi" value="hapus">
                        <input type="hidden" name="id" value="<?= $did ?>">
                        <span class="mut">Hapus permanen &mdash; dampak: <?= e(sekolah_dampak($did)) ?>.</span>
                        <input type="text" name="konfirmasi" placeholder="ketik HAPUS" style="max-width:150px">
                        <button class="btn btn-kecil btn-err" type="submit"
                            data-confirm="Hapus madrasah <?= e($d['nama']) ?> beserta seluruh datanya?">Hapus</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</div>
<?php
page_bottom();
