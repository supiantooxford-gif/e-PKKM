<?php
/**
 * e-PKKM - HALAMAN DEPAN (LANDING PAGE): modul bersama.
 *
 * Permintaan pemilik: "Halaman depan buat landing page seperti Aplikasi Supervisi" - jadi halaman
 * depan aplikasi menjadi landing page (beranda + slider, fitur aplikasi, keunggulan, testimoni,
 * tentang aplikasi, kontak) dengan PANEL MASUK yang tetap dipertahankan, dan seluruh isinya diisi
 * lewat akun Super Admin (menu "Halaman Depan (Landing)") — ⚠️ HALAMAN LANDING DIHAPUS v1.11.0:
 * berkas `landing.php` beserta menunya sudah tidak ada (halaman depan kini panel masuk dua kolom).
 * Berkas ini dipertahankan karena (a) `landing_ikon()` masih dipakai halaman publik Pendaftaran
 * Penilai dan (b) tabel/isi landing lama tidak dihapus agar data tidak hilang.
 *
 * Isi halaman disimpan di DUA tempat:
 *  - teks tunggal (label beranda, statistik, judul tiap bagian, kontak) -> tabel `app_pengaturan`
 *    dengan awalan kunci `landing_` (tingkat APLIKASI, dipakai bersama semua madrasah);
 *  - daftar berulang (slider, fitur, keunggulan, testimoni) dan pesan kontak -> tabel `landing_*`.
 *
 * Nilai BAWAAN ada di `landing_bawaan()`/`landing_daftar_bawaan()`; dipakai bila Super Admin belum
 * pernah mengubahnya. Mengosongkan sebuah isian berarti benar-benar kosong (bagian itu tidak
 * dicetak), bukan kembali ke bawaan.
 *
 * Modul ini boleh dimuat tanpa init.php (dipakai `inc/db.php` saat mengisi data awal), jadi JANGAN
 * memanggil fungsi basis data pada saat modul dimuat.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

const LANDING_JUDUL_MAKS = 160;
const LANDING_TEKS_MAKS = 700;
const LANDING_ISI_MAKS = 2500;
const LANDING_NAMA_MAKS = 90;
const LANDING_PESAN_MAKS = 1200;
const LANDING_MAKS_SESI = 5;   // maksimal pengiriman pesan kontak per sesi

/** Jenis daftar yang boleh dikelola di halaman depan. */
function landing_jenis_list(): array
{
    return ['slider', 'fitur', 'unggulan', 'testimoni'];
}

/** Hanya menerima jenis yang dikenal. */
function landing_jenis_sah(string $jenis): string
{
    $j = strtolower(trim($jenis));
    return in_array($j, landing_jenis_list(), true) ? $j : '';
}

/** Nama tabel daftar (WHITELIST - jangan menerima nama tabel dari pengguna). */
function landing_tabel(string $jenis): string
{
    return match (landing_jenis_sah($jenis)) {
        'slider' => 'landing_slider',
        'fitur' => 'landing_fitur',
        'unggulan' => 'landing_unggulan',
        'testimoni' => 'landing_testimoni',
        default => '',
    };
}

/** Label tampilan tiap jenis daftar. */
function landing_jenis_label(string $jenis): string
{
    return match (landing_jenis_sah($jenis)) {
        'slider' => 'Slider Beranda',
        'fitur' => 'Fitur Aplikasi',
        'unggulan' => 'Keunggulan',
        'testimoni' => 'Testimoni',
        default => '',
    };
}

/** Jenis daftar yang itemnya menyimpan FOTO. */
function landing_jenis_berfoto(string $jenis): bool
{
    return in_array(landing_jenis_sah($jenis), ['slider', 'testimoni'], true);
}

// ---------------------------------------------------------------------------------------------
// ISI BAWAAN
// ---------------------------------------------------------------------------------------------

