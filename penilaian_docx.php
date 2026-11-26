<?php
/**
 * e-PKKM - UNDUHAN WORD (.docx) LEMBAR BUTIR PENILAIAN satu tahun.
 *
 * Permintaan pemilik: "Pada akun penilai tambahkan fitur download word dan cetak butir penilaian
 * setiap tahunnya." Isinya sama dengan lembar cetak `cetak_penilaian.php` (butir per tugas utama
 * beserta skor 1-4 yang sudah diisi), tetapi berbentuk berkas Word siap cetak.
 *
 * Parameter: ?posisi=1|2|3…&tahun=1..4
 */
require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/form_penilaian.php';
require_once __DIR__ . '/inc/rekap_cetak.php';
require_once __DIR__ . '/inc/docx.php';

$u = require_login();
$s = require_sekolah();
$sid = (int)$s['id'];

$posisi = (string)(int)($_GET['posisi'] ?? 0);
$tahun = (int)($_GET['tahun'] ?? 0);
$posisiSah = array_key_exists($posisi, PKKM_PENILAI_PENGAWAS) || penilai_posisi_tambahan($posisi);
if (!$posisiSah || !array_key_exists($tahun, PKKM_TAHUN_LIST)) {
    flash('Permintaan unduhan lembar penilaian tidak dikenal.', 'err');
    redirect('penilaian.php');
}
if (penilai_posisi_tambahan($posisi) && $tahun !== 4) {
    flash('Penilai Tambahan hanya mengisi penilaian Tahun Keempat.', 'err');
    redirect('penilaian.php');
}
$kategori = penilai_kategori_posisi($posisi);

$p = penilaian_ambil($sid, $kategori, $posisi, $tahun, false);
$skor = $p ? penilaian_skor((int)$p['id']) : [];
$rinci = nilai_rinci($skor, $tahun);
$pen = penilai_ambil($sid, $kategori, $posisi);
$tanggal = penilai_tanggal($sid, $kategori, $posisi, $tahun);
if ($tanggal === '') {
    $tanggal = (string)($s['tanggal_' . $tahun] ?? '');
}
$tempat = rekap_isi_teks($pen['tempat'] ?? '') !== '' ? (string)$pen['tempat'] : (string)($s['tempat'] ?? '');
$namaPenilai = rekap_isi_teks($pen['nama'] ?? '') !== '' ? (string)$pen['nama'] : (string)$u['nama'];

