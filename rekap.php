<?php
/**
 * e-PKKM - Rekap PKKM: nilai gabungan hasil PKKM Pengawas I dan Pengawas II Tahun Pertama
 * sampai Tahun Keempat, ditambah komponen Tahun Keempat (Guru, Tendik, Komite, Kabid, Kasi).
 */
require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/form_penilaian.php';
$u = require_login();
$s = require_sekolah();
$sid = (int)$s['id'];
$rekap = rekap_tahunan($sid);
$r4 = rekap_4tahun($sid);
$komp = rekap_komponen_tahun4($sid);

page_top('Rekap PKKM', 'Nilai gabungan PKKM Pengawas I &amp; II Tahun Pertama s.d. Keempat.');
?>
<div class="grid g4">
    <?php foreach (PKKM_TAHUN_LIST as $tahun => $label): $r = $rekap[$tahun]; ?>
        <div class="card stat"><?= ikon('rekap', 20) ?><div>
            <div class="stat-angka"><?= n2($r['rerata']) ?></div>
            <div class="stat-label"><?= e($label) ?> <span class="pil <?= e(predikat_kelas($r['predikat'])) ?>"><?= e($r['predikat']) ?></span></div>
        </div></div>
    <?php endforeach; ?>
</div>

<div class="card">
    <div class="baris-info">
        <div><span>Madrasah:</span> <b><?= e($s['nama']) ?></b></div>
        <div><span>Kepala Madrasah:</span> <b><?= e($s['kepala_nama'] ?: '-') ?></b></div>
        <div><span>Nilai kinerja 4 tahun:</span> <b><?= n2($r4['nilai']) ?></b>
            <span class="pil <?= e(predikat_kelas($r4['predikat'])) ?>"><?= e($r4['predikat']) ?></span></div>
        <div><span>Tahun terisi:</span> <b><?= (int)$r4['ada'] ?> dari 4</b></div>
    </div>
    <div class="form-aksi">
        <a class="btn" href="cetak_rekap.php"><?= ikon('cetak', 16) ?> Cetak Rekap</a>
        <a class="btn btn-emas" href="cetak_rekap.php?auto=1" target="_blank" rel="noopener"><?= ikon('cetak', 16) ?> Cetak Langsung</a>
        <a class="btn btn-ghost" href="rekap_docx.php"><?= ikon('unduh', 16) ?> Unduh Word (.docx)</a>
        <a class="btn btn-ghost" href="laporan.php"><?= ikon('lapor', 16) ?> Laporan</a>
    </div>
</div>

<div class="card">
    <div class="card-kepala"><h2>Rekapitulasi Hasil Penilaian Kinerja Tahunan</h2></div>
    <div class="geser"><table class="tbl">
        <thead><tr><th rowspan="2">Tahun</th><th colspan="2" class="tengah">Nilai Pengawas</th>
            <th rowspan="2" class="tengah">Rerata</th><th rowspan="2" class="tengah">Predikat</th>
            <th rowspan="2" class="tengah">Tahun</th><th rowspan="2" class="tengah">Tanggal Penilaian</th></tr>
            <tr><th class="tengah">Pengawas I</th><th class="tengah">Pengawas II</th></tr>
        </thead>
        <tbody>
        <?php foreach ($rekap as $tahun => $r): ?>
            <tr>
                <td><b><?= e(PKKM_TAHUN_LIST[$tahun]) ?></b></td>
                <td class="tengah"><?= n2($r['kode']['1']['nilai'] ?? null) ?></td>
                <td class="tengah"><?= n2($r['kode']['2']['nilai'] ?? null) ?></td>
                <td class="tengah"><b><?= n2($r['rerata']) ?></b></td>
                <td class="tengah"><span class="pil <?= e(predikat_kelas($r['predikat'])) ?>"><?= e($r['predikat']) ?></span></td>
                <td class="tengah"><?= e(tahun_penilaian_tampil($sid, $s, (int)$tahun) ?: '-') ?></td>
                <td class="tengah"><?= e(tanggal_id((string)($s['tanggal_' . $tahun] ?? '')) ?: '-') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr><td colspan="3">Nilai Kinerja Kepala Madrasah 4 Tahun (NKKM)</td>
                <td class="tengah"><?= n2($r4['nilai']) ?></td>
                <td class="tengah"><span class="pil <?= e(predikat_kelas($r4['predikat'])) ?>"><?= e($r4['predikat']) ?></span></td>
                <td colspan="2"></td>
            </tr>
        </tfoot>
    </table></div>
    <?php $slotRekap = penilai_sekolah_slots($sid);
          $jmlSlotRekap = penilai_sekolah_slot_jumlah($sid); ?>
    <p class="kecil mut mt8 mb0">NKKM = (rata-rata nilai Tahun Pertama-Ketiga + 5 &times; nilai Tahun Keempat) &divide; 6,
        yaitu rumus yang dipakai berkas instrumen PKKM. Nilai Tahun Keempat memakai
        <?= $r4['sumber_tahun4'] === 'kumulatif' ? 'angka kumulatif seluruh penilai' : 'nilai pengawas (komponen penilai lain belum lengkap)' ?>.
        Rincian tiap komponen ada pada tabel di bawah. Nilai Tahun Keempat = rata-rata
        <b><?= (int)$jmlSlotRekap['nilai'] ?> dari 7 penilai</b> yang sudah mengisi (lihat tabel 7 Penilai Tahun Keempat).</p>
