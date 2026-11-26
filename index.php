<?php
/**
 * e-PKKM - Dashboard (Super Admin lintas madrasah / dashboard madrasah / dashboard pengawas).
 */
require_once __DIR__ . '/inc/init.php';
$u = require_login();

// -------- penilai memilih madrasah & posisinya (POST) --------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $u['role'] === 'pengawas') {
    if (!csrf_ok()) {
        tolak_csrf();
    }
    $aksi = (string)($_POST['aksi'] ?? '');

    // v1.12.0 (permintaan pemilik): PENILAI 1 (Ketua Tim) menentukan PENILAI 2 (Anggota Tim)
    // dari daftar pengawas yang ada. Ditegakkan di server: hanya pemegang posisi 1 yang boleh.
    if ($aksi === 'penilai2_simpan') {
        $peta = is_array($_POST['penilai2'] ?? null) ? $_POST['penilai2'] : [];
        $hasil = penilai2_simpan((int)$u['id'], $peta);
        $pesan = [];
        if ($hasil['disimpan'] > 0) {
            $pesan[] = $hasil['disimpan'] . ' madrasah mendapat ' . penilai_posisi_teks('2') . '.';
        }
        if ($hasil['dikosongkan'] > 0) {
            $pesan[] = $hasil['dikosongkan'] . ' madrasah dikosongkan ' . penilai_posisi_teks('2') . '.';
        }
        if (!$pesan) {
            $pesan[] = 'Tidak ada perubahan ' . penilai_posisi_teks('2') . '.';
        }
        flash(implode(' ', $pesan)
            . ($hasil['ditolak'] ? ' Dilewati: ' . implode('; ', $hasil['ditolak']) . '.' : ''),
            $hasil['ditolak'] ? 'err' : 'ok');
        redirect('index.php#cardBinaanSaya');
    }

    if ($aksi === 'tugas_simpan') {
        // v1.12.0: akun pengawas BINAAN Supervisi tidak memilih sendiri lagi — posisinya melekat
        // otomatis sebagai Penilai 1 (Ketua Tim) sesuai peta pengawas binaan aplikasi Supervisi.
        if (penilai_jabatan_pengawas($u) && penilai_punya_peta_binaan($u)) {
            flash('Posisi Anda mengikuti peta pengawas binaan aplikasi Supervisi (otomatis '
                . penilai_posisi_teks('1') . '). ' . penilai_posisi_teks('2')
                . ' ditentukan lewat kartu Madrasah Binaan Saya.', 'err');
            redirect('index.php#cardBinaanSaya');
        }
        $pilih = is_array($_POST['pilih'] ?? null) ? $_POST['pilih'] : [];
        // Formulir halaman ini memakai CENTANG madrasah + pilihan posisi (radio).
        // Bentuk lama (satu pilihan bernilai posisi) tetap diterima agar tautan/skrip lama tidak rusak.
        $gayaRadio = is_array($_POST['posisi'] ?? null);
        $petaPosisi = $gayaRadio ? $_POST['posisi'] : [];
        // Penilai berjabatan Pengawas memakai posisi 1/2; jabatan lain menjadi PENILAI TAMBAHAN
        // Tahun Keempat (posisi dihitung otomatis mulai 3) — permintaan pemilik.
        $jabatanPengawas = penilai_jabatan_pengawas($u);
        $rencana = [];
        $tanpaPosisi = 0;
        foreach ($pilih as $sid => $v) {
            $sid = (int)$sid;
            if ($gayaRadio) {
                $pos = (int)($petaPosisi[$sid] ?? 0);
                if (!in_array($pos, [1, 2], true)) {
                    $tanpaPosisi++;
                    continue;
                }
            } elseif ($jabatanPengawas) {
                $pos = (int)$v;
                if (!in_array($pos, [1, 2], true)) {
                    continue;
                }
            } else {
                $pos = PKKM_POSISI_TAMBAHAN;   // penilai tambahan (nomor posisi dihitung di modul)
            }
            $rencana[$sid] = $pos;
        }
        // Madrasah yang TIDAK dicentang = dilepas (dikirim sebagai 0) supaya penugasan lama ikut
        // dibersihkan — penugasan_penilai_simpan() hanya melepas madrasah yang dikirim 0.
        foreach (all("SELECT id FROM sekolah WHERE aktif = 1 AND daftar_status = 'disetujui'") as $sk) {
            $sidSemua = (int)$sk['id'];
            if (!isset($rencana[$sidSemua])) {
                $rencana[$sidSemua] = 0;
            }
        }
        $hasil = penugasan_penilai_simpan((int)$u['id'], $rencana);
        flash('Pilihan madrasah tersimpan: ' . $hasil['jumlah'] . ' madrasah.'
            . ($tanpaPosisi > 0 ? ' ' . $tanpaPosisi . ' madrasah dicentang tanpa posisi sehingga belum tersimpan.' : '')
            . ($hasil['ditolak'] ? ' Dilewati: ' . implode('; ', $hasil['ditolak']) . '.' : ''));
        redirect('index.php');
    }
    flash('Aksi tidak dikenal.', 'err');
    redirect('index.php');
}

// -------- keluar dari konteks madrasah --------
if (isset($_GET['keluar'])) {
    mulai_sesi();
    unset($_SESSION['ctx_sekolah_id']);
    redirect('index.php');
}

// -------- masuk konteks madrasah (Super Admin & Pengawas) --------
if (isset($_GET['masuk'])) {
    $id = (int)$_GET['masuk'];
    $s = one('SELECT * FROM sekolah WHERE id = ? AND aktif = 1', [$id]);
    if (!$s) {
        flash('Madrasah tidak ditemukan atau sedang nonaktif.', 'err');
        redirect('index.php');
    }
    if ($u['role'] === 'pengawas' && pengawas_posisi_di((int)$u['id'], $id) === 0) {
        flash('Anda tidak ditugaskan pada madrasah tersebut.', 'err');
        redirect('index.php');
    }
    if ($u['role'] !== 'super' && $u['role'] !== 'pengawas') {
        redirect('index.php');
    }
    set_sekolah_context($id);
    redirect('index.php');
}

$s = current_sekolah();

