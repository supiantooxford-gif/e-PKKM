<?php
/**
 * e-PKKM - Penilaian Kepala Madrasah: daftar penilaian Pengawas I & II untuk Tahun Pertama
 * sampai Tahun Keempat pada satu madrasah.
 *
 * Pengawas hanya melihat posisinya sendiri; kepala madrasah melihat hasil (hanya-lihat);
 * Super Admin boleh membantu mengisi.
 */
require_once __DIR__ . '/inc/init.php';
$u = require_login();
$s = require_sekolah();
$sid = (int)$s['id'];
$posisiSaya = $u['role'] === 'pengawas' ? pengawas_posisi_di((int)$u['id'], $sid) : 0;
$tahunBoleh = penilai_tahun_boleh($u);
$batasJabatan = $u['role'] === 'pengawas' && !penilai_jabatan_pengawas($u);

page_top('Penilaian Kepala Madrasah', e($s['nama']) . ' &middot; instrumen Pengawas I dan Pengawas II, Tahun Pertama s.d. Keempat.');
?>
<div class="card">
    <div class="baris-info">
        <div><span>Kepala Madrasah:</span> <b><?= e($s['kepala_nama'] ?: '-') ?></b></div>
        <div><span>NIP:</span> <b><?= e($s['kepala_nip'] ?: '-') ?></b></div>
        <div><span>Penilaian diisi:</span> <b>Pengawas I &amp; Pengawas II</b></div>
        <?php if ($posisiSaya): ?>
            <div><span>Posisi Anda:</span> <b><?= e(penilai_posisi_teks($posisiSaya)) ?></b></div>
        <?php endif; ?>
        <?php if ($u['role'] === 'pengawas'): ?>
            <div><span>Jabatan Anda:</span> <b><?= e(trim((string)($u['jabatan'] ?? '')) ?: 'belum diisi') ?></b></div>
            <div><span>Tahun yang dapat Anda nilai:</span> <b><?= e(penilai_tahun_label($u)) ?></b></div>
        <?php endif; ?>
    </div>
</div>

<?php if ($batasJabatan): ?>
    <div class="alert alert-info">Jabatan Anda <b>bukan Pengawas</b>, sehingga Anda hanya dapat menilai
        <b>Tahun Keempat</b>. Lembar Tahun Pertama s.d. Ketiga hanya dapat diisi penilai berjabatan Pengawas
        (lembar itu tetap dapat Anda lihat).</div>
<?php endif; ?>

