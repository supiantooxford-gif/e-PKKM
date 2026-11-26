<?php
/**
 * e-PKKM - LOG AKTIVITAS & PENGGUNA ONLINE (v1.15.0).
 *
 * Permintaan pemilik: *"Pada akun Super Admin tambahkan fitur log aktifitas seperti aplikasi e-Guru
 * termasuk hapus riwayatnya"* — mengikuti e-GURU (`e-guru/log.php` + helper di
 * `e-guru/inc/helpers.php`) dan Supervisi Guru (`supervisi-guru/log.php`).
 *
 * Dua hal yang ditampilkan halaman `log.php`:
 *   1. PENGGUNA SEDANG ONLINE → tabel `sesi_aktif` (online bila aktif ≤ LOG_MENIT_ONLINE menit lalu);
 *   2. RIWAYAT masuk / keluar / gagal masuk → tabel `log_aktivitas`.
 *
 * Aturan yang WAJIB dipertahankan:
 *   - Setiap penulisan log dibungkus try/catch: **pencatatan tidak boleh menggagalkan permintaan
 *     pengguna** (mis. basis data terkunci sesaat).
 *   - `log_ip()` TIDAK mengambil entri PERTAMA `X-Forwarded-For` (dapat dipalsukan klien) — lihat
 *     catatan keamanan pada fungsinya.
 *   - Riwayat menyimpan SNAPSHOT nama/madrasah sehingga tetap terbaca setelah akunnya dihapus.
 *   - Percobaan masuk gagal dibatasi 2000 baris terbaru (halaman masuk publik).
 */

if (!defined('LOG_MENIT_ONLINE')) {
    /** Jumlah menit tanpa aktivitas sebelum sebuah sesi dianggap tidak online lagi. */
    define('LOG_MENIT_ONLINE', 15);
}

if (!function_exists('ip_privat')) {
    /** true bila alamat IP termasuk loopback/privat/terpesan (mis. 127.0.0.1, 10.x, 192.168.x). */
    function ip_privat(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return true;
        }
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }
}