// =====================================================================
// CABANG PENILAI (peran `pengawas`) - pilih madrasah + posisi, lalu daftar tugas
// =====================================================================
if ($u['role'] === 'pengawas' && !$s) {
    $jabatanSaya = trim((string)($u['jabatan'] ?? ''));
    $jabatanPengawasSaya = penilai_jabatan_pengawas($u);

    // v1.12.0 (permintaan pemilik): daftar madrasah BINAAN dari peta pengawas binaan aplikasi
    // Supervisi, dan pengawas binaan OTOMATIS menjadi Penilai 1 (Ketua Tim). Sinkronisasi ini
    // idempoten: hanya mengisi posisi 1 yang masih kosong.
    $pakaiBinaan = $jabatanPengawasSaya && penilai_punya_peta_binaan($u);
    $binaanSinkron = $pakaiBinaan ? penilai_binaan_sinkron($u) : ['baru' => 0, 'bentrok' => []];
    $tugas = pengawas_tugas((int)$u['id']);
    $binaanSaya = $pakaiBinaan ? penilai_binaan_pengawas($u) : [];
    $daftarPengawas = $binaanSaya ? penilai_akun_pengawas_list((int)$u['id']) : [];
    // Pilihan sendiri (centang madrasah + posisi) hanya dipakai akun penilai yang TIDAK punya
    // peta binaan Supervisi (mis. akun penilai yang dibuat Super Admin secara manual).
    $pilihan = $binaanSaya ? [] : penilai_sekolah_pilihan((int)$u['id']);
    $jmlPilih = 0;
    foreach ($pilihan as $pl) {
        if ((int)$pl['posisi'] > 0) {
            $jmlPilih++;
        }
    }
    page_top('Dashboard Penilai', $jabatanPengawasSaya
        ? 'Seluruh madrasah binaan Anda tampil di sini — Anda menjadi ' . penilai_posisi_teks('1') . ' dan menentukan '
            . penilai_posisi_teks('2') . '.'
        : 'Pilih madrasah yang Anda nilai sebagai penilai Tahun Keempat.');
    ?>
    <div class="card" id="cardIdentitasPenilai">
        <div class="card-kepala"><h2>Identitas Penilai</h2>
            <span class="penilai-jabatan-chip"><?= e($jabatanSaya !== '' ? $jabatanSaya : 'jabatan belum diisi') ?></span>
        </div>
        <div class="baris-info">
            <div><span>Nama Penilai:</span> <b><?= e($u['nama']) ?></b></div>
            <div><span>Nama Pengguna:</span> <b><?= e($u['username']) ?></b></div>
            <div><span>NIP:</span> <b><?= e(trim((string)($u['nip'] ?? '')) !== '' ? $u['nip'] : '-') ?></b></div>
            <div><span>Unit Kerja/Instansi:</span> <b><?= e(trim((string)($u['instansi'] ?? '')) !== '' ? $u['instansi'] : '-') ?></b></div>
            <div><span>Kabupaten/Kota:</span> <b><?= e(trim((string)($u['kab_kota'] ?? '')) !== '' ? $u['kab_kota'] : '-') ?></b></div>
            <div><span>Status akun:</span> <b>Penilai (pengawas)</b></div>
            <div><span>Tahun yang dapat dinilai:</span> <b><?= e(penilai_tahun_label($u)) ?></b></div>
        </div>
        <?php if (!penilai_jabatan_pengawas($u)): ?>
            <p class="ket mb0">Jabatan Anda <b><?= e(trim((string)($u['jabatan'] ?? '')) ?: 'belum diisi') ?></b>
                &mdash; bukan Pengawas, sehingga Anda hanya menilai <b>Tahun Keempat</b>.
                Tahun Pertama s.d. Ketiga hanya dapat dinilai penilai berjabatan Pengawas.</p>
        <?php endif; ?>
    </div>

    <div class="grid g3">
        <div class="card stat"><?= ikon('madrasah', 20) ?><div>
            <div class="stat-angka"><?= count($tugas) ?></div><div class="stat-label">Madrasah dinilai</div></div></div>
        <div class="card stat"><?= ikon('nilai', 20) ?><div>
            <div class="stat-angka"><?= count(array_filter($tugas, fn($t) => (int)$t['aktif'] === 1)) ?></div>
            <div class="stat-label">Madrasah aktif</div></div></div>
        <div class="card stat"><?= ikon('lapor', 20) ?><div>
            <div class="stat-angka"><?= penilai_jabatan_pengawas($u) ? count(PKKM_TAHUN_LIST) * 2 : 7 ?></div>
            <div class="stat-label"><?= penilai_jabatan_pengawas($u)
                ? 'Penilaian per madrasah'
                : 'Penilai Tahun Keempat per madrasah' ?></div></div></div>
    </div>

    <!-- ============ MADRASAH BINAAN SAYA (v1.12.0, permintaan pemilik) ============ -->
    <?php if ($binaanSaya): ?>
    <div class="card" id="cardBinaanSaya">
        <div class="card-kepala"><h2>Madrasah Binaan Saya</h2>
            <span class="pil pil-unggul"><?= count($binaanSaya) ?> madrasah</span>
        </div>
        <p class="ket">Seluruh madrasah binaan yang ditetapkan pada aplikasi Supervisi tampil di sini. Anda
            <b>otomatis</b> menjadi <b><?= e(penilai_posisi_teks('1')) ?></b> pada setiap madrasah
            binaan. Untuk <b><?= e(penilai_posisi_teks('2')) ?></b>, tentukan sendiri dari
            <b>daftar pengawas yang ada</b> pada kolom paling kanan, lalu tekan
            <b>Simpan Penilai 2</b>.</p>
        <?php if (($binaanSinkron['baru'] ?? 0) > 0): ?>
            <div class="alert alert-info"><?= (int)$binaanSinkron['baru'] ?> madrasah binaan baru
                ditetapkan kepada Anda sebagai <?= e(penilai_posisi_teks('1')) ?>.</div>
        <?php endif; ?>
        <?php if (!empty($binaanSinkron['bentrok'])): ?>
            <div class="alert alert-err">
                <b>Madrasah yang sudah dinilai pengawas binaan lain (posisi
                    <?= e(penilai_posisi_teks('1')) ?> tidak direbut):</b>
                <ul class="mb0">
                    <?php foreach ($binaanSinkron['bentrok'] as $bt): ?><li><?= e($bt) ?></li><?php endforeach; ?>
                </ul>
                Anda tetap dapat ditunjuk sebagai <?= e(penilai_posisi_teks('2')) ?> oleh Ketua Tim madrasah itu.
            </div>
        <?php endif; ?>
        <form method="post" action="index.php" id="formPenilai2">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="penilai2_simpan">
            <?php if (!$daftarPengawas): ?>
                <p class="mut mb0">Belum ada akun pengawas lain yang dapat dipilih sebagai
                    <?= e(penilai_posisi_teks('2')) ?>. Akun pengawas dibuat Super Admin lewat menu
                    <b>Akun Penilai</b> (atau ditarik dari aplikasi Supervisi).</p>
            <?php endif; ?>
            <div class="geser"><table class="tbl penilai-binaan" id="tblBinaanSaya">
                <thead><tr><th class="tengah">No</th><th>Madrasah</th><th>Kecamatan</th>
                    <th>Posisi Saya</th><th class="kolom-penilai2"><?= e(penilai_posisi_teks('2')) ?></th></tr></thead>
                <tbody>
                <?php foreach ($binaanSaya as $i => $b):
                    $sidb = (int)$b['id'];
                    $ketuaB = $b['ketua'];
                    $p2B = $b['penilai2'];
                    $sayaKetua = (int)$b['posisi'] === 1; ?>
                    <tr data-binaan-baris>
                        <td class="tengah"><?= $i + 1 ?></td>
                        <td><b><?= e($b['nama']) ?></b>
                            <div class="kecil mut"><?= e(status_lembaga_label($b['status_lembaga'])) ?></div></td>
                        <td class="kecil"><?= e($b['kecamatan'] !== '' ? $b['kecamatan'] : '-') ?></td>
                        <td class="kecil">
                            <?php if ($sayaKetua): ?>
                                <span class="pil pil-unggul"><?= e(penilai_posisi_teks('1')) ?></span>
                            <?php elseif ((int)$b['posisi'] === 2): ?>
                                <span class="pil pil-baik"><?= e(penilai_posisi_teks('2')) ?></span>
                                <div class="mut">Ketua Tim:
                                    <?= $ketuaB ? e((string)$ketuaB['nama']) : '-' ?></div>
                            <?php elseif ($ketuaB): ?>
                                <span class="pil pil-kurang">posisi belum ada</span>
                                <div class="mut">Sudah dinilai <b><?= e((string)$ketuaB['nama']) ?></b>
                                    (<?= e(penilai_posisi_teks('1')) ?>)</div>
                            <?php else: ?>
                                <span class="mut">belum ada penugasan</span>
                            <?php endif; ?>
                        </td>
                        <td class="kecil">
                            <?php if ($sayaKetua && $daftarPengawas): ?>
                                <select name="penilai2[<?= $sidb ?>]" id="penilai2Sek<?= $sidb ?>"
                                        <?= $p2B ? '' : '' ?>>
                                    <option value="0">— belum ditentukan —</option>
                                    <?php foreach ($daftarPengawas as $dp):
                                        $dipilih = $p2B && (int)$p2B['id'] === (int)$dp['id']; ?>
                                        <option value="<?= (int)$dp['id'] ?>" <?= $dipilih ? 'selected' : '' ?>>
                                            <?= e((string)$dp['nama']
                                                . (trim((string)($dp['jabatan'] ?? '')) !== ''
                                                    ? ' — ' . (string)$dp['jabatan'] : '')) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php else: ?>
                                <?= $p2B ? e((string)$p2B['nama']) : '<span class="mut">belum ditentukan</span>' ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
            <?php if ($daftarPengawas): ?>
            <div class="form-aksi">
                <button class="btn" type="submit"><?= ikon('simpan', 16) ?> Simpan Penilai 2</button>
            </div>
            <?php endif; ?>
        </form>
    </div>
    <?php endif; ?>

    <!-- ============ PILIH MADRASAH & POSISI (hanya akun penilai tanpa peta binaan) ============ -->
    <?php if (!$binaanSaya): ?>
    <div class="card" id="cardPilihMadrasah">
        <div class="card-kepala"><h2>Pilih Madrasah yang Akan Dinilai</h2>
            <span class="pil pil-unggul"><?= $jmlPilih ?> dipilih</span>
        </div>
        <?php if ($jabatanPengawasSaya): ?>
        <p class="ket">Akun Anda belum terhubung dengan peta pengawas binaan aplikasi Supervisi, sehingga
            madrasah dipilih sendiri di sini. Centang madrasah yang akan Anda nilai, lalu tentukan posisi
            Anda (<b>Penilai 1</b> atau <b>Penilai 2</b>). Satu posisi pada satu madrasah hanya boleh
            dipegang satu penilai, sehingga posisi yang sudah dipegang penilai lain tidak dapat dipilih.</p>
        <form method="post" action="index.php" id="formPilihMadrasah">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="tugas_simpan">
            <?php if (!$pilihan): ?>
                <p class="mut mb0">Belum ada madrasah aktif yang dapat dinilai.</p>
            <?php else: ?>
            <div class="geser"><table class="tbl penilai-pilih" id="tblPilihMadrasah">
                <thead><tr><th class="tengah">No</th><th>Madrasah</th><th>Kecamatan</th>
                    <th>Penilai 1 saat ini</th><th>Penilai 2 saat ini</th>
                    <th class="tengah kolom-centang">Centang</th>
                    <th class="kolom-posisi">Posisi Saya</th></tr></thead>
                <tbody>
                <?php foreach ($pilihan as $i => $pl):
                    $sidp = (int)$pl['id'];
                    $posSaya = (int)$pl['posisi'];
                    $pe1 = $pl['pemegang']['1'];
                    $pe2 = $pl['pemegang']['2'];
                    $lain1 = $pe1 && (int)$pe1['id'] !== (int)$u['id'];
                    $lain2 = $pe2 && (int)$pe2['id'] !== (int)$u['id'];
                    $penuh = $lain1 && $lain2;
                    ?>
                    <tr data-pilih-baris>
                        <td class="tengah"><?= $i + 1 ?></td>
                        <td><b><?= e($pl['nama']) ?></b></td>
                        <td class="kecil"><?= e($pl['kecamatan'] ?: '-') ?></td>
                        <td class="kecil"><?= $pe1 ? e(penilai_nama_lengkap($pe1)) : '<span class="mut">belum ada</span>' ?></td>
                        <td class="kecil"><?= $pe2 ? e(penilai_nama_lengkap($pe2)) : '<span class="mut">belum ada</span>' ?></td>
                        <td class="tengah">
                            <input type="checkbox" name="pilih[<?= $sidp ?>]" value="1"
                                   id="pilihSek<?= $sidp ?>" data-pilih-cek
                                   <?= $posSaya > 0 ? 'checked' : '' ?> <?= $penuh ? 'disabled' : '' ?>
                                   title="<?= $penuh ? 'Kedua posisi sudah dipegang penilai lain' : 'Centang bila madrasah ini akan Anda nilai' ?>">
                        </td>
                        <td class="nowrap">
                            <label class="pilih-posisi">
                                <input type="radio" name="posisi[<?= $sidp ?>]" value="1"
                                       data-pilih-radio <?= $posSaya === 1 ? 'checked' : '' ?>
                                       <?= $lain1 ? 'disabled data-terkunci="1"' : '' ?>>
                                <span><?= e(penilai_posisi_teks('1')) ?><?= $lain1 ? ' — sudah: ' . e($pe1['nama']) : '' ?></span>
                            </label>
                            <label class="pilih-posisi">
                                <input type="radio" name="posisi[<?= $sidp ?>]" value="2"
                                       data-pilih-radio <?= $posSaya === 2 ? 'checked' : '' ?>
                                       <?= $lain2 ? 'disabled data-terkunci="1"' : '' ?>>
                                <span><?= e(penilai_posisi_teks('2')) ?><?= $lain2 ? ' — sudah: ' . e($pe2['nama']) : '' ?></span>
                            </label>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
            <div class="form-aksi">
                <button class="btn" type="submit"><?= ikon('simpan', 16) ?> Simpan Pilihan Madrasah</button>
            </div>
            <?php endif; ?>
        </form>
        <?php else: ?>
        <?php /* ---------- Penilai Tahun Keempat: satu dari 5 slot jabatan (bukan Pengawas) ---------- */ ?>
        <?php $slotSaya = penilai_slot_dari_jabatan($jabatanSaya, PKKM_STATUS_LEMBAGA_BAWAAN); ?>
        <div class="alert alert-info">Jabatan Anda <b><?= e($jabatanSaya !== '' ? $jabatanSaya : 'belum diisi') ?></b>
            bukan <b>Pengawas</b>, sehingga Anda menilai sebagai salah satu dari <b>7 penilai Tahun Keempat</b>.
            Slot Anda mengikuti jabatan &amp; <b>status lembaga</b> madrasah:
            <b>Negeri</b> = Kabid, Kasie, Pengawas 1, Pengawas 2, Guru, Tendik, Komite;
            <b>Swasta</b> = Yayasan, Kasie, Pengawas 1, Pengawas 2, Guru, Tendik, Komite.
            Madrasah hanya dapat dipilih bila <b>penilaian Tahun Pertama s.d. Ketiga sudah lengkap seluruh
            butirnya</b>.</div>
        <form method="post" action="index.php" id="formPilihMadrasah">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="tugas_simpan">
            <?php if (!$pilihan): ?>
                <p class="mut mb0">Belum ada madrasah aktif yang dapat dinilai.</p>
            <?php else: ?>
            <div class="geser"><table class="tbl penilai-pilih" id="tblPilihMadrasah">
                <thead><tr><th class="tengah">No</th><th>Madrasah</th><th>Kecamatan</th>
                    <th>Status</th><th>Slot Anda</th><th>Penilaian Tahun 1 &ndash; 3</th>
                    <th class="tengah kolom-centang">Centang</th></tr></thead>
                <tbody>
                <?php foreach ($pilihan as $i => $pl):
                    $sidp = (int)$pl['id'];
                    $posSaya = (int)$pl['posisi'];
                    $sudahPilih = $posSaya >= PKKM_POSISI_TAMBAHAN;
                    $siap = $pl['siap'] ?: ['siap' => false, 'teks' => '', 'tahun' => []];
                    $slotPl = $pl['slot'];
                    $boleh = ($siap['siap'] && $slotPl !== null && $pl['slot_pemegang'] === null) || $sudahPilih;
                    ?>
                    <tr data-pilih-baris class="<?= $boleh ? '' : 'baris-terkunci' ?>">
                        <td class="tengah"><?= $i + 1 ?></td>
                        <td><b><?= e($pl['nama']) ?></b></td>
                        <td class="kecil"><?= e($pl['kecamatan'] ?: '-') ?></td>
                        <td class="tengah"><?= status_lembaga_pil($pl['status_lembaga']) ?></td>
                        <td class="kecil">
                            <?php if ($slotPl === null): ?>
                                <span class="pil pil-kurang">tidak ada slot</span>
                                <div class="mut">Jabatan Anda tidak termasuk penilai Tahun Keempat pada madrasah
                                    <?= e(status_lembaga_label($pl['status_lembaga'])) ?>.</div>
                            <?php elseif ($sudahPilih): ?>
                                <b><?= e($slotPl['label']) ?></b> <span class="pil pil-unggul">Anda</span>
                            <?php elseif ($pl['slot_pemegang']): ?>
                                <b><?= e($slotPl['label']) ?></b>
                                <div class="mut">sudah diisi <?= e((string)($pl['slot_pemegang']['nama'] ?? 'penilai lain')) ?></div>
                            <?php else: ?>
                                <b><?= e($slotPl['label']) ?></b> <span class="pil pil-kosong">kosong</span>
                            <?php endif; ?>
                        </td>
                        <td class="kecil">
                            <?php if ($siap['siap']): ?>
                                <span class="pil pil-unggul">lengkap</span>
                            <?php else: ?>
                                <span class="pil pil-kurang">belum lengkap</span>
                            <?php endif; ?>
                            <?php foreach (($siap['tahun'] ?? []) as $t => $tt): ?>
                                <div class="penilai-tahun-cek <?= $tt['lengkap'] ? 'ok' : 'kurang' ?>">
                                    <?= $tt['lengkap'] ? '&#10003;' : '&#10007;' ?>
                                    <?= e(PKKM_TAHUN_LIST[$t]) ?>
                                    <span class="mut">(<?= (int)$tt['terisi']['1'] ?>+<?= (int)$tt['terisi']['2'] ?>/<?= (int)$tt['jumlah'] ?> butir)</span>
                                </div>
                            <?php endforeach; ?>
                            <?php if (!$boleh): ?>
                                <div class="mut">Belum bisa dipilih &mdash; lihat keterangan pada kolom
                                    <b>Slot Anda</b> dan <b>Penilaian Tahun 1&ndash;3</b>.</div>
                            <?php endif; ?>
                        </td>
                        <td class="tengah">
                            <input type="checkbox" name="pilih[<?= $sidp ?>]" value="<?= (int)PKKM_POSISI_TAMBAHAN ?>" id="pilihSek<?= $sidp ?>" data-pilih-cek<?= $sudahPilih ? ' checked' : '' ?><?= $boleh ? '' : ' disabled' ?><?= $boleh ? '' : ' data-terkunci="1"' ?> title="<?= $boleh ? 'Centang bila madrasah ini akan Anda nilai (Tahun Keempat)' : 'Belum bisa dipilih: slot/jabatan atau penilaian Tahun 1-3 belum memenuhi' ?>">
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
            <div class="form-aksi">
                <button class="btn" type="submit"><?= ikon('simpan', 16) ?> Simpan Pilihan Madrasah</button>
            </div>
            <?php endif; ?>
        </form>
        <?php endif; ?>
    </div>
    <?php endif; /* !$binaanSaya */ ?>

    <div class="card">
        <div class="card-kepala"><h2>Madrasah yang akan dinilai</h2></div>
        <?php if (!$tugas): ?>
            <p class="mut mb0">Belum ada madrasah yang Anda pilih.<?= $binaanSaya
                ? ' Binaan Anda muncul otomatis pada kartu <b>Madrasah Binaan Saya</b> di atas.'
                : ' Pilih madrasah pada kartu <b>Pilih Madrasah yang Akan Dinilai</b> di atas.' ?></p>
        <?php else: ?>
        <div class="geser"><table class="tbl">
            <thead><tr><th>No</th><th>Madrasah</th><th>Posisi Anda</th><th class="tengah">Penilaian Terisi</th>
                <th class="tengah">Nilai Tahun 4 Tahun</th><th>Aksi</th></tr></thead>
            <tbody>
            <?php foreach ($tugas as $i => $t):
                $sid = (int)$t['sekolah_id'];
                $posisi = (string)(int)$t['posisi'];
                $kategoriSaya = penilai_kategori_posisi($posisi);
                $tahunSaya = penilai_posisi_tambahan($posisi) ? [4] : array_keys(PKKM_TAHUN_LIST);
                $terisi = 0;
                foreach ($tahunSaya as $th) {
                    $r = nilai_penilaian($sid, $kategoriSaya, $posisi, $th);
                    if ($r && $r['terisi'] > 0) { $terisi++; }
                }
                $r4 = rekap_4tahun($sid);
                ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><b><?= e($t['nama']) ?></b>
                        <?php if (!(int)$t['aktif']): ?><div><span class="pil pil-kurang">Nonaktif</span></div><?php endif; ?></td>
                    <td><?= e(penilai_posisi_teks($posisi, status_lembaga_sekolah($sid))) ?></td>
                    <td class="tengah"><?= $terisi ?> / <?= count($tahunSaya) ?>
                        <?php if (penilai_posisi_tambahan($posisi)): ?><div class="kecil mut">Tahun Keempat</div><?php endif; ?></td>
                    <td class="tengah"><?= n2($r4['nilai']) ?> <span class="pil <?= e(predikat_kelas($r4['predikat'])) ?>"><?= e($r4['predikat']) ?></span></td>
                    <td><?php if ((int)$t['aktif']): ?>
                        <a class="btn btn-kecil" href="index.php?masuk=<?= $sid ?>">Buka</a>
                    <?php else: ?><span class="mut kecil">-</span><?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <?php endif; ?>
    </div>
    <div class="card">
        <h3>Yang Anda isi sebagai penilai</h3>
        <p class="mb0"><?= $jabatanPengawasSaya
            ? 'Penilaian kepala madrasah pada posisi Anda (Tahun Pertama s.d. Keempat) diisi pada menu <b>Penilaian Kepala Madrasah</b>.'
            : 'Sebagai ' . e(PKKM_PENILAI_TAMBAHAN_LABEL) . ', Anda hanya mengisi <b>Tahun Keempat</b> pada menu <b>Penilaian Kepala Madrasah</b>.' ?>
            Hasilnya otomatis tampil di menu Laporan &amp; Rekap PKKM.</p>
    </div>
    <?php
    page_bottom();
    exit;
}

