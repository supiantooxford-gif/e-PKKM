<?php
/**
 * e-PKKM - HALAMAN DEPAN (LANDING): halaman pengisian untuk Super Admin.
 *
 * Tab: Slider Beranda, Beranda & Statistik, Fitur Aplikasi, Keunggulan, Testimoni,
 * Tentang Aplikasi, Kontak, dan Pesan Masuk (pesan dari formulir kontak halaman depan).
 * Halaman ini LINTAS MADRASAH (isi halaman depan dipakai bersama semua madrasah).
 */
require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/landing.php';
require_super();

$tabList = [
    'slider' => 'Slider Beranda',
    'beranda' => 'Beranda & Statistik',
    'fitur' => 'Fitur Aplikasi',
    'unggulan' => 'Keunggulan',
    'kontak' => 'Kontak',
    'pesan' => 'Pesan Masuk',
];
$daftarTab = ['slider', 'fitur', 'unggulan'];

/** Kunci teks yang boleh disimpan per tab (whitelist - kunci lain diabaikan). */
function landing_kunci_tab(string $tab): array
{
    return match ($tab) {
        'beranda' => ['label', 'stat_1_angka', 'stat_1_label', 'stat_2_angka', 'stat_2_label',
            'stat_3_angka', 'stat_3_label', 'stat_4_angka', 'stat_4_label'],
        'fitur' => ['fitur_judul', 'fitur_sub'],
        'unggulan' => ['unggulan_judul', 'unggulan_sub'],
        'kontak' => ['kontak_judul', 'kontak_sub', 'kontak_alamat', 'kontak_telepon', 'kontak_email',
            'kontak_jam', 'kontak_ajakan'],
        default => [],
    };
}

