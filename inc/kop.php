<?php
/**
 * e-PKKM - kepala surat (kop) & blok tanda tangan untuk lembar cetak.
 */

if (!function_exists('kop_cetak')) {
    /** Kop lembar cetak: nama madrasah + alamat + wilayah + kontak. */
    function kop_cetak(array $s, string $judul = '', string $baris = ''): string
    {
        $wilayah = [];
        if (trim((string)($s['desa'] ?? '')) !== '') {
            $wilayah[] = 'Desa/Kelurahan ' . $s['desa'];
        }
        if (trim((string)($s['kecamatan'] ?? '')) !== '') {
            $wilayah[] = 'Kec. ' . $s['kecamatan'];
        }
        if (trim((string)($s['kabupaten'] ?? '')) !== '') {
            $wilayah[] = 'Kab. ' . $s['kabupaten'];
        }
        if (trim((string)($s['provinsi'] ?? '')) !== '') {
            $wilayah[] = $s['provinsi'];
        }
        $kontak = [];
        if (trim((string)($s['email'] ?? '')) !== '') {
            $kontak[] = 'email: ' . $s['email'];
        }
        if (trim((string)($s['website'] ?? '')) !== '') {
            $kontak[] = 'website: ' . $s['website'];
        }
        $ident = [];
        if (trim((string)($s['npsn'] ?? '')) !== '') {
            $ident[] = 'NPSN: ' . $s['npsn'];
        }
        if (trim((string)($s['nsm'] ?? '')) !== '') {
            $ident[] = 'NSM: ' . $s['nsm'];
        }
        if (trim((string)($s['telepon'] ?? '')) !== '') {
            $ident[] = 'HP: ' . $s['telepon'];
        }

        $h = '<div class="sheet-head">';
        if (trim((string)($s['alamat'] ?? '')) !== '') {
            $h .= '<div class="kop-alamat">' . e($s['alamat']) . '</div>';
        }
        if ($wilayah) {
            $h .= '<div class="kop-alamat">' . e(implode(', ', $wilayah)) . '</div>';
        }
        if ($kontak) {
            $h .= '<div class="kop-alamat">' . e(implode(', ', $kontak)) . '</div>';
        }
        if ($ident) {
            $h .= '<div class="kop-alamat">' . e(implode(', ', $ident)) . '</div>';
        }
        if ($baris !== '') {
            $h .= '<div class="kop-ket">' . e($baris) . '</div>';
        }
        if ($judul !== '') {
            $h .= '<div class="kop-judul">' . e($judul) . '</div>';
        }
        return $h . '</div>';
    }
}

if (!function_exists('ttd_tempat')) {
    /** Baris "Tempat, tanggal" di atas nama penanda tangan. */
    function ttd_tempat(?string $tempat, ?string $tanggal): string
    {
        $t = trim((string)$tempat);
        $d = tanggal_id((string)$tanggal);
        if ($t !== '' && $d !== '') {
            return $t . ', ' . $d;
        }
        return $t !== '' ? $t : $d;
    }
}

if (!function_exists('ttd_blok')) {
    /**
     * Satu blok tanda tangan.
     * $jenis: 'kanan' (pelaksana/penilai) atau 'kiri' (mengetahui / kepala madrasah).
     */
    function ttd_blok(string $judul, string $nama, string $nip = '', string $jenis = 'kanan', string $tempatTanggal = '', string $space = ''): string
    {
        $h = '<div class="sheet-sign sheet-sign-' . e($jenis) . '">';
        if ($tempatTanggal !== '') {
            $h .= '<div class="ttd-tempat">' . e($tempatTanggal) . '</div>';
        }
        if ($space !== '') {
            $h .= '<div class="ttd-spacer">' . e($space) . '</div>';
        }
        $h .= '<div class="ttd-jabatan">' . nl2br(e($judul)) . '</div>';
        $h .= '<div class="ttd-nama">' . e($nama) . '</div>';
        if (trim($nip) !== '') {
            $h .= '<div class="ttd-nip">NIP. ' . e($nip) . '</div>';
        }
        return $h . '</div>';
    }
}

if (!function_exists('ttd_blok_rapi')) {
    /**
     * Blok tanda tangan LEMBAR RESMI (gaya berkas instrumen PKKM contoh pemilik).
     *
     * Susunannya: baris "tempat, tanggal" (blok kiri diisi baris kosong sebagai PENYEIMBANG) ->
     * jabatan -> ruang tanda tangan -> nama -> NIP. Karena kedua blok selalu memakai jumlah baris
     * yang SAMA, NAMA pada blok kiri & kanan tercetak SEJAJAR. Jangan menghapus baris penyeimbang
     * itu tanpa memeriksa keselarasan nama di lembar cetak.
     */
    function ttd_blok_rapi(string $jabatan, string $nama, string $nip = '', string $jenis = 'kiri', string $tempatTanggal = ''): string
    {
        $nip = trim($nip);
        $h = '<div class="sheet-sign sheet-sign-' . e($jenis) . '">';
        $h .= '<div class="ttd-tempat">' . ($tempatTanggal !== '' ? e($tempatTanggal) : '&nbsp;') . '</div>';
        $h .= '<div class="ttd-jabatan">' . e($jabatan) . '</div>';
        $h .= '<div class="ttd-ruang"></div>';
        $h .= '<div class="ttd-nama">' . e($nama) . '</div>';
        if ($nip !== '' && !in_array($nip, ['-', '–', '—'], true)) {
            $h .= '<div class="ttd-nip">NIP. ' . e($nip) . '</div>';
        }
        return $h . '</div>';
    }
}

if (!function_exists('kop_meta_baris')) {
    /** Identitas ringkas pada lembar cetak (label: nilai). */
    function kop_meta_baris(array $baris): string
    {
        $h = '<div class="sheet-meta">';
        foreach ($baris as $label => $nilai) {
            if ($nilai === null || $nilai === '') {
                continue;
            }
            $h .= '<div><span>' . e($label) . '</span><b>' . e($nilai) . '</b></div>';
        }
        return $h . '</div>';
    }
}
