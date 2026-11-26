<?php
/**
 * e-PKKM - LOG AKTIVITAS (khusus Super Admin) — v1.15.0.
 *
 * Permintaan pemilik: *"Pada akun Super Admin tambahkan fitur log aktifitas seperti aplikasi e-Guru
 * termasuk hapus riwayatnya"* — pola sama dengan `e-guru/log.php` dan `supervisi-guru/log.php`.
 *
 * Dua bagian:
 *   1. SEDANG ONLINE — akun yang masih memakai aplikasi (aktif dalam LOG_MENIT_ONLINE menit terakhir),
 *      sumbernya tabel `sesi_aktif`.
 *   2. RIWAYAT       — masuk / keluar / gagal masuk beserta nama, peran, madrasah, tanggal-jam,
 *                      IP, dan peramban (tabel `log_aktivitas`).
 *
 * Penghapusan riwayat (permintaan pemilik):
 *   - hapus SATU baris (kolom Tindakan),
 *   - "Bersihkan Riwayat Lama": mode `hari` (lebih lama dari N hari) ATAU `semua` (SELURUH riwayat),
 *     lengkap dengan tabel pratinjau berapa baris yang akan terhapus untuk tiap pilihan — supaya
 *     tidak terlihat "tidak berfungsi" ketika menghasilkan 0 baris.
 */
require_once __DIR__ . '/inc/init.php';
$u = require_super();

/** Tanggal valid (YYYY-MM-DD) atau null. */
function log_tgl_ok($v): ?string
{
    $v = trim((string)$v);
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) === 1 ? $v : null;
}

$AKSI = ['masuk' => 'Masuk', 'keluar' => 'Keluar', 'gagal' => 'Gagal masuk'];
$AKSI_KELAS = ['masuk' => 'pil-unggul', 'keluar' => 'pil-kosong', 'gagal' => 'pil-kurang'];

