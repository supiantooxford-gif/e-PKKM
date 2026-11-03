<?php
/**
 * e-PKKM - PENDAFTARAN MADRASAH (permintaan pemilik).
 *
 * Madrasah mendaftar SENDIRI lewat halaman publik `daftar.php` dengan mengisi identitas lembaga
 * + akun kepala madrasah. Pendaftaran berstatus **menunggu** dan **belum dapat dipakai masuk**
 * sampai Super Admin menyetujui/mengaktifkannya di halaman `sekolah.php`.
 *
 * Status disimpan pada kolom `sekolah.daftar_status`:
 *   'menunggu'  - baru mendaftar, menunggu persetujuan Super Admin (lembaga belum aktif)
 *   'disetujui' - sudah disetujui/diaktifkan (boleh masuk)
 *   'ditolak'   - ditolak Super Admin beserta alasannya
 * Madrasah LAMA (sebelum fitur ini ada) bernilai 'disetujui' sehingga tidak ada yang terkunci.
 *
 * Catatan: akun kepala madrasah yang mendaftar dibuat dengan `aktif = 1`, tetapi `sekolah.aktif = 0`
 * sehingga login TETAP ditolak (pesannya khusus: "menunggu persetujuan"), dan setelah disetujui
 * Super Admin lembaga menjadi aktif sehingga akun itu langsung bisa dipakai masuk.
 */

// ------------------------------------------------------------------ status --
if (!function_exists('daftar_status_list')) {
    /** Daftar status pendaftaran + labelnya. */
    function daftar_status_list(): array
    {
        return [
            'menunggu' => 'Menunggu Persetujuan',
            'disetujui' => 'Disetujui',
            'ditolak' => 'Ditolak',
        ];
    }
}

if (!function_exists('daftar_status_sah')) {
    /** Hanya menerima status yang dikenal (selain itu 'menunggu'). */
    function daftar_status_sah(string $status): string
    {
        $s = strtolower(trim($status));
        return array_key_exists($s, daftar_status_list()) ? $s : 'menunggu';
    }
}

if (!function_exists('daftar_status_sekolah')) {
    /** Status pendaftaran sebuah baris madrasah ('disetujui' bila kolomnya kosong). */
    function daftar_status_sekolah(?array $s): string
    {
        $v = trim((string)($s['daftar_status'] ?? ''));
        return $v === '' ? 'disetujui' : daftar_status_sah($v);
    }
}

if (!function_exists('daftar_pil')) {
    /** Pil status pendaftaran (HTML) untuk ditampilkan pada daftar. */
    function daftar_pil(?array $s): string
    {
        $status = daftar_status_sekolah($s);
        $kelas = $status === 'disetujui' ? 'pil-unggul' : ($status === 'ditolak' ? 'pil-kurang' : 'pil-cukup');
        return '<span class="pil ' . $kelas . '">' . e(daftar_status_list()[$status]) . '</span>';
    }
}

// ------------------------------------------------------------------ daftar --
if (!function_exists('daftar_pendaftar_list')) {
    /**
     * Daftar madrasah menurut status pendaftaran (urut pendaftaran terbaru lebih dulu).
     * Status '' / 'semua' = seluruh pendaftar.
     */
    function daftar_pendaftar_list(string $status = 'menunggu'): array
    {
        try {
            if ($status === '' || $status === 'semua') {
                return all('SELECT * FROM sekolah ORDER BY daftar_pada DESC, id DESC');
            }
            return all('SELECT * FROM sekolah WHERE daftar_status = ? ORDER BY daftar_pada DESC, id DESC',
                [daftar_status_sah($status)]);
        } catch (Throwable $t) {
            return [];
        }
    }
}

if (!function_exists('daftar_menunggu_jumlah')) {
    /** Jumlah pendaftar yang belum ditinjau (dipakai lencana menu sidebar). */
    function daftar_menunggu_jumlah(): int
    {
        try {
            return (int)val("SELECT COUNT(*) FROM sekolah WHERE daftar_status = 'menunggu'");
        } catch (Throwable $t) {
            return 0;
        }
    }
}

if (!function_exists('daftar_akun_kepala')) {
    /** Akun kepala madrasah (peran admin) milik sebuah madrasah pendaftar. */
    function daftar_akun_kepala(int $sekolahId): ?array
    {
        if ($sekolahId <= 0) {
            return null;
        }
        return one("SELECT * FROM users WHERE sekolah_id = ? AND role = 'admin' ORDER BY id LIMIT 1", [$sekolahId]);
    }
}

