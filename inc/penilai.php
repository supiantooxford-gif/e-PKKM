<?php
/**
 * e-PKKM - modul PENILAI (permintaan pemilik).
 *
 * Isi modul ini:
 *  1. daftar JABATAN penilai (global, diisi Super Admin) + pengelolaannya;
 *  2. pendaftaran PENILAI secara mandiri (halaman publik `daftar_penilai.php`) yang membuat akun
 *     berperan `pengawas` (status tetap pengawas) lengkap dengan jabatan, NIP, dan instansi;
 *  3. pemilihan madrasah oleh penilai beserta posisinya:
 *       - jabatan Pengawas        -> posisi 1/2 (Penilai 1 / Penilai 2),
 *       - jabatan lain (v1.7.0)   -> POSISI TAMBAHAN (>= 3) untuk Tahun Keempat, dan madrasah hanya
 *                                   boleh dipilih bila Tahun Pertama s.d. Ketiga sudah lengkap
 *                                   seluruh butirnya. Satu madrasah boleh punya lebih dari dua penilai;
 *  4. penyelarasan data `penilai` (yang dipakai lembar cetak & kop laporan) dengan akun penilai,
 *     sehingga nama penilai yang tercetak selalu nama akun yang benar-benar ditugaskan.
 *
 * Catatan istilah: pada halaman penilai dipakai "Penilai 1 / Penilai 2" (PKKM_PENILAI_POSISI),
 * sedangkan dokumen cetak tetap memakai istilah resmi "Pengawas I / II" (PKKM_PENILAI_PENGAWAS).
 */

// ============================================================================
// 1. DAFTAR JABATAN PENILAI
// ============================================================================

if (!function_exists('jabatan_penilai_list')) {
    /**
     * Daftar jabatan penilai (urut urutan, id).
     * @param bool $aktifSaja true = hanya yang diaktifkan Super Admin (dipakai formulir pendaftaran).
     */
    function jabatan_penilai_list(bool $aktifSaja = true): array
    {
        try {
            $sql = 'SELECT * FROM jabatan_penilai' . ($aktifSaja ? ' WHERE aktif = 1' : '')
                . ' ORDER BY urutan, id';
            return all($sql);
        } catch (Throwable $t) {
            return [];
        }
    }
}

if (!function_exists('jabatan_penilai_by_id')) {
    function jabatan_penilai_by_id(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        try {
            return one('SELECT * FROM jabatan_penilai WHERE id = ?', [$id]);
        } catch (Throwable $t) {
            return null;
        }
    }
}

if (!function_exists('jabatan_penilai_by_nama')) {
    function jabatan_penilai_by_nama(string $nama): ?array
    {
        $nama = trim($nama);
        if ($nama === '') {
            return null;
        }
        try {
            return one('SELECT * FROM jabatan_penilai WHERE LOWER(TRIM(nama)) = LOWER(?)', [$nama]);
        } catch (Throwable $t) {
            return null;
        }
    }
}

if (!function_exists('jabatan_penilai_sah')) {
    /** Apakah nama jabatan ada pada daftar AKTIF (dipakai validasi pendaftaran penilai). */
    function jabatan_penilai_sah(string $nama): bool
    {
        $j = jabatan_penilai_by_nama($nama);
        return $j !== null && (int)$j['aktif'] === 1;
    }
}

if (!function_exists('jabatan_penilai_urut_baru')) {
    /** Urutan untuk jabatan baru: paling akhir (kelipatan 10). */
    function jabatan_penilai_urut_baru(): int
    {
        $maks = (int)(val('SELECT COALESCE(MAX(urutan), 0) FROM jabatan_penilai') ?? 0);
        return $maks + 10;
    }
}

