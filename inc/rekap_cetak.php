<?php
/**
 * e-PKKM - penyusun ISI lembar "Rekapitulasi Hasil Penilaian Kinerja Tahunan Kepala Madrasah".
 *
 * Modul ini adalah SATU-SATUNYA sumber isi lembar rekap, dipakai bersama oleh:
 *   - cetak_rekap.php  (lembar cetak A4 potrait / cetak langsung), dan
 *   - rekap_docx.php   (unduhan Word .docx yang siap dicetak).
 * Jadi angka, identitas, dan susunan baris pada kedua keluaran SELALU sama.
 *
 * Bentuk lembar mengikuti contoh berkas instrumen pemilik:
 *   judul -> "Yang bertanda tangan di bawah ini:" -> Identitas Penilai -> "Menyatakan bahwa:" ->
 *   Identitas Kepala Madrasah -> kalimat penilaian -> tabel tugas utama -> tanda tangan.
 * Tahun Pertama-Ketiga memuat 4 tugas utama (A-D), Tahun Keempat 5 tugas utama (A-E).
 */

require_once __DIR__ . '/config.php';

if (!function_exists('rekap_isi_teks')) {
    /**
     * Nilai identitas yang dicetak: kosong / "-" / "–" / "—" / "." / "_" dianggap KOSONG
     * (tidak pernah dicetak sebagai tanda hubung yang janggal).
     */
    function rekap_isi_teks($nilai): string
    {
        $t = trim((string)$nilai);
        return in_array($t, ['', '-', '–', '—', '.', '_'], true) ? '' : $t;
    }
}

if (!function_exists('rekap_nilai_teks')) {
    /** Angka lembar rekap: dua desimal dengan koma (mis. 91,50); kosong = "-". */
    function rekap_nilai_teks($nilai): string
    {
        if ($nilai === null || $nilai === '') {
            return '-';
        }
        return number_format((float)$nilai, 2, ',', '.');
    }
}

if (!function_exists('rekap_identitas_penilai')) {
    /**
     * Identitas penilai yang menandatangani lembar (Ketua Tim Penilai = posisi 1).
     * Sumber utama AKUN penilai (hasil pendaftaran penilai), cadangan baris tabel `penilai`
     * (data lama / diisi Super Admin) — Pangkat/Golongan hanya ada pada tabel `penilai`.
     */
    function rekap_identitas_penilai(int $sekolahId): array
    {
        $akun = penilai_sekolah_baris($sekolahId, '1');
        $baris = one('SELECT * FROM penilai WHERE sekolah_id = ? AND kategori = ? AND kode = ?',
            [$sekolahId, 'pengawas', '1']) ?: [];
        $pilih = function ($a, $b): string {
            $x = rekap_isi_teks($a);
            return $x !== '' ? $x : rekap_isi_teks($b);
        };
        return [
            'nama' => $pilih($akun['nama'] ?? '', $baris['nama'] ?? ''),
            'nip' => $pilih($akun['nip'] ?? '', $baris['nip'] ?? ''),
            'pangkat' => rekap_isi_teks($baris['pangkat'] ?? ''),
            'jabatan' => $pilih($akun['jabatan'] ?? '', $baris['jabatan'] ?? ''),
            'unit_kerja' => $pilih($akun['unit_kerja'] ?? '', $baris['unit_kerja'] ?? ''),
            'tempat' => rekap_isi_teks($baris['tempat'] ?? ''),
        ];
    }
}

if (!function_exists('rekap_identitas_kepala')) {
    /** Identitas kepala madrasah yang dinilai (dari baris tabel `sekolah`). */
    function rekap_identitas_kepala(array $s): array
    {
        return [
            'nama' => rekap_isi_teks($s['kepala_nama'] ?? ''),
            'nip' => rekap_isi_teks($s['kepala_nip'] ?? ''),
            'pangkat' => rekap_isi_teks($s['kepala_pangkat'] ?? ''),
            'jabatan' => 'Kepala Madrasah',
            'unit_kerja' => rekap_isi_teks($s['nama'] ?? ''),
        ];
    }
}

