<?php
/**
 * e-PKKM - LEMBAR REKAP PKKM (dicetak langsung / disimpan PDF).
 *
 * Bentuk tiap lembar mengikuti contoh berkas instrumen pemilik:
 *   judul -> "Yang bertanda tangan di bawah ini:" -> Identitas Penilai -> "Menyatakan bahwa:" ->
 *   Identitas Kepala Madrasah -> kalimat penilaian -> tabel tugas utama -> tanda tangan.
 * Tahun Pertama-Ketiga memuat 4 tugas utama (A-D); Tahun Keempat 5 tugas utama (A-E).
 *
 * Satu lembar = satu halaman (6 lembar: 4 tahun + Tahun Keempat + Empat Tahunan).
 * Isi lembar dibangun inc/rekap_cetak.php supaya SAMA PERSIS dengan unduhan Word (rekap_docx.php).
 */
require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/rekap_cetak.php';

$u = require_login();
$s = require_sekolah();
$sid = (int)$s['id'];
$rekap = rekap_tahunan($sid);
$r4 = rekap_4tahun($sid);
$komp = rekap_komponen_tahun4($sid);

$identPenilai = rekap_identitas_penilai($sid);
$identKepala = rekap_identitas_kepala($s);
$barisPenilai = rekap_identitas_baris($identPenilai);
$barisKepala = rekap_identitas_baris($identKepala);
$tempat = $identPenilai['tempat'] !== '' ? $identPenilai['tempat'] : (string)($s['tempat'] ?? '');

if (!function_exists('rk_ident_html')) {
    /** Tabel identitas (label : nilai) pada lembar resmi. */
    function rk_ident_html(array $baris): string
    {
        $h = '<table class="rk-ident"><tbody>';
        foreach ($baris as $label => $nilai) {
            $h .= '<tr><th scope="row">' . e($label) . '</th><td class="rk-titik">:</td><td>'
                . e($nilai) . '</td></tr>';
        }
        return $h . '</tbody></table>';
    }
}

if (!function_exists('rk_ttd_html')) {
    /** Sepasang tanda tangan lembar resmi: Ketua Tim Penilai (kiri) & Kepala Madrasah (kanan). */
    function rk_ttd_html(array $identPenilai, array $s, string $tempat, string $tanggal): string
    {
        $namaKetua = $identPenilai['nama'] !== '' ? $identPenilai['nama'] : '..............................';
        $namaKepala = rekap_isi_teks($s['kepala_nama'] ?? '') !== ''
            ? rekap_isi_teks($s['kepala_nama'] ?? '') : '..............................';
        $h = '<div class="sheet-foot dua-ttd rk-ttd">';
        $h .= ttd_blok_rapi('Ketua Tim Penilai', $namaKetua, (string)($identPenilai['nip'] ?? ''), 'kiri');
        $h .= ttd_blok_rapi('Kepala Madrasah', $namaKepala, (string)($s['kepala_nip'] ?? ''), 'kanan',
            ttd_tempat($tempat, $tanggal));
        return $h . '</div>';
    }
}

