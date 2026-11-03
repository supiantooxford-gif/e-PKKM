<?php
/**
 * e-PKKM - helper umum: basis data, keluaran, sesi, CSRF, hak akses, konteks madrasah,
 * tanggal & angka.
 */

if (!function_exists('e')) {
    /** Escape untuk keluaran HTML. */
    function e($s): string
    {
        return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('sq')) {
    /** Koneksi PDO tunggal (SQLite). */
    function db(): PDO
    {
        static $pdo = null;
        if ($pdo instanceof PDO) {
            return $pdo;
        }
        if (!is_dir(DATA_DIR)) {
            @mkdir(DATA_DIR, 0777, true);
        }
        $pdo = new PDO('sqlite:' . DB_FILE);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA busy_timeout = 5000');
        $pdo->exec('PRAGMA foreign_keys = ON');
        try {
            $pdo->exec('PRAGMA journal_mode = WAL');
            $pdo->exec('PRAGMA synchronous = NORMAL');
        } catch (Throwable $t) {
            // diabaikan: sebagian sistem berkas tidak mendukung WAL
        }
        return $pdo;
    }
}

if (!function_exists('q')) {
    /** Jalankan query, kembalikan PDOStatement. */
    function q(string $sql, array $args = []): PDOStatement
    {
        $st = db()->prepare($sql);
        $st->execute($args);
        return $st;
    }
}

if (!function_exists('all')) {
    function all(string $sql, array $args = []): array
    {
        return q($sql, $args)->fetchAll();
    }
}

if (!function_exists('one')) {
    function one(string $sql, array $args = []): ?array
    {
        $r = q($sql, $args)->fetch();
        return $r === false ? null : $r;
    }
}

if (!function_exists('val')) {
    function val(string $sql, array $args = [])
    {
        $r = q($sql, $args)->fetch(PDO::FETCH_NUM);
        return $r === false ? null : $r[0];
    }
}

if (!function_exists('exec_q')) {
    function exec_q(string $sql, array $args = []): int
    {
        return q($sql, $args)->rowCount();
    }
}

if (!function_exists('mulai_transaksi')) {
    /** Transaksi tulis: BEGIN IMMEDIATE (bukan BEGIN biasa) supaya tahan akses bersamaan. */
    function mulai_transaksi(): void
    {
        db()->exec('BEGIN IMMEDIATE');
    }
}

if (!function_exists('selesai_transaksi')) {
    function selesai_transaksi(bool $commit = true): void
    {
        db()->exec($commit ? 'COMMIT' : 'ROLLBACK');
    }
}

// ------------------------------------------------------------------ sesi ----
if (!function_exists('mulai_sesi')) {
    function mulai_sesi(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        if (!is_dir(SESI_DIR)) {
            @mkdir(SESI_DIR, 0777, true);
        }
        if (is_dir(SESI_DIR) && is_writable(SESI_DIR)) {
            session_save_path(SESI_DIR);
        }
        session_set_cookie_params([
            'httponly' => true,
            'samesite' => 'Lax',
            'secure' => (($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'),
        ]);
        session_start();
    }
}

// ---------------------------------------------------------------- CSRF -----
if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        mulai_sesi();
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(16));
        }
        return $_SESSION['csrf'];
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
    }
}

