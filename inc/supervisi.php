<?php
/**
 * e-PKKM - TARIK DATA DARI APLIKASI SUPERVISI GURU
 *
 * Permintaan pemilik (v1.11.0):
 *   "Data sekolah, pengawas dan kepala sekolah akan ditarik oleh super admin melalui cek lembaga
 *    yang status aktif di aplikasi supervisi. Yang mendaftar hanya yang jabatan selain pengawas.
 *    Lembaga dan pengawas akan ditarik dari aplikasi supervisi."
 *
 * Pilihan pemilik (jangan diubah tanpa bertanya):
 *   1. yang boleh ditarik HANYA lembaga berstatus AKTIF (berlangganan aktif) pada aplikasi
 *      Supervisi Guru — lembaga yang masih mode terbatas/masa uji (`dibatasi`) TIDAK ikut;
 *   2. akun kepala madrasah & akun pengawas memakai **nama pengguna DAN kata sandi Supervisi**
 *      (hash sandi disalin apa adanya, sama pola e-GURU → CBT). Setelah itu pemilik akun tetap
 *      bebas mengganti sandinya sendiri di e-PKKM; penarikan ULANG tidak menimpa sandi yang
 *      sudah diganti (kolom penanda `users.sandi_supervisi`);
 *   3. pengawas langsung ditugaskan pada posisi Penilai 1 & 2 madrasahnya (urutan dari Supervisi:
 *      pengawas pertama = Penilai 1, kedua = Penilai 2). Hanya dua posisi itu yang tersedia pada
 *      PKKM, pengawas selebihnya tetap dibuatkan akun tetapi tidak ditugaskan (dilaporkan).
 *
 * CARA KERJA
 *   - Basis data Supervisi dibaca BACA-SAJA (mode=ro + PRAGMA query_only) — aplikasi ini TIDAK
 *     pernah menulis apa pun ke sana.
 *   - Madrasah dicocokkan dengan data lokal lewat `supervisi_id`, lalu NPSN, lalu NAMA yang
 *     dinormalkan (huruf kecil tanpa spasi/tanda baca). Menarik ulang tidak pernah menggandakan.
 *   - Akun dicocokkan lewat `supervisi_id` (kolom baru), lalu nama pengguna.
 *   - Kolom yang kosong di Supervisi tidak mengosongkan data e-PKKM yang sudah diisi.
 */
declare(strict_types=1);

/** Berkas basis data Supervisi Guru (boleh diarahkan lewat SUPERVISI_DB_FILE untuk pengujian). */
function supervisi_berkas(): string
{
    $env = getenv('SUPERVISI_DB_FILE');
    if (is_string($env) && trim($env) !== '') {
        return trim($env);
    }
    return dirname(__DIR__, 2) . '/supervisi-guru/data/supervisi.sqlite';
}

/**
 * Koneksi BACA-SAJA ke basis data Supervisi Guru. null = tidak tersedia.
 *
 * Percobaan pertama memakai URI baca-saja; bila gagal dibuka biasa lalu dikunci dengan
 * `PRAGMA query_only = ON` supaya tetap mustahil menulis.
 */
