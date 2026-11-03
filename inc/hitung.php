<?php
/**
 * e-PKKM - mesin perhitungan nilai PKKM.
 *
 * RUMUS (mengikuti berkas instrumen asli "Aplikasi PKKM.xls"):
 *  1. Tiap butir bernilai 1-4 (PKKM_SKOR_MAKS).
 *  2. Nilai tiap Tugas Utama (bagian A-E) = (jumlah skor butir / (4 x jumlah butir)) x 100.
 *     Contoh pada berkas asli: bagian A 94 dari 25 butir -> 94/100 x 100 = 94,00.
 *  3. Nilai tahunan = rata-rata nilai tugas utama yang berlaku pada tahun itu
 *     (Tahun Pertama-Ketiga: A-D ; Tahun Keempat: A-E).
 *     Contoh: (94 + 92,857142857 + 80,555555556 + 93,181818182) / 4 = 90,148629149.
 *  4. Rekap PKKM = rata-rata hasil Pengawas I dan Pengawas II (per tugas utama dan tahunan).
 *  5. Predikat (PermenPANRB 6/2022): Amat Baik >= 91 ; Baik 81-90,99 ; Cukup 71-80,99 ;
 *     Kurang < 71.
 *
 * Selama butir belum terisi lengkap, nilai dihitung dari butir yang SUDAH terisi dan
 * disebut "nilai sementara" pada tampilan (bukan nilai resmi).
 */

require_once __DIR__ . '/instrumen.php';

if (!function_exists('butir_tahun')) {
    /** Butir yang berlaku pada satu tahun penilaian. */
    function butir_tahun(int $tahun): array
    {
        $out = [];
        foreach (instrumen_butir() as $kode => $b) {
            if (in_array($tahun, $b['tahun'], true)) {
                $out[$kode] = $b;
            }
        }
        return $out;
    }
}

if (!function_exists('bagian_tahun')) {
    /** Susunan bagian + butir per bagian untuk satu tahun. */
    function bagian_tahun(int $tahun): array
    {
        $out = [];
        foreach (instrumen_bagian() as $kode => $b) {
            if (!in_array($tahun, $b['tahun'], true)) {
                continue;
            }
            $out[$kode] = ['judul' => $b['judul'], 'butir' => [], 'maks' => 0];
        }
        foreach (butir_tahun($tahun) as $kode => $b) {
            $out[$b['bagian']]['butir'][] = $kode;
        }
        foreach ($out as $kode => $b) {
            $out[$kode]['maks'] = count($b['butir']) * PKKM_SKOR_MAKS;
        }
        return $out;
    }
}

if (!function_exists('butir_urut')) {
    /** Urutkan kode butir secara alami (1.1.1, 1.1.2, ... 2.10.5). */
    function butir_urut(array $kode): array
    {
        usort($kode, function ($a, $b) {
            $pa = array_map('intval', explode('.', $a));
            $pb = array_map('intval', explode('.', $b));
            for ($i = 0; $i < 3; $i++) {
                $x = $pa[$i] ?? 0;
                $y = $pb[$i] ?? 0;
                if ($x !== $y) {
                    return $x <=> $y;
                }
            }
            return 0;
        });
        return $kode;
    }
}

if (!function_exists('penilaian_ambil')) {
    /** Baris penilaian (dibuat bila belum ada dan $buat = true). */
    function penilaian_ambil(int $sekolahId, string $kategori, string $kode, int $tahun, bool $buat = false): ?array
    {
        $r = one(
            'SELECT * FROM penilaian WHERE sekolah_id = ? AND kategori = ? AND kode_penilai = ? AND tahun = ?',
            [$sekolahId, $kategori, $kode, $tahun]
        );
        if ($r || !$buat) {
            return $r;
        }
        q(
            'INSERT INTO penilaian (sekolah_id, kategori, kode_penilai, tahun, status, dibuat, diubah)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$sekolahId, $kategori, $kode, $tahun, 'draft', date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]
        );
        return one('SELECT * FROM penilaian WHERE id = ?', [db()->lastInsertId()]);
    }
}

if (!function_exists('penilaian_skor')) {
    /** Skor tersimpan: kode butir => skor. */
    function penilaian_skor(int $penilaianId): array
    {
        if ($penilaianId <= 0) {
            return [];
        }
        $out = [];
        foreach (all('SELECT butir, skor FROM penilaian_butir WHERE penilaian_id = ?', [$penilaianId]) as $r) {
            $out[$r['butir']] = (int)$r['skor'];
        }
        return $out;
    }
}