/** Isi bawaan halaman depan (dipakai sekali saat pemasangan & sebagai nilai awal isian). */
function landing_bawaan(): array
{
    return [
        // --- Beranda (label kecil; beranda memakai slider + empat kotak statistik)
        'label' => 'Sistem Informasi Penilaian Kinerja Kepala Madrasah',

        // --- Statistik (pasangan angka + label; dikosongkan bila tidak ingin ditampilkan)
        'stat_1_angka' => '109',
        'stat_1_label' => 'Butir Penilaian Instrumen PKKM',
        'stat_2_angka' => '4',
        'stat_2_label' => 'Tahun Penilaian (Tahun 1-4)',
        'stat_3_angka' => '2',
        'stat_3_label' => 'Pengawas Penilai (Pengawas I & II)',
        'stat_4_angka' => '100%',
        'stat_4_label' => 'Berbasis Web, Tanpa Instalasi',

        // --- Judul tiap bagian
        'fitur_judul' => 'Fitur Aplikasi',
        'fitur_sub' => 'Seluruh kebutuhan penilaian kinerja kepala madrasah dalam satu aplikasi: '
            . 'dari instrumen butir penilaian sampai laporan dan rekap siap cetak.',
        'unggulan_judul' => 'Keunggulan',
        'unggulan_sub' => 'Alasan madrasah memakai aplikasi ini untuk penilaian kinerja kepalanya.',
        'testimoni_judul' => 'Testimoni',
        'testimoni_judul_besar' => 'Apa Kata Pengguna Kami?',
        'testimoni_sub' => 'Pengalaman pengawas dan kepala madrasah yang memakai aplikasi ini.',
        'tentang_judul' => 'Tentang Aplikasi',
        'tentang_sub' => 'Sekilas aplikasi ini beserta cara kerjanya.',
        'tentang_isi' => "e-PKKM adalah aplikasi penilaian kinerja kepala madrasah berbasis web. "
            . "Instrumennya mengikuti format PKKM: 109 butir penilaian pada lima tugas utama — Usaha "
            . "Pengembangan Madrasah, Pelaksanaan Tugas Manajerial, Pengembangan Kewirausahaan, "
            . "Supervisi kepada Guru dan Tenaga Kependidikan, serta Hasil Kinerja Kepala Madrasah "
            . "(khusus Tahun Keempat).\n\n"
            . "Setiap butir diberi skor 1-4, lalu aplikasi menghitung nilai tiap tugas utama, nilai "
            . "kinerja tahunan, nilai gabungan Pengawas I dan Pengawas II, nilai Tahun Keempat yang "
            . "kumulatif (Kepala Bidang, Kepala Seksi, Pengawas, Guru, Tenaga Kependidikan, dan Komite "
            . "Madrasah), sampai Nilai Kinerja Kepala Madrasah empat tahunan beserta predikatnya.\n\n"
            . "Aplikasi ini multi-madrasah: satu pemasangan dapat dipakai banyak madrasah sekaligus "
            . "dengan data tiap madrasah yang terpisah, akun kepala madrasah, akun Pengawas I, dan "
            . "akun Pengawas II.",
        'kontak_judul' => 'Kontak',
        'kontak_sub' => 'Ingin bertanya, mencoba, atau mengaktifkan aplikasi? Hubungi kami.',

        // --- Kontak
        'kontak_alamat' => '',
        'kontak_telepon' => '',
        'kontak_email' => '',
        'kontak_jam' => 'Senin - Sabtu, 07.00 - 21.00 WIB',
        'kontak_ajakan' => 'Silakan kirim pesan lewat formulir di samping, atau hubungi langsung '
            . 'lewat WhatsApp/telepon. Pesan yang masuk akan dibaca Admin aplikasi.',
    ];
}