<?php
$adaAksi = false;
$rekap = rekap_tahunan($sid);
// Cetak & unduh butir memakai posisi penilai yang sedang masuk (Super Admin: posisi 1)
$posisiCetak = $posisiSaya > 0 ? (string)$posisiSaya : '1';
?>
<div class="card">
    <div class="card-kepala"><h2>Tahun Pertama s.d. Tahun Keempat</h2>
        <div class="aksi">
            <?php if ($u['role'] !== 'admin'): ?>
                <a class="btn btn-kecil btn-ghost" href="laporan.php"><?= ikon('lapor', 15) ?> Laporan</a>
                <a class="btn btn-kecil btn-ghost" href="rekap.php"><?= ikon('rekap', 15) ?> Rekap PKKM</a>
            <?php endif; ?>
        </div>
    </div>
    <div class="geser"><table class="tbl">
        <thead>
        <tr><th rowspan="2">Tahun Penilaian</th><th colspan="2" class="tengah">Status Pengisian</th>
            <th colspan="3" class="tengah">Nilai</th><th rowspan="2" class="tengah">Aksi</th></tr>
        <tr><th class="tengah">Pengawas I</th><th class="tengah">Pengawas II</th>
            <th class="tengah">Pengawas I</th><th class="tengah">Pengawas II</th><th class="tengah">Rerata</th></tr>
        </thead>
        <tbody>
        <?php foreach (PKKM_TAHUN_LIST as $tahun => $label):
            $r = $rekap[$tahun]; ?>
            <tr>
                <td><b><?= e($label) ?></b>
                    <div class="kecil mut"><?= e(($thP = tahun_penilaian_tampil($sid, $s, (int)$tahun)) !== '' ? 'tahun ' . $thP : 'tahun belum diisi') ?>
                        <?= $s['tanggal_' . $tahun] ? ' &middot; ' . e(tanggal_id((string)$s['tanggal_' . $tahun])) : '' ?></div></td>
                <?php foreach (['1', '2'] as $kode):
                    $n = $r['kode'][$kode];
                    $tglP = penilai_tanggal($sid, 'pengawas', $kode, (int)$tahun); ?>
                    <td class="tengah">
                        <?php if ($n && $n['lengkap']): ?>
                            <span class="pil pil-unggul">Lengkap</span>
                        <?php elseif ($n && $n['terisi'] > 0): ?>
                            <span class="pil pil-cukup"><?= (int)$n['terisi'] ?>/<?= (int)$n['jumlah'] ?></span>
                        <?php else: ?>
                            <span class="pil pil-kosong">Belum diisi</span>
                        <?php endif; ?>
                        <div class="kecil mut"><?= $tglP !== ''
                            ? 'dinilai ' . e(tanggal_id($tglP))
                            : 'tanggal menilai belum diisi' ?></div>
                    </td>
                <?php endforeach; ?>
                <td class="tengah"><?= n2($r['kode']['1']['nilai'] ?? null) ?></td>
                <td class="tengah"><?= n2($r['kode']['2']['nilai'] ?? null) ?></td>
                <td class="tengah"><b><?= n2($r['rerata']) ?></b>
                    <div><span class="pil <?= e(predikat_kelas($r['predikat'])) ?>"><?= e($r['predikat']) ?></span></div>
                    <?php if ((int)$tahun === 4 && ($r['tambahan'] ?? [])): ?>
                        <div class="kecil mut">termasuk <?= count($r['tambahan']) ?> penilai lain
                            (<?= e(status_lembaga_label(status_lembaga_sekolah($sid))) ?>)</div>
                    <?php endif; ?></td>
                <td class="nowrap">
                    <?php
                    $tombol = 0;
                    foreach (['1', '2'] as $kode):
                        // boleh_isi_tahun() sekaligus menegakkan aturan jabatan (Tahun 1-3 = pengawas saja)
                        if (!boleh_isi_tahun($sid, $kode, (int)$tahun)) { continue; }
                        $adaAksi = true;
                        $tombol++;
                        $n = $r['kode'][$kode];
                        $teks = ($n && $n['terisi'] > 0) ? 'Ubah P' . $kode : 'Isi P' . $kode;
                        ?>
                        <a class="btn btn-kecil" href="penilaian_form.php?posisi=<?= $kode ?>&tahun=<?= (int)$tahun ?>"><?= e($teks) ?></a>
                    <?php endforeach;
                    // penilai Tahun Keempat (slot jabatan: Guru/Tendik/Komite/Kabid-Yayasan/Kasie)
                    // mengisi lembarnya sendiri (posisi >= 3)
                    if (penilai_posisi_tambahan($posisiSaya) && (int)$tahun === 4
                        && boleh_isi_tahun($sid, (string)$posisiSaya, 4)) {
                        $adaAksi = true;
                        $tombol++;
                        $rT = $r['tambahan'][(string)$posisiSaya] ?? null;
                        ?>
                        <a class="btn btn-kecil btn-emas" href="penilaian_form.php?posisi=<?= (int)$posisiSaya ?>&tahun=4"><?= $rT && $rT['terisi'] > 0 ? 'Ubah Lembar Saya' : 'Isi Lembar Saya' ?></a>
                    <?php }
                    if ($batasJabatan && !in_array((int)$tahun, $tahunBoleh, true) && $posisiSaya > 0): ?>
                        <span class="pil pil-cukup">hanya Tahun Keempat</span>
                    <?php endif; ?>
                    <a class="btn btn-kecil btn-ghost" title="Cetak butir penilaian"
                       href="cetak_penilaian.php?posisi=<?= e($posisiCetak) ?>&tahun=<?= (int)$tahun ?>"><?= ikon('cetak', 14) ?> Butir</a>
                    <a class="btn btn-kecil btn-ghost" title="Unduh butir penilaian (Word)"
                       href="penilaian_docx.php?posisi=<?= e($posisiCetak) ?>&tahun=<?= (int)$tahun ?>"><?= ikon('unduh', 14) ?> Word</a>
                    <?php if ($tombol === 0): ?><span class="mut kecil">hanya lihat</span><?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php if (!$adaAksi): ?>
        <p class="ket mt8 mb0">Penilaian hanya dapat diisi oleh pengawas yang ditugaskan pada posisi tersebut
            (atau Super Admin). Bila Anda pengawas madrasah ini, hubungi Super Admin untuk penugasan posisinya.</p>
    <?php endif; ?>
