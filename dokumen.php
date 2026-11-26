<?php
/**
 * e-PKKM - UNGGAH DOKUMEN BUKTI BUTIR PENILAIAN (akun kepala madrasah).
 *
 * Permintaan pemilik:
 *  - menu unggah dokumen pada akun kepala madrasah dengan PILIHAN TAHUN (Tahun 1, 2, 3, 4);
 *  - dokumen diunggah untuk SEMUA butir penilaian, tampilannya memuat Indikator Kinerja,
 *    Data Yang Diharapkan, dan kolom unggah di sampingnya;
 *  - dokumen itu masuk ke tim penilai (Pengawas I & II) untuk dilihat, diberi komentar,
 *    dan diberi skor nilai (komentar & skor diisi pada menu Penilaian Kepala Madrasah).
 *
 * Yang boleh mengunggah/menghapus: kepala madrasah (role admin) dan Super Admin dalam konteks.
 * Pengawas hanya melihat (dokumen & komentar) - penilaiannya lewat lembar penilaian.
 */
require_once __DIR__ . '/inc/init.php';
$u = require_login();
$s = require_sekolah();
$sid = (int)$s['id'];
$bolehUnggah = in_array($u['role'], ['admin', 'super'], true);

$tahun = (int)($_REQUEST['tahun'] ?? 1);
if (!array_key_exists($tahun, PKKM_TAHUN_LIST)) {
    $tahun = 1;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Hak akses diperiksa SEBELUM CSRF: pengawas memang tidak pernah melihat formulirnya,
    // jadi jawabannya harus 403 (bukan 419).
    if (!$bolehUnggah) {
        http_response_code(403);
        page_ditolak('Hanya kepala madrasah (atau Super Admin dalam konteks) yang dapat mengunggah dokumen.');
    }
    if (!csrf_ok()) {
        tolak_csrf();
    }
    $aksi = (string)($_POST['aksi'] ?? '');

    if ($aksi === 'unggah') {
        $butir = trim((string)($_POST['butir'] ?? ''));
        $daftar = butir_tahun($tahun);
        if (!isset($daftar[$butir])) {
            flash('Butir penilaian tidak dikenal pada tahun tersebut.', 'err');
            redirect('dokumen.php?tahun=' . $tahun);
        }
        // PHP MEMBUANG seluruh $_POST/$_FILES bila total kiriman melebihi post_max_size -
        // tanpa pemeriksaan ini pengguna melihat "tidak terjadi apa-apa".
        if (!$_POST && !$_FILES && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
            flash('Ukuran kiriman melebihi batas server (' . ini_get('post_max_size')
                . '). Perkecil berkasnya lalu ulangi.', 'err');
            redirect('dokumen.php?tahun=' . $tahun);
        }
        $hasil = dokumen_unggah_berkas($sid, $tahun, $butir, 'berkas');
        if ($hasil['ok'] > 0) {
            flash($hasil['ok'] . ' dokumen tersimpan untuk butir ' . $butir . ' ('
                . pilihan_tahun($tahun) . '). Dokumen ini dapat dilihat dan dikomentari Pengawas I dan Pengawas II.');
        }
        foreach ($hasil['gagal'] as $pesan) {
            flash($pesan, 'err');
        }
        if ($hasil['ok'] === 0 && !$hasil['gagal']) {
            flash('Tidak ada berkas yang diunggah.', 'err');
        }
        redirect('dokumen.php?tahun=' . $tahun . '#butir-' . str_replace('.', '-', $butir));
    }

    if ($aksi === 'tautan') {
        // v1.19.0 - TAUTAN GOOGLE DRIVE (permintaan pemilik): dokumen yang sudah ada di Drive
        // tidak perlu diunggah ulang, cukup ditempel tautannya.
        $butir = trim((string)($_POST['butir'] ?? ''));
        $daftar = butir_tahun($tahun);
        if (!isset($daftar[$butir])) {
            flash('Butir penilaian tidak dikenal pada tahun tersebut.', 'err');
            redirect('dokumen.php?tahun=' . $tahun);
        }
        $simpan = dokumen_simpan_tautan($sid, $tahun, $butir, (string)($_POST['tautan'] ?? ''),
            (string)($_POST['judul_tautan'] ?? ''), (string)$u['nama'], (int)$u['id']);
        flash($simpan['ok']
            ? 'Tautan Google Drive tersimpan untuk butir ' . $butir . ' (' . pilihan_tahun($tahun)
                . '). Penilai Pengawas I dan Pengawas II dapat membukanya dari lembar penilaian.'
            : (string)$simpan['pesan'], $simpan['ok'] ? 'ok' : 'err');
        redirect('dokumen.php?tahun=' . $tahun . '#butir-' . str_replace('.', '-', $butir));
    }

    if ($aksi === 'hapus') {
        $ok = dokumen_hapus((int)($_POST['id'] ?? 0), $sid);
        flash($ok ? 'Dokumen dihapus.' : 'Dokumen tidak ditemukan (atau bukan milik madrasah ini).', $ok ? 'ok' : 'err');
        redirect('dokumen.php?tahun=' . $tahun);
    }

    flash('Aksi tidak dikenal.', 'err');
    redirect('dokumen.php?tahun=' . $tahun);
}

