<?php
/**
 * e-PKKM - Lembar cetak penilaian (butir per butir) untuk satu penilai.
 * Parameter: ?posisi=1|2&tahun=1..4  ATAU  ?kategori=gtk&jenis=guru1|...
 */
require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/form_penilaian.php';
$u = require_login();
$s = require_sekolah();
$sid = (int)$s['id'];

$kategori = (string)($_GET['kategori'] ?? 'pengawas');
if ($kategori === 'gtk') {
    $jenis = (string)($_GET['jenis'] ?? 'guru1');
    if (!array_key_exists($jenis, PKKM_PENILAI_GTK)) {
        flash('Jenis penilai tidak dikenal.', 'err');
        redirect(current_sekolah() ? 'penilaian.php' : 'index.php');
    }
    $tahun = 4;
    $kode = $jenis;
} else {
    $kode = (string)(int)($_GET['posisi'] ?? 1);
    $tahun = (int)($_GET['tahun'] ?? 1);
    // posisi 1/2 = Pengawas I & II; posisi >= 3 = penilai TAMBAHAN Tahun Keempat (permintaan pemilik)
    $kategori = penilai_kategori_posisi($kode);
    if ((!array_key_exists($kode, PKKM_PENILAI_PENGAWAS) && !penilai_posisi_tambahan($kode))
        || !array_key_exists($tahun, PKKM_TAHUN_LIST)) {
        flash('Permintaan cetak tidak dikenal.', 'err');
        redirect('penilaian.php');
    }
}

$p = penilaian_ambil($sid, $kategori, $kode, $tahun, true);
$skor = penilaian_skor((int)$p['id']);
$rinci = nilai_rinci($skor, $tahun);
$pen = penilai_ambil($sid, $kategori, $kode);
$tgl = $kategori === 'gtk'
    ? ((string)($pen['tanggal'] ?? '') ?: (string)($s['tanggal_4'] ?? ''))
    : ((string)($pen['tanggal_' . $tahun] ?? '') ?: (string)($s['tanggal_' . $tahun] ?? ''));
$tempat = (string)($pen['tempat'] ?? '') !== '' ? $pen['tempat'] : (string)$s['tempat'];