if (!function_exists('csrf_ok')) {
    function csrf_ok(): bool
    {
        $t = (string)($_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        return $t !== '' && hash_equals(csrf_token(), $t);
    }
}

// --------------------------------------------------------------- flash -----
if (!function_exists('flash')) {
    function flash(string $pesan, string $tipe = 'ok'): void
    {
        mulai_sesi();
        $_SESSION['flash'][] = ['pesan' => $pesan, 'tipe' => $tipe];
    }
}

if (!function_exists('flash_ambil')) {
    function flash_ambil(): array
    {
        mulai_sesi();
        $f = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']);
        return $f;
    }
}

if (!function_exists('redirect')) {
    function redirect(string $url): void
    {
        // hanya menerima alamat relatif aplikasi ini (anti pengalihan terbuka)
        if (preg_match('#^(https?:)?//#i', $url) || strpos($url, ':') !== false) {
            $url = 'index.php';
        }
        header('Location: ' . $url);
        exit;
    }
}

// ------------------------------------------------------------ pengguna -----
if (!function_exists('current_user')) {
    function current_user(): ?array
    {
        static $u = null;
        static $sudah = false;
        if ($sudah) {
            return $u;
        }
        $sudah = true;
        mulai_sesi();
        $id = (int)($_SESSION['uid'] ?? 0);
        if ($id <= 0) {
            return $u = null;
        }
        $u = one('SELECT * FROM users WHERE id = ?', [$id]);
        if ($u && !(int)$u['aktif']) {
            return $u = null;
        }
        return $u;
    }
}

if (!function_exists('is_super')) {
    function is_super(): bool
    {
        $u = current_user();
        return $u !== null && $u['role'] === 'super';
    }
}

if (!function_exists('is_admin')) {
    function is_admin(): bool
    {
        $u = current_user();
        return $u !== null && $u['role'] === 'admin';
    }
}

if (!function_exists('is_pengawas')) {
    function is_pengawas(): bool
    {
        $u = current_user();
        return $u !== null && $u['role'] === 'pengawas';
    }
}

if (!function_exists('role_label')) {
    function role_label(?string $role = null): string
    {
        $u = current_user();
        $r = $role ?? ($u['role'] ?? '');
        // Peran `pengawas` kini ditampilkan sebagai PENILAI (permintaan pemilik); statusnya tetap
        // pengawas, sedangkan keterangan mengikuti JABATAN yang dipilih saat mendaftar.
        if ($r === 'pengawas') {
            $jab = $role === null ? trim((string)($u['jabatan'] ?? '')) : '';
            return $jab === '' ? 'Penilai (Pengawas)' : 'Penilai — ' . $jab;
        }
        return [
            'super' => 'Super Admin',
            'admin' => 'Kepala Madrasah',
        ][$r] ?? '-';
    }
}

if (!function_exists('require_login')) {
    function require_login(): array
    {
        $u = current_user();
        if (!$u) {
            redirect('login.php');
        }
        return $u;
    }
}

if (!function_exists('require_super')) {
    function require_super(): array
    {
        $u = require_login();
        if ($u['role'] !== 'super') {
            http_response_code(403);
            page_ditolak('Halaman ini hanya untuk Super Admin.');
        }
        return $u;
    }
}

// -------------------------------------------------------- konteks madrasah --
if (!function_exists('current_sekolah')) {
    /** Madrasah yang sedang dibuka: admin -> madrasahnya; super/pengawas -> $_SESSION['ctx_sekolah_id']. */
    function current_sekolah(): ?array
    {
        static $cache = [];
        $u = current_user();
        if (!$u) {
            return null;
        }
        if ($u['role'] === 'admin') {
            $sid = (int)($u['sekolah_id'] ?? 0);
        } else {
            mulai_sesi();
            $sid = (int)($_SESSION['ctx_sekolah_id'] ?? 0);
        }
        if ($sid <= 0) {
            return null;
        }
        if (array_key_exists($sid, $cache)) {
            return $cache[$sid];
        }
        $s = one('SELECT * FROM sekolah WHERE id = ? AND aktif = 1', [$sid]);
        if (!$s) {
            if ($u['role'] !== 'admin') {
                mulai_sesi();
                unset($_SESSION['ctx_sekolah_id']);
            }
            return $cache[$sid] = null;
        }
        return $cache[$sid] = $s;
    }
}

if (!function_exists('sekolah_id')) {
    function sekolah_id(): int
    {
        $s = current_sekolah();
        return $s ? (int)$s['id'] : 0;
    }
}

if (!function_exists('set_sekolah_context')) {
    function set_sekolah_context(int $id): void
    {
        mulai_sesi();
        $_SESSION['ctx_sekolah_id'] = $id;
    }
}

if (!function_exists('require_sekolah')) {
    /** Halaman milik satu madrasah: super tanpa konteks dialihkan memilih madrasah. */
    function require_sekolah(): array
    {
        $u = require_login();
        $s = current_sekolah();
        if (!$s) {
            if ($u['role'] === 'super') {
                flash('Pilih madrasah yang ingin dibuka terlebih dahulu.', 'err');
                redirect('sekolah.php');
            }
            if ($u['role'] === 'pengawas') {
                redirect('index.php');
            }
            http_response_code(403);
            page_ditolak('Akun Anda belum ditautkan ke madrasah.');
        }
        return $s;
    }
}

if (!function_exists('pengawas_tugas')) {
    /** Penugasan pengawas: daftar madrasah + posisi. */
    function pengawas_tugas(int $userId): array
    {
        return all(
            'SELECT ps.sekolah_id, ps.posisi, s.nama, s.aktif
               FROM pengawas_sekolah ps JOIN sekolah s ON s.id = ps.sekolah_id
              WHERE ps.user_id = ? ORDER BY s.nama',
            [$userId]
        );
    }
}

if (!function_exists('pengawas_posisi_di')) {
    /** Posisi pengawas pada sebuah madrasah (1/2), 0 bila bukan pengawas madrasah itu. */
    function pengawas_posisi_di(int $userId, int $sekolahId): int
    {
        $p = val('SELECT posisi FROM pengawas_sekolah WHERE user_id = ? AND sekolah_id = ?', [$userId, $sekolahId]);
        return $p === null ? 0 : (int)$p;
    }
}

if (!function_exists('boleh_isi_penilaian')) {
    /**
     * Siapa yang boleh mengisi penilaian kepala madrasah:
     *  - pengawas yang ditugaskan dengan posisi tersebut pada madrasah itu
     *  - Super Admin (pemilik aplikasi, boleh membantu bila penugasannya keliru)
     */
    function boleh_isi_penilaian(int $sekolahId, string $posisi): bool
    {
        $u = current_user();
        if (!$u) {
            return false;
        }
        if ($u['role'] === 'super') {
            return true;
        }
        if ($u['role'] !== 'pengawas') {
            return false;
        }
        return pengawas_posisi_di((int)$u['id'], $sekolahId) === (int)$posisi;
    }
}

if (!function_exists('penilai_jabatan_pengawas')) {
    /**
     * Apakah sebuah akun penilai berjabatan PENGAWAS (boleh menilai Tahun Pertama-Keempat).
     *
     * Aturan pemilik: "selain jabatan pengawas hanya bisa menilai di tahun ke 4; tahun ke 1-3 hanya
     * jabatan pengawas yang bisa menilai". Jabatan diambil dari kolom `users.jabatan` (daftar yang
     * diisi Super Admin). Jabatan yang belum diisi (akun penilai LAMA sebelum fitur jabatan ada)
     * diperlakukan sebagai pengawas agar tidak ada penilai yang mendadak kehilangan Tahun 1-3.
     *
     * @param array|null $u baris users; null = pengguna yang sedang masuk
     */
    function penilai_jabatan_pengawas(?array $u = null): bool
    {
        if ($u === null) {
            $u = current_user();
        }
        if (!$u || ($u['role'] ?? '') !== 'pengawas') {
            return false;
        }
        $jab = strtolower(trim((string)($u['jabatan'] ?? '')));
        if ($jab === '' || $jab === '-') {
            return true;   // data lama: dianggap pengawas
        }
        return str_starts_with($jab, 'pengawas');
    }
}

if (!function_exists('penilai_tahun_boleh')) {
    /** Daftar tahun penilaian yang boleh diisi sebuah akun penilai (kunci 1..4). */
    function penilai_tahun_boleh(?array $u = null): array
    {
        if (penilai_jabatan_pengawas($u)) {
            return array_keys(PKKM_TAHUN_LIST);
        }
        return [4];   // jabatan selain pengawas: hanya Tahun Keempat
    }
}

if (!function_exists('penilai_tahun_label')) {
    /** Keterangan singkat tahun yang boleh dinilai, mis. "Tahun Pertama – Keempat". */
    function penilai_tahun_label(?array $u = null): string
    {
        $t = penilai_tahun_boleh($u);
        return count($t) >= count(PKKM_TAHUN_LIST)
            ? 'Tahun Pertama – Keempat'
            : 'Hanya Tahun Keempat';
    }
}

if (!function_exists('boleh_isi_tahun')) {
    /**
     * Boleh mengisi lembar penilaian pada TAHUN tertentu?
     *  - syarat posisi/penugasan sama dengan boleh_isi_penilaian();
     *  - ditambah aturan pemilik: jabatan selain pengawas hanya boleh mengisi Tahun Keempat.
     */
    function boleh_isi_tahun(int $sekolahId, string $posisi, int $tahun): bool
    {
        if (!boleh_isi_penilaian($sekolahId, $posisi)) {
            return false;
        }
        $u = current_user();
        if ($u && $u['role'] === 'super') {
            return true;   // Super Admin tetap boleh membantu seluruh tahun
        }
        return in_array($tahun, penilai_tahun_boleh($u), true);
    }
}

if (!function_exists('boleh_isi_gtk')) {
    /**
     * Penilaian Guru & Tendik (kategori `gtk`) — FITUR LAMA.
     *
     * Sejak v1.8.0 menu "Penilaian Guru & Tendik" DIHAPUS (permintaan pemilik): peran Guru, Tendik,
     * Komite, Kepala Bidang/Yayasan, dan Kepala Seksi kini menjadi AKUN PENILAI Tahun Keempat
     * (7 penilai) sehingga penilaiannya diisi penilai masing-masing, bukan oleh Super Admin.
     * Fungsi ini DIPERTAHANKAN hanya agar pemanggil lama tidak fatal; selalu false.
     */
    function boleh_isi_gtk(int $sekolahId): bool
    {
        return false;
    }
}

// -------------------------------------------------------------- tanggal -----
if (!function_exists('bulan_id')) {
    function bulan_id(): array
    {
        return [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    }
}

if (!function_exists('tanggal_id')) {
    /** 2026-11-25 -> 25 November 2026 (teks kosong bila tidak sah). */
    function tanggal_id(?string $iso, bool $denganHari = false): string
    {
        $iso = trim((string)$iso);
        if ($iso === '' || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $iso, $m)) {
            return '';
        }
        $ts = mktime(0, 0, 0, (int)$m[2], (int)$m[3], (int)$m[1]);
        if (!$ts) {
            return '';
        }
        $b = bulan_id();
        $hari = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        $teks = (int)$m[3] . ' ' . $b[(int)$m[2]] . ' ' . $m[1];
        if ($denganHari) {
            $teks = $hari[(int)date('w', $ts)] . ', ' . $teks;
        }
        return $teks;
    }
}

if (!function_exists('waktu_id')) {
    function waktu_id(?string $ts): string
    {
        $ts = trim((string)$ts);
        if ($ts === '') {
            return '-';
        }
        $t = strtotime($ts);
        if (!$t) {
            return e($ts);
        }
        return tanggal_id(date('Y-m-d', $t)) . ' ' . date('H:i', $t);
    }
}

if (!function_exists('tanggal_iso')) {
    /** Menerima 25 November 2026 / 2026-11-25 / 25/11/2026 -> ISO. */
    function tanggal_iso(string $teks): string
    {
        $teks = trim($teks);
        if ($teks === '') {
            return '';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $teks) && checkdate((int)substr($teks, 5, 2), (int)substr($teks, 8, 2), (int)substr($teks, 0, 4))) {
            return $teks;
        }
        $b = array_change_key_case(array_flip(bulan_id()), CASE_LOWER);
        if (preg_match('/^(\d{1,2})\s+([A-Za-z]+)\s+(\d{4})$/', $teks, $m)) {
            $bl = $b[strtolower(substr($m[2], 0, 3))] ?? 0;
            if ($bl && checkdate($bl, (int)$m[1], (int)$m[3])) {
                return sprintf('%04d-%02d-%02d', (int)$m[3], $bl, (int)$m[1]);
            }
        }
        if (preg_match('#^(\d{1,2})[/.-](\d{1,2})[/.-](\d{4})$#', $teks, $m)) {
            if (checkdate((int)$m[2], (int)$m[1], (int)$m[3])) {
                return sprintf('%04d-%02d-%02d', (int)$m[3], (int)$m[2], (int)$m[1]);
            }
        }
        return '';
    }
}