$dokumen = dokumen_list($sid, $tahun);
$komentar = komentar_butir_map($sid, $tahun);
$jumlahTahun = dokumen_jumlah_per_tahun($sid);
$perTahun = PKKM_TAHUN_LIST[$tahun];
$totalButir = count(butir_tahun($tahun));
$butirTerisi = dokumen_butir_terisi($sid, $tahun);
$persen = $totalButir > 0 ? (int)round($butirTerisi / $totalButir * 100) : 0;
$batasDokumen = dok_batas_server('pdf');

page_top('Unggah Dokumen Butir Penilaian',
    e($s['nama']) . ' &middot; dokumen bukti untuk setiap butir instrumen PKKM, dipilih per tahun penilaian.');
?>
<div class="card">
    <div class="baris-info">
        <div><span>Madrasah:</span> <b><?= e($s['nama']) ?></b></div>
        <div><span>Kepala Madrasah:</span> <b><?= e($s['kepala_nama'] ?: '-') ?></b></div>
        <div><span>Total dokumen:</span> <b><?= dokumen_jumlah($sid) ?> dokumen</b></div>
        <div><span>Butir terisi (<?= e($perTahun) ?>):</span> <b><?= $butirTerisi ?> dari <?= $totalButir ?> butir</b></div>
    </div>
    <p class="ket mt8 mb0">Unggah dokumen bukti (PDF, DOCX, XLSX, PPTX, CSV, TXT, ZIP, atau gambar, <b>maksimal
        <?= e(dok_ukuran_teks($batasDokumen)) ?> per berkas</b>) untuk setiap butir penilaian &mdash; atau, bila
        dokumennya <b>sudah ada di Google Drive</b>, cukup tempel <b>tautan Google Drive</b>-nya pada kolom
        <b>Tautan Google Drive</b> di bawah. Dokumen yang tersimpan (berkas maupun tautan) otomatis masuk ke tim
        penilai &mdash; <b>Pengawas I</b> dan <b>Pengawas II</b> dapat melihat/membukanya, memberi
        <b>komentar</b>, dan memberi <b>skor nilai</b> pada menu <b>Penilaian Kepala Madrasah</b>. Pilih tahun
        penilaian di bawah: dokumen tiap tahun disimpan terpisah.</p>
</div>