/** Isi bawaan daftar slider/fitur/unggulan/testimoni (dipakai SEKALI saat pemasangan). */
function landing_daftar_bawaan(): array
{
    return [
        // Slider beranda: judul + keterangan + foto (foto dilengkapi Super Admin; tanpa foto tetap
        // tampil dengan latar gradasi berikon, bukan kotak rusak).
        'slider' => [
            ['Penilaian Kinerja Kepala Madrasah yang Terukur',
                'Instrumen PKKM lengkap 109 butir penilaian pada lima tugas utama. Setiap butir diberi skor 1-4, nilai dan predikat dihitung otomatis.'],
            ['Empat Tahun Penilaian dalam Satu Aplikasi',
                'Penilaian Tahun Pertama sampai Tahun Keempat oleh Pengawas I (ketua tim) dan Pengawas II (anggota tim), masing-masing mengisi penilaiannya sendiri.'],
            ['Laporan dan Rekap Siap Cetak',
                'Rekapan hasil tiap pengawas, nilai gabungan, nilai kumulatif Tahun Keempat, dan Nilai Kinerja Kepala Madrasah empat tahunan - lengkap dengan lembar cetak ber-kop madrasah.'],
        ],
        'fitur' => [
            ['doc', 'Instrumen PKKM Lengkap', 'Butir penilaian sesuai format PKKM: 109 butir pada lima tugas utama (A-E) beserta kolom data yang diharapkan dan kolom skor 1-4.'],
            ['chart', 'Nilai & Predikat Otomatis', 'Nilai tiap tugas utama, nilai tahunan, dan predikat (Amat Baik/Baik/Cukup/Kurang) dihitung otomatis mengikuti rumus instrumen.'],
            ['users', 'Dua Pengawas Penilai', 'Pengawas I dan Pengawas II mengisi penilaiannya sendiri pada tahun yang sama; hasilnya digabungkan otomatis pada rekap.'],
            ['calendar', 'Tahun Pertama - Keempat', 'Setiap tahun penilaian disimpan tersendiri beserta tahun dan tanggal pelaksanaannya, siap dipakai lagi pada penilaian berikutnya.'],
            ['user', 'Guru, Tendik & Komite', 'Penilaian Tahun Keempat dari Guru, Tenaga Kependidikan, dan Komite Madrasah, serta Kepala Bidang dan Kepala Seksi.'],
            ['medal', 'NKKM Empat Tahunan', 'Nilai Kinerja Kepala Madrasah empat tahunan dihitung dari hasil Tahun Pertama sampai Keempat beserta predikatnya.'],
            ['report', 'Laporan & Rekap', 'Rekapan hasil PKKM Pengawas I dan Pengawas II, rekap nilai gabungan, dan rekap nilai komponen Tahun Keempat.'],
            ['print', 'Dokumen Siap Cetak', 'Lembar penilaian per butir, lembar laporan tiap tahun, dan lembar rekap - memakai kop madrasah serta tanda tangan penilai dan kepala madrasah.'],
        ],
        'unggulan' => [
            ['clock', 'Mudah Digunakan', 'Tampilan sederhana dengan alur yang jelas, sehingga pengawas maupun kepala madrasah langsung paham tanpa pelatihan panjang.'],
            ['shield', 'Data Tiap Madrasah Terpisah', 'Akun hanya melihat madrasah yang menjadi tugasnya, dan kata sandi tersimpan teracak.'],
            ['school', 'Multi-Madrasah', 'Satu pemasangan melayani banyak madrasah; Super Admin mengelola madrasah beserta akun dan penugasan pengawasnya.'],
            ['check', 'Tidak Ada Hitungan Manual', 'Seluruh perhitungan nilai dan predikat dilakukan aplikasi, termasuk penggabungan nilai dua pengawas.'],
            ['globe', 'Bisa Dibuka di Mana Saja', 'Cukup memakai peramban: laptop, komputer, maupun telepon pintar.'],
            ['support', 'Dukungan Penuh', 'Admin aplikasi siap membantu saat dibutuhkan, termasuk saat pengisian instrumen.'],
        ],
        'testimoni' => [
            ['Pengawas Madrasah (contoh)', 'Pengguna Aplikasi', 'Instrumennya sudah tersedia lengkap dengan skor dan nilai otomatis, jadi penilaian lebih cepat dan hasilnya bisa langsung dicetak.'],
            ['Kepala Madrasah (contoh)', 'Pengguna Aplikasi', 'Saya bisa melihat hasil penilaian tiap tahun dan tahu aspek mana yang perlu diperbaiki.'],
            ['Pengawas II (contoh)', 'Pengguna Aplikasi', 'Masing-masing pengawas mengisi penilaiannya sendiri, lalu rekapnya langsung tergabung tanpa perlu menghitung manual.'],
        ],
    ];
}

// ---------------------------------------------------------------------------------------------
// TEKS TUNGGAL (app_pengaturan)
// ---------------------------------------------------------------------------------------------

/** Isi satu kunci halaman depan (kunci tanpa awalan "landing_"). */
function landing_teks(string $kunci, ?string $def = null): string
{
    $bawaan = landing_bawaan();
    $nilaiBawaan = $bawaan[$kunci] ?? ($def ?? '');
    return app_setting('landing_' . $kunci, (string)$nilaiBawaan);
}

