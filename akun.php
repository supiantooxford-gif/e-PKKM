<?php
/**
 * e-PKKM - Akun Saya: ubah nama & ganti kata sandi sendiri (semua peran).
 */
require_once __DIR__ . '/inc/init.php';
$u = require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) {
        tolak_csrf();
    }
    $aksi = (string)($_POST['aksi'] ?? '');

    if ($aksi === 'profil') {
        $nama = bersih_teks((string)($_POST['nama'] ?? ''), PKKM_TEKS_PENDEK);
        if ($nama === '') {
            flash('Nama wajib diisi.', 'err');
        } else {
            q('UPDATE users SET nama = ? WHERE id = ?', [$nama, (int)$u['id']]);
            flash('Nama Anda diperbarui.');
        }
        redirect('akun.php');
    }

    if ($aksi === 'sandi') {
        $lama = (string)($_POST['sandi_lama'] ?? '');
        $baru = (string)($_POST['sandi_baru'] ?? '');
        $ulang = (string)($_POST['sandi_ulang'] ?? '');
        $cek = one('SELECT sandi FROM users WHERE id = ?', [(int)$u['id']]);
        if (!$cek || !password_verify($lama, $cek['sandi'])) {
            flash('Kata sandi lama tidak sesuai.', 'err');
        } elseif (strlen($baru) < 6) {
            flash('Kata sandi baru minimal 6 karakter.', 'err');
        } elseif ($baru !== $ulang) {
            flash('Ulangi kata sandi baru dengan tepat.', 'err');
        } else {
            q('UPDATE users SET sandi = ? WHERE id = ?', [password_hash($baru, PASSWORD_DEFAULT), (int)$u['id']]);
            flash('Kata sandi berhasil diganti.');
        }
        redirect('akun.php');
    }

    flash('Aksi tidak dikenal.', 'err');
    redirect('akun.php');
}

$sekolah = current_sekolah();
page_top('Akun Saya', 'Perbarui nama tampilan dan kata sandi Anda.');
?>
<div class="grid g2">
    <div class="card">
        <div class="card-kepala"><h2>Profil</h2></div>
        <div class="baris-info">
            <div><span>Nama pengguna:</span> <b><?= e($u['username']) ?></b></div>
            <div><span>Peran:</span> <b><?= e(role_label()) ?></b></div>
            <div><span>Madrasah:</span> <b><?= e($sekolah['nama'] ?? 'Lintas madrasah') ?></b></div>
            <?php if (is_pengawas()): ?>
                <div><span>Jabatan:</span> <b><?= e(trim((string)($u['jabatan'] ?? '')) !== '' ? $u['jabatan'] : '-') ?></b></div>
                <div><span>NIP:</span> <b><?= e(trim((string)($u['nip'] ?? '')) !== '' ? $u['nip'] : '-') ?></b></div>
                <div><span>Unit Kerja:</span> <b><?= e(trim((string)($u['instansi'] ?? '')) !== '' ? $u['instansi'] : '-') ?></b></div>
                <div><span>Penugasan:</span> <b><?= count(pengawas_tugas((int)$u['id'])) ?> madrasah</b></div>
            <?php endif; ?>
        </div>
        <form method="post" action="akun.php" class="mt16">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="profil">
            <div class="form-baris">
                <label for="nama">Nama Tampilan</label>
                <input type="text" id="nama" name="nama" value="<?= e($u['nama']) ?>" required>
            </div>
            <div class="form-aksi"><button class="btn" type="submit"><?= ikon('simpan', 16) ?> Simpan Nama</button></div>
        </form>
    </div>

    <div class="card">
        <div class="card-kepala"><h2>Ganti Kata Sandi</h2></div>
        <form method="post" action="akun.php">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="sandi">
            <div class="form-baris"><label for="sandi_lama">Kata Sandi Lama</label>
                <input type="password" id="sandi_lama" name="sandi_lama" required></div>
            <div class="form-baris"><label for="sandi_baru">Kata Sandi Baru (min 6)</label>
                <input type="password" id="sandi_baru" name="sandi_baru" required></div>
            <div class="form-baris"><label for="sandi_ulang">Ulangi Kata Sandi Baru</label>
                <input type="password" id="sandi_ulang" name="sandi_ulang" required></div>
            <div class="form-aksi"><button class="btn btn-emas" type="submit"><?= ikon('kunci', 16) ?> Ganti Kata Sandi</button></div>
        </form>
    </div>
</div>
<?php
page_bottom();