// =====================================================================
// CABANG SUPER ADMIN tanpa konteks - ringkasan lintas madrasah
// =====================================================================
if ($u['role'] === 'super' && !$s) {
    $daftar = all('SELECT * FROM sekolah WHERE aktif = 1 ORDER BY nama');
    $jmlPenilaian = (int)val("SELECT COUNT(*) FROM penilaian WHERE status IS NOT NULL");
    $jmlButir = (int)val('SELECT COUNT(*) FROM penilaian_butir');
    $selesai = 0;
    $totalPenilaian = 0;
    foreach ($daftar as $d) {
        $pr = progres_sekolah((int)$d['id']);
        $selesai += $pr['selesai'];
        $totalPenilaian += $pr['total'];
    }
    page_top('Ringkasan Semua Madrasah', 'Super Admin &middot; semua madrasah dalam satu aplikasi.');
    ?>
    <div class="grid g4">
        <div class="card stat"><?= ikon('madrasah', 20) ?><div>
            <div class="stat-angka"><?= count($daftar) ?></div><div class="stat-label">Madrasah aktif</div></div></div>
        <div class="card stat"><?= ikon('nilai', 20) ?><div>
            <div class="stat-angka"><?= $jmlPenilaian ?></div><div class="stat-label">Penilaian dibuka</div></div></div>
        <div class="card stat"><?= ikon('rekap', 20) ?><div>
            <div class="stat-angka"><?= $selesai ?></div><div class="stat-label">Penilaian lengkap (<?= $totalPenilaian ?> slot)</div></div></div>
        <div class="card stat"><?= ikon('guru', 20) ?><div>
            <div class="stat-angka"><?= $jmlButir ?></div><div class="stat-label">Baris skor butir</div></div></div>
    </div>

    <div class="card">
        <div class="card-kepala"><h2>Daftar Madrasah</h2>
            <div class="aksi">
                <a class="btn btn-ghost btn-kecil" href="sekolah.php"><?= ikon('madrasah', 15) ?> Kelola Madrasah</a>
                <a class="btn btn-emas btn-kecil" href="tarik_supervisi.php"><?= ikon('unduh', 15) ?> Tarik dari Supervisi</a>
            </div>
        </div>
        <?php if (!$daftar): ?>
            <p class="mut mb0">Belum ada madrasah. Tarik lembaga berstatus aktif dari aplikasi
                Supervisi pada menu <a href="tarik_supervisi.php">Tarik dari Supervisi</a> &mdash;
                madrasah, kepala madrasah, dan pengawas masuk otomatis.</p>
        <?php else: ?>
        <div class="geser"><table class="tbl">
            <thead><tr><th>No</th><th>Madrasah</th><th>Kepala Madrasah</th>
                <th class="tengah">Progres Penilaian</th><th class="tengah">Nilai 4 Tahun</th><th>Aksi</th></tr></thead>
            <tbody>
            <?php foreach ($daftar as $i => $d):
                $sid = (int)$d['id'];
                $pr = progres_sekolah($sid);
                $r4 = rekap_4tahun($sid); ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><b><?= e($d['nama']) ?></b><div class="kecil mut"><?= e($d['kecamatan'] ?: '-') ?></div></td>
                    <td><?= e($d['kepala_nama'] ?: '-') ?></td>
                    <td class="tengah"><?= $pr['selesai'] ?>/<?= $pr['total'] ?> <span class="kecil mut">(<?= $pr['persen'] ?>%)</span></td>
                    <td class="tengah"><?= n2($r4['nilai']) ?> <span class="pil <?= e(predikat_kelas($r4['predikat'])) ?>"><?= e($r4['predikat']) ?></span></td>
                    <td><a class="btn btn-kecil" href="index.php?masuk=<?= $sid ?>">Buka</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <?php endif; ?>
    </div>
    <?php
    page_bottom();
    exit;
}

