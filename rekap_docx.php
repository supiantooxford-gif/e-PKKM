<?php
/**
 * e-PKKM - UNDUHAN WORD (.docx) lembar REKAP PKKM.
 *
 * Isinya SAMA PERSIS dengan lembar cetak (cetak_rekap.php) karena keduanya memakai penyusun yang
 * sama: inc/rekap_cetak.php. Berkas .docx dibuat sendiri (inc/docx.php) tanpa pustaka luar,
 * berukuran A4 potrait dengan margin 2,5 cm sehingga hasil unduhannya rapi & siap dicetak.
 */
require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/rekap_cetak.php';
require_once __DIR__ . '/inc/docx.php';

$u = require_login();
$s = require_sekolah();
$sid = (int)$s['id'];
$rekap = rekap_tahunan($sid);
$r4 = rekap_4tahun($sid);
$komp = rekap_komponen_tahun4($sid);

$identPenilai = rekap_identitas_penilai($sid);
$identKepala = rekap_identitas_kepala($s);
$tempat = $identPenilai['tempat'] !== '' ? $identPenilai['tempat'] : (string)($s['tempat'] ?? '');

if (!function_exists('rk_docx_identitas')) {
    /** Blok identitas Word (tabel tanpa garis: label · ":" · nilai). */
    function rk_docx_identitas(array $baris): array
    {
        $isi = [];
        foreach ($baris as $label => $nilai) {
            $isi[] = [(string)$label, ':', (string)$nilai];
        }
        return [
            'tabel' => [
                ['', '', ''],
                $isi,
                ['border' => false, 'kepala' => false, 'lebar' => [2268, 567, 6235],
                    'tengah' => [false, false, false]],
            ],
        ];
    }
}

if (!function_exists('rk_docx_ttd')) {
    /** Blok tanda tangan Word: Ketua Tim Penilai (kiri) & Kepala Madrasah (kanan) — nama sejajar. */
    function rk_docx_ttd(array $identPenilai, array $s, string $tempat, string $tanggal): array
    {
        $namaKetua = $identPenilai['nama'] !== '' ? $identPenilai['nama'] : '..............................';
        $namaKepala = rekap_isi_teks($s['kepala_nama'] ?? '') !== ''
            ? rekap_isi_teks($s['kepala_nama'] ?? '') : '..............................';
        $nipKetua = rekap_isi_teks($identPenilai['nip'] ?? '');
        $nipKepala = rekap_isi_teks($s['kepala_nip'] ?? '');
        $kiri = "\nKetua Tim Penilai,\n\n\n" . $namaKetua . ($nipKetua !== '' ? "\nNIP. " . $nipKetua : '');
        $barisTempat = ttd_tempat($tempat, $tanggal);
        $kanan = ($barisTempat !== '' ? $barisTempat : '-')
            . "\nKepala Madrasah,\n\n\n" . $namaKepala . ($nipKepala !== '' ? "\nNIP. " . $nipKepala : '');
        return [
            'tabel' => [
                ['', ''],
                [[['teks' => $kiri, 'tengah' => true], ['teks' => $kanan, 'tengah' => true]]],
                ['border' => false, 'kepala' => false, 'lebar' => [4535, 4535]],
            ],
        ];
    }
}

$blok = [];

/** Judul + identitas yang sama pada setiap lembar. */
$judulBlok = function (string $judul, string $baris2, array $identPenilai, array $identKepala,
    string $kalimat): array {
    return [
        ['teks' => $judul, 'tebal' => true, 'tengah' => true, 'ukuran' => 25, 'jarak' => 0],
        ['teks' => $baris2, 'tebal' => true, 'tengah' => true, 'ukuran' => 24, 'jarak' => 200],
        ['teks' => 'Yang bertanda tangan di bawah ini:', 'jarak' => 20],
        ['teks' => 'Identitas Penilai', 'tebal' => true, 'indent' => 1134, 'jarak' => 20],
        rk_docx_identitas(rekap_identitas_baris($identPenilai)),
        ['teks' => 'Menyatakan bahwa:', 'jarak' => 60],
        ['teks' => 'Identitas Kepala Madrasah', 'tebal' => true, 'indent' => 1134, 'jarak' => 20],
        rk_docx_identitas(rekap_identitas_baris($identKepala)),
        ['teks' => $kalimat, 'jarak' => 80],
    ];
};

foreach ($rekap as $tahun => $r) {
    if ($blok) {
        $blok[] = ['halaman' => true];
    }
    $judul = rekap_judul_tahun((int)$tahun);
    $tbl = rekap_tabel_tahun($sid, (int)$tahun, $r);
    $jmlKanan = count($tbl['kolom']);

    $blok = array_merge($blok, $judulBlok(
        $judul[0],
        $judul[1],
        $identPenilai,
        $identKepala,
        rekap_kalimat_penilaian('Penilaian Kinerja Tahunan', rekap_isi_teks($s['tahun_' . $tahun] ?? ''))
    ));

    // kolom tabel: No | Tugas Utama | (kolom penilai…) | Rerata
    $kolom = ['No', 'Tugas Utama'];
    foreach ($tbl['kolom'] as $k) {
        $kolom[] = $k['label'];
    }
    $wNum = (int)min(1500, max(680, floor((9070 - 700 - 2600) / max(1, $jmlKanan))));
    $lebar = array_merge([700], [9070 - 700 - $wNum * $jmlKanan], array_fill(0, $jmlKanan, $wNum));

    $baris = [];
    foreach ($tbl['baris'] as $b) {
        $sel = [(string)$b['no'] . '.', $b['judul']];
        foreach ($tbl['kolom'] as $k) {
            $sel[] = $k['kunci'] === 'rerata' ? $b['rerata'] : ($b['nilai'][$k['kunci']] ?? '-');
        }
        $baris[] = $sel;
    }
    $kaki = [['teks' => 'Jumlah Rerata', 'span' => 2, 'tebal' => true]];
    foreach ($tbl['kolom'] as $k) {
        $kaki[] = ['teks' => $k['kunci'] === 'rerata' ? $tbl['kaki']['rerata'] : ($tbl['kaki']['nilai'][$k['kunci']] ?? '-'), 'tebal' => true];
    }
    $baris[] = $kaki;
    $baris[] = [
        ['teks' => 'Predikat Penilaian Kinerja ' . (PKKM_TAHUN_LIST[$tahun] ?? ''),
            'span' => 1 + $jmlKanan, 'tebal' => true],
        ['teks' => $tbl['predikat'], 'tebal' => true],
    ];

    $blok[] = ['tabel' => [$kolom, $baris, ['lebar' => $lebar, 'tengah' => array_merge([true, false], array_fill(0, $jmlKanan, true))]]];
    $blok[] = rk_docx_ttd($identPenilai, $s, $tempat, rekap_tanggal($sid, $s, (int)$tahun));
}