/** Simpan banyak kunci sekaligus: ['fitur_judul' => '...', ...]. */
function landing_simpan_teks(array $nilai): void
{
    foreach ($nilai as $k => $v) {
        app_setting_set('landing_' . (string)$k, (string)$v);
    }
}

/** Empat pasang angka + label statistik beranda (baris kosong tidak ditampilkan). */
function landing_stat_list(): array
{
    $out = [];
    for ($i = 1; $i <= 4; $i++) {
        $angka = trim(landing_teks('stat_' . $i . '_angka'));
        $label = trim(landing_teks('stat_' . $i . '_label'));
        if ($angka === '' && $label === '') {
            continue;
        }
        $out[] = ['angka' => $angka, 'label' => $label];
    }
    return $out;
}

// ---------------------------------------------------------------------------------------------
// DAFTAR (slider / fitur / keunggulan / testimoni)
// ---------------------------------------------------------------------------------------------

function landing_list(string $jenis, bool $aktifSaja = true): array
{
    $tabel = landing_tabel($jenis);
    if ($tabel === '') {
        return [];
    }
    $sql = 'SELECT * FROM ' . $tabel;
    if ($aktifSaja) {
        $sql .= ' WHERE aktif = 1';
    }
    $sql .= ' ORDER BY urutan, id';
    try {
        return all($sql);
    } catch (Throwable $e) {
        return [];
    }
}

function landing_item_by_id(string $jenis, int $id): ?array
{
    $tabel = landing_tabel($jenis);
    if ($tabel === '' || $id <= 0) {
        return null;
    }
    return one('SELECT * FROM ' . $tabel . ' WHERE id = ?', [$id]);
}

function landing_urutan_baru(string $jenis): int
{
    $tabel = landing_tabel($jenis);
    if ($tabel === '') {
        return 10;
    }
    $maks = val('SELECT COALESCE(MAX(urutan), 0) FROM ' . $tabel);
    return (int)$maks + 10;
}

function landing_item_tambah(string $jenis, array $d): void
{
    $tabel = landing_tabel($jenis);
    if ($tabel === '') {
        return;
    }
    $kini = date('Y-m-d H:i:s');
    if ($jenis === 'testimoni') {
        q('INSERT INTO ' . $tabel . ' (nama, jabatan, isi, foto, urutan, aktif, dibuat) VALUES (?,?,?,?,?,1,?)',
            [$d['nama'] ?? '', $d['jabatan'] ?? '', $d['isi'] ?? '', $d['foto'] ?? null,
                landing_urutan_baru($jenis), $kini]);
        return;
    }
    if ($jenis === 'slider') {
        q('INSERT INTO ' . $tabel . ' (judul, keterangan, foto, urutan, aktif, dibuat) VALUES (?,?,?,?,1,?)',
            [$d['judul'] ?? '', $d['keterangan'] ?? '', $d['foto'] ?? null, landing_urutan_baru($jenis), $kini]);
        return;
    }
    q('INSERT INTO ' . $tabel . ' (ikon, judul, teks, urutan, aktif, dibuat) VALUES (?,?,?,?,1,?)',
        [$d['ikon'] ?? 'check', $d['judul'] ?? '', $d['teks'] ?? '', landing_urutan_baru($jenis), $kini]);
}

function landing_item_simpan(string $jenis, int $id, array $d): void
{
    $tabel = landing_tabel($jenis);
    if ($tabel === '' || $id <= 0) {
        return;
    }
    if ($jenis === 'testimoni') {
        q('UPDATE ' . $tabel . ' SET nama = ?, jabatan = ?, isi = ?, aktif = ? WHERE id = ?',
            [$d['nama'] ?? '', $d['jabatan'] ?? '', $d['isi'] ?? '', (int)($d['aktif'] ?? 1), $id]);
        return;
    }
    if ($jenis === 'slider') {
        q('UPDATE ' . $tabel . ' SET judul = ?, keterangan = ?, aktif = ? WHERE id = ?',
            [$d['judul'] ?? '', $d['keterangan'] ?? '', (int)($d['aktif'] ?? 1), $id]);
        return;
    }
    q('UPDATE ' . $tabel . ' SET ikon = ?, judul = ?, teks = ?, aktif = ? WHERE id = ?',
        [$d['ikon'] ?? 'check', $d['judul'] ?? '', $d['teks'] ?? '', (int)($d['aktif'] ?? 1), $id]);
}