// =====================================================================
// DASHBOARD SATU MADRASAH (admin / super dalam konteks / pengawas dalam konteks)
// =====================================================================
if (!$s) {
    redirect('login.php');
}
$sid = (int)$s['id'];
$rekap = rekap_tahunan($sid);
$r4 = rekap_4tahun($sid);
$pr = progres_sekolah($sid);
$komp = rekap_komponen_tahun4($sid);
$posisiSaya = $u['role'] === 'pengawas' ? pengawas_posisi_di((int)$u['id'], $sid) : 0;

page_top('Dashboard Madrasah', e($s['nama']) . ' &middot; Tahun penilaian '
    . (($th1D = tahun_penilaian_tampil($sid, $s, 1)) !== '' ? $th1D : '-') . ' s.d. '
    . (($th4D = tahun_penilaian_tampil($sid, $s, 4)) !== '' ? $th4D : '-'));
?>
<div class="grid g4">
    <div class="card stat"><?= ikon('nilai', 20) ?><div>
        <div class="stat-angka"><?= $pr['selesai'] ?>/<?= $pr['total'] ?></div>
        <div class="stat-label">Penilaian pengawas lengkap</div></div></div>
    <div class="card stat"><?= ikon('lapor', 20) ?><div>
        <div class="stat-angka"><?= n2($rekap[4]['rerata']) ?></div>
        <div class="stat-label">Nilai Tahun Keempat (gabungan)</div></div></div>
    <div class="card stat"><?= ikon('rekap', 20) ?><div>
        <div class="stat-angka"><?= n2($r4['nilai']) ?></div>
        <div class="stat-label">Nilai kinerja 4 tahun</div></div></div>
    <div class="card stat"><?= ikon('guru', 20) ?><div>
        <div class="stat-angka"><span class="pil <?= e(predikat_kelas($r4['predikat'])) ?>"><?= e($r4['predikat']) ?></span></div>
        <div class="stat-label">Predikat <?= $r4['ada'] ?> tahun terisi</div></div></div>
