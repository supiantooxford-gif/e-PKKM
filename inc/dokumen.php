<?php
/**
 * e-PKKM - DOKUMEN BUKTI BUTIR PENILAIAN + KOMENTAR PENILAI.
 *
 * Alur (permintaan pemilik):
 *  - kepala madrasah mengunggah dokumen bukti untuk SEMUA butir penilaian, dipilih per TAHUN
 *    (Tahun Pertama s.d. Keempat);
 *  - dokumen itu masuk ke tim penilai (Pengawas I dan Pengawas II) untuk dilihat, diberi
 *    komentar, dan diberi skor nilai pada lembar penilaian.
 *
 * Berkas TIDAK disimpan di server aplikasi: diunggah lewat media proxy platform dan yang
 * disimpan ke basis data hanya URL hasilnya (aturan platform, sama seperti foto/logo).
 *
 * Tabel:
 *  - `dokumen_butir`  (sekolah_id, tahun, butir, url, nama_berkas, ukuran, pengunggah, dibuat)
 *  - `butir_komentar` (sekolah_id, tahun, butir, posisi, isi, penilai_id, penilai_nama, ...)
 *    satu komentar per penilai per butir per tahun (UNIQUE).
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

// ---------------------------------------------------------------------------------------------
// BERKAS: jenis, ukuran, validasi
// ---------------------------------------------------------------------------------------------

if (!function_exists('dok_ekstensi')) {
    /** Ekstensi berkas (huruf kecil, tanpa titik). */
    function dok_ekstensi(string $nama): string
    {
        return strtolower((string)pathinfo($nama, PATHINFO_EXTENSION));
    }
}

if (!function_exists('dok_ekstensi_list')) {
    function dok_ekstensi_list(): array
    {
        return array_merge(DOK_EKSTENSI_GAMBAR, DOK_EKSTENSI_DOKUMEN);
    }
}

if (!function_exists('dok_jenis')) {
    /** 'gambar' atau 'dokumen' (nama tak dikenal -> 'dokumen'). */
    function dok_jenis(string $nama): string
    {
        return in_array(dok_ekstensi($nama), DOK_EKSTENSI_GAMBAR, true) ? 'gambar' : 'dokumen';
    }
}

if (!function_exists('dok_maks_berkas')) {
    /** Batas ukuran satu berkas menurut jenisnya (gambar 15 MB, dokumen 50 MB). */
    function dok_maks_berkas(string $nama): int
    {
        return dok_jenis($nama) === 'gambar' ? DOK_MAKS_GAMBAR : DOK_MAKS_DOKUMEN;
    }
}

if (!function_exists('dok_batas_server')) {
    /**
     * Batas ukuran yang benar-benar diterima SERVER ini (bukan hanya batas platform) untuk satu
     * berkas. $nama berkas dipakai memilih batas jenisnya (gambar 15 MB / dokumen 50 MB).
     * Dipakai untuk memberi tahu angka yang jujur pada formulir - bukan menebak.
     */
    function dok_batas_server(string $nama = ''): int
    {
        $ke = static function (string $v): int {
            $v = trim($v);
            if ($v === '') {
                return 0;
            }
            $satuan = strtolower(substr($v, -1));
            $angka = (float)$v;
            $kali = match ($satuan) {
                'g' => 1024 * 1024 * 1024,
                'm' => 1024 * 1024,
                'k' => 1024,
                default => 1,
            };
            return (int)round($angka * $kali);
        };
        $batas = [];
        $u = $ke((string)ini_get('upload_max_filesize'));
        if ($u > 0) {
            $batas[] = $u;
        }
        $p = $ke((string)ini_get('post_max_size'));
        if ($p > 0) {
            $batas[] = max(1024 * 1024, $p - 512 * 1024);
        }
        $batas[] = $nama !== '' ? dok_maks_berkas($nama) : DOK_MAKS_DOKUMEN;
        return max(1024 * 1024, min($batas));
    }
}

if (!function_exists('dok_ukuran_teks')) {
    function dok_ukuran_teks(int $byte): string
    {
        if ($byte <= 0) {
            return '-';
        }
        if ($byte >= 1048576) {
            return number_format($byte / 1048576, 1, ',', '.') . ' MB';
        }
        return number_format(max(1, (int)round($byte / 1024)), 0, ',', '.') . ' KB';
    }
}

