<?php
/**
 * e-PKKM - Menu "Penilaian Guru & Tendik" SUDAH DIHAPUS (permintaan pemilik, v1.8.0).
 *
 * Peran Guru, Tenaga Kependidikan, Komite Madrasah, Kepala Bidang / Yayasan, dan Kepala Seksi kini
 * menjadi **AKUN PENILAI Tahun Keempat** (7 penilai: Pengawas 1, Pengawas 2, lalu lima jabatan itu).
 * Penilaian mereka diisi sendiri oleh masing-masing penilai pada menu **Penilaian Kepala Madrasah**
 * lewat lembar penilai Tahun Keempat miliknya.
 *
 * Berkas ini dipertahankan hanya sebagai PENGALIH: tautan/lama (bookmark) tidak menjadi halaman
 * kosong (404), tetapi diarahkan ke halaman penilaian dengan penjelasan singkat.
 * Data GTK lama (tabel `penilaian` kategori `gtk`) TIDAK dihapus — masih dipakai sebagai cadangan
 * perhitungan komponen Tahun Keempat bila slot penilai belum terisi.
 */
require_once __DIR__ . '/inc/init.php';
require_login();

flash('Menu "Penilaian Guru & Tendik" sudah digabung: Guru, Tendik, Komite, Kepala Bidang/Yayasan, '
    . 'dan Kepala Seksi kini menjadi AKUN PENILAI Tahun Keempat (7 penilai). '
    . 'Penilaian diisi penilai masing-masing pada menu Penilaian Kepala Madrasah.', 'err');
// tanpa konteks madrasah (mis. Super Admin) kembalikan ke dashboard, bukan ke pemilih madrasah
redirect(current_sekolah() ? 'penilaian.php' : 'index.php');
