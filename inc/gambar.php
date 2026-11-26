<?php
/**
 * e-PKKM - GAMBAR HALAMAN DEPAN DILAYANI APLIKASI SENDIRI (v1.14.0).
 *
 * Permintaan pemilik: *"Pastikan semua gambar yang ada dihalaman depan tidak bisa dibuka sumbernya
 * (dikunci) seperti pada aplikasi e-guru dan supervisi."*
 *
 * Cara kerjanya (sama pola dengan e-GURU v1.91.0 & Supervisi Guru v1.32.0):
 *   1. Halaman depan TIDAK mencetak alamat CDN gambar slider ke dalam HTML. Yang dicetak hanyalah
 *      alamat aplikasi sendiri: `gambar.php?j=slider&id=<id>&v=<sidik>`.
 *   2. `gambar.php` mengambil gambarnya dari CDN **di sisi server** lalu meneruskannya. Permintaan
 *      yang bukan dari halaman aplikasi ini (dibuka langsung di tab baru, atau dipasang di situs
 *      lain) dijawab **403** → alamat gambar tidak bisa dibuka/dibagikan/di-hotlink.
 *   3. Di sisi tampilan gambar diberi kelas `lp-lindung` + `data-lindung="1"` di dalam wadah
 *      `data-lindung-wadah="1"` → tidak bisa diklik kanan, tidak bisa diseret, dan menu "Simpan
 *      gambar" pada tekan-lama di HP tidak muncul (lihat blok CSS "GAMBAR … DILINDUNGI" pada
 *      `assets/style.css` dan `initLindungGambar()` pada `assets/app.js`).
 *
 * ⚠️ BATAS TEKNIS (sudah dijelaskan ke pemilik pada aplikasi lain): gambar yang tampil memang sudah
 * diunduh peramban. Yang ditutup adalah klik kanan/simpan gambar, seret, buka alamat gambar,
 * hotlink, dan alamat sumber pada View page source. Pengunjung yang membuka Developer Tools tetap
 * dapat menyalin berkas yang sudah ada di perambannya.
 *
 * Mengganti gambar (Super Admin) tidak perlu mengubah apa pun di sini: nilai `v` adalah sidik alamat
 * foto, sehingga berkas baru otomatis memakai alamat baru (cache peramban tidak salah tampil).
 */

/** Host media proxy platform yang boleh diambil aplikasi ini (mencegah SSRF). */
const GAMBAR_HOST_IZIN = ['media.vibecoder.co.id'];

/** Batas ukuran berkas yang mau diteruskan (pengaman; batas unggah gambar aplikasi 15 MB). */
const GAMBAR_MAKS_BYTE = 20 * 1024 * 1024;

/** Lama cache di peramban pengunjung (detik). Alamat ikut berubah bila gambarnya diganti. */
const GAMBAR_CACHE_DETIK = 604800; // 7 hari

/** Jenis MIME yang boleh diteruskan ke peramban. */
const GAMBAR_MIME = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];

/**
 * Halaman aplikasi yang boleh menjadi asal permintaan gambar (cadangan untuk peramban lama yang
 * belum mengirim `Sec-Fetch-Site`). Yang dipakai NAMA HALAMAN, bukan host — lihat catatan pada
 * gambar_referer_sah().
 */
const GAMBAR_REFERER_HALAMAN = ['login.php', 'index.php'];

if (!function_exists('gambar_host_izin')) {
    /**
     * Daftar host yang boleh diambil server ini.
     *
     * `PKKM_GAMBAR_HOST_UJI` boleh diisi SATU host tambahan untuk pengujian (server contoh gambar
     * di localhost) — pola yang sama dengan `SUPERVISI_DB_FILE` pada menu Tarik dari Supervisi.
     * Tidak pernah diisi pada aplikasi yang dipakai pengguna.
     */
    function gambar_host_izin(): array
    {
        $daftar = GAMBAR_HOST_IZIN;
        $env = getenv('PKKM_GAMBAR_HOST_UJI');
        if (is_string($env) && trim($env) !== '') {
            $daftar[] = strtolower(trim($env));
        }
        return $daftar;
    }
}