if (!function_exists('dok_validasi')) {
    /**
     * Periksa berkas sebelum dikirim ke media proxy. Bukan hanya ekstensi: ISI berkas ikut
     * diperiksa supaya berkas yang hanya DIGANTI NAMANYA ditolak.
     *
     * @return array{ok:bool, pesan:string}
     */
    function dok_validasi(string $namaAsli, int $ukuran, string $tmp = ''): array
    {
        $ext = dok_ekstensi($namaAsli);
        if (!in_array($ext, dok_ekstensi_list(), true)) {
            return ['ok' => false, 'pesan' => 'Jenis berkas .' . ($ext !== '' ? $ext : '(tanpa ekstensi)')
                . ' tidak didukung. Yang diterima: ' . implode(', ', dok_ekstensi_list()) . '.'];
        }
        if ($ukuran <= 0) {
            return ['ok' => false, 'pesan' => 'Berkas kosong atau gagal diunggah.'];
        }
        $maks = dok_maks_berkas($namaAsli);
        if ($ukuran > $maks) {
            return ['ok' => false, 'pesan' => 'Ukuran berkas ' . $ext . ' maksimal ' . dok_ukuran_teks($maks)
                . ' (berkas Anda ' . dok_ukuran_teks($ukuran) . ').'];
        }
        if ($tmp !== '' && is_file($tmp)) {
            $awal = (string)@file_get_contents($tmp, false, null, 0, 8);
            if (dok_jenis($namaAsli) === 'gambar') {
                if ($ext !== 'svg') {
                    if (function_exists('getimagesize') && @getimagesize($tmp) === false) {
                        return ['ok' => false, 'pesan' => 'Berkas ini sepertinya bukan gambar yang sah.'];
                    }
                } elseif (stripos((string)@file_get_contents($tmp), '<svg') === false) {
                    return ['ok' => false, 'pesan' => 'Berkas SVG ini tidak memuat gambar SVG yang sah.'];
                }
            } elseif ($ext === 'pdf') {
                if (strncmp($awal, '%PDF', 4) !== 0) {
                    return ['ok' => false, 'pesan' => 'Berkas ini bukan PDF yang sah.'];
                }
            } elseif (in_array($ext, ['docx', 'xlsx', 'pptx', 'zip'], true)) {
                // semuanya berupa arsip ZIP: diawali "PK" dan berukuran wajar
                if (strncmp($awal, 'PK', 2) !== 0 || $ukuran < 512) {
                    return ['ok' => false, 'pesan' => 'Berkas ' . $ext . ' ini rusak / bukan dokumen yang sah.'];
                }
            } elseif (in_array($ext, ['csv', 'txt'], true)) {
                $isi = (string)@file_get_contents($tmp, false, null, 0, 4096);
                if (strpos($isi, "\0") !== false) {
                    return ['ok' => false, 'pesan' => 'Berkas ' . $ext . ' ini berisi data yang tidak sah.'];
                }
            }
        }
        return ['ok' => true, 'pesan' => ''];
    }
}

if (!function_exists('dok_label_berkas')) {
    /** Label pendek jenis berkas untuk ditampilkan (PDF, DOCX, GAMBAR, ...). */
    function dok_label_berkas(string $nama): string
    {
        $ext = dok_ekstensi($nama);
        return $ext === '' ? 'BERKAS' : strtoupper($ext);
    }
}

// ---------------------------------------------------------------------------------------------
// DATA DOKUMEN
// ---------------------------------------------------------------------------------------------

if (!function_exists('dokumen_list')) {
    /**
     * Dokumen satu madrasah. $tahun = 0 berarti semua tahun.
     * Hasil: [butir => [baris, ...]] (urut waktu unggah).
     */
    function dokumen_list(int $sekolahId, int $tahun = 0): array
    {
        if ($sekolahId <= 0) {
            return [];
        }
        $sql = 'SELECT * FROM dokumen_butir WHERE sekolah_id = ?';
        $args = [$sekolahId];
        if ($tahun > 0) {
            $sql .= ' AND tahun = ?';
            $args[] = $tahun;
        }
        $sql .= ' ORDER BY id';
        $out = [];
        foreach (all($sql, $args) as $r) {
            $out[(string)$r['butir']][] = $r;
        }
        return $out;
    }
}

