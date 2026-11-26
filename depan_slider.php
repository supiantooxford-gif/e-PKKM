<?php
/**
 * e-PKKM - SLIDER GAMBAR HALAMAN DEPAN (khusus Super Admin) — v1.13.0.
 *
 * Permintaan pemilik: area ilustrasi pada halaman masuk diganti **slider beberapa gambar** yang
 * diisi oleh Super Admin. Di halaman ini Super Admin:
 *   - menambah gambar (unggah → media proxy platform, maks 15 MB per berkas),
 *   - mengubah judul/keterangan gambar, menggeser urutan (▲▼), mengaktifkan/menonaktifkan,
 *   - mengganti gambar tanpa menghapus barisnya,
 *   - menghapus gambar dari slider.
 * Bila belum ada gambar aktif, halaman depan otomatis memakai ilustrasi bawaan (tidak kosong).
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
        redirect('depan_slider.php');
    }

    // ------------------------------------------------------------ TAMBAH GAMBAR
    if ($aksi === 'tambah') {
        $hasil = depan_slider_unggah(is_array($_FILES['gambar'] ?? null) ? $_FILES['gambar'] : []);
        if (!$hasil['ok']) {
            flash($hasil['pesan'], 'err');
            redirect('depan_slider.php');
        }
        $judul = bersih_teks((string)($_POST['judul'] ?? ''), PKKM_TEKS_PENDEK);
        $ket = bersih_teks((string)($_POST['keterangan'] ?? ''), PKKM_TEKS_PENDEK);
        $kini = date('Y-m-d H:i:s');
        mulai_transaksi();
        try {
            q('INSERT INTO depan_slider (foto, judul, keterangan, urutan, aktif, dibuat, diubah)
               VALUES (?, ?, ?, ?, 1, ?, ?)', [$hasil['url'], $judul, $ket, depan_slider_urut_baru(), $kini, $kini]);
            selesai_transaksi(true);
            flash('Gambar slider ditambahkan dan langsung tampil pada halaman depan.');
        } catch (Throwable $t) {
            selesai_transaksi(false);
            flash('Gambar gagal disimpan: ' . $t->getMessage(), 'err');
        }
        redirect('depan_slider.php');
    }

    // ------------------------------------------------------------ UBAH TEKS / GANTI GAMBAR
    if ($aksi === 'ubah') {
        $id = (int)($_POST['id'] ?? 0);
        $s = depan_slider_by_id($id);
        if (!$s) {
            flash('Gambar slider tidak ditemukan.', 'err');
            redirect('depan_slider.php');
        }
        $judul = bersih_teks((string)($_POST['judul'] ?? ''), PKKM_TEKS_PENDEK);
        $ket = bersih_teks((string)($_POST['keterangan'] ?? ''), PKKM_TEKS_PENDEK);
        $foto = (string)$s['foto'];
        $adaBerkas = (int)($_FILES['gambar']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        if ($adaBerkas) {
            $hasil = depan_slider_unggah(is_array($_FILES['gambar'] ?? null) ? $_FILES['gambar'] : []);
            if (!$hasil['ok']) {
                flash($hasil['pesan'], 'err');
                redirect('depan_slider.php?edit=' . $id . '#formSlider');
            }
            $foto = $hasil['url'];
        }
        q('UPDATE depan_slider SET foto = ?, judul = ?, keterangan = ?, diubah = ? WHERE id = ?',
            [$foto, $judul, $ket, date('Y-m-d H:i:s'), $id]);
        flash('Gambar slider diperbarui.');
        redirect('depan_slider.php');
    }

    // ------------------------------------------------------------ AKTIF / NONAKTIF
    if ($aksi === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $s = depan_slider_by_id($id);
        if ($s) {
            $baru = (int)$s['aktif'] ? 0 : 1;
            q('UPDATE depan_slider SET aktif = ?, diubah = ? WHERE id = ?', [$baru, date('Y-m-d H:i:s'), $id]);
            flash($baru ? 'Gambar ditampilkan pada halaman depan.'
                : 'Gambar disembunyikan dari halaman depan (data tetap tersimpan).');
        }
        redirect('depan_slider.php');
    }

    // ------------------------------------------------------------ GESER URUTAN
    if ($aksi === 'geser') {
        $id = (int)($_POST['id'] ?? 0);
        $arah = (int)($_POST['arah'] ?? 0) < 0 ? -1 : 1;
        $ok = depan_slider_geser($id, $arah);
        flash($ok ? 'Urutan gambar diperbarui.' : 'Urutan tidak berubah (sudah di ujung).');
        redirect('depan_slider.php');
    }

    // ------------------------------------------------------------ HAPUS
    if ($aksi === 'hapus') {
        $id = (int)($_POST['id'] ?? 0);
        $s = depan_slider_by_id($id);
        if (!$s) {
            flash('Gambar slider tidak ditemukan.', 'err');
            redirect('depan_slider.php');
        }
        if (trim((string)($_POST['konfirmasi'] ?? '')) !== 'HAPUS') {
            flash('Penghapusan dibatalkan: ketik HAPUS untuk menyetujui.', 'err');
            redirect('depan_slider.php');
        }
        mulai_transaksi();
        try {
            q('DELETE FROM depan_slider WHERE id = ?', [$id]);
            selesai_transaksi(true);
            flash('Gambar slider dihapus.');
        } catch (Throwable $t) {
            selesai_transaksi(false);
            flash('Gagal menghapus gambar: ' . $t->getMessage(), 'err');
        }
        redirect('depan_slider.php');
    }

    flash('Aksi tidak dikenal.', 'err');
    redirect('depan_slider.php');
}

$editId = (int)($_GET['edit'] ?? 0);
$edit = $editId > 0 ? depan_slider_by_id($editId) : null;
$daftar = depan_slider_list(false);
$jmlAktif = 0;
foreach ($daftar as $d) {
    if ((int)$d['aktif'] === 1) {
        $jmlAktif++;
    }
}
$maksFoto = FOTO_MAKS_BYTE;

page_top('Slider Halaman Depan', 'Gambar yang tampil pada area ilustrasi halaman masuk aplikasi.', true);
?>
<div class="card" id="cardInfoSlider">
    <div class="card-kepala"><h2><?= ikon('buka', 18) ?> Slider Halaman Depan</h2>
        <div class="aksi">
            <a class="btn btn-ghost btn-kecil" href="login.php" target="_blank" rel="noopener">
                <?= ikon('buka', 15) ?> Lihat Halaman Depan</a>
        </div>
    </div>
    <p class="ket">Halaman masuk menampilkan <b>slider gambar</b> pada kolom kiri. Unggah
        <b>beberapa gambar</b> (JPG, PNG, GIF, WEBP, atau SVG, maks
        <?= e(number_format($maksFoto / 1048576, 0, ',', '.')) ?> MB per gambar); gambar berganti
        otomatis setiap 6 detik dan pengunjung juga dapat menekan panah atau titik navigasi.
        Urutkan gambar dengan tombol <b>&#9650;</b>/<b>&#9660;</b>, dan sembunyikan gambar tanpa
        menghapusnya lewat tombol <b>Nonaktifkan</b>.</p>
    <p class="kecil mut mb0">Saat ini: <b><?= count($daftar) ?></b> gambar &middot;
        <b><?= $jmlAktif ?></b> aktif ditampilkan<?= $jmlAktif === 0
            ? ' &mdash; selama belum ada gambar aktif, halaman depan memakai ilustrasi bawaan.' : '.' ?></p>
</div>

<div class="grid g2">
    <div class="card" id="formSlider">
        <div class="card-kepala"><h2><?= $edit ? 'Ubah Gambar Slider' : 'Tambah Gambar Slider' ?></h2>
            <?php if ($edit): ?><div class="aksi"><a class="btn btn-ghost btn-kecil"
                href="depan_slider.php">Batal Ubah</a></div><?php endif; ?>
        </div>
        <form method="post" action="depan_slider.php" enctype="multipart/form-data" class="form-slider">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="<?= $edit ? 'ubah' : 'tambah' ?>">
            <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>"><?php endif; ?>
            <div>
                <label for="sgambar">Gambar<?= $edit ? ' (biarkan kosong bila tidak diganti)' : '' ?></label>
                <input type="file" id="sgambar" name="gambar" accept="image/jpeg,image/png,image/gif,image/webp,image/svg+xml"
                       <?= $edit ? '' : 'required' ?>>
                <p class="ket mb0">Disarankan gambar mendatar (mis. 1200 &times; 720 piksel). Maks
                    <?= e(number_format($maksFoto / 1048576, 0, ',', '.')) ?> MB.</p>
            </div>
            <div>
                <label for="sjudul">Judul gambar (boleh dikosongkan)</label>
                <input type="text" id="sjudul" name="judul" maxlength="120"
                       value="<?= e((string)($edit['judul'] ?? '')) ?>" placeholder="mis. Penilaian kinerja yang transparan">
            </div>
            <div>
                <label for="sketerangan">Keterangan (boleh dikosongkan)</label>
                <input type="text" id="sketerangan" name="keterangan" maxlength="200"
                       value="<?= e((string)($edit['keterangan'] ?? '')) ?>" placeholder="mis. Instrumen resmi, penilai tekun, rekap empat tahun">
            </div>
            <?php if ($edit && trim((string)($edit['foto'] ?? '')) !== ''): ?>
                <div class="slider-pratinjau">
                    <span class="kecil mut">Gambar sekarang</span>
                    <img src="<?= e((string)$edit['foto']) ?>" alt="Gambar slider saat ini">
                </div>
            <?php endif; ?>
            <div class="form-aksi">
                <button class="btn" type="submit"><?= ikon('simpan', 16) ?>
                    <?= $edit ? 'Simpan Perubahan' : 'Tambah ke Slider' ?></button>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="card-kepala"><h2>Pratinjau Slider</h2>
            <span class="pil <?= $jmlAktif > 0 ? 'pil-unggul' : 'pil-kosong' ?>">
                <?= $jmlAktif > 0 ? $jmlAktif . ' gambar aktif' : 'ilustrasi bawaan' ?></span>
        </div>
        <?php if ($jmlAktif === 0): ?>
            <p class="mut mb0">Belum ada gambar aktif. Halaman depan menampilkan ilustrasi bawaan
                sampai gambar pertama diunggah dan diaktifkan.</p>
        <?php else: ?>
            <div class="slider-pratinjau-layar"><?= depan_slider_html() ?></div>
        <?php endif; ?>
    </div>
</div>

<div class="card" id="cardDaftarSlider">
    <div class="card-kepala"><h2>Daftar Gambar Slider</h2>
        <span class="kecil mut"><?= count($daftar) ?> gambar</span>
    </div>
    <?php if (!$daftar): ?>
        <p class="mut mb0">Belum ada gambar. Unggah gambar pertama pada formulir
            <b>Tambah Gambar Slider</b> di atas.</p>
    <?php else: ?>
    <div class="geser"><table class="tbl tbl-slider" id="tblSlider">
        <thead><tr><th class="tengah">No</th><th>Gambar</th><th>Judul &amp; Keterangan</th>
            <th class="tengah">Urutan</th><th class="tengah">Status</th><th>Aksi</th></tr></thead>
        <tbody>
        <?php foreach ($daftar as $i => $d):
            $did = (int)$d['id']; ?>
            <tr>
                <td class="tengah"><?= $i + 1 ?></td>
                <td><img class="slider-mini" src="<?= e((string)$d['foto']) ?>" alt="Gambar slider <?= $i + 1 ?>"></td>
                <td>
                    <b><?= e(trim((string)($d['judul'] ?? '')) !== '' ? (string)$d['judul'] : '(tanpa judul)') ?></b>
                    <div class="kecil mut"><?= e(trim((string)($d['keterangan'] ?? '')) !== ''
                        ? (string)$d['keterangan'] : 'tanpa keterangan') ?></div>
                </td>
                <td class="tengah nowrap">
                    <form method="post" action="depan_slider.php" class="inline">
                        <?= csrf_field() ?><input type="hidden" name="aksi" value="geser">
                        <input type="hidden" name="id" value="<?= $did ?>"><input type="hidden" name="arah" value="-1">
                        <button class="btn btn-kecil btn-ghost" type="submit" aria-label="Naikkan urutan"
                            <?= $i === 0 ? 'disabled' : '' ?>>&#9650;</button>
                    </form>
                    <form method="post" action="depan_slider.php" class="inline">
                        <?= csrf_field() ?><input type="hidden" name="aksi" value="geser">
                        <input type="hidden" name="id" value="<?= $did ?>"><input type="hidden" name="arah" value="1">
                        <button class="btn btn-kecil btn-ghost" type="submit" aria-label="Turunkan urutan"
                            <?= $i === count($daftar) - 1 ? 'disabled' : '' ?>>&#9660;</button>
                    </form>
                </td>
                <td class="tengah">
                    <?php if ((int)$d['aktif']): ?>
                        <span class="pil pil-unggul">Aktif</span>
                    <?php else: ?>
                        <span class="pil pil-kosong">Nonaktif</span>
                    <?php endif; ?>
                </td>
                <td class="nowrap">
                    <a class="btn btn-kecil btn-ghost" href="depan_slider.php?edit=<?= $did ?>#formSlider">Ubah</a>
                    <form method="post" action="depan_slider.php" class="inline">
                        <?= csrf_field() ?><input type="hidden" name="aksi" value="toggle">
                        <input type="hidden" name="id" value="<?= $did ?>">
                        <button class="btn btn-kecil btn-ghost" type="submit">
                            <?= (int)$d['aktif'] ? 'Nonaktifkan' : 'Aktifkan' ?></button>
                    </form>
                    <form method="post" action="depan_slider.php" class="inline form-hapus-slider">
                        <?= csrf_field() ?><input type="hidden" name="aksi" value="hapus">
                        <input type="hidden" name="id" value="<?= $did ?>">
                        <input type="text" name="konfirmasi" placeholder="ketik HAPUS" maxlength="10">
                        <button class="btn btn-kecil btn-err" type="submit"
                            data-confirm="Hapus gambar slider ini dari daftar?">Hapus</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</div>
<?php
page_bottom();