if (!function_exists('log_ip')) {
    /**
     * Alamat IP pemakai (dipakai Log Aktivitas).
     *
     * ⚠️ KEAMANAN (jangan dikembalikan ke versi lama): entri **PERTAMA** `X-Forwarded-For` dapat
     * ditulis sendiri oleh klien, sehingga riwayat bisa dibuat menyesatkan. Urutan kepercayaan:
     *   1. `REMOTE_ADDR` (diisi server) bila publik;
     *   2. entri **TERAKHIR** yang publik dari `X-Forwarded-For` (nilai yang ditambahkan proxy) —
     *      dipakai bila aplikasi berada di belakang proxy lokal (REMOTE_ADDR privat);
     *   3. alamat privat/terpesan apa pun, lalu `REMOTE_ADDR`.
     */
    function log_ip(): string
    {
        $remote = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
        if ($remote !== '' && filter_var($remote, FILTER_VALIDATE_IP) !== false && !ip_privat($remote)) {
            return substr($remote, 0, 45);
        }
        $fwd = (string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
        if ($fwd !== '') {
            $daftar = [];
            foreach (explode(',', $fwd) as $bagian) {
                $ip = trim($bagian);
                if (filter_var($ip, FILTER_VALIDATE_IP) !== false) {
                    $daftar[] = $ip;
                }
            }
            for ($i = count($daftar) - 1; $i >= 0; $i--) {   // dari KANAN (yang ditambahkan proxy)
                if (!ip_privat($daftar[$i])) {
                    return substr($daftar[$i], 0, 45);
                }
            }
            if ($daftar) {
                return substr($daftar[count($daftar) - 1], 0, 45);
            }
        }
        return $remote === '' ? '-' : substr($remote, 0, 45);
    }
}

if (!function_exists('log_peramban')) {
    /** Nama peramban + sistem operasi dari User-Agent (ringkas, ditampilkan apa adanya). */
    function log_peramban(?string $ua = null): string
    {
        $ua = trim($ua === null ? (string)($_SERVER['HTTP_USER_AGENT'] ?? '') : $ua);
        if ($ua === '') {
            return 'Tidak dikenal';
        }
        // Urutan penting: Edge & Opera memuat kata "Chrome", jadi diperiksa lebih dulu.
        $nama = 'Peramban lain';
        foreach ([
            ['/Edg(?:e|A|iOS)?\/([\d.]+)/i', 'Edge'],
            ['/OPR\/([\d.]+)/i', 'Opera'],
            ['/SamsungBrowser\/([\d.]+)/i', 'Samsung Internet'],
            ['/Firefox\/([\d.]+)/i', 'Firefox'],
            ['/CriOS\/([\d.]+)/i', 'Chrome'],
            ['/Chrome\/([\d.]+)/i', 'Chrome'],
            ['/Version\/([\d.]+).*Safari/i', 'Safari'],
            ['/Safari\/([\d.]+)/i', 'Safari'],
        ] as $pola) {
            if (preg_match($pola[0], $ua, $m) === 1) {
                $versi = (string)($m[1] ?? '');
                $utama = $versi !== '' ? explode('.', $versi)[0] : '';
                $nama = $pola[1] . ($utama !== '' ? ' ' . $utama : '');
                break;
            }
        }
        $sistem = '';
        if (stripos($ua, 'Android') !== false) {
            $sistem = 'Android';
        } elseif (preg_match('/iPhone|iPad|iPod/i', $ua) === 1) {
            $sistem = 'iPhone/iPad';
        } elseif (stripos($ua, 'Windows') !== false) {
            $sistem = 'Windows';
        } elseif (stripos($ua, 'Mac OS X') !== false || stripos($ua, 'Macintosh') !== false) {
            $sistem = 'macOS';
        } elseif (stripos($ua, 'CrOS') !== false) {
            $sistem = 'ChromeOS';
        } elseif (stripos($ua, 'Linux') !== false) {
            $sistem = 'Linux';
        }
        $hp = preg_match('/Mobile|Android|iPhone|iPad|iPod/i', $ua) === 1 ? 'HP' : 'Komputer';
        return $nama . ($sistem !== '' ? ' · ' . $sistem : '') . ' · ' . $hp;
    }
}

if (!function_exists('log_sesi_penanda')) {
    /** Penanda sesi (hash) — id sesi mentah tidak pernah disimpan di basis data. */
    function log_sesi_penanda(): string
    {
        return substr(hash('sha256', (string)session_id()), 0, 32);
    }
}

if (!function_exists('log_waktu_teks')) {
    /**
     * Tanggal & jam sebagai TEKS BIASA (tanpa entitas HTML), mis. "25 November 2026, 14:32:07".
     * WAJIB dipakai bila hasilnya masuk ke `e()` atau ke NILAI ATRIBUT (mis. `data-confirm`).
     */
    function log_waktu_teks(?string $dt): string
    {
        $dt = trim((string)$dt);
        if ($dt === '') {
            return '-';
        }
        $ts = strtotime($dt);
        if ($ts === false) {
            return $dt;
        }
        return tanggal_id(date('Y-m-d', $ts)) . ', ' . date('H:i:s', $ts);
    }
}

if (!function_exists('log_aktivitas_catat')) {
    /**
     * Catat satu aktivitas ke `log_aktivitas`.
     *
     * @param string     $aktivitas  'masuk' | 'keluar' | 'gagal'
     * @param array|null $user       baris users (boleh dari current_user())
     * @param string     $namaManual nama yang diketik saat masuk (untuk percobaan gagal)
     * @param string     $keterangan alasan singkat (mis. "kata sandi salah")
     */
    function log_aktivitas_catat(string $aktivitas, ?array $user = null, string $namaManual = '',
                                 string $keterangan = ''): void
    {
        try {
            $uid = $user ? (int)($user['id'] ?? 0) : 0;
            $nama = $user ? trim((string)($user['nama'] ?? '')) : '';
            if ($nama === '') {
                $nama = $namaManual !== '' ? $namaManual : '(nama tidak dikenal)';
            }
            $username = $user ? trim((string)($user['username'] ?? '')) : $namaManual;
            $role = $user ? (string)($user['role'] ?? '') : '';
            $sid = $user ? (int)($user['sekolah_id'] ?? 0) : 0;
            $sekolah = '';
            if ($sid > 0) {
                $sekolah = trim((string)($user['sekolah_nama'] ?? ''));
                if ($sekolah === '') {
                    $sekolah = (string)(val('SELECT nama FROM sekolah WHERE id = ?', [$sid]) ?? '');
                }
            }
            q('INSERT INTO log_aktivitas (user_id, nama, username, role, sekolah_id, sekolah, aktivitas,
                    keterangan, ip, peramban, waktu)
               VALUES (?,?,?,?,?,?,?,?,?,?,?)', [
                $uid > 0 ? $uid : null,
                $nama,
                $username !== '' ? $username : null,
                $role !== '' ? $role : null,
                $sid > 0 ? $sid : null,
                $sekolah !== '' ? $sekolah : null,
                $aktivitas,
                $keterangan !== '' ? $keterangan : null,
                log_ip(),
                log_peramban(),
                date('Y-m-d H:i:s'),
            ]);
        } catch (Throwable $e) {
            // Diabaikan dengan sengaja: kegagalan mencatat log tidak boleh menghalangi pengguna.
        }
    }
}