if (!function_exists('rekap_tanggal')) {
    /**
     * Tanggal yang dicetak pada blok tanda tangan satu tahun:
     * tanggal MENILAI milik penilai posisi 1 (diisi penilai lewat halaman penilaian) lebih dulu,
     * baru tanggal yang ditetapkan Super Admin pada data madrasah. Keduanya kosong = ''.
     */
    function rekap_tanggal(int $sekolahId, array $s, int $tahun): string
    {
        $tgl = function_exists('penilai_tanggal')
            ? penilai_tanggal($sekolahId, 'pengawas', '1', $tahun) : '';
        if ($tgl === '') {
            $tgl = trim((string)($s['tanggal_' . (int)$tahun] ?? ''));
        }
        return $tgl;
    }
}

if (!function_exists('rekap_identitas_baris')) {
    /**
     * Baris identitas siap cetak (label => nilai). Label yang nilainya kosong TETAP dicetak
     * dengan tanda "-" pada lembar resmi supaya bentuk formulirnya utuh.
     */
    function rekap_identitas_baris(array $ident): array
    {
        $out = [];
        foreach (['Nama' => 'nama', 'NIP' => 'nip', 'Pangkat/Golongan' => 'pangkat',
            'Jabatan' => 'jabatan', 'Unit Kerja' => 'unit_kerja'] as $label => $kunci) {
            $nilai = rekap_isi_teks($ident[$kunci] ?? '');
            $out[$label] = $nilai !== '' ? $nilai : '-';
        }
        return $out;
    }
}

if (!function_exists('rekap_label_penilai_tambahan')) {
    /**
     * Judul kolom penilai Tahun Keempat selain Pengawas I & II.
     * Diambil dari JABATAN penilainya (Kepala Bidang / Yayasan / Kepala Seksi / Guru / Tenaga
     * Kependidikan / Komite Madrasah); bila jabatannya tidak dikenali, dipakai label slot menurut
     * nomor posisinya. Jangan memakai nomor posisi saja — pernah menampilkan "Yayasan" untuk
     * penilai yang jabatannya Guru.
     */
    function rekap_label_penilai_tambahan(int $sekolahId, string $kode): string
    {
        $status = status_lembaga_sekolah($sekolahId);
        $baris = one('SELECT jabatan FROM penilai WHERE sekolah_id = ? AND kategori = ? AND kode = ?',
            [$sekolahId, PKKM_KATEGORI_TAMBAHAN, $kode]) ?: [];
        $jabatan = rekap_isi_teks($baris['jabatan'] ?? '');
        if ($jabatan !== '') {
            $slot = penilai_slot_dari_jabatan($jabatan, $status);
            return $slot !== null ? $slot['label'] : $jabatan;
        }
        return penilai_posisi_teks($kode, $status);
    }
}

