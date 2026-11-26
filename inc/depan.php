<?php
/**
 * e-PKKM - SLIDER GAMBAR HALAMAN DEPAN (v1.13.0, permintaan pemilik).
 *
 * Permintaan pemilik: *"Yang saya tandai warna merah dibuatkan slider beberapa gambar yang diisi
 * oleh super admin."* Area yang dimaksud = tempat ilustrasi SVG pada kolom kiri halaman masuk
 * (`.dp-gambar`). Isinya kini **slider gambar** yang dikelola Super Admin lewat menu
 * **Layanan → Slider Halaman Depan** (`depan_slider.php`).
 *
 * Catatan penting:
 *  - Gambar disimpan lewat MEDIA PROXY platform (`media_upload()`); basis data hanya menyimpan URL
 *    CDN-nya (`depan_slider.foto`). Batas per berkas FOTO_MAKS_BYTE (15 MB) + validasi isi gambar.
 *  - Bila Super Admin belum mengunggah satu gambar pun, halaman depan menampilkan **ilustrasi SVG
 *    bawaan** (`depan_ilustrasi()`) supaya tidak pernah kosong.
 *  - v1.14.0 (permintaan pemilik): gambar slider pada halaman depan **DILINDUNGI** — alamat CDN
 *    tidak pernah dicetak ke HTML; gambar dialirkan lewat `gambar.php` (server-side, 403 bila
 *    dibuka langsung) dan tidak bisa diklik kanan / diseret / disimpan (kelas `lp-lindung`).
 *  - Slider murni progresif: tanpa JavaScript gambar pertama tetap tampil (`.dp-slide.on`).
 */

if (!function_exists('depan_slider_list')) {
    /**
     * Daftar gambar slider halaman depan, urut `urutan` lalu id.
     *
     * @param bool $aktifSaja true = hanya yang diaktifkan (dipakai halaman depan)
     */
    function depan_slider_list(bool $aktifSaja = true): array
    {
        try {
            $sql = 'SELECT * FROM depan_slider' . ($aktifSaja ? ' WHERE aktif = 1' : '')
                . ' ORDER BY urutan, id';
            return all($sql);
        } catch (Throwable $t) {
            return [];
        }
    }
}

if (!function_exists('depan_slider_by_id')) {
    function depan_slider_by_id(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        try {
            return one('SELECT * FROM depan_slider WHERE id = ?', [$id]);
        } catch (Throwable $t) {
            return null;
        }
    }
}

if (!function_exists('depan_slider_urut_baru')) {
    /** Urutan untuk gambar baru: paling akhir (kelipatan 10). */
    function depan_slider_urut_baru(): int
    {
        $maks = (int)(val('SELECT COALESCE(MAX(urutan), 0) FROM depan_slider') ?? 0);
        return $maks + 10;
    }
}