// ---------------------------------------------------------------- aksi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) {
        tolak_csrf();
    }
    $aksi = (string)($_POST['aksi'] ?? '');

    if ($aksi === 'bersihkan') {
        // DUA cara membersihkan (mengikuti e-GURU v1.64.1):
        //   mode=hari  → hanya yang LEBIH LAMA dari N hari,
        //   mode=semua → SELURUH riwayat masuk/keluar/gagal dihapus tanpa kecuali.
        $mode = (string)($_POST['mode'] ?? 'hari') === 'semua' ? 'semua' : 'hari';
        // Tombol cepat mengirim `hari_pilih` (nama TERPISAH dari isian `hari`) supaya nilainya tidak
        // bertabrakan dengan isian angka pada formulir yang sama.
        $hari = max(1, (int)($_POST['hari_pilih'] ?? 0) ?: (int)($_POST['hari'] ?? 90));

        if (strtoupper(trim((string)($_POST['konfirmasi'] ?? ''))) !== 'HAPUS') {
            flash('Ketik HAPUS untuk konfirmasi. Tidak ada data yang dihapus.', 'err');
            redirect('log.php');
        }
        $totalSebelum = (int)(val('SELECT COUNT(*) FROM log_aktivitas') ?? 0);
        if ($totalSebelum === 0) {
            flash('Riwayat masuk & keluar memang masih kosong — belum ada yang perlu dibersihkan.');
            redirect('log.php');
        }
        if ($mode === 'semua') {
            q('DELETE FROM log_aktivitas');
            flash('Seluruh riwayat dibersihkan: ' . $totalSebelum . ' baris dihapus '
                . '(masuk, keluar, dan gagal masuk).', 'err');
            redirect('log.php');
        }
        $batas = date('Y-m-d H:i:s', time() - $hari * 86400);
        $n = q('DELETE FROM log_aktivitas WHERE waktu < ?', [$batas])->rowCount();
        if ($n > 0) {
            flash($n . ' baris riwayat lebih lama dari ' . $hari . ' hari dihapus '
                . '(sebelum ' . log_waktu_teks($batas) . ').');
        } else {
            // JANGAN hanya menulis "0 baris dihapus" — pengguna mengira fiturnya rusak.
            $tertua = (string)(val('SELECT MIN(waktu) FROM log_aktivitas') ?? '');
            $umur = $tertua !== '' ? max(0, (int)floor((time() - (int)strtotime($tertua)) / 86400)) : 0;
            flash('Tidak ada riwayat yang lebih lama dari ' . $hari . ' hari, jadi tidak ada yang perlu '
                . 'dihapus. Riwayat terlama Anda baru berumur ' . $umur . ' hari. '
                . ($umur < $hari
                    ? 'Ketik angka hari yang lebih kecil dari ' . max(1, $umur) . ', atau pilih '
                        . '"SELURUH riwayat" untuk mengosongkan semuanya.'
                    : 'Pilih "SELURUH riwayat" untuk mengosongkan semuanya.'), 'err');
        }
        redirect('log.php');
    }

    if ($aksi === 'hapus_riwayat') {
        // Permintaan pemilik: hapus SATU baris riwayat tertentu.
        $id = (int)($_POST['id'] ?? 0);
        $r = $id > 0 ? one('SELECT * FROM log_aktivitas WHERE id = ?', [$id]) : null;
        if (!$r) {
            flash('Baris riwayat tidak ditemukan (mungkin sudah dihapus).', 'err');
        } else {
            q('DELETE FROM log_aktivitas WHERE id = ?', [$id]);
            $ket = trim((string)($r['nama'] ?? '')) !== '' ? (string)$r['nama'] : (string)($r['username'] ?? '');
            flash('Riwayat ' . ($ket !== '' ? $ket . ' — ' : '') . log_waktu_teks((string)$r['waktu'])
                . ' (' . ($AKSI[(string)$r['aktivitas']] ?? (string)$r['aktivitas']) . ') dihapus.');
        }
        // Kembali ke halaman & filter yang sama supaya konteks tidak hilang.
        $kembali = trim((string)($_POST['kembali'] ?? ''));
        redirect($kembali !== '' && strncmp($kembali, 'log.php', 7) === 0 ? $kembali : 'log.php');
    }

    if ($aksi === 'bersihkan_online') {
        $n = log_sesi_bersihkan(1);
        flash($n > 0
            ? $n . ' catatan sesi lama dibuang dari daftar (riwayat masuk/keluar tidak terpengaruh).'
            : 'Tidak ada catatan sesi lama yang perlu dibuang.');
        redirect('log.php');
    }

    flash('Aksi tidak dikenal.', 'err');
    redirect('log.php');
}

// ---------------------------------------------------------------- filter riwayat
$fCari = trim((string)($_GET['cari'] ?? ''));
$fAksi = (string)($_GET['aktivitas'] ?? '');
$fDari = log_tgl_ok($_GET['dari'] ?? null);
$fSampai = log_tgl_ok($_GET['sampai'] ?? null);
if ($fDari !== null && $fSampai !== null && $fDari > $fSampai) {
    [$fDari, $fSampai] = [$fSampai, $fDari];
}
if (!isset($AKSI[$fAksi])) {
    $fAksi = '';
}

$w = [];
$pArgs = [];
if ($fCari !== '') {
    $w[] = '(nama LIKE ? OR username LIKE ? OR ip LIKE ? OR peramban LIKE ? OR keterangan LIKE ? OR sekolah LIKE ?)';
    $like = '%' . $fCari . '%';
    array_push($pArgs, $like, $like, $like, $like, $like, $like);
}
if ($fAksi !== '') {
    $w[] = 'aktivitas = ?';
    $pArgs[] = $fAksi;
}
if ($fDari !== null) {
    $w[] = 'waktu >= ?';
    $pArgs[] = $fDari . ' 00:00:00';
}
if ($fSampai !== null) {
    $w[] = 'waktu <= ?';
    $pArgs[] = $fSampai . ' 23:59:59';
}
$where = $w ? ' WHERE ' . implode(' AND ', $w) : '';