$tab = (string)($_GET['tab'] ?? 'slider');
if (!array_key_exists($tab, $tabList)) {
    $tab = 'slider';
}
// Jenis item SELALU mengikuti tab yang sedang dibuka. Tautan "Ubah" pada daftar item
// hanya membawa ?tab=…&edit=ID (tanpa ?item=…), sehingga menurunkan jenis dari tab
// membuat formulir benar-benar masuk mode UBAH. Tanpa ini $dataEdit selalu null →
// formulir tampil kosong dalam mode TAMBAH dan menekan "Simpan Item" membuat item
// BARU (duplikat), bukan memperbarui — itulah keluhan "tidak bisa mengedit ulang".
$item = in_array($tab, $daftarTab, true) ? $tab : '';
$editId = (int)($_GET['edit'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) {
        tolak_csrf();
    }
    $aksi = (string)($_POST['aksi'] ?? '');

    if ($aksi === 'simpan_teks') {
        $kunci = landing_kunci_tab((string)($_POST['tab'] ?? ''));
        $nilai = [];
        foreach ($kunci as $k) {
            $maks = ($k === 'tentang_isi') ? LANDING_ISI_MAKS : LANDING_TEKS_MAKS;
            $nilai[$k] = landing_potong((string)($_POST[$k] ?? ''), $maks);
        }
        $tabKirim = (string)($_POST['tab'] ?? '');
        if ($nilai) {
            landing_simpan_teks($nilai);
            flash('Isi bagian "' . ($tabList[$tabKirim] ?? 'Halaman Depan') . '" tersimpan.');
        }
        redirect('landing.php?tab=' . urlencode($tabKirim));
    }

    if ($aksi === 'item_tambah' || $aksi === 'item_simpan') {
        $jenis = landing_jenis_sah((string)($_POST['jenis'] ?? ''));
        if ($jenis === '') {
            flash('Jenis daftar tidak dikenal.', 'err');
            redirect('landing.php?tab=slider');
        }
        $id = (int)($_POST['id'] ?? 0);
        if ($jenis === 'testimoni') {
            $d = [
                'nama' => landing_potong((string)($_POST['nama'] ?? ''), LANDING_NAMA_MAKS),
                'jabatan' => landing_potong((string)($_POST['jabatan'] ?? ''), LANDING_NAMA_MAKS),
                'isi' => landing_potong((string)($_POST['isi'] ?? ''), LANDING_TEKS_MAKS),
                'aktif' => isset($_POST['aktif']) ? 1 : 0,
            ];
            if ($d['nama'] === '' || $d['isi'] === '') {
                flash('Nama dan isi testimoni wajib diisi.', 'err');
                redirect('landing.php?tab=testimoni');
            }
        } elseif ($jenis === 'slider') {
            $d = [
                'judul' => landing_potong((string)($_POST['judul'] ?? ''), LANDING_JUDUL_MAKS),
                'keterangan' => landing_potong((string)($_POST['keterangan'] ?? ''), LANDING_TEKS_MAKS),
                'aktif' => isset($_POST['aktif']) ? 1 : 0,
            ];
            if ($d['judul'] === '') {
                flash('Judul slide wajib diisi.', 'err');
                redirect('landing.php?tab=slider');
            }
        } else {
            $d = [
                'ikon' => landing_ikon_sah((string)($_POST['ikon'] ?? 'check')),
                'judul' => landing_potong((string)($_POST['judul'] ?? ''), LANDING_JUDUL_MAKS),
                'teks' => landing_potong((string)($_POST['teks'] ?? ''), LANDING_TEKS_MAKS),
                'aktif' => isset($_POST['aktif']) ? 1 : 0,
            ];
            if ($d['judul'] === '') {
                flash('Judul wajib diisi.', 'err');
                redirect('landing.php?tab=' . urlencode($jenis));
            }
        }

        if ($aksi === 'item_tambah') {
            landing_item_tambah($jenis, $d);
            $id = (int)db()->lastInsertId();
            flash('Item baru ditambahkan pada ' . landing_jenis_label($jenis) . '.');
        } else {
            if ($id <= 0 || !landing_item_by_id($jenis, $id)) {
                flash('Item tidak ditemukan.', 'err');
                redirect('landing.php?tab=' . urlencode($jenis));
            }
            landing_item_simpan($jenis, $id, $d);
            flash('Item diperbarui.');
        }

        // unggahan foto (slider & testimoni)
        if (landing_jenis_berfoto($jenis)) {
            if (isset($_POST['hapus_foto']) && (int)$_POST['hapus_foto'] === 1) {
                landing_foto_simpan($jenis, $id, null);
                flash('Foto dihapus.');
            } elseif (!empty($_FILES['foto']['name'])) {
                $hasil = landing_unggah_foto_berkas('foto');
                if ($hasil['ok']) {
                    landing_foto_simpan($jenis, $id, $hasil['url']);
                    flash('Foto berhasil diunggah.');
                } else {
                    flash('Foto tidak diunggah: ' . $hasil['pesan'], 'err');
                }
            }
        }
        redirect('landing.php?tab=' . urlencode($jenis));
    }

    if ($aksi === 'item_hapus') {
        $jenis = landing_jenis_sah((string)($_POST['jenis'] ?? ''));
        landing_item_hapus($jenis, (int)($_POST['id'] ?? 0));
        flash('Item dihapus.');
        redirect('landing.php?tab=' . urlencode($jenis));
    }

    if ($aksi === 'item_geser') {
        $jenis = landing_jenis_sah((string)($_POST['jenis'] ?? ''));
        landing_item_geser($jenis, (int)($_POST['id'] ?? 0), (int)($_POST['arah'] ?? 0));
        redirect('landing.php?tab=' . urlencode($jenis));
    }

    if ($aksi === 'item_aktif') {
        $jenis = landing_jenis_sah((string)($_POST['jenis'] ?? ''));
        $id = (int)($_POST['id'] ?? 0);
        $lama = landing_item_by_id($jenis, $id);
        if ($lama) {
            landing_item_simpan($jenis, $id, array_merge($lama, ['aktif' => (int)$lama['aktif'] ? 0 : 1]));
            flash('Item ' . ((int)$lama['aktif'] ? 'disembunyikan' : 'ditampilkan kembali') . ' pada halaman depan.');
        }
        redirect('landing.php?tab=' . urlencode($jenis));
    }

    if ($aksi === 'pesan_baca') {
        landing_pesan_tandai((int)($_POST['id'] ?? 0), (int)($_POST['dibaca'] ?? 1) === 1);
        redirect('landing.php?tab=pesan');
    }

    if ($aksi === 'pesan_hapus') {
        landing_pesan_hapus((int)($_POST['id'] ?? 0));
        flash('Pesan dihapus.');
        redirect('landing.php?tab=pesan');
    }

    if ($aksi === 'pesan_semua_baca') {
        q('UPDATE landing_pesan SET dibaca = 1 WHERE dibaca = 0');
        flash('Semua pesan ditandai sudah dibaca.');
        redirect('landing.php?tab=pesan');
    }

    flash('Aksi tidak dikenal.', 'err');
    redirect('landing.php');
}