if (!function_exists('depan_slider_geser')) {
    /** Tukar urutan dengan tetangganya (arah -1 naik, +1 turun). */
    function depan_slider_geser(int $id, int $arah): bool
    {
        $s = depan_slider_by_id($id);
        if (!$s) {
            return false;
        }
        $tetangga = $arah < 0
            ? one('SELECT * FROM depan_slider WHERE urutan < ? OR (urutan = ? AND id < ?)
                    ORDER BY urutan DESC, id DESC LIMIT 1', [(int)$s['urutan'], (int)$s['urutan'], $id])
            : one('SELECT * FROM depan_slider WHERE urutan > ? OR (urutan = ? AND id > ?)
                    ORDER BY urutan ASC, id ASC LIMIT 1', [(int)$s['urutan'], (int)$s['urutan'], $id]);
        if (!$tetangga) {
            return false;
        }
        mulai_transaksi();
        try {
            q('UPDATE depan_slider SET urutan = ? WHERE id = ?', [-1, $id]);
            q('UPDATE depan_slider SET urutan = ? WHERE id = ?', [(int)$s['urutan'], (int)$tetangga['id']]);
            q('UPDATE depan_slider SET urutan = ? WHERE id = ?', [(int)$tetangga['urutan'], $id]);
            selesai_transaksi(true);
            return true;
        } catch (Throwable $t) {
            selesai_transaksi(false);
            return false;
        }
    }
}

if (!function_exists('depan_slider_unggah')) {
    /**
     * Unggah SATU gambar slider ke media proxy.
     *
     * @param array $berkas baris $_FILES
     * @return array{ok:bool,url:string,pesan:string}
     */
    function depan_slider_unggah(array $berkas): array
    {
        $gagal = ['ok' => false, 'url' => '', 'pesan' => ''];
        if (($berkas['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return ['ok' => false, 'url' => '', 'pesan' => 'Pilih berkas gambar terlebih dahulu.'];
        }
        if ((int)($berkas['error'] ?? 0) !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'url' => '', 'pesan' => 'Berkas gagal diunggah (kode '
                . (int)($berkas['error'] ?? 0) . '). Perkecil berkasnya lalu ulangi.'];
        }
        $nama = (string)($berkas['name'] ?? 'gambar');
        $tmp = (string)($berkas['tmp_name'] ?? '');
        $ukuran = (int)($berkas['size'] ?? 0);
        $cek = validasi_foto($nama, $ukuran, $tmp);
        if (!$cek['ok']) {
            return ['ok' => false, 'url' => '', 'pesan' => $cek['pesan']];
        }
        $bytes = @file_get_contents($tmp);
        if ($bytes === false || $bytes === '') {
            return ['ok' => false, 'url' => '', 'pesan' => 'Berkas gambar tidak dapat dibaca.'];
        }
        $hasil = media_upload((string)$bytes, $nama);
        if (empty($hasil['ok'])) {
            return ['ok' => false, 'url' => '', 'pesan' => (string)($hasil['pesan'] ?? 'Unggahan gagal.')];
        }
        return ['ok' => true, 'url' => (string)$hasil['url'], 'pesan' => ''];
    }
}

if (!function_exists('depan_ilustrasi')) {
    /**
     * Ilustrasi madrasah (SVG sebaris, tanpa berkas gambar) — dipakai halaman depan sebagai
     * CADANGAN bila Super Admin belum mengunggah satu pun gambar slider.
     */
    function depan_ilustrasi(): string
    {
        return <<<'SVG'
<svg class="dp-ilustrasi" viewBox="0 0 720 430" role="img" aria-label="Ilustrasi madrasah dan penilaian kinerja">
  <defs>
    <linearGradient id="dpLangit" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0" stop-color="#f4faf6"/><stop offset="1" stop-color="#e6f3ea"/>
    </linearGradient>
    <linearGradient id="dpKubah" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0" stop-color="#2f8b5f"/><stop offset="1" stop-color="#14603f"/>
    </linearGradient>
    <linearGradient id="dpBukit" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0" stop-color="#bfe3c8"/><stop offset="1" stop-color="#8ecb9f"/>
    </linearGradient>
  </defs>

  <rect x="0" y="0" width="720" height="430" rx="26" fill="url(#dpLangit)"/>
  <circle cx="600" cy="86" r="46" fill="#ffffff" opacity=".7"/>
  <circle cx="132" cy="96" r="30" fill="#ffffff" opacity=".55"/>

  <!-- awan -->
  <g fill="#ffffff" opacity=".9">
    <ellipse cx="176" cy="72" rx="34" ry="17"/>
    <ellipse cx="204" cy="60" rx="26" ry="20"/>
    <ellipse cx="228" cy="74" rx="22" ry="14"/>
    <ellipse cx="560" cy="150" rx="30" ry="15"/>
    <ellipse cx="584" cy="139" rx="22" ry="17"/>
  </g>

  <!-- ikon melayang -->
  <g opacity=".95">
    <rect x="86" y="132" width="52" height="40" rx="8" fill="#ffffff" stroke="#9fc7ac"/>
    <rect x="94" y="140" width="36" height="4" rx="2" fill="#b9d8c4"/>
    <rect x="94" y="150" width="26" height="4" rx="2" fill="#b9d8c4"/>
    <circle cx="130" cy="158" r="7" fill="#c9a227"/>
    <rect x="596" y="196" width="54" height="44" rx="9" fill="#ffffff" stroke="#9fc7ac"/>
    <rect x="606" y="222" width="8" height="10" rx="2" fill="#2f8b5f"/>
    <rect x="619" y="214" width="8" height="18" rx="2" fill="#4aa877"/>
    <rect x="632" y="206" width="8" height="26" rx="2" fill="#c9a227"/>
  </g>
  <g fill="#cfe7d8">
    <path d="M300 92l7 16 16 7-16 7-7 16-7-16-16-7 16-7z"/>
    <path d="M660 288l5 12 12 5-12 5-5 12-5-12-12-5 12-5z"/>
  </g>

  <!-- bukit -->
  <path d="M0 316c74-44 140-30 206-8 74 24 128-22 204-22 82 0 130 40 196 40 42 0 82-10 114-22v126H0z" fill="url(#dpBukit)"/>
  <path d="M0 356c90-30 166-6 238 8 82 16 140-14 214-16 90-2 158 26 268 12v70H0z" fill="#a8d6b5" opacity=".75"/>

  <!-- pepohonan -->
  <g>
    <rect x="96" y="286" width="9" height="56" rx="4" fill="#7a5b3a"/>
    <circle cx="100" cy="272" r="30" fill="#3f9a68"/>
    <circle cx="74" cy="286" r="20" fill="#4aa877"/>
    <circle cx="126" cy="288" r="18" fill="#357f56"/>
    <rect x="612" y="292" width="8" height="52" rx="4" fill="#7a5b3a"/>
    <circle cx="616" cy="276" r="26" fill="#3f9a68"/>
    <circle cx="644" cy="290" r="17" fill="#357f56"/>
  </g>

  <!-- madrasah -->
  <g>
    <!-- menara kiri & kanan -->
    <g>
      <rect x="212" y="212" width="30" height="126" rx="4" fill="#f5faf6" stroke="#14603f" stroke-width="3"/>
      <path d="M208 212h38l-6-16h-26z" fill="#14603f"/>
      <circle cx="227" cy="188" r="6" fill="#c9a227"/>
      <rect x="221" y="238" width="12" height="26" rx="6" fill="#d6eadc" stroke="#14603f" stroke-width="2"/>
    </g>
    <g>
      <rect x="480" y="212" width="30" height="126" rx="4" fill="#f5faf6" stroke="#14603f" stroke-width="3"/>
      <path d="M476 212h38l-6-16h-26z" fill="#14603f"/>
      <circle cx="495" cy="188" r="6" fill="#c9a227"/>
      <rect x="489" y="238" width="12" height="26" rx="6" fill="#d6eadc" stroke="#14603f" stroke-width="2"/>
    </g>

    <!-- bangunan utama -->
    <rect x="250" y="238" width="222" height="100" rx="6" fill="#ffffff" stroke="#14603f" stroke-width="3"/>
    <path d="M361 238c-30 0-48-18-48-40 0 22-18 24-18 24h132s-18-2-18-24c0 22-18 40-48 40z" fill="url(#dpKubah)"/>
    <circle cx="361" cy="176" r="7" fill="#c9a227"/>
    <path d="M361 150c9 8 9 18 0 24-9-6-9-16 0-24z" fill="#2f8b5f"/>
    <!-- jendela & pintu -->
    <g fill="#d6eadc" stroke="#14603f" stroke-width="2">
      <rect x="272" y="258" width="26" height="34" rx="13"/>
      <rect x="424" y="258" width="26" height="34" rx="13"/>
      <rect x="316" y="266" width="24" height="26" rx="12"/>
      <rect x="382" y="266" width="24" height="26" rx="12"/>
    </g>
    <path d="M345 338v-34c0-9 7-16 16-16s16 7 16 16v34z" fill="#14603f"/>
    <rect x="292" y="300" width="40" height="8" rx="4" fill="#c9a227"/>
    <rect x="390" y="300" width="40" height="8" rx="4" fill="#c9a227"/>
  </g>

  <!-- papan nama -->
  <g>
    <rect x="300" y="352" width="122" height="30" rx="8" fill="#0b4a33"/>
    <text x="361" y="372" text-anchor="middle" font-family="Inter, system-ui, sans-serif"
          font-size="15" font-weight="700" fill="#c9a227">MADRASAH</text>
  </g>

  <!-- lembar penilaian -->
  <g>
    <rect x="70" y="286" width="104" height="86" rx="10" fill="#ffffff" stroke="#9fc7ac" stroke-width="2"/>
    <rect x="84" y="302" width="60" height="5" rx="2" fill="#b9d8c4"/>
    <rect x="84" y="314" width="76" height="5" rx="2" fill="#b9d8c4"/>
    <rect x="84" y="326" width="48" height="5" rx="2" fill="#b9d8c4"/>
    <circle cx="150" cy="348" r="14" fill="#2f8b5f"/>
    <path d="M143 348l5 5 9-10" fill="none" stroke="#ffffff" stroke-width="3"
          stroke-linecap="round" stroke-linejoin="round"/>
  </g>
</svg>
SVG;
    }
}

if (!function_exists('depan_slider_html')) {
    /**
     * HTML area gambar halaman depan: slider gambar (bila Super Admin sudah mengisi) atau ilustrasi
     * bawaan sebagai cadangan.
     */
    function depan_slider_html(): string
    {
        $daftar = array_values(array_filter(depan_slider_list(true),
            fn($s) => trim((string)($s['foto'] ?? '')) !== ''));
        if (!$daftar) {
            return depan_ilustrasi();
        }
        $banyak = count($daftar) > 1;
        $html = '<div class="dp-slider" id="dpSlider" data-slider' . ($banyak ? ' data-otomatis="6000"' : '') . '>'
            . '<div class="dp-slide-layar">';
        foreach ($daftar as $i => $s) {
            $judul = trim((string)($s['judul'] ?? ''));
            $ket = trim((string)($s['keterangan'] ?? ''));
            // v1.14.0 (permintaan pemilik): gambar DILINDUNGI — alamat CDN tidak pernah dicetak.
            // Yang dipakai gambar_tag() → `gambar.php?j=slider&id=…&v=…` (diteruskan server) dan
            // diberi `lp-lindung`/`data-lindung` supaya tidak bisa diklik kanan / diseret.
            // Wadah `data-lindung-wadah` menutup seluruhnya dengan lapisan tembus pandang.
            $html .= '<figure class="dp-slide' . ($i === 0 ? ' on' : '') . '" data-slide data-lindung-wadah="1">'
                . gambar_tag((int)$s['id'], (string)$s['foto'],
                    $judul !== '' ? $judul : 'Gambar halaman depan ' . ($i + 1),
                    'dp-slide-gambar', $i !== 0);
            if ($judul !== '' || $ket !== '') {
                $html .= '<figcaption class="dp-slide-ket">'
                    . ($judul !== '' ? '<b>' . e($judul) . '</b>' : '')
                    . ($ket !== '' ? '<span>' . e($ket) . '</span>' : '')
                    . '</figcaption>';
            }
            $html .= '</figure>';
        }
        $html .= '</div>';
        if ($banyak) {
            $html .= '<button class="dp-slide-panah dp-slide-sebelum" type="button" data-slide-panah="-1"'
                . ' aria-label="Gambar sebelumnya">&#10094;</button>'
                . '<button class="dp-slide-panah dp-slide-lanjut" type="button" data-slide-panah="1"'
                . ' aria-label="Gambar berikutnya">&#10095;</button>'
                . '<div class="dp-slide-titik" role="tablist" aria-label="Pilih gambar">';
            foreach ($daftar as $i => $s) {
                $html .= '<button type="button" class="dp-slide-titik-item' . ($i === 0 ? ' on' : '') . '"'
                    . ' data-slide-titik="' . $i . '" role="tab" aria-label="Gambar ' . ($i + 1) . '"'
                    . ' aria-selected="' . ($i === 0 ? 'true' : 'false') . '"></button>';
            }
            $html .= '</div>';
        }
        return $html . '</div>';
    }
}

if (!function_exists('logo_app_url')) {
    /** Alamat CDN logo aplikasi (diunggah Super Admin) — '' bila belum ada. */
    function logo_app_url(): string
    {
        $url = trim((string)app_setting('logo_app'));
        return gambar_host_sah($url) ? $url : '';
    }
}

if (!function_exists('logo_app_bawaan')) {
    /**
     * Lencana logo BAWAAN (SVG sebaris) — dipakai bila Super Admin belum mengunggah logo.
     *
     * v1.17.0 (permintaan pemilik): tempat logo aplikasi pada panel masuk dibuat BULAT. Selama
     * logo belum diunggah, ditampilkan lencana ini supaya lingkarannya tidak kosong.
     */
    function logo_app_bawaan(): string
    {
        return <<<'SVG'
<svg class="dp-logo-lencana" viewBox="0 0 120 120" role="img" aria-label="Logo e-PKKM">
  <defs>
    <linearGradient id="lgEmas" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0" stop-color="#f0d97a"/><stop offset=".5" stop-color="#c9a227"/><stop offset="1" stop-color="#f0d97a"/>
    </linearGradient>
    <linearGradient id="lgHijau" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0" stop-color="#1c6b47"/><stop offset="1" stop-color="#0b4a33"/>
    </linearGradient>
  </defs>
  <circle cx="60" cy="60" r="58" fill="url(#lgEmas)"/>
  <circle cx="60" cy="60" r="51" fill="url(#lgHijau)"/>
  <circle cx="60" cy="60" r="45" fill="none" stroke="#ffffff" stroke-opacity=".35" stroke-width="1.4"/>
  <!-- bintang -->
  <path d="M60 19l3.6 7.5 8.2 1.1-6 5.8 1.5 8.2-7.3-3.9-7.3 3.9 1.5-8.2-6-5.8 8.2-1.1z" fill="#f0d97a"/>
  <!-- buku terbuka -->
  <path d="M27 70c11-4.5 22-4 33 3 11-7 22-7.5 33-3v11c-11-4-22-3.5-33 3.5-11-7-22-7.5-33-3.5z" fill="#ffffff"/>
  <path d="M60 63.5v24.5" stroke="#c9a227" stroke-width="2.4" stroke-linecap="round"/>
  <!-- toga -->
  <path d="M60 40l21 8-21 8-21-8z" fill="#f0d97a"/>
  <path d="M72 50.5v8.5c0 3.4-5.4 6-12 6s-12-2.6-12-6v-8.5" fill="none" stroke="#f0d97a" stroke-width="2.2"/>
  <path d="M86 49v12" stroke="#ffffff" stroke-width="2" stroke-linecap="round"/>
  <circle cx="86" cy="63" r="3" fill="#ffffff"/>
  <!-- centang daftar -->
  <g stroke="#8fd6ad" stroke-width="2.4" fill="none" stroke-linecap="round" stroke-linejoin="round">
    <path d="M31 32l3.4 3.4L42 28"/><path d="M31 44l3.4 3.4L42 40"/>
  </g>
</svg>
SVG;
    }
}

if (!function_exists('logo_app_tag')) {
    /**
     * Markup logo aplikasi untuk panel masuk (BULAT).
     *
     * Logo diunggah Super Admin (menu Layanan → Logo Aplikasi) dan disimpan di media proxy; alamat
     * CDN-nya tidak pernah dicetak ke HTML — gambar dialirkan `gambar.php?j=logo` (sama seperti
     * gambar halaman depan yang sudah dikunci pada v1.14.0).
     */
    function logo_app_tag(string $kelas = ''): string
    {
        $remote = logo_app_url();
        if ($remote === '') {
            return '<div class="dp-logo ' . e($kelas) . '">' . logo_app_bawaan() . '</div>';
        }
        $src = gambar_url(0, $remote, 'logo');
        return '<div class="dp-logo ' . e($kelas) . '">'
            . '<img src="' . e($src) . '" alt="' . e(APP_NAME) . '" class="lp-lindung" data-lindung="1"'
            . ' draggable="false" decoding="async"></div>';
    }
}