if (!function_exists('gambar_host_sah')) {
    /** Apakah alamat gambar boleh diambil server ini? (hanya http/https pada media platform) */
    function gambar_host_sah(string $url): bool
    {
        if ($url === '' || strlen($url) > 500) {
            return false;
        }
        $p = parse_url($url);
        if (!is_array($p)) {
            return false;
        }
        $skema = strtolower((string)($p['scheme'] ?? ''));
        if ($skema !== 'https' && $skema !== 'http') {
            return false;
        }
        $host = strtolower((string)($p['host'] ?? ''));
        if ($host === '') {
            return false;
        }
        foreach (gambar_host_izin() as $izin) {
            if ($host === strtolower($izin)) {
                return true;
            }
        }
        return substr($host, -strlen('.vibecoder.co.id')) === '.vibecoder.co.id';
    }
}

if (!function_exists('gambar_remote')) {
    /**
     * Alamat asli (CDN) sebuah gambar halaman depan — HANYA dipakai di sisi server.
     *
     * @param string $jenis 'slider' (default) atau 'logo' (logo aplikasi pada panel masuk)
     * @param int    $id    id baris `depan_slider` (diabaikan untuk logo)
     */
    function gambar_remote(string $jenis, int $id): string
    {
        $url = '';
        try {
            if ($jenis === 'logo') {
                // v1.17.0: logo aplikasi (bulat) pada panel masuk — diunggah Super Admin.
                $url = trim(app_setting('logo_app'));
            } elseif ($id > 0) {
                $baris = one('SELECT foto FROM depan_slider WHERE id = ? AND aktif = 1', [$id]);
                $url = trim((string)($baris['foto'] ?? ''));
            }
        } catch (Throwable $e) {
            return '';
        }
        return gambar_host_sah($url) ? $url : '';
    }
}

if (!function_exists('gambar_versi')) {
    /** Sidik pendek alamat gambar — dipakai sebagai `v=` (pengusir cache) dan ETag. */
    function gambar_versi(string $remote): string
    {
        return substr(sha1($remote), 0, 12);
    }
}

if (!function_exists('gambar_url')) {
    /** Alamat LOKAL gambar yang dicetak ke HTML (selalu relatif — aplikasi di subfolder). */
    function gambar_url(int $id, string $remote, string $jenis = 'slider'): string
    {
        if ($remote === '' || ($jenis === 'slider' && $id <= 0)) {
            return '';
        }
        $q = 'j=' . rawurlencode($jenis);
        if ($jenis === 'slider') {
            $q .= '&id=' . $id;
        }
        return 'gambar.php?' . $q . '&v=' . gambar_versi($remote);
    }
}

if (!function_exists('gambar_tag')) {
    /**
     * Markup `<img>` gambar slider halaman depan yang sudah dilindungi.
     * WAJIB dipakai halaman depan supaya (a) alamat CDN tidak bocor dan (b) penjagaan
     * klik-kanan/seret selalu terpasang.
     */
    function gambar_tag(int $id, string $remote, string $alt, string $kelas = '', bool $lazy = false,
                       string $jenis = 'slider'): string
    {
        $src = gambar_url($id, $remote, $jenis);
        if ($src === '') {
            return '';
        }
        $kelas = trim($kelas . ' lp-lindung');
        return '<img src="' . e($src) . '" alt="' . e($alt) . '" class="' . e($kelas) . '"'
            . ' data-lindung="1" draggable="false" decoding="async"'
            . ($lazy ? ' loading="lazy"' : '') . '>';
    }
}