if (!function_exists('pn_docx_identitas')) {
    /** Blok identitas Word (tabel tanpa garis: label · ":" · nilai). */
    function pn_docx_identitas(array $baris): array
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

if (!function_exists('pn_docx_ttd')) {
    /** Blok tanda tangan: Kepala Madrasah (kiri) & Penilai (kanan) — nama sejajar. */
    function pn_docx_ttd(array $s, string $namaPenilai, string $nipPenilai, string $label, string $tempat, string $tanggal): array
    {
        $namaKepala = rekap_isi_teks($s['kepala_nama'] ?? '') !== ''
            ? rekap_isi_teks($s['kepala_nama'] ?? '') : '..............................';
        $nipKepala = rekap_isi_teks($s['kepala_nip'] ?? '');
        $barisTempat = ttd_tempat($tempat, $tanggal);
        $kiri = "\nKepala Madrasah,\n\n\n" . $namaKepala . ($nipKepala !== '' ? "\nNIP. " . $nipKepala : '');
        $kanan = ($barisTempat !== '' ? $barisTempat : '-') . "\n" . $label . ",\n\n\n" . $namaPenilai
            . ($nipPenilai !== '' ? "\nNIP. " . $nipPenilai : '');
        return [
            'tabel' => [
                ['', ''],
                [[['teks' => $kiri, 'tengah' => true], ['teks' => $kanan, 'tengah' => true]]],
                ['border' => false, 'kepala' => false, 'lebar' => [4535, 4535]],
            ],
        ];
    }
}

$identPenilai = [
    'Nama Penilai' => $namaPenilai,
    'NIP Penilai' => rekap_isi_teks($pen['nip'] ?? '') !== '' ? (string)$pen['nip'] : '-',
    'Jabatan' => rekap_isi_teks($pen['jabatan'] ?? '') !== '' ? (string)$pen['jabatan'] : '-',
    'Pangkat/Golongan' => rekap_isi_teks($pen['pangkat'] ?? '') !== '' ? (string)$pen['pangkat'] : '-',
    'Unit Kerja' => rekap_isi_teks($pen['unit_kerja'] ?? '') !== '' ? (string)$pen['unit_kerja'] : '-',
];
$identKepala = [
    'Nama' => rekap_isi_teks($s['kepala_nama'] ?? '') !== '' ? (string)$s['kepala_nama'] : '-',
    'NIP' => rekap_isi_teks($s['kepala_nip'] ?? '') !== '' ? (string)$s['kepala_nip'] : '-',
    'Pangkat/Golongan' => rekap_isi_teks($s['kepala_pangkat'] ?? '') !== '' ? (string)$s['kepala_pangkat'] : '-',
    'Jabatan' => 'Kepala Madrasah',
    'Unit Kerja' => (string)$s['nama'],
];
$identMadrasah = [
    'Madrasah' => (string)$s['nama'],
    'NPSN' => rekap_isi_teks($s['npsn'] ?? '') !== '' ? (string)$s['npsn'] : '-',
    'NSM' => rekap_isi_teks($s['nsm'] ?? '') !== '' ? (string)$s['nsm'] : '-',
    'Tahun Penilaian' => tahun_penilaian_tampil($sid, $s, (int)$tahun) ?: '-',
    'Tanggal Menilai' => tanggal_id($tanggal) ?: '-',
];

$label = penilai_label($kategori, $posisi);
$judul = 'Instrumen Penilaian Kinerja Kepala Madrasah';
$sub = 'Lembar Penilaian ' . pilihan_tahun($tahun);

$blok = [
    ['teks' => $judul, 'tebal' => true, 'tengah' => true, 'ukuran' => 25, 'jarak' => 0],
    ['teks' => $sub . ' — ' . $label, 'tebal' => true, 'tengah' => true, 'ukuran' => 23, 'jarak' => 160],
    ['teks' => 'Identitas Penilai', 'tebal' => true, 'jarak' => 20],
    pn_docx_identitas($identPenilai),
    ['teks' => 'Identitas Kepala Madrasah', 'tebal' => true, 'jarak' => 20],
    pn_docx_identitas($identKepala),
    ['teks' => 'Keterangan Lembar', 'tebal' => true, 'jarak' => 20],
    pn_docx_identitas($identMadrasah),
];

// ---- tabel butir per tugas utama ----
$lebar = [480, 800, 3550, 3550, 690];   // jumlah = 9070 dxa (lebar teks A4 potrait)
foreach (bagian_tahun($tahun) as $bk => $bagian) {
    $blok[] = ['teks' => $bk . '. ' . $bagian['judul'], 'tebal' => true, 'ukuran' => 22, 'jarak' => 40];
    $baris = [];
    $no = 0;
    foreach ($bagian['butir'] as $kodeButir) {
        $b = instrumen_butir()[$kodeButir];
        $no++;
        $baris[] = [
            (string)$no,
            (string)$kodeButir,
            (string)$b['indikator'],
            (string)$b['data'],
            isset($skor[$kodeButir]) ? (string)(int)$skor[$kodeButir] : '-',
        ];
    }
    $blok[] = ['tabel' => [
        ['No', 'No. UBT', 'Indikator Kinerja', 'Data Yang Diharapkan', 'Skor'],
        $baris,
        ['lebar' => $lebar, 'tengah' => [true, true, false, false, true]],
    ]];
    $blok[] = ['teks' => 'Jumlah skor ' . $bk . ' (maks ' . (int)$bagian['maks'] . ') — Nilai Kompetensi: '
        . rekap_nilai_teks($rinci['bagian'][$bk] ?? null), 'tebal' => true, 'ukuran' => 20, 'jarak' => 120];
}

// ---- ringkasan nilai ----
$ringkas = [];
foreach (bagian_tahun($tahun) as $bk => $bagian) {
    $ringkas[] = [(string)$bk, (string)$bagian['judul'], rekap_nilai_teks($rinci['bagian'][$bk] ?? null)];
}
$ringkas[] = [['teks' => 'Nilai Kinerja Tahunan', 'span' => 2, 'tebal' => true],
    ['teks' => rekap_nilai_teks($rinci['nilai']), 'tebal' => true]];
$ringkas[] = [['teks' => 'Predikat', 'span' => 2, 'tebal' => true],
    ['teks' => predikat_dari($rinci['nilai']), 'tebal' => true]];
$blok[] = ['teks' => 'Rekapitulasi Nilai', 'tebal' => true, 'jarak' => 60];
$blok[] = ['tabel' => [
    ['Tugas Utama', 'Uraian', 'Nilai'],
    $ringkas,
    ['lebar' => [1100, 6470, 1500], 'tengah' => [true, false, true]],
]];
$blok[] = ['teks' => 'Nilai tiap tugas utama = (jumlah skor butir ÷ (4 × jumlah butir)) × 100. '
    . 'Nilai kinerja tahunan = rata-rata tugas utama yang berlaku pada tahun ini. '
    . 'Butir terisi: ' . (int)$rinci['terisi'] . ' dari ' . (int)$rinci['jumlah'] . '.',
    'ukuran' => 19, 'jarak' => 80];

$blok[] = pn_docx_ttd($s, $namaPenilai,
    rekap_isi_teks($pen['nip'] ?? '') !== '' ? (string)$pen['nip'] : '', $label, $tempat, $tanggal);

$namaBerkas = 'Lembar-Penilaian-' . preg_replace('/[^A-Za-z0-9]+/', '-', (string)$s['nama'])
    . '-' . preg_replace('/[^A-Za-z0-9]+/', '-', pilihan_tahun($tahun)) . '-P' . (int)$posisi . '.docx';
docx_kirim($namaBerkas, docx_bangun($blok, $judul . ' ' . pilihan_tahun($tahun)));
