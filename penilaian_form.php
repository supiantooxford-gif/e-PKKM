<?php
/**
 * e-PKKM - Lembar penilaian kepala madrasah oleh Pengawas I / Pengawas II untuk satu tahun.
 * Hanya pengawas pemegang posisi tersebut (atau Super Admin) yang boleh mengisi.
 */
require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/form_penilaian.php';
$u = require_login();
$s = require_sekolah();
$sid = (int)$s['id'];

$posisi = (string)(int)($_REQUEST['posisi'] ?? 0);
$tahun = (int)($_REQUEST['tahun'] ?? 0);
$posisiSah = array_key_exists($posisi, PKKM_PENILAI_PENGAWAS) || penilai_posisi_tambahan($posisi);
if (!$posisiSah || !array_key_exists($tahun, PKKM_TAHUN_LIST)) {
    flash('Permintaan penilaian tidak dikenal.', 'err');
    redirect('penilaian.php');
}
// Posisi tambahan (>= 3) = penilai TAMBAHAN Tahun Keempat (permintaan pemilik): hanya untuk Tahun Keempat.
if (penilai_posisi_tambahan($posisi) && $tahun !== 4) {
    flash('Penilai Tambahan hanya mengisi penilaian Tahun Keempat.', 'err');
    redirect('penilaian.php');
}
$kategori = penilai_kategori_posisi($posisi);
$bolehIsi = boleh_isi_tahun($sid, $posisi, $tahun);
// Aturan pemilik: jabatan selain pengawas hanya boleh menilai Tahun Keempat
// (hanya berlaku untuk akun PENILAI; Super Admin & kepala madrasah tidak dibatasi aturan ini).
$salahTahun = $u['role'] === 'pengawas' && !penilai_jabatan_pengawas($u) && $tahun !== 4;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($salahTahun) {
        http_response_code(403);
        page_ditolak('Jabatan Anda bukan Pengawas, sehingga hanya dapat menilai Tahun Keempat. '
            . 'Tahun Pertama s.d. Ketiga hanya dapat dinilai oleh penilai berjabatan Pengawas.');
    }
    if (!$bolehIsi) {
        http_response_code(403);
        page_ditolak('Anda tidak ditugaskan pada posisi penilai tersebut.');
    }
    if (!csrf_ok()) {
        tolak_csrf();
    }
    $aksi = (string)($_POST['aksi'] ?? 'simpan');
    // ---- TANGGAL MENILAI (permintaan pemilik: diisi SEBELUM mengisi skor tiap tahun) ----
    if ($aksi === 'tanggal') {
        $tglSimpan = penilai_tanggal_simpan($sid, $kategori, $posisi, $tahun, (string)($_POST['tanggal'] ?? ''));
        if ($tglSimpan === '') {
            flash('Tanggal menilai belum tersimpan \u{2014} isi tanggal yang sah (hari/bulan/tahun).', 'err');
        } else {
            flash('Tanggal menilai ' . pilihan_tahun($tahun) . ' tersimpan: ' . tanggal_id($tglSimpan)
                . '. Silakan lanjutkan mengisi skor penilaian.');
        }
        redirect('penilaian_form.php?posisi=' . $posisi . '&tahun=' . $tahun);
    }
    // Lembar lama / skrip yang mengirim skor tanpa tanggal: tanggal menilai diisi otomatis
    // (hari ini) supaya tidak ada penilaian yang tanggalnya kosong, lalu tetap dapat diubah.
    $tglOtomatis = false;
    if (!penilai_tanggal_ada($sid, $kategori, $posisi, $tahun)) {
        $tglOtomatis = penilai_tanggal_simpan($sid, $kategori, $posisi, $tahun, date('Y-m-d')) !== '';
    }
    $skorKirim = is_array($_POST['skor'] ?? null) ? $_POST['skor'] : [];
    penilaian_simpan($sid, $kategori, $posisi, $tahun, $skorKirim, $aksi === 'selesai');
    // Komentar penilai untuk dokumen tiap butir (permintaan pemilik: dokumen dilihat, diberi
    // komentar, lalu diberi skor). Hanya penilai yang berhak mengisi pada lembar ini.
    $komentarKirim = is_array($_POST['komentar'] ?? null) ? $_POST['komentar'] : [];
    $jmlKomentar = komentar_butir_simpan($sid, $tahun, $posisi, $komentarKirim);
    $valid = butir_tahun($tahun);
    $jml = 0;
    foreach ($skorKirim as $b => $v) {
        if (isset($valid[$b]) && (int)$v >= 1 && (int)$v <= PKKM_SKOR_MAKS) {
            $jml++;
        }
    }
    $catatanTgl = $tglOtomatis
        ? ' Tanggal menilai diisi otomatis (' . tanggal_id(date('Y-m-d'))
            . ') karena belum ditetapkan \u{2014} dapat Anda ubah pada kartu Tanggal Menilai.'
        : '';
    if ($aksi === 'selesai' && $jml === count($valid)) {
        flash('Penilaian diselesaikan: ' . count($valid) . ' butir terisi untuk ' . pilihan_tahun($tahun)
            . ($jmlKomentar > 0 ? ' dan ' . $jmlKomentar . ' komentar dokumen tersimpan.' : '.') . $catatanTgl);
    } else {
        flash('Draft penilaian tersimpan (' . $jml . ' dari ' . count($valid) . ' butir terisi'
            . ($jmlKomentar > 0 ? ', ' . $jmlKomentar . ' komentar dokumen' : '') . ').' . $catatanTgl);
    }
    redirect('penilaian_form.php?posisi=' . $posisi . '&tahun=' . $tahun);
}