// ------------------------------------------------------------------- aksi --
if (!function_exists('daftar_setujui')) {
    /**
     * Menyetujui / mengaktifkan pendaftaran: lembaga menjadi AKTIF dan statusnya 'disetujui',
     * sehingga akun kepala madrasah langsung bisa masuk. Alasan penolakan lama dibersihkan.
     */
    function daftar_setujui(int $sekolahId): bool
    {
        if ($sekolahId <= 0) {
            return false;
        }
        $s = one('SELECT * FROM sekolah WHERE id = ?', [$sekolahId]);
        if (!$s) {
            return false;
        }
        mulai_transaksi();
        try {
            // nilai teks selalu lewat parameter terikat (JANGAN menulis 'disetujui' sebagai literal SQL)
            q('UPDATE sekolah SET daftar_status = ?, aktif = 1, daftar_alasan = NULL, diubah = ? WHERE id = ?',
                ['disetujui', date('Y-m-d H:i:s'), $sekolahId]);
            selesai_transaksi(true);
            return true;
        } catch (Throwable $t) {
            selesai_transaksi(false);
            return false;
        }
    }
}

if (!function_exists('daftar_tolak')) {
    /** Menolak pendaftaran beserta alasannya (lembaga tetap nonaktif, akun tidak dapat masuk). */
    function daftar_tolak(int $sekolahId, string $alasan): bool
    {
        if ($sekolahId <= 0) {
            return false;
        }
        $alasan = bersih_teks($alasan, 500);
        mulai_transaksi();
        try {
            q("UPDATE sekolah SET daftar_status = ?, aktif = 0, daftar_alasan = ?, diubah = ? WHERE id = ?",
                ['ditolak', $alasan, date('Y-m-d H:i:s'), $sekolahId]);
            selesai_transaksi(true);
            return true;
        } catch (Throwable $t) {
            selesai_transaksi(false);
            return false;
        }
    }
}

if (!function_exists('daftar_nonaktifkan')) {
    /** Menonaktifkan lembaga yang sudah disetujui (tidak mengubah status pendaftaran). */
    function daftar_nonaktifkan(int $sekolahId): bool
    {
        if ($sekolahId <= 0) {
            return false;
        }
        q('UPDATE sekolah SET aktif = 0, diubah = ? WHERE id = ?', [date('Y-m-d H:i:s'), $sekolahId]);
        return true;
    }
}

if (!function_exists('daftar_pesan_masuk')) {
    /** Pesan yang ditampilkan pada halaman masuk ketika lembaga pendaftar belum disetujui/ditolak. */
    function daftar_pesan_masuk(?array $s): string
    {
        if (!$s) {
            return '';
        }
        $status = daftar_status_sekolah($s);
        if ($status === 'menunggu') {
            return 'Pendaftaran madrasah Anda masih MENUNGGU PERSETUJUAN Admin aplikasi. '
                . 'Akun kepala madrasah dapat dipakai masuk setelah pendaftaran disetujui.';
        }
        if ($status === 'ditolak') {
            $alasan = trim((string)($s['daftar_alasan'] ?? ''));
            return 'Pendaftaran madrasah Anda DITOLAK Admin aplikasi.'
                . ($alasan !== '' ? ' Alasan: ' . $alasan : '') . ' Hubungi kontak di bawah untuk keterangan.';
        }
        return '';
    }
}

// --------------------------------------------------------------- formulir ---
if (!function_exists('daftar_medan_lembaga')) {
    /** Medan identitas lembaga yang diisi pendaftar: nama kolom => label. */
    function daftar_medan_lembaga(): array
    {
        return [
            'nama' => 'Nama Madrasah',
            // Status lembaga (permintaan pemilik: pilihan di SEBELAH NAMA madrasah) — menentukan
            // susunan 7 penilai Tahun Keempat (negeri: Kabid…, swasta: Yayasan…).
            'status_lembaga' => 'Status Lembaga',
            'npsn' => 'NPSN',
            'nsm' => 'NSM',
            'alamat' => 'Alamat',
            'desa' => 'Desa/Kelurahan',
            'kecamatan' => 'Kecamatan',
            'kabupaten' => 'Kabupaten/Kota',
            'provinsi' => 'Provinsi',
            'telepon' => 'Telp/HP',
            'email' => 'Email',
            'website' => 'Situs/Web',
            'kepala_nama' => 'Nama Kepala Madrasah',
            'kepala_nip' => 'NIP Kepala Madrasah',
            'tempat' => 'Tempat Penilaian',
        ];
    }
}

if (!function_exists('daftar_batas_sesi')) {
    /** Batas jumlah pendaftaran per sesi (anti-spam halaman publik). */
    function daftar_batas_sesi(): int
    {
        return 3;
    }
}