// ---------------------------------------------------------------- angka -----
if (!function_exists('n2')) {
    /** Angka dengan 2 desimal (untuk tampilan nilai). */
    function n2($n, int $desimal = 2): string
    {
        if ($n === null || $n === '') {
            return '-';
        }
        return number_format((float)$n, $desimal, ',', '.');
    }
}

if (!function_exists('potong_teks')) {
    /** Potong teks dengan aman (mbstring tidak selalu tersedia di server). */
    function potong_teks(string $s, int $maks): string
    {
        if (function_exists('mb_substr')) {
            return mb_substr($s, 0, $maks, 'UTF-8');
        }
        if (strlen($s) <= $maks) {
            return $s;
        }
        $p = substr($s, 0, $maks);
        // jangan memotong di tengah karakter UTF-8
        while ($p !== '' && (ord(substr($p, -1)) & 0xC0) === 0x80) {
            $p = substr($p, 0, -1);
        }
        return $p;
    }
}

if (!function_exists('bersih_teks')) {
    function bersih_teks($s, int $maks = PKKM_TEKS_PANJANG): string
    {
        $s = trim(preg_replace('/\s+/u', ' ', (string)$s));
        return potong_teks($s, $maks);
    }
}

if (!function_exists('pilihan_tahun')) {
    function pilihan_tahun(int $tahun): string
    {
        return PKKM_TAHUN_LIST[$tahun] ?? ('Tahun ' . $tahun);
    }
}