if (!function_exists('dokumen_jumlah')) {
    /** Jumlah berkas (satu madrasah; $tahun = 0 -> semua tahun). */
    function dokumen_jumlah(int $sekolahId, int $tahun = 0): int
    {
        if ($sekolahId <= 0) {
            return 0;
        }
        if ($tahun > 0) {
            return (int)val('SELECT COUNT(*) FROM dokumen_butir WHERE sekolah_id = ? AND tahun = ?', [$sekolahId, $tahun]);
        }
        return (int)val('SELECT COUNT(*) FROM dokumen_butir WHERE sekolah_id = ?', [$sekolahId]);
    }
}

if (!function_exists('dokumen_jumlah_per_tahun')) {
    /** [tahun => jumlah berkas] untuk chip pemilih tahun. */
    function dokumen_jumlah_per_tahun(int $sekolahId): array
    {
        $out = array_fill_keys(array_keys(PKKM_TAHUN_LIST), 0);
        if ($sekolahId <= 0) {
            return $out;
        }
        foreach (all('SELECT tahun, COUNT(*) AS n FROM dokumen_butir WHERE sekolah_id = ? GROUP BY tahun', [$sekolahId]) as $r) {
            $out[(int)$r['tahun']] = (int)$r['n'];
        }
        return $out;
    }
}

if (!function_exists('dokumen_butir_terisi')) {
    /** Berapa butir (pada satu tahun) yang sudah punya dokumen. */
    function dokumen_butir_terisi(int $sekolahId, int $tahun): int
    {
        if ($sekolahId <= 0) {
            return 0;
        }
        return (int)val('SELECT COUNT(DISTINCT butir) FROM dokumen_butir WHERE sekolah_id = ? AND tahun = ?',
            [$sekolahId, $tahun]);
    }
}

if (!function_exists('dokumen_by_id')) {
    function dokumen_by_id(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        return one('SELECT * FROM dokumen_butir WHERE id = ?', [$id]);
    }
}

if (!function_exists('dokumen_simpan')) {
    /**
     * Simpan satu dokumen hasil unggahan. Batas per butir diperiksa di sini juga (server-side).
     * @return array{ok:bool, pesan:string}
     */
    function dokumen_simpan(int $sekolahId, int $tahun, string $butir, string $url, string $namaBerkas,
                            int $ukuran, string $pengunggah, ?int $pengunggahId): array
    {
        if ($sekolahId <= 0 || !array_key_exists($tahun, PKKM_TAHUN_LIST) || trim($butir) === '' || $url === '') {
            return ['ok' => false, 'pesan' => 'Data dokumen tidak lengkap.'];
        }
        $jml = (int)val('SELECT COUNT(*) FROM dokumen_butir WHERE sekolah_id = ? AND tahun = ? AND butir = ?',
            [$sekolahId, $tahun, $butir]);
        if ($jml >= DOK_MAKS_PER_BUTIR) {
            return ['ok' => false, 'pesan' => 'Butir ini sudah punya ' . DOK_MAKS_PER_BUTIR
                . ' dokumen (batas maksimal). Hapus salah satu bila ingin mengunggah yang baru.'];
        }
        q('INSERT INTO dokumen_butir (sekolah_id, tahun, butir, url, nama_berkas, ukuran, pengunggah, pengunggah_id, dibuat)
           VALUES (?,?,?,?,?,?,?,?,?)',
            [$sekolahId, $tahun, $butir, $url, potong_teks($namaBerkas, DOK_NAMA_MAKS), $ukuran,
                $pengunggah !== '' ? $pengunggah : null, $pengunggahId, date('Y-m-d H:i:s')]);
        return ['ok' => true, 'pesan' => ''];
    }
}