if (!function_exists('nilai_bagian')) {
    /** Nilai satu tugas utama (0-100) atau null bila belum ada skor. */
    function nilai_bagian(array $skor, array $butirBagian): ?float
    {
        $jml = 0;
        $n = 0;
        foreach ($butirBagian as $kode) {
            if (isset($skor[$kode])) {
                $jml += (int)$skor[$kode];
                $n++;
            }
        }
        if ($n === 0) {
            return null;
        }
        return $jml / (PKKM_SKOR_MAKS * $n) * 100;
    }
}

if (!function_exists('nilai_rinci')) {
    /**
     * Rincian nilai satu penilaian:
     * ['bagian' => [A => nilai, ...], 'nilai' => float|null, 'terisi' => n, 'jumlah' => N, 'lengkap' => bool]
     */
    function nilai_rinci(array $skor, int $tahun): array
    {
        $bagian = [];
        $adaNilai = [];
        $terisi = 0;
        $jumlah = 0;
        foreach (bagian_tahun($tahun) as $kode => $b) {
            $nilai = nilai_bagian($skor, $b['butir']);
            $bagian[$kode] = $nilai;
            if ($nilai !== null) {
                $adaNilai[] = $nilai;
            }
            foreach ($b['butir'] as $bk) {
                $jumlah++;
                if (isset($skor[$bk])) {
                    $terisi++;
                }
            }
        }
        return [
            'bagian' => $bagian,
            'nilai' => $adaNilai ? array_sum($adaNilai) / count($adaNilai) : null,
            'terisi' => $terisi,
            'jumlah' => $jumlah,
            'lengkap' => $jumlah > 0 && $terisi === $jumlah,
        ];
    }
}

if (!function_exists('nilai_penilaian')) {
    /** Rincian nilai satu penilaian (kategori/kode/tahun) pada satu madrasah. */
    function nilai_penilaian(int $sekolahId, string $kategori, string $kode, int $tahun): ?array
    {
        $p = penilaian_ambil($sekolahId, $kategori, $kode, $tahun, false);
        if (!$p) {
            return null;
        }
        $r = nilai_rinci(penilaian_skor((int)$p['id']), $tahun);
        $r['status'] = $p['status'];
        $r['diisi_pada'] = $p['diisi_pada'] ?? null;
        $r['diisi_nama'] = $p['diisi_nama'] ?? null;
        return $r;
    }
}

if (!function_exists('predikat_dari')) {
    function predikat_dari($nilai): string
    {
        if ($nilai === null || $nilai === '') {
            return '-';
        }
        $n = (float)$nilai;
        foreach (PKKM_PREDIKAT as $label => $batas) {
            if ($n >= $batas) {
                return $label;
            }
        }
        return 'Kurang';
    }
}

if (!function_exists('predikat_kelas')) {
    /** Kelas CSS pil predikat. */
    function predikat_kelas(string $predikat): string
    {
        switch ($predikat) {
            case 'Amat Baik':
                return 'pil-unggul';
            case 'Baik':
                return 'pil-baik';
            case 'Cukup':
                return 'pil-cukup';
            case 'Kurang':
                return 'pil-kurang';
            default:
                return 'pil-kosong';
        }
    }
}

if (!function_exists('penilaian_tambahan_list')) {
    /**
     * Penilaian PENILAI TAMBAHAN Tahun Keempat sebuah madrasah (kategori `tambahan`).
     * Permintaan pemilik: nilai tahun ke-4 = rata-rata bersama Pengawas I & II.
     *
     * @return array<string,array> kunci = kode_penilai (nomor posisi tambahan) => rincian nilai
     */
    function penilaian_tambahan_list(int $sekolahId, int $tahun = 4): array
    {
        $out = [];
        try {
            $rows = all("SELECT * FROM penilaian WHERE sekolah_id = ? AND kategori = ? AND tahun = ?
                          ORDER BY CAST(kode_penilai AS INTEGER), kode_penilai",
                [$sekolahId, PKKM_KATEGORI_TAMBAHAN, $tahun]);
        } catch (Throwable $t) {
            return $out;
        }
        foreach ($rows as $r) {
            $kode = (string)$r['kode_penilai'];
            $rinci = nilai_rinci(penilaian_skor((int)$r['id']), $tahun);
            $rinci['kode'] = $kode;
            $rinci['diisi_nama'] = (string)($r['diisi_nama'] ?? '');
            $out[$kode] = $rinci;
        }
        return $out;
    }
}