if (!function_exists('tolak_csrf')) {
    /** Token CSRF tidak sah / kedaluwarsa: jawab 419 dengan halaman ramah. */
    function tolak_csrf(): void
    {
        http_response_code(419);
        echo '<!doctype html><html lang="id"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>Sesi berakhir - ' . e(APP_NAME) . '</title>'
            . '<link rel="stylesheet" href="assets/style.css?v=' . e(APP_VERSION) . '"></head>'
            . '<body class="bg-deco"><main class="wrap wrap-sempit">'
            . '<div class="card"><h1>Sesi Anda sudah berakhir</h1>'
            . '<p class="mut">Halaman sudah dibuka terlalu lama atau dibuka dari jendela lain. '
            . 'Muat ulang halaman lalu ulangi pengisian.</p>'
            . '<p><a class="btn" href="index.php">Kembali ke Dashboard</a> '
            . '<a class="btn btn-ghost" href="login.php">Masuk ulang</a></p></div>'
            . '</main></body></html>';
        exit;
    }
}

if (!function_exists('page_ditolak')) {
    /** Halaman 403 sederhana. */
    function page_ditolak(string $pesan): void
    {
        echo '<!doctype html><html lang="id"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>Akses ditolak - ' . e(APP_NAME) . '</title>'
            . '<link rel="stylesheet" href="assets/style.css?v=' . e(APP_VERSION) . '"></head>'
            . '<body class="bg-deco"><main class="wrap wrap-sempit">'
            . '<div class="card"><h1>Akses ditolak</h1><p class="mut">' . e($pesan) . '</p>'
            . '<p><a class="btn" href="index.php">Kembali ke Dashboard</a> '
            . '<a class="btn btn-ghost" href="logout.php">Keluar</a></p></div>'
            . '</main></body></html>';
        exit;
    }
}

