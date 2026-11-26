<?php
/**
 * e-PKKM - endpoint penerus GAMBAR HALAMAN DEPAN (v1.14.0).
 *
 * Halaman depan (`login.php`) mencetak alamat `gambar.php?j=slider&id=…&v=…` — BUKAN alamat CDN
 * gambarnya — lalu berkas ini mengambil gambarnya dari CDN di sisi server dan meneruskannya.
 * Dengan begitu: alamat sumber tidak terlihat pengunjung (klik kanan / View page source), alamat
 * gambar tidak bisa dibuka langsung atau di-hotlink (403), dan gambarnya tidak bisa diklik kanan /
 * diseret / disimpan (kelas `lp-lindung` + `initLindungGambar()` di `assets/app.js`).
 *
 * Berkas ini TIDAK memakai sesi (lihat `PKKM_TANPA_SESI`) supaya permintaan gambar tidak saling
 * mengunci berkas sesi dan tidak memasang cookie pada balasan gambar.
 */
declare(strict_types=1);

define('PKKM_TANPA_SESI', true);

require_once __DIR__ . '/inc/init.php';

gambar_halaman();