if (!function_exists('jabatan_penilai_geser')) {
    /** Tukar urutan dengan tetangganya (arah -1 naik, +1 turun). */
    function jabatan_penilai_geser(int $id, int $arah): bool
    {
        $j = jabatan_penilai_by_id($id);
        if (!$j) {
            return false;
        }
        $tetangga = $arah < 0
            ? one('SELECT * FROM jabatan_penilai WHERE urutan < ? OR (urutan = ? AND id < ?)
                    ORDER BY urutan DESC, id DESC LIMIT 1', [(int)$j['urutan'], (int)$j['urutan'], $id])
            : one('SELECT * FROM jabatan_penilai WHERE urutan > ? OR (urutan = ? AND id > ?)
                    ORDER BY urutan ASC, id ASC LIMIT 1', [(int)$j['urutan'], (int)$j['urutan'], $id]);
        if (!$tetangga) {
            return false;
        }
        mulai_transaksi();
        try {
            // pakai urutan sementara supaya UNIQUE (bila ada) tetap aman
            q('UPDATE jabatan_penilai SET urutan = ? WHERE id = ?', [-1, $id]);
            q('UPDATE jabatan_penilai SET urutan = ? WHERE id = ?', [(int)$j['urutan'], (int)$tetangga['id']]);
            q('UPDATE jabatan_penilai SET urutan = ? WHERE id = ?', [(int)$tetangga['urutan'], $id]);
            selesai_transaksi(true);
            return true;
        } catch (Throwable $t) {
            selesai_transaksi(false);
            return false;
        }
    }
}

if (!function_exists('jabatan_penilai_pemakai')) {
    /** Berapa akun yang memakai jabatan itu (dipakai sebelum menghapus jabatan). */
    function jabatan_penilai_pemakai(string $nama): int
    {
        $nama = trim($nama);
        if ($nama === '') {
            return 0;
        }
        try {
            return (int)val('SELECT COUNT(*) FROM users WHERE LOWER(TRIM(jabatan)) = LOWER(?)', [$nama]);
        } catch (Throwable $t) {
            return 0;
        }
    }
}

// ============================================================================
// 2. PENDAFTARAN PENILAI
// ============================================================================

if (!function_exists('jabatan_pengawas_nama')) {
    /**
     * Apakah NAMA JABATAN termasuk jabatan pengawas (diawali "pengawas")?
     *
     * Sejak v1.11.0 (permintaan pemilik) jabatan pengawas TIDAK mendaftar sendiri di
     * `daftar_penilai.php` — akun pengawas ditarik Super Admin dari aplikasi Supervisi.
     */
    function jabatan_pengawas_nama(string $nama): bool
    {
        return str_starts_with(strtolower(trim($nama)), 'pengawas');
    }
}

if (!function_exists('jabatan_penilai_daftar_list')) {
    /** Jabatan yang boleh dipilih pada halaman PENDAFTARAN penilai (semua kecuali pengawas). */
    function jabatan_penilai_daftar_list(): array
    {
        return array_values(array_filter(
            jabatan_penilai_list(true),
            fn($j) => !jabatan_pengawas_nama((string)$j['nama'])
        ));
    }
}

if (!function_exists('penilai_medan_daftar')) {
    /** Medan pendaftaran penilai: nama field => label tampilan. */
    function penilai_medan_daftar(): array
    {
        return [
            'nama' => 'Nama Lengkap (beserta gelar)',
            'nip' => 'NIP',
            'jabatan' => 'Jabatan',
            'instansi' => 'Unit Kerja / Instansi',
            'kab_kota' => 'Kabupaten/Kota',
            'hp' => 'No. HP / WhatsApp',
        ];
    }
}

if (!function_exists('penilai_batas_sesi')) {
    /** Batas jumlah pendaftaran penilai per sesi (anti-spam halaman publik). */
    function penilai_batas_sesi(): int
    {
        return 3;
    }
}

if (!function_exists('penilai_captcha_baru')) {
    /** Soal pengaman pendaftaran penilai (terpisah dari pendaftaran madrasah). */
    function penilai_captcha_baru(): array
    {
        mulai_sesi();
        if (empty($_SESSION['penilai_captcha']) || !is_array($_SESSION['penilai_captcha'])) {
            $_SESSION['penilai_captcha'] = ['a' => random_int(2, 9), 'b' => random_int(2, 9)];
        }
        return $_SESSION['penilai_captcha'];
    }
}

if (!function_exists('penilai_captcha_ganti')) {
    function penilai_captcha_ganti(): array
    {
        mulai_sesi();
        $_SESSION['penilai_captcha'] = ['a' => random_int(2, 9), 'b' => random_int(2, 9)];
        return $_SESSION['penilai_captcha'];
    }
}

if (!function_exists('penilai_simpan_daftar')) {
    /**
     * Simpan pendaftaran penilai (akun berperan `pengawas`, langsung aktif).
     *
     * @param array $isi  medan profil penilai (nama, nip, jabatan, instansi, kab_kota, hp)
     * @param array $akun ['username' => ..., 'sandi' => ...]
     * @return array ['ok' => bool, 'id' => int, 'pesan' => string]
     */
    function penilai_simpan_daftar(array $isi, array $akun): array
    {
        $nama = bersih_teks((string)($isi['nama'] ?? ''), PKKM_TEKS_PENDEK);
        $jabatan = trim((string)($isi['jabatan'] ?? ''));
        $username = trim((string)($akun['username'] ?? ''));
        $sandi = (string)($akun['sandi'] ?? '');

        if ($nama === '') {
            return ['ok' => false, 'id' => 0, 'pesan' => 'Nama lengkap wajib diisi.'];
        }
        if ($jabatan === '' || !jabatan_penilai_sah($jabatan)) {
            return ['ok' => false, 'id' => 0,
                'pesan' => 'Jabatan wajib dipilih dari daftar yang tersedia.'];
        }
        if (jabatan_pengawas_nama($jabatan)) {
            // Permintaan pemilik v1.11.0: hanya jabatan SELAIN pengawas yang mendaftar sendiri;
            // akun pengawas + penugasannya ditarik Super Admin dari aplikasi Supervisi.
            return ['ok' => false, 'id' => 0,
                'pesan' => 'Jabatan pengawas tidak mendaftar di sini — akun pengawas ditarik Super Admin '
                    . 'dari aplikasi Supervisi. Silakan pilih jabatan lain atau hubungi Super Admin aplikasi.'];
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

        $kolom = [
            'nama' => $nama,
            'nip' => bersih_teks((string)($isi['nip'] ?? ''), PKKM_TEKS_PENDEK),
            'jabatan' => $jabatan,
            'instansi' => bersih_teks((string)($isi['instansi'] ?? ''), PKKM_TEKS_PENDEK),
            'kab_kota' => bersih_teks((string)($isi['kab_kota'] ?? ''), PKKM_TEKS_PENDEK),
            'hp' => bersih_teks((string)($isi['hp'] ?? ''), PKKM_TEKS_PENDEK),
        ];

        mulai_transaksi();
        try {
            q('INSERT INTO users (sekolah_id, nama, username, sandi, role, aktif, nip, jabatan, instansi, kab_kota, hp, asal, dibuat)
               VALUES (NULL, ?, ?, ?, ?, 1, ?, ?, ?, ?, ?, ?, ?)', [
                $kolom['nama'], $username, password_hash($sandi, PASSWORD_DEFAULT), 'pengawas',
                $kolom['nip'], $kolom['jabatan'], $kolom['instansi'], $kolom['kab_kota'], $kolom['hp'],
                'pendaftaran', date('Y-m-d H:i:s'),
            ]);
            $id = (int)db()->lastInsertId();
            selesai_transaksi(true);
            return ['ok' => true, 'id' => $id, 'pesan' => ''];
        } catch (Throwable $t) {
            selesai_transaksi(false);
            return ['ok' => false, 'id' => 0, 'pesan' => 'Pendaftaran gagal disimpan. Silakan coba lagi.'];
        }
    }
}

// ============================================================================
// 3. POSISI, PENUGASAN, DAN PENYELARASAN DATA PENILAI
// ============================================================================

if (!function_exists('penilai_posisi_teks')) {
    /**
     * Teks posisi: "Penilai 1 (Ketua Tim)" / "Penilai 2 (Anggota Tim)" / penilai tambahan.
     * Posisi >= 3 = penilai TAMBAHAN Tahun Keempat (permintaan pemilik: Tahun Keempat boleh
     * dinilai lebih dari dua penilai, di luar Pengawas I & II).
     */
    function penilai_posisi_teks($kode, ?string $statusLembaga = null): string
    {
        $kode = (string)(int)$kode;
        if (isset(PKKM_PENILAI_POSISI[$kode])) {
            return PKKM_PENILAI_POSISI[$kode];
        }
        if ((int)$kode >= PKKM_POSISI_TAMBAHAN) {
            // Nomor posisi >= 3 = salah satu dari 5 slot penilai Tahun Keempat. Label slot bergantung
            // pada STATUS LEMBAGA (negeri: Kepala Bidang, swasta: Yayasan); bila statusnya tidak
            // diketahui, dipakai label umum menurut urutan slot.
            if ($statusLembaga === null) {
                $umum = [3 => 'Kepala Bidang / Yayasan', 4 => 'Kepala Seksi', 5 => 'Guru',
                    6 => 'Tenaga Kependidikan', 7 => 'Komite Madrasah'];
                return $umum[(int)$kode] ?? PKKM_PENILAI_TAMBAHAN_LABEL;
            }
            $slot = penilai_slot_menurut_posisi($kode, $statusLembaga);
            return $slot !== null ? $slot['label'] : PKKM_PENILAI_TAMBAHAN_LABEL;
        }
        return '-';
    }
}

// ============================================================================
// 3b. STATUS LEMBAGA + SLOT PENILAI TAHUN KEEMPAT (7 penilai) — permintaan pemilik
//     Negeri : Kabid, Kasie, Pengawas 1, Pengawas 2, Guru, Tendik, Komite
//     Swasta : Yayasan, Kasie, Pengawas 1, Pengawas 2, Guru, Tendik, Komite
// ============================================================================

if (!function_exists('status_lembaga_sah')) {
    /** Hanya menerima status lembaga yang dikenal ('negeri'/'swasta'); selain itu bawaan. */
    function status_lembaga_sah($nilai): string
    {
        $v = strtolower(trim((string)$nilai));
        return array_key_exists($v, PKKM_STATUS_LEMBAGA) ? $v : PKKM_STATUS_LEMBAGA_BAWAAN;
    }
}

if (!function_exists('status_lembaga_sekolah')) {
    /** Status lembaga sebuah madrasah (baris `sekolah` atau id). */
    function status_lembaga_sekolah($sekolah): string
    {
        if (is_numeric($sekolah)) {
            $sekolah = one('SELECT status_lembaga FROM sekolah WHERE id = ?', [(int)$sekolah]);
        }
        if (!is_array($sekolah)) {
            return PKKM_STATUS_LEMBAGA_BAWAAN;
        }
        return status_lembaga_sah($sekolah['status_lembaga'] ?? '');
    }
}

if (!function_exists('status_lembaga_label')) {
    /** Label tampilan status lembaga, mis. "Negeri" / "Swasta". */
    function status_lembaga_label(?string $status = null): string
    {
        $s = $status === null ? PKKM_STATUS_LEMBAGA_BAWAAN : status_lembaga_sah($status);
        return PKKM_STATUS_LEMBAGA[$s];
    }
}

if (!function_exists('status_lembaga_pil')) {
    /** Pil HTML status lembaga (dipakai daftar/kartu). */
    function status_lembaga_pil(?string $status): string
    {
        $s = status_lembaga_sah($status ?? '');
        return '<span class="pil ' . ($s === 'negeri' ? 'pil-baik' : 'pil-cukup') . '">'
            . e(PKKM_STATUS_LEMBAGA[$s]) . '</span>';
    }
}

if (!function_exists('penilai_slot_list')) {
    /**
     * Lima slot penilai Tahun Keempat (selain Pengawas 1 & 2) untuk sebuah status lembaga.
     * Tiap baris: kunci, label, jabatan[], posisi (nomor posisi penyimpanan).
     */
    function penilai_slot_list(?string $statusLembaga = null): array
    {
        $s = status_lembaga_sah($statusLembaga ?? PKKM_STATUS_LEMBAGA_BAWAAN);
        $out = [];
        $pos = PKKM_POSISI_TAMBAHAN;
        foreach ((PKKM_SLOT_PENILAI[$s] ?? []) as $slot) {
            $slot['posisi'] = $pos;
            $slot['status'] = $s;
            $out[$slot['kunci']] = $slot;
            $pos++;
        }
        return $out;
    }
}

if (!function_exists('penilai_slot_dari_jabatan')) {
    /**
     * Slot mana yang ditempati sebuah JABATAN pada madrasah berstatus tertentu?
     * Mengembalikan array slot (lihat penilai_slot_list()) atau null bila jabatan itu tidak
     * termasuk 7 penilai Tahun Keempat pada status tersebut (mis. Kabid di madrasah swasta).
     */
    function penilai_slot_dari_jabatan(string $jabatan, ?string $statusLembaga = null): ?array
    {
        $jab = strtolower(trim($jabatan));
        if ($jab === '' || $jab === '-') {
            return null;
        }
        foreach (penilai_slot_list($statusLembaga) as $slot) {
            foreach ($slot['jabatan'] as $awalan) {
                if ($jab === $awalan || str_starts_with($jab, $awalan . ' ')) {
                    return $slot;
                }
            }
        }
        return null;
    }
}

if (!function_exists('penilai_slot_menurut_posisi')) {
    /** Slot yang "biasanya" menempati nomor posisi tertentu (untuk label saat nomor posisi diketahui). */
    function penilai_slot_menurut_posisi($posisi, ?string $statusLembaga = null): ?array
    {
        $posisi = (int)$posisi;
        if ($posisi < PKKM_POSISI_TAMBAHAN) {
            return null;
        }
        $i = $posisi - PKKM_POSISI_TAMBAHAN;
        $status = $statusLembaga === null ? PKKM_STATUS_LEMBAGA_BAWAAN : status_lembaga_sah($statusLembaga);
        $daftar = array_values(PKKM_SLOT_PENILAI[$status] ?? []);
        if (isset($daftar[$i])) {
            $daftar[$i]['posisi'] = $posisi;
            $daftar[$i]['status'] = $status;
            return $daftar[$i];
        }
        return null;
    }
}

if (!function_exists('penilai_slot_label')) {
    /** Label slot pada madrasah tertentu (dipakai tampilan penugasan). */
    function penilai_slot_label(string $kunci, ?string $statusLembaga = null): string
    {
        $slot = penilai_slot_list($statusLembaga)[$kunci] ?? null;
        return $slot !== null ? $slot['label'] : ucfirst($kunci);
    }
}

if (!function_exists('penilai_posisi_tambahan')) {
    /** Apakah nomor posisi itu posisi PENILAI TAMBAHAN Tahun Keempat (>= 3)? */
    function penilai_posisi_tambahan($posisi): bool
    {
        return (int)$posisi >= PKKM_POSISI_TAMBAHAN;
    }
}

if (!function_exists('penilai_kategori_posisi')) {
    /** Kategori baris `penilaian`/`penilai` untuk sebuah posisi penugasan. */
    function penilai_kategori_posisi($posisi): string
    {
        return penilai_posisi_tambahan($posisi) ? PKKM_KATEGORI_TAMBAHAN : 'pengawas';
    }
}

if (!function_exists('penilai_akun_tambahan')) {
    /**
     * Daftar akun PENILAI TAMBAHAN Tahun Keempat sebuah madrasah (posisi >= 3), urut posisi.
     * @return array baris users + 'posisi'
     */
    function penilai_akun_tambahan(int $sekolahId): array
    {
        if ($sekolahId <= 0) {
            return [];
        }
        return all("SELECT u.*, ps.posisi FROM pengawas_sekolah ps JOIN users u ON u.id = ps.user_id
                     WHERE ps.sekolah_id = ? AND ps.posisi >= ? AND u.role = 'pengawas'
                     ORDER BY ps.posisi, u.nama", [$sekolahId, PKKM_POSISI_TAMBAHAN]);
    }
}

if (!function_exists('penilai_slot_pemegang')) {
    /**
     * Akun penilai yang sudah memegang sebuah SLOT pada madrasah tertentu (selain akun $kecualiUserId).
     * Slot ditentukan dari JABATAN akun, jadi akun berjabatan sama = slot yang sama.
     *
     * @return array|null baris users (+ 'posisi')
     */
    function penilai_slot_pemegang(int $sekolahId, string $kunci, int $kecualiUserId = 0): ?array
    {
        if ($sekolahId <= 0 || $kunci === '') {
            return null;
        }
        $status = status_lembaga_sekolah($sekolahId);
        foreach (all("SELECT u.*, ps.posisi FROM pengawas_sekolah ps JOIN users u ON u.id = ps.user_id
                       WHERE ps.sekolah_id = ? AND ps.posisi >= ? AND ps.user_id <> ? AND u.role = 'pengawas'
                       ORDER BY ps.posisi", [$sekolahId, PKKM_POSISI_TAMBAHAN, $kecualiUserId]) as $akun) {
            $slot = penilai_slot_dari_jabatan((string)($akun['jabatan'] ?? ''), $status);
            if ($slot !== null && $slot['kunci'] === $kunci) {
                return $akun;
            }
        }
        return null;
    }
}

if (!function_exists('penilai_posisi_tambahan_baru')) {
    /** Nomor posisi tambahan yang masih kosong pada sebuah madrasah (>= 3). */
    function penilai_posisi_tambahan_baru(int $sekolahId, int $kecualiUserId = 0): int
    {
        // Hindari SEMUA nomor yang sudah dipakai: penugasan lain, sisa baris `penilaian`/`penilai`
        // kategori tambahan (mis. akun penilainya sudah dihapus). Kalau tidak, penilai baru bisa
        // menempati nomor ber-sisa data lama sehingga mewarisi skor/identitas orang lain.
        $maks = (int)(val('SELECT COALESCE(MAX(posisi), ?) FROM pengawas_sekolah
                           WHERE sekolah_id = ? AND user_id <> ?',
            [PKKM_POSISI_TAMBAHAN - 1, $sekolahId, $kecualiUserId]) ?? 0);
        $maksP = (int)(val("SELECT COALESCE(MAX(CAST(kode_penilai AS INTEGER)), ?) FROM penilaian
                             WHERE sekolah_id = ? AND kategori = ?",
            [PKKM_POSISI_TAMBAHAN - 1, $sekolahId, PKKM_KATEGORI_TAMBAHAN]) ?? 0);
        $maksI = (int)(val("SELECT COALESCE(MAX(CAST(kode AS INTEGER)), ?) FROM penilai
                             WHERE sekolah_id = ? AND kategori = ?",
            [PKKM_POSISI_TAMBAHAN - 1, $sekolahId, PKKM_KATEGORI_TAMBAHAN]) ?? 0);
        return max(PKKM_POSISI_TAMBAHAN, max($maks, $maksP, $maksI) + 1);
    }
}

if (!function_exists('penilai_kelengkapan_tahun')) {
    /**
     * Kelengkapan penilaian PENGAWAS pada satu tahun (semua butir terisi).
     * Dipakai syarat pemilihan madrasah oleh penilai selain jabatan Pengawas.
     *
     * @return array{tahun:int,lengkap:bool,kode:array<string,bool>,terisi:array<string,int>,jumlah:int,teks:string}
     */
    function penilai_kelengkapan_tahun(int $sekolahId, int $tahun): array
    {
        $jumlah = count(butir_tahun($tahun));
        $kode = [];
        $terisi = [];
        foreach (['1', '2'] as $p) {
            $r = nilai_penilaian($sekolahId, 'pengawas', $p, $tahun);
            $n = $r ? (int)$r['terisi'] : 0;
            $terisi[$p] = $n;
            $kode[$p] = $jumlah > 0 && $n === $jumlah;
        }
        // "sudah dinilai lengkap" = Pengawas I DAN Pengawas II sama-sama mengisi seluruh butir.
        $lengkap = $jumlah > 0 && $kode['1'] && $kode['2'];
        return [
            'tahun' => $tahun,
            'lengkap' => $lengkap,
            'kode' => $kode,
            'terisi' => $terisi,
            'jumlah' => $jumlah,
            'teks' => $lengkap
                ? PKKM_TAHUN_LIST[$tahun] . ' lengkap'
                : PKKM_TAHUN_LIST[$tahun] . ' belum lengkap (Pengawas I ' . $terisi['1'] . '/' . $jumlah
                    . ', Pengawas II ' . $terisi['2'] . '/' . $jumlah . ' butir)',
        ];
    }
}

if (!function_exists('penilai_kesiapan_tahun4')) {
    /**
     * Kesiapan sebuah madrasah dinilai penilai TAMBAHAN Tahun Keempat.
     *
     * Aturan pemilik: "penilai selain jabatan pengawas hanya bisa memilih madrasah yang sudah
     * dinilai pada tahun 1-3. Jika madrasah tersebut belum dinilai tahun 1-3 maka tidak bisa dipilih."
     * Syaratnya (pilihan pemilik): ketiga tahun SUDAH TERISI LENGKAP SEMUA BUTIR.
     *
     * @return array{siap:bool,tahun:array<int,array>,kurang:int[],teks:string}
     */
    function penilai_kesiapan_tahun4(int $sekolahId): array
    {
        $tahun = [];
        $kurang = [];
        foreach ([1, 2, 3] as $t) {
            $tahun[$t] = penilai_kelengkapan_tahun($sekolahId, $t);
            if (!$tahun[$t]['lengkap']) {
                $kurang[] = $t;
            }
        }
        $siap = $kurang === [];
        return [
            'siap' => $siap,
            'tahun' => $tahun,
            'kurang' => $kurang,
            'teks' => $siap
                ? 'Tahun Pertama s.d. Ketiga sudah lengkap'
                : 'Belum dinilai lengkap pada ' . implode(' dan ', array_map(
                    fn($t) => PKKM_TAHUN_LIST[$t], $kurang)),
        ];
    }
}

if (!function_exists('penilai_boleh_pilih_sekolah')) {
    /**
     * Boleh TIDAKnya sebuah akun penilai memilih/menilai madrasah ini.
     *  - akun berjabatan Pengawas : semua madrasah aktif (Tahun 1-4);
     *  - jabatan lain             : hanya madrasah yang Tahun 1-3 sudah lengkap (Tahun Keempat).
     */
    function penilai_boleh_pilih_sekolah(int $sekolahId, ?array $user = null): bool
    {
        if (penilai_jabatan_pengawas($user)) {
            return true;
        }
        return penilai_kesiapan_tahun4($sekolahId)['siap'];
    }
}

if (!function_exists('penilai_akun_untuk')) {
    /** Akun penilai yang memegang sebuah posisi pada madrasah tertentu (null bila belum ada). */
    function penilai_akun_untuk(int $sekolahId, string $posisi): ?array
    {
        if ($sekolahId <= 0) {
            return null;
        }
        return one("SELECT u.* FROM pengawas_sekolah ps JOIN users u ON u.id = ps.user_id
                     WHERE ps.sekolah_id = ? AND ps.posisi = ? AND u.role = 'pengawas'
                     ORDER BY u.nama LIMIT 1", [$sekolahId, (int)$posisi]);
    }
}

if (!function_exists('penilai_sekolah_baris')) {
    /**
     * Data penilai 1 / 2 sebuah madrasah untuk DITAMPILKAN.
     * Sumber utama: AKUN penilai yang memilih posisi itu (hasil pendaftaran penilai).
     * Cadangan: baris tabel `penilai` (data lama / diisi Super Admin) supaya lembar cetak lama tetap terbaca.
     *
     * @return array{nama:string,nip:string,jabatan:string,unit_kerja:string,kabupaten:string,sumber:string,akun:?array}
     */
    function penilai_sekolah_baris(int $sekolahId, string $posisi): array
    {
        $kosong = ['nama' => '', 'nip' => '', 'jabatan' => '', 'unit_kerja' => '',
            'kabupaten' => '', 'sumber' => '', 'akun' => null];
        $akun = penilai_akun_untuk($sekolahId, $posisi);
        if ($akun) {
            return [
                'nama' => (string)$akun['nama'],
                'nip' => (string)($akun['nip'] ?? ''),
                'jabatan' => (string)($akun['jabatan'] ?? ''),
                'unit_kerja' => (string)($akun['instansi'] ?? ''),
                'kabupaten' => (string)($akun['kab_kota'] ?? ''),
                'sumber' => 'akun',
                'akun' => $akun,
            ];
        }
        $data = one('SELECT * FROM penilai WHERE sekolah_id = ? AND kategori = ? AND kode = ?',
            [$sekolahId, 'pengawas', (string)(int)$posisi]);
        if (!$data) {
            return $kosong;
        }
        return [
            'nama' => (string)($data['nama'] ?? ''),
            'nip' => (string)($data['nip'] ?? ''),
            'jabatan' => (string)($data['jabatan'] ?? ''),
            'unit_kerja' => (string)($data['unit_kerja'] ?? ''),
            'kabupaten' => (string)($data['kabupaten'] ?? ''),
            'sumber' => 'data',
            'akun' => null,
        ];
    }
}

if (!function_exists('penilai_daftar_sekolah')) {
    /** Data Penilai 1 & 2 satu madrasah (kunci '1' dan '2'). */
    function penilai_daftar_sekolah(int $sekolahId): array
    {
        return [
            '1' => penilai_sekolah_baris($sekolahId, '1'),
            '2' => penilai_sekolah_baris($sekolahId, '2'),
        ];
    }
}

if (!function_exists('penilai_sinkron')) {
    /**
     * Selaraskan baris `penilai` (identitas yang tercetak pada lembar) dengan profil AKUN penilai.
     * Kategori mengikuti posisi: 1/2 = `pengawas`, >= 3 = `tambahan` (Penilai Tambahan Tahun Keempat).
     */
    function penilai_sinkron(int $userId, int $sekolahId, int $posisi): void
    {
        $u = one('SELECT * FROM users WHERE id = ?', [$userId]);
        if (!$u || $sekolahId <= 0 || $posisi < 1) {
            return;
        }
        $kategori = penilai_kategori_posisi($posisi);
        $isi = [
            'user_id' => $userId,
            'nama' => (string)$u['nama'],
            'nip' => (string)($u['nip'] ?? ''),
            'jabatan' => (string)($u['jabatan'] ?? ''),
            'unit_kerja' => (string)($u['instansi'] ?? ''),
            'kabupaten' => (string)($u['kab_kota'] ?? ''),
        ];
        $ada = one('SELECT * FROM penilai WHERE sekolah_id = ? AND kategori = ? AND kode = ?',
            [$sekolahId, $kategori, (string)$posisi]);
        if ($ada) {
            $set = [];
            $args = [];
            foreach ($isi as $k => $v) {
                $set[] = $k . ' = ?';
                $args[] = $v;
            }
            $set[] = 'diubah = ?';
            $args[] = date('Y-m-d H:i:s');
            $args[] = (int)$ada['id'];
            q('UPDATE penilai SET ' . implode(', ', $set) . ' WHERE id = ?', $args);
            return;
        }
        $kolom = array_keys($isi);
        q('INSERT INTO penilai (sekolah_id, kategori, kode, ' . implode(', ', $kolom) . ', dibuat, diubah)
           VALUES (?, ?, ?, ' . implode(', ', array_fill(0, count($kolom), '?')) . ', ?, ?)',
            array_merge([$sekolahId, $kategori, (string)$posisi], array_values($isi),
                [date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]));
    }
}

if (!function_exists('penilai_pindah_tambahan')) {
    /**
     * Pindahkan penilaian & identitas penilai milik SEORANG akun dari posisi Pengawas I/II (1/2)
     * ke posisi TAMBAHAN Tahun Keempat (>= 3). Dipakai saat jabatan akun berubah dari Pengawas
     * menjadi jabatan lain (mis. Guru) sehingga posisinya tidak lagi menahan posisi Pengawas.
     * Hanya baris milik akun itu yang dipindah.
     */
    function penilai_pindah_tambahan(int $userId, int $sekolahId, int $lama, int $baru): void
    {
        if ($userId <= 0 || $sekolahId <= 0 || $lama < 1 || $baru < PKKM_POSISI_TAMBAHAN) {
            return;
        }
        $bentrok = (int)(val('SELECT COUNT(*) FROM penilaian WHERE sekolah_id = ? AND kategori = ? AND kode_penilai = ?',
            [$sekolahId, PKKM_KATEGORI_TAMBAHAN, (string)$baru]) ?? 0);
        if ($bentrok === 0) {
            q("UPDATE penilaian SET kategori = ?, kode_penilai = ?
                WHERE sekolah_id = ? AND kategori = 'pengawas' AND kode_penilai = ? AND diisi_oleh = ?",
                [PKKM_KATEGORI_TAMBAHAN, (string)$baru, $sekolahId, (string)$lama, $userId]);
        }
        q("UPDATE penilai SET kategori = ?, kode = ?
            WHERE sekolah_id = ? AND kategori = 'pengawas' AND kode = ? AND user_id = ?",
            [PKKM_KATEGORI_TAMBAHAN, (string)$baru, $sekolahId, (string)$lama, $userId]);
    }
}

if (!function_exists('penugasan_penilai_simpan')) {
    /**
     * Simpan penugasan seorang penilai.
     *
     * ATURAN (permintaan pemilik, v1.7.0):
     *  - akun berjabatan PENGAWAS memakai posisi 1 (Penilai 1) atau 2 (Penilai 2);
     *    satu posisi pada satu madrasah hanya boleh dipegang satu akun;
     *  - akun jabatan LAIN hanya menilai Tahun Keempat dan memakai POSISI TAMBAHAN (3, 4, 5, …),
     *    sehingga satu madrasah boleh punya lebih dari 2 penilai; madrasahnya hanya boleh dipilih
     *    bila Tahun Pertama s.d. Ketiga sudah terisi lengkap seluruh butir.
     *
     * Penugasan yang SUDAH ADA (data lama) tetap dipertahankan walaupun madrasahnya belum lengkap
     * (agar penilai tidak kehilangan riwayatnya saat menyimpan pilihan lain).
     *
     * @param array $rencana [sekolah_id => posisi]; untuk penilai tambahan cukup kirim
     *                       PKKM_POSISI_TAMBAHAN (atau angka >= 3) — nomor posisinya dihitung di sini.
     * @return array ['jumlah' => int, 'ditolak' => string[]]
     */
    function penugasan_penilai_simpan(int $userId, array $rencana): array
    {
        $user = one('SELECT * FROM users WHERE id = ?', [$userId]);
        if (!$user) {
            return ['jumlah' => 0, 'ditolak' => ['Akun penilai tidak ditemukan.']];
        }
        $jabatanPengawas = penilai_jabatan_pengawas($user);
        $jabatan = trim((string)($user['jabatan'] ?? ''));
        $sah = [];
        foreach (all('SELECT id, nama FROM sekolah WHERE aktif = 1') as $s) {
            $sah[(int)$s['id']] = (string)$s['nama'];
        }

        $lama = [];
        foreach (all('SELECT sekolah_id, posisi FROM pengawas_sekolah WHERE user_id = ?', [$userId]) as $t) {
            $lama[(int)$t['sekolah_id']] = (int)$t['posisi'];
        }

        $ditolak = [];
        $bersih = [];    // sekolah_id => posisi final
        $pindah = [];    // [sekolah_id, posisi lama 1/2, posisi tambahan baru]
        // sekolah yang permintaannya DITOLAK: penugasan lamanya harus DIPERTAHANKAN (jangan sampai
        // satu permintaan yang gagal menghapus seluruh penugasan akun itu — pernah terjadi).
        $ditolakSid = [];
        $hapusSid = [];  // sekolah yang dikirim dengan posisi 0 (dilepas)
        foreach ($rencana as $sid => $pos) {
            $sid = (int)$sid;
            $pos = (int)$pos;
            if (!isset($sah[$sid])) {
                continue;
            }
            if ($pos === 0) {
                // nilai 0 = "tidak ditugaskan" (dilepas). Hanya madrasah yang DIKIRIM dengan 0 yang
                // dilepas; madrasah yang tidak ikut dikirim (formulir sebagian) tidak disentuh.
                $hapusSid[$sid] = true;
                continue;
            }
            if ($pos < 1) {
                continue;
            }
            $nama = $sah[$sid];
            if ($jabatanPengawas) {
                if (!in_array($pos, [1, 2], true)) {
                    $ditolak[] = $nama . ': jabatan Pengawas hanya memakai posisi Penilai 1 atau Penilai 2';
                    $ditolakSid[$sid] = true;
                    continue;
                }
                $lain = one("SELECT u.nama FROM pengawas_sekolah ps JOIN users u ON u.id = ps.user_id
                              WHERE ps.sekolah_id = ? AND ps.posisi = ? AND ps.user_id <> ?",
                    [$sid, $pos, $userId]);
                if ($lain) {
                    $ditolak[] = $nama . ' sebagai ' . penilai_posisi_teks($pos, status_lembaga_sekolah($sid))
                        . ' sudah dipegang ' . $lain['nama'];
                    $ditolakSid[$sid] = true;
                    continue;
                }
                $bersih[$sid] = $pos;
                continue;
            }
            // -------- jabatan selain Pengawas: satu dari 5 slot penilai Tahun Keempat --------
            // Slot ditentukan oleh JABATAN akun menurut STATUS LEMBAGA madrasah
            // (negeri: Kabid/Kasie/Guru/Tendik/Komite; swasta: Yayasan/Kasie/Guru/Tendik/Komite).
            $sudah = (int)($lama[$sid] ?? 0);
            $siap = penilai_kesiapan_tahun4($sid);
            $statusLembaga = status_lembaga_sekolah($sid);
            $slot = penilai_slot_dari_jabatan((string)($user['jabatan'] ?? ''), $statusLembaga);
            if ($slot === null) {
                $ditolak[] = $nama . ': jabatan "' . ($jabatan !== '' ? $jabatan : 'belum diisi')
                    . '" tidak termasuk penilai Tahun Keempat pada madrasah '
                    . status_lembaga_label($statusLembaga) . ' (yang menilai: '
                    . implode(', ', array_map(fn($sl) => $sl['label'], penilai_slot_list($statusLembaga)))
                    . ')';
                $ditolakSid[$sid] = true;
                continue;
            }
            if (!$siap['siap'] && $sudah === 0) {
                $ditolak[] = $nama . ' belum dapat dipilih: ' . $siap['teks']
                    . ' (Tahun Keempat mengikuti penilaian Tahun Pertama s.d. Ketiga)';
                $ditolakSid[$sid] = true;
                continue;
            }
            // satu slot hanya boleh dipegang satu akun
            $pemegang = penilai_slot_pemegang($sid, $slot['kunci'], $userId);
            if ($pemegang) {
                $ditolak[] = $nama . ': slot ' . $slot['label'] . ' pada madrasah itu sudah diisi '
                    . (string)($pemegang['nama'] ?? 'penilai lain');
                $ditolakSid[$sid] = true;
                continue;
            }
            $bersih[$sid] = $sudah >= PKKM_POSISI_TAMBAHAN
                ? $sudah
                : penilai_posisi_tambahan_baru($sid, $userId);
            // jabatan berubah dari Pengawas -> jabatan lain: penilaian lamanya ikut dipindahkan
            if ($sudah >= 1 && $sudah < PKKM_POSISI_TAMBAHAN) {
                $pindah[] = [$sid, $sudah, $bersih[$sid]];
            }
        }

        // Set final penugasan:
        //  - diterima ($bersih),
        //  - penugasan LAMA dipertahankan kecuali madrasah itu DIKIRIM dengan 0 (dilepas) atau
        //    posisinya diganti (sudah ada di $bersih),
        //  - madrasah yang permintaannya ditolak => penugasan lamanya tetap.
        $final = $bersih;
        foreach ($lama as $sidL => $posL) {
            if (isset($final[$sidL]) || isset($hapusSid[$sidL])) {
                continue;
            }
            $final[$sidL] = $posL;
        }
        $pindah = array_values(array_filter($pindah, fn($pj) => isset($bersih[$pj[0]])));

        mulai_transaksi();
        try {
            q('DELETE FROM pengawas_sekolah WHERE user_id = ?', [$userId]);
            foreach ($pindah as [$sidP, $lamaP, $baruP]) {
                penilai_pindah_tambahan($userId, $sidP, $lamaP, $baruP);
            }
            ksort($final);
            foreach ($final as $sid => $pos) {
                q('INSERT OR REPLACE INTO pengawas_sekolah (user_id, sekolah_id, posisi, dibuat) VALUES (?, ?, ?, ?)',
                    [$userId, $sid, $pos, date('Y-m-d H:i:s')]);
                if (isset($bersih[$sid])) {
                    penilai_sinkron($userId, $sid, $pos);
                }
            }
            // posisi yang dilepas: hapus baris penilai milik akun ini saja (data isian Super Admin dibiarkan)
            foreach ($lama as $sid => $pos) {
                if (isset($final[$sid])) {
                    continue;
                }
                q('DELETE FROM penilai WHERE sekolah_id = ? AND kategori = ? AND kode = ?
                     AND (user_id = ? OR user_id IS NULL)',
                    [$sid, penilai_kategori_posisi($pos), (string)$pos, $userId]);
            }
            selesai_transaksi(true);
        } catch (Throwable $t) {
            selesai_transaksi(false);
            return ['jumlah' => 0, 'ditolak' => array_merge($ditolak, ['Penugasan gagal disimpan: ' . $t->getMessage()])];
        }
        return ['jumlah' => count($bersih), 'ditolak' => $ditolak, 'dipertahankan' => count($final) - count($bersih)];
    }
}

if (!function_exists('penilai_sekolah_pilihan')) {
    /**
     * Daftar madrasah untuk halaman PILIH MADRASAH pada akun penilai.
     * Hanya madrasah aktif & sudah disetujui; setiap baris memuat posisi akun ini (0 = belum),
     * nama pemegang posisi Pengawas I & II, penilai tambahan Tahun Keempat, serta kesiapan
     * Tahun 1-3 (syarat bagi penilai selain jabatan Pengawas).
     */
    function penilai_sekolah_pilihan(int $userId, ?array $user = null): array
    {
        if ($user === null) {
            $user = one('SELECT * FROM users WHERE id = ?', [$userId]);
        }
        $pengawasJab = penilai_jabatan_pengawas($user);
        $daftar = all("SELECT id, nama, kecamatan, kabupaten, status_lembaga FROM sekolah
                        WHERE aktif = 1 AND daftar_status = 'disetujui' ORDER BY nama");
        $milik = [];
        foreach (all('SELECT sekolah_id, posisi FROM pengawas_sekolah WHERE user_id = ?', [$userId]) as $t) {
            $milik[(int)$t['sekolah_id']] = (int)$t['posisi'];
        }
        $hasil = [];
        foreach ($daftar as $s) {
            $sid = (int)$s['id'];
            $baris = $s;
            $baris['posisi'] = $milik[$sid] ?? 0;
            $baris['pemegang'] = ['1' => penilai_akun_untuk($sid, '1'), '2' => penilai_akun_untuk($sid, '2')];
            $baris['tambahan'] = penilai_akun_tambahan($sid);
            $baris['siap'] = $pengawasJab ? null : penilai_kesiapan_tahun4($sid);
            $status = status_lembaga_sah($s['status_lembaga'] ?? '');
            $baris['status_lembaga'] = $status;
            if ($pengawasJab) {
                $baris['slot'] = null;
                $baris['slot_pemegang'] = null;
            } else {
                // slot yang akan ditempati akun ini (menurut jabatannya) pada madrasah tersebut
                $slot = penilai_slot_dari_jabatan((string)($user['jabatan'] ?? ''), $status);
                $baris['slot'] = $slot;
                $baris['slot_pemegang'] = $slot === null ? null
                    : penilai_slot_pemegang($sid, $slot['kunci'], (int)$user['id']);
            }
            $baris['boleh'] = $pengawasJab
                || (($baris['siap']['siap'] ?? false) && ($baris['slot'] !== null)
                    && $baris['slot_pemegang'] === null)
                || $baris['posisi'] >= PKKM_POSISI_TAMBAHAN;
            $hasil[] = $baris;
        }
        return $hasil;
    }
}

if (!function_exists('penilai_sekolah_slots')) {
    /**
     * Tujuh penilai Tahun Keempat sebuah madrasah, URUT tampil:
     * Penilai 1, Penilai 2 (Pengawas I & II), lalu lima slot menurut STATUS LEMBAGA.
     *
     * Tiap baris: kunci, posisi, label, jabatan (label resmi untuk cetak), kategori (`pengawas`/
     * `tambahan`), akun (baris users + posisi, atau null), rinci (nilai_rinci atau null).
     *
     * @return array<string,array> kunci => baris
     */
    function penilai_sekolah_slots(int $sekolahId): array
    {
        if ($sekolahId <= 0) {
            return [];
        }
        $status = status_lembaga_sekolah($sekolahId);
        $out = [];
        foreach (['1' => 1, '2' => 2] as $kunci => $posisi) {
            $akun = penilai_akun_untuk($sekolahId, $kunci);
            $rinci = nilai_penilaian($sekolahId, 'pengawas', $kunci, 4);
            $out['pengawas' . $kunci] = [
                'kunci' => 'pengawas' . $kunci,
                'posisi' => $posisi,
                'label' => penilai_posisi_teks($kunci),
                'jabatan' => PKKM_PENILAI_PENGAWAS[$kunci],
                'kategori' => 'pengawas',
                'akun' => $akun,
                'rinci' => $rinci,
            ];
        }
        // akun penilai tambahan (posisi >= 3) dipetakan ke slotnya masing-masing
        $perSlot = [];
        foreach (all("SELECT u.*, ps.posisi FROM pengawas_sekolah ps JOIN users u ON u.id = ps.user_id
                       WHERE ps.sekolah_id = ? AND ps.posisi >= ? AND u.role = 'pengawas'
                       ORDER BY ps.posisi", [$sekolahId, PKKM_POSISI_TAMBAHAN]) as $akun) {
            $slot = penilai_slot_dari_jabatan((string)($akun['jabatan'] ?? ''), $status);
            if ($slot !== null && !isset($perSlot[$slot['kunci']])) {
                $perSlot[$slot['kunci']] = $akun;
            }
        }
        foreach (penilai_slot_list($status) as $kunci => $slot) {
            $akun = $perSlot[$kunci] ?? null;
            $rinci = $akun !== null
                ? nilai_penilaian($sekolahId, PKKM_KATEGORI_TAMBAHAN, (string)(int)$akun['posisi'], 4)
                : null;
            $out[$kunci] = [
                'kunci' => $kunci,
                'posisi' => null,
                'label' => $slot['label'],
                'jabatan' => $slot['label'],
                'kategori' => PKKM_KATEGORI_TAMBAHAN,
                'akun' => $akun,
                'rinci' => $rinci,
            ];
        }
        return $out;
    }
}

if (!function_exists('penilai_sekolah_slot_jumlah')) {
    /** Berapa dari 7 penilai Tahun Keempat yang sudah terisi akunnya. */
    function penilai_sekolah_slot_jumlah(int $sekolahId): array
    {
        $slots = penilai_sekolah_slots($sekolahId);
        $isi = 0;
        $nilai = 0;
        foreach ($slots as $sl) {
            if ($sl['akun'] !== null) {
                $isi++;
            }
            if (($sl['rinci']['nilai'] ?? null) !== null) {
                $nilai++;
            }
        }
        return ['isi' => $isi, 'nilai' => $nilai, 'total' => count($slots)];
    }
}

if (!function_exists('penilai_nama_lengkap')) {
    /** Nama akun penilai + jabatannya, mis. "Herianto Dalanggo — Pengawas". */
    function penilai_nama_lengkap(?array $u): string
    {
        if (!$u) {
            return '';
        }
        $nama = trim((string)($u['nama'] ?? ''));
        $jab = trim((string)($u['jabatan'] ?? ''));
        return $jab === '' ? $nama : $nama . ' — ' . $jab;
    }
}

// ============================================================================
// 9. TANGGAL MENILAI per penilai per tahun (permintaan pemilik)
//    "Pada halaman penilai tambahkan fitur tanggal menilai sebelum mereka mengisi skor penilaian
//     setiap tahunnya."
//    Disimpan pada kolom `penilai.tanggal_1 … tanggal_4` (sudah ada, dipakai lembar cetak),
//    sehingga tanggal ini otomatis tercetak pada lembar penilaian, laporan, dan rekap.
// ============================================================================

if (!function_exists('penilai_tanggal_kolom')) {
    /** Nama kolom tanggal pada tabel `penilai` untuk satu tahun penilaian. */
    function penilai_tanggal_kolom(int $tahun): string
    {
        return 'tanggal_' . (int)$tahun;
    }
}

if (!function_exists('penilai_tanggal')) {
    /**
     * Tanggal menilai yang SUDAH disimpan seorang penilai untuk satu tahun ('' bila belum).
     * Sumber: baris tabel `penilai` (kategori & kode sesuai posisi penilai).
     */
    function penilai_tanggal(int $sekolahId, string $kategori, string $kode, int $tahun): string
    {
        if ($sekolahId <= 0 || !array_key_exists((int)$tahun, PKKM_TAHUN_LIST)) {
            return '';
        }
        $kolom = penilai_tanggal_kolom($tahun);
        $r = one('SELECT ' . $kolom . ' AS tgl FROM penilai WHERE sekolah_id = ? AND kategori = ? AND kode = ?',
            [$sekolahId, $kategori, $kode]);
        return $r ? trim((string)($r['tgl'] ?? '')) : '';
    }
}

if (!function_exists('penilai_tanggal_simpan')) {
    /**
     * Simpan TANGGAL MENILAI seorang penilai untuk satu tahun.
     * Baris `penilai` dibuat bila belum ada (kolom identitas lain dibiarkan kosong dan akan
     * dilengkapi penilai_sinkron()). Mengembalikan tanggal tersimpan ('' bila tidak sah).
     */
    function penilai_tanggal_simpan(int $sekolahId, string $kategori, string $kode, int $tahun, string $tanggal): string
    {
        if ($sekolahId <= 0 || !array_key_exists((int)$tahun, PKKM_TAHUN_LIST)) {
            return '';
        }
        $tgl = tanggal_iso($tanggal);
        if ($tgl === '') {
            return '';
        }
        $kolom = penilai_tanggal_kolom($tahun);
        $now = date('Y-m-d H:i:s');
        $ada = one('SELECT id FROM penilai WHERE sekolah_id = ? AND kategori = ? AND kode = ?',
            [$sekolahId, $kategori, $kode]);
        if ($ada) {
            q('UPDATE penilai SET ' . $kolom . ' = ?, diubah = ? WHERE id = ?', [$tgl, $now, (int)$ada['id']]);
            return $tgl;
        }
        q('INSERT INTO penilai (sekolah_id, kategori, kode, ' . $kolom . ', dibuat, diubah) VALUES (?,?,?,?,?,?)',
            [$sekolahId, $kategori, $kode, $tgl, $now, $now]);
        return $tgl;
    }
}

if (!function_exists('penilai_tanggal_ada')) {
    /** Apakah seorang penilai sudah menetapkan tanggal menilai tahun itu? */
    function penilai_tanggal_ada(int $sekolahId, string $kategori, string $kode, int $tahun): bool
    {
        return penilai_tanggal($sekolahId, $kategori, $kode, $tahun) !== '';
    }
}

if (!function_exists('tahun_penilaian_tampil')) {
    /**
     * TAHUN PENILAIAN yang ditampilkan/dicetak untuk satu tahun PKKM ('' bila belum dapat diketahui).
     *
     * v1.18.0 — permintaan pemilik: kolom "Tahun penilaian" pada lembar penilaian masih "-" padahal
     * penilai sudah mengisi TANGGAL MENILAI. Sebabnya: `sekolah.tahun_1..4` hanya diisi Super Admin
     * lewat Input Data Madrasah dan pada data live masih kosong. Sejak versi ini tahun penilaian
     * diturunkan dari TANGGAL MENILAI tahun itu (angka tahun pada tanggal), sehingga tidak perlu
     * diisi dua kali. Urutan sumber:
     *   1. `sekolah.tahun_<n>` — isian Super Admin SELALU menang (jangan ditimpa),
     *   2. tanggal menilai Pengawas I (Ketua Tim) pada kolom `penilai.tanggal_<n>`,
     *   3. tanggal menilai penilai lain (Pengawas II / penilai tambahan) pada kolom yang sama,
     *   4. tanggal penilaian pada data madrasah `sekolah.tanggal_<n>`.
     * Semua kosong → '' (tampil "-").
     */
    function tahun_penilaian_tampil(int $sekolahId, array $s, int $tahun): string
    {
        static $memo = [];
        $tahun = (int)$tahun;
        if (!array_key_exists($tahun, PKKM_TAHUN_LIST)) {
            return '';
        }
        $kunci = $sekolahId . ':' . $tahun;
        if (array_key_exists($kunci, $memo)) {
            return $memo[$kunci];
        }
        // 1. isian Super Admin (data madrasah) — dipakai apa adanya
        $simpan = trim((string)($s['tahun_' . $tahun] ?? ''));
        if ($simpan !== '' && (int)$simpan > 0) {
            return $memo[$kunci] = (string)(int)$simpan;
        }
        // 2 & 3. angka tahun dari tanggal menilai para penilai (Pengawas I lebih dulu)
        $kolom = penilai_tanggal_kolom($tahun);
        $angka = '';
        if ($sekolahId > 0) {
            $baris = all('SELECT kategori, kode, ' . $kolom . ' AS tgl FROM penilai
                          WHERE sekolah_id = ? AND ' . $kolom . " IS NOT NULL AND TRIM(" . $kolom . ") <> ''", [$sekolahId]);
            usort($baris, static function (array $a, array $b): int {
                $pa = (($a['kategori'] ?? '') === 'pengawas' && (string)($a['kode'] ?? '') === '1') ? 0 : 1;
                $pb = (($b['kategori'] ?? '') === 'pengawas' && (string)($b['kode'] ?? '') === '1') ? 0 : 1;
                return $pa <=> $pb;
            });
            foreach ($baris as $b) {
                $angka = tahun_penilaian_dari_tanggal((string)($b['tgl'] ?? ''));
                if ($angka !== '') {
                    break;
                }
            }
        }
        // 4. cadangan: tanggal penilaian pada data madrasah
        if ($angka === '') {
            $angka = tahun_penilaian_dari_tanggal((string)($s['tanggal_' . $tahun] ?? ''));
        }
        return $memo[$kunci] = $angka;
    }
}

if (!function_exists('tahun_penilaian_dari_tanggal')) {
    /** Angka tahun (4 digit) dari sebuah tanggal ISO `YYYY-MM-DD`; '' bila tidak terbaca. */
    function tahun_penilaian_dari_tanggal(string $tanggal): string
    {
        $iso = tanggal_iso($tanggal);
        if ($iso === '') {
            return '';
        }
        $th = (int)substr($iso, 0, 4);
        return ($th >= 1900 && $th <= 2200) ? (string)$th : '';
    }
}

// ============================================================================
// 4. PENGAWAS BINAAN DARI APLIKASI SUPERVISI → otomatis PENILAI 1 (KETUA TIM)
//    v1.12.0 — permintaan pemilik:
//      "Pada halaman akun pengawas muncul seluruh daftar madrasah/sekolah binaannya. Dan dia
//       otomatis menjadi penilai 1 (Ketua Tim). Untuk penilai 2 dia yang akan menentukan dari
//       daftar pengawas yang ada."
//
//    Sumber peta binaan = `pengawas_sekolah` pada basis data Supervisi (dibaca BACA-SAJA lewat
//    inc/supervisi.php). Karena itu halaman akun pengawas TIDAK lagi memakai pilihan sendiri
//    "Penilai 1 / Penilai 2": posisi 1 melekat otomatis, sedangkan posisi 2 ditunjuk Ketua Tim.
// ============================================================================

if (!function_exists('penilai_posisi_pemegang_user')) {
    /** user_id yang memegang sebuah posisi pada madrasah tertentu (0 = belum ada). */
    function penilai_posisi_pemegang_user(int $sekolahId, int $posisi): int
    {
        if ($sekolahId <= 0 || $posisi <= 0) {
            return 0;
        }
        return (int)(val('SELECT user_id FROM pengawas_sekolah WHERE sekolah_id = ? AND posisi = ?',
            [$sekolahId, $posisi]) ?? 0);
    }
}

if (!function_exists('penilai_binaan_pengawas')) {
    /**
     * Seluruh madrasah BINAAN seorang akun pengawas (permintaan pemilik).
     *
     * Binaan = lembaga pada PETA PENGAWAS BINAAN aplikasi Supervisi yang sudah ditarik ke e-PKKM
     * (dicocokkan lewat `sekolah.supervisi_id`), ditambah madrasah tempat akun ini sudah ditugaskan
     * sebagai Penilai 1 (data lama / penugasan Super Admin) supaya tidak ada tugas yang "hilang".
     *
     * @return array tiap baris: id, nama, kecamatan, status_lembaga, posisi (posisi akun ini),
     *               ketua (?array akun pemegang posisi 1), penilai2 (?array akun pemegang posisi 2)
     */
    function penilai_binaan_pengawas(array $user): array
    {
        $uid = (int)($user['id'] ?? 0);
        if ($uid <= 0) {
            return [];
        }
        $supId = (int)($user['supervisi_id'] ?? 0);
        $binaanSuper = $supId > 0 ? supervisi_binaan_ids($supId) : [];

        $milik = [];
        foreach (all('SELECT sekolah_id, posisi FROM pengawas_sekolah WHERE user_id = ?', [$uid]) as $t) {
            $milik[(int)$t['sekolah_id']] = (int)$t['posisi'];
        }

        $hasil = [];
        foreach (all("SELECT id, nama, kecamatan, kabupaten, status_lembaga, supervisi_id FROM sekolah
                       WHERE aktif = 1 AND daftar_status = 'disetujui' ORDER BY nama") as $s) {
            $sid = (int)$s['id'];
            $dariBinaan = $binaanSuper !== [] && (int)($s['supervisi_id'] ?? 0) > 0
                && isset($binaanSuper[(int)$s['supervisi_id']]);
            $posisi = (int)($milik[$sid] ?? 0);
            // tampil bila binaan Supervisi, atau akun ini memang sudah ditugaskan di sana
            if (!$dariBinaan && $posisi <= 0) {
                continue;
            }
            $hasil[] = [
                'id' => $sid,
                'nama' => (string)$s['nama'],
                'kecamatan' => (string)($s['kecamatan'] ?? ''),
                'kabupaten' => (string)($s['kabupaten'] ?? ''),
                'status_lembaga' => status_lembaga_sah($s['status_lembaga'] ?? ''),
                'binaan_supervisi' => $dariBinaan,
                'posisi' => $posisi,
                'ketua' => penilai_akun_untuk($sid, '1'),
                'penilai2' => penilai_akun_untuk($sid, '2'),
            ];
        }
        return $hasil;
    }
}

if (!function_exists('penilai_punya_peta_binaan')) {
    /**
     * Apakah akun ini punya PETA BINAAN pada aplikasi Supervisi (akun tertarik dari Supervisi dan
     * terdaftar sebagai pengawas binaan minimal satu lembaga aktif)?
     *
     * Akun yang TIDAK punya peta binaan (mis. akun penilai buatan Super Admin) tetap memakai
     * formulir pilihan sendiri seperti sebelumnya, sehingga tidak ada akun yang "kehilangan" jalan
     * untuk memperoleh penugasan.
     */
    function penilai_punya_peta_binaan(array $user): bool
    {
        $supId = (int)($user['supervisi_id'] ?? 0);
        return $supId > 0 && supervisi_binaan_ids($supId) !== [];
    }
}

if (!function_exists('penilai_binaan_sinkron')) {
    /**
     * Tetapkan pengawas binaan sebagai PENILAI 1 (Ketua Tim) pada madrasah binaannya yang posisinya
     * masih kosong. Idempoten — aman dipanggil setiap kali halaman penilai dibuka.
     *
     * Posisi 1 yang sudah dipegang pengawas binaan LAIN tidak pernah direbut (dilaporkan sebagai
     * "bentrok"); posisi 2 tidak pernah diisi otomatis (ditentukan Ketua Tim).
     *
     * @return array{baru:int, bentrok:string[]}
     */
    function penilai_binaan_sinkron(array $user): array
    {
        $hasil = ['baru' => 0, 'bentrok' => []];
        $uid = (int)($user['id'] ?? 0);
        if ($uid <= 0 || ($user['role'] ?? '') !== 'pengawas') {
            return $hasil;
        }
        foreach (penilai_binaan_pengawas($user) as $b) {
            $sid = (int)$b['id'];
            if ((int)$b['posisi'] > 0) {
                continue;   // sudah punya posisi (1 = Ketua Tim, 2 = ditunjuk Ketua, >=3 = tambahan)
            }
            $pemegang = penilai_posisi_pemegang_user($sid, 1);
            if ($pemegang > 0) {
                $nama = (string)(val('SELECT nama FROM users WHERE id = ?', [$pemegang]) ?? 'pengawas lain');
                $hasil['bentrok'][] = $b['nama'] . ' sudah dinilai oleh ' . $nama;
                continue;
            }
            mulai_transaksi();
            try {
                q('INSERT OR REPLACE INTO pengawas_sekolah (user_id, sekolah_id, posisi, dibuat)
                   VALUES (?, ?, 1, ?)', [$uid, $sid, date('Y-m-d H:i:s')]);
                selesai_transaksi(true);
            } catch (Throwable $t) {
                selesai_transaksi(false);
                continue;
            }
            penilai_sinkron($uid, $sid, 1);
            $hasil['baru']++;
        }
        return $hasil;
    }
}

if (!function_exists('penilai_akun_pengawas_list')) {
    /**
     * "Daftar pengawas yang ada" — akun pengawas AKTIF yang dapat dipilih menjadi Penilai 2.
     *
     * Hanya akun yang jabatannya memang (atau dianggap) pengawas: Penilai 2 menilai Tahun Pertama
     * s.d. Keempat, sehingga jabatan lain (Guru/Tendik/Komite/…) tidak ditawarkan.
     *
     * @param int $kecualiUserId akun yang tidak perlu ditawarkan (Ketua Tim itu sendiri)
     */
    function penilai_akun_pengawas_list(int $kecualiUserId = 0): array
    {
        $hasil = [];
        foreach (all("SELECT id, nama, jabatan, nip, instansi, username, role
                        FROM users
                       WHERE role = 'pengawas' AND aktif = 1 AND id <> ?
                       ORDER BY nama", [$kecualiUserId]) as $u) {
            if (!penilai_jabatan_pengawas($u)) {
                continue;
            }
            $hasil[] = $u;
        }
        return $hasil;
    }
}

if (!function_exists('penilai2_simpan')) {
    /**
     * Ketua Tim (Penilai 1) menentukan PENILAI 2 (Anggota Tim) sebuah madrasah dari daftar pengawas.
     *
     * Ditegakkan di server (bukan hanya di formulir):
     *  - hanya akun yang memegang posisi 1 pada madrasah itu yang boleh menentukan;
     *  - calon wajib akun pengawas yang AKTIF, bukan dirinya sendiri, dan bukan Ketua Tim madrasah itu;
     *  - nilai 0 = "belum ditentukan" (melepas Penilai 2 yang lama);
     *  - satu posisi hanya dipegang satu akun: pemegang posisi 2 yang lama otomatis dilepas.
     *
     * @param int   $ketuaId user_id Ketua Tim yang sedang menyimpan
     * @param array $peta    [sekolah_id => user_id calon penilai 2 (0 = kosongkan)]
     * @return array{disimpan:int, dikosongkan:int, ditolak:string[]}
     */
    function penilai2_simpan(int $ketuaId, array $peta): array
    {
        $hasil = ['disimpan' => 0, 'dikosongkan' => 0, 'ditolak' => []];
        if ($ketuaId <= 0) {
            $hasil['ditolak'][] = 'Akun tidak dikenali.';
            return $hasil;
        }
        foreach ($peta as $sid => $calon) {
            $sid = (int)$sid;
            $calon = (int)$calon;
            if ($sid <= 0) {
                continue;
            }
            $s = one('SELECT id, nama FROM sekolah WHERE id = ? AND aktif = 1', [$sid]);
            if (!$s) {
                continue;
            }
            $nama = (string)$s['nama'];
            if (pengawas_posisi_di($ketuaId, $sid) !== 1) {
                $hasil['ditolak'][] = $nama . ': hanya ' . penilai_posisi_teks('1')
                    . ' yang boleh menentukan ' . penilai_posisi_teks('2') . '.';
                continue;
            }
            $lama = penilai_posisi_pemegang_user($sid, 2);

            // ---------------- kosongkan (belum ditentukan) ----------------
            if ($calon === 0) {
                if ($lama === 0) {
                    continue;
                }
                mulai_transaksi();
                try {
                    q('DELETE FROM pengawas_sekolah WHERE sekolah_id = ? AND posisi = 2', [$sid]);
                    q("DELETE FROM penilai WHERE sekolah_id = ? AND kategori = 'pengawas' AND kode = '2'",
                        [$sid]);
                    selesai_transaksi(true);
                    $hasil['dikosongkan']++;
                } catch (Throwable $t) {
                    selesai_transaksi(false);
                    $hasil['ditolak'][] = $nama . ': gagal mengosongkan (' . $t->getMessage() . ')';
                }
                continue;
            }

            // ---------------- tetapkan calon ----------------
            if ($calon === $ketuaId) {
                $hasil['ditolak'][] = $nama . ': Anda sendiri sudah menjadi ' . penilai_posisi_teks('1')
                    . ', tidak dapat merangkap ' . penilai_posisi_teks('2') . '.';
                continue;
            }
            $akun = one("SELECT * FROM users WHERE id = ? AND role = 'pengawas' AND aktif = 1", [$calon]);
            if (!$akun) {
                $hasil['ditolak'][] = $nama . ': akun penilai yang dipilih tidak ditemukan atau nonaktif.';
                continue;
            }
            if (!penilai_jabatan_pengawas($akun)) {
                $hasil['ditolak'][] = $nama . ': ' . $akun['nama'] . ' bukan pengawas (jabatan '
                    . (trim((string)($akun['jabatan'] ?? '')) !== '' ? $akun['jabatan'] : 'belum diisi')
                    . '), sehingga tidak dapat menjadi ' . penilai_posisi_teks('2') . '.';
                continue;
            }
            if (pengawas_posisi_di($calon, $sid) === 1) {
                $hasil['ditolak'][] = $nama . ': ' . $akun['nama'] . ' sudah menjadi '
                    . penilai_posisi_teks('1') . ' pada madrasah itu.';
                continue;
            }
            if ($lama === $calon) {
                continue;   // tidak berubah
            }
            mulai_transaksi();
            try {
                if ($lama > 0) {
                    q('DELETE FROM pengawas_sekolah WHERE sekolah_id = ? AND posisi = 2 AND user_id <> ?',
                        [$sid, $calon]);
                }
                q('INSERT OR REPLACE INTO pengawas_sekolah (user_id, sekolah_id, posisi, dibuat)
                   VALUES (?, ?, 2, ?)', [$calon, $sid, date('Y-m-d H:i:s')]);
                selesai_transaksi(true);
            } catch (Throwable $t) {
                selesai_transaksi(false);
                $hasil['ditolak'][] = $nama . ': gagal menyimpan (' . $t->getMessage() . ')';
                continue;
            }
            penilai_sinkron($calon, $sid, 2);
            $hasil['disimpan']++;
        }
        return $hasil;
    }
}