if (!function_exists('dokumen_hapus')) {
    /** Hapus satu dokumen; barisnya WAJIB milik madrasah yang sedang dibuka (anti-IDOR). */
    function dokumen_hapus(int $id, int $sekolahId): bool
    {
        $d = dokumen_by_id($id);
        if (!$d || (int)$d['sekolah_id'] !== $sekolahId) {
            return false;
        }
        q('DELETE FROM dokumen_butir WHERE id = ?', [$id]);
        return true;
    }
}

if (!function_exists('dokumen_unggah_berkas')) {
    /**
     * Proses berkas dari sebuah formulir ($_FILES[$field], boleh satu berkas atau beberapa
     * dengan name="...[]") lalu unggah ke media proxy. Semua berkas yang lolos disimpan.
     *
     * @return array{ok:int, gagal:array<int,string>}  ok = jumlah berkas tersimpan
     */
    function dokumen_unggah_berkas(int $sekolahId, int $tahun, string $butir, string $field = 'berkas'): array
    {
        $f = $_FILES[$field] ?? null;
        if (!is_array($f) || !isset($f['name'])) {
            return ['ok' => 0, 'gagal' => ['Tidak ada berkas yang dipilih.']];
        }
        $nama = is_array($f['name']) ? $f['name'] : [$f['name']];
        $tmp = is_array($f['tmp_name']) ? $f['tmp_name'] : [$f['tmp_name']];
        $ukuran = is_array($f['size']) ? $f['size'] : [$f['size']];
        $galat = is_array($f['error']) ? $f['error'] : [$f['error']];

        $u = current_user();
        $berhasil = 0;
        $pesan = [];
        $sisa = max(0, DOK_MAKS_PER_BUTIR - (int)val(
            'SELECT COUNT(*) FROM dokumen_butir WHERE sekolah_id = ? AND tahun = ? AND butir = ?',
            [$sekolahId, $tahun, $butir]
        ));
        foreach ($nama as $i => $nm) {
            $nm = trim((string)$nm);
            if ($nm === '') {
                continue;
            }
            if ($sisa <= 0) {
                $pesan[] = 'Batas ' . DOK_MAKS_PER_BUTIR . ' dokumen per butir sudah tercapai - "' . $nm . '" tidak diunggah.';
                continue;
            }
            $kode = (int)($galat[$i] ?? UPLOAD_ERR_NO_FILE);
            if ($kode !== UPLOAD_ERR_OK) {
                $pesan[] = ($kode === UPLOAD_ERR_INI_SIZE || $kode === UPLOAD_ERR_FORM_SIZE)
                    ? '"' . $nm . '" melebihi batas server (' . ini_get('upload_max_filesize') . ').'
                    : '"' . $nm . '" gagal diunggah (kode ' . $kode . ').';
                continue;
            }
            $size = (int)($ukuran[$i] ?? 0);
            $path = (string)($tmp[$i] ?? '');
            $cek = dok_validasi($nm, $size, $path);
            if (empty($cek['ok'])) {
                $pesan[] = (string)$cek['pesan'];
                continue;
            }
            $bytes = @file_get_contents($path);
            if ($bytes === false || $bytes === '') {
                $pesan[] = '"' . $nm . '" tidak dapat dibaca di server.';
                continue;
            }
            $hasil = media_upload($bytes, $nm);
            if (empty($hasil['ok'])) {
                $pesan[] = '"' . $nm . '": ' . (string)($hasil['pesan'] ?? 'unggahan ditolak.');
                continue;
            }
            $simpan = dokumen_simpan($sekolahId, $tahun, $butir, (string)$hasil['url'], $nm, $size,
                (string)($u['nama'] ?? ''), $u ? (int)$u['id'] : null);
            if (empty($simpan['ok'])) {
                $pesan[] = (string)$simpan['pesan'];
                continue;
            }
            $berhasil++;
            $sisa--;
        }
        return ['ok' => $berhasil, 'gagal' => $pesan];
    }
}

// ---------------------------------------------------------------------------------------------
// KOMENTAR PENILAI PER BUTIR
// ---------------------------------------------------------------------------------------------