if (!function_exists('log_sesi_mulai')) {
    /** Mulai sesi: daftarkan sebagai pengguna online + catat aktivitas "masuk". */
    function log_sesi_mulai(array $u): void
    {
        $_SESSION['__log_ping'] = time();
        try {
            $sid = log_sesi_penanda();
            $now = date('Y-m-d H:i:s');
            q('DELETE FROM sesi_aktif WHERE id = ?', [$sid]);
            q('INSERT INTO sesi_aktif (id, user_id, ip, peramban, mulai, terakhir) VALUES (?,?,?,?,?,?)',
                [$sid, (int)$u['id'], log_ip(), log_peramban(), $now, $now]);
        } catch (Throwable $e) {
        }
        log_aktivitas_catat('masuk', $u);
    }
}

if (!function_exists('log_sesi_ping')) {
    /**
     * Perbarui waktu aktif sesi (dipanggil dari `require_login()`).
     * Ditulis paling sering sekali per 60 detik agar tidak membebani basis data.
     */
    function log_sesi_ping(): void
    {
        $u = current_user();
        if (!$u) {
            return;
        }
        $kini = time();
        if ($kini - (int)($_SESSION['__log_ping'] ?? 0) < 60) {
            return;
        }
        $_SESSION['__log_ping'] = $kini;
        try {
            $sid = log_sesi_penanda();
            $now = date('Y-m-d H:i:s');
            $ip = log_ip();
            $pb = log_peramban();
            q('INSERT OR IGNORE INTO sesi_aktif (id, user_id, ip, peramban, mulai, terakhir) VALUES (?,?,?,?,?,?)',
                [$sid, (int)$u['id'], $ip, $pb, $now, $now]);
            q('UPDATE sesi_aktif SET user_id = ?, terakhir = ?, ip = ?, peramban = ? WHERE id = ?',
                [(int)$u['id'], $now, $ip, $pb, $sid]);
        } catch (Throwable $e) {
        }
    }
}

if (!function_exists('log_sesi_selesai')) {
    /** Akhiri sesi: catat aktivitas "keluar" + hilangkan dari daftar online. */
    function log_sesi_selesai(?array $u): void
    {
        try {
            if ($u) {
                log_aktivitas_catat('keluar', $u);
            }
            q('DELETE FROM sesi_aktif WHERE id = ?', [log_sesi_penanda()]);
        } catch (Throwable $e) {
        }
    }
}

if (!function_exists('log_batasi_gagal')) {
    /**
     * Batasi riwayat percobaan masuk yang GAGAL (halaman masuk publik, bisa dipicu siapa saja).
     * Hanya `$maks` baris terbaru yang disimpan; riwayat masuk/keluar tidak tersentuh.
     */
    function log_batasi_gagal(int $maks = 2000): void
    {
        try {
            $jml = (int)(val('SELECT COUNT(*) FROM log_aktivitas WHERE aktivitas = ?', ['gagal']) ?? 0);
            if ($jml > $maks) {
                q('DELETE FROM log_aktivitas WHERE aktivitas = ? AND id NOT IN (
                       SELECT id FROM log_aktivitas WHERE aktivitas = ? ORDER BY id DESC LIMIT ?)',
                    ['gagal', 'gagal', $maks]);
            }
        } catch (Throwable $e) {
        }
    }
}

