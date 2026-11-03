<?php
/**
 * e-PKKM - Laporan: rekapan hasil PKKM Pengawas I dan rekapan hasil PKKM Pengawas II
 * untuk Tahun Pertama sampai Tahun Keempat, beserta rekap gabungan keduanya.
 */
require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/form_penilaian.php';
$u = require_login();
$s = require_sekolah();
$sid = (int)$s['id'];
$rekap = rekap_tahunan($sid);
$pen = ['1' => penilai_ambil($sid, 'pengawas', '1'), '2' => penilai_ambil($sid, 'pengawas', '2')];
$bagian = instrumen_bagian();

page_top('Laporan', 'Rekapan hasil PKKM Pengawas I dan Pengawas II, Tahun Pertama s.d. Tahun Keempat.');
?>
<div class="card">
    <div class="baris-info">
        <div><span>Madrasah:</span> <b><?= e($s['nama']) ?></b></div>
        <div><span>Kepala Madrasah:</span> <b><?= e($s['kepala_nama'] ?: '-') ?></b></div>
        <div><span>NIP:</span> <b><?= e($s['kepala_nip'] ?: '-') ?></b></div>
        <div><span>Tahun penilaian:</span> <b><?= (int)($s['tahun_1'] ?: 0) ?: '-' ?> s.d. <?= (int)($s['tahun_4'] ?: 0) ?: '-' ?></b></div>
    </div>
    <div class="form-aksi">
        <a class="btn" href="cetak_laporan.php?semua=1" target="_blank" rel="noopener"><?= ikon('cetak', 16) ?> Cetak Laporan (semua tahun)</a>
        <a class="btn btn-emas" href="cetak_laporan.php?semua=1&auto=1" target="_blank" rel="noopener"><?= ikon('cetak', 16) ?> Cetak Langsung</a>
        <a class="btn btn-ghost" href="rekap.php"><?= ikon('rekap', 16) ?> Rekap PKKM</a>
        <?php if ($u['role'] === 'pengawas'): ?>
            <a class="btn btn-ghost" href="penilaian.php"><?= ikon('nilai', 16) ?> Penilaian</a>
        <?php endif; ?>
    </div>
</div>

<?php // ---------------- CETAK LAPORAN PER TAHUN (permintaan pemilik: dirapikan) ---------------- ?>
<div class="card" id="cardCetakLaporanTahun">
    <div class="card-kepala"><h2>Cetak Laporan per Tahun</h2>
        <div class="aksi"><span class="kecil mut">lembar A4 resmi, satu tahun satu halaman</span></div>
    </div>
    <p class="ket">Pilih tahun yang ingin dicetak. Lembar laporan memuat identitas penilai &amp; kepala
        madrasah, tabel nilai tiap tugas utama, predikat, serta tanda tangan yang namanya sudah sejajar.</p>
    <div class="geser"><table class="tbl tbl-padat" id="tblCetakLaporanTahun">
        <thead><tr><th style="width:46px" class="tengah">No</th><th>Tahun Penilaian</th>
            <th class="tengah">Tanggal Menilai P1</th><th class="tengah">Tanggal Menilai P2</th>
            <th class="tengah">Nilai Gabungan</th><th class="tengah">Cetak Pengawas I</th>
            <th class="tengah">Cetak Pengawas II</th><th class="tengah">Keduanya</th></tr></thead>
        <tbody>
        <?php $noL = 0; foreach (PKKM_TAHUN_LIST as $tahun => $label): $noL++;
            $t1 = penilai_tanggal($sid, 'pengawas', '1', (int)$tahun);
            $t2 = penilai_tanggal($sid, 'pengawas', '2', (int)$tahun); ?>
            <tr>
                <td class="tengah"><?= $noL ?></td>
                <td><b><?= e($label) ?></b>
                    <div class="kecil mut"><?= e($s['tahun_' . $tahun] ? 'tahun ' . (int)$s['tahun_' . $tahun] : 'tahun belum diisi') ?></div></td>
                <td class="tengah"><?= $t1 !== '' ? e(tanggal_id($t1)) : '<span class="mut kecil">belum diisi</span>' ?></td>
                <td class="tengah"><?= $t2 !== '' ? e(tanggal_id($t2)) : '<span class="mut kecil">belum diisi</span>' ?></td>
                <td class="tengah"><b><?= n2($rekap[$tahun]['rerata']) ?></b>
                    <div><span class="pil <?= e(predikat_kelas($rekap[$tahun]['predikat'])) ?>"><?= e($rekap[$tahun]['predikat']) ?></span></div></td>
                <td class="tengah"><a class="btn btn-kecil btn-ghost"
                    href="cetak_laporan.php?posisi=1&tahun=<?= (int)$tahun ?>"><?= ikon('cetak', 14) ?> P1</a></td>
                <td class="tengah"><a class="btn btn-kecil btn-ghost"
                    href="cetak_laporan.php?posisi=2&tahun=<?= (int)$tahun ?>"><?= ikon('cetak', 14) ?> P2</a></td>
                <td class="tengah"><a class="btn btn-kecil btn-emas"
                    href="cetak_laporan.php?semua=1&tahun=<?= (int)$tahun ?>"><?= ikon('cetak', 14) ?> Cetak</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</div>