// Paginasi riwayat: 10 baris per halaman (aturan yang sama dengan e-GURU & Supervisi Guru),
// sisanya lewat tombol "Selanjutnya" supaya halaman tidak terlalu panjang.
$perHal = 10;
$totalRiwayat = (int)(val('SELECT COUNT(*) FROM log_aktivitas' . $where, $pArgs) ?? 0);
$jmlHal = max(1, (int)ceil($totalRiwayat / $perHal));
$hal = max(1, min($jmlHal, (int)($_GET['hal'] ?? 1)));
$rows = all('SELECT * FROM log_aktivitas' . $where . ' ORDER BY waktu DESC, id DESC LIMIT ' . $perHal
    . ' OFFSET ' . (($hal - 1) * $perHal), $pArgs);

// Alamat halaman + filter yang sedang dilihat — dikirim balik setelah hapus satu baris.
$qFilter = array_filter(['cari' => $fCari, 'aktivitas' => $fAksi, 'dari' => $fDari, 'sampai' => $fSampai],
    static fn($v) => $v !== '' && $v !== null);
$tautanSendiri = 'log.php' . ($qFilter || $hal > 1
    ? '?' . http_build_query(array_merge($qFilter, $hal > 1 ? ['hal' => $hal] : []))
    : '');

// ---------------------------------------------------------------- sedang online
log_sesi_bersihkan(2);   // catatan sesi lama dibuang (riwayat masuk/keluar tidak disentuh)
$online = log_online();
$onlineAkun = count(array_unique(array_map(static fn(array $r): int => (int)$r['user_id'], $online)));

// ---------------------------------------------------------------- ringkasan
$hariIni = date('Y-m-d');
$masukHariIni = (int)(val('SELECT COUNT(*) FROM log_aktivitas WHERE aktivitas = ? AND waktu >= ?',
    ['masuk', $hariIni . ' 00:00:00']) ?? 0);
$gagalHariIni = (int)(val('SELECT COUNT(*) FROM log_aktivitas WHERE aktivitas = ? AND waktu >= ?',
    ['gagal', $hariIni . ' 00:00:00']) ?? 0);
$keluarHariIni = (int)(val('SELECT COUNT(*) FROM log_aktivitas WHERE aktivitas = ? AND waktu >= ?',
    ['keluar', $hariIni . ' 00:00:00']) ?? 0);