/** Simpan hanya kolom foto (dipakai setelah unggahan berhasil) + kosongkan bila hapus dicentang. */
function landing_foto_simpan(string $jenis, int $id, ?string $url): void
{
    $tabel = landing_tabel($jenis);
    if ($tabel === '' || $id <= 0 || !landing_jenis_berfoto($jenis)) {
        return;
    }
    q('UPDATE ' . $tabel . ' SET foto = ? WHERE id = ?', [$url, $id]);
}

function landing_item_hapus(string $jenis, int $id): void
{
    $tabel = landing_tabel($jenis);
    if ($tabel === '' || $id <= 0) {
        return;
    }
    q('DELETE FROM ' . $tabel . ' WHERE id = ?', [$id]);
}

/** Tukar urutan dengan tetangga terdekat (naik = -1, turun = +1). */
function landing_item_geser(string $jenis, int $id, int $arah): void
{
    $tabel = landing_tabel($jenis);
    if ($tabel === '' || $id <= 0) {
        return;
    }
    $ini = one('SELECT id, urutan FROM ' . $tabel . ' WHERE id = ?', [$id]);
    if (!$ini) {
        return;
    }
    $op = $arah < 0 ? '<' : '>';
    $urut = $arah < 0 ? 'DESC' : 'ASC';
    $tetangga = one('SELECT id, urutan FROM ' . $tabel . ' WHERE (urutan ' . $op . ' ? OR (urutan = ? AND id ' . $op . ' ?))
                     ORDER BY urutan ' . $urut . ', id ' . $urut . ' LIMIT 1',
        [(int)$ini['urutan'], (int)$ini['urutan'], $id]);
    if (!$tetangga) {
        return;
    }
    q('UPDATE ' . $tabel . ' SET urutan = ? WHERE id = ?', [(int)$tetangga['urutan'], $id]);
    q('UPDATE ' . $tabel . ' SET urutan = ? WHERE id = ?', [(int)$ini['urutan'], (int)$tetangga['id']]);
}

// ---------------------------------------------------------------------------------------------
// PESAN KONTAK
// ---------------------------------------------------------------------------------------------

function landing_pesan_list(int $maks = 100): array
{
    $maks = max(1, min(500, $maks));
    try {
        return all('SELECT * FROM landing_pesan ORDER BY id DESC LIMIT ' . $maks);
    } catch (Throwable $e) {
        return [];
    }
}

function landing_pesan_baru(): int
{
    try {
        return (int)val('SELECT COUNT(*) FROM landing_pesan WHERE dibaca = 0');
    } catch (Throwable $e) {
        return 0;
    }
}

function landing_pesan_jumlah(): int
{
    try {
        return (int)val('SELECT COUNT(*) FROM landing_pesan');
    } catch (Throwable $e) {
        return 0;
    }
}

function landing_pesan_hapus(int $id): void
{
    if ($id > 0) {
        q('DELETE FROM landing_pesan WHERE id = ?', [$id]);
    }
}

function landing_pesan_tandai(int $id, bool $dibaca = true): void
{
    if ($id > 0) {
        q('UPDATE landing_pesan SET dibaca = ? WHERE id = ?', [$dibaca ? 1 : 0, $id]);
    }
}

/** Ringkasan jumlah isi tiap daftar (dipakai halaman Super Admin). */
function landing_ringkas(): array
{
    $out = [];
    foreach (landing_jenis_list() as $j) {
        $tabel = landing_tabel($j);
        try {
            $total = (int)val('SELECT COUNT(*) FROM ' . $tabel);
            $aktif = (int)val('SELECT COUNT(*) FROM ' . $tabel . ' WHERE aktif = 1');
        } catch (Throwable $e) {
            $total = 0;
            $aktif = 0;
        }
        $out[$j] = ['total' => $total, 'aktif' => $aktif];
    }
    return $out;
}

// ---------------------------------------------------------------------------------------------
// KONTAK & IKON
// ---------------------------------------------------------------------------------------------