if (!function_exists('gambar_referer_sah')) {
    /**
     * Apakah permintaan gambar datang dari halaman aplikasi ini sendiri?
     *
     * 🚨 JEBAKAN YANG SUDAH MENGGIGIT di aplikasi lain (jangan diulang): bila aplikasi dipasang
     * pada DOMAIN SENDIRI, platform meneruskan permintaan dengan `HTTP_HOST` = host INTERNAL,
     * sedangkan peramban mengirim `Referer` berisi domain yang dilihat pengunjung → pencocokan HOST
     * GAGAL dan semua gambar dijawab 403 (halaman depan jadi kosong).
     *
     * Karena itu **jangan membandingkan host sama sekali**. Yang dipakai:
     *   1. `Sec-Fetch-Site` (diisi peramban sendiri, tidak dapat dipalsukan dari JavaScript):
     *      same-origin / same-site → boleh; cross-site / none → tolak.
     *   2. Peramban lama tanpa `Sec-Fetch-Site` → `Referer` dicocokkan lewat NAMA HALAMAN-nya.
     */
    function gambar_referer_sah(): bool
    {
        $sit = strtolower(trim((string)($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '')));
        if ($sit !== '') {
            return $sit === 'same-origin' || $sit === 'same-site';
        }
        $ref = trim((string)($_SERVER['HTTP_REFERER'] ?? ''));
        if ($ref === '') {
            return false;
        }
        $jalur = (string)parse_url($ref, PHP_URL_PATH);
        return in_array(strtolower(basename($jalur)), GAMBAR_REFERER_HALAMAN, true);
    }
}

if (!function_exists('gambar_svg_aman')) {
    /** Apakah isi SVG aman diteruskan (SVG dapat menjalankan kode). */
    function gambar_svg_aman(string $isi): bool
    {
        if ($isi === '' || strlen($isi) > 2 * 1024 * 1024) {
            return false;
        }
        $bawah = strtolower($isi);
        if (strpos($bawah, '<svg') === false) {
            return false;
        }
        foreach (['<script', '<foreignobject', '<iframe', '<embed', '<object',
                  '<!entity', '<!doctype', 'javascript:', 'data:text/html'] as $tanda) {
            if (strpos($bawah, $tanda) !== false) {
                return false;
            }
        }
        return preg_match('/\son[a-z]{3,}\s*=/i', $isi) !== 1;
    }
}

if (!function_exists('gambar_mime_dari_url')) {
    /** Jenis gambar dari ekstensi (cadangan bila CDN tidak mengirim Content-Type yang jelas). */
    function gambar_mime_dari_url(string $url): string
    {
        $jalur = strtolower((string)parse_url($url, PHP_URL_PATH));
        $peta = [
            'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
            'gif' => 'image/gif', 'webp' => 'image/webp', 'svg' => 'image/svg+xml',
        ];
        return $peta[(string)pathinfo($jalur, PATHINFO_EXTENSION)] ?? '';
    }
}