function supervisi_koneksi(): ?PDO
{
    static $pdo = false; // false = belum dicoba, null = gagal
    if ($pdo !== false) {
        return $pdo;
    }
    $pdo = null;
    $berkas = supervisi_berkas();
    if ($berkas === '' || !is_file($berkas) || !is_readable($berkas)) {
        return $pdo;
    }
    foreach (['sqlite:file:' . $berkas . '?mode=ro', 'sqlite:' . $berkas] as $dsn) {
        try {
            $p = new PDO($dsn, null, null, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            $p->exec('PRAGMA query_only = ON');
            // Pastikan tabel inti memang ada (berkas lain / basis data rusak ditolak).
            $p->query('SELECT id, nama FROM sekolah LIMIT 1');
            $p->query('SELECT id, nama, username, role FROM users LIMIT 1');
            $p->query('SELECT user_id, sekolah_id FROM pengawas_sekolah LIMIT 1');
            $pdo = $p;
            return $pdo;
        } catch (Throwable $t) {
            $pdo = null;
        }
    }
    return $pdo;
}

function supervisi_tersedia(): bool
{
    return supervisi_koneksi() !== null;
}

/** Pesan ramah saat basis data Supervisi tidak dapat dibaca. */
function supervisi_pesan_galat(): string
{
    return 'Data aplikasi Supervisi tidak dapat dibaca. Pastikan aplikasi Supervisi Guru tersedia '
        . 'pada server ini (' . supervisi_berkas() . ').';
}

/** Nama untuk pencocokan: huruf kecil, hanya huruf & angka. */
function supervisi_nama_normal(string $nama): string
{
    $nama = strtolower(trim($nama));
    $nama = preg_replace('/[^a-z0-9]+/', '', $nama);
    return (string)$nama;
}

/** Apakah sebuah baris sekolah Supervisi berstatus AKTIF (berlangganan aktif)? */
function supervisi_sekolah_aktif(array $s): bool
{
    if ((int)($s['aktif'] ?? 0) !== 1) {
        return false;
    }
    if (strtolower(trim((string)($s['verifikasi'] ?? 'disetujui'))) !== 'disetujui') {
        return false;
    }
    // `aktivasi` = aktif (sudah berlangganan) / dibatasi (belum aktivasi / masa uji)
    if (strtolower(trim((string)($s['aktivasi'] ?? 'aktif'))) !== 'aktif') {
        return false;
    }
    $sampai = trim((string)($s['aktif_sampai'] ?? ''));
    if ($sampai !== '') {
        $ts = strtotime($sampai);
        if ($ts !== false && $ts < strtotime(date('Y-m-d'))) {
            return false;   // masa aktif sudah lewat
        }
    }
    return true;
}

/** Akun kepala sekolah pada Supervisi untuk satu lembaga (null bila tidak ada). */
function supervisi_kepala(int $sekolahId): ?array
{
    $pdo = supervisi_koneksi();
    if (!$pdo || $sekolahId <= 0) {
        return null;
    }
    try {
        $st = $pdo->prepare("SELECT id, nama, username, password_hash, nip, sekolah_id
                               FROM users
                              WHERE sekolah_id = ? AND role = 'admin' AND aktif = 1
                              ORDER BY id LIMIT 20");
        $st->execute([$sekolahId]);
        $semua = $st->fetchAll();
        if (!$semua) {
            return null;
        }
        // Bila ada beberapa akun kepala, utamakan yang namanya sama dengan kepala_sekolah lembaga.
        $stS = $pdo->prepare('SELECT kepala_sekolah FROM sekolah WHERE id = ?');
        $stS->execute([$sekolahId]);
        $namaKepala = supervisi_nama_normal((string)$stS->fetchColumn());
        if ($namaKepala !== '') {
            foreach ($semua as $u) {
                if (supervisi_nama_normal((string)$u['nama']) === $namaKepala) {
                    return $u;
                }
            }
        }
        return $semua[0];
    } catch (Throwable $t) {
        return null;
    }
}

/** Daftar pengawas yang ditugaskan pada satu lembaga Supervisi. */
function supervisi_pengawas(int $sekolahId): array
{
    $pdo = supervisi_koneksi();
    if (!$pdo || $sekolahId <= 0) {
        return [];
    }
    try {
        // Kolom `foto` TIDAK diambil: tidak dipakai di sini, dan beberapa basis data Supervisi lama
        // belum memilikinya (mengambilnya membuat kueri gagal dan pengawas tidak terdeteksi).
        $st = $pdo->prepare("SELECT u.id, u.nama, u.username, u.password_hash, u.nip, u.aktif
                               FROM pengawas_sekolah ps
                               JOIN users u ON u.id = ps.user_id
                              WHERE ps.sekolah_id = ? AND u.role = 'pengawas'
                              ORDER BY u.nama, u.id");
        $st->execute([$sekolahId]);
        $hasil = [];
        foreach ($st->fetchAll() as $u) {
            if ((int)$u['aktif'] !== 1) {
                continue;
            }
            $hasil[] = $u;
        }
        return $hasil;
    } catch (Throwable $t) {
        return [];
    }
}

/**
 * PETA PENGAWAS BINAAN (v1.12.0, permintaan pemilik).
 *
 * "Pada halaman akun pengawas muncul seluruh daftar madrasah/sekolah binaannya. Dan dia otomatis
 *  menjadi penilai 1 (Ketua Tim)."
 *
 * Mengembalikan daftar id lembaga SUPERVISI yang pengawasnya adalah akun Supervisi `$supervisiUserId`
 * (tabel `pengawas_sekolah` Supervisi = peta binaan). Hanya lembaga berstatus AKTIF yang dipakai,
 * sama dengan kriteria penarikan lembaga.
 *
 * @return array<int,string> id lembaga Supervisi => nama lembaga
 */
function supervisi_binaan_ids(int $supervisiUserId): array
{
    $pdo = supervisi_koneksi();
    if (!$pdo || $supervisiUserId <= 0) {
        return [];
    }
    try {
        $st = $pdo->prepare('SELECT s.id, s.nama, s.aktif, s.verifikasi, s.aktivasi, s.aktif_sampai
                               FROM pengawas_sekolah ps JOIN sekolah s ON s.id = ps.sekolah_id
                              WHERE ps.user_id = ? ORDER BY s.nama');
        $st->execute([$supervisiUserId]);
        $hasil = [];
        foreach ($st->fetchAll() as $s) {
            if (!supervisi_sekolah_aktif($s)) {
                continue;   // lembaga nonaktif / masa uji tidak termasuk binaan yang dinilai
            }
            $hasil[(int)$s['id']] = (string)$s['nama'];
        }
        return $hasil;
    } catch (Throwable $t) {
        return [];
    }
}

/**
 * Daftar lembaga Supervisi yang statusnya AKTIF, dilengkapi keterangan lokal
 * (sudah/belum pernah ditarik, jumlah pengawas, nama kepala).
 */
function supervisi_lembaga_aktif(): array
{
    $pdo = supervisi_koneksi();
    if (!$pdo) {
        return [];
    }
    try {
        $baris = $pdo->query("SELECT id, nama, npsn, nsm, alamat, desa, kecamatan, kabupaten, provinsi,
                                     telepon, email, website, kepala_sekolah, nip_kepala_sekolah,
                                     jenis, aktif, verifikasi, aktivasi, aktif_sampai
                                FROM sekolah ORDER BY nama")->fetchAll();
    } catch (Throwable $t) {
        return [];
    }
    $hasil = [];
    foreach ($baris as $s) {
        if (!supervisi_sekolah_aktif($s)) {
            continue;
        }
        $sid = (int)$s['id'];
        $kepala = supervisi_kepala($sid);
        $pengawas = supervisi_pengawas($sid);
        $lokal = supervisi_sekolah_lokal([
            'id' => $sid,
            'nama' => (string)$s['nama'],
            'npsn' => (string)($s['npsn'] ?? ''),
        ]);
        $s['kepala'] = $kepala;
        $s['pengawas'] = $pengawas;
        $s['lokal'] = $lokal;
        $s['sudah'] = $lokal !== null;
        $hasil[] = $s;
    }
    return $hasil;
}

/**
 * Cari baris `sekolah` lokal untuk sebuah lembaga Supervisi: supervisi_id -> NPSN -> nama.
 *
 * @param array $s baris Supervisi dengan kunci id/nama/npsn
 */
function supervisi_sekolah_lokal(array $s): ?array
{
    $sid = (int)($s['id'] ?? 0);
    if ($sid > 0) {
        $baris = one('SELECT * FROM sekolah WHERE supervisi_id = ?', [$sid]);
        if ($baris) {
            return $baris;
        }
    }
    $npsn = trim((string)($s['npsn'] ?? ''));
    if ($npsn !== '') {
        $baris = one('SELECT * FROM sekolah WHERE TRIM(COALESCE(npsn, \'\')) = ?', [$npsn]);
        if ($baris) {
            return $baris;
        }
    }
    $namaNormal = supervisi_nama_normal((string)($s['nama'] ?? ''));
    if ($namaNormal !== '') {
        foreach (all('SELECT * FROM sekolah') as $baris) {
            if (supervisi_nama_normal((string)$baris['nama']) === $namaNormal) {
                return $baris;
            }
        }
    }
    return null;
}

/** Nama pengguna yang belum dipakai (menambahkan akhiran angka bila perlu). */
function supervisi_username_baru(string $dasar, int $kecualiUserId = 0): string
{
    $dasar = trim($dasar);
    if ($dasar === '' || !daftar_username_sah($dasar)) {
        $dasar = 'akun';
    }
    $dasar = substr($dasar, 0, 34);
    $calon = $dasar;
    $n = 1;
    while (true) {
        $ada = one('SELECT id FROM users WHERE LOWER(username) = LOWER(?)', [$calon]);
        if (!$ada || (int)$ada['id'] === $kecualiUserId) {
            return $calon;
        }
        $n++;
        $calon = $dasar . $n;
    }
}

/**
 * Selaraskan sandi akun lokal dengan sandi Supervisi.
 *
 * Akun BARU selalu memakai sandi Supervisi. Akun LAMA hanya ikut berubah bila sandinya belum
 * pernah diganti sendiri di e-PKKM — dikenali dari `users.sandi_supervisi` (hash Supervisi yang
 * terakhir disalin). Akun yang sandinya sudah berbeda dari penanda itu DIBIARKAN, supaya penarikan
 * ulang tidak mengembalikan sandi yang sudah diganti pemiliknya.
 *
 * @return array ['sandi' => hash yang dipakai, 'berubah' => bool]
 */
function supervisi_sandi_selaras(array $lokal, string $hashSupervisi, bool $baru = false): array
{
    $hashSupervisi = trim($hashSupervisi);
    $sekarang = (string)($lokal['sandi'] ?? '');
    if ($hashSupervisi === '') {
        return ['sandi' => $sekarang, 'berubah' => false];
    }
    if ($baru || $sekarang === '') {
        return ['sandi' => $hashSupervisi, 'berubah' => $sekarang !== $hashSupervisi];
    }
    $penanda = (string)($lokal['sandi_supervisi'] ?? '');
    if ($penanda === '' || $sekarang !== $penanda) {
        return ['sandi' => $sekarang, 'berubah' => false];   // sandi sudah diganti sendiri
    }
    return ['sandi' => $hashSupervisi, 'berubah' => $sekarang !== $hashSupervisi];
}

/**
 * Tarik SATU lembaga (beserta kepala madrasah dan pengawas) dari aplikasi Supervisi.
 *
 * @param int  $supervisiId id sekolah pada basis data Supervisi
 * @param bool $denganPengawas tarik juga akun & penugasan pengawas
 * @return array laporan: ['ok'=>bool,'pesan'=>string,'sekolah'=>..., 'kepala'=>..., 'pengawas'=>[], 'lewat'=>[]]
 */
function tarik_lembaga_supervisi(int $supervisiId, bool $denganPengawas = true): array
{
    $pdo = supervisi_koneksi();
    if (!$pdo) {
        return ['ok' => false, 'pesan' => supervisi_pesan_galat(), 'sekolah' => null, 'kepala' => null,
            'pengawas' => [], 'lewat' => []];
    }
    $st = $pdo->prepare('SELECT * FROM sekolah WHERE id = ?');
    $st->execute([$supervisiId]);
    $s = $st->fetch();
    if (!$s) {
        return ['ok' => false, 'pesan' => 'Lembaga tidak ditemukan pada aplikasi Supervisi.',
            'sekolah' => null, 'kepala' => null, 'pengawas' => [], 'lewat' => []];
    }
    if (!supervisi_sekolah_aktif($s)) {
        return ['ok' => false, 'pesan' => 'Lembaga "' . $s['nama'] . '" tidak berstatus AKTIF di aplikasi Supervisi, '
            . 'sehingga tidak dapat ditarik.', 'sekolah' => null, 'kepala' => null, 'pengawas' => [], 'lewat' => []];
    }

    $lewat = [];
    $lokal = supervisi_sekolah_lokal($s);
    $baru = $lokal === null;
    $sekarang = date('Y-m-d H:i:s');

    // ---------------------------------------------------------------- madrasah
    $medan = [
        'nama'         => trim((string)$s['nama']),
        'npsn'         => trim((string)($s['npsn'] ?? '')),
        'nsm'          => trim((string)($s['nsm'] ?? '')),
        'alamat'       => trim((string)($s['alamat'] ?? '')),
        'desa'         => trim((string)($s['desa'] ?? '')),
        'kecamatan'    => trim((string)($s['kecamatan'] ?? '')),
        'kabupaten'    => trim((string)($s['kabupaten'] ?? '')),
        'provinsi'     => trim((string)($s['provinsi'] ?? '')),
        'telepon'      => trim((string)($s['telepon'] ?? '')),
        'email'        => trim((string)($s['email'] ?? '')),
        'website'      => trim((string)($s['website'] ?? '')),
        'kepala_nama'  => trim((string)($s['kepala_sekolah'] ?? '')),
        'kepala_nip'   => trim((string)($s['nip_kepala_sekolah'] ?? '')),
    ];

    mulai_transaksi();
    try {
        if ($baru) {
            q('INSERT INTO sekolah (nama, npsn, nsm, alamat, desa, kecamatan, kabupaten, provinsi,
                    telepon, email, website, kepala_nama, kepala_nip, status_lembaga,
                    aktif, daftar_status, daftar_pada, supervisi_id, sumber, dibuat, diubah)
               VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,1,?,?,?,?,?,?)', [
                $medan['nama'], $medan['npsn'], $medan['nsm'], $medan['alamat'], $medan['desa'],
                $medan['kecamatan'], $medan['kabupaten'], $medan['provinsi'], $medan['telepon'],
                $medan['email'], $medan['website'], $medan['kepala_nama'], $medan['kepala_nip'],
                PKKM_STATUS_LEMBAGA_BAWAAN, 'disetujui', $sekarang, $supervisiId, 'supervisi',
                $sekarang, $sekarang,
            ]);
            $sekolahId = (int)db()->lastInsertId();
        } else {
            $sekolahId = (int)$lokal['id'];
            // Kolom kosong dari Supervisi TIDAK mengosongkan data e-PKKM yang sudah diisi.
            $set = ['supervisi_id = ?', 'sumber = ?', 'diubah = ?'];
            $args = [$supervisiId, 'supervisi', $sekarang];
            foreach ($medan as $k => $v) {
                if ($v === '') {
                    continue;
                }
                $set[] = $k . ' = ?';
                $args[] = $v;
            }
            $args[] = $sekolahId;
            q('UPDATE sekolah SET ' . implode(', ', $set) . ' WHERE id = ?', $args);
        }
        selesai_transaksi(true);
    } catch (Throwable $t) {
        selesai_transaksi(false);
        return ['ok' => false, 'pesan' => 'Gagal menyimpan madrasah: ' . $t->getMessage(),
            'sekolah' => null, 'kepala' => null, 'pengawas' => [], 'lewat' => []];
    }

    $hasil = [
        'ok'      => true,
        'pesan'   => '',
        'sekolah' => ['id' => $sekolahId, 'nama' => $medan['nama'], 'baru' => $baru],
        'kepala'  => null,
        'pengawas' => [],
        'lewat'   => [],
    ];

    // ---------------------------------------------------------------- kepala madrasah
    $kepala = supervisi_kepala($supervisiId);
    if ($kepala) {
        $hasil['kepala'] = tarik_akun_supervisi($kepala, $sekolahId, 'admin', null, $lewat);
    } elseif ($medan['kepala_nama'] !== '') {
        $lewat[] = 'Akun kepala madrasah tidak tersedia di Supervisi (hanya nama kepala yang tercatat).';
    }

    // ------------------------------------------------------------------- pengawas
    // v1.12.0 (permintaan pemilik): PETA PENGAWAS BINAAN Supervisi menentukan siapa Ketua Tim —
    // "Pada halaman akun pengawas muncul seluruh daftar madrasah/sekolah binaannya. Dan dia
    // otomatis menjadi penilai 1 (Ketua Tim)." Jadi pengawas binaan lembaga ini ditugaskan pada
    // POSISI 1, bukan dibagi otomatis ke 1 & 2. Penilai 2 ditentukan sendiri oleh Ketua Tim.
    if ($denganPengawas) {
        $pengawas = supervisi_pengawas($supervisiId);
        if (count($pengawas) > 1) {
            $lewat[] = 'Lembaga ini punya ' . count($pengawas) . ' pengawas binaan; posisi '
                . penilai_posisi_teks('1') . ' hanya untuk satu orang (yang pertama menurut nama). '
                . 'Pengawas binaan lainnya tetap dibuatkan akun dan dapat ditunjuk sebagai '
                . penilai_posisi_teks('2') . ' oleh Ketua Tim.';
        }
        foreach ($pengawas as $p) {
            $hasil['pengawas'][] = tarik_akun_supervisi($p, null, 'pengawas', $sekolahId, $lewat, 1);
        }
        if (!$pengawas) {
            $lewat[] = 'Belum ada pengawas yang ditugaskan pada lembaga ini di aplikasi Supervisi.';
        }
    }
    $hasil['lewat'] = $lewat;
    return $hasil;
}

/**
 * Buat/perbarui SATU akun (kepala atau pengawas) dari baris akun Supervisi.
 *
 * @param array    $akun       baris users Supervisi (id,nama,username,password_hash,nip)
 * @param int|null $sekolahId  id madrasah lokal (untuk kepala)
 * @param string   $peran      'admin' atau 'pengawas'
 * @param int|null $tugaskan   id madrasah yang diberi penugasan pengawas (null = kepala)
 * @param array    $lewat      daftar catatan (by reference)
 * @param int|null $posisi     posisi penilai (1/2) atau null bila tidak ditugaskan
 */
function tarik_akun_supervisi(array $akun, ?int $sekolahId, string $peran, ?int $tugaskan,
                              array &$lewat, ?int $posisi = null): array
{
    $sidSupervisi = (int)$akun['id'];
    $nama = trim((string)($akun['nama'] ?? ''));
    $usernameSupervisi = trim((string)($akun['username'] ?? ''));
    $hash = (string)($akun['password_hash'] ?? '');
    $nip = trim((string)($akun['nip'] ?? ''));
    $sekarang = date('Y-m-d H:i:s');

    // 1) cocokkan lewat penanda supervisi_id
    $lokal = one('SELECT * FROM users WHERE supervisi_id = ?', [$sidSupervisi]);
    $baru = false;
    // 2) atau lewat nama pengguna Supervisi — HANYA bila perannya sama DAN (untuk kepala
    //    madrasah) akun itu belum terikat madrasah lain. Tanpa syarat terakhir, akun kepala
    //    madrasah lain yang kebetulan bernama pengguna sama akan ikut dipindahkan.
    if (!$lokal && $usernameSupervisi !== '') {
        $calon = one('SELECT * FROM users WHERE LOWER(username) = LOWER(?)', [$usernameSupervisi]);
        if ($calon && (string)$calon['role'] === $peran) {
            $boleh = $peran !== 'admin'
                || (int)($calon['sekolah_id'] ?? 0) === 0
                || (int)$calon['sekolah_id'] === (int)$sekolahId;
            if ($boleh) {
                $lokal = $calon;
            }
        }
    }
    $posisiFinal = 0;

    mulai_transaksi();
    try {
        if (!$lokal) {
            $baru = true;
            $username = supervisi_username_baru($usernameSupervisi !== '' ? $usernameSupervisi : 'akun');
            $sandi = supervisi_sandi_selaras([], $hash, true);
            q('INSERT INTO users (sekolah_id, nama, username, sandi, role, aktif, nip, jabatan, asal,
                    supervisi_id, sandi_supervisi, dibuat)
               VALUES (?,?,?,?,?,1,?,?,?,?,?,?)', [
                $peran === 'admin' ? $sekolahId : null,
                $nama !== '' ? $nama : $username,
                $username,
                $sandi['sandi'] !== '' ? $sandi['sandi'] : password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT),
                $peran,
                $nip,
                $peran === 'pengawas' ? 'Pengawas' : null,
                'supervisi',
                $sidSupervisi,
                $hash !== '' ? $hash : null,
                $sekarang,
            ]);
            $userId = (int)db()->lastInsertId();
            if ($username !== $usernameSupervisi && $usernameSupervisi !== '') {
                $lewat[] = 'Nama pengguna "' . $usernameSupervisi . '" sudah dipakai akun lain; akun '
                    . ($nama !== '' ? $nama : $username) . ' memakai "' . $username . '".';
            }
        } else {
            $userId = (int)$lokal['id'];
            $sandi = supervisi_sandi_selaras($lokal, $hash, false);
            $set = ['sandi = ?', 'sandi_supervisi = ?', 'supervisi_id = ?', 'asal = ?'];
            $args = [$sandi['sandi'], $hash !== '' ? $hash : null, $sidSupervisi, 'supervisi'];
            if ($nama !== '') {
                $set[] = 'nama = ?';
                $args[] = $nama;
            }
            if ($nip !== '') {
                $set[] = 'nip = ?';
                $args[] = $nip;
            }
            if ($peran === 'admin' && $sekolahId) {
                $set[] = 'sekolah_id = ?';
                $args[] = $sekolahId;
            }
            $args[] = $userId;
            q('UPDATE users SET ' . implode(', ', $set) . ' WHERE id = ?', $args);
            if ($sandi['berubah']) {
                $lewat[] = 'Sandi akun ' . ($nama !== '' ? $nama : $usernameSupervisi)
                    . ' diselaraskan dengan sandi Supervisi (belum pernah diganti di e-PKKM).';
            }
        }

        // --------------------------------------------- penugasan pengawas binaan (posisi 1)
        // v1.12.0: pengawas binaan otomatis menjadi PENILAI 1 (Ketua Tim). Bila posisi 1 sudah
        // dipegang pengawas binaan lain, akun tetap dibuat TANPA penugasan (ia dapat ditunjuk
        // sebagai Penilai 2 oleh Ketua Tim) — posisi 2 TIDAK lagi diisi otomatis.
        if ($peran === 'pengawas' && $tugaskan && $posisi !== null) {
            $sudah = (int)(val('SELECT posisi FROM pengawas_sekolah WHERE user_id = ? AND sekolah_id = ?',
                [$userId, $tugaskan]) ?? 0);
            if ($sudah > 0) {
                $posisiFinal = $sudah;
            } else {
                $pemegang = one('SELECT u.nama FROM pengawas_sekolah ps JOIN users u ON u.id = ps.user_id
                                  WHERE ps.sekolah_id = ? AND ps.posisi = ?', [$tugaskan, (int)$posisi]);
                if ($pemegang) {
                    $lewat[] = 'Pengawas binaan ' . ($nama !== '' ? $nama : $usernameSupervisi)
                        . ' belum ditugaskan: ' . penilai_posisi_teks((int)$posisi)
                        . ' sudah dipegang ' . (string)($pemegang['nama'] ?? 'pengawas lain')
                        . '. Akunnya dibuat dan dapat ditunjuk sebagai ' . penilai_posisi_teks('2')
                        . ' oleh Ketua Tim.';
                    $posisi = null;
                }
                if ($posisi !== null) {
                    $posisiFinal = (int)$posisi;
                    q('INSERT OR REPLACE INTO pengawas_sekolah (user_id, sekolah_id, posisi, dibuat)
                       VALUES (?,?,?,?)', [$userId, $tugaskan, (int)$posisi, $sekarang]);
                }
            }
        }
        selesai_transaksi(true);
    } catch (Throwable $t) {
        selesai_transaksi(false);
        $lewat[] = 'Gagal menyimpan akun ' . ($nama !== '' ? $nama : $usernameSupervisi) . ': ' . $t->getMessage();
        return ['id' => 0, 'nama' => $nama, 'username' => $usernameSupervisi, 'baru' => false, 'posisi' => 0];
    }

    // identitas penilai (tabel `penilai`) diselaraskan di luar transaksi (fungsi itu bertransaksi
    // sendiri) — hanya bila penugasan posisi 1/2 berhasil.
    if ($peran === 'pengawas' && $tugaskan && ($posisiFinal ?? 0) > 0) {
        penilai_sinkron($userId, $tugaskan, (int)$posisiFinal);
    }

    $row = one('SELECT id, nama, username FROM users WHERE id = ?', [$userId]);
    return [
        'id'       => $userId,
        'nama'     => (string)($row['nama'] ?? $nama),
        'username' => (string)($row['username'] ?? $usernameSupervisi),
        'baru'     => $baru,
        'posisi'   => (int)($posisiFinal ?? 0),
    ];
}

/**
 * Tarik beberapa lembaga sekaligus.
 *
 * @param int[] $ids id sekolah pada basis data Supervisi
 * @return array daftar laporan per lembaga
 */
function tarik_lembaga_supervisi_banyak(array $ids): array
{
    $hasil = [];
    foreach (array_unique(array_map('intval', $ids)) as $sid) {
        if ($sid <= 0) {
            continue;
        }
        $hasil[] = tarik_lembaga_supervisi($sid);
    }
    return $hasil;
}