<div class="card">
    <div class="card-kepala"><h2>Pilih Tahun Penilaian</h2>
        <div class="aksi">
            <span class="kecil mut">Maks <?= (int)DOK_MAKS_PER_BUTIR ?> dokumen per butir (berkas + tautan) &middot;
                maks <?= e(dok_ukuran_teks($batasDokumen)) ?> per berkas</span>
        </div>
    </div>
    <div class="chip-tahun">
        <?php foreach (PKKM_TAHUN_LIST as $no => $label): ?>
            <a class="chip-tahun-item<?= $no === $tahun ? ' on' : '' ?>" href="dokumen.php?tahun=<?= (int)$no ?>">
                <b><?= e($label) ?></b>
                <span><?= (int)($jumlahTahun[$no] ?? 0) ?> dokumen</span>
            </a>
        <?php endforeach; ?>
    </div>
    <div class="progres-mini">
        <span class="progres-mini-label">Progres dokumen <?= e($perTahun) ?>:
            <b><?= $butirTerisi ?></b>/<?= $totalButir ?> butir (<?= $persen ?>%)</span>
        <span class="progres-mini-track"><i style="width:<?= $persen ?>%"></i></span>
    </div>
</div>

<?php if (!$bolehUnggah): ?>
    <div class="alert alert-info">Anda membuka halaman ini dalam mode <b>hanya lihat</b> &mdash; pengunggahan dokumen
        dilakukan oleh kepala madrasah. Dokumen di bawah ini dapat Anda lihat beserta komentar penilainya.</div>
<?php endif; ?>

