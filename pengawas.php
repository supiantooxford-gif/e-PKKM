<?php
/**
 * e-PKKM - AKUN PENILAI (khusus Super Admin).
 *
 * Permintaan pemilik (v1.8.0): menu "Akun Pengawas" diganti menjadi **Akun Penilai** dan isinya
 * SELURUH akun penilai &mdash; baik berjabatan Pengawas maupun jabatan lain (Kabid/Yayasan, Kasie,
 * Guru, Tendik, Komite).
 *
 * Aturan penugasan (7 penilai Tahun Keempat):
 *  - jabatan Pengawas memakai posisi Penilai 1 (ketua tim) atau Penilai 2 (anggota tim);
 *    satu posisi pada satu madrasah hanya boleh dipegang satu akun;
 *  - jabatan lain menempati SATU SLOT penilai Tahun Keempat menurut STATUS LEMBAGA madrasah
 *    (negeri: Kabid/Kasie/Guru/Tendik/Komite; swasta: Yayasan/Kasie/Guru/Tendik/Komite) dan hanya
 *    boleh memilih madrasah yang penilaian Tahun Pertama s.d. Ketiga-nya sudah lengkap.
 */
require_once __DIR__ . '/inc/init.php';
$u = require_super();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) {
        tolak_csrf();
    }
    $aksi = (string)($_POST['aksi'] ?? '');

    if ($aksi === 'simpan') {
        $id = (int)($_POST['id'] ?? 0);
        $nama = bersih_teks((string)($_POST['nama'] ?? ''), PKKM_TEKS_PENDEK);
        $username = trim((string)($_POST['username'] ?? ''));
        $sandi = (string)($_POST['sandi'] ?? '');
        $aktif = isset($_POST['aktif']) ? 1 : 0;
        $nip = bersih_teks((string)($_POST['nip'] ?? ''), PKKM_TEKS_PENDEK);
        $jabatan = trim((string)($_POST['jabatan'] ?? ''));
        $instansi = bersih_teks((string)($_POST['instansi'] ?? ''), PKKM_TEKS_PENDEK);
        $kabKota = bersih_teks((string)($_POST['kab_kota'] ?? ''), PKKM_TEKS_PENDEK);

        if ($nama === '' || $username === '') {
            flash('Nama dan nama pengguna wajib diisi.', 'err');
            redirect('pengawas.php' . ($id ? '?edit=' . $id : ''));
        }
        if ($jabatan !== '' && !jabatan_penilai_by_nama($jabatan)) {
            flash('Jabatan "' . $jabatan . '" tidak ada pada daftar jabatan penilai.', 'err');
            redirect('pengawas.php' . ($id ? '?edit=' . $id : ''));
        }
        if ($sandi !== '' && strlen($sandi) < 6) {
            flash('Kata sandi minimal 6 karakter.', 'err');
            redirect('pengawas.php' . ($id ? '?edit=' . $id : ''));
        }
        $kembar = val('SELECT id FROM users WHERE username = ? AND id <> ?', [$username, $id]);
        if ($kembar) {
            flash('Nama pengguna "' . $username . '" sudah dipakai.', 'err');
            redirect('pengawas.php' . ($id ? '?edit=' . $id : ''));
        }

        // -------- penugasan: madrasah => posisi --------
        $pilih = is_array($_POST['tugas'] ?? null) ? $_POST['tugas'] : [];
        $sekolahSah = [];
        foreach (all('SELECT id, nama FROM sekolah ORDER BY nama') as $sk) {
            $sekolahSah[(int)$sk['id']] = $sk['nama'];
        }
        $rencana = [];
        foreach ($pilih as $sid => $posisi) {
            $sid = (int)$sid;
            $posisi = (int)$posisi;
            // 0 = "tidak ditugaskan" (dilepas), 1/2 = Penilai 1 & 2 (jabatan Pengawas), >= 3 = slot
            // penilai Tahun Keempat. Kecocokan posisi dengan jabatan ditegakkan di
            // penugasan_penilai_simpan(). Nilai 0 WAJIB diteruskan supaya penugasan lama dilepas.
            if (!isset($sekolahSah[$sid]) || $posisi < 0) {
                continue;
            }
            $rencana[$sid] = $posisi;
        }

        mulai_transaksi();
        try {
            if ($id > 0) {
                $lama = one('SELECT * FROM users WHERE id = ? AND role = ?', [$id, 'pengawas']);
                if (!$lama) {
                    selesai_transaksi(false);
                    flash('Akun pengawas tidak ditemukan.', 'err');
                    redirect('pengawas.php');
                }
                q('UPDATE users SET nama = ?, username = ?, aktif = ?, nip = ?, jabatan = ?, instansi = ?, kab_kota = ?
                    WHERE id = ?', [$nama, $username, $aktif, $nip !== '' ? $nip : null,
                    $jabatan !== '' ? $jabatan : null, $instansi !== '' ? $instansi : null,
                    $kabKota !== '' ? $kabKota : null, $id]);
                if ($sandi !== '') {
                    q('UPDATE users SET sandi = ? WHERE id = ?', [password_hash($sandi, PASSWORD_DEFAULT), $id]);
                }
            } else {
                q('INSERT INTO users (sekolah_id, nama, username, sandi, role, aktif, nip, jabatan, instansi, kab_kota, asal, dibuat)
                   VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [$nama, $username, password_hash($sandi, PASSWORD_DEFAULT), 'pengawas', $aktif,
                     $nip !== '' ? $nip : null, $jabatan !== '' ? $jabatan : null,
                     $instansi !== '' ? $instansi : null, $kabKota !== '' ? $kabKota : null,
                     'manual', date('Y-m-d H:i:s')]);
                $id = (int)db()->lastInsertId();
            }
            selesai_transaksi(true);
        } catch (Throwable $t) {
            selesai_transaksi(false);
            flash('Gagal menyimpan: ' . $t->getMessage(), 'err');
            redirect('pengawas.php');
        }

        // penugasan madrasah + penyelarasan data penilai (dipakai lembar cetak & laporan)
        $hasil = penugasan_penilai_simpan($id, $rencana);
        flash('Akun penilai "' . $nama . '" disimpan &middot; ' . $hasil['jumlah'] . ' penugasan madrasah.'
            . ($hasil['ditolak'] ? ' Dilewati: ' . implode('; ', $hasil['ditolak']) . '.' : ''));
        redirect('pengawas.php');
    }

    if ($aksi === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $p = one('SELECT * FROM users WHERE id = ? AND role = ?', [$id, 'pengawas']);
        if ($p) {
            q('UPDATE users SET aktif = ? WHERE id = ?', [(int)$p['aktif'] ? 0 : 1, $id]);
            flash('Status akun "' . $p['nama'] . '" diperbarui.');
        }
        redirect('pengawas.php');
    }

    if ($aksi === 'hapus') {
        $id = (int)($_POST['id'] ?? 0);
        $p = one('SELECT * FROM users WHERE id = ? AND role = ?', [$id, 'pengawas']);
        if ($p && trim((string)($_POST['konfirmasi'] ?? '')) === 'HAPUS') {
            q('DELETE FROM users WHERE id = ?', [$id]);
            flash('Akun pengawas "' . $p['nama'] . '" dihapus (penugasan madrasahnya ikut dibersihkan).');
        } else {
            flash('Penghapusan dibatalkan: ketik HAPUS untuk menyetujui.', 'err');
        }
        redirect('pengawas.php');
    }

    flash('Aksi tidak dikenal.', 'err');
    redirect('pengawas.php');
}