<?php foreach (['1' => 1, '2' => 2] as $kode => $urut): ?>
<div class="card">
    <div class="card-kepala">
        <h2>Rekapan Hasil PKKM <?= e(PKKM_PENILAI_PENGAWAS[$kode]) ?></h2>
        <div class="aksi"><span class="pil <?= e($pen[$kode]['nama'] ?? '' ? 'pil-unggul' : 'pil-kosong') ?>">
            <?= e($pen[$kode]['nama'] ?? 'identitas penilai belum diisi') ?></span></div>
    </div>
    <div class="geser"><table class="tbl">
        <thead><tr><th>Tugas Utama</th>
            <?php foreach (PKKM_TAHUN_LIST as $label): ?><th class="tengah"><?= e($label) ?></th><?php endforeach; ?>
        </tr></thead>
        <tbody>
        <?php foreach ($bagian as $bk => $b): ?>
            <tr><td><b><?= e($bk) ?>.</b> <?= e($b['judul']) ?></td>
                <?php foreach (array_keys(PKKM_TAHUN_LIST) as $tahun): ?>
                    <td class="tengah"><?= in_array($tahun, $b['tahun'], true) ? n2($rekap[$tahun]['kode'][$kode]['bagian'][$bk] ?? null) : '<span class="mut">-</span>' ?></td>
                <?php endforeach; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr><td>Nilai Kinerja Tahunan</td>
                <?php foreach (array_keys(PKKM_TAHUN_LIST) as $tahun): ?>
                    <td class="tengah"><?= n2($rekap[$tahun]['kode'][$kode]['nilai'] ?? null) ?></td>
                <?php endforeach; ?>
            </tr>
            <tr><td>Predikat</td>
                <?php foreach (array_keys(PKKM_TAHUN_LIST) as $tahun):
                    $p = predikat_dari($rekap[$tahun]['kode'][$kode]['nilai'] ?? null); ?>
                    <td class="tengah"><span class="pil <?= e(predikat_kelas($p)) ?>"><?= e($p) ?></span></td>
                <?php endforeach; ?>
            </tr>
        </tfoot>
    </table></div>
    <p class="kecil mut mt8 mb0">Tanggal menilai yang tercatat:
        <?php $tgl = []; foreach (array_keys(PKKM_TAHUN_LIST) as $tahun) {
            $d = tanggal_id(penilai_tanggal($sid, 'pengawas', (string)$kode, (int)$tahun));
            if ($d !== '') { $tgl[] = pilihan_tahun($tahun) . ': ' . $d; }
        } echo $tgl ? e(implode(' · ', $tgl)) : 'belum diisi'; ?>.</p>
</div>
<?php endforeach; ?>

<?php $slotLap = penilai_sekolah_slots($sid);
      $jmlSlot = penilai_sekolah_slot_jumlah($sid); ?>
