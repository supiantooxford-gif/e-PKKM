<?php
/**
 * e-PKKM - DAFTAR JABATAN PENILAI (khusus Super Admin).
 *
 * Permintaan pemilik: pada formulir Pendaftaran Penilai, bagian Jabatan memakai LIST OTOMATIS yang
 * diisi Super Admin (mis. Kabag, Kasie, Pengawas, Guru, Komite).
 *
 * Halaman ini mengelola daftar tersebut: tambah, ubah nama, urutkan, aktif/nonaktifkan, dan hapus.
 * Jabatan yang sudah dipakai akun penilai TIDAK boleh dihapus (agar data akun tidak kehilangan
 * jabatannya) — nonaktifkan saja supaya tidak muncul pada formulir pendaftaran.
 */
require_once __DIR__ . '/inc/init.php';
$u = require_super();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) {
        tolak_csrf();
    }
    $aksi = (string)($_POST['aksi'] ?? '');
    $id = (int)($_POST['id'] ?? 0);

    if ($aksi === 'tambah' || $aksi === 'ubah') {
        $nama = bersih_teks((string)($_POST['nama'] ?? ''), 60);
        if ($nama === '') {
            flash('Nama jabatan wajib diisi.', 'err');
            redirect('jabatan.php' . ($id > 0 ? '?edit=' . $id : ''));
        }
        $kembar = one('SELECT * FROM jabatan_penilai WHERE LOWER(TRIM(nama)) = LOWER(?) AND id <> ?', [$nama, $id]);
        if ($kembar) {
            flash('Jabatan "' . $nama . '" sudah ada pada daftar.', 'err');
            redirect('jabatan.php' . ($id > 0 ? '?edit=' . $id : ''));
        }
        if ($aksi === 'tambah') {
            q('INSERT INTO jabatan_penilai (nama, urutan, aktif, dibuat) VALUES (?, ?, 1, ?)',
                [$nama, jabatan_penilai_urut_baru(), date('Y-m-d H:i:s')]);
            flash('Jabatan "' . $nama . '" ditambahkan.');
        } else {
            $j = jabatan_penilai_by_id($id);
            if (!$j) {
                flash('Jabatan tidak ditemukan.', 'err');
                redirect('jabatan.php');
            }
            q('UPDATE jabatan_penilai SET nama = ? WHERE id = ?', [$nama, $id]);
            // akun penilai yang memakai jabatan lama ikut diperbarui namanya
            if ((string)$j['nama'] !== $nama) {
                q('UPDATE users SET jabatan = ? WHERE LOWER(TRIM(jabatan)) = LOWER(?)', [$nama, (string)$j['nama']]);
            }
            flash('Jabatan diperbarui menjadi "' . $nama . '".');
        }
        redirect('jabatan.php');
    }

    if ($aksi === 'toggle') {
        $j = jabatan_penilai_by_id($id);
        if ($j) {
            q('UPDATE jabatan_penilai SET aktif = ? WHERE id = ?', [(int)$j['aktif'] ? 0 : 1, $id]);
            flash('Jabatan "' . $j['nama'] . '" ' . ((int)$j['aktif'] ? 'dinonaktifkan' : 'diaktifkan') . '.');
        }
        redirect('jabatan.php');
    }

    if ($aksi === 'geser') {
        $arah = ((int)($_POST['arah'] ?? 0)) < 0 ? -1 : 1;
        if (!jabatan_penilai_geser($id, $arah)) {
            flash('Urutan tidak dapat diubah lagi.', 'err');
        }
        redirect('jabatan.php');
    }

    if ($aksi === 'hapus') {
        $j = jabatan_penilai_by_id($id);
        if (!$j) {
            flash('Jabatan tidak ditemukan.', 'err');
            redirect('jabatan.php');
        }
        $dipakai = jabatan_penilai_pemakai((string)$j['nama']);
        if ($dipakai > 0) {
            flash('Jabatan "' . $j['nama'] . '" dipakai ' . $dipakai . ' akun penilai sehingga tidak dapat '
                . 'dihapus. Nonaktifkan saja agar tidak muncul pada formulir pendaftaran.', 'err');
            redirect('jabatan.php');
        }
        q('DELETE FROM jabatan_penilai WHERE id = ?', [$id]);
        flash('Jabatan "' . $j['nama'] . '" dihapus.');
        redirect('jabatan.php');
    }

    flash('Aksi tidak dikenal.', 'err');
    redirect('jabatan.php');
}

$editId = (int)($_GET['edit'] ?? 0);
$editJ = jabatan_penilai_by_id($editId);
$daftar = jabatan_penilai_list(false);
$jumlahPenilai = (int)val("SELECT COUNT(*) FROM users WHERE role = 'pengawas'");

// halaman lintas madrasah -> istilah generik "Sekolah" (pola halaman Super Admin lain)
$GLOBALS['__vs_istilah_nonaktif'] = true;

