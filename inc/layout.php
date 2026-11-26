<?php
/**
 * e-PKKM - kerangka halaman: sidebar, bilah atas, isi, dan penutup.
 */

if (!function_exists('ikon')) {
    /** Ikon SVG sederhana (inline, tanpa pustaka luar). */
    function ikon(string $nama, int $ukuran = 18): string
    {
        $p = [
            'dash' => '<path d="M3 12l9-8 9 8v8a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/>',
            'madrasah' => '<path d="M12 3l9 6v12H3V9z"/><path d="M9 21v-6h6v6"/>',
            'nilai' => '<path d="M4 4h16v16H4z"/><path d="M8 9h8M8 13h8M8 17h5"/>',
            'guru' => '<circle cx="12" cy="8" r="3.2"/><path d="M5 20c1.4-3.6 4-5.2 7-5.2s5.6 1.6 7 5.2"/>',
            'lapor' => '<path d="M5 3h9l5 5v13H5z"/><path d="M14 3v5h5"/><path d="M8 13h8M8 17h5"/>',
            'rekap' => '<path d="M4 20V6M10 20V3M16 20v-9M22 20H2"/>',
            'akun' => '<circle cx="12" cy="9" r="3.4"/><path d="M4.5 20c1.5-3.8 4.3-5.6 7.5-5.6s6 1.8 7.5 5.6"/>',
            'pengawas' => '<circle cx="11" cy="8" r="3"/><path d="M4 20c1.3-3.4 3.8-5 7-5"/><path d="M15 15l2 2 4-4"/>',
            'kunci' => '<circle cx="8" cy="12" r="3.5"/><path d="M11.5 12H21M17 12v3M20 12v2"/>',
            'keluar' => '<path d="M10 4H5v16h5"/><path d="M14 8l4 4-4 4M18 12H9"/>',
            'cetak' => '<path d="M7 8V3h10v5"/><path d="M5 8h14v8h-3v5H8v-5H5z"/>',
            'simpan' => '<path d="M5 3h11l3 3v15H5z"/><path d="M8 3v6h6V3M8 21v-7h8v7"/>',
            'tambah' => '<path d="M12 5v14M5 12h14"/>',
            'menu' => '<path d="M4 7h16M4 12h16M4 17h16"/>',
            'buka' => '<path d="M4 6h7l2 2h7v11H4z"/>',
            'dokumen' => '<path d="M13 3v5h5"/><path d="M19 8.5V20a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h7z"/>'
                . '<path d="M8 13h8M8 17h5"/>',
            'unggah' => '<path d="M12 16V6M8 10l4-4 4 4"/><path d="M4 18h16v3H4z"/>',
            'unduh' => '<path d="M12 4v10M8 10l4 4 4-4"/><path d="M4 18h16v3H4z"/>',
            'centang' => '<path d="M4 12l5 5L20 6"/>',
            'panah' => '<path d="M5 12h13M13 6l6 6-6 6"/>',
            'layanan' => '<path d="M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h6v6h-6z"/>',
            'daftar' => '<path d="M8 6h12M8 12h12M8 18h12"/><path d="M4 6h.01M4 12h.01M4 18h.01"/>',
            'clock' => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/>',
            'user' => '<circle cx="12" cy="8.5" r="3.3"/><path d="M4.8 20c1.4-3.6 4-5.4 7.2-5.4s5.8 1.8 7.2 5.4"/>',
        ];
        $isi = $p[$nama] ?? $p['dash'];
        return '<svg class="ic" viewBox="0 0 24 24" width="' . $ukuran . '" height="' . $ukuran . '" fill="none" '
            . 'stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">' . $isi . '</svg>';
    }
}