</div>

<?php // ---------------- CETAK & UNDUH BUTIR PENILAIAN SETIAP TAHUN (permintaan pemilik) ---------------- ?>
<div class="card" id="cardCetakButir">
    <div class="card-kepala"><h2>Cetak &amp; Unduh Butir Penilaian setiap Tahun</h2>
        <div class="aksi">
            <span class="pil pil-unggul"><?= e(penilai_posisi_teks($posisiCetak, status_lembaga_sekolah($sid))) ?></span>
        </div>
    </div>
    <p class="ket">Lembar butir penilaian (indikator + data yang diharapkan + skor 1-4) dapat
        <b>dicetak</b> atau <b>diunduh sebagai berkas Word (.docx)</b> setiap tahun. Berkas Word sudah
        siap cetak (A4), lengkap dengan identitas penilai, tanda tangan, dan tanggal menilai Anda.</p>
    <div class="geser"><table class="tbl tbl-padat" id="tblCetakButir">
        <thead><tr><th style="width:46px" class="tengah">No</th><th>Tahun Penilaian</th>
            <th class="tengah">Tanggal Menilai</th><th class="tengah">Butir Terisi</th>
            <th class="tengah">Cetak Butir</th><th class="tengah">Unduh Word</th>
            <th class="tengah">Cetak Laporan</th></tr></thead>
        <tbody>
        <?php $noC = 0; foreach (PKKM_TAHUN_LIST as $tahun => $label):
            $noC++;
            $rC = $rekap[$tahun]['kode'][$posisiCetak] ?? ($rekap[$tahun]['tambahan'][$posisiCetak] ?? null);
            $tglC = penilai_tanggal($sid, penilai_kategori_posisi($posisiCetak), $posisiCetak, (int)$tahun);
            // penilai tambahan hanya punya lembar Tahun Keempat
            $bolehC = !penilai_posisi_tambahan($posisiCetak) || (int)$tahun === 4; ?>
            <tr>
                <td class="tengah"><?= $noC ?></td>
                <td><b><?= e($label) ?></b>
                    <div class="kecil mut"><?= e(($thC = tahun_penilaian_tampil($sid, $s, (int)$tahun)) !== '' ? 'tahun ' . $thC : 'tahun belum diisi') ?></div></td>
                <td class="tengah"><?= $tglC !== '' ? e(tanggal_id($tglC))
                    : '<span class="mut kecil">belum diisi</span>' ?></td>
                <td class="tengah"><?= $rC ? (int)$rC['terisi'] . ' / ' . (int)$rC['jumlah'] : '<span class="mut">-</span>' ?></td>
                <td class="tengah"><?php if ($bolehC): ?>
                    <a class="btn btn-kecil btn-ghost" href="cetak_penilaian.php?posisi=<?= e($posisiCetak) ?>&tahun=<?= (int)$tahun ?>"><?= ikon('cetak', 14) ?> Cetak</a>
                <?php else: ?><span class="mut kecil">-</span><?php endif; ?></td>
                <td class="tengah"><?php if ($bolehC): ?>
                    <a class="btn btn-kecil btn-emas" href="penilaian_docx.php?posisi=<?= e($posisiCetak) ?>&tahun=<?= (int)$tahun ?>"><?= ikon('unduh', 14) ?> Word</a>
                <?php else: ?><span class="mut kecil">-</span><?php endif; ?></td>
                <td class="tengah"><?php if ($bolehC): ?>
                    <a class="btn btn-kecil btn-ghost" href="cetak_laporan.php?posisi=<?= e($posisiCetak) ?>&tahun=<?= (int)$tahun ?>"><?= ikon('lapor', 14) ?> Laporan</a>
                <?php else: ?><span class="mut kecil">-</span><?php endif; ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <p class="kecil mut mt8 mb0">Bila tanggal menilai sebuah tahun masih kosong, buka lembarnya
        (<b>Isi</b>/<b>Ubah</b> pada tabel di atas) &mdash; tanggal diisi lebih dulu sebelum skor penilaian.</p>
</div>

<?php $slotTahun4 = penilai_sekolah_slots($sid);
      $slotJml = penilai_sekolah_slot_jumlah($sid);
      $statusLembaga = status_lembaga_sekolah($sid); ?>
