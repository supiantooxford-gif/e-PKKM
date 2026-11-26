<?php
/**
 * e-PKKM - penyusun lembar penilaian (dipakai penilaian Pengawas I/II dan penilaian
 * Guru/Tendik/Komite/Kabid/Kasi). Satu mesin untuk semua penilai.
 */

if (!function_exists('penilai_ambil')) {
    /** Baris data penilai (identitas yang tercetak pada lembar). */
    function penilai_ambil(int $sekolahId, string $kategori, string $kode): array
    {
        $r = one('SELECT * FROM penilai WHERE sekolah_id = ? AND kategori = ? AND kode = ?', [$sekolahId, $kategori, $kode]);
        return $r ?: [];
    }
}

if (!function_exists('penilai_label')) {
    /** Nama penilai sesuai kategori + kode. */
    function penilai_label(string $kategori, string $kode): string
    {
        if ($kategori === 'pengawas') {
            return PKKM_PENILAI_PENGAWAS[$kode] ?? 'Pengawas';
        }
        if ($kategori === PKKM_KATEGORI_TAMBAHAN) {
            // Penilai tambahan Tahun Keempat (permintaan pemilik: boleh lebih dari 2 penilai).
            return PKKM_PENILAI_TAMBAHAN_LABEL;
        }
        return PKKM_PENILAI_GTK[$kode] ?? 'Penilai';
    }
}

if (!function_exists('penilaian_simpan')) {
    /**
     * Simpan skor butir sebuah penilaian.
     * $skor = [kode_butir => 1..4] (butir di luar instrumen tahun tsb diabaikan).
     */
    function penilaian_simpan(int $sekolahId, string $kategori, string $kode, int $tahun, array $skor, bool $selesai): int
    {
        $valid = butir_tahun($tahun);
        $bersih = [];
        foreach ($skor as $b => $v) {
            $v = (int)$v;
            if (!isset($valid[$b]) || $v < 1 || $v > PKKM_SKOR_MAKS) {
                continue;
            }
            $bersih[$b] = $v;
        }
        mulai_transaksi();
        try {
            $p = penilaian_ambil($sekolahId, $kategori, $kode, $tahun, true);
            $pid = (int)$p['id'];
            q('DELETE FROM penilaian_butir WHERE penilaian_id = ?', [$pid]);
            $st = db()->prepare('INSERT INTO penilaian_butir (penilaian_id, butir, skor) VALUES (?, ?, ?)');
            foreach ($bersih as $b => $v) {
                $st->execute([$pid, (string)$b, (int)$v]);
            }
            $u = current_user();
            $lengkap = count($bersih) === count($valid) && count($valid) > 0;
            $status = ($selesai && $lengkap) ? 'selesai' : ($lengkap ? 'lengkap' : 'draft');
            q('UPDATE penilaian SET status = ?, diisi_oleh = ?, diisi_nama = ?, diisi_pada = ?, diubah = ? WHERE id = ?',
                [$status, $u['id'] ?? null, $u['nama'] ?? null, date('Y-m-d H:i:s'), date('Y-m-d H:i:s'), $pid]);
            selesai_transaksi(true);
            return $pid;
        } catch (Throwable $t) {
            selesai_transaksi(false);
            throw $t;
        }
    }
}