$ringkas = landing_ringkas();
$pesanBaru = landing_pesan_baru();
$judulItem = $item;
$dataEdit = ($judulItem !== '' && $editId > 0) ? landing_item_by_id($judulItem, $editId) : null;

page_top('Halaman Depan (Landing)', 'Super Admin &middot; isi halaman depan aplikasi: beranda (slider beranda &amp; statistik), fitur, keunggulan, dan kontak.');
?>
<div class="card">
    <div class="baris-info">
        <div><span>Slider beranda:</span> <b><?= (int)$ringkas['slider']['aktif'] ?> slide aktif</b></div>
        <div><span>Fitur:</span> <b><?= (int)$ringkas['fitur']['aktif'] ?> aktif</b></div>
        <div><span>Keunggulan:</span> <b><?= (int)$ringkas['unggulan']['aktif'] ?> aktif</b></div>
        <div><span>Pesan belum dibaca:</span> <b><?= (int)$pesanBaru ?></b></div>
    </div>
    <div class="form-aksi">
        <a class="btn" href="login.php" target="_blank" rel="noopener"><?= ikon('buka', 16) ?> Lihat Halaman Depan</a>
    </div>
</div>

<div class="card">
    <div class="aksi" style="display:flex;gap:8px;flex-wrap:wrap">
        <?php foreach ($tabList as $k => $label): ?>
            <a class="btn btn-kecil <?= $k === $tab ? '' : 'btn-ghost' ?>" href="landing.php?tab=<?= e($k) ?>">
                <?= e($label) ?><?= ($k === 'pesan' && $pesanBaru > 0) ? ' (' . (int)$pesanBaru . ')' : '' ?>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<?php $kunciTab = landing_kunci_tab($tab); if ($kunciTab): ?>
<div class="card">
    <div class="card-kepala"><h2>Isi <?= e($tabList[$tab]) ?></h2></div>
    <form method="post" action="landing.php">
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="simpan_teks">
        <input type="hidden" name="tab" value="<?= e($tab) ?>">
        <?php if ($tab === 'kontak'): ?>
            <div class="form-2">
                <div><label for="kontak_judul">Judul bagian</label>
                    <input type="text" id="kontak_judul" name="kontak_judul" value="<?= e(landing_teks('kontak_judul')) ?>"></div>
                <div><label for="kontak_sub">Kalimat pengantar</label>
                    <input type="text" id="kontak_sub" name="kontak_sub" value="<?= e(landing_teks('kontak_sub')) ?>"></div>
                <div><label for="kontak_alamat">Alamat</label>
                    <input type="text" id="kontak_alamat" name="kontak_alamat" value="<?= e(landing_teks('kontak_alamat')) ?>"></div>
                <div><label for="kontak_telepon">Nomor WhatsApp/Telepon</label>
                    <input type="text" id="kontak_telepon" name="kontak_telepon" value="<?= e(landing_teks('kontak_telepon')) ?>"
                           placeholder="mis. 085394726089"></div>
                <div><label for="kontak_email">Email</label>
                    <input type="text" id="kontak_email" name="kontak_email" value="<?= e(landing_teks('kontak_email')) ?>"></div>
                <div><label for="kontak_jam">Jam layanan</label>
                    <input type="text" id="kontak_jam" name="kontak_jam" value="<?= e(landing_teks('kontak_jam')) ?>"></div>
            </div>
            <div class="form-baris">
                <label for="kontak_ajakan">Kalimat ajakan</label>
                <textarea id="kontak_ajakan" name="kontak_ajakan" style="min-height:90px"><?= e(landing_teks('kontak_ajakan')) ?></textarea>
            </div>
            <p class="ket">Tombol WhatsApp otomatis muncul bila nomor di atas diisi.</p>
        <?php elseif ($tab === 'beranda'): ?>
            <div class="form-baris">
                <label for="label">Label kecil di atas slider</label>
                <input type="text" id="label" name="label" value="<?= e(landing_teks('label')) ?>">
            </div>
            <h4>Empat kotak statistik</h4>
            <div class="geser"><table class="tbl tbl-padat">
                <thead><tr><th>Kotak</th><th>Angka</th><th>Label</th></tr></thead>
                <tbody>
                <?php for ($i = 1; $i <= 4; $i++): ?>
                    <tr><td><?= $i ?></td>
                        <td><input type="text" id="stat_<?= $i ?>_angka" name="stat_<?= $i ?>_angka"
                                   value="<?= e(landing_teks('stat_' . $i . '_angka')) ?>"></td>
                        <td><input type="text" id="stat_<?= $i ?>_label" name="stat_<?= $i ?>_label"
                                   value="<?= e(landing_teks('stat_' . $i . '_label')) ?>"></td></tr>
                <?php endfor; ?>
                </tbody>
            </table></div>
            <p class="ket">Kosongkan angka dan label bila kotak itu tidak ingin ditampilkan.</p>
        <?php else: ?>
            <div class="form-2">
                <div><label for="<?= e($tab) ?>_judul">Judul bagian</label>
                    <input type="text" id="<?= e($tab) ?>_judul" name="<?= e($tab) ?>_judul" value="<?= e(landing_teks($tab . '_judul')) ?>"></div>
                <div><label for="<?= e($tab) ?>_sub">Kalimat pengantar</label>
                    <input type="text" id="<?= e($tab) ?>_sub" name="<?= e($tab) ?>_sub" value="<?= e(landing_teks($tab . '_sub')) ?>"></div>
            </div>
        <?php endif; ?>
        <div class="form-aksi"><button class="btn" type="submit"><?= ikon('simpan', 16) ?> Simpan</button></div>
    </form>