$p = penilaian_ambil($sid, $kategori, $posisi, $tahun, true);
$skor = penilaian_skor((int)$p['id']);
$penilai = penilai_ambil($sid, $kategori, $posisi);
$rinci = nilai_rinci($skor, $tahun);
$dokButir = dokumen_list($sid, $tahun);
$komButir = komentar_butir_map($sid, $tahun);
$jmlDokumen = 0;
foreach ($dokButir as $baris) {
    $jmlDokumen += count($baris);
}
$jmlKomentarSaya = komentar_butir_jumlah($sid, $tahun, $posisi);
$bolehUnggahDok = in_array($u['role'], ['admin', 'super'], true);
// ---- TANGGAL MENILAI tahun ini (harus diisi lebih dulu sebelum skor) ----
$tanggalMenilai = penilai_tanggal($sid, $kategori, $posisi, $tahun);
$tanggalAda = $tanggalMenilai !== '';
$bolehIsiSkor = $bolehIsi && $tanggalAda;   // skor baru bisa diisi setelah tanggal ditetapkan

page_top('Lembar Penilaian PKKM', e($s['nama']) . ' &middot; ' . e(penilai_label($kategori, $posisi)) . ' &middot; ' . e(pilihan_tahun($tahun)));
?>
<div class="card">
    <div class="baris-info">
        <div><span>Penilai:</span> <b><?= e($penilai['nama'] ?? $u['nama']) ?></b></div>
        <div><span>Posisi:</span> <b><?= e(penilai_label($kategori, $posisi)) ?></b></div>
        <div><span>Kepala Madrasah:</span> <b><?= e($s['kepala_nama'] ?: '-') ?></b></div>
        <div><span>Tahun penilaian:</span> <b><?= e(tahun_penilaian_tampil($sid, $s, $tahun) ?: '-') ?></b></div>
        <div><span>Tanggal menilai:</span> <b><?= $tanggalAda
            ? e(tanggal_id($tanggalMenilai))
            : '<span class="pil pil-cukup">belum diisi</span>' ?></b></div>
        <div><span>Status:</span> <b><?= $rinci['lengkap'] ? 'Lengkap' : ($rinci['terisi'] > 0 ? 'Draft' : 'Belum diisi') ?></b></div>
        <div><span>Dokumen kepala madrasah:</span> <b><?= $jmlDokumen ?> dokumen</b></div>
        <div><span>Komentar Anda:</span> <b><?= $jmlKomentarSaya ?> butir</b></div>
    </div>