/** Tautan WhatsApp dari nomor pada isian kontak (08... -> 628...). */
function landing_wa_tautan(string $pesan = ''): string
{
    $nomor = preg_replace('/\D+/', '', landing_teks('kontak_telepon')) ?? '';
    if ($nomor === '') {
        return '';
    }
    $tautan = 'https://wa.me/' . wa_internasional($nomor);
    if ($pesan !== '') {
        $tautan .= '?text=' . rawurlencode($pesan);
    }
    return $tautan;
}

/** Daftar ikon yang boleh dipilih untuk fitur & keunggulan. */
function landing_ikon_list(): array
{
    return [
        'check' => 'Centang / Penilaian',
        'chart' => 'Grafik / Nilai Otomatis',
        'report' => 'Laporan & Rekap',
        'calendar' => 'Jadwal / Tahun Penilaian',
        'doc' => 'Dokumen / Instrumen',
        'print' => 'Cetak / Printer',
        'upload' => 'Impor / Unggah',
        'school' => 'Madrasah / Lembaga',
        'globe' => 'Dunia / Internet',
        'star' => 'Bintang / Kualitas',
        'medal' => 'Medali / Predikat',
        'clock' => 'Jam / Waktu',
        'shield' => 'Perisai / Keamanan',
        'eye' => 'Mata / Pengawasan',
        'users' => 'Beberapa Orang',
        'user' => 'Satu Orang',
        'key' => 'Kunci / Akun',
        'support' => 'Dukungan / Bantuan',
        'chat' => 'Percakapan',
        'heart' => 'Hati / Kepuasan',
    ];
}

function landing_ikon_sah(string $nama): string
{
    return array_key_exists($nama, landing_ikon_list()) ? $nama : 'check';
}

/** Ikon halaman depan (tanpa pustaka luar). */
function landing_ikon(string $nama, int $size = 24): string
{
    $p = [
        'check' => '<path d="M9.6 16.2 5.4 12l-1.4 1.4 5.6 5.6 12-12L20.2 5.6z"/>',
        'chart' => '<path d="M4 20h16v-2H4zM6 16h3V8H6zm4.5 0h3V4h-3zM15 16h3v-6h-3z"/>',
        'report' => '<path d="M5 3h9l5 5v13H5zm2 2v14h10V9h-4V5zm2 6h6v1.6H9zm0 3.4h6V16H9z"/>',
        'calendar' => '<path d="M5 3h2v2h10V3h2v2h2v16H3V5h2zm0 6v10h14V9zM7 11h3v3H7z"/>',
        'doc' => '<path d="M14 3v5h5"/><path d="M19 8.5V19a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h7z"/>',
        'print' => '<path d="M7 3h10v5H7zm-3 7h16v7h-3v4H7v-4H4zm5 4v4h6v-4z"/>',
        'upload' => '<path d="M11 3h2v8h3l-4 4-4-4h3zM5 18h14v3H5z"/>',
        'school' => '<path d="M12 3 2 8v13h20V8zm0 2.2 7 3.5V19H5V8.7zM9 19v-5h6v5z"/>',
        'globe' => '<path d="M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18m0 2c1.7 0 3 2.9 3 7s-1.3 7-3 7-3-2.9-3-7 1.3-7 3-7"/>',
        'star' => '<path d="M12 3l2.7 5.6 6.1.8-4.5 4.2 1.2 6L12 16.7 6.5 19.6l1.2-6L3.2 9.4l6.1-.8z"/>',
        'medal' => '<path d="M9 2h6l-2 6h-2zM12 9a6 6 0 1 0 0 12 6 6 0 0 0 0-12m0 3 1 2 2 .3-1.5 1.4.4 2-1.9-1-1.9 1 .4-2L9 13.3l2-.3z"/>',
        'clock' => '<path d="M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18m0 2a7 7 0 1 1 0 14 7 7 0 0 1 0-14m-1 2v6l4 2 .8-1.6L13 11.5V7z"/>',
        'shield' => '<path d="M12 2 4 6v6c0 5 3.4 9.2 8 10 4.6-.8 8-5 8-10V6z"/>',
        'eye' => '<path d="M12 5c6 0 10 7 10 7s-4 7-10 7S2 12 2 12s4-7 10-7m0 3.5A3.5 3.5 0 1 0 12 15.5a3.5 3.5 0 0 0 0-7"/>',
        'users' => '<path d="M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8m0 2c-4 0-7 2.2-7 5v1h14v-1c0-2.8-3-5-7-5m8.5-1.5a3 3 0 1 0 0-6 3 3 0 0 0 0 6m.5 2c2.4 0 4 1.2 4 3v1h-4v-1c0-1.2-.4-2.2-1.2-3z"/>',
        'user' => '<path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8m0 2c-4 0-8 2-8 5v1h16v-1c0-3-4-5-8-5"/>',
        'key' => '<path d="M14 3a5 5 0 0 0-4.6 7L3 16.4V21h4.6l1.4-1.4V17h2v-2h2l1.2-1.2A5 5 0 1 0 14 3m1.6 3.4a1.6 1.6 0 1 1 0 3.2 1.6 1.6 0 0 1 0-3.2"/>',
        'support' => '<path d="M12 2a7 7 0 0 0-7 7v4h2V9a5 5 0 0 1 10 0v4h2V9a7 7 0 0 0-7-7M6 15h3v6H6zm9 0h3v6h-3z"/>',
        'chat' => '<path d="M4 4h16v12H8l-4 4z"/>',
        'heart' => '<path d="M12 21S3 14.6 3 8.9A4.9 4.9 0 0 1 12 6a4.9 4.9 0 0 1 9 2.9C21 14.6 12 21 12 21"/>',
        'telepon' => '<path d="M7 3h3l2 5-2 1a11 11 0 0 0 5 5l1-2 5 2v3a2 2 0 0 1-2 2A16 16 0 0 1 5 5a2 2 0 0 1 2-2"/>',
        'email' => '<path d="M3 5h18v14H3zm2.7 2L12 11.4 18.3 7z"/>',
        'lokasi' => '<path d="M12 2a7 7 0 0 0-7 7c0 5 7 13 7 13s7-8 7-13a7 7 0 0 0-7-7m0 4.5a2.5 2.5 0 1 1 0 5 2.5 2.5 0 0 1 0-5"/>',
    ];
    $d = $p[$nama] ?? $p['check'];
    return '<svg class="lp-ico" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24"'
        . ' fill="currentColor" aria-hidden="true">' . $d . '</svg>';
}