</div>
<?php endif; ?>

<?php if (in_array($tab, $daftarTab, true)): ?>
    <div class="card">
        <div class="card-kepala">
            <h2><?= e(landing_jenis_label($tab)) ?> &mdash; Daftar Item</h2>
            <?php if (!$dataEdit): ?>
                <div class="aksi"><a class="btn btn-emas btn-kecil" href="landing.php?tab=<?= e($tab) ?>&item=<?= e($tab) ?>#formItem"><?= ikon('tambah', 15) ?> Tambah Item</a></div>
            <?php endif; ?>
        </div>
        <div class="geser"><table class="tbl">
            <thead><tr><th>No</th><th>Isi</th><th class="tengah">Urutan</th><th class="tengah">Tampil</th><th>Aksi</th></tr></thead>
            <tbody>
            <?php $daftarItem = landing_list($tab, false); foreach ($daftarItem as $i => $it): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td>
                        <?php if ($tab === 'slider'): ?>
                            <b><?= e($it['judul']) ?></b>
                            <div class="kecil mut"><?= e(potong_teks((string)$it['keterangan'], 120)) ?></div>
                            <div class="kecil"><?= trim((string)$it['foto']) !== '' ? 'berfoto' : 'tanpa foto (latar gradasi)' ?></div>
                        <?php elseif ($tab === 'testimoni'): ?>
                            <b><?= e($it['nama']) ?></b> <span class="kecil mut"><?= e($it['jabatan']) ?></span>
                            <div class="kecil">&ldquo;<?= e(potong_teks((string)$it['isi'], 140)) ?>&rdquo;</div>
                        <?php else: ?>
                            <span class="lp-ikon-kecil"><?= landing_ikon(landing_ikon_sah((string)$it['ikon']), 16) ?></span>
                            <b><?= e($it['judul']) ?></b>
                            <div class="kecil mut"><?= e(potong_teks((string)$it['teks'], 140)) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="tengah nowrap">
                        <form method="post" action="landing.php" style="display:inline">
                            <?= csrf_field() ?><input type="hidden" name="aksi" value="item_geser">
                            <input type="hidden" name="jenis" value="<?= e($tab) ?>"><input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
                            <input type="hidden" name="arah" value="-1">
                            <button class="btn btn-kecil btn-ghost" type="submit" aria-label="Naikkan">&and;</button>
                        </form>
                        <form method="post" action="landing.php" style="display:inline">
                            <?= csrf_field() ?><input type="hidden" name="aksi" value="item_geser">
                            <input type="hidden" name="jenis" value="<?= e($tab) ?>"><input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
                            <input type="hidden" name="arah" value="1">
                            <button class="btn btn-kecil btn-ghost" type="submit" aria-label="Turunkan">&or;</button>
                        </form>
                    </td>
                    <td class="tengah"><?= (int)$it['aktif'] ? '<span class="pil pil-unggul">Tampil</span>' : '<span class="pil pil-kosong">Disembunyikan</span>' ?></td>
                    <td class="nowrap">
                        <a class="btn btn-kecil btn-ghost" href="landing.php?tab=<?= e($tab) ?>&edit=<?= (int)$it['id'] ?>#formItem">Ubah</a>
                        <form method="post" action="landing.php" style="display:inline">
                            <?= csrf_field() ?><input type="hidden" name="aksi" value="item_aktif">
                            <input type="hidden" name="jenis" value="<?= e($tab) ?>"><input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
                            <button class="btn btn-kecil btn-ghost" type="submit"><?= (int)$it['aktif'] ? 'Sembunyikan' : 'Tampilkan' ?></button>
                        </form>
                        <form method="post" action="landing.php" style="display:inline">
                            <?= csrf_field() ?><input type="hidden" name="aksi" value="item_hapus">
                            <input type="hidden" name="jenis" value="<?= e($tab) ?>"><input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
                            <button class="btn btn-kecil btn-err" type="submit"
                                data-confirm="Hapus item ini dari halaman depan?">Hapus</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$daftarItem): ?>
                <tr><td colspan="5" class="mut">Belum ada item. Klik "Tambah Item".</td></tr>
            <?php endif; ?>
            </tbody>
        </table></div>
    </div>

    <div class="card" id="formItem">
        <div class="card-kepala"><h2><?= $dataEdit ? 'Ubah' : 'Tambah' ?> Item <?= e(landing_jenis_label($tab)) ?></h2>
            <?php if ($dataEdit): ?><div class="aksi"><a class="btn btn-ghost btn-kecil" href="landing.php?tab=<?= e($tab) ?>">Batal ubah</a></div><?php endif; ?>
        </div>
        <?php $berfoto = landing_jenis_berfoto($tab); ?>
        <form method="post" action="landing.php" <?= $berfoto ? 'enctype="multipart/form-data"' : '' ?>>
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="<?= $dataEdit ? 'item_simpan' : 'item_tambah' ?>">
            <input type="hidden" name="jenis" value="<?= e($tab) ?>">
            <?php if ($dataEdit): ?><input type="hidden" name="id" value="<?= (int)$dataEdit['id'] ?>"><?php endif; ?>

            <?php if ($tab === 'slider'): ?>
                <div class="form-baris"><label for="judul">Judul Slide</label>
                    <input type="text" id="judul" name="judul" required value="<?= e($dataEdit['judul'] ?? '') ?>"></div>
                <div class="form-baris"><label for="keterangan">Keterangan</label>
                    <textarea id="keterangan" name="keterangan" style="min-height:110px"><?= e($dataEdit['keterangan'] ?? '') ?></textarea></div>
                <div class="form-2">
                    <div><label for="slider_foto">Foto/Gambar Slide (opsional, PNG/JPG/WEBP/SVG maks 15 MB)</label>
                        <input type="file" id="slider_foto" name="foto" accept="image/*"></div>
                    <div><label>Foto saat ini</label>
                        <?php $fs = trim((string)($dataEdit['foto'] ?? '')); ?>
                        <?php if ($fs !== ''): ?>
                            <img src="<?= e($fs) ?>" alt="Foto slide" class="lp-pratinjau">
                            <label class="kecil"><input type="checkbox" name="hapus_foto" value="1"> Hapus foto</label>
                        <?php else: ?>
                            <p class="kecil mut mb0">Belum ada foto &mdash; slide tetap tampil dengan latar gradasi.</p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php elseif ($tab === 'testimoni'): ?>
                <div class="form-2">
                    <div><label for="nama">Nama</label>
                        <input type="text" id="nama" name="nama" required value="<?= e($dataEdit['nama'] ?? '') ?>"></div>
                    <div><label for="jabatan">Jabatan/Keterangan</label>
                        <input type="text" id="jabatan" name="jabatan" value="<?= e($dataEdit['jabatan'] ?? '') ?>"
                               placeholder="mis. Pengawas Madrasah"></div>
                </div>
                <div class="form-baris"><label for="isi">Isi testimoni</label>
                    <textarea id="isi" name="isi" required style="min-height:110px"><?= e($dataEdit['isi'] ?? '') ?></textarea></div>
                <div class="form-2">
                    <div><label for="testimoni_foto">Foto (opsional, maks 15 MB)</label>
                        <input type="file" id="testimoni_foto" name="foto" accept="image/*"></div>
                    <div><label>Foto saat ini</label>
                        <?php $ft = trim((string)($dataEdit['foto'] ?? '')); ?>
                        <?php if ($ft !== ''): ?>
                            <img src="<?= e($ft) ?>" alt="Foto testimoni" class="lp-pratinjau lp-pratinjau-bulat">
                            <label class="kecil"><input type="checkbox" name="hapus_foto" value="1"> Hapus foto</label>
                        <?php else: ?>
                            <p class="kecil mut mb0">Belum ada foto &mdash; memakai inisial nama.</p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="form-2">
                    <div><label for="ikon">Ikon</label>
                        <select id="ikon" name="ikon">
                            <?php foreach (landing_ikon_list() as $k => $label): ?>
                                <option value="<?= e($k) ?>" <?= ($dataEdit['ikon'] ?? '') === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select></div>
                    <div><label for="judul">Judul</label>
                        <input type="text" id="judul" name="judul" required value="<?= e($dataEdit['judul'] ?? '') ?>"></div>
                </div>
                <div class="form-baris"><label for="teks">Teks</label>
                    <textarea id="teks" name="teks" style="min-height:110px"><?= e($dataEdit['teks'] ?? '') ?></textarea></div>
            <?php endif; ?>

            <label class="kecil" style="font-size:.9rem">
                <input type="checkbox" name="aktif" value="1" <?= (!$dataEdit || (int)$dataEdit['aktif']) ? 'checked' : '' ?>>
                Tampilkan di halaman depan</label>
            <div class="form-aksi"><button class="btn" type="submit"><?= ikon('simpan', 16) ?> Simpan Item</button></div>
        </form>
    </div>
