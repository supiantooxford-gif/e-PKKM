<?php
/**
 * e-PKKM - skema basis data (idempoten).
 *
 * Pola penjaga (sama seperti e-GURU/e-BOS):
 *  - data/.schema_ok berisi SCHEMA_VERSION yang sudah dijalankan.
 *  - skema_terkini() memeriksa penanda DAN keberadaan tabel/kolom wajib, sehingga
 *    penanda yang lebih baru daripada isi basis data bisa memulihkan diri sendiri.
 *  - Seluruh isi ensure_schema() dijalankan dalam SATU transaksi (BEGIN IMMEDIATE).
 */

const SCHEMA_VERSION = '12';

/** Tabel yang WAJIB ada. */
const SCHEMA_TABEL_WAJIB = ['app_pengaturan', 'sekolah', 'users', 'pengawas_sekolah', 'penilai', 'penilaian',
    'penilaian_butir', 'landing_slider', 'landing_fitur', 'landing_unggulan', 'landing_testimoni', 'landing_pesan',
    'dokumen_butir', 'butir_komentar', 'jabatan_penilai', 'depan_slider',
    // v11 - Log Aktivitas (Super Admin): riwayat masuk/keluar/gagal + daftar sesi online
    'log_aktivitas', 'sesi_aktif'];

/** Kolom yang WAJIB ada (boleh daftar per tabel). */
const SCHEMA_KOLOM_WAJIB = [
    'sekolah' => ['tahun_1', 'tahun_2', 'tahun_3', 'tahun_4', 'tanggal_1', 'tempat',
        'daftar_status', 'daftar_pada', 'daftar_alasan', 'status_lembaga',
        // v9 - penanda asal data (tarik dari aplikasi Supervisi)
        'supervisi_id', 'sumber'],
    'users' => ['foto', 'nip', 'jabatan', 'instansi', 'kab_kota', 'hp', 'asal',
        // v9 - penanda akun hasil tarikan Supervisi + sandi Supervisi terakhir
        'supervisi_id', 'sandi_supervisi'],
    'penilaian' => ['status', 'diisi_oleh'],
    'penilai' => ['kategori', 'kode', 'user_id'],
    // v12 - dokumen butir boleh berupa TAUTAN Google Drive (tautan = 1), bukan hanya berkas unggahan
    'dokumen_butir' => ['tautan'],
];

if (!function_exists('db_add_column')) {
    function db_add_column(?string $tabel, string $kolom, string $definisi): void
    {
        $tabel = (string)$tabel;
        $pdo = db();
        $ada = false;
        foreach ($pdo->query('PRAGMA table_info(' . $tabel . ')') as $c) {
            if (strcasecmp($c['name'], $kolom) === 0) {
                $ada = true;
                break;
            }
        }
        if (!$ada) {
            $pdo->exec('ALTER TABLE ' . $tabel . ' ADD COLUMN ' . $kolom . ' ' . $definisi);
        }
    }
}

if (!function_exists('tabel_ada')) {
    function tabel_ada(string $tabel): bool
    {
        $r = val("SELECT name FROM sqlite_master WHERE type = 'table' AND name = ?", [$tabel]);
        return $r !== null;
    }
}