page_top('Daftar Jabatan Penilai', 'Super Admin &middot; daftar jabatan yang tampil pada formulir Pendaftaran Penilai.', true);
?>
<div class="grid g-form">
    <div class="card">
        <div class="card-kepala"><h2><?= $editJ ? 'Ubah Jabatan' : 'Tambah Jabatan Penilai' ?></h2>
            <?php if ($editJ): ?><div class="aksi"><a class="btn btn-ghost btn-kecil" href="jabatan.php">Batal ubah</a></div><?php endif; ?>
        </div>
        <form method="post" action="jabatan.php">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="<?= $editJ ? 'ubah' : 'tambah' ?>">
            <?php if ($editJ): ?><input type="hidden" name="id" value="<?= (int)$editJ['id'] ?>"><?php endif; ?>
            <div class="form-baris">
                <label for="jnama">Nama Jabatan</label>
                <input type="text" id="jnama" name="nama" required maxlength="60"
                       value="<?= e($editJ['nama'] ?? '') ?>" placeholder="mis. Kabag">
            </div>
            <p class="ket">Jabatan ini menjadi pilihan pada formulir <b>Pendaftaran Penilai</b> di halaman depan.</p>
            <div class="form-aksi"><button class="btn" type="submit"><?= ikon('simpan', 16) ?>
                <?= $editJ ? 'Simpan Perubahan' : 'Tambah Jabatan' ?></button></div>
        </form>
        <div class="ket mt16" style="text-align:left">
            <b>Catatan</b>
            <ul class="mb0">
                <li>Jabatan yang <b>dinonaktifkan</b> tidak lagi muncul pada formulir pendaftaran, tetapi akun
                    penilai yang sudah memakainya tetap utuh.</li>
                <li>Jabatan yang sudah dipakai akun penilai <b>tidak dapat dihapus</b> &mdash; nonaktifkan saja.</li>
                <li>Akun penilai yang mendaftar sendiri tampil pada menu <b>Akun Penilai</b>
                    (<?= $jumlahPenilai ?> akun penilai saat ini).</li>
            </ul>
        </div>
    </div>

    <div class="card">
        <div class="card-kepala"><h2>Daftar Jabatan</h2>
            <span class="pil pil-unggul"><?= count($daftar) ?> jabatan</span>
        </div>
        <?php if (!$daftar): ?>
            <p class="mut mb0">Belum ada jabatan. Tambahkan lewat formulir di samping.</p>
        <?php else: ?>
        <div class="geser"><table class="tbl" id="tblJabatan">
            <thead><tr><th class="tengah" style="width:52px">No</th><th>Nama Jabatan</th>
                <th class="tengah">Dipakai</th><th class="tengah">Status</th>
                <th class="tengah" style="width:104px">Urutan</th>
                <th class="tengah aksi-kolom">Aksi</th></tr></thead>
            <tbody>
            <?php foreach ($daftar as $i => $j): $jid = (int)$j['id'];
                $pakai = jabatan_penilai_pemakai((string)$j['nama']);
                $pengawasJab = stripos(trim((string)$j['nama']), 'pengawas') === 0; ?>
                <tr>
                    <td class="tengah"><?= $i + 1 ?></td>
                    <td><b><?= e($j['nama']) ?></b>
                        <div class="kecil mut"><?= $pengawasJab
                            ? 'boleh menilai Tahun Pertama s.d. Keempat'
                            : 'hanya menilai Tahun Keempat (penilai tambahan)' ?></div></td>
                    <td class="tengah"><?= $pakai ?> akun</td>
                    <td class="tengah"><?= (int)$j['aktif']
                        ? '<span class="pil pil-unggul">Aktif</span>'
                        : '<span class="pil pil-kurang">Nonaktif</span>' ?></td>
                    <td class="tengah">
                        <div class="urutan-sel">
                            <form method="post" action="jabatan.php">
                                <?= csrf_field() ?><input type="hidden" name="aksi" value="geser">
                                <input type="hidden" name="id" value="<?= $jid ?>"><input type="hidden" name="arah" value="-1">
                                <button class="btn btn-kecil btn-ghost" type="submit" title="Naikkan"
                                    aria-label="Naikkan urutan <?= e($j['nama']) ?>">&uarr;</button>
                            </form>
                            <form method="post" action="jabatan.php">
                                <?= csrf_field() ?><input type="hidden" name="aksi" value="geser">
                                <input type="hidden" name="id" value="<?= $jid ?>"><input type="hidden" name="arah" value="1">
                                <button class="btn btn-kecil btn-ghost" type="submit" title="Turunkan"
                                    aria-label="Turunkan urutan <?= e($j['nama']) ?>">&darr;</button>
                            </form>
                        </div>
                    </td>
                    <td class="tengah aksi-kolom">
                        <div class="row-aksi">
                            <a class="btn btn-kecil btn-ghost" href="jabatan.php?edit=<?= $jid ?>#">Ubah</a>
                            <form method="post" action="jabatan.php">
                                <?= csrf_field() ?><input type="hidden" name="aksi" value="toggle">
                                <input type="hidden" name="id" value="<?= $jid ?>">
                                <button class="btn btn-kecil btn-ghost" type="submit"><?= (int)$j['aktif'] ? 'Nonaktifkan' : 'Aktifkan' ?></button>
                            </form>
                            <form method="post" action="jabatan.php">
                                <?= csrf_field() ?><input type="hidden" name="aksi" value="hapus">
                                <input type="hidden" name="id" value="<?= $jid ?>">
                                <button class="btn btn-kecil btn-err" type="submit"
                                    data-confirm="Hapus jabatan <?= e($j['nama']) ?>?">Hapus</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <?php endif; ?>
    </div>
</div>
<?php
page_bottom();