// ---------------- Lembar Tahun Keempat (komponen 7 penilai) ----------------
$blok[] = ['halaman' => true];
$blok = array_merge($blok, $judulBlok(
    'Rekapitulasi Penilaian Kinerja Tahun Keempat',
    'Kepala Madrasah',
    $identPenilai,
    $identKepala,
    rekap_kalimat_penilaian('Penilaian Kinerja Tahun Keempat', rekap_isi_teks($s['tahun_4'] ?? ''))
));
$slotRekap = penilai_sekolah_slots($sid);
$blok[] = ['teks' => 'Dinilai oleh ' . count($slotRekap) . ' penilai Tahun Keempat ('
    . status_lembaga_label(status_lembaga_sekolah($sid)) . ': '
    . implode(', ', array_map(fn($sl) => $sl['label'], $slotRekap)) . ').', 'ukuran' => 19, 'jarak' => 40];
$barisKomp = [];
foreach (rekap_baris_komponen_tahun4($komp) as $b) {
    $barisKomp[] = [(string)$b['no'] . '.', $b['judul'], $b['nilai']];
}
$barisKomp[] = [['teks' => 'Hasil Penilaian Kinerja Tahun Keempat', 'span' => 2, 'tebal' => true],
    ['teks' => rekap_nilai_teks($komp['jumlah']), 'tebal' => true]];
$barisKomp[] = [['teks' => 'Predikat Penilaian Kinerja Tahun Keempat', 'span' => 2, 'tebal' => true],
    ['teks' => predikat_dari($komp['jumlah']), 'tebal' => true]];
$blok[] = ['tabel' => [
    ['No', 'Jenis Penilaian', 'Skor'],
    $barisKomp,
    ['lebar' => [700, 6870, 1500], 'tengah' => [true, false, true]],
]];
$blok[] = ['teks' => 'Nilai Tahun Keempat = rata-rata komponen yang sudah terisi ('
    . implode(', ', array_map(fn($k) => $k['label'], $komp['komponen'])) . ') — '
    . (int)$komp['ada'] . ' dari ' . count($komp['komponen']) . ' komponen.', 'ukuran' => 19, 'jarak' => 60];
$blok[] = rk_docx_ttd($identPenilai, $s, $tempat, rekap_tanggal($sid, $s, 4));

// ---------------- Lembar Empat Tahunan (NKKM) ----------------
$blok[] = ['halaman' => true];
$blok = array_merge($blok, $judulBlok(
    'Rekapitulasi Penilaian Kinerja Empat Tahunan',
    'Kepala Madrasah',
    $identPenilai,
    $identKepala,
    rekap_kalimat_penilaian('Penilaian Kinerja Empat Tahunan', rekap_isi_teks($s['tahun_4'] ?? ''))
));
$baris4 = [];
foreach (rekap_baris_empat_tahunan($r4) as $b) {
    $baris4[] = [(string)$b['no'] . '.', $b['judul'], $b['nilai']];
}
$baris4[] = [['teks' => 'Nilai Kinerja Kepala Madrasah (NKKM)', 'span' => 2, 'tebal' => true],
    ['teks' => rekap_nilai_teks($r4['nilai']), 'tebal' => true]];
$baris4[] = [['teks' => 'Predikat Nilai Kinerja Kepala Madrasah', 'span' => 2, 'tebal' => true],
    ['teks' => $r4['predikat'], 'tebal' => true]];
$blok[] = ['tabel' => [
    ['No', 'Jenis Penilaian', 'Skor'],
    $baris4,
    ['lebar' => [700, 6870, 1500], 'tengah' => [true, false, true]],
]];
$blok[] = ['teks' => 'NKKM = (rata-rata nilai Tahun Pertama-Ketiga + 5 x nilai Tahun Keempat) : 6. '
    . 'Nilai tiap tahun = gabungan seluruh penilai; nilai Tahun Keempat = rata-rata komponen yang sudah '
    . 'terisi (' . (int)$komp['ada'] . ' dari ' . count($komp['komponen']) . ').', 'ukuran' => 19, 'jarak' => 60];
$blok[] = rk_docx_ttd($identPenilai, $s, $tempat, rekap_tanggal($sid, $s, 4));

$nama = 'Rekap-PKKM-' . preg_replace('/[^A-Za-z0-9]+/', '-', (string)$s['nama']) . '.docx';
docx_kirim($nama, docx_bangun($blok, 'Rekap PKKM ' . $s['nama']));