if (!function_exists('menu_sidebar')) {
    /**
     * Daftar menu sesuai peran.
     *
     * Bentuk tiap entri: [href, label, ikon, lencana] atau, untuk menu berkelompok (hanya Super Admin),
     * ['grup', 'Nama Grup', 'ikon', [ [href, label, ikon, lencana], … ]].
     *
     * SUSUNAN HALAMAN ADMIN (permintaan pemilik, jangan diubah tanpa bertanya):
     *   Dashboard → Tarik dari Supervisi → Kelola Madrasah → Layanan (Akun Penilai, Daftar
     *   Jabatan Penilai, Slider Halaman Depan) → Log Aktivitas → Data Madrasah → Daftar Penilai →
     *   Penilaian Kepala Madrasah → Laporan → Rekap → Akun Saya.
     * Catatan v1.8.0: menu "Akun Pengawas" berganti nama menjadi **Akun Penilai** (isinya SELURUH
     * akun penilai: pengawas maupun jabatan lain), dan menu **Penilaian Guru & Tendik DIHAPUS**
     * (peran Guru/Tendik/Komite/Kabid/Yayasan/Kasie kini menjadi AKUN PENILAI Tahun Keempat).
     * Catatan v1.11.0: menu **Halaman Depan (Landing) DIHAPUS** (halaman depan kini panel masuk dua
     * kolom tanpa landing page) dan "Persetujuan Madrasah" menjadi **Kelola Madrasah** karena
     * pendaftaran mandiri madrasah dihapus (lembaga ditarik dari aplikasi Supervisi).
     */
    function menu_sidebar(): array
    {
        $u = current_user();
        $s = current_sekolah();
        $m = [];
        // Peran pengawas kini tampil sebagai PENILAI (permintaan pemilik) — statusnya tetap pengawas.
        $m[] = ['index.php', $u['role'] === 'pengawas' ? 'Dashboard Penilai' : 'Dashboard', 'dash'];

        // ---------------------------------------------------------------- SUPER ADMIN
        if ($u['role'] === 'super') {
            // Saat Super Admin MASUK ke satu madrasah (konteks aktif), dua menu pengelolaan madrasah
            // itu ditampilkan tepat di bawah Dashboard agar tetap dapat dipakai.
            if ($s) {
                $m[] = ['madrasah.php', 'Input Data Madrasah', 'madrasah'];
                $m[] = ['dokumen.php', 'Unggah Dokumen', 'dokumen'];
            }
            // TARIK DATA DARI APLIKASI SUPERVISI (v1.11.0, permintaan pemilik): lembaga aktif +
            // kepala madrasah + pengawas. Satu-satunya jalan masuknya lembaga (pendaftaran mandiri
            // madrasah sudah DIHAPUS).
            $m[] = ['tarik_supervisi.php', 'Tarik dari Supervisi', 'unduh'];
            // Pengelolaan madrasah (dulu "Persetujuan Madrasah" — pendaftaran mandiri sudah dihapus)
            $m[] = ['sekolah.php', 'Kelola Madrasah', 'madrasah'];
            // sub menu LAYANAN (menu "Halaman Depan" DIHAPUS karena landing page tidak dipakai lagi)
            $m[] = ['grup', 'Layanan', 'layanan', [
                ['pengawas.php', 'Akun Penilai', 'pengawas'],
                ['jabatan.php', 'Daftar Jabatan Penilai', 'nilai'],
                // v1.13.0 (permintaan pemilik): slider gambar pada halaman masuk diisi Super Admin
                ['depan_slider.php', 'Slider Halaman Depan', 'buka'],
                // v1.17.0 (permintaan pemilik): logo aplikasi bulat pada panel masuk
                ['logo.php', 'Logo Aplikasi', 'madrasah'],
            ]];
            // v1.15.0 (permintaan pemilik): Log Aktivitas — riwayat masuk/keluar/gagal + pengguna
            // online, seperti aplikasi e-GURU. Lencana = jumlah sesi yang sedang online.
            $m[] = ['log.php', 'Log Aktivitas', 'clock', log_jumlah_online()];
            $m[] = ['data_madrasah.php', 'Data Madrasah', 'daftar', (int)val('SELECT COUNT(*) FROM sekolah')];
            $m[] = ['data_penilai.php', 'Daftar Penilai', 'guru',
                (int)val("SELECT COUNT(*) FROM users WHERE role = 'pengawas'")];
            $m[] = ['penilaian.php', 'Penilaian Kepala Madrasah', 'nilai'];
            $m[] = ['laporan.php', 'Laporan', 'lapor'];
            $m[] = ['rekap.php', 'Rekap', 'rekap'];
            $m[] = ['akun.php', 'Akun Saya', 'akun'];
            return $m;
        }

        // ------------------------------------------------------- KEPALA MADRASAH
        if ($u['role'] === 'admin' || ($u['role'] === 'super' && $s)) {
            $m[] = ['madrasah.php', 'Input Data Madrasah', 'madrasah'];
            $m[] = ['dokumen.php', 'Unggah Dokumen', 'dokumen'];
        }
        $m[] = ['penilaian.php', 'Penilaian Kepala Madrasah', 'nilai'];
        if ($u['role'] !== 'pengawas' || $s) {
            $m[] = ['laporan.php', 'Laporan', 'lapor'];
            $m[] = ['rekap.php', 'Rekap PKKM', 'rekap'];
        }
        $m[] = ['akun.php', 'Akun Saya', 'akun'];
        return $m;
    }
}