<?php endif; ?>

<?php if ($tab === 'pesan'): ?>
    <div class="card">
        <div class="card-kepala"><h2>Pesan Masuk dari Formulir Kontak</h2>
            <div class="aksi">
                <?php if ($pesanBaru > 0): ?>
                    <form method="post" action="landing.php" style="display:inline">
                        <?= csrf_field() ?><input type="hidden" name="aksi" value="pesan_semua_baca">
                        <button class="btn btn-kecil btn-ghost" type="submit">Tandai semua sudah dibaca</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        <?php $daftarPesan = landing_pesan_list(100); ?>
        <?php if (!$daftarPesan): ?>
            <p class="mut mb0">Belum ada pesan.</p>
        <?php else: ?>
            <div class="geser"><table class="tbl">
                <thead><tr><th>Waktu</th><th>Pengirim</th><th>Pesan</th><th class="tengah">Status</th><th>Aksi</th></tr></thead>
                <tbody>
                <?php foreach ($daftarPesan as $p): ?>
                    <tr>
                        <td class="nowrap"><?= e(waktu_id($p['dibuat'])) ?><div class="kecil mut"><?= e($p['ip']) ?></div></td>
                        <td><b><?= e($p['nama']) ?></b><div class="kecil mut"><?= e($p['kontak']) ?></div></td>
                        <td><?= nl2br(e($p['pesan'])) ?></td>
                        <td class="tengah"><?= (int)$p['dibaca'] ? '<span class="pil pil-unggul">Dibaca</span>' : '<span class="pil pil-cukup">Baru</span>' ?></td>
                        <td class="nowrap">
                            <form method="post" action="landing.php" style="display:inline">
                                <?= csrf_field() ?><input type="hidden" name="aksi" value="pesan_baca">
                                <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                                <input type="hidden" name="dibaca" value="<?= (int)$p['dibaca'] ? 0 : 1 ?>">
                                <button class="btn btn-kecil btn-ghost" type="submit"><?= (int)$p['dibaca'] ? 'Tandai baru' : 'Tandai dibaca' ?></button>
                            </form>
                            <form method="post" action="landing.php" style="display:inline">
                                <?= csrf_field() ?><input type="hidden" name="aksi" value="pesan_hapus">
                                <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                                <button class="btn btn-kecil btn-err" type="submit" data-confirm="Hapus pesan ini?">Hapus</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        <?php endif; ?>
    </div>
<?php endif; ?>
<?php
page_bottom();