$terakhir = one('SELECT * FROM log_aktivitas ORDER BY waktu DESC, id DESC LIMIT 1');
$perambanUmum = all('SELECT peramban, COUNT(*) AS jml FROM log_aktivitas
                      WHERE peramban IS NOT NULL GROUP BY peramban ORDER BY jml DESC LIMIT 5');

// ------------------------------------------------- ringkasan pembersihan (pratinjau)
$totalAda = (int)(val('SELECT COUNT(*) FROM log_aktivitas') ?? 0);
$jmlMasuk = (int)(val('SELECT COUNT(*) FROM log_aktivitas WHERE aktivitas = ?', ['masuk']) ?? 0);
$jmlKeluar = (int)(val('SELECT COUNT(*) FROM log_aktivitas WHERE aktivitas = ?', ['keluar']) ?? 0);
$jmlGagal = (int)(val('SELECT COUNT(*) FROM log_aktivitas WHERE aktivitas = ?', ['gagal']) ?? 0);
$tertuaWaktu = (string)(val('SELECT MIN(waktu) FROM log_aktivitas') ?? '');
$umurTertua = $tertuaWaktu !== '' ? max(0, (int)floor((time() - (int)strtotime($tertuaWaktu)) / 86400)) : 0;
// Angka hari bawaan: pilihan terkecil yang BENAR-BENAR menghapus sesuatu (dulu selalu 90 → 0 baris).
$hariBawaan = 1;
foreach ([7, 30, 90, 365] as $cHari) {
    if ($umurTertua >= $cHari) {
        $hariBawaan = $cHari;
        break;
    }
}
$pilihanBersih = [];
foreach ([1 => 'Lebih lama dari 1 hari', 7 => 'Lebih lama dari 7 hari',
          30 => 'Lebih lama dari 30 hari', 90 => 'Lebih lama dari 90 hari',
          365 => 'Lebih lama dari 365 hari'] as $cHari => $labelnya) {
    $n = (int)(val('SELECT COUNT(*) FROM log_aktivitas WHERE waktu < ?',
        [date('Y-m-d H:i:s', time() - $cHari * 86400)]) ?? 0);
    $pilihanBersih[] = ['label' => $labelnya, 'hapus' => $n, 'sisa' => max(0, $totalAda - $n)];
}
$pilihanBersih[] = ['label' => 'SELURUH riwayat (masuk, keluar, gagal)', 'hapus' => $totalAda, 'sisa' => 0];

page_top('Log Aktivitas', 'Super Admin &middot; pemantauan akun &amp; aktivitas masuk');
?>
<div class="card" id="cardInfoLog">
    <div class="card-kepala"><h2><?= ikon('clock', 18) ?> Log Aktivitas &amp; Pengguna Online</h2>
        <div class="aksi">
            <span class="pil pil-unggul"><?= count($online) ?> sesi online</span>
            <span class="pil pil-kosong"><?= $totalAda ?> baris riwayat</span>
        </div>
    </div>
    <p class="ket mb0">Pantau siapa saja yang sedang memakai aplikasi (online) dan riwayat
        masuk/keluar setiap akun beserta tanggal, jam, IP, dan peramban yang dipakai. Sesi dianggap
        online bila aktif dalam <b><?= (int)LOG_MENIT_ONLINE ?> menit</b> terakhir. Riwayat dapat
        dihapus satu per satu atau dibersihkan sekaligus pada kartu
        <b>Bersihkan Riwayat Lama</b> di bawah.</p>
</div>

<div class="grid g4" id="ringkasLog">
    <div class="card stat"><?= ikon('user', 20) ?><div>
        <div class="stat-angka"><?= count($online) ?></div>
        <div class="stat-label">Sedang online<?= $onlineAkun !== count($online) ? ' (' . $onlineAkun . ' akun)' : '' ?></div>
    </div></div>
    <div class="card stat"><?= ikon('kunci', 20) ?><div>
        <div class="stat-angka"><?= $masukHariIni ?></div>
        <div class="stat-label">Masuk hari ini<?= $keluarHariIni > 0 ? ' &middot; ' . $keluarHariIni . ' keluar' : '' ?></div>
    </div></div>
    <div class="card stat"><?= ikon('pengawas', 20) ?><div>
        <div class="stat-angka"><?= $gagalHariIni ?></div>
        <div class="stat-label">Gagal masuk hari ini</div>
    </div></div>
    <div class="card stat"><?= ikon('daftar', 20) ?><div>
        <div class="stat-angka"><?= $totalRiwayat ?></div>
        <div class="stat-label">Riwayat<?= $qFilter ? ' (sesuai saringan)' : ' tercatat' ?></div>
    </div></div>
</div>

<div class="card" id="cardOnline">
    <div class="card-kepala"><h2>Sedang Online Sekarang</h2>
        <div class="aksi">
            <span class="kecil mut">aktif dalam <?= (int)LOG_MENIT_ONLINE ?> menit terakhir</span>
            <form method="post" class="inline"
                  data-confirm="Buang catatan sesi yang sudah lama tidak aktif? Riwayat masuk/keluar tidak dihapus.">
                <?= csrf_field() ?>
                <input type="hidden" name="aksi" value="bersihkan_online">
                <button class="btn btn-kecil btn-ghost" type="submit">Buang sesi lama</button>
            </form>
        </div>
    </div>
    <?php if (!$online): ?>
        <p class="mut mb0">Belum ada pengguna online dalam <?= (int)LOG_MENIT_ONLINE ?> menit terakhir.</p>
    <?php else: ?>
    <div class="geser"><table class="tbl" id="tblOnline">
        <thead><tr><th>Nama</th><th>Peran</th><th>Asal</th><th>Masuk</th>
            <th>Terakhir aktif</th><th>IP</th><th>Peramban</th></tr></thead>
        <tbody>
        <?php foreach ($online as $o):
            $nama = trim((string)$o['nama']) !== '' ? (string)$o['nama'] : (string)$o['username'];
            $aktifMenit = max(0, (int)floor((time() - (int)strtotime((string)$o['terakhir'])) / 60)); ?>
            <tr>
                <td><b><?= e($nama) ?></b><div class="kecil mut"><?= e((string)$o['username']) ?></div></td>
                <td class="kecil"><?= e(log_role_label((string)$o['role'], (string)($o['jabatan'] ?? ''))) ?></td>
                <td class="kecil"><?= e(log_sekolah_label((string)$o['role'], (string)($o['sekolah_nama'] ?? ''))) ?></td>
                <td class="kecil nowrap"><?= e(log_waktu_teks((string)$o['mulai'])) ?></td>
                <td class="kecil nowrap"><span class="pil pil-unggul"><?= $aktifMenit < 1
                    ? 'baru saja' : $aktifMenit . ' menit lalu' ?></span></td>
                <td class="kecil nowrap"><?= e((string)($o['ip'] ?: '-')) ?></td>
                <td class="kecil"><?= e((string)($o['peramban'] ?: '-')) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</div>

<div class="card" id="cardRiwayat">
    <div class="card-kepala"><h2>Riwayat Masuk &amp; Keluar</h2>
        <span class="kecil mut"><?= $perHal ?> baris per halaman</span>
    </div>
    <p class="ket">Seluruh riwayat, termasuk percobaan masuk yang gagal (sandi salah, akun atau
        madrasah nonaktif). Nama akun dan madrasah disimpan apa adanya pada saat kejadian, sehingga
        riwayat tetap terbaca walau akunnya kemudian dihapus.</p>

    <form class="saring" method="get" action="log.php">
        <div class="saring-item">
            <label for="cari">Cari</label>
            <input type="search" id="cari" name="cari" value="<?= e($fCari) ?>"
                   placeholder="nama / pengguna / IP / madrasah">
        </div>
        <div class="saring-item">
            <label for="aktivitas">Aktivitas</label>
            <select id="aktivitas" name="aktivitas">
                <option value="">Semua</option>
                <?php foreach ($AKSI as $k => $lbl): ?>
                    <option value="<?= e($k) ?>" <?= $fAksi === $k ? 'selected' : '' ?>><?= e($lbl) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="saring-item">
            <label for="dari">Dari tanggal</label>
            <input type="date" id="dari" name="dari" value="<?= e((string)$fDari) ?>">
        </div>
        <div class="saring-item">
            <label for="sampai">Sampai tanggal</label>
            <input type="date" id="sampai" name="sampai" value="<?= e((string)$fSampai) ?>">
        </div>
        <div class="saring-aksi">
            <button class="btn btn-kecil" type="submit">Terapkan</button>
            <?php if ($qFilter): ?><a class="btn btn-kecil btn-ghost" href="log.php">Reset</a><?php endif; ?>
        </div>
    </form>

    <?php if (!$rows): ?>
        <p class="mut mb0"><?= $qFilter
            ? 'Tidak ada riwayat yang cocok dengan saringan. Coba ubah kata kunci, jenis aktivitas, atau rentang tanggalnya.'
            : 'Belum ada riwayat aktivitas. Riwayat terisi otomatis setiap kali ada akun masuk atau keluar.' ?></p>
    <?php else: ?>
    <div class="geser"><table class="tbl" id="tblRiwayat">
        <thead><tr><th>Tanggal &amp; Jam</th><th>Nama</th><th>Peran</th><th>Asal</th>
            <th class="tengah">Aktivitas</th><th>IP</th><th>Peramban</th>
            <th class="tengah">Tindakan</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r):
            $nama = trim((string)($r['nama'] ?? ''));
            if ($nama === '') {
                $nama = (string)($r['username'] ?? '-');
            }
            $akt = (string)$r['aktivitas'];
            $ket = trim((string)($r['keterangan'] ?? '')); ?>
            <tr>
                <td class="nowrap"><?= e(log_waktu_teks((string)$r['waktu'])) ?></td>
                <td><b><?= e($nama) ?></b>
                    <?php if (trim((string)($r['username'] ?? '')) !== ''): ?>
                        <div class="kecil mut"><?= e((string)$r['username']) ?></div>
                    <?php endif; ?></td>
                <td class="kecil"><?= e(log_role_label($r['role'] ?? '', '')) ?></td>
                <td class="kecil"><?= e(log_sekolah_label($r['role'] ?? '', $r['sekolah'] ?? '')) ?></td>
                <td class="tengah">
                    <span class="pil <?= e($AKSI_KELAS[$akt] ?? 'pil-kosong') ?>"><?= e($AKSI[$akt] ?? $akt) ?></span>
                    <?php if ($ket !== ''): ?><div class="kecil mut"><?= e($ket) ?></div><?php endif; ?>
                </td>
                <td class="kecil nowrap"><?= e((string)($r['ip'] ?: '-')) ?></td>
                <td class="kecil"><?= e((string)($r['peramban'] ?: '-')) ?></td>
                <td class="tengah">
                    <?php // Permintaan pemilik: hapus SATU baris riwayat. ?>
                    <form method="post" action="log.php" class="inline"
                          data-confirm="Hapus riwayat ini?&#10;<?= e($nama) ?> — <?= e(log_waktu_teks((string)$r['waktu'])) ?>&#10;Penghapusan tidak dapat dibatalkan.">
                        <?= csrf_field() ?>
                        <input type="hidden" name="aksi" value="hapus_riwayat">
                        <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                        <input type="hidden" name="kembali" value="<?= e($tautanSendiri) ?>">
                        <button class="btn btn-kecil btn-ghost" type="submit" title="Hapus riwayat ini">Hapus</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>

    <?php if ($jmlHal > 1): ?>
        <div class="baris-paginasi" id="paginasiLog">
            <?php $barisAwal = (($hal - 1) * $perHal) + 1; $barisAkhir = min($totalRiwayat, $hal * $perHal); ?>
            <span class="kecil mut">Menampilkan <?= $barisAwal ?>&ndash;<?= $barisAkhir ?> dari
                <?= $totalRiwayat ?> baris (<?= (int)$perHal ?> per halaman)</span>
            <div class="aksi">
                <?php $tautanHal = static fn(int $n): string =>
                    'log.php?' . http_build_query(array_merge($qFilter, ['hal' => $n])); ?>
                <?php if ($hal > 1): ?>
                    <a class="btn btn-kecil btn-ghost" href="<?= e($tautanHal(1)) ?>">Awal</a>
                    <a class="btn btn-kecil btn-ghost" href="<?= e($tautanHal($hal - 1)) ?>">&larr; Sebelumnya</a>
                <?php endif; ?>
                <span class="pil pil-kosong">Halaman <?= $hal ?> / <?= $jmlHal ?></span>
                <?php if ($hal < $jmlHal): ?>
                    <a class="btn btn-kecil btn-ghost" id="btnSelanjutnya" href="<?= e($tautanHal($hal + 1)) ?>">Selanjutnya &rarr;</a>
                    <a class="btn btn-kecil btn-ghost" href="<?= e($tautanHal($jmlHal)) ?>">Akhir</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<div class="grid g2">
    <div class="card" id="cardPeramban">
        <div class="card-kepala"><h2>Peramban yang Paling Sering Dipakai</h2></div>
        <?php if ($perambanUmum): ?>
            <table class="tbl tbl-padat"><tbody>
            <?php foreach ($perambanUmum as $pb): ?>
                <tr><td><?= e((string)$pb['peramban']) ?></td>
                    <td class="tengah nowrap"><span class="pil pil-kosong"><?= (int)$pb['jml'] ?> kali</span></td></tr>
            <?php endforeach; ?>
            </tbody></table>
        <?php else: ?>
            <p class="mut mb0">Belum ada data peramban.</p>
        <?php endif; ?>
        <p class="ket mb0 mt8">IP diambil dari permintaan yang diterima aplikasi. Bila pengguna berada di
            balik jaringan/proxy, IP yang tampil bisa berupa IP jaringan tersebut.</p>
    </div>

    <div class="card" id="cardBersih">
        <div class="card-kepala"><h2>Bersihkan Riwayat Lama</h2>
            <span class="pil pil-kurang">tindakan permanen</span>
        </div>
        <p class="ket">Riwayat terakhir tercatat:
            <?php if ($terakhir): ?>
                <b><?= e(log_waktu_teks((string)$terakhir['waktu'])) ?></b> —
                <?= e((string)($terakhir['nama'] ?? '')) ?>
                (<?= e($AKSI[(string)($terakhir['aktivitas'] ?? '')] ?? (string)($terakhir['aktivitas'] ?? '')) ?>).
            <?php else: ?>
                belum ada.
            <?php endif; ?>
        </p>
        <?php if ($totalAda === 0): ?>
            <div class="alert alert-info">Riwayat masuk &amp; keluar masih kosong — belum ada yang perlu
                dibersihkan.</div>
        <?php else: ?>
            <table class="tbl tbl-padat" id="tblBersih">
                <thead><tr><th>Kalau dibersihkan dengan pilihan …</th>
                    <th class="tengah">Baris yang terhapus</th><th class="tengah">Sisa</th></tr></thead>
                <tbody>
                <?php foreach ($pilihanBersih as $pb): ?>
                    <tr><td><?= e($pb['label']) ?></td>
                        <td class="tengah"><b><?= (int)$pb['hapus'] ?></b></td>
                        <td class="tengah"><?= (int)$pb['sisa'] ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <div class="baris-info mt8" id="ringkasBersih">
                <div><span>Tersimpan:</span> <b><?= $totalAda ?> baris</b></div>
                <div><span>Riwayat terlama:</span> <b><?= $umurTertua ?> hari</b>
                    <?= $tertuaWaktu !== '' ? '(' . e(tanggal_id(substr($tertuaWaktu, 0, 10))) . ')' : '' ?></div>
                <div><span>Masuk / keluar / gagal:</span>
                    <b><?= $jmlMasuk ?> / <?= $jmlKeluar ?> / <?= $jmlGagal ?></b></div>
            </div>
            <form method="post" action="log.php" class="mt8"
                  data-confirm="Bersihkan riwayat sesuai pilihan yang aktif? Tindakan ini permanen.">
                <?= csrf_field() ?>
                <input type="hidden" name="aksi" value="bersihkan">
                <div class="form-2">
                    <div>
                        <label for="mode">Yang dibersihkan</label>
                        <select id="mode" name="mode">
                            <option value="hari">Hanya yang lebih lama dari (hari)</option>
                            <option value="semua">SELURUH riwayat (masuk, keluar, gagal)</option>
                        </select>
                    </div>
                    <div>
                        <label for="hari">Lebih lama dari (hari)</label>
                        <input type="number" id="hari" name="hari" value="<?= (int)$hariBawaan ?>"
                               min="1" max="3650">
                    </div>
                </div>
                <p class="ket mb0">Isi angka hari di atas, atau pakai tombol cepat berikut (angka pada
                    tombol = batas hari):</p>
                <div class="aksi-cepat">
                    <?php foreach ([1, 7, 30, 90, 365] as $cHari): ?>
                        <button class="btn btn-kecil btn-ghost" type="submit" name="hari_pilih"
                                value="<?= $cHari ?>">&gt; <?= $cHari ?> hari</button>
                    <?php endforeach; ?>
                </div>
                <div class="mt8">
                    <label for="konfirmasi">Ketik HAPUS untuk konfirmasi</label>
                    <input type="text" id="konfirmasi" name="konfirmasi" placeholder="HAPUS"
                           style="max-width:220px" autocomplete="off">
                </div>
                <div class="form-aksi">
                    <button class="btn btn-err" type="submit">Bersihkan Riwayat</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <h3>Catatan</h3>
    <p class="ket mb0">Daftar <b>online</b> dihitung dari aktivitas terakhir tiap akun
        (<?= (int)LOG_MENIT_ONLINE ?> menit), jadi akun yang menutup peramban tanpa menekan
        <i>Keluar</i> akan hilang sendiri dari daftar. Riwayat <b>keluar</b> hanya tercatat bila
        pengguna benar-benar menekan tombol Keluar; masa berlaku sesi yang habis tidak dihitung
        sebagai keluar. Riwayat tidak pernah menampilkan kata sandi dan tidak menyimpan id sesi.
        Percobaan masuk yang gagal dibatasi <b>2000 baris terbaru</b> agar tabelnya tidak membengkak
        (halaman masuk dapat diakses siapa saja). Akun/madrasah yang dinonaktifkan tidak lagi
        dihitung online.</p>
</div>
<?php
page_bottom();