if (!function_exists('form_penilaian_html')) {
    /**
     * Lembar penilaian: satu tabel per tugas utama (A-E) dengan pilihan skor 1-4.
     * Setiap tabel dibungkus penanda `data-bagian` supaya ringkasan nilai hidup (JavaScript)
     * dapat menghitung ulang nilai tiap tugas utama secara langsung.
     * Bila $bolehIsi = false, pilihan skor hanya ditampilkan (dinonaktifkan).
     *
     * $opsi['dokumen'] (opsional) = [butir => baris dokumen] mengaktifkan kolom DOKUMEN di samping
     * "Data Yang Diharapkan" - dipakai penilai untuk melihat dokumen unggahan kepala madrasah,
     * menuliskan komentar per butir, lalu memberi skor (permintaan pemilik). Bila tidak diisi,
     * tabel tetap seperti semula (dipakai penilaian Guru & Tendik).
     */
    function form_penilaian_html(array $s, string $kategori, string $kode, int $tahun, bool $bolehIsi, array $skor,
                                array $opsi = []): string
    {
        $pakaiDok = array_key_exists('dokumen', $opsi);
        $dok = $pakaiDok ? (array)$opsi['dokumen'] : [];
        $kom = $pakaiDok ? (array)($opsi['komentar'] ?? []) : [];
        $posisi = (string)($opsi['posisi'] ?? '');
        $h = '';
        foreach (bagian_tahun($tahun) as $bk => $bagian) {
            $h .= '<h4 class="mt16">' . e($bk) . '. ' . e($bagian['judul'])
                . ' <span class="kecil mut">(' . count($bagian['butir']) . ' butir &middot; skor maks '
                . (int)$bagian['maks'] . ')</span></h4>';
            $h .= '<div class="geser"><table class="tbl tbl-padat tbl-nilai" data-bagian="' . e($bk) . '">';
            // Lebar kolom DIPATOK lewat colgroup + table-layout:fixed supaya tombol skor 1-4 berada
            // pada posisi yang sama di semua baris (baris judul unsur memakai colspan).
            $h .= '<colgroup><col class="butir-no"><col class="butir-kode"><col><col>'
                . ($pakaiDok ? '<col class="butir-dok">' : '') . '<col class="butir-skor"></colgroup>';
            $h .= '<thead><tr><th class="butir-no">No</th><th class="butir-kode">No. UBT</th>'
                . '<th>Indikator Kinerja</th><th>Data Yang Diharapkan</th>'
                . ($pakaiDok ? '<th class="butir-dok">Dokumen</th>' : '')
                . '<th class="butir-skor">Skor</th></tr></thead><tbody>';
            $no = 0;
            $unsurTampil = '';
            $kolomUnsur = $pakaiDok ? 5 : 4;
            foreach ($bagian['butir'] as $kodeButir) {
                $b = instrumen_butir()[$kodeButir];
                $no++;
                if ($b['unsur'] !== $unsurTampil) {
                    $unsurTampil = $b['unsur'];
                    $h .= '<tr><td class="butir-no"><b>' . e($unsurTampil) . '</b></td>'
                        . '<td colspan="' . $kolomUnsur . '"><b>' . e(instrumen_unsur()[$unsurTampil] ?? '') . '</b></td></tr>';
                }
                $h .= '<tr><td class="butir-no">' . $no . '</td>'
                    . '<td class="tengah kecil">' . e($kodeButir) . '</td>'
                    . '<td>' . e($b['indikator']) . '</td>'
                    . '<td class="kecil">' . e($b['data']) . '</td>';
                if ($pakaiDok) {
                    $h .= '<td class="butir-dok">' . form_dokumen_sel_html($kodeButir, $dok[$kodeButir] ?? [],
                            $kom, $posisi, $bolehIsi) . '</td>';
                }
                $h .= '<td>';
                $h .= '<div class="skor-sel">';
                for ($v = 1; $v <= PKKM_SKOR_MAKS; $v++) {
                    $cek = ((int)($skor[$kodeButir] ?? 0) === $v) ? ' checked' : '';
                    $h .= '<label class="skor-opt"><input type="radio" name="skor[' . e($kodeButir) . ']" value="' . $v . '"'
                        . $cek . ($bolehIsi ? '' : ' disabled') . '><span>' . $v . '</span></label>';
                }
                $h .= '</div></td></tr>';
            }
            $h .= '</tbody><tfoot><tr><td colspan="' . ($pakaiDok ? 5 : 4) . '">Jumlah Skor ' . e($bk) . '. ' . e($bagian['judul'])
                . ' &mdash; Nilai Kompetensi</td><td class="tengah kecil">Nilai: <b data-nilai="' . e($bk) . '">'
                . n2(nilai_bagian($skor, $bagian['butir'])) . '</b></td></tr></tfoot>';
            $h .= '</table></div>';
        }
        return $h;
    }
}