if (!function_exists('pilihan_teks')) {
    /** Nilai aman dari daftar pilihan (nilai asing -> bawaan). */
    function pilihan_teks(array $daftar, $nilai, string $bawaan = ''): string
    {
        $nilai = (string)$nilai;
        return array_key_exists($nilai, $daftar) ? $nilai : $bawaan;
    }
}

// =================================================================================
// BERKAS FOTO (dipakai halaman depan / landing page)
// =================================================================================

if (!defined('FOTO_EKSTENSI')) {
    define('FOTO_EKSTENSI', ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg']);
}
if (!defined('FOTO_MAKS_BYTE')) {
    define('FOTO_MAKS_BYTE', 15 * 1024 * 1024);
}
if (!defined('MEDIA_ENDPOINT')) {
    define('MEDIA_ENDPOINT', 'http://127.0.0.1:4310/api/app-media/upload');
}

if (!function_exists('permintaan_ip')) {
    /** IP pengunjung (di belakang proxy: entri terakhir X-Forwarded-For). */
    function permintaan_ip(): string
    {
        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $bagian = array_filter(array_map('trim', explode(',', (string)$_SERVER['HTTP_X_FORWARDED_FOR'])));
            if ($bagian) {
                $ip = (string)end($bagian);
            }
        }
        return potong_teks($ip, 60);
    }
}

if (!function_exists('wa_internasional')) {
    /** 0… / +62… / 62… -> 62… (tanpa tanda baca). */
    function wa_internasional(string $nomor): string
    {
        $n = preg_replace('/\D+/', '', $nomor) ?? '';
        if ($n === '') {
            return '';
        }
        if (str_starts_with($n, '0')) {
            return '62' . substr($n, 1);
        }
        if (str_starts_with($n, '620')) {
            return '62' . substr($n, 3);
        }
        return $n;
    }
}