// ---------------------------------------------------------------------------------------------
// ALAT BANTU
// ---------------------------------------------------------------------------------------------

/** Potong teks ke sejumlah karakter. */
function landing_potong(?string $teks, int $maks): string
{
    $teks = trim((string)$teks);
    if ($maks <= 0) {
        return '';
    }
    return potong_teks($teks, $maks);
}

/** Foto satu slide slider (string kosong bila belum diunggah). */
function landing_slider_gambar(array $slide): string
{
    return trim((string)($slide['foto'] ?? ''));
}

/**
 * Unggah satu berkas foto dari formulir ($_FILES[$field]) ke media proxy platform.
 * Hanya URL hasil respons yang dikembalikan (URL itulah yang disimpan ke basis data).
 *
 * @return array{ok:bool, url:string, pesan:string}
 */
function landing_unggah_foto_berkas(string $field): array
{
    $f = $_FILES[$field] ?? null;
    if (!is_array($f) || trim((string)($f['name'] ?? '')) === '') {
        return ['ok' => false, 'url' => '', 'pesan' => 'Tidak ada berkas foto yang dipilih.'];
    }
    $kode = (int)($f['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($kode !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'url' => '', 'pesan' => ($kode === UPLOAD_ERR_INI_SIZE || $kode === UPLOAD_ERR_FORM_SIZE)
            ? 'Berkasnya melebihi batas server (' . ini_get('upload_max_filesize') . '). Perkecil dulu fotonya.'
            : 'Gagal diunggah (kode ' . $kode . '). Silakan coba lagi.'];
    }
    $namaAsli = (string)$f['name'];
    $ukuran = (int)($f['size'] ?? 0);
    $tmp = (string)($f['tmp_name'] ?? '');
    $cek = validasi_foto($namaAsli, $ukuran, $tmp);
    if (empty($cek['ok'])) {
        return ['ok' => false, 'url' => '', 'pesan' => (string)$cek['pesan']];
    }
    $bytes = @file_get_contents($tmp);
    if ($bytes === false) {
        return ['ok' => false, 'url' => '', 'pesan' => 'Gagal membaca berkas foto di server.'];
    }
    $hasil = media_upload($bytes, $namaAsli);
    if (empty($hasil['ok'])) {
        return ['ok' => false, 'url' => '', 'pesan' => (string)($hasil['pesan'] ?? 'Unggahan foto ditolak.')];
    }
    return ['ok' => true, 'url' => (string)$hasil['url'], 'pesan' => ''];
}

