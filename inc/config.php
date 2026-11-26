<?php
/**
 * e-PKKM - Aplikasi Penilaian Kinerja Kepala Madrasah
 * Konfigurasi dasar aplikasi.
 *
 * Aturan penting aplikasi ini (sama pola dengan e-GURU / e-BOS):
 *  - Tautan ke berkas sendiri SELALU relatif (aplikasi dilayani pada subfolder).
 *  - Berkas data ada di dalam folder aplikasi (data/pkkm.sqlite, data/sessions).
 *  - Jangan menaruh <style> di halaman; seluruh gaya ada di assets/style.css.
 */

define('APP_NAME', 'e-PKKM');
define('APP_SUB', 'Penilaian Kinerja Kepala Madrasah');
define('APP_VERSION', '1.20.0');
define('APP_AUTHOR', 'Supianto');
define('APP_AUTHOR_JABATAN', 'Kepala MA. Sirojuth Tholibiin Mootilango');

define('DB_FILE', __DIR__ . '/../data/pkkm.sqlite');
define('DATA_DIR', __DIR__ . '/../data');
define('SESI_DIR', DATA_DIR . '/sessions');

// Tautan relatif: aplikasi dilayani pada /e-pkkm/ (subfolder), bukan pada akar domain.
define('BASE', '');

// ---- Tahun Penilaian PKKM ----
define('PKKM_TAHUN_LIST', [1 => 'Tahun Pertama', 2 => 'Tahun Kedua', 3 => 'Tahun Ketiga', 4 => 'Tahun Keempat']);

// ---- Predikat Penilaian Kinerja (PermenPANRB 6/2022) ----
define('PKKM_PREDIKAT', [
    'Amat Baik' => 91,
    'Baik' => 81,
    'Cukup' => 71,
    'Kurang' => 0,
]);

// ---- Kategori penilai ----
// pengawas : kode_penilai '1' (Pengawas I / Ketua Tim) dan '2' (Pengawas II / Anggota Tim)
// gtk      : penilai Tahun Keempat (Guru, Tendik, Komite, Kabid, Kasi)
define('PKKM_PENILAI_PENGAWAS', [
    '1' => 'Pengawas I (Ketua Tim)',
    '2' => 'Pengawas II (Anggota Tim)',
]);

// ---- Posisi penilai pada halaman PENILAI (pendaftaran sendiri) ----
// Pemilik meminta istilah "Penilai 1" / "Penilai 2" pada halaman penilai; dokumen cetak tetap
// memakai istilah resmi "Pengawas I / II" (PKKM_PENILAI_PENGAWAS) supaya lembar tidak berubah.
define('PKKM_PENILAI_POSISI', [
    '1' => 'Penilai 1 (Ketua Tim)',
    '2' => 'Penilai 2 (Anggota Tim)',
]);

// ---- PENILAI TAHUN KEEMPAT (7 penilai) — permintaan pemilik ----
// "Untuk penilaian tahun ke-4 ... ada 7 [penilai]": Pengawas 1, Pengawas 2, dan lima jabatan lain
// yang berbeda menurut STATUS LEMBAGA:
//   - Negeri : Kabid, Kasie, Pengawas 1, Pengawas 2, Guru, Tendik, Komite
//   - Swasta : Yayasan, Kasie, Pengawas 1, Pengawas 2, Guru, Tendik, Komite
// Penugasan disimpan di `pengawas_sekolah`; posisi 1 & 2 tetap Pengawas I & II, sedangkan lima
// jabatan lain memakai posisi mulai PKKM_POSISI_TAMBAHAN (3, 4, 5, …) dan penilaiannya tersimpan pada
// kategori `tambahan` dengan kode = nomor posisi itu. Slot ditentukan oleh JABATAN akun penilai
// (lihat penilai_slot_dari_jabatan() di inc/penilai.php), bukan oleh nomor posisinya.
define('PKKM_POSISI_TAMBAHAN', 3);
define('PKKM_KATEGORI_TAMBAHAN', 'tambahan');
define('PKKM_PENILAI_TAMBAHAN_LABEL', 'Penilai Tahun Keempat (7 Penilai)');

// ---- STATUS LEMBAGA (Negeri / Swasta) ----
// Diisi saat pendaftaran madrasah (sebelah nama madrasah) dan dapat diubah pada Input Data Madrasah.
// Status ini menentukan susunan 5 penilai Tahun Keempat selain Pengawas 1 & 2.
define('PKKM_STATUS_LEMBAGA', ['negeri' => 'Negeri', 'swasta' => 'Swasta']);
define('PKKM_STATUS_LEMBAGA_BAWAAN', 'swasta');