</div>

<?php if ($salahTahun): ?>
    <div class="alert alert-err">Jabatan Anda adalah <b><?= e(trim((string)($u['jabatan'] ?? '')) ?: 'belum diisi') ?></b>
        &mdash; bukan <b>Pengawas</b>, sehingga Anda hanya dapat <b>menilai Tahun Keempat</b>.
        Lembar Tahun Pertama s.d. Ketiga ini hanya dapat dinilai oleh penilai berjabatan Pengawas.
        Hubungi Admin aplikasi bila jabatan Anda seharusnya Pengawas.</div>
<?php elseif (!$bolehIsi): ?>
    <div class="alert alert-info">Anda membuka lembar ini dalam mode <b>hanya lihat</b> &mdash; nilai hanya dapat
        diubah oleh penilai pemegang posisi <b><?= e(penilai_label($kategori, $posisi)) ?></b> atau Super Admin.</div>
<?php endif; ?>

<div class="card">
    <div class="card-kepala"><h3>Dokumen Bukti dari Kepala Madrasah</h3>
        <div class="aksi">
            <?php if ($bolehUnggahDok): ?>
                <a class="btn btn-kecil btn-ghost" href="dokumen.php?tahun=<?= (int)$tahun ?>"><?= ikon('dokumen', 15) ?> Kelola Unggahan</a>
            <?php endif; ?>
        </div>
    </div>
    <p class="ket mb0">Dokumen bukti tiap butir (unggahan kepala madrasah) &mdash; berupa <b>berkas</b> atau
        <b>tautan Google Drive</b> (bertanda <b>DRIVE</b>) &mdash; tampil pada kolom <b>Dokumen</b> di lembar
        di bawah ini. Penilai dapat melihat dokumennya, menuliskan <b>komentar</b> untuk butir itu, lalu memberi
        <b>skor 1-4</b>. Komentar tersimpan bersama nilainya saat lembar ini disimpan<?= $jmlDokumen > 0
            ? ' &mdash; saat ini tersedia ' . $jmlDokumen . ' berkas untuk ' . pilihan_tahun($tahun) . '.'
            : ' &mdash; kepala madrasah belum mengunggah dokumen untuk ' . pilihan_tahun($tahun) . '.' ?></p>
</div>

<?php // ---------------- TANGGAL MENILAI (diisi SEBELUM mengisi skor tiap tahun) ---------------- ?>
<div class="card" id="cardTanggalMenilai">
    <div class="card-kepala"><h2>Tanggal Menilai &mdash; <?= e(pilihan_tahun($tahun)) ?></h2>
        <div class="aksi">
            <span class="pil <?= $tanggalAda ? 'pil-unggul' : 'pil-cukup' ?>"><?= $tanggalAda
                ? 'tersimpan ' . e(tanggal_id($tanggalMenilai)) : 'belum diisi' ?></span>
        </div>
    </div>
    <p class="ket">Tetapkan <b>tanggal Anda melakukan penilaian</b> pada <?= e(pilihan_tahun($tahun)) ?>
        terlebih dahulu. Tanggal ini tercetak pada lembar penilaian, laporan, dan rekap PKKM.
        <?php if ($tahun <= 3): ?>Nilai <b><?= e(tahun_penilaian_tampil($sid, $s, $tahun) ?: '-') ?></b><?php else: ?>
            Tahun penilaian <b><?= e(tahun_penilaian_tampil($sid, $s, 4) ?: '-') ?></b><?php endif; ?></p>
    <?php if ($bolehIsi): ?>
        <form method="post" action="penilaian_form.php?posisi=<?= e($posisi) ?>&tahun=<?= (int)$tahun ?>" id="formTanggalMenilai">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="tanggal">
            <div class="tgl-nilai">
                <div>
                    <label for="tanggal_menilai">Tanggal menilai</label>
                    <input type="date" id="tanggal_menilai" name="tanggal" required
                           value="<?= e($tanggalMenilai !== '' ? $tanggalMenilai : date('Y-m-d')) ?>">
                </div>
                <button class="btn" type="submit"><?= ikon('simpan', 16) ?>
                    <?= $tanggalAda ? 'Ubah Tanggal Menilai' : 'Simpan Tanggal Menilai' ?></button>
            </div>
        </form>
    <?php else: ?>
        <p class="mb0"><?= $tanggalAda
            ? 'Tanggal menilai penilai pemegang posisi ini: <b>' . e(tanggal_id($tanggalMenilai)) . '</b>.'
            : 'Penilai pemegang posisi ini belum menetapkan tanggal menilai.' ?></p>
    <?php endif; ?>
    <?php if ($bolehIsi && !$tanggalAda): ?>
        <div class="alert alert-info mt12 mb0" id="pesanTanggalDulu">Isi <b>Tanggal Menilai</b> di atas lebih dulu
            (tombol <b>Simpan Tanggal Menilai</b>), setelah itu kolom skor penilaian akan terbuka.</div>
    <?php endif; ?>