// ---------------------------------------------------------------------------------------------
// ISI AWAL (dijalankan SEKALI oleh migrasi)
// ---------------------------------------------------------------------------------------------

/** Isi halaman depan dengan isi bawaan SEKALI SAJA (penanda `landing_seed`). */
function landing_seed(PDO $pdo): void
{
    $sudah = (int)$pdo->query("SELECT COUNT(*) FROM app_pengaturan WHERE kunci = 'landing_seed'")->fetchColumn();
    if ($sudah > 0) {
        return;
    }
    $st = $pdo->prepare('INSERT INTO app_pengaturan (kunci, nilai) VALUES (?, ?)
                         ON CONFLICT(kunci) DO UPDATE SET nilai = excluded.nilai');
    foreach (landing_bawaan() as $k => $v) {
        $st->execute(['landing_' . $k, (string)$v]);
    }
    $kini = date('Y-m-d H:i:s');
    $isi = landing_daftar_bawaan();
    $urut = 10;
    foreach ($isi['fitur'] as $f) {
        $pdo->prepare('INSERT INTO landing_fitur (ikon, judul, teks, urutan, aktif, dibuat) VALUES (?,?,?,?,1,?)')
            ->execute([$f[0], $f[1], $f[2], $urut, $kini]);
        $urut += 10;
    }
    $urut = 10;
    foreach ($isi['unggulan'] as $f) {
        $pdo->prepare('INSERT INTO landing_unggulan (ikon, judul, teks, urutan, aktif, dibuat) VALUES (?,?,?,?,1,?)')
            ->execute([$f[0], $f[1], $f[2], $urut, $kini]);
        $urut += 10;
    }
    $urut = 10;
    foreach ($isi['testimoni'] as $f) {
        $pdo->prepare('INSERT INTO landing_testimoni (nama, jabatan, isi, urutan, aktif, dibuat) VALUES (?,?,?,?,1,?)')
            ->execute([$f[0], $f[1], $f[2], $urut, $kini]);
        $urut += 10;
    }
    $st->execute(['landing_seed', $kini]);
}

/**
 * Isi SLIDER BERANDA dengan contoh bawaan SEKALI SAJA (penanda `landing_slider_seed`), dan HANYA
 * bila tabelnya masih kosong - isi yang sudah dibuat Super Admin tidak boleh ditambahi contoh.
 */
function landing_slider_seed(PDO $pdo): void
{
    $sudah = (int)$pdo->query("SELECT COUNT(*) FROM app_pengaturan WHERE kunci = 'landing_slider_seed'")->fetchColumn();
    if ($sudah > 0) {
        return;
    }
    $simpanPenanda = static function (PDO $pdo, string $kapan): void {
        $pdo->prepare('INSERT INTO app_pengaturan (kunci, nilai) VALUES (?, ?)
                       ON CONFLICT(kunci) DO UPDATE SET nilai = excluded.nilai')
            ->execute(['landing_slider_seed', $kapan]);
    };
    $jml = (int)$pdo->query('SELECT COUNT(*) FROM landing_slider')->fetchColumn();
    if ($jml > 0) {
        $simpanPenanda($pdo, date('Y-m-d H:i:s'));
        return;
    }
    $kini = date('Y-m-d H:i:s');
    $urut = 10;
    foreach (landing_daftar_bawaan()['slider'] as $s) {
        $pdo->prepare('INSERT INTO landing_slider (judul, keterangan, foto, urutan, aktif, dibuat) VALUES (?,?,NULL,?,1,?)')
            ->execute([$s[0], $s[1], $urut, $kini]);
        $urut += 10;
    }
    $simpanPenanda($pdo, $kini);
}