// Slot penilai Tahun Keempat selain Pengawas 1 & 2, per status lembaga (urutan = urutan tampil).
// 'jabatan' = awalan nama jabatan yang MENEMPATI slot itu (huruf kecil), dipakai mencocokkan
// jabatan akun penilai dari daftar `jabatan_penilai`.
define('PKKM_SLOT_PENILAI', [
    'negeri' => [
        ['kunci' => 'kabid', 'label' => 'Kepala Bidang', 'jabatan' => ['kabid', 'kabag', 'kepala bidang', 'kepala bag']],
        ['kunci' => 'kasie', 'label' => 'Kepala Seksi', 'jabatan' => ['kasie', 'kasi', 'kepala seksi']],
        ['kunci' => 'guru', 'label' => 'Guru', 'jabatan' => ['guru']],
        ['kunci' => 'tendik', 'label' => 'Tenaga Kependidikan', 'jabatan' => ['tendik', 'tenaga kependidikan']],
        ['kunci' => 'komite', 'label' => 'Komite Madrasah', 'jabatan' => ['komite']],
    ],
    'swasta' => [
        ['kunci' => 'yayasan', 'label' => 'Yayasan', 'jabatan' => ['yayasan', 'ketua yayasan', 'pengurus yayasan']],
        ['kunci' => 'kasie', 'label' => 'Kepala Seksi', 'jabatan' => ['kasie', 'kasi', 'kepala seksi']],
        ['kunci' => 'guru', 'label' => 'Guru', 'jabatan' => ['guru']],
        ['kunci' => 'tendik', 'label' => 'Tenaga Kependidikan', 'jabatan' => ['tendik', 'tenaga kependidikan']],
        ['kunci' => 'komite', 'label' => 'Komite Madrasah', 'jabatan' => ['komite']],
    ],
]);

// ---- Daftar JABATAN penilai (diisi/diubah Super Admin pada menu "Daftar Jabatan Penilai") ----
// Nilai awal dipakai SEKALI saat pemasangan; sesudah itu daftar tersimpan di tabel `jabatan_penilai`
// sehingga perubahan Super Admin tidak pernah ditimpa migrasi berikutnya.
// 'Kabid' & 'Yayasan' & 'Tendik' diperlukan agar 7 penilai Tahun Keempat dapat dipilih.
define('PKKM_JABATAN_BAWAAN', ['Kabag', 'Kabid', 'Kasie', 'Pengawas', 'Guru', 'Tendik', 'Komite', 'Yayasan']);

// ---- Data penilai GTK LAMA (kategori `gtk`) ----
// Menu "Penilaian Guru & Tendik" sudah DIHAPUS (permintaan pemilik) karena peran itu kini menjadi
// akun penilai Tahun Keempat. Konstanta ini hanya dipakai membaca/mencetak data GTK lama
// sebagai cadangan bila slot penilai Tahun Keempat belum terisi.

define('PKKM_PENILAI_GTK', [
    'guru1' => 'Guru I',
    'guru2' => 'Guru II',
    'tendik1' => 'Tenaga Kependidikan I',
    'tendik2' => 'Tenaga Kependidikan II',
    'komite1' => 'Komite Madrasah I',
    'komite2' => 'Komite Madrasah II',
    'kabid' => 'Kepala Bidang',
    'kasi' => 'Kepala Seksi',
]);

// ---- Batas isian ----
define('PKKM_TEKS_PENDEK', 120);
define('PKKM_TEKS_PANJANG', 255);

// ---- Dokumen bukti butir penilaian (diunggah kepala madrasah) ----
// Ekstensi mengikuti yang diterima layanan penyimpanan platform: gambar, dokumen, dan arsip.
// (.doc / .xls lama sengaja TIDAK dipakai - layanan penyimpanan menolaknya.)
define('DOK_EKSTENSI_GAMBAR', ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg']);
define('DOK_EKSTENSI_DOKUMEN', ['pdf', 'docx', 'xlsx', 'pptx', 'csv', 'txt', 'zip']);
// v1.19.0 - batas ukuran DIPERKECIL menjadi 2 MB per berkas (permintaan pemilik:
// "Pada unggah dokumen kepala madrasah batasi maksimal hanya 2MB").
define('DOK_MAKS_BYTE', 2 * 1024 * 1024);      // 2 MB - batas SATU berkas (gambar maupun dokumen)
define('DOK_MAKS_GAMBAR', DOK_MAKS_BYTE);
define('DOK_MAKS_DOKUMEN', DOK_MAKS_BYTE);
define('DOK_MAKS_PER_BUTIR', 5);               // maksimal 5 dokumen per butir per tahun (berkas + tautan)
define('DOK_NAMA_MAKS', 160);                  // panjang nama berkas yang disimpan
define('DOK_KOMENTAR_MAKS', 1000);             // panjang komentar penilai per butir
// v1.19.0 - TAUTAN GOOGLE DRIVE sebagai alternatif unggah berkas (permintaan pemilik:
// "sediakan fitur untuk memasukkan link google drive jika dokumennya sudah ada di google drive").
define('DOK_TAUTAN_MAKS', 500);                // panjang tautan yang disimpan
define('DOK_TAUTAN_NAMA', 'Dokumen Google Drive');   // nama bawaan bila judul tidak diisi
define('DOK_TAUTAN_NAMA_MAKS', 90);            // panjang judul tautan
