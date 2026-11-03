<?php
/**
 * e-PKKM - CETAK LAPORAN per tahun (lembar resmi, satu tahun satu halaman).
 *
 * Permintaan pemilik: "Cetak laporan per tahun di menu laporan di akun penilai diperbaiki dan
 * dirapikan." Lembar ini sekarang memakai bentuk yang sama dengan lembar rekap resmi:
 *   judul -> "Yang bertanda tangan di bawah ini:" -> Identitas Penilai -> "Menyatakan bahwa:" ->
 *   Identitas Kepala Madrasah -> kalimat penilaian -> tabel tugas utama -> tanda tangan rapi
 * (Kepala Madrasah di kiri, Penilai di kanan dengan baris "tempat, tanggal" di atas nama).
 *
 * Parameter:
 *   ?posisi=1|2|…|semua   penilai yang dicetak (bawaan 1; "semua" = semua penilai pengawas)
 *   ?tahun=1..4           hanya satu tahun (kosong = seluruh tahun)
 *   ?auto=1               langsung mencetak
 */
require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/form_penilaian.php';
require_once __DIR__ . '/inc/rekap_cetak.php';

$u = require_login();
$s = require_sekolah();
$sid = (int)$s['id'];

$semua = isset($_GET['semua']) || (string)($_GET['posisi'] ?? '') === 'semua';
$posisi = (string)(int)($_GET['posisi'] ?? 1);
if (!array_key_exists($posisi, PKKM_PENILAI_PENGAWAS)) {
    $posisi = '1';
}
$daftar = $semua ? ['1', '2'] : [$posisi];
$tahunFilter = (int)($_GET['tahun'] ?? 0);
$tahunList = array_key_exists($tahunFilter, PKKM_TAHUN_LIST) ? [$tahunFilter] : array_keys(PKKM_TAHUN_LIST);

$rekap = rekap_tahunan($sid);
$identKepala = rekap_identitas_kepala($s);
$barisKepala = rekap_identitas_baris($identKepala);

if (!function_exists('lp_identitas_penilai')) {
    /** Identitas penilai satu posisi (akun penilai lebih dulu, cadangan baris tabel `penilai`). */
    function lp_identitas_penilai(int $sekolahId, string $kode): array
    {
        $baris = penilai_ambil($sekolahId, 'pengawas', $kode);
        $ident = [
            'nama' => (string)($baris['nama'] ?? ''),
            'nip' => (string)($baris['nip'] ?? ''),
            'pangkat' => (string)($baris['pangkat'] ?? ''),
            'jabatan' => (string)($baris['jabatan'] ?? ''),
            'unit_kerja' => (string)($baris['unit_kerja'] ?? ''),
            'tempat' => (string)($baris['tempat'] ?? ''),
        ];
        // Tanggal menilai: diisi penilai sendiri per tahun (kolom tanggal_1..4)
        foreach (array_keys(PKKM_TAHUN_LIST) as $t) {
            $ident['tanggal_' . $t] = penilai_tanggal($sekolahId, 'pengawas', $kode, (int)$t);
        }
        return $ident;
    }
}