ob_start();
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Cetak Rekap PKKM - <?= e($s['nama']) ?></title>
<link rel="stylesheet" href="assets/style.css?v=<?= e(APP_VERSION) ?>">
<style>
    /* @page WAJIB di halaman (ukuran kertas PDF); seluruh gaya lembar resmi ada di
       assets/style.css blok "LEMBAR RESMI PKKM". */
    @page { size: A4 portrait; margin: 14mm 16mm; }
    body { background: #fff; }
    .wrap { padding: 0; max-width: none; }
</style>
</head>
<body>
<div class="wrap">
    <div class="cetak-tombol">
        <button class="btn" type="button" onclick="window.print()">Cetak / Simpan PDF</button>
        <a class="btn btn-emas" href="cetak_rekap.php?auto=1">Cetak Langsung</a>
        <a class="btn btn-ghost" href="rekap_docx.php">Unduh Word (.docx)</a>
        <a class="btn btn-ghost" href="rekap.php">Kembali</a>
    </div>

    <?php foreach ($rekap as $tahun => $r):
        $judul = rekap_judul_tahun((int)$tahun);
        $tbl = rekap_tabel_tahun($sid, (int)$tahun, $r);
        $jmlKanan = count($tbl['kolom']);
        $kelasTbl = 'tbl rk-tbl' . ($jmlKanan > 6 ? ' rk-padat' : ($jmlKanan > 4 ? ' rk-banyak' : ''));
        $tanggal = rekap_tanggal($sid, $s, (int)$tahun); ?>
        <div class="sheet rk-sheet" data-tahun="<?= (int)$tahun ?>">
            <div class="rk-judul"><?= e($judul[0]) ?></div>
            <div class="rk-judul2"><?= e($judul[1]) ?></div>

            <p class="rk-ayat">Yang bertanda tangan di bawah ini:</p>
            <div class="rk-sub">Identitas Penilai</div>
            <?= rk_ident_html($barisPenilai) ?>

            <p class="rk-ayat">Menyatakan bahwa:</p>
            <div class="rk-sub">Identitas Kepala Madrasah</div>
            <?= rk_ident_html($barisKepala) ?>

            <p class="rk-ayat"><?= e(rekap_kalimat_penilaian('Penilaian Kinerja Tahunan', rekap_isi_teks(tahun_penilaian_tampil($sid, $s, (int)$tahun)))) ?></p>

            <table class="<?= e($kelasTbl) ?>">
                <colgroup><col class="rk-no"><col><?php for ($i = 0; $i < $jmlKanan; $i++): ?><col class="rk-num"><?php endfor; ?></colgroup>
                <thead><tr><th class="tengah">No</th><th class="tengah">Tugas Utama</th>
                    <?php foreach ($tbl['kolom'] as $k): ?>
                        <th><?= e($k['label']) ?></th>
                    <?php endforeach; ?></tr></thead>
                <tbody>
                <?php foreach ($tbl['baris'] as $b): ?>
                    <tr><td class="tengah"><?= (int)$b['no'] ?>.</td><td><?= e($b['judul']) ?></td>
                        <?php foreach ($tbl['kolom'] as $k): ?>
                            <td class="tengah"><?= e($k['kunci'] === 'rerata' ? $b['rerata'] : ($b['nilai'][$k['kunci']] ?? '-')) ?></td>
                        <?php endforeach; ?></tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr><td colspan="2">Jumlah Rerata</td>
                        <?php foreach ($tbl['kolom'] as $k): ?>
                            <td class="tengah"><?= e($k['kunci'] === 'rerata' ? $tbl['kaki']['rerata'] : ($tbl['kaki']['nilai'][$k['kunci']] ?? '-')) ?></td>
                        <?php endforeach; ?></tr>
                    <tr><td colspan="<?= 1 + $jmlKanan ?>">Predikat Penilaian Kinerja <?= e(PKKM_TAHUN_LIST[$tahun]) ?></td>
                        <td class="tengah"><?= e($tbl['predikat']) ?></td></tr>
                </tfoot>
            </table>

            <?= rk_ttd_html($identPenilai, $s, $tempat, $tanggal) ?>
        </div>
    <?php endforeach; ?>

    <?php // ---------------- Lembar 5: Rekap Tahun Keempat (komponen 7 penilai) ----------------
    $barisKomp = rekap_baris_komponen_tahun4($komp);
    $slotRekap = penilai_sekolah_slots($sid); ?>
    <div class="sheet rk-sheet" data-tahun="4-komponen">
        <div class="rk-judul">Rekapitulasi Penilaian Kinerja Tahun Keempat</div>
        <div class="rk-judul2">Kepala Madrasah</div>

        <p class="rk-ayat">Yang bertanda tangan di bawah ini:</p>
        <div class="rk-sub">Identitas Penilai</div>
        <?= rk_ident_html($barisPenilai) ?>

        <p class="rk-ayat">Menyatakan bahwa:</p>
        <div class="rk-sub">Identitas Kepala Madrasah</div>
        <?= rk_ident_html($barisKepala) ?>

        <p class="rk-ayat"><?= e(rekap_kalimat_penilaian('Penilaian Kinerja Tahun Keempat', rekap_isi_teks(tahun_penilaian_tampil($sid, $s, 4)))) ?></p>
        <p class="rk-note">Dinilai oleh <?= count($slotRekap) ?> penilai Tahun Keempat
            (<?= e(status_lembaga_label(status_lembaga_sekolah($sid))) ?>:
            <?= e(implode(', ', array_map(fn($sl) => $sl['label'], $slotRekap))) ?>).</p>

        <table class="tbl rk-tbl">
            <colgroup><col class="rk-no"><col><col class="rk-num"></colgroup>
            <thead><tr><th class="tengah">No</th><th class="tengah">Jenis Penilaian</th><th class="tengah">Skor</th></tr></thead>
            <tbody>
            <?php foreach ($barisKomp as $b): ?>
                <tr><td class="tengah"><?= (int)$b['no'] ?>.</td><td><?= e($b['judul']) ?></td>
                    <td class="tengah"><?= e($b['nilai']) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr><td colspan="2">Hasil Penilaian Kinerja Tahun Keempat</td>
                    <td class="tengah"><?= e(rekap_nilai_teks($komp['jumlah'])) ?></td></tr>
                <tr><td colspan="2">Predikat Penilaian Kinerja Tahun Keempat</td>
                    <td class="tengah"><?= e(predikat_dari($komp['jumlah'])) ?></td></tr>
            </tfoot>
        </table>

        <p class="rk-note">Nilai Tahun Keempat = rata-rata komponen yang sudah terisi
            (<?= e(implode(', ', array_map(fn($k) => $k['label'], $komp['komponen']))) ?>)
            &mdash; <?= (int)$komp['ada'] ?> dari <?= count($komp['komponen']) ?> komponen.</p>

        <?= rk_ttd_html($identPenilai, $s, $tempat, rekap_tanggal($sid, $s, 4)) ?>
    </div>

    <?php // ---------------- Lembar 6: Rekap Empat Tahunan (NKKM) ----------------
    $baris4 = rekap_baris_empat_tahunan($r4); ?>
    <div class="sheet rk-sheet" data-tahun="4-tahunan">
        <div class="rk-judul">Rekapitulasi Penilaian Kinerja Empat Tahunan</div>
        <div class="rk-judul2">Kepala Madrasah</div>

        <p class="rk-ayat">Yang bertanda tangan di bawah ini:</p>
        <div class="rk-sub">Identitas Penilai</div>
        <?= rk_ident_html($barisPenilai) ?>

        <p class="rk-ayat">Menyatakan bahwa:</p>
        <div class="rk-sub">Identitas Kepala Madrasah</div>
        <?= rk_ident_html($barisKepala) ?>

        <p class="rk-ayat"><?= e(rekap_kalimat_penilaian('Penilaian Kinerja Empat Tahunan', rekap_isi_teks(tahun_penilaian_tampil($sid, $s, 4)))) ?></p>

        <table class="tbl rk-tbl">
            <colgroup><col class="rk-no"><col><col class="rk-num"></colgroup>
            <thead><tr><th class="tengah">No</th><th class="tengah">Jenis Penilaian</th><th class="tengah">Skor</th></tr></thead>
            <tbody>
            <?php foreach ($baris4 as $b): ?>
                <tr><td class="tengah"><?= (int)$b['no'] ?>.</td><td><?= e($b['judul']) ?></td>
                    <td class="tengah"><?= e($b['nilai']) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr><td colspan="2">Nilai Kinerja Kepala Madrasah (NKKM)</td>
                    <td class="tengah"><?= e(rekap_nilai_teks($r4['nilai'])) ?></td></tr>
                <tr><td colspan="2">Predikat Nilai Kinerja Kepala Madrasah</td>
                    <td class="tengah"><?= e($r4['predikat']) ?></td></tr>
            </tfoot>
        </table>

        <p class="rk-note">NKKM = (rata-rata nilai Tahun Pertama-Ketiga + 5 &times; nilai Tahun Keempat) &divide; 6.
            Nilai tiap tahun = gabungan seluruh penilai; nilai Tahun Keempat = rata-rata komponen yang
            sudah terisi (<?= (int)$komp['ada'] ?> dari <?= count($komp['komponen']) ?>).</p>

        <?= rk_ttd_html($identPenilai, $s, $tempat, rekap_tanggal($sid, $s, 4)) ?>
    </div>
</div>
<?php if (isset($_GET['auto'])): ?>
<script>window.addEventListener('load', function () { window.print(); });</script>
<?php endif; ?>
</body>
</html>
<?php
ob_end_flush();