if (!function_exists('validasi_foto')) {
    /** Periksa berkas foto sebelum dikirim ke media proxy. */
    function validasi_foto(string $namaAsli, int $ukuran, string $tmp = ''): array
    {
        $ext = strtolower((string)pathinfo($namaAsli, PATHINFO_EXTENSION));
        if (!in_array($ext, FOTO_EKSTENSI, true)) {
            return ['ok' => false, 'pesan' => 'Format berkas harus ' . implode(', ', FOTO_EKSTENSI)
                . ' (berkas Anda: ' . ($ext !== '' ? $ext : 'tanpa ekstensi') . ').'];
        }
        if ($ukuran <= 0) {
            return ['ok' => false, 'pesan' => 'Berkas foto kosong atau gagal diunggah.'];
        }
        if ($ukuran > FOTO_MAKS_BYTE) {
            return ['ok' => false, 'pesan' => 'Ukuran foto maksimal 15 MB (berkas Anda '
                . number_format($ukuran / 1048576, 1, ',', '.') . ' MB).'];
        }
        if ($ext !== 'svg' && $tmp !== '' && function_exists('getimagesize')) {
            if (@getimagesize($tmp) === false) {
                return ['ok' => false, 'pesan' => 'Berkas ini sepertinya bukan gambar yang sah.'];
            }
        }
        return ['ok' => true, 'pesan' => ''];
    }
}

if (!function_exists('media_upload')) {
    /**
     * Kirim berkas ke media proxy platform (server-side). Yang dikembalikan hanya URL hasilnya.
     * Token unggah dibaca dari `.vibecoder-media-token` — TIDAK pernah dikirim ke peramban.
     */
    function media_upload(string $bytes, string $filename): array
    {
        if ($bytes === '') {
            return ['ok' => false, 'pesan' => 'Berkas kosong.'];
        }
        $tokenFile = dirname(__DIR__) . '/.vibecoder-media-token';
        if (!is_file($tokenFile) || !is_readable($tokenFile)) {
            return ['ok' => false, 'pesan' => 'Layanan penyimpanan berkas belum siap (token unggah tidak ditemukan).'];
        }
        $token = trim((string)@file_get_contents($tokenFile));
        if ($token === '') {
            return ['ok' => false, 'pesan' => 'Layanan penyimpanan berkas belum siap (token unggah kosong).'];
        }
        $endpoint = MEDIA_ENDPOINT . '?filename=' . rawurlencode($filename);
        $body = false;
        $code = 0;
        if (function_exists('curl_init')) {
            $ch = curl_init($endpoint);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $bytes,
                CURLOPT_HTTPHEADER => ['X-App-Media-Token: ' . $token, 'Content-Type: application/octet-stream'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => 180,
            ]);
            $body = curl_exec($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
        } else {
            $ctx = stream_context_create(['http' => [
                'method' => 'POST',
                'header' => "X-App-Media-Token: " . $token . "\r\nContent-Type: application/octet-stream\r\n",
                'content' => $bytes,
                'timeout' => 180,
                'ignore_errors' => true,
            ]]);
            $body = @file_get_contents($endpoint, false, $ctx);
            if (isset($http_response_header) && is_array($http_response_header)) {
                foreach ($http_response_header as $h) {
                    if (preg_match('#^HTTP/\S+\s+(\d{3})#i', $h, $m)) {
                        $code = (int)$m[1];
                    }
                }
            }
        }
        if ($body === false) {
            return ['ok' => false, 'pesan' => 'Tidak dapat menghubungi layanan penyimpanan berkas. Coba lagi.'];
        }
        $data = json_decode((string)$body, true);
        if ($code !== 200 || !is_array($data) || empty($data['ok']) || empty($data['url'])) {
            $pesan = is_array($data) && !empty($data['pesan']) ? (string)$data['pesan']
                : 'Unggahan ditolak layanan penyimpanan berkas (kode ' . $code . ').';
            return ['ok' => false, 'pesan' => $pesan];
        }
        return ['ok' => true, 'url' => (string)$data['url'], 'pesan' => ''];
    }
}