if (!function_exists('kolom_ada')) {
    function kolom_ada(string $tabel, string $kolom): bool
    {
        foreach (db()->query('PRAGMA table_info(' . $tabel . ')') as $c) {
            if (strcasecmp($c['name'], $kolom) === 0) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('skema_terkini')) {
    /** true bila penanda cocok DAN seluruh tabel/kolom wajib benar-benar ada. */
    function skema_terkini(): bool
    {
        $berkas = DATA_DIR . '/.schema_ok';
        $isi = is_file($berkas) ? trim((string)@file_get_contents($berkas)) : '';
        if ($isi !== SCHEMA_VERSION) {
            return false;
        }
        try {
            foreach (SCHEMA_TABEL_WAJIB as $t) {
                if (!tabel_ada($t)) {
                    return false;
                }
            }
            foreach (SCHEMA_KOLOM_WAJIB as $t => $kolom) {
                if (!tabel_ada($t)) {
                    return false;
                }
                foreach ((array)$kolom as $k) {
                    if (!kolom_ada($t, $k)) {
                        return false;
                    }
                }
            }
        } catch (Throwable $t) {
            return false;
        }
        return true;
    }
}

if (!function_exists('jabatan_dedup_sekali')) {
    /**
     * v8 - hapus jabatan DUPLIKAT hasil penyemaian berulang (lihat catatan di ensure_schema()).
     * Aman: hanya baris yang TIDAK dipakai akun dan punya pasangan versi re-nama "Nama (…)".
     * Sekaligus menulis penanda `jabatan_slot_v1` agar penyemaian jabatan baru tidak berulang.
     */
    function jabatan_dedup_sekali(PDO $pdo): void
    {
        $stFlag = $pdo->prepare('SELECT nilai FROM app_pengaturan WHERE kunci = ?');
        $stFlag->execute(['jabatan_dedup_v1']);
        $sudah = $stFlag->fetchColumn() !== false;

        // penanda seeding jabatan 7 penilai: pastikan ada supaya tidak menambah ulang nama yang dihapus
        $stFlag->execute(['jabatan_slot_v1']);
        if ($stFlag->fetchColumn() === false) {
            $pdo->prepare('INSERT INTO app_pengaturan (kunci, nilai) VALUES (?, ?)
                            ON CONFLICT(kunci) DO UPDATE SET nilai = excluded.nilai')
                ->execute(['jabatan_slot_v1', date('Y-m-d H:i:s')]);
        }
        if ($sudah) {
            return;
        }

        $stCari = $pdo->prepare('SELECT id, nama FROM jabatan_penilai WHERE LOWER(TRIM(nama)) = LOWER(?)');
        $stPasangan = $pdo->prepare('SELECT COUNT(*) FROM jabatan_penilai
                                      WHERE id <> ? AND LOWER(TRIM(nama)) LIKE LOWER(?)');
        $stPakai = $pdo->prepare('SELECT COUNT(*) FROM users WHERE LOWER(TRIM(jabatan)) = LOWER(?)');
        $stHapus = $pdo->prepare('DELETE FROM jabatan_penilai WHERE id = ?');
        foreach (['Kabid', 'Kabag', 'Kasie', 'Tendik', 'Yayasan'] as $nama) {
            $stCari->execute([$nama]);
            $baris = $stCari->fetch(PDO::FETCH_ASSOC);
            if (!$baris) {
                continue;
            }
            $stPakai->execute([$nama]);
            if ((int)$stPakai->fetchColumn() > 0) {
                continue;   // dipakai akun -> jangan disentuh
            }
            $stPasangan->execute([(int)$baris['id'], $nama . ' (%']);
            if ((int)$stPasangan->fetchColumn() > 0) {
                $stHapus->execute([(int)$baris['id']]);
            }
        }
        $pdo->prepare('INSERT INTO app_pengaturan (kunci, nilai) VALUES (?, ?)
                        ON CONFLICT(kunci) DO UPDATE SET nilai = excluded.nilai')
            ->execute(['jabatan_dedup_v1', date('Y-m-d H:i:s')]);
    }
}

if (!function_exists('migrasi_penilai_tambahan')) {
    /**
     * v6 - Pindahkan penugasan LAMA milik akun berjabatan BUKAN Pengawas dari posisi 1/2 ke
     * POSISI TAMBAHAN (3, 4, …).
     *
     * Latar belakang (permintaan pemilik): penilai selain jabatan Pengawas hanya menilai Tahun
     * Keempat, dan Tahun Keempat boleh dinilai LEBIH DARI DUA penilai. Posisi 1 & 2 karena itu
     * disediakan untuk Pengawas I & II, sedangkan jabatan lain memakai posisi tambahan.
     *
     * Dijalankan SEKALI (penanda app_pengaturan `penilai_tambahan_v1`), tanpa menghapus data:
     * baris `pengawas_sekolah`, `penilaian` (Tahun Keempat), dan `penilai` milik akun itu ikut
     * dipindahkan ke kategori `tambahan` dengan kode = nomor posisi barunya.
     */
    function migrasi_penilai_tambahan(PDO $pdo): void
    {
        $st = $pdo->prepare('SELECT nilai FROM app_pengaturan WHERE kunci = ?');
        $st->execute(['penilai_tambahan_v1']);
        if ($st->fetchColumn() !== false) {
            return;
        }
        // nomor posisi berikutnya: hindari SEMUA nomor yang sudah dipakai, baik oleh penugasan
        // maupun oleh sisa baris `penilaian`/`penilai` kategori tambahan (mis. akun penilainya
        // sudah dihapus pemilik) — supaya tidak bertabrakan dengan UNIQUE dan tidak mewarisi skor lama.
        $stSekolah = $pdo->prepare('SELECT COALESCE(MAX(posisi), 2) FROM pengawas_sekolah WHERE sekolah_id = ?');
        $stMaksPenilaian = $pdo->prepare("SELECT COALESCE(MAX(CAST(kode_penilai AS INTEGER)), 2) FROM penilaian
                                           WHERE sekolah_id = ? AND kategori = 'tambahan'");
        $stMaksPenilai = $pdo->prepare("SELECT COALESCE(MAX(CAST(kode AS INTEGER)), 2) FROM penilai
                                         WHERE sekolah_id = ? AND kategori = 'tambahan'");
        $stNaik = $pdo->prepare('UPDATE pengawas_sekolah SET posisi = ? WHERE user_id = ? AND sekolah_id = ? AND posisi = ?');
        $stCekPenilaian = $pdo->prepare("SELECT COUNT(*) FROM penilaian
                                         WHERE sekolah_id = ? AND kategori = 'tambahan' AND kode_penilai = ?");
        $stPindahPenilaian = $pdo->prepare("UPDATE penilaian SET kategori = 'tambahan', kode_penilai = ?
                                             WHERE sekolah_id = ? AND kategori = 'pengawas' AND kode_penilai = ?");
        $stPindahPenilai = $pdo->prepare("UPDATE penilai SET kategori = 'tambahan', kode = ?
                                           WHERE sekolah_id = ? AND kategori = 'pengawas' AND kode = ?
                                             AND (user_id = ? OR user_id IS NULL)");
        // tabel `penilai` ber-UNIQUE (sekolah_id, kategori, kode) -> periksa apakah baris tujuan sudah ada
        $stCekPenilai = $pdo->prepare("SELECT COUNT(*) FROM penilai
                                        WHERE sekolah_id = ? AND kategori = 'tambahan' AND kode = ?");
        $stHapusPenilai = $pdo->prepare("DELETE FROM penilai
                                          WHERE sekolah_id = ? AND kategori = 'pengawas' AND kode = ?
                                            AND (user_id = ? OR user_id IS NULL)");

        $daftar = $pdo->query("SELECT ps.user_id, ps.sekolah_id, ps.posisi
                                 FROM pengawas_sekolah ps JOIN users u ON u.id = ps.user_id
                                WHERE u.role = 'pengawas' AND ps.posisi IN (1, 2)
                                  AND TRIM(COALESCE(u.jabatan, '')) <> ''
                                  AND LOWER(TRIM(u.jabatan)) NOT LIKE 'pengawas%'
                                ORDER BY ps.sekolah_id, ps.posisi")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($daftar as $r) {
            $sid = (int)$r['sekolah_id'];
            $lama = (int)$r['posisi'];
            $uid = (int)$r['user_id'];
            // Satu baris gagal TIDAK BOLEH menggagalkan seluruh migrasi (kalau gagal, transaksi
            // skema ikut batal dan aplikasi tidak bisa dibuka sama sekali).
            try {
                $stSekolah->execute([$sid]);
                $stMaksPenilaian->execute([$sid]);
                $stMaksPenilai->execute([$sid]);
                $maks = max((int)$stSekolah->fetchColumn(), (int)$stMaksPenilaian->fetchColumn(),
                    (int)$stMaksPenilai->fetchColumn());
                $baru = max(PKKM_POSISI_TAMBAHAN, $maks + 1);
                $stNaik->execute([$baru, $uid, $sid, $lama]);
                $stCekPenilaian->execute([$sid, (string)$baru]);
                if ((int)$stCekPenilaian->fetchColumn() === 0) {
                    $stPindahPenilaian->execute([(string)$baru, $sid, (string)$lama]);
                }
                $stCekPenilai->execute([$sid, (string)$baru]);
                if ((int)$stCekPenilai->fetchColumn() > 0) {
                    // sudah ada identitas penilai pada posisi tambahan itu -> cukup buang baris lama
                    $stHapusPenilai->execute([$sid, (string)$lama, $uid]);
                } else {
                    $stPindahPenilai->execute([(string)$baru, $sid, (string)$lama, $uid]);
                }
            } catch (Throwable $t) {
                error_log('e-PKKM migrasi penilai tambahan dilewati (sekolah ' . $sid . ', penilai '
                    . $uid . '): ' . $t->getMessage());
                continue;
            }
        }
        $pdo->prepare('INSERT INTO app_pengaturan (kunci, nilai) VALUES (?, ?)
                        ON CONFLICT(kunci) DO UPDATE SET nilai = excluded.nilai')
            ->execute(['penilai_tambahan_v1', date('Y-m-d H:i:s')]);
    }
}

if (!function_exists('ensure_schema')) {
    function ensure_schema(): void
    {
        if (skema_terkini()) {
            return;
        }
        if (!is_dir(DATA_DIR)) {
            @mkdir(DATA_DIR, 0777, true);
        }
        $pdo = db();
        $pdo->exec('PRAGMA foreign_keys = OFF');
        $pdo->exec('BEGIN IMMEDIATE');
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS app_pengaturan (
                kunci TEXT PRIMARY KEY,
                nilai TEXT
            )");

            $pdo->exec("CREATE TABLE IF NOT EXISTS sekolah (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                nama TEXT NOT NULL,
                npsn TEXT,
                nsm TEXT,
                alamat TEXT,
                desa TEXT,
                kecamatan TEXT,
                kabupaten TEXT,
                provinsi TEXT,
                telepon TEXT,
                email TEXT,
                website TEXT,
                kepala_nama TEXT,
                kepala_nip TEXT,
                kepala_gender TEXT,
                kepala_lahir TEXT,
                kepala_pangkat TEXT,
                kepala_tmt TEXT,
                kepala_masa_kerja TEXT,
                kepala_nuptk TEXT,
                kepala_pendidikan TEXT,
                tahun_1 INTEGER,
                tahun_2 INTEGER,
                tahun_3 INTEGER,
                tahun_4 INTEGER,
                tanggal_1 TEXT,
                tanggal_2 TEXT,
                tanggal_3 TEXT,
                tanggal_4 TEXT,
                tempat TEXT,
                aktif INTEGER NOT NULL DEFAULT 1,
                daftar_status TEXT NOT NULL DEFAULT 'disetujui',
                daftar_pada TEXT,
                daftar_alasan TEXT,
                status_lembaga TEXT NOT NULL DEFAULT 'swasta',
                supervisi_id INTEGER,
                sumber TEXT NOT NULL DEFAULT 'manual',
                dibuat TEXT,
                diubah TEXT
            )");

            $pdo->exec("CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                sekolah_id INTEGER REFERENCES sekolah(id) ON DELETE CASCADE,
                nama TEXT NOT NULL,
                username TEXT NOT NULL UNIQUE,
                sandi TEXT NOT NULL,
                role TEXT NOT NULL DEFAULT 'pengawas',
                aktif INTEGER NOT NULL DEFAULT 1,
                foto TEXT,
                nip TEXT,
                jabatan TEXT,
                instansi TEXT,
                kab_kota TEXT,
                hp TEXT,
                asal TEXT,
                supervisi_id INTEGER,
                sandi_supervisi TEXT,
                dibuat TEXT
            )");

            // v11 - LOG AKTIVITAS (permintaan pemilik: "Pada akun Super Admin tambahkan fitur log
            // aktifitas seperti aplikasi e-Guru termasuk hapus riwayatnya").
            // Nama akun & nama madrasah disimpan sebagai SNAPSHOT supaya riwayat tetap terbaca
            // walau akun/madrasahnya kelak dihapus (relasinya SET NULL, bukan CASCADE).
            $pdo->exec("CREATE TABLE IF NOT EXISTS log_aktivitas (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
                nama TEXT,
                username TEXT,
                role TEXT,
                sekolah_id INTEGER REFERENCES sekolah(id) ON DELETE SET NULL,
                sekolah TEXT,
                aktivitas TEXT NOT NULL,
                keterangan TEXT,
                ip TEXT,
                peramban TEXT,
                waktu TEXT NOT NULL
            )");
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_log_waktu ON log_aktivitas (waktu)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_log_aktivitas ON log_aktivitas (aktivitas)');

            // Sesi yang masih berjalan → dasar daftar "sedang online". `id` adalah HASH id sesi;
            // id sesi yang sebenarnya tidak pernah disimpan.
            $pdo->exec("CREATE TABLE IF NOT EXISTS sesi_aktif (
                id TEXT PRIMARY KEY,
                user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                ip TEXT,
                peramban TEXT,
                mulai TEXT NOT NULL,
                terakhir TEXT NOT NULL
            )");
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_sesi_aktif_terakhir ON sesi_aktif (terakhir)');

            // v10 - SLIDER GAMBAR HALAMAN DEPAN (permintaan pemilik): gambar yang diisi Super Admin
            // pada area ilustrasi halaman masuk. Hanya URL hasil media proxy yang disimpan.
            $pdo->exec("CREATE TABLE IF NOT EXISTS depan_slider (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                foto TEXT NOT NULL,
                judul TEXT,
                keterangan TEXT,
                urutan INTEGER NOT NULL DEFAULT 10,
                aktif INTEGER NOT NULL DEFAULT 1,
                dibuat TEXT,
                diubah TEXT
            )");
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_depan_slider ON depan_slider (aktif, urutan, id)');

            // Daftar JABATAN penilai (GLOBAL, diisi Super Admin). Jabatan dipilih penilai saat
            // mendaftar sendiri lewat halaman `daftar_penilai.php`.
            $pdo->exec("CREATE TABLE IF NOT EXISTS jabatan_penilai (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                nama TEXT NOT NULL UNIQUE,
                urutan INTEGER NOT NULL DEFAULT 10,
                aktif INTEGER NOT NULL DEFAULT 1,
                dibuat TEXT
            )");
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_jabatan_penilai ON jabatan_penilai (aktif, urutan, id)');

            $pdo->exec("CREATE TABLE IF NOT EXISTS pengawas_sekolah (
                user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                sekolah_id INTEGER NOT NULL REFERENCES sekolah(id) ON DELETE CASCADE,
                posisi INTEGER NOT NULL DEFAULT 1,
                dibuat TEXT,
                PRIMARY KEY (user_id, sekolah_id)
            )");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_pengawas_sekolah ON pengawas_sekolah (sekolah_id, posisi)");
            // Satu posisi pada satu madrasah hanya boleh dipegang satu akun penilai (aturan pemilik).
            // Dibuat dalam try/catch: basis data lama yang (karena kesalahan input) punya baris ganda
            // tetap bisa dimigrasi, dan aturan tetap ditegakkan oleh kode aplikasi.
            try {
                $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS ux_pengawas_posisi ON pengawas_sekolah (sekolah_id, posisi)');
            } catch (Throwable $t) {
                // diabaikan (data lama masih berisi posisi ganda)
            }

            $pdo->exec("CREATE TABLE IF NOT EXISTS penilai (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                sekolah_id INTEGER NOT NULL REFERENCES sekolah(id) ON DELETE CASCADE,
                kategori TEXT NOT NULL,
                kode TEXT NOT NULL,
                user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
                nama TEXT,
                nip TEXT,
                jabatan TEXT,
                pangkat TEXT,
                unit_kerja TEXT,
                kabupaten TEXT,
                provinsi TEXT,
                tempat TEXT,
                tanggal_1 TEXT,
                tanggal_2 TEXT,
                tanggal_3 TEXT,
                tanggal_4 TEXT,
                tanggal TEXT,
                dibuat TEXT,
                diubah TEXT,
                UNIQUE (sekolah_id, kategori, kode)
            )");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_penilai_sekolah ON penilai (sekolah_id, kategori)");

            $pdo->exec("CREATE TABLE IF NOT EXISTS penilaian (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                sekolah_id INTEGER NOT NULL REFERENCES sekolah(id) ON DELETE CASCADE,
                kategori TEXT NOT NULL,
                kode_penilai TEXT NOT NULL,
                tahun INTEGER NOT NULL,
                status TEXT NOT NULL DEFAULT 'draft',
                diisi_oleh INTEGER REFERENCES users(id) ON DELETE SET NULL,
                diisi_nama TEXT,
                diisi_pada TEXT,
                dibuat TEXT,
                diubah TEXT,
                UNIQUE (sekolah_id, kategori, kode_penilai, tahun)
            )");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_penilaian_sekolah ON penilaian (sekolah_id, kategori, tahun)");

            $pdo->exec("CREATE TABLE IF NOT EXISTS penilaian_butir (
                penilaian_id INTEGER NOT NULL REFERENCES penilaian(id) ON DELETE CASCADE,
                butir TEXT NOT NULL,
                skor INTEGER NOT NULL,
                PRIMARY KEY (penilaian_id, butir)
            )");

            // ---------- HALAMAN DEPAN (LANDING PAGE) ----------
            $pdo->exec("CREATE TABLE IF NOT EXISTS landing_slider (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                judul TEXT,
                keterangan TEXT,
                foto TEXT,
                urutan INTEGER NOT NULL DEFAULT 10,
                aktif INTEGER NOT NULL DEFAULT 1,
                dibuat TEXT
            )");
            $pdo->exec("CREATE TABLE IF NOT EXISTS landing_fitur (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                ikon TEXT NOT NULL DEFAULT 'check',
                judul TEXT,
                teks TEXT,
                urutan INTEGER NOT NULL DEFAULT 10,
                aktif INTEGER NOT NULL DEFAULT 1,
                dibuat TEXT
            )");
            $pdo->exec("CREATE TABLE IF NOT EXISTS landing_unggulan (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                ikon TEXT NOT NULL DEFAULT 'check',
                judul TEXT,
                teks TEXT,
                urutan INTEGER NOT NULL DEFAULT 10,
                aktif INTEGER NOT NULL DEFAULT 1,
                dibuat TEXT
            )");
            $pdo->exec("CREATE TABLE IF NOT EXISTS landing_testimoni (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                nama TEXT,
                jabatan TEXT,
                isi TEXT,
                foto TEXT,
                urutan INTEGER NOT NULL DEFAULT 10,
                aktif INTEGER NOT NULL DEFAULT 1,
                dibuat TEXT
            )");
            $pdo->exec("CREATE TABLE IF NOT EXISTS landing_pesan (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                nama TEXT,
                kontak TEXT,
                pesan TEXT,
                ip TEXT,
                dibaca INTEGER NOT NULL DEFAULT 0,
                dibuat TEXT
            )");
            foreach (['landing_slider', 'landing_fitur', 'landing_unggulan', 'landing_testimoni'] as $t) {
                $pdo->exec('CREATE INDEX IF NOT EXISTS idx_' . $t . '_urut ON ' . $t . ' (aktif, urutan, id)');
            }
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_landing_pesan_dibaca ON landing_pesan (dibaca, id)');

            // ---------- DOKUMEN BUKTI BUTIR + KOMENTAR PENILAI (skema v3) ----------
            // Diunggah kepala madrasah per TAHUN penilaian dan per butir instrumen; dilihat,
            // dikomentari, dan diberi skor oleh Pengawas I & II. Hanya URL media proxy yang disimpan.
            $pdo->exec("CREATE TABLE IF NOT EXISTS dokumen_butir (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                sekolah_id INTEGER NOT NULL REFERENCES sekolah(id) ON DELETE CASCADE,
                tahun INTEGER NOT NULL,
                butir TEXT NOT NULL,
                url TEXT NOT NULL,
                nama_berkas TEXT,
                ukuran INTEGER NOT NULL DEFAULT 0,
                pengunggah TEXT,
                pengunggah_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
                dibuat TEXT,
                tautan INTEGER NOT NULL DEFAULT 0
            )");
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_dokumen_butir ON dokumen_butir (sekolah_id, tahun, butir)');

            $pdo->exec("CREATE TABLE IF NOT EXISTS butir_komentar (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                sekolah_id INTEGER NOT NULL REFERENCES sekolah(id) ON DELETE CASCADE,
                tahun INTEGER NOT NULL,
                butir TEXT NOT NULL,
                posisi TEXT NOT NULL,
                isi TEXT,
                penilai_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
                penilai_nama TEXT,
                dibuat TEXT,
                diubah TEXT,
                UNIQUE (sekolah_id, tahun, butir, posisi)
            )");
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_butir_komentar ON butir_komentar (sekolah_id, tahun)');

            // ---------- jaring pengaman kolom (penanda bisa lebih baru dari isi) ----------
            db_add_column('users', 'foto', 'TEXT');
            db_add_column('sekolah', 'tahun_1', 'INTEGER');
            db_add_column('sekolah', 'tahun_2', 'INTEGER');
            db_add_column('sekolah', 'tahun_3', 'INTEGER');
            db_add_column('sekolah', 'tahun_4', 'INTEGER');
            db_add_column('sekolah', 'tanggal_1', 'TEXT');
            db_add_column('sekolah', 'tanggal_2', 'TEXT');
            db_add_column('sekolah', 'tanggal_3', 'TEXT');
            db_add_column('sekolah', 'tanggal_4', 'TEXT');
            db_add_column('sekolah', 'tempat', 'TEXT');
            db_add_column('sekolah', 'daftar_status', "TEXT NOT NULL DEFAULT 'disetujui'");
            db_add_column('sekolah', 'daftar_pada', 'TEXT');
            db_add_column('sekolah', 'daftar_alasan', 'TEXT');
            // madrasah LAMA (sebelum ada fitur pendaftaran) dianggap sudah disetujui
            $pdo->exec("UPDATE sekolah SET daftar_status = 'disetujui'
                         WHERE daftar_status IS NULL OR TRIM(daftar_status) = ''");
            // v7 - STATUS LEMBAGA (negeri/swasta) menentukan susunan 7 penilai Tahun Keempat.
            // Madrasah lama (kebanyakan madrasah swasta/yayasan) diisi 'swasta'; nilai asing dinormalkan.
            db_add_column('sekolah', 'status_lembaga', "TEXT NOT NULL DEFAULT 'swasta'");
            $pdo->exec("UPDATE sekolah SET status_lembaga = 'swasta'
                         WHERE status_lembaga IS NULL OR TRIM(status_lembaga) = ''
                            OR LOWER(TRIM(status_lembaga)) NOT IN ('negeri', 'swasta')");
            // v12 (v1.19.0) - dokumen butir boleh berupa TAUTAN Google Drive, bukan hanya berkas
            // unggahan. `url` menyimpan tautannya, `ukuran` = 0, `tautan` = 1.
            db_add_column('dokumen_butir', 'tautan', 'INTEGER NOT NULL DEFAULT 0');
            db_add_column('penilaian', 'status', "TEXT NOT NULL DEFAULT 'draft'");
            db_add_column('penilaian', 'diisi_oleh', 'INTEGER');
            db_add_column('penilaian', 'diisi_nama', 'TEXT');
            db_add_column('penilaian', 'diisi_pada', 'TEXT');
            db_add_column('penilai', 'kategori', 'TEXT');
            db_add_column('penilai', 'kode', 'TEXT');
            db_add_column('penilai', 'user_id', 'INTEGER');
            // kolom profil PENILAI pada tabel users (diisi saat penilai mendaftar sendiri)
            db_add_column('users', 'nip', 'TEXT');
            db_add_column('users', 'jabatan', 'TEXT');
            db_add_column('users', 'instansi', 'TEXT');
            db_add_column('users', 'kab_kota', 'TEXT');
            db_add_column('users', 'hp', 'TEXT');
            db_add_column('users', 'asal', 'TEXT');
            // v9 - TARIK DATA DARI APLIKASI SUPERVISI (v1.11.0): penanda asal data + sandi Supervisi
            // terakhir yang disalin (dipakai menentukan apakah sandi sudah diganti sendiri di e-PKKM).
            db_add_column('sekolah', 'supervisi_id', 'INTEGER');
            db_add_column('sekolah', 'sumber', "TEXT NOT NULL DEFAULT 'manual'");
            db_add_column('users', 'supervisi_id', 'INTEGER');
            db_add_column('users', 'sandi_supervisi', 'TEXT');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_sekolah_supervisi ON sekolah (supervisi_id)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_users_supervisi ON users (supervisi_id)');

            // ---------- daftar JABATAN penilai bawaan (SEKALI; suntingan Super Admin tidak ditimpa) ----------
            $jabatanAda = (int)$pdo->query('SELECT COUNT(*) FROM jabatan_penilai')->fetchColumn();
            $stJab = $pdo->prepare('INSERT OR IGNORE INTO jabatan_penilai (nama, urutan, aktif, dibuat)
                                    VALUES (?, ?, 1, ?)');
            if ($jabatanAda === 0) {
                $u = 10;
                foreach (PKKM_JABATAN_BAWAAN as $jab) {
                    $stJab->execute([$jab, $u, date('Y-m-d H:i:s')]);
                    $u += 10;
                }
            } else {
                // v7 - jabatan yang dibutuhkan 7 penilai Tahun Keempat (Kabid, Tendik, Yayasan)
                // ditambahkan SEKALI saja. Penanda `jabatan_slot_v1` mencegahnya ditambahkan ulang
                // setiap migrasi sehingga jabatan yang sudah DI-RE-NAMA Super Admin tidak muncul lagi
                // sebagai baris baru (dan tidak ada baris ganda).
                $stFlag = $pdo->prepare('SELECT nilai FROM app_pengaturan WHERE kunci = ?');
                $stFlag->execute(['jabatan_slot_v1']);
                if ($stFlag->fetchColumn() === false) {
                    $stMax = $pdo->query('SELECT COALESCE(MAX(urutan), 0) FROM jabatan_penilai');
                    $u = (int)$stMax->fetchColumn();
                    foreach (['Kabid', 'Tendik', 'Yayasan'] as $jab) {
                        $stCek = $pdo->prepare('SELECT COUNT(*) FROM jabatan_penilai WHERE LOWER(TRIM(nama)) = LOWER(?)');
                        $stCek->execute([$jab]);
                        if ((int)$stCek->fetchColumn() > 0) {
                            continue;
                        }
                        $u += 10;
                        $stJab->execute([$jab, $u, date('Y-m-d H:i:s')]);
                    }
                    $pdo->prepare('INSERT INTO app_pengaturan (kunci, nilai) VALUES (?, ?)
                                    ON CONFLICT(kunci) DO UPDATE SET nilai = excluded.nilai')
                        ->execute(['jabatan_slot_v1', date('Y-m-d H:i:s')]);
                }
            }

            // ---------- v8: bersihkan JABATAN DUPLIKAT akibat penyemaian bertingkat ----------
            // Saat fitur 7 penilai dirilis, penyemaian jabatan (Kabid/Tendik/Yayasan) sempat berjalan
            // lebih dari sekali sehingga pada basis data yang jabatannya SUDAH DI-RE-NAMA Super Admin
            // (mis. "Kabid (Kepala Bidang)") muncul baris tambahan "Kabid"/"Kasie" yang tidak dipakai.
            // Migrasi ini menghapus baris pendek itu HANYA bila: (a) tidak dipakai akun mana pun dan
            // (b) ada baris lain yang namanya berawalan nama itu + " (" (versi re-nama pemilik).
            // Dijalankan sekali (penanda `jabatan_dedup_v1`); jabatan yang dipakai TIDAK pernah dihapus.
            jabatan_dedup_sekali($pdo);

            // ---------- akun Super Admin pertama (instalasi baru) ----------
            $jmlUser = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
            if ($jmlUser === 0) {
                $st = $pdo->prepare('INSERT INTO users (sekolah_id, nama, username, sandi, role, aktif, dibuat)
                                     VALUES (NULL, ?, ?, ?, ?, 1, ?)');
                $st->execute(['Super Admin', 'admin', password_hash('admin123', PASSWORD_DEFAULT), 'super', date('Y-m-d H:i:s')]);
            }

            // ---------- isi awal halaman depan (SEKALI saja) ----------
            require_once __DIR__ . '/landing.php';
            landing_seed($pdo);
            landing_slider_seed($pdo);

            // ---------- v6: penilai TAMBAHAN Tahun Keempat (permintaan pemilik) ----------
            // Akun penilai yang jabatannya BUKAN Pengawas hanya menilai Tahun Keempat, sehingga
            // penugasan lama yang masih memakai posisi 1/2 dipindahkan ke posisi tambahan (3, 4, …)
            // agar posisi Pengawas I & II tidak "tertahan" oleh jabatan lain. Dijalankan SEKALI
            // (penanda di app_pengaturan) sehingga tidak menggeser penugasan baru setiap migrasi.
            migrasi_penilai_tambahan($pdo);

            $pdo->exec('COMMIT');
        } catch (Throwable $t) {
            $pdo->exec('ROLLBACK');
            $pdo->exec('PRAGMA foreign_keys = ON');
            throw $t;
        }
        $pdo->exec('PRAGMA foreign_keys = ON');
        @file_put_contents(DATA_DIR . '/.schema_ok', SCHEMA_VERSION);
    }
}

// ------------------------------------------------------------- pengaturan ---
if (!function_exists('app_setting')) {
    function app_setting(string $kunci, string $bawaan = ''): string
    {
        try {
            $v = val('SELECT nilai FROM app_pengaturan WHERE kunci = ?', [$kunci]);
            return $v === null ? $bawaan : (string)$v;
        } catch (Throwable $t) {
            return $bawaan;
        }
    }
}

if (!function_exists('app_setting_set')) {
    function app_setting_set(string $kunci, string $nilai): void
    {
        q('INSERT INTO app_pengaturan (kunci, nilai) VALUES (?, ?)
             ON CONFLICT(kunci) DO UPDATE SET nilai = excluded.nilai', [$kunci, $nilai]);
    }
}
