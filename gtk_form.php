<?php
/**
 * e-PKKM - Lembar penilaian GTK (menu "Penilaian Guru & Tendik") SUDAH DIHAPUS (v1.8.0).
 * Peran Guru/Tendik/Komite/Kabid-Yayasan/Kasie kini menjadi AKUN PENILAI Tahun Keempat; lembar
 * penilaiannya dibuka dari menu **Penilaian Kepala Madrasah** (lembar penilai Tahun Keempat).
 * Berkas ini dipertahankan sebagai PENGALIH agar tautan lama tidak menjadi halaman kosong.
 */
require_once __DIR__ . '/inc/init.php';
require_login();

flash('Lembar Penilaian Guru & Tendik sudah digabung: penilai Tahun Keempat mengisi lembarnya sendiri '
    . 'melalui menu Penilaian Kepala Madrasah.', 'err');
// tanpa konteks madrasah (mis. Super Admin) kembalikan ke dashboard, bukan ke pemilih madrasah
redirect(current_sekolah() ? 'penilaian.php' : 'index.php');