if (!function_exists('form_dokumen_sel_html')) {
    /**
     * Isi kolom DOKUMEN satu butir pada lembar penilaian:
     *  - daftar dokumen kepala madrasah: BERKAS unggahan atau TAUTAN Google Drive (v1.19.0),
     *  - komentar penilai (isian bila berhak mengisi, teks bila hanya melihat).
     */
    function form_dokumen_sel_html(string $butir, array $berkas, array $komentar, string $posisi, bool $bolehIsi): string
    {
        $h = '';
        if ($berkas) {
            $h .= '<ul class="dok-daftar dok-daftar-kecil">';
            foreach ($berkas as $d) {
                $tautanD = dokumen_adalah_tautan($d);
                $h .= '<li><span class="dok-pil' . ($tautanD ? ' dok-pil-drive' : '') . '">'
                    . ($tautanD ? 'DRIVE' : e(dok_label_berkas((string)$d['nama_berkas']))) . '</span>'
                    . '<a href="' . e($d['url']) . '" target="_blank" rel="noopener noreferrer"'
                    . ' title="' . e($tautanD ? $d['url'] : $d['nama_berkas']) . '">'
                    . e(potong_teks((string)$d['nama_berkas'], 26)) . '</a></li>';
            }
            $h .= '</ul>';
        } else {
            $h .= '<p class="kecil mut mb0">Belum ada dokumen.</p>';
        }

        $lama = komentar_butir_teks($komentar, $butir, $posisi);
        $lain = '';
        // komentar penilai LAIN pada butir ini — termasuk penilai tambahan Tahun Keempat
        $kodeLain = array_keys(PKKM_PENILAI_PENGAWAS);
        foreach (array_keys((array)($komentar[$butir] ?? [])) as $kodeT) {
            $kodeLain[] = (string)$kodeT;
        }
        $kodeLain = array_values(array_unique($kodeLain));
        sort($kodeLain, SORT_NUMERIC);
        foreach ($kodeLain as $p) {
            $p = (string)$p;
            if ($p === $posisi) {
                continue;
            }
            $t = komentar_butir_teks($komentar, $butir, $p);
            if ($t !== '') {
                $labelLain = penilai_posisi_tambahan($p) ? 'Penilai Tambahan' : 'Pengawas ' . $p;
                $lain .= '<div class="dok-komentar-lain"><b>' . e($labelLain) . ':</b> '
                    . nl2br(e(potong_teks($t, 240))) . '</div>';
            }
        }

        if ($bolehIsi) {
            $h .= '<textarea class="dok-komentar-input" name="komentar[' . e($butir) . ']" rows="2"
                       maxlength="' . (int)DOK_KOMENTAR_MAKS . '"
                       placeholder="Komentar penilai untuk butir ini">' . e($lama) . '</textarea>';
        } elseif ($lama !== '') {
            $h .= '<div class="dok-komentar"><b>Komentar:</b> ' . nl2br(e($lama)) . '</div>';
        }
        return $h . $lain;
    }
}

if (!function_exists('form_ringkasan_html')) {
    /** Ringkasan nilai hidup (diperbarui JavaScript, tetap terbaca tanpa JavaScript). */
    function form_ringkasan_html(int $tahun, array $skor): string
    {
        $r = nilai_rinci($skor, $tahun);
        $h = '<div class="ringkas-nilai">';
        foreach (bagian_tahun($tahun) as $bk => $bagian) {
            $h .= '<div><span>' . e($bk) . '. ' . e(potong_teks($bagian['judul'], 34)) . '</span>'
                . '<b data-nilai="' . e($bk) . '">' . n2($r['bagian'][$bk]) . '</b></div>';
        }
        $h .= '<div><span>Nilai tahunan</span><b data-nilai-total>' . n2($r['nilai']) . '</b></div>';
        $h .= '<div><span>Predikat</span><b><span class="pil ' . e(predikat_kelas(predikat_dari($r['nilai'])))
            . '" data-predikat>' . e(predikat_dari($r['nilai'])) . '</span></b></div>';
        $h .= '<div><span>Butir terisi</span><b data-terisi>' . (int)$r['terisi'] . ' dari ' . (int)$r['jumlah'] . ' butir terisi</b></div>';
        return $h . '</div>';
    }
}