if (!function_exists('log_gagal')) {
    /** Catat percobaan masuk yang gagal + jaga jumlah barisnya. */
    function log_gagal(?array $user, string $namaManual, string $keterangan): void
    {
        log_aktivitas_catat('gagal', $user, $namaManual, $keterangan);
        log_batasi_gagal();
    }
}

if (!function_exists('log_sesi_bersihkan')) {
    /** Buang catatan sesi yang sudah lama tidak aktif (bukan penghapusan riwayat). */
    function log_sesi_bersihkan(int $hari = 1): int
    {
        try {
            $st = q('DELETE FROM sesi_aktif WHERE terakhir < ?',
                [date('Y-m-d H:i:s', time() - max(1, $hari) * 86400)]);
            return $st->rowCount();
        } catch (Throwable $e) {
            return 0;
        }
    }
}

if (!function_exists('log_online_syarat')) {
    /**
     * Syarat SQL sebuah sesi dianggap "sedang online".
     *
     * Hanya sesi dari akun yang MASIH aktif dan madrasahnya aktif + disetujui — akun/madrasah yang
     * dinonaktifkan tidak lagi dihitung online walau sesinya belum sempat ditutup. Akun Super Admin
     * (`sekolah_id` NULL) selalu boleh.
     */
    function log_online_syarat(string $alias = 'sx', string $aliasUser = 'u'): string
    {
        return $alias . '.terakhir >= ? AND ' . $aliasUser . '.aktif = 1
            AND (' . $aliasUser . '.sekolah_id IS NULL OR EXISTS (
                    SELECT 1 FROM sekolah sk WHERE sk.id = ' . $aliasUser . '.sekolah_id
                      AND sk.aktif = 1 AND sk.daftar_status = "disetujui"))';
    }
}

if (!function_exists('log_online')) {
    /** Pengguna yang dianggap MASIH online (aktif dalam LOG_MENIT_ONLINE menit terakhir). */
    function log_online(int $menit = LOG_MENIT_ONLINE): array
    {
        $batas = date('Y-m-d H:i:s', time() - max(1, $menit) * 60);
        try {
            return all('SELECT sx.id AS sesi, sx.ip, sx.peramban, sx.mulai, sx.terakhir,
                               u.id AS user_id, u.nama, u.username, u.role, u.sekolah_id, u.jabatan,
                               s.nama AS sekolah_nama
                          FROM sesi_aktif sx
                          JOIN users u ON u.id = sx.user_id
                          LEFT JOIN sekolah s ON s.id = u.sekolah_id
                         WHERE ' . log_online_syarat() . '
                         ORDER BY sx.terakhir DESC, u.nama', [$batas]);
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('log_jumlah_online')) {
    /** Berapa pengguna yang sedang online (dipakai lencana pada menu sidebar). */
    function log_jumlah_online(int $menit = LOG_MENIT_ONLINE): int
    {
        try {
            $batas = date('Y-m-d H:i:s', time() - max(1, $menit) * 60);
            return (int)(val('SELECT COUNT(*) FROM sesi_aktif sx JOIN users u ON u.id = sx.user_id
                              WHERE ' . log_online_syarat(), [$batas]) ?? 0);
        } catch (Throwable $e) {
            return 0;
        }
    }
}

if (!function_exists('log_sekolah_label')) {
    /** Ringkasan asal madrasah seorang pengguna pada daftar log. */
    function log_sekolah_label(?string $role, ?string $sekolah): string
    {
        $sekolah = trim((string)$sekolah);
        if ($sekolah !== '') {
            return $sekolah;
        }
        return in_array((string)$role, ['super', 'pengawas'], true) ? 'Lintas madrasah' : '-';
    }
}

if (!function_exists('log_role_label')) {
    /** Label peran untuk baris log (memakai jabatan bila tersedia — pola `role_label()`). */
    function log_role_label(?string $role, ?string $jabatan = ''): string
    {
        $role = (string)$role;
        if ($role === 'pengawas') {
            $jab = trim((string)$jabatan);
            return $jab === '' ? 'Penilai (Pengawas)' : 'Penilai — ' . $jab;
        }
        return [
            'super' => 'Super Admin',
            'admin' => 'Kepala Madrasah',
        ][$role] ?? ($role !== '' ? $role : '-');
    }
}