ob_start();
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Cetak Laporan PKKM - <?= e($s['nama']) ?></title>
<link rel="stylesheet" href="assets/style.css?v=<?= e(APP_VERSION) ?>">
<style>
    /* @page WAJIB di halaman (ukuran kertas PDF); gaya lembar resmi ada di assets/style.css. */
    @page { size: A4 portrait; margin: 14mm 16mm; }
    body { background: #fff; }
    .wrap { padding: 0; max-width: none; }
</style>
</head>
<body>
<div class="wrap">
    <div class="cetak-tombol">
        <button class="btn" type="button" onclick="window.print()">Cetak / Simpan PDF</button>
        <a class="btn btn-emas" href="cetak_laporan.php?semua=1<?= $tahunFilter ? '&tahun=' . (int)$tahunFilter : '' ?>&auto=1">Cetak Langsung</a>
        <?php if (!$semua): ?>
            <a class="btn btn-ghost" href="cetak_laporan.php?semua=1<?= $tahunFilter ? '&tahun=' . (int)$tahunFilter : '' ?>">Cetak Kedua Penilai</a>
        <?php endif; ?>
        <?php foreach (PKKM_TAHUN_LIST as $t => $label): ?>
            <a class="btn btn-kecil <?= (int)$tahunFilter === (int)$t ? 'btn-emas' : 'btn-ghost' ?>"
               href="cetak_laporan.php?<?= $semua ? 'semua=1' : 'posisi=' . e($posisi) ?>&tahun=<?= (int)$t ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
        <?php if ($tahunFilter): ?>
            <a class="btn btn-kecil btn-ghost" href="cetak_laporan.php?<?= $semua ? 'semua=1' : 'posisi=' . e($posisi) ?>">Semua Tahun</a>
        <?php endif; ?>
        <a class="btn btn-ghost" href="laporan.php">Kembali</a>
    </div>

    <?php foreach ($daftar as $kode):
        $pen = lp_identitas_penilai($sid, $kode);
        $barisPenilai = rekap_identitas_baris([
            'nama' => $pen['nama'], 'nip' => $pen['nip'], 'pangkat' => $pen['pangkat'],
            'jabatan' => $pen['jabatan'], 'unit_kerja' => $pen['unit_kerja'],
        ]);
        $tempat = rekap_isi_teks($pen['tempat']) !== '' ? $pen['tempat'] : (string)($s['tempat'] ?? '');
        $namaPenilai = rekap_isi_teks($pen['nama']) !== '' ? $pen['nama'] : '..............................';
        foreach ($tahunList as $tahun):
            $r = $rekap[$tahun]['kode'][$kode] ?? null;
            $tgl = rekap_isi_teks($pen['tanggal_' . $tahun] ?? '') !== ''
                ? $pen['tanggal_' . $tahun] : (string)($s['tanggal_' . $tahun] ?? '');
            $labelTahun = PKKM_TAHUN_LIST[$tahun];
            $labelPenilai = penilai_posisi_teks($kode, status_lembaga_sekolah($sid));
            $nilai = $r['nilai'] ?? null; ?>
            <div class="sheet rk-sheet" data-tahun="<?= (int)$tahun ?>" data-posisi="<?= e($kode) ?>">
                <div class="rk-judul">Laporan Hasil Penilaian Kinerja Tahunan Kepala Madrasah</div>
                <div class="rk-judul2"><?= e($labelTahun) ?> &mdash; <?= e($labelPenilai) ?></div>

                <p class="rk-ayat">Yang bertanda tangan di bawah ini:</p>
                <div class="rk-sub">Identitas Penilai</div>
                <table class="rk-ident"><tbody>
                    <?php foreach ($barisPenilai as $label => $isi): ?>
                        <tr><th scope="row"><?= e($label) ?></th><td class="rk-titik">:</td><td><?= e($isi) ?></td></tr>
                    <?php endforeach; ?>
                </tbody></table>

                <p class="rk-ayat">Menyatakan bahwa:</p>
                <div class="rk-sub">Identitas Kepala Madrasah</div>
                <table class="rk-ident"><tbody>
                    <?php foreach ($barisKepala as $label => $isi): ?>
                        <tr><th scope="row"><?= e($label) ?></th><td class="rk-titik">:</td><td><?= e($isi) ?></td></tr>
                    <?php endforeach; ?>
                </tbody></table>

                <p class="rk-ayat"><?= e(rekap_kalimat_penilaian('Penilaian Kinerja Tahunan',
                    rekap_isi_teks($s['tahun_' . $tahun] ?? ''))) ?></p>

                <table class="tbl rk-tbl">
                    <colgroup><col class="rk-no"><col><col class="rk-num"><col class="rk-num"></colgroup>
                    <thead><tr><th class="tengah">No</th><th class="tengah">Tugas Utama</th>
                        <th>Jumlah Butir</th><th>Skor</th></tr></thead>
                    <tbody>
                    <?php $no = 0; foreach (bagian_tahun($tahun) as $bk => $b): $no++; ?>
                        <tr><td class="tengah"><?= $no ?>.</td>
                            <td><?= e($b['judul']) ?></td>
                            <td class="tengah"><?= count($b['butir']) ?></td>
                            <td class="tengah"><?= rekap_nilai_teks($r['bagian'][$bk] ?? null) ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr><td colspan="3">Nilai Kinerja Tahunan</td>
                            <td class="tengah"><?= rekap_nilai_teks($nilai) ?></td></tr>
                        <tr><td colspan="3">Predikat</td>
                            <td class="tengah"><?= e(predikat_dari($nilai)) ?></td></tr>
                        <tr><td colspan="3">Butir Terisi</td>
                            <td class="tengah"><?= $r ? (int)$r['terisi'] . ' / ' . (int)$r['jumlah'] : '-' ?></td></tr>
                    </tfoot>
                </table>

                <p class="rk-note">Nilai tiap tugas utama = (jumlah skor butir &divide; (4 &times; jumlah butir)) &times; 100;
                    nilai kinerja tahunan = rata-rata seluruh tugas utama yang berlaku pada tahun ini.
                    Predikat mengikuti ambang PermenPANRB 6/2022 (Amat Baik &ge; 91; Baik 81-90,99;
                    Cukup 71-80,99; Kurang &lt; 71).</p>

                <div class="sheet-foot dua-ttd rk-ttd">
                    <?= ttd_blok_rapi('Kepala Madrasah',
                        rekap_isi_teks($s['kepala_nama'] ?? '') !== '' ? (string)$s['kepala_nama'] : '..............................',
                        (string)($s['kepala_nip'] ?? ''), 'kiri') ?>
                    <?= ttd_blok_rapi('Penilai', $namaPenilai, (string)($pen['nip'] ?? ''), 'kanan',
                        ttd_tempat($tempat, $tgl)) ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endforeach; ?>
</div>
<?php if (isset($_GET['auto'])): ?>
<script>window.addEventListener('load', function () { window.print(); });</script>
<?php endif; ?>
</body>
</html>
<?php
ob_end_flush();