$editId = (int)($_GET['edit'] ?? 0);
$editP = $editId > 0 ? one('SELECT * FROM users WHERE id = ? AND role = ?', [$editId, 'pengawas']) : null;
$tugasP = [];
if ($editP) {
    foreach (all('SELECT sekolah_id, posisi FROM pengawas_sekolah WHERE user_id = ?', [$editId]) as $t) {
        $tugasP[(int)$t['sekolah_id']] = (int)$t['posisi'];
    }
}
$sekolahList = all('SELECT * FROM sekolah ORDER BY aktif DESC, nama');
$paraPengawas = all('SELECT * FROM users WHERE role = ? ORDER BY nama', ['pengawas']);

$jabatanPengawasEdit = $editP ? penilai_jabatan_pengawas($editP) : true;
page_top('Akun Penilai',
    'Super Admin &middot; SELURUH akun penilai (pengawas maupun jabatan lain) beserta penugasan madrasahnya: '
    . '<b>Penilai 1 &amp; 2</b> untuk jabatan Pengawas, <b>satu slot penilai Tahun Keempat</b> '
    . '(Kabid/Yayasan, Kasie, Guru, Tendik, Komite) untuk jabatan lain &mdash; total 7 penilai per madrasah.', true);
?>
<div class="grid g-form">
    <div class="card">
        <div class="card-kepala"><h2><?= $editP ? 'Ubah Akun Penilai' : 'Tambah Akun Penilai' ?></h2>
            <?php if ($editP): ?><div class="aksi"><a class="btn btn-ghost btn-kecil" href="pengawas.php">Batal ubah</a></div><?php endif; ?>
        </div>
        <form method="post" action="pengawas.php">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="simpan">
            <?php if ($editP): ?><input type="hidden" name="id" value="<?= (int)$editP['id'] ?>"><?php endif; ?>
            <div class="form-2">
                <div><label for="pnama">Nama Penilai</label>
                    <input type="text" id="pnama" name="nama" required value="<?= e($editP['nama'] ?? '') ?>"
                           placeholder="mis. Herianto Dalanggo, M.Pd.I"></div>
                <div><label for="puser">Nama Pengguna</label>
                    <input type="text" id="puser" name="username" required value="<?= e($editP['username'] ?? '') ?>"></div>
                <div><label for="psandi">Kata Sandi <?= $editP ? '(kosongkan bila tidak diganti)' : '(min 6)' ?></label>
                    <input type="text" id="psandi" name="sandi" <?= $editP ? '' : 'required' ?>></div>
                <div><label for="paktif">Status</label>
                    <label class="kecil" style="font-size:.9rem">
                        <input type="checkbox" name="aktif" value="1" <?= (!$editP || (int)$editP['aktif']) ? 'checked' : '' ?>>
                        Akun aktif (boleh masuk)</label></div>
                <div><label for="pnip">NIP</label>
                    <input type="text" id="pnip" name="nip" value="<?= e($editP['nip'] ?? '') ?>"
                           placeholder="NIP/NUPTK (boleh dikosongkan)"></div>
                <div><label for="pjabatan">Jabatan</label>
                    <select id="pjabatan" name="jabatan">
                        <option value="">— belum dipilih —</option>
                        <?php
                        $jabatanPilih = jabatan_penilai_list(true);
                        $jabatanSekarang = (string)($editP['jabatan'] ?? '');
                        if ($jabatanSekarang !== '' && !jabatan_penilai_by_nama($jabatanSekarang)) {
                            $jabatanPilih[] = ['nama' => $jabatanSekarang, 'aktif' => 0];
                        }
                        foreach ($jabatanPilih as $jp): ?>
                            <option value="<?= e($jp['nama']) ?>" <?= $jabatanSekarang === (string)$jp['nama'] ? 'selected' : '' ?>>
                                <?= e($jp['nama']) ?><?= (int)$jp['aktif'] ? '' : ' (nonaktif)' ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="ket mb0">Daftar jabatan diatur di menu <a href="jabatan.php">Daftar Jabatan Penilai</a>.</p>
                </div>
                <div><label for="pinstansi">Unit Kerja / Instansi</label>
                    <input type="text" id="pinstansi" name="instansi" value="<?= e($editP['instansi'] ?? '') ?>"
                           placeholder="mis. Kantor Kemenag Kab. Gorontalo"></div>
                <div><label for="pkabkota">Kabupaten/Kota</label>
                    <input type="text" id="pkabkota" name="kab_kota" value="<?= e($editP['kab_kota'] ?? '') ?>"
                           placeholder="mis. Gorontalo"></div>
            </div>

            <h4 class="mt16">Penugasan Madrasah</h4>
            <p class="ket">Pilihan posisi mengikuti <b>jabatan yang tersimpan</b>. Bila jabatan baru diubah
                (atau akun baru dibuat), simpan dulu akunnya, lalu buka lagi lewat tombol
                <b>Ubah &amp; Binaan</b> untuk menetapkan penugasan madrasahnya.</p>
            <?php if ($jabatanPengawasEdit): ?>
                <p class="ket">Jabatan akun ini <b>Pengawas</b> (atau belum diisi), sehingga ia menilai
                    <b>Tahun Pertama s.d. Keempat</b> pada posisi <b>Penilai 1</b> atau <b>Penilai 2</b>.
                    Satu posisi pada satu madrasah hanya boleh dipegang satu akun.</p>
            <?php else: ?>
                <p class="ket">Jabatan akun ini <b>bukan Pengawas</b>, sehingga ia menempati <b>satu slot
                    Penilai Tahun Keempat</b> sesuai jabatannya &amp; <b>status lembaga</b> madrasah:
                    <b>Negeri</b> = Kabid, Kasie, Pengawas 1, Pengawas 2, Guru, Tendik, Komite;
                    <b>Swasta</b> = Yayasan, Kasie, Pengawas 1, Pengawas 2, Guru, Tendik, Komite.
                    Madrasah hanya bisa dipilih bila Tahun Pertama s.d. Ketiga sudah lengkap seluruh butir
                    dan slotnya belum diisi penilai lain.</p>
            <?php endif; ?>
            <div class="geser"><table class="tbl tbl-padat">
                <thead><tr><th>Madrasah</th>
                    <th style="width:330px"><?= $jabatanPengawasEdit ? 'Posisi' : 'Slot Penilai Tahun Keempat' ?></th></tr></thead>
                <tbody>
                <?php foreach ($sekolahList as $sk): $sid = (int)$sk['id'];
                    $dipilih = $tugasP[$sid] ?? 0;
                    $siapT4 = penilai_kesiapan_tahun4($sid);
                    $statusSk = status_lembaga_sekolah($sk);
                    $slotSk = penilai_slot_dari_jabatan((string)($editP['jabatan'] ?? ''), $statusSk);
                    $pemegangSlot = $slotSk === null ? null
                        : penilai_slot_pemegang($sid, $slotSk['kunci'], $editId); ?>
                    <tr>
                        <td><?= e($sk['nama']) ?> <?= status_lembaga_pil($statusSk) ?>
                            <?= (int)$sk['aktif'] ? '' : ' <span class="pil pil-kurang">Nonaktif</span>' ?>
                            <?php if (!$jabatanPengawasEdit): ?>
                                <div class="kecil mut">
                                    <?php if ($dipilih >= PKKM_POSISI_TAMBAHAN): ?>
                                        <span class="pil pil-cukup">sudah ditugaskan</span>
                                        <?= e($siapT4['teks']) ?>
                                    <?php elseif ($slotSk === null): ?>
                                        <span class="pil pil-kurang">tidak ada slot</span>
                                        jabatan ini tidak termasuk penilai Tahun Keempat madrasah
                                        <?= e(status_lembaga_label($statusSk)) ?>
                                    <?php elseif ($pemegangSlot): ?>
                                        <span class="pil pil-kurang">slot terisi</span>
                                        <?= e($slotSk['label']) ?> sudah diisi
                                        <?= e((string)($pemegangSlot['nama'] ?? 'penilai lain')) ?>
                                    <?php elseif ($siapT4['siap']): ?>
                                        <span class="pil pil-unggul">Tahun 1&ndash;3 lengkap</span>
                                        slot <b><?= e($slotSk['label']) ?></b> kosong
                                    <?php else: ?>
                                        <span class="pil pil-kurang">belum dapat dipilih</span>
                                        <?= e($siapT4['teks']) ?>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <select name="tugas[<?= $sid ?>]">
                                <option value="0">— tidak ditugaskan —</option>
                                <?php if ($jabatanPengawasEdit):
                                    foreach (['1' => 1, '2' => 2] as $pv => $pn):
                                    $lain = one('SELECT u.nama FROM pengawas_sekolah ps JOIN users u ON u.id = ps.user_id
                                                  WHERE ps.sekolah_id = ? AND ps.posisi = ? AND ps.user_id <> ?',
                                        [$sid, (int)$pv, $editId]);
                                    ?>
                                    <option value="<?= $pv ?>" <?= $dipilih === (int)$pv ? 'selected' : '' ?>
                                        <?= $lain ? 'disabled' : '' ?>>
                                        <?= e(penilai_posisi_teks((string)$pv)) ?> &mdash;
                                        <?= e(PKKM_PENILAI_PENGAWAS[(string)$pv]) ?><?= $lain ? ' — sudah: ' . e($lain['nama']) : '' ?>
                                    </option>
                                <?php endforeach;
                                else:
                                    $sudahT4 = $dipilih >= PKKM_POSISI_TAMBAHAN;
                                    $bolehT4 = $slotSk !== null
                                        && ($sudahT4 || ($siapT4['siap'] && $pemegangSlot === null));
                                    $labelOpsi = $slotSk !== null
                                        ? 'Slot ' . $slotSk['label'] . ' (Tahun Keempat)'
                                        : 'Tidak ada slot untuk jabatan ini pada madrasah '
                                            . status_lembaga_label($statusSk);
                                    ?>
                                    <option value="<?= (int)PKKM_POSISI_TAMBAHAN ?>"
                                        <?= $sudahT4 ? 'selected' : '' ?>
                                        <?= $bolehT4 ? '' : 'disabled' ?>><?= e($labelOpsi) ?></option>
                                <?php endif; ?>
                            </select>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
            <div class="form-aksi"><button class="btn" type="submit"><?= ikon('simpan', 16) ?> Simpan Akun &amp; Penugasan</button></div>
        </form>
    </div>

    <div class="card">
        <div class="card-kepala"><h2>Daftar Akun Penilai</h2>
            <span class="kecil mut">Seluruh akun penilai: pengawas &amp; jabatan lain</span>
            <div class="aksi"><a class="btn btn-kecil btn-ghost" href="jabatan.php"><?= ikon('nilai', 15) ?> Daftar Jabatan Penilai</a></div>
        </div>
        <?php if (!$paraPengawas): ?>
            <p class="mut mb0">Belum ada akun penilai. Penilai dapat mendaftar sendiri lewat halaman
                <a href="daftar_penilai.php" target="_blank" rel="noopener">Pendaftaran Penilai</a>,
                atau ditambahkan lewat formulir di samping.</p>
        <?php else: ?>
        <div class="geser"><table class="tbl" id="tblAkunPenilai">
            <thead><tr><th>Nama Penilai</th><th class="tengah">Jabatan</th>
                <th>Madrasah yang Dinilai &amp; Posisi</th><th class="tengah">Status</th>
                <th class="tengah aksi-kolom">Aksi</th></tr></thead>
            <tbody>
            <?php foreach ($paraPengawas as $p): $pid = (int)$p['id'];
                $jab = trim((string)($p['jabatan'] ?? ''));
                $jabPeng = penilai_jabatan_pengawas($p); ?>
                <tr>
                    <td><b><?= e($p['nama']) ?></b>
                        <?php if ((string)($p['asal'] ?? '') === 'pendaftaran'): ?>
                            <span class="pil pil-cukup" title="Mendaftar sendiri lewat halaman Pendaftaran Penilai">dari pendaftaran</span>
                        <?php endif; ?>
                        <div class="kecil mut"><?= e($p['username']) ?>
                            <?= trim((string)($p['nip'] ?? '')) !== '' ? ' &middot; NIP ' . e($p['nip']) : '' ?></div></td>
                    <td class="tengah">
                        <span class="penilai-jabatan-chip<?= $jabPeng ? ' jabatan-pengawas' : '' ?>"><?= e($jab !== '' ? $jab : 'belum diisi') ?></span>
                        <div class="kecil mut"><?= $jabPeng ? 'Tahun 1&ndash;4 (Penilai 1/2)'
                            : 'Tahun Keempat (7 penilai)' ?></div></td>
                    <td class="kecil">
                        <?php $tgs = all('SELECT ps.posisi, s.nama, s.status_lembaga
                                            FROM pengawas_sekolah ps JOIN sekolah s ON s.id = ps.sekolah_id
                                            WHERE ps.user_id = ? ORDER BY s.nama', [$pid]);
                        if (!$tgs): ?><span class="mut">belum memilih madrasah</span><?php else: ?>
                            <ul class="daftar-ringkas">
                            <?php foreach ($tgs as $t): $posT = (int)$t['posisi']; ?>
                                <li><?= e($t['nama']) ?> <?= status_lembaga_pil($t['status_lembaga'] ?? null) ?>
                                    <span class="pil <?= penilai_posisi_tambahan($posT) ? 'pil-cukup' : 'pil-baik' ?>"><?= e(penilai_posisi_teks((string)$posT, status_lembaga_sah($t['status_lembaga'] ?? ''))) ?></span></li>
                            <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </td>
                    <td class="tengah"><?= (int)$p['aktif']
                        ? '<span class="pil pil-unggul">Aktif</span>'
                        : '<span class="pil pil-kurang">Nonaktif</span>' ?></td>
                    <td class="tengah aksi-kolom">
                        <div class="row-aksi">
                            <a class="btn btn-kecil btn-ghost" href="pengawas.php?edit=<?= $pid ?>#">Ubah &amp; Binaan</a>
                            <form method="post" action="pengawas.php">
                                <?= csrf_field() ?><input type="hidden" name="aksi" value="toggle">
                                <input type="hidden" name="id" value="<?= $pid ?>">
                                <button class="btn btn-kecil btn-ghost" type="submit"><?= (int)$p['aktif'] ? 'Nonaktifkan' : 'Aktifkan' ?></button>
                            </form>
                            <form method="post" action="pengawas.php">
                                <?= csrf_field() ?><input type="hidden" name="aksi" value="hapus">
                                <input type="hidden" name="id" value="<?= $pid ?>">
                                <input type="hidden" name="konfirmasi" value="HAPUS">
                                <button class="btn btn-kecil btn-err" type="submit"
                                    data-confirm="Hapus akun pengawas <?= e($p['nama']) ?>?">Hapus</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <?php endif; ?>
    </div>
</div>
<?php
page_bottom();