if (!function_exists('penilai_tambahan_ringkas')) {
    /**
     * Ringkasan penilai tambahan Tahun Keempat (untuk tampilan/cetak):
     * [kode => ['posisi'=>int,'nama'=>string,'jabatan'=>string,'rinci'=>array]]
     */
    function penilai_tambahan_ringkas(int $sekolahId): array
    {
        $nilai = penilaian_tambahan_list($sekolahId, 4);
        $hasil = [];
        $akun = [];
        foreach (penilai_akun_tambahan($sekolahId) as $a) {
            $akun[(string)(int)$a['posisi']] = $a;
        }
        $kodeSemua = array_keys($nilai + $akun);
        sort($kodeSemua, SORT_NUMERIC);
        foreach ($kodeSemua as $kode) {
            $kode = (string)$kode;
            $a = $akun[$kode] ?? null;
            // identitas cadangan: baris `penilai` kategori tambahan (dulu diisi penilai_sinkron()).
            // JANGAN memanggil penilai_ambil() di sini — fungsi itu milik inc/form_penilaian.php yang
            // dimuat SESUDAH inc/hitung.php.
            $ident = $a ?: (one('SELECT * FROM penilai WHERE sekolah_id = ? AND kategori = ? AND kode = ?',
                [$sekolahId, PKKM_KATEGORI_TAMBAHAN, $kode]) ?: []);
            $rinci = $nilai[$kode] ?? null;
            // Lewati sisa data lama yang sudah tidak punya penugasan DAN tidak berisi skor sama sekali
            // (mis. akun penilainya sudah dihapus pemilik) supaya daftar tidak berisi baris kosong.
            if ($a === null && ($rinci === null || (int)$rinci['terisi'] === 0)) {
                continue;
            }
            $hasil[$kode] = [
                'posisi' => (int)$kode,
                'nama' => (string)($ident['nama'] ?? ($rinci['diisi_nama'] ?? '')),
                'jabatan' => (string)($ident['jabatan'] ?? ''),
                'rinci' => $rinci,
            ];
        }
        return $hasil;
    }
}

if (!function_exists('rekap_tahunan')) {
    /**
     * Rekap satu madrasah untuk seluruh tahun:
     * [
     *   1 => ['kode' => [1 => [bagian=>nilai, nilai, terisi, jumlah, lengkap], 2 => ...],
     *         'rerata_bagian' => [A=>nilai, ...], 'rerata' => float|null, 'predikat' => string,
     *         'lengkap' => bool]
     * ]
     */
    function rekap_tahunan(int $sekolahId, bool $denganGtk = false): array
    {
        $out = [];
        foreach (array_keys(PKKM_TAHUN_LIST) as $tahun) {
            $per = [];
            foreach (['1', '2'] as $kode) {
                $per[$kode] = nilai_penilaian($sekolahId, 'pengawas', $kode, $tahun);
            }
            // PENILAI TAMBAHAN Tahun Keempat (permintaan pemilik): nilai tahun ke-4 = rata-rata
            // bersama Pengawas I & II, sehingga madrasah boleh dinilai lebih dari dua penilai.
            $tambahan = ((int)$tahun === 4) ? penilaian_tambahan_list($sekolahId, 4) : [];
            $rerataBagian = [];
            $ada = [];
            foreach (bagian_tahun($tahun) as $b => $info) {
                $vals = [];
                foreach (['1', '2'] as $kode) {
                    $v = $per[$kode]['bagian'][$b] ?? null;
                    if ($v !== null) {
                        $vals[] = $v;
                    }
                }
                foreach ($tambahan as $tr) {
                    $v = $tr['bagian'][$b] ?? null;
                    if ($v !== null) {
                        $vals[] = $v;
                    }
                }
                $rerataBagian[$b] = $vals ? array_sum($vals) / count($vals) : null;
            }
            foreach (['1', '2'] as $kode) {
                if ($per[$kode] !== null && $per[$kode]['nilai'] !== null) {
                    $ada[] = $per[$kode]['nilai'];
                }
            }
            foreach ($tambahan as $tr) {
                if ($tr['nilai'] !== null) {
                    $ada[] = $tr['nilai'];
                }
            }
            $rerata = $ada ? array_sum($ada) / count($ada) : null;
            $out[$tahun] = [
                'kode' => $per,
                'tambahan' => $tambahan,
                'rerata_bagian' => $rerataBagian,
                'rerata' => $rerata,
                'predikat' => predikat_dari($rerata),
                'lengkap' => ($per['1']['lengkap'] ?? false) && ($per['2']['lengkap'] ?? false),
            ];
        }
        return $out;
    }
}