if (!function_exists('daftar_captcha_baru')) {
    /**
     * Buat soal pengaman (penjumlahan) baru bila belum ada. Mengembalikan ['a' => int, 'b' => int].
     */
    function daftar_captcha_baru(): array
    {
        mulai_sesi();
        if (empty($_SESSION['daftar_captcha']) || !is_array($_SESSION['daftar_captcha'])) {
            $_SESSION['daftar_captcha'] = ['a' => random_int(2, 9), 'b' => random_int(2, 9)];
        }
        return $_SESSION['daftar_captcha'];
    }
}

if (!function_exists('daftar_captcha_ganti')) {
    /** Ganti soal pengaman setelah percobaan dikirim. */
    function daftar_captcha_ganti(): array
    {
        mulai_sesi();
        $_SESSION['daftar_captcha'] = ['a' => random_int(2, 9), 'b' => random_int(2, 9)];
        return $_SESSION['daftar_captcha'];
    }
}

if (!function_exists('daftar_username_sah')) {
    /** Nama pengguna: 4-40 karakter, hanya huruf/angka/titik/garis bawah/garis hubung. */
    function daftar_username_sah(string $u): bool
    {
        return (bool)preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{3,39}$/', $u);
    }
}

if (!function_exists('daftar_simpan')) {
    /**
     * Simpan pendaftaran baru (lembaga + akun kepala madrasah) dalam SATU transaksi.
     *
     * @param array $isi   medan identitas lembaga (sudah dibersihkan)
     * @param array $akun  ['nama' => ..., 'username' => ..., 'sandi' => ...]
     * @return array ['ok' => bool, 'id' => int, 'pesan' => string]
     */
    function daftar_simpan(array $isi, array $akun): array
    {
        $nama = trim((string)($isi['nama'] ?? ''));
        if ($nama === '') {
            return ['ok' => false, 'id' => 0, 'pesan' => 'Nama madrasah wajib diisi.'];
        }
        $username = trim((string)($akun['username'] ?? ''));
        $sandi = (string)($akun['sandi'] ?? '');
        $namaAkun = bersih_teks((string)($akun['nama'] ?? ''), PKKM_TEKS_PENDEK);
        if ($namaAkun === '') {
            $namaAkun = $nama;
        }
        if (!daftar_username_sah($username)) {
            return ['ok' => false, 'id' => 0,
                'pesan' => 'Nama pengguna 4-40 karakter: huruf, angka, titik, garis bawah, atau garis hubung.'];
        }
        if (strlen($sandi) < 6) {
            return ['ok' => false, 'id' => 0, 'pesan' => 'Kata sandi minimal 6 karakter.'];
        }
        if (val('SELECT id FROM users WHERE LOWER(username) = LOWER(?)', [$username])) {
            return ['ok' => false, 'id' => 0,
                'pesan' => 'Nama pengguna "' . $username . '" sudah dipakai. Silakan pilih nama pengguna lain.'];
        }
        if (val('SELECT id FROM sekolah WHERE LOWER(TRIM(nama)) = LOWER(TRIM(?))', [$nama])) {
            return ['ok' => false, 'id' => 0,
                'pesan' => 'Madrasah dengan nama itu sudah terdaftar. Hubungi Admin aplikasi bila ini madrasah Anda.'];
        }

        $medan = daftar_medan_lembaga();
        $nilai = [];
        foreach ($medan as $k => $label) {
            $nilai[$k] = bersih_teks((string)($isi[$k] ?? ''), PKKM_TEKS_PENDEK);
        }
        $nilai['nama'] = $nama;
        $nilai['status_lembaga'] = status_lembaga_sah($isi['status_lembaga'] ?? '');
        $nilai['aktif'] = 0;                     // belum boleh dipakai masuk
        $nilai['daftar_status'] = 'menunggu';    // menunggu persetujuan Super Admin
        $nilai['daftar_pada'] = date('Y-m-d H:i:s');
        $nilai['dibuat'] = date('Y-m-d H:i:s');
        $kolom = array_keys($nilai);

        mulai_transaksi();
        try {
            q('INSERT INTO sekolah (' . implode(', ', $kolom) . ') VALUES ('
                . implode(', ', array_fill(0, count($kolom), '?')) . ')', array_values($nilai));
            $sid = (int)db()->lastInsertId();
            q("INSERT INTO users (sekolah_id, nama, username, sandi, role, aktif, dibuat)
               VALUES (?, ?, ?, ?, 'admin', 1, ?)",
                [$sid, $namaAkun, $username, password_hash($sandi, PASSWORD_DEFAULT), date('Y-m-d H:i:s')]);
            selesai_transaksi(true);
            return ['ok' => true, 'id' => $sid, 'pesan' => ''];
        } catch (Throwable $t) {
            selesai_transaksi(false);
            return ['ok' => false, 'id' => 0,
                'pesan' => 'Pendaftaran gagal disimpan. Silakan coba lagi.'];
        }
    }
}