</div>

<div class="card" id="cardRekapPenilaiTambahan">
    <div class="card-kepala"><h2>7 Penilai Tahun Keempat</h2>
        <div class="aksi">
            <span class="pil <?= status_lembaga_sekolah($sid) === 'negeri' ? 'pil-baik' : 'pil-cukup' ?>">Madrasah
                <?= e(status_lembaga_label(status_lembaga_sekolah($sid))) ?></span>
            <span class="pil pil-unggul"><?= (int)$jmlSlotRekap['isi'] ?>/<?= (int)$jmlSlotRekap['total'] ?> terisi</span>
        </div>
    </div>
    <div class="geser"><table class="tbl tbl-padat" id="tblRekapPenilaiTahun4">
        <thead><tr><th class="tengah" style="width:46px">No</th><th>Penilai (Slot)</th><th>Nama Penilai</th>
            <th class="tengah">Butir Terisi</th><th class="tengah">Nilai Tahun Keempat</th></tr></thead>
        <tbody>
        <?php $noTR = 0; foreach ($slotRekap as $sl): $noTR++; $akun = $sl['akun']; $rT = $sl['rinci']; ?>
            <tr><td class="tengah"><?= $noTR ?></td>
                <td><b><?= e($sl['label']) ?></b></td>
                <td class="kecil"><?= $akun ? e((string)$akun['nama'])
                    : '<span class="mut">belum diisi</span>' ?></td>
                <td class="tengah"><?= $rT ? (int)$rT['terisi'] . ' / ' . (int)$rT['jumlah'] : '<span class="mut">-</span>' ?></td>
                <td class="tengah"><?= $rT ? n2($rT['nilai']) : '<span class="mut">-</span>' ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <p class="kecil mut mt8 mb0">Susunan 7 penilai mengikuti <b>Status Lembaga</b>. Guru, Tendik, Komite,
        Kabid/Yayasan, dan Kasie mengisi lembarnya masing-masing melalui menu
        <b>Penilaian Kepala Madrasah</b>.</p>
</div>

<div class="card">
    <div class="card-kepala"><h2>Rekapitulasi Penilaian Kinerja Empat Tahunan</h2></div>
    <div class="geser"><table class="tbl">
        <thead><tr><th style="width:40px">No</th><th>Jenis Penilaian</th><th class="tengah" style="width:120px">Skor</th></tr></thead>
        <tbody>
        <?php foreach ($r4['per_tahun'] as $tahun => $nilai): ?>
            <tr><td><?= (int)$tahun ?>.</td>
                <td>Penilaian Kinerja <?= e(PKKM_TAHUN_LIST[$tahun]) ?>
                    <?php if ((int)$tahun === 4): ?><span class="kecil mut">(kumulatif seluruh penilai)</span><?php endif; ?></td>
                <td class="tengah"><?= n2($nilai) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr><td colspan="2"><b>Nilai Kinerja Kepala Madrasah (NKKM)</b></td>
                <td class="tengah"><b><?= n2($r4['nilai']) ?></b></td></tr>
            <tr><td colspan="2">Predikat Nilai Kinerja Kepala Madrasah</td>
                <td class="tengah"><span class="pil <?= e(predikat_kelas($r4['predikat'])) ?>"><?= e($r4['predikat']) ?></span></td></tr>
        </tfoot>
    </table></div>
</div>

<div class="card">
    <div class="card-kepala"><h2>Rekapitulasi Penilaian Tahun Keempat</h2></div>
    <div class="geser"><table class="tbl">
        <thead><tr><th>No</th><th>Jenis Penilaian</th><th class="tengah">Skor</th></tr></thead>
        <tbody>
        <?php $no = 0; foreach ($komp['komponen'] as $k): $no++; ?>
            <tr><td><?= $no ?>.</td><td><?= e($k['label']) ?></td>
                <td class="tengah"><?= n2($k['nilai']) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr><td colspan="2">Hasil Penilaian Kinerja Tahun Keempat (rata-rata komponen terisi)</td>
                <td class="tengah"><?= n2($komp['jumlah']) ?></td></tr>
            <tr><td colspan="2">Predikat Penilaian Kinerja Tahun Keempat</td>
                <td class="tengah"><span class="pil <?= e(predikat_kelas(predikat_dari($komp['jumlah']))) ?>"><?= e(predikat_dari($komp['jumlah'])) ?></span></td></tr>
        </tfoot>
    </table></div>
    <?php if ($komp['ada'] < 6): ?>
        <p class="keterangan kecil mut mt8 mb0">Baru <?= (int)$komp['ada'] ?> dari 6 komponen yang terisi &mdash;
            penilaian <?= e(implode(', ', array_map(fn($sl) => $sl['label'], array_filter($slotRekap,
                fn($sl) => $sl['kategori'] === PKKM_KATEGORI_TAMBAHAN)))) ?> diisi
            masing-masing penilai pada menu <b>Penilaian Kepala Madrasah</b>
            (madrasah <b><?= e(status_lembaga_label($komp['status'] ?? '')) ?></b>).</p>
    <?php endif; ?>
</div>
<?php
page_bottom();