if (!function_exists('rekap_gtk')) {
    /** Rekap penilaian Tahun Keempat oleh Guru/Tendik/Komite/Kabid/Kasi. */
    function rekap_gtk(int $sekolahId): array
    {
        $out = [];
        foreach (PKKM_PENILAI_GTK as $kode => $label) {
            $out[$kode] = [
                'label' => $label,
                'nilai' => nilai_penilaian($sekolahId, 'gtk', $kode, 4),
            ];
        }
        return $out;
    }
}

if (!function_exists('rerata_gtk')) {
    /** Rata-rata kelompok penilai GTK (guru/tendik/komite = dua penilai, dikosongkan = tidak dihitung). */
    function rerata_gtk(array $gtk, array $kodeList): ?float
    {
        $vals = [];
        foreach ($kodeList as $kode) {
            $n = $gtk[$kode]['nilai']['nilai'] ?? null;
            if ($n !== null) {
                $vals[] = $n;
            }
        }
        return $vals ? array_sum($vals) / count($vals) : null;
    }
}

if (!function_exists('rekap_komponen_tahun4')) {
    /**
     * Komponen penilaian Tahun Keempat (mengikuti berkas asli):
     * Kepala Bidang, Kepala Seksi, rata-rata Pengawas, rata-rata Guru,
     * rata-rata Tenaga Kependidikan, rata-rata Komite Madrasah.
     */
    function rekap_komponen_tahun4(int $sekolahId): array
    {
        // Komponen Tahun Keempat mengikuti 7 PENILAI Tahun Keempat (permintaan pemilik):
        //   1. Kepala Bidang (madrasah NEGERI) / Yayasan (madrasah SWASTA)
        //   2. Kepala Seksi
        //   3. Rata-rata Pengawas (Pengawas 1 & 2)
        //   4. Guru      5. Tenaga Kependidikan      6. Komite Madrasah
        // Bila slot penilai belum terisi, dipakai CADANGAN data GTK lama (kategori `gtk`) agar
        // angka yang pernah diisi pada menu lama tidak hilang.
        $status = status_lembaga_sekolah($sekolahId);
        $slots = penilai_sekolah_slots($sekolahId);
        $gtk = rekap_gtk($sekolahId);
        $nilai = function (string $kunci) use ($slots): ?float {
            foreach ($slots as $sl) {
                if ($sl['kunci'] === $kunci) {
                    $v = $sl['rinci']['nilai'] ?? null;
                    return $v === null ? null : (float)$v;
                }
            }
            return null;
        };
        $pertama = null;
        $labelPertama = '';
        foreach ($slots as $sl) {
            if (in_array($sl['kunci'], ['kabid', 'yayasan'], true)) {
                $pertama = $sl['rinci']['nilai'] ?? null;
                $labelPertama = $sl['label'];
                break;
            }
        }
        $tambahanTerisi = 0;
        foreach ($slots as $sl) {
            if ($sl['kategori'] === PKKM_KATEGORI_TAMBAHAN && ($sl['rinci']['nilai'] ?? null) !== null) {
                $tambahanTerisi++;
            }
        }
        $komponen = [
            'kabid' => [
                'label' => 'Hasil Penilaian ' . ($labelPertama !== '' ? $labelPertama
                    : ($status === 'negeri' ? 'Kepala Bidang' : 'Yayasan')),
                'nilai' => $pertama !== null ? (float)$pertama : ($gtk['kabid']['nilai']['nilai'] ?? null),
                'sumber' => $pertama !== null ? 'penilai' : 'gtk',
            ],
            'kasi' => [
                'label' => 'Hasil Penilaian Kepala Seksi',
                'nilai' => $nilai('kasie') ?? ($gtk['kasi']['nilai']['nilai'] ?? null),
                'sumber' => $nilai('kasie') !== null ? 'penilai' : 'gtk',
            ],
            'pengawas' => [
                'label' => $tambahanTerisi > 0
                    ? 'Rata-rata Hasil Penilaian Pengawas & ' . $tambahanTerisi . ' Penilai Lain'
                    : 'Rata-rata Hasil Penilaian Pengawas',
                'nilai' => $nilai('pengawas1') !== null || $nilai('pengawas2') !== null
                    ? ($nilai('pengawas1') === null ? $nilai('pengawas2')
                        : ($nilai('pengawas2') === null ? $nilai('pengawas1')
                            : ($nilai('pengawas1') + $nilai('pengawas2')) / 2))
                    : null,
                'tambahan' => $tambahanTerisi,
            ],
            'guru' => [
                'label' => 'Rata-rata Hasil Penilaian Guru',
                'nilai' => $nilai('guru') ?? rerata_gtk($gtk, ['guru1', 'guru2']),
                'sumber' => $nilai('guru') !== null ? 'penilai' : 'gtk',
            ],
            'tendik' => [
                'label' => 'Rata-rata Hasil Penilaian Tenaga Kependidikan',
                'nilai' => $nilai('tendik') ?? rerata_gtk($gtk, ['tendik1', 'tendik2']),
                'sumber' => $nilai('tendik') !== null ? 'penilai' : 'gtk',
            ],
            'komite' => [
                'label' => 'Rata-rata Hasil Penilaian Komite Madrasah',
                'nilai' => $nilai('komite') ?? rerata_gtk($gtk, ['komite1', 'komite2']),
                'sumber' => $nilai('komite') !== null ? 'penilai' : 'gtk',
            ],
        ];
        $vals = [];
        foreach ($komponen as $k) {
            if ($k['nilai'] !== null) {
                $vals[] = (float)$k['nilai'];
            }
        }
        return [
            'komponen' => $komponen,
            'jumlah' => $vals ? array_sum($vals) / count($vals) : null,
            'ada' => count($vals),
            'status' => $status,
        ];
    }
}