</div>

<?php
// =====================================================================
// GRAFIK DASHBOARD KEPALA MADRASAH (permintaan pemilik):
//   1. grafik capaian nilai setiap tahun (+ garis NKKM),
//   2. grafik nilai berdasarkan tugas utama.
// Angka dihitung di server lalu dikirim sebagai JSON; grafik hanya pelengkap tampilan.
// =====================================================================
if ($u['role'] === 'admin'):
    $labelTahunGrafik = [];
    $nilaiTahunGrafik = [];
    foreach (array_keys(PKKM_TAHUN_LIST) as $t) {
        $labelTahunGrafik[] = pilihan_tahun((int)$t);
        $nilaiTahunGrafik[] = $rekap[$t]['rerata'] !== null ? round((float)$rekap[$t]['rerata'], 2) : null;
    }
    $nkknGrafik = $r4['nilai'] !== null ? round((float)$r4['nilai'], 2) : null;
    $labelTugas = [];
    $setTugas = [];
    foreach (array_keys(PKKM_TAHUN_LIST) as $t) {
        $setTugas[$t] = [];
    }
    foreach (instrumen_bagian() as $bk => $b) {
        $labelTugas[] = $bk . '. ' . $b['judul'];
        foreach (array_keys(PKKM_TAHUN_LIST) as $t) {
            $setTugas[$t][] = in_array($t, $b['tahun'], true) && ($rekap[$t]['rerata_bagian'][$bk] ?? null) !== null
                ? round((float)$rekap[$t]['rerata_bagian'][$bk], 2) : null;
        }
    }
    $dataGrafik = [
        'tahun' => [
            'labels' => $labelTahunGrafik,
            'nilai' => $nilaiTahunGrafik,
            'nkkn' => $nkknGrafik,
            'nkkn_garis' => $nkknGrafik !== null ? array_fill(0, count($labelTahunGrafik), $nkknGrafik) : [],
        ],
        'tugas' => [
            'labels' => $labelTugas,
            'datasets' => array_map(fn($t) => [
                'label' => pilihan_tahun((int)$t),
                'data' => $setTugas[$t],
            ], array_keys(PKKM_TAHUN_LIST)),
        ],
    ];
    ?>