</div>

<form method="post" action="penilaian_form.php?posisi=<?= e($posisi) ?>&tahun=<?= (int)$tahun ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="posisi" value="<?= e($posisi) ?>">
    <input type="hidden" name="tahun" value="<?= (int)$tahun ?>">

    <div class="card">
        <div class="card-kepala"><h2>Ringkasan Nilai</h2>
            <div class="aksi">
                <a class="btn btn-ghost btn-kecil" href="cetak_penilaian.php?posisi=<?= e($posisi) ?>&tahun=<?= (int)$tahun ?>"><?= ikon('cetak', 15) ?> Cetak Butir</a>
                <a class="btn btn-ghost btn-kecil" href="penilaian_docx.php?posisi=<?= e($posisi) ?>&tahun=<?= (int)$tahun ?>"><?= ikon('unduh', 15) ?> Unduh Word</a>
                <a class="btn btn-ghost btn-kecil" href="penilaian.php">Kembali</a>
            </div>
        </div>
        <?= form_ringkasan_html($tahun, $skor) ?>
        <p class="kecil mut mt8 mb0">Nilai tiap tugas utama = (jumlah skor butir &divide; (4 &times; jumlah butir)) &times; 100.
            Nilai tahunan = rata-rata tugas utama yang berlaku pada tahun ini. Skor tiap butir 1-4.</p>
    </div>

    <div class="card" data-skor data-maks="<?= PKKM_SKOR_MAKS ?>">
        <div class="card-kepala"><h2>Butir Penilaian &mdash; <?= e(pilihan_tahun($tahun)) ?></h2></div>
        <?php if ($bolehIsi && !$tanggalAda): ?>
            <div class="alert alert-err" id="skorTerkunci">Lembar ini masih <b>terkunci</b> &mdash;
                isi <b>Tanggal Menilai</b> pada kartu di atas, lalu simpan. Setelah itu kolom skor 1-4 dapat diisi.</div>
        <?php endif; ?>
        <?= form_penilaian_html($s, $kategori, $posisi, $tahun, $bolehIsiSkor, $skor, [
            'dokumen' => $dokButir,
            'komentar' => $komButir,
            'posisi' => $posisi,
        ]) ?>
        <?php if ($bolehIsiSkor): ?>
            <div class="form-aksi">
                <button class="btn" type="submit" name="aksi" value="simpan"><?= ikon('simpan', 16) ?> Simpan Draft</button>
                <button class="btn btn-emas" type="submit" name="aksi" value="selesai"><?= ikon('centang', 16) ?> Simpan &amp; Selesaikan</button>
            </div>
            <p class="kecil mut mt8 mb0">Menyimpan akan MENGGANTI seluruh skor tersimpan pada lembar ini sesuai isian di atas.</p>
        <?php endif; ?>
    </div>
</form>
<?php
page_bottom();