if (!function_exists('rekap_4tahun')) {
    /**
     * Rekap empat tahunan:
     *  - Tahun Pertama-Ketiga = nilai tahunan gabungan Pengawas I & II;
     *  - Tahun Keempat = nilai kumulatif seluruh komponen penilai (Kepala Bidang, Kepala
     *    Seksi, Pengawas, Guru, Tenaga Kependidikan, Komite Madrasah) - bila komponennya
     *    belum ada, dipakai nilai tahunan pengawas sebagai cadangan;
     *  - NKKM = (rata-rata Tahun 1-3 + 5 x Tahun Keempat) / 6, sama dengan rumus yang
     *    dipakai berkas instrumen asli (diuji: 96,0909692159 pada data contoh berkas).
     */
    function rekap_4tahun(int $sekolahId): array
    {
        $rekap = rekap_tahunan($sekolahId);
        $komp = rekap_komponen_tahun4($sekolahId);
        $tahunNilai = [];
        for ($t = 1; $t <= 3; $t++) {
            $tahunNilai[$t] = $rekap[$t]['rerata'];
        }
        $pengawas4 = $rekap[4]['rerata'];
        $tahunNilai[4] = $komp['jumlah'] !== null ? $komp['jumlah'] : $pengawas4;
        $sumber4 = $komp['jumlah'] !== null ? 'kumulatif' : ($pengawas4 !== null ? 'pengawas' : 'kosong');

        $awal = [];
        for ($t = 1; $t <= 3; $t++) {
            if ($tahunNilai[$t] !== null) {
                $awal[] = (float)$tahunNilai[$t];
            }
        }
        $t4 = $tahunNilai[4] !== null ? (float)$tahunNilai[4] : null;
        if ($t4 !== null && $awal) {
            $nilai = (array_sum($awal) / count($awal) + 5 * $t4) / 6;
        } elseif ($t4 !== null) {
            $nilai = $t4;
        } elseif ($awal) {
            $nilai = array_sum($awal) / count($awal);
        } else {
            $nilai = null;
        }
        $ada = 0;
        foreach ($tahunNilai as $v) {
            if ($v !== null) {
                $ada++;
            }
        }
        return [
            'per_tahun' => $tahunNilai,
            'rekap' => $rekap,
            'sumber_tahun4' => $sumber4,
            'komponen_tahun4' => $komp,
            'ada' => $ada,
            'nilai' => $nilai,
            'predikat' => predikat_dari($nilai),
        ];
    }
}

if (!function_exists('progres_sekolah')) {
    /** Progres pengisian: berapa dari 8 penilaian (2 pengawas x 4 tahun) yang sudah diisi. */
    function progres_sekolah(int $sekolahId): array
    {
        $terisi = 0;
        $selesai = 0;
        $total = 0;
        foreach (array_keys(PKKM_TAHUN_LIST) as $tahun) {
            foreach (['1', '2'] as $kode) {
                $total++;
                $r = nilai_penilaian($sekolahId, 'pengawas', $kode, $tahun);
                if ($r && $r['terisi'] > 0) {
                    $terisi++;
                }
                if ($r && $r['lengkap']) {
                    $selesai++;
                }
            }
        }
        return ['terisi' => $terisi, 'selesai' => $selesai, 'total' => $total,
            'persen' => $total ? round($terisi / $total * 100) : 0];
    }
}