<div class="grid g2" id="cardGrafikPKKM">
    <div class="card">
        <div class="card-kepala"><h2>Capaian Nilai Setiap Tahun</h2>
            <span class="pil <?= e(predikat_kelas($r4['predikat'])) ?>">NKKM <?= n2($r4['nilai']) ?>
                &middot; <?= e($r4['predikat']) ?></span>
        </div>
        <div class="grafik-kotak"><canvas id="chNilaiTahun" role="img"
            aria-label="Grafik capaian nilai kinerja kepala madrasah setiap tahun"></canvas></div>
        <p class="grafik-ket">Batang = nilai kinerja tahunan (rata-rata seluruh penilai pada tahun itu);
            garis putus-putus emas = NKKM (nilai empat tahunan).</p>
        <div class="geser"><table class="tbl tbl-padat mt8" id="tblGrafikTahun">
            <thead><tr><th>Tahun</th><th class="tengah">Nilai</th><th class="tengah">Predikat</th></tr></thead>
            <tbody>
            <?php foreach ($rekap as $t => $r): ?>
                <tr><td><?= e(pilihan_tahun((int)$t)) ?></td>
                    <td class="tengah"><?= n2($r['rerata']) ?></td>
                    <td class="tengah"><span class="pil <?= e(predikat_kelas($r['predikat'])) ?>"><?= e($r['predikat']) ?></span></td></tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot><tr><td>NKKM (empat tahunan)</td>
                <td class="tengah"><?= n2($r4['nilai']) ?></td>
                <td class="tengah"><?= e($r4['predikat']) ?></td></tr></tfoot>
        </table></div>
    </div>
    <div class="card">
        <div class="card-kepala"><h2>Nilai Berdasarkan Tugas Utama</h2>
            <span class="pil pil-unggul">A&ndash;E</span>
        </div>
        <div class="grafik-kotak"><canvas id="chTugasUtama" role="img"
            aria-label="Grafik nilai kepala madrasah berdasarkan tugas utama tiap tahun"></canvas></div>
        <p class="grafik-ket">Tiap kelompok = satu tugas utama; tiap batang = satu tahun penilaian.
            Tugas utama <b>E</b> hanya berlaku pada Tahun Keempat.</p>
        <div class="geser"><table class="tbl tbl-padat mt8" id="tblGrafikTugas">
            <thead><tr><th>Tugas Utama</th>
                <?php foreach (PKKM_TAHUN_LIST as $label): ?><th class="tengah"><?= e($label) ?></th><?php endforeach; ?>
            </tr></thead>
            <tbody>
            <?php foreach (instrumen_bagian() as $bk => $b): ?>
                <tr><td><b><?= e($bk) ?>.</b> <?= e($b['judul']) ?></td>
                    <?php foreach (array_keys(PKKM_TAHUN_LIST) as $t): ?>
                        <td class="tengah"><?= in_array($t, $b['tahun'], true)
                            ? n2($rekap[$t]['rerata_bagian'][$bk] ?? null) : '<span class="mut">-</span>' ?></td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    </div>