if (!function_exists('gambar_ambil_stream')) {
    /** Cadangan bila curl tidak tersedia/gagal. @return array{0:string,1:string,2:int} */
    function gambar_ambil_stream(string $url): array
    {
        $konteks = stream_context_create([
            'http' => ['timeout' => 10, 'user_agent' => APP_NAME . '/' . APP_VERSION,
                       'follow_location' => 1, 'max_redirects' => 2],
            'ssl'  => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);
        $isi = (string)@file_get_contents($url, false, $konteks, 0, GAMBAR_MAKS_BYTE + 1);
        $tipe = '';
        foreach (($http_response_header ?? []) as $h) {
            if (stripos($h, 'content-type:') === 0) {
                $tipe = trim(substr($h, 13));
            }
        }
        return [$isi, $tipe, $isi !== '' ? 200 : 0];
    }
}

if (!function_exists('gambar_ambil')) {
    /**
     * Ambil isi gambar dari CDN (sisi server). curl lebih dulu; `file_get_contents` sebagai
     * cadangan (satu kegagalan curl tidak boleh membuat seluruh gambar halaman depan tidak tampil).
     *
     * @return array{ok:bool,isi:string,mime:string,kode:int}
     */
    function gambar_ambil(string $url): array
    {
        $keluar = ['ok' => false, 'isi' => '', 'mime' => '', 'kode' => 0];
        if (!gambar_host_sah($url)) {
            return $keluar;
        }
        $isi = '';
        $tipe = '';
        $kode = 0;
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 2,
                CURLOPT_CONNECTTIMEOUT => 4,
                CURLOPT_TIMEOUT        => 10,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_ENCODING       => '',
                CURLOPT_USERAGENT      => APP_NAME . '/' . APP_VERSION,
            ]);
            $hasilCurl = curl_exec($ch);
            $kode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $tipe = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            if (is_string($hasilCurl)) {
                $isi = $hasilCurl;
            }
            curl_close($ch);
        }
        if (($kode !== 200 || $isi === '') && (bool)ini_get('allow_url_fopen')) {
            [$isiCadangan, $tipeCadangan, $kodeCadangan] = gambar_ambil_stream($url);
            if ($kodeCadangan === 200 && $isiCadangan !== '') {
                $isi = $isiCadangan;
                $tipe = $tipeCadangan;
                $kode = $kodeCadangan;
            }
        }
        if ($kode !== 200 || $isi === '' || strlen($isi) > GAMBAR_MAKS_BYTE) {
            return $keluar;
        }
        $mime = strtolower(trim((string)explode(';', $tipe)[0]));
        if (!in_array($mime, GAMBAR_MIME, true)) {
            $mime = gambar_mime_dari_url($url);
        }
        if ($mime === '') {
            return $keluar;
        }
        if ($mime === 'image/svg+xml' && !gambar_svg_aman($isi)) {
            return $keluar;
        }
        return ['ok' => true, 'isi' => $isi, 'mime' => $mime, 'kode' => $kode];
    }
}

if (!function_exists('gambar_galat')) {
    /** Balasan galat singkat (tanpa isi halaman aplikasi). */
    function gambar_galat(int $kode): void
    {
        if (!headers_sent()) {
            http_response_code($kode);
            header('Content-Type: text/plain; charset=utf-8');
            header('Cache-Control: no-store');
        }
        echo $kode === 403 ? 'Gambar hanya dapat ditampilkan pada halaman aplikasi ini.'
                           : 'Gambar tidak ditemukan.';
    }
}

if (!function_exists('gambar_halaman')) {
    /** Titik masuk `gambar.php`: validasi → ambil dari CDN → teruskan ke peramban. */
    function gambar_halaman(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        $jenis = (string)($_GET['j'] ?? 'slider');
        if (!in_array($jenis, ['slider', 'logo'], true)) {
            $jenis = 'slider';
        }
        if (!headers_sent()) {
            header('X-Content-Type-Options: nosniff');
            header('X-Robots-Tag: noindex, nofollow, noimageindex');
        }
        $remote = gambar_remote($jenis, $id);
        if ($remote === '') {
            gambar_galat(404);
            return;
        }
        // Hanya boleh dibuka dari halaman aplikasi ini (bukan dibuka langsung / dipasang di situs lain).
        if (!gambar_referer_sah()) {
            gambar_galat(403);
            return;
        }
        $etag = '"' . gambar_versi($remote) . '"';
        if (!headers_sent()) {
            header('ETag: ' . $etag);
            header('Cache-Control: private, max-age=' . GAMBAR_CACHE_DETIK . ', immutable');
        }
        if (trim((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) {
            http_response_code(304);
            return;
        }
        $hasil = gambar_ambil($remote);
        if (empty($hasil['ok'])) {
            error_log('[e-PKKM] gambar: gagal mengambil "' . $jenis . '" id ' . $id . ' (kode ' . (int)$hasil['kode'] . ')');
            gambar_galat(404);
            return;
        }
        if (!headers_sent()) {
            header('Content-Type: ' . $hasil['mime']);
            header('Content-Length: ' . strlen($hasil['isi']));
            header('Content-Disposition: inline');
            if ($hasil['mime'] === 'image/svg+xml') {
                header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; sandbox");
            }
        }
        echo $hasil['isi'];
    }
}