<?php foreach (bagian_tahun($tahun) as $bk => $bagian): ?>
    <div class="card">
        <div class="card-kepala"><h2><?= e($bk) ?>. <?= e($bagian['judul']) ?></h2>
            <div class="aksi"><span class="kecil mut"><?= count($bagian['butir']) ?> butir &middot; skor maks
                <?= (int)$bagian['maks'] ?></span></div>
        </div>
        <div class="geser"><table class="tbl tbl-padat tbl-dokumen">
            <colgroup><col class="dok-no"><col class="dok-kode"><col><col><col class="dok-kolom"></colgroup>
            <thead><tr><th class="dok-no">No</th><th class="dok-kode">No. UBT</th>
                <th>Indikator Kinerja</th><th>Data Yang Diharapkan</th>
                <th class="dok-kolom">Dokumen (unggah di samping)</th></tr></thead>
            <tbody>
            <?php $no = 0; foreach ($bagian['butir'] as $kodeButir):
                $b = instrumen_butir()[$kodeButir]; $no++;
                $berkas = $dokumen[$kodeButir] ?? [];
                $k1 = komentar_butir_teks($komentar, $kodeButir, '1');
                $k2 = komentar_butir_teks($komentar, $kodeButir, '2'); ?>
                <tr id="butir-<?= e(str_replace('.', '-', $kodeButir)) ?>">
                    <td class="tengah"><?= $no ?></td>
                    <td class="tengah kecil nowrap"><?= e($kodeButir) ?></td>
                    <td><?= e($b['indikator']) ?></td>
                    <td class="kecil"><?= e($b['data']) ?></td>
                    <td class="dok-sel">
                        <?php if ($berkas): ?>
                            <ul class="dok-daftar">
                                <?php foreach ($berkas as $d): $tautanD = dokumen_adalah_tautan($d); ?>
                                    <li>
                                        <span class="dok-pil<?= $tautanD ? ' dok-pil-drive' : '' ?>"><?= $tautanD
                                            ? 'DRIVE' : e(dok_label_berkas((string)$d['nama_berkas'])) ?></span>
                                        <a href="<?= e($d['url']) ?>" target="_blank" rel="noopener noreferrer"
                                           title="<?= e($tautanD ? $d['url'] : $d['nama_berkas']) ?>"><?= e(potong_teks((string)$d['nama_berkas'], 34)) ?></a>
                                        <span class="kecil mut"><?= $tautanD
                                            ? e(dok_tautan_ringkas((string)$d['url'], 28))
                                            : e(dok_ukuran_teks((int)$d['ukuran'])) ?></span>
                                        <?php if ($bolehUnggah): ?>
                                            <form method="post" action="dokumen.php?tahun=<?= (int)$tahun ?>" class="dok-hapus">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="aksi" value="hapus">
                                                <input type="hidden" name="tahun" value="<?= (int)$tahun ?>">
                                                <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
                                                <button class="btn btn-kecil btn-ghost" type="submit"
                                                    data-confirm="Hapus dokumen ini?">Hapus</button>
                                            </form>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p class="kecil mut mb0">Belum ada dokumen.</p>
                        <?php endif; ?>

                        <?php if ($k1 !== '' || $k2 !== ''): ?>
                            <div class="dok-komentar">
                                <?php if ($k1 !== ''): ?>
                                    <p class="mb0"><b>Pengawas I:</b> <?= nl2br(e($k1)) ?></p>
                                <?php endif; ?>
                                <?php if ($k2 !== ''): ?>
                                    <p class="mb0"><b>Pengawas II:</b> <?= nl2br(e($k2)) ?></p>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($bolehUnggah && count($berkas) < DOK_MAKS_PER_BUTIR): ?>
                            <form class="dok-unggah" method="post" action="dokumen.php?tahun=<?= (int)$tahun ?>"
                                  enctype="multipart/form-data">
                                <?= csrf_field() ?>
                                <input type="hidden" name="aksi" value="unggah">
                                <input type="hidden" name="tahun" value="<?= (int)$tahun ?>">
                                <input type="hidden" name="butir" value="<?= e($kodeButir) ?>">
                                <input type="file" name="berkas[]" multiple
                                       accept="<?= e('.' . implode(',.', dok_ekstensi_list())) ?>"
                                       aria-label="Berkas dokumen butir <?= e($kodeButir) ?>">
                                <button class="btn btn-kecil" type="submit"><?= ikon('unggah', 14) ?> Unggah</button>
                            </form>
                            <p class="kecil mut mb0"><?= count($berkas) ?>/<?= (int)DOK_MAKS_PER_BUTIR ?> dokumen
                                &middot; maks <?= e(dok_ukuran_teks($batasDokumen)) ?> per berkas</p>
                            <?php // v1.19.0 - TAUTAN GOOGLE DRIVE (permintaan pemilik) ?>
                            <form class="dok-tautan" method="post" action="dokumen.php?tahun=<?= (int)$tahun ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="aksi" value="tautan">
                                <input type="hidden" name="tahun" value="<?= (int)$tahun ?>">
                                <input type="hidden" name="butir" value="<?= e($kodeButir) ?>">
                                <label class="dok-tautan-label" for="tautan-<?= e(str_replace('.', '-', $kodeButir)) ?>">
                                    Tautan Google Drive</label>
                                <input type="url" name="tautan" id="tautan-<?= e(str_replace('.', '-', $kodeButir)) ?>"
                                       inputmode="url" autocomplete="off" maxlength="<?= (int)DOK_TAUTAN_MAKS ?>"
                                       placeholder="https://drive.google.com/…"
                                       aria-label="Tautan Google Drive butir <?= e($kodeButir) ?>">
                                <input type="text" name="judul_tautan" autocomplete="off"
                                       maxlength="<?= (int)DOK_TAUTAN_NAMA_MAKS ?>"
                                       placeholder="Nama dokumen (opsional)"
                                       aria-label="Nama dokumen tautan butir <?= e($kodeButir) ?>">
                                <button class="btn btn-kecil btn-ghost" type="submit">Simpan Tautan</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot><tr><td colspan="4">Jumlah dokumen <?= e($bk) ?>. <?= e($bagian['judul']) ?></td>
                <td class="tengah"><?= (int)array_sum(array_map(
                    fn($k) => count($dokumen[$k] ?? []), $bagian['butir'])) ?> dokumen</td></tr></tfoot>
        </table></div>
    </div>
<?php endforeach; ?>
<?php
page_bottom();