</div>
<script type="application/json" id="dataGrafikPKKM"><?= json_encode($dataGrafik,
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script src="assets/vendor/chart.umd.min.js?v=<?= e(APP_VERSION) ?>"></script>
<?php endif; ?>

<div class="card">
    <div class="baris-info">
        <div><span>NPSN:</span> <b><?= e($s['npsn'] ?: '-') ?></b></div>
        <div><span>NSM:</span> <b><?= e($s['nsm'] ?: '-') ?></b></div>
        <div><span>Kepala Madrasah:</span> <b><?= e($s['kepala_nama'] ?: '-') ?></b></div>
        <div><span>NIP:</span> <b><?= e($s['kepala_nip'] ?: '-') ?></b></div>
        <div><span>Kecamatan:</span> <b><?= e($s['kecamatan'] ?: '-') ?></b></div>
        <div><span>Tempat penilaian:</span> <b><?= e($s['tempat'] ?: '-') ?></b></div>
        <?php if ($u['role'] === 'pengawas'): ?>
            <div><span>Jabatan Anda:</span> <b><?= e(trim((string)($u['jabatan'] ?? '')) ?: 'belum diisi') ?></b></div>
            <div><span>Tahun yang dapat dinilai:</span> <b><?= e(penilai_tahun_label($u)) ?></b></div>
        <?php endif; ?>
    </div>
</div>

<?php if ($u['role'] !== 'pengawas'):
    $slotKepala = penilai_sekolah_slots($sid);
    $statusKepala = status_lembaga_sekolah($sid); ?>
<div class="card" id="cardPenilaiMadrasah">
    <div class="card-kepala"><h2>7 Penilai Tahun Keempat</h2>
        <div class="aksi">
            <span class="pil <?= $statusKepala === 'negeri' ? 'pil-baik' : 'pil-cukup' ?>">Madrasah
                <?= e(status_lembaga_label($statusKepala)) ?></span>
            <span class="kecil mut">Nama penilai diambil dari akun penilai yang memilih madrasah ini</span>
        </div>
    </div>
    <p class="ket">Susunan penilai Tahun Keempat mengikuti <b>Status Lembaga</b>:
        <b>Negeri</b> = Kabid, Kasie, Pengawas 1, Pengawas 2, Guru, Tendik, Komite;
        <b>Swasta</b> = Yayasan, Kasie, Pengawas 1, Pengawas 2, Guru, Tendik, Komite.</p>
    <div class="geser"><table class="tbl tbl-padat" id="tblPenilaiTambahanMadrasah">
        <thead><tr><th class="tengah" style="width:46px">No</th><th>Penilai (Slot)</th><th>Nama Penilai</th>
            <th>Jabatan / Instansi</th><th class="tengah">Nilai Tahun Keempat</th></tr></thead>
        <tbody>
        <?php $noT = 0; foreach ($slotKepala as $sl): $noT++; $akun = $sl['akun']; $rT = $sl['rinci']; ?>
            <tr>
                <td class="tengah"><?= $noT ?></td>
                <td><b><?= e($sl['label']) ?></b>
                    <?php if ($sl['kategori'] === 'pengawas'): ?>
                        <div class="kecil mut"><?= e($sl['jabatan']) ?></div>
                    <?php endif; ?></td>
                <td><?= $akun ? e((string)$akun['nama']) : '<span class="mut">belum diisi</span>' ?></td>
                <td class="kecil"><?= $akun
                    ? e((string)($akun['jabatan'] ?? '') !== '' ? $akun['jabatan'] : '-')
                        . ((string)($akun['instansi'] ?? '') !== '' ? '<div class="mut">' . e($akun['instansi']) . '</div>' : '')
                    : '<span class="mut">-</span>' ?></td>
                <td class="tengah"><?= $rT ? n2($rT['nilai']) : '<span class="mut">belum diisi</span>' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-kepala"><h2>Hasil Penilaian Pengawas I &amp; II per Tahun</h2>
        <div class="aksi">
            <a class="btn btn-kecil" href="penilaian.php"><?= ikon('nilai', 15) ?> Buka Penilaian</a>
            <a class="btn btn-ghost btn-kecil" href="laporan.php"><?= ikon('lapor', 15) ?> Laporan</a>
        </div>
    </div>
    <div class="geser"><table class="tbl">
        <thead><tr><th>Tahun</th><th class="tengah">Pengawas I</th><th class="tengah">Pengawas II</th>
            <th class="tengah">Rerata</th><th class="tengah">Predikat</th><th class="tengah">Status</th>
            <?php if ($u['role'] !== 'admin'): ?><th>Aksi</th><?php endif; ?></tr></thead>
        <tbody>
        <?php foreach ($rekap as $tahun => $r): ?>
            <tr>
                <td><b><?= e(pilihan_tahun((int)$tahun)) ?></b>
                    <div class="kecil mut"><?= e(($thI = tahun_penilaian_tampil($sid, $s, (int)$tahun)) !== '' ? $thI : 'tahun belum diisi') ?></div></td>
                <td class="tengah"><?= n2($r['kode']['1']['nilai'] ?? null) ?></td>
                <td class="tengah"><?= n2($r['kode']['2']['nilai'] ?? null) ?></td>
                <td class="tengah"><b><?= n2($r['rerata']) ?></b></td>
                <td class="tengah"><span class="pil <?= e(predikat_kelas($r['predikat'])) ?>"><?= e($r['predikat']) ?></span></td>
                <td class="tengah"><?= $r['lengkap'] ? '<span class="pil pil-unggul">Lengkap</span>' : '<span class="pil pil-kosong">Belum lengkap</span>' ?></td>
                <?php if ($u['role'] !== 'admin'): ?>
                <td class="nowrap">
                    <?php $adaTombol = 0;
                    foreach (['1', '2'] as $kode):
                        if (!boleh_isi_tahun($sid, $kode, (int)$tahun)) { continue; }
                        $adaTombol++; ?>
                        <a class="btn btn-kecil btn-ghost" href="penilaian_form.php?posisi=<?= $kode ?>&tahun=<?= (int)$tahun ?>">Isi P<?= $kode ?> Th <?= (int)$tahun ?></a>
                    <?php endforeach; ?>
                    <?php if ($adaTombol === 0 && $u['role'] === 'pengawas' && $posisiSaya > 0): ?>
                        <span class="mut kecil"><?= penilai_jabatan_pengawas()
                            ? 'hanya lihat'
                            : 'hanya Tahun Keempat' ?></span>
                    <?php endif; ?>
                </td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</div>

<?php $slotTahun4 = penilai_sekolah_slots($sid);
      $slotJml = penilai_sekolah_slot_jumlah($sid); ?>
<div class="grid g21">
    <div class="card" id="cardPenilaiTahun4">
        <div class="card-kepala"><h3>7 Penilai Tahun Keempat</h3>
            <span class="pil pil-unggul"><?= (int)$slotJml['isi'] ?>/<?= (int)$slotJml['total'] ?> terisi
                &middot; <?= (int)$slotJml['nilai'] ?> bernilai</span></div>
        <p class="ket">Madrasah <b><?= e(status_lembaga_label($komp['status'] ?? '')) ?></b> &mdash; penilai Tahun
            Keempat: Pengawas 1, Pengawas 2, dan
            <?= e(implode(', ', array_map(fn($sl) => $sl['label'],
                array_filter($slotTahun4, fn($sl) => $sl['kategori'] === PKKM_KATEGORI_TAMBAHAN)))) ?>.</p>
        <div class="geser"><table class="tbl tbl-padat" id="tblPenilaiTahun4">
            <thead><tr><th class="tengah" style="width:46px">No</th><th>Penilai</th><th>Nama</th>
                <th class="tengah">Butir</th><th class="tengah">Nilai</th></tr></thead>
            <tbody>
            <?php $noT4 = 0; foreach ($slotTahun4 as $sl): $noT4++;
                $akun = $sl['akun']; $rT = $sl['rinci']; ?>
                <tr>
                    <td class="tengah"><?= $noT4 ?></td>
                    <td><b><?= e($sl['label']) ?></b>
                        <?php if ($sl['kategori'] === 'pengawas'): ?>
                            <div class="kecil mut"><?= e($sl['jabatan']) ?></div>
                        <?php endif; ?></td>
                    <td class="kecil"><?= $akun
                        ? e((string)$akun['nama']) . ((string)($akun['jabatan'] ?? '') !== '' ? '<div class="mut">' . e($akun['jabatan']) . '</div>' : '')
                        : '<span class="mut">belum diisi</span>' ?></td>
                    <td class="tengah"><?= $rT ? (int)$rT['terisi'] . '/' . (int)$rT['jumlah'] : '<span class="mut">-</span>' ?></td>
                    <td class="tengah"><?= $rT ? n2($rT['nilai']) : '<span class="mut">-</span>' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <p class="kecil mut mt8 mb0">Nilai Tahun Keempat = rata-rata ketujuh penilai tersebut.
            Guru/Tendik/Komite/Kabid-Yayasan/Kasie masuk melalui akun penilainya masing-masing
            (menu <b>Akun Penilai</b> pada akun Super Admin).</p>
    </div>
    <div class="card">
        <h3>Komponen Tahun Keempat</h3>
        <div class="geser"><table class="tbl tbl-padat">
            <tbody>
            <?php foreach ($komp['komponen'] as $k): ?>
                <tr><td><?= e($k['label']) ?></td><td class="tengah"><?= n2($k['nilai']) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr><td>Rata-rata komponen</td><td class="tengah"><?= n2($komp['jumlah']) ?></td></tr>
                <tr><td>Predikat</td><td class="tengah"><span class="pil <?= e(predikat_kelas(predikat_dari($komp['jumlah']))) ?>"><?= e(predikat_dari($komp['jumlah'])) ?></span></td></tr>
            </tfoot>
        </table></div>
        <p class="kecil mut mt8 mb0">Nilai kumulatif Tahun Keempat dihitung dari rata-rata komponen yang sudah
            terisi, mengikuti tabel pada lembar rekap instrumen PKKM.</p>
    </div>
</div>
<?php if ($u['role'] === 'admin'): ?>
<div class="card">
    <h3>Yang perlu dilengkapi kepala madrasah</h3>
    <p class="mb0">Lengkapi <b>Input Data Madrasah</b> (identitas madrasah dan data kepala madrasah) agar kop
        lembar cetak dan identitas pada laporan/rekap terisi lengkap. Data <b>Penilai 1 &amp; 2</b> diisi oleh
        penilai masing-masing (mereka memilih madrasah ini pada Dashboard Penilai), sedangkan
        <b>tahun &amp; tanggal penilaian</b> ditetapkan Admin aplikasi. Nilai hanya dapat diisi oleh penilai
        yang memilih madrasah ini.</p>
    <div class="form-aksi"><a class="btn" href="madrasah.php"><?= ikon('madrasah', 16) ?> Input Data Madrasah</a></div>
</div>
<?php endif; ?>
<?php
page_bottom();