<div class="card" id="cardLaporanPenilaiTambahan">
    <div class="card-kepala"><h2>7 Penilai Tahun Keempat</h2>
        <div class="aksi">
            <span class="pil <?= status_lembaga_sekolah($sid) === 'negeri' ? 'pil-baik' : 'pil-cukup' ?>">Madrasah
                <?= e(status_lembaga_label(status_lembaga_sekolah($sid))) ?></span>
            <span class="pil pil-unggul"><?= (int)$jmlSlot['isi'] ?>/<?= (int)$jmlSlot['total'] ?> terisi</span>
        </div>
    </div>
    <div class="geser"><table class="tbl tbl-padat" id="tblLaporanPenilaiTahun4">
        <thead><tr><th class="tengah" style="width:46px">No</th><th>Penilai (Slot)</th><th>Nama Penilai</th>
            <th class="tengah">Butir Terisi</th><th class="tengah">Nilai Tahun Keempat</th></tr></thead>
        <tbody>
        <?php $noT = 0; foreach ($slotLap as $sl): $noT++; $akun = $sl['akun']; $rT = $sl['rinci']; ?>
            <tr><td class="tengah"><?= $noT ?></td>
                <td><b><?= e($sl['label']) ?></b></td>
                <td class="kecil"><?= $akun ? e((string)$akun['nama'])
                    : '<span class="mut">belum diisi</span>' ?></td>
                <td class="tengah"><?= $rT ? (int)$rT['terisi'] . ' / ' . (int)$rT['jumlah'] : '<span class="mut">-</span>' ?></td>
                <td class="tengah"><?= $rT ? n2($rT['nilai']) : '<span class="mut">-</span>' ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</div>

<div class="card">
    <div class="card-kepala"><h2>Rekapitulasi Gabungan Pengawas I &amp; II</h2></div>
    <div class="geser"><table class="tbl">
        <thead><tr><th>Tugas Utama</th>
            <th class="tengah">Tahun Pertama</th><th class="tengah">Tahun Kedua</th>
            <th class="tengah">Tahun Ketiga</th><th class="tengah">Tahun Keempat</th></tr></thead>
        <tbody>
        <?php foreach ($bagian as $bk => $b): ?>
            <tr><td><b><?= e($bk) ?>.</b> <?= e($b['judul']) ?></td>
                <?php foreach (array_keys(PKKM_TAHUN_LIST) as $tahun): ?>
                    <td class="tengah"><?= in_array($tahun, $b['tahun'], true) ? n2($rekap[$tahun]['rerata_bagian'][$bk] ?? null) : '<span class="mut">-</span>' ?></td>
                <?php endforeach; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr><td>Nilai Kinerja Tahunan (rata-rata)</td>
                <?php foreach (array_keys(PKKM_TAHUN_LIST) as $tahun): ?>
                    <td class="tengah"><?= n2($rekap[$tahun]['rerata']) ?></td>
                <?php endforeach; ?>
            </tr>
            <tr><td>Predikat</td>
                <?php foreach (array_keys(PKKM_TAHUN_LIST) as $tahun): ?>
                    <td class="tengah"><span class="pil <?= e(predikat_kelas($rekap[$tahun]['predikat'])) ?>"><?= e($rekap[$tahun]['predikat']) ?></span></td>
                <?php endforeach; ?>
            </tr>
        </tfoot>
    </table></div>
    <p class="kecil mut mt8 mb0">Angka gabungan = rata-rata hasil Pengawas I dan Pengawas II;
        pada <b>Tahun Keempat</b> ikut dirata-ratakan <b>penilai Tahun Keempat lainnya</b> sesuai
        <b>Status Lembaga</b> madrasah (<?= e(status_lembaga_label(status_lembaga_sekolah($sid))) ?>:
        <?= e(implode(', ', array_map(fn($sl) => $sl['label'], array_filter($slotLap,
            fn($sl) => $sl['kategori'] === PKKM_KATEGORI_TAMBAHAN)))) ?>).
        Predikat mengikuti ambang PermenPANRB 6/2022 (Amat Baik &ge; 91; Baik 81-90,99; Cukup 71-80,99; Kurang &lt; 71).</p>
</div>
<?php
page_bottom();