if (!function_exists('rekap_tabel_tahun')) {
    /**
     * Isi tabel satu tahun penilaian (bentuk lembar resmi).
     *
     * @return array{
     *   kolom: array<int,array{kunci:string,label:string}>,
     *   baris: array<int,array{no:int,judul:string,nilai:array<string,string>}>,
     *   kaki: array{nilai:array<string,string>,rerata:string},
     *   predikat: string
     * }
     */
    function rekap_tabel_tahun(int $sekolahId, int $tahun, array $r): array
    {
        $kolom = [
            ['kunci' => '1', 'label' => 'Skor 1'],
            ['kunci' => '2', 'label' => 'Skor 2'],
        ];
        foreach (array_keys($r['tambahan'] ?? []) as $kode) {
            $kolom[] = ['kunci' => (string)$kode, 'label' => rekap_label_penilai_tambahan($sekolahId, (string)$kode)];
        }
        $kolom[] = ['kunci' => 'rerata', 'label' => 'Rerata'];

        $nilaiBaris = function (string $bagian) use ($r, $kolom): array {
            $out = [];
            foreach ($kolom as $k) {
                if ($k['kunci'] === 'rerata') {
                    continue;
                }
                $out[$k['kunci']] = $k['kunci'] === '1' || $k['kunci'] === '2'
                    ? rekap_nilai_teks($r['kode'][$k['kunci']]['bagian'][$bagian] ?? null)
                    : rekap_nilai_teks($r['tambahan'][$k['kunci']]['bagian'][$bagian] ?? null);
            }
            return $out;
        };

        $baris = [];
        $no = 0;
        foreach (instrumen_bagian() as $kodeBagian => $b) {
            if (!in_array($tahun, $b['tahun'], true)) {
                continue;
            }
            $no++;
            $baris[] = [
                'no' => $no,
                'judul' => $b['judul'],
                'nilai' => $nilaiBaris((string)$kodeBagian),
                'rerata' => rekap_nilai_teks($r['rerata_bagian'][$kodeBagian] ?? null),
            ];
        }

        $kaki = ['nilai' => []];
        foreach ($kolom as $k) {
            if ($k['kunci'] === 'rerata') {
                continue;
            }
            $kaki['nilai'][$k['kunci']] = $k['kunci'] === '1' || $k['kunci'] === '2'
                ? rekap_nilai_teks($r['kode'][$k['kunci']]['nilai'] ?? null)
                : rekap_nilai_teks($r['tambahan'][$k['kunci']]['nilai'] ?? null);
        }
        $kaki['rerata'] = rekap_nilai_teks($r['rerata'] ?? null);

        return [
            'kolom' => $kolom,
            'baris' => $baris,
            'kaki' => $kaki,
            'predikat' => (string)($r['predikat'] ?? '-'),
        ];
    }
}

if (!function_exists('rekap_baris_komponen_tahun4')) {
    /** Baris tabel "Rekapitulasi Penilaian Kinerja Tahun Keempat" (komponen 7 penilai). */
    function rekap_baris_komponen_tahun4(array $komp): array
    {
        $out = [];
        $no = 0;
        foreach ($komp['komponen'] as $k) {
            $no++;
            $out[] = ['no' => $no, 'judul' => (string)$k['label'], 'nilai' => rekap_nilai_teks($k['nilai'])];
        }
        return $out;
    }
}

if (!function_exists('rekap_baris_empat_tahunan')) {
    /** Baris tabel "Rekapitulasi Penilaian Kinerja Empat Tahunan" (per tahun + NKKM). */
    function rekap_baris_empat_tahunan(array $r4): array
    {
        $out = [];
        foreach ($r4['per_tahun'] as $tahun => $nilai) {
            $out[] = [
                'no' => (int)$tahun,
                'judul' => 'Penilaian Kinerja ' . (PKKM_TAHUN_LIST[(int)$tahun] ?? '')
                    . ((int)$tahun === 4 ? ' (kumulatif seluruh penilai)' : ''),
                'nilai' => rekap_nilai_teks($nilai),
            ];
        }
        return $out;
    }
}

if (!function_exists('rekap_judul_tahun')) {
    /** Judul lembar satu tahun: dua baris seperti contoh ("… Tahunan Kepala Madrasah" + "Tahun …"). */
    function rekap_judul_tahun(int $tahun): array
    {
        return [
            'Rekapitulasi Hasil Penilaian Kinerja Tahunan Kepala Madrasah',
            (string)(PKKM_TAHUN_LIST[$tahun] ?? ''),
        ];
    }
}

if (!function_exists('rekap_kalimat_penilaian')) {
    /** Kalimat pengantar tabel (persis pola contoh pemilik). */
    function rekap_kalimat_penilaian(string $bagian, $tahun): string
    {
        $t = rekap_isi_teks($tahun);
        return 'Telah dilakukan ' . $bagian . ' Kepala Madrasah pada tahun '
            . ($t !== '' ? $t : '-') . ' dengan hasil sebagai berikut.';
    }
}