if (!function_exists('page_top')) {
    /**
     * Kerangka halaman: sidebar + bilah atas + pembuka isi.
     *
     * @param string $judul judul halaman
     * @param string $sub   subjudul (HTML boleh) — dicetak di bawah judul
     * @param bool   $lebar true = isi memakai lebar layar penuh (halaman bertabel lebar,
     *                      mis. Akun Penilai / Daftar Jabatan Penilai yang dulu terasa sempit
     *                      dan menyisakan ruang kosong besar di layar lebar)
     */
    function page_top(string $judul, string $sub = '', bool $lebar = false): void
    {
        $u = require_login();
        $s = current_sekolah();
        $halaman = basename($_SERVER['SCRIPT_NAME'] ?? 'index.php');
        echo '<!doctype html><html lang="id"><head><meta charset="utf-8">';
        echo '<meta name="viewport" content="width=device-width,initial-scale=1">';
        echo '<title>' . e($judul) . ' - ' . e(APP_NAME) . '</title>';
        echo '<link rel="stylesheet" href="assets/style.css?v=' . e(APP_VERSION) . '">';
        echo '<link rel="icon" href="data:image/svg+xml,'
            . rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><rect width="24" height="24" fill="#0b4a33"/><path d="M12 4l8 5v11H4V9z" fill="#c9a227"/></svg>')
            . '">';
        echo '</head><body class="bg-deco">';
        echo '<div class="shell">';

        // ---------------- sidebar ----------------
        echo '<aside class="side" id="side"><div class="side-brand">'
            . '<span class="side-logo">' . ikon('madrasah', 22) . '</span>'
            . '<span><b>' . e(APP_NAME) . '</b><i>' . e(APP_SUB) . '</i></span>'
            . '</div><nav class="nav">';
        foreach (menu_sidebar() as $item) {
            // -------- menu berkelompok (mis. "Layanan"): <details> bekerja tanpa JavaScript --------
            if ($item[0] === 'grup') {
                $labelGrup = (string)$item[1];
                // ⚠️ JANGAN memakai nama variabel $sub di sini: $sub adalah SUBJUDUL halaman
                // (parameter page_top). Versi lama memakai $sub untuk daftar submenu sehingga
                // seluruh halaman Super Admin mencetak "Array" di bawah judulnya.
                $subMenu = is_array($item[3] ?? null) ? $item[3] : [];
                $aktifGrup = false;
                foreach ($subMenu as $sb) {
                    if ($halaman === $sb[0]) {
                        $aktifGrup = true;
                        break;
                    }
                }
                echo '<details class="nav-grup"' . ($aktifGrup ? ' open' : '') . '>'
                    . '<summary class="nav-grup-kepala">' . ikon((string)$item[2])
                    . '<span>' . e($labelGrup) . '</span><i class="nav-caret">&#9662;</i></summary>'
                    . '<div class="nav-sub">';
                foreach ($subMenu as $sb) {
                    $on = $halaman === $sb[0] ? ' on' : '';
                    echo '<a class="nav-item nav-anak' . $on . '" href="' . e($sb[0]) . '">' . ikon((string)$sb[2])
                        . '<span>' . e($sb[1]) . '</span>';
                    $lencanaSb = (int)($sb[3] ?? 0);
                    if ($lencanaSb > 0) {
                        echo '<b class="nav-lencana">' . $lencanaSb . '</b>';
                    }
                    echo '</a>';
                }
                echo '</div></details>';
                continue;
            }

            $href = $item[0];
            $label = $item[1];
            $ic = $item[2];
            $lencana = (int)($item[3] ?? 0);
            $on = $halaman === $href ? ' on' : '';
            echo '<a class="nav-item' . $on . '" href="' . e($href) . '">' . ikon($ic)
                . '<span>' . e($label) . '</span>';
            if ($lencana > 0) {
                echo '<b class="nav-lencana">' . $lencana . '</b>';
            }
            echo '</a>';
        }
        echo '</nav><div class="side-foot"><span class="mut kecil">Versi ' . e(APP_VERSION) . '</span></div></aside>';

        // ---------------- isi ----------------
        echo '<div class="main">';
        echo '<header class="top">'
            . '<button class="btn-menu" type="button" data-menu aria-label="Buka menu">' . ikon('menu', 20) . '</button>'
            . '<div class="top-judul"><h1>' . e($judul) . '</h1>';
        if ($sub !== '') {
            echo '<p class="mut">' . $sub . '</p>';
        }
        echo '</div><div class="top-kanan">';
        echo '<span class="top-user"><b>' . e($u['nama']) . '</b><i>' . e(role_label()) . '</i></span>';
        echo '<a class="btn btn-ghost btn-kecil" href="logout.php">' . ikon('keluar', 16) . ' Keluar</a>';
        echo '</div></header>';

        // spanduk konteks (Super Admin / Pengawas yang sedang membuka satu madrasah)
        if ($s && $u['role'] !== 'admin') {
            echo '<div class="spanduk">' . ikon('madrasah', 18)
                . '<span>Sedang membuka: <b>' . e($s['nama']) . '</b>'
                . ($u['role'] === 'pengawas' ? ' &middot; posisi Anda: <b>' . e(penilai_posisi_teks((string)pengawas_posisi_di((int)$u['id'], (int)$s['id']))) . '</b>' : '')
                . '</span>'
                . '<a class="btn btn-ghost btn-kecil" href="index.php?keluar=1">Ganti madrasah</a></div>';
        }
        echo '<main class="isi' . ($lebar ? ' isi-lebar' : '') . '">';

        // pesan
        foreach (flash_ambil() as $f) {
            echo '<div class="alert alert-' . e($f['tipe'] === 'err' ? 'err' : 'ok') . '">' . e($f['pesan']) . '</div>';
        }
    }
}

if (!function_exists('page_bottom')) {
    function page_bottom(): void
    {
        echo '</main><footer class="foot">' . e(APP_NAME) . ' ' . e(APP_SUB)
            . ' &middot; Versi ' . e(APP_VERSION)
            . ' &middot; Dikembangkan oleh ' . e(APP_AUTHOR) . ' (' . e(APP_AUTHOR_JABATAN) . ')</footer>'
            . '</div></div>';
        echo '<script src="assets/app.js?v=' . e(APP_VERSION) . '"></script>';
        echo '</body></html>';
    }
}