<div class="card" id="cardPenilaiTambahan">
    <div class="card-kepala"><h2>7 Penilai Tahun Keempat</h2>
        <div class="aksi">
            <span class="pil <?= $statusLembaga === 'negeri' ? 'pil-baik' : 'pil-cukup' ?>">Madrasah
                <?= e(status_lembaga_label($statusLembaga)) ?></span>
            <span class="pil pil-unggul"><?= (int)$slotJml['isi'] ?>/<?= (int)$slotJml['total'] ?> terisi
                &middot; <?= (int)$slotJml['nilai'] ?> bernilai</span>
        </div>
    </div>
    <p class="ket">Nilai Tahun Keempat = rata-rata ketujuh penilai. Susunannya mengikuti <b>Status Lembaga</b>:
        <b>Negeri</b> = Kabid, Kasie, Pengawas 1, Pengawas 2, Guru, Tendik, Komite;
        <b>Swasta</b> = Yayasan, Kasie, Pengawas 1, Pengawas 2, Guru, Tendik, Komite. Penilai jabatan lain
        memilih madrasah ini pada <b>Dashboard Penilai</b> setelah penilaian Tahun Pertama s.d. Ketiga
        lengkap seluruh butirnya.</p>
    <div class="geser"><table class="tbl tbl-padat" id="tblPenilaiTahun4">
        <thead><tr><th class="tengah" style="width:46px">No</th><th>Penilai (Slot)</th><th>Nama Penilai</th>
            <th class="tengah">Butir Terisi</th><th class="tengah">Nilai Tahun Keempat</th></tr></thead>
        <tbody>
        <?php $noT = 0; foreach ($slotTahun4 as $sl): $noT++;
            $akun = $sl['akun']; $rT = $sl['rinci']; ?>
            <tr>
                <td class="tengah"><?= $noT ?></td>
                <td><b><?= e($sl['label']) ?></b>
                    <?php if ($sl['kategori'] === 'pengawas'): ?>
                        <div class="kecil mut"><?= e($sl['jabatan']) ?></div>
                    <?php endif; ?></td>
                <td class="kecil"><?= $akun
                    ? '<b>' . e((string)$akun['nama']) . '</b>'
                        . ((string)($akun['jabatan'] ?? '') !== '' ? '<div class="mut">' . e($akun['jabatan']) . '</div>' : '')
                        . ((int)$akun['id'] === (int)($u['id'] ?? 0) ? ' <span class="pil pil-unggul">Anda</span>' : '')
                    : '<span class="mut">belum diisi</span>' ?></td>
                <td class="tengah"><?= $rT ? (int)$rT['terisi'] . ' / ' . (int)$rT['jumlah'] : '<span class="mut">-</span>' ?></td>
                <td class="tengah"><?= $rT ? n2($rT['nilai']) : '<span class="mut">-</span>' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</div>

<div class="card">
    <h3>Nilai tiap Tugas Utama (gabungan Pengawas I &amp; II)</h3>
    <div class="geser"><table class="tbl">
        <thead><tr><th>Tugas Utama</th>
            <?php foreach (PKKM_TAHUN_LIST as $label): ?><th class="tengah"><?= e($label) ?></th><?php endforeach; ?>
        </tr></thead>
        <tbody>
        <?php foreach (instrumen_bagian() as $kode => $b): ?>
            <tr><td><b><?= e($kode) ?>.</b> <?= e($b['judul']) ?>
                <div class="kecil mut"><?= count(bagian_tahun(4)[$kode]['butir'] ?? []) ?> butir
                    <?= in_array($kode, ['E'], true) ? '(Tahun Keempat)' : '' ?></div></td>
                <?php foreach (array_keys(PKKM_TAHUN_LIST) as $tahun): ?>
                    <td class="tengah"><?= in_array($tahun, $b['tahun'], true) ? n2($rekap[$tahun]['rerata_bagian'][$kode] ?? null) : '<span class="mut">-</span>' ?></td>
                <?php endforeach; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr><td>Nilai tahunan (rata-rata tugas utama)</td>
            <?php foreach (array_keys(PKKM_TAHUN_LIST) as $tahun): ?>
                <td class="tengah"><?= n2($rekap[$tahun]['rerata']) ?></td>
            <?php endforeach; ?>
        </tr></tfoot>
    </table></div>
    <p class="kecil mut mt8 mb0">Tahun Pertama-Ketiga memakai 4 tugas utama (A-D); Tahun Keempat menambah
        bagian E &mdash; Hasil Kinerja Kepala Madrasah.</p>
</div>
<?php
page_bottom();
