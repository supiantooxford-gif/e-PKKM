<?php
/**
 * e-PKKM - titik masuk bersama (config, helper, skema, mesin hitung, kerangka halaman).
 * Setiap halaman *.php di akar memanggil berkas ini lebih dulu.
 */

error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
date_default_timezone_set('Asia/Makassar');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/daftar.php';
require_once __DIR__ . '/penilai.php';
require_once __DIR__ . '/hitung.php';
require_once __DIR__ . '/dokumen.php';
require_once __DIR__ . '/form_penilaian.php';
require_once __DIR__ . '/kop.php';
require_once __DIR__ . '/layout.php';

mulai_sesi();
ensure_schema();