if (!function_exists('komentar_butir_map')) {
    /** [butir][posisi] => baris komentar untuk satu madrasah + tahun. */
    function komentar_butir_map(int $sekolahId, int $tahun): array
    {
        if ($sekolahId <= 0 || $tahun <= 0) {
            return [];
        }
        $out = [];
        foreach (all('SELECT * FROM butir_komentar WHERE sekolah_id = ? AND tahun = ? ORDER BY id',
            [$sekolahId, $tahun]) as $r) {
            $out[(string)$r['butir']][(string)$r['posisi']] = $r;
        }
        return $out;
    }
}

if (!function_exists('komentar_butir_jumlah')) {
    /** Jumlah butir yang sudah dikomentari seorang penilai (untuk ringkasan). */
    function komentar_butir_jumlah(int $sekolahId, int $tahun, string $posisi): int
    {
        if ($sekolahId <= 0 || $tahun <= 0) {
            return 0;
        }
        return (int)val("SELECT COUNT(*) FROM butir_komentar WHERE sekolah_id = ? AND tahun = ? AND posisi = ?
                          AND TRIM(COALESCE(isi,'')) <> ''", [$sekolahId, $tahun, $posisi]);
    }
}

if (!function_exists('komentar_butir_simpan')) {
    /**
     * Simpan komentar seorang penilai untuk banyak butir sekaligus.
     * Komentar kosong = komentarnya dihapus. Butir di luar instrumen tahun itu diabaikan.
     *
     * @param array<string,string> $isi [kode butir => isi komentar]
     * @return int jumlah komentar yang tersimpan
     */
    function komentar_butir_simpan(int $sekolahId, int $tahun, string $posisi, array $isi): int
    {
        if ($sekolahId <= 0 || !array_key_exists($tahun, PKKM_TAHUN_LIST)) {
            return 0;
        }
        // posisi 1/2 (Pengawas I & II) atau posisi >= 3 (penilai tambahan Tahun Keempat)
        if (!array_key_exists($posisi, PKKM_PENILAI_PENGAWAS) && !penilai_posisi_tambahan($posisi)) {
            return 0;
        }
        $valid = butir_tahun($tahun);
        $u = current_user();
        $jumlah = 0;
        $kini = date('Y-m-d H:i:s');
        // peta komentar yang sudah ada (satu query) - sekaligus melewati butir yang tidak berubah
        $ada = [];
        foreach (all('SELECT id, butir, isi FROM butir_komentar WHERE sekolah_id = ? AND tahun = ? AND posisi = ?',
            [$sekolahId, $tahun, $posisi]) as $r) {
            $ada[(string)$r['butir']] = $r;
        }
        foreach ($isi as $butir => $teks) {
            $butir = (string)$butir;
            if (!isset($valid[$butir])) {
                continue;
            }
            $teks = potong_teks(trim((string)$teks), DOK_KOMENTAR_MAKS);
            $baris = $ada[$butir] ?? null;
            if ($teks === '') {
                if ($baris) {
                    q('DELETE FROM butir_komentar WHERE id = ?', [(int)$baris['id']]);
                }
                continue;
            }
            if ($baris) {
                if (trim((string)$baris['isi']) === $teks) {
                    $jumlah++;
                    continue;
                }
                q('UPDATE butir_komentar SET isi = ?, penilai_id = ?, penilai_nama = ?, diubah = ? WHERE id = ?',
                    [$teks, $u['id'] ?? null, $u['nama'] ?? null, $kini, (int)$baris['id']]);
            } else {
                q('INSERT INTO butir_komentar (sekolah_id, tahun, butir, posisi, isi, penilai_id, penilai_nama, dibuat, diubah)
                   VALUES (?,?,?,?,?,?,?,?,?)',
                    [$sekolahId, $tahun, $butir, $posisi, $teks, $u['id'] ?? null, $u['nama'] ?? null, $kini, $kini]);
            }
            $jumlah++;
        }
        return $jumlah;
    }
}

if (!function_exists('komentar_butir_teks')) {
    /** Isi komentar satu penilai pada satu butir (string kosong bila belum ada). */
    function komentar_butir_teks(array $map, string $butir, string $posisi): string
    {
        return trim((string)($map[$butir][$posisi]['isi'] ?? ''));
    }
}