$judul = 'Lembar Penilaian ' . ($kategori === 'gtk' ? 'Tahun Keempat' : pilihan_tahun($tahun));
ob_start();
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Cetak <?= e($judul) ?> - <?= e($s['nama']) ?></title>
<link rel="stylesheet" href="assets/style.css?v=<?= e(APP_VERSION) ?>">
<style>
    @page { size: A4 landscape; margin: 10mm 10mm; }
    body { background: #fff; }
    .sheet { max-width: none; border: 0; padding: 0; }
    .wrap { padding: 0; max-width: none; }
    /* tabel lembar penilaian: rapat, berkepala berulang, baris tidak terpotong antar halaman */
    .tbl { width: 100%; table-layout: fixed; }
    .tbl th, .tbl td { padding: 2.5px 4px; font-size: .66rem; line-height: 1.32; }
    .tbl thead th { font-size: .66rem; }
    .tbl tbody tr { break-inside: avoid; page-break-inside: avoid; }
    .tbl thead { display: table-header-group; }
    .butir-no { width: 40px; }
    .butir-kode { width: 60px; }
    .butir-skor { width: 64px; }
    .tbl-nilai .skor-sel { display: none; }
    .tbl-nilai td.kolom-skor { text-align: center; font-weight: 700; }
    .cetak-tombol { display: flex; gap: 10px; margin-bottom: 12px; }
    .sheet-meta { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 1px 12px; }
    .sheet-meta div { border-bottom: 1px dotted #b9c6be; padding: 1px 0; }
    .sheet-meta span { display: inline-block; min-width: 108px; color: #4a5a52; }
    .sheet-meta b { color: #111; }
    .tabel-nilai-akhir { margin-top: 8px; }
    @media print { .cetak-tombol { display: none; } }
</style>
</head>
<body>
<div class="wrap" style="max-width:100%">
    <div class="cetak-tombol">
        <button class="btn" type="button" onclick="window.print()">Cetak / Simpan PDF</button>
        <a class="btn btn-ghost" href="<?= $kategori === 'gtk' ? 'penilaian.php' : 'penilaian_form.php?posisi=' . e($kode) . '&tahun=' . (int)$tahun ?>">Kembali</a>
    </div>
    <div class="sheet">
        <?= kop_cetak($s, 'Instrumen PKKM ' . ($kategori === 'gtk' ? 'Tahun Keempat' : 'Tahun ' . pilihan_tahun($tahun)), 'Penilaian Kinerja Kepala Madrasah') ?>
        <div class="sheet-judul"><?= e($judul) ?> &mdash; <?= e(penilai_label($kategori, $kode)) ?></div>
        <div class="sheet-sub"><?= e($kategori === 'gtk'
            ? 'Guru / Tenaga Kependidikan / Komite Madrasah / Kepala Bidang / Kepala Seksi'
            : ($kategori === PKKM_KATEGORI_TAMBAHAN
                ? 'Penilai Tambahan Tahun Keempat (di luar Pengawas I & II)'
                : 'Pengawas Madrasah')) ?></div>

        <?= kop_meta_baris([
            'Nama Penilai' => $pen['nama'] ?? '-',
            'NIP Penilai' => $pen['nip'] ?? '-',
            'Jabatan' => $pen['jabatan'] ?? '-',
            'Pangkat/Golongan' => $pen['pangkat'] ?? '-',
            'Unit Kerja' => $pen['unit_kerja'] ?? '-',
            'Nama Kepala Madrasah' => $s['kepala_nama'] ?: '-',
            'NIP Kepala' => $s['kepala_nip'] ?: '-',
            'Tahun Penilaian' => (string)($s['tahun_' . $tahun] ?: '-'),
            'Tanggal Penilaian' => tanggal_id($tgl) ?: '-',
            'Tempat' => $tempat !== '' ? $tempat : '-',
        ]) ?>

        <?php foreach (bagian_tahun($tahun) as $bk => $bagian): ?>
            <h4><?= e($bk) ?>. <?= e($bagian['judul']) ?></h4>
            <table class="tbl tbl-padat">
                <colgroup><col class="butir-no"><col class="butir-kode"><col><col><col class="butir-skor"></colgroup>
                <thead><tr><th class="butir-no">No</th><th class="butir-kode">No. UBT</th>
                    <th>Indikator Kinerja</th><th>Data Yang Diharapkan</th><th class="butir-skor">Skor</th></tr></thead>
                <tbody>
                <?php $no = 0; foreach ($bagian['butir'] as $kodeButir):
                    $b = instrumen_butir()[$kodeButir]; $no++; ?>
                    <tr><td class="tengah"><?= $no ?></td><td class="tengah"><?= e($kodeButir) ?></td>
                        <td><?= e($b['indikator']) ?></td><td><?= e($b['data']) ?></td>
                        <td class="tengah kolom-skor"><?= isset($skor[$kodeButir]) ? (int)$skor[$kodeButir] : '-' ?></td></tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot><tr><td colspan="4">Jumlah Skor <?= e($bk) ?>. <?= e($bagian['judul']) ?>
                        (maks <?= (int)$bagian['maks'] ?>) &mdash; Nilai Kompetensi</td>
                    <td class="tengah"><?= n2($rinci['bagian'][$bk]) ?></td></tr></tfoot>
            </table>
        <?php endforeach; ?>

        <table class="tbl tbl-padat tabel-nilai-akhir">
            <tfoot>
                <tr><td><b>NILAI KINERJA <?= e($kategori === 'gtk' ? 'TAHUN KEEMPAT' : strtoupper(pilihan_tahun($tahun))) ?></b></td>
                    <td class="tengah" style="width:120px"><b><?= n2($rinci['nilai']) ?></b></td>
                    <td class="tengah" style="width:150px">Predikat: <b><?= e(predikat_dari($rinci['nilai'])) ?></b></td></tr>
            </tfoot>
        </table>

        <p class="sheet-note">Nilai tiap tugas utama = (jumlah skor butir &divide; (4 &times; jumlah butir yang dinilai)) &times; 100.
            Nilai kinerja tahunan = rata-rata nilai tugas utama yang berlaku pada tahun tersebut.
            Butir terisi <?= (int)$rinci['terisi'] ?> dari <?= (int)$rinci['jumlah'] ?>.</p>

        <div class="sheet-foot">
            <?= ttd_blok("Mengetahui,\n" . ($s['kepala_nama'] ? 'Kepala Madrasah' : 'Kepala Madrasah'), $s['kepala_nama'] ?: '..............................', (string)($s['kepala_nip'] ?? ''), 'kiri', '', '&nbsp;') ?>
            <?= ttd_blok("Penilai,\n" . penilai_label($kategori, $kode), $pen['nama'] ?? '..............................', (string)($pen['nip'] ?? ''), 'kanan', ttd_tempat($tempat, $tgl)) ?>
        </div>
    </div>
</div>
<?php if (isset($_GET['auto'])): ?>
<script>window.addEventListener('load', function () { window.print(); });</script>
<?php endif; ?>
</body>
</html>
<?php
ob_end_flush();
