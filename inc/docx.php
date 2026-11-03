<?php
/**
 * e-PKKM - pembuat berkas Word (.docx) sederhana TANPA pustaka luar.
 *
 * Aplikasi ini tidak memakai ekstensi opsional (ZipArchive tidak tersedia di server), sedangkan
 * .docx pada dasarnya adalah berkas ZIP berisi XML. ZIP di sini ditulis sendiri dengan metode
 * STORE (tanpa kompresi) — bentuk ZIP yang sah dan dapat dibuka Word / LibreOffice / WPS.
 *
 * Isi dokumen disusun dari daftar BLOK:
 *   ['teks' => '...', 'tebal' => bool, 'tengah' => bool, 'miring' => bool, 'kanan' => bool,
 *    'ukuran' => int (setengah poin; 24 = 12pt), 'jarak' => int (dxa), 'indent' => int,
 *    'halaman' => bool,                                  // pemisah halaman
 *    'tabel' => [ [judul kolom...], [baris...], [opsi tabel...] ]]
 */

require_once __DIR__ . '/config.php';

if (!function_exists('docx_zip')) {
    /** Susun berkas ZIP dari daftar entri (nama => isi) — semuanya TANPA kompresi. */
    function docx_zip(array $entri): string
    {
        $lokal = '';
        $pusat = '';
        $offset = 0;
        $jam = getdate();
        $waktuDos = (($jam['hours'] << 11) | ($jam['minutes'] << 5) | ($jam['seconds'] >> 1)) & 0xFFFF;
        $tglDos = ((($jam['year'] - 1980) << 9) | ($jam['mon'] << 5) | $jam['mday']) & 0xFFFF;

        foreach ($entri as $nama => $isi) {
            $nama = (string)$nama;
            $isi = (string)$isi;
            $crc = crc32($isi);
            $panjang = strlen($isi);
            $kepala = pack('V', 0x04034b50) . pack('v', 20) . pack('v', 0) . pack('v', 0)
                . pack('v', $waktuDos) . pack('v', $tglDos) . pack('V', $crc)
                . pack('V', $panjang) . pack('V', $panjang) . pack('v', strlen($nama)) . pack('v', 0);
            $lokal .= $kepala . $nama . $isi;

            $pusat .= pack('V', 0x02014b50) . pack('v', 20) . pack('v', 20) . pack('v', 0) . pack('v', 0)
                . pack('v', $waktuDos) . pack('v', $tglDos) . pack('V', $crc)
                . pack('V', $panjang) . pack('V', $panjang) . pack('v', strlen($nama)) . pack('v', 0)
                . pack('v', 0) . pack('v', 0) . pack('v', 0) . pack('V', 0) . pack('V', $offset)
                . $nama;
            $offset += strlen($kepala) + strlen($nama) + $panjang;
        }
        $akhir = pack('V', 0x06054b50) . pack('v', 0) . pack('v', 0)
            . pack('v', count($entri)) . pack('v', count($entri))
            . pack('V', strlen($pusat)) . pack('V', strlen($lokal)) . pack('v', 0);
        return $lokal . $pusat . $akhir;
    }
}

if (!function_exists('docx_esc')) {
    /** Escape teks untuk XML Word. */
    function docx_esc(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('docx_par')) {
    /**
     * Satu paragraf Word. Baris baru (\n) menjadi <w:br/> di dalam paragraf yang sama.
     * $opsi: tebal, miring, tengah, kanan, ukuran (setengah poin), jarak (dxa), indent (dxa).
     */
    function docx_par(string $teks, array $opsi = []): string
    {
        $ukuran = (int)($opsi['ukuran'] ?? 23); // 23 = 11,5 pt
        $rpr = '<w:rPr><w:rFonts w:ascii="Times New Roman" w:hAnsi="Times New Roman"/>'
            . '<w:sz w:val="' . $ukuran . '"/><w:szCs w:val="' . $ukuran . '"/>'
            . (!empty($opsi['tebal']) ? '<w:b/>' : '') . (!empty($opsi['miring']) ? '<w:i/>' : '') . '</w:rPr>';
        $jc = !empty($opsi['tengah']) ? 'center' : (!empty($opsi['kanan']) ? 'right' : 'both');
        $pr = '<w:pPr><w:jc w:val="' . $jc . '"/>';
        $indent = (int)($opsi['indent'] ?? 0);
        if ($indent > 0) {
            $pr .= '<w:ind w:left="' . $indent . '"/>';
        }
        $pr .= '<w:spacing w:before="0" w:after="' . (int)($opsi['jarak'] ?? 40)
            . '" w:line="240" w:lineRule="auto"/></w:pPr>';

        $isi = '';
        foreach (explode("\n", $teks) as $i => $baris) {
            if ($i > 0) {
                $isi .= '<w:r><w:br/></w:r>';
            }
            $isi .= '<w:r>' . $rpr . '<w:t xml:space="preserve">' . docx_esc($baris) . '</w:t></w:r>';
        }
        if ($isi === '') {
            $isi = '<w:r>' . $rpr . '<w:t xml:space="preserve"></w:t></w:r>';
        }
        return '<w:p>' . $pr . $isi . '</w:p>';
    }
}

if (!function_exists('docx_sel')) {
    /**
     * Satu sel tabel Word. $sel boleh string (teks biasa) atau array:
     * ['teks'=>..., 'tebal'=>bool, 'tengah'=>bool, 'kanan'=>bool, 'span'=>int,
     *  'ukuran'=>int, 'latar'=>'D9D9D9'].
     */
    function docx_sel($sel, int $lebar = 0, bool $tengahDefault = false): string
    {
        $teks = is_array($sel) ? (string)($sel['teks'] ?? '') : (string)$sel;
        $tebal = is_array($sel) && !empty($sel['tebal']);
        $miring = is_array($sel) && !empty($sel['miring']);
        $tengah = is_array($sel) ? (!empty($sel['tengah']) || (!isset($sel['tengah']) && $tengahDefault)) : $tengahDefault;
        if (is_array($sel) && !empty($sel['kanan'])) {
            $tengah = false;
        }
        $span = is_array($sel) ? max(1, (int)($sel['span'] ?? 1)) : 1;
        $ukuran = is_array($sel) ? (int)($sel['ukuran'] ?? 20) : 20; // 20 = 10 pt
        $latar = is_array($sel) ? (string)($sel['latar'] ?? '') : '';

        $pr = '<w:tcPr>';
        if ($lebar > 0 && $span === 1) {
            $pr .= '<w:tcW w:w="' . $lebar . '" w:type="dxa"/>';
        }
        if ($span > 1) {
            $pr .= '<w:gridSpan w:val="' . $span . '"/>';
        }
        if (preg_match('/^[0-9A-Fa-f]{6}$/', $latar)) {
            $pr .= '<w:shd w:val="clear" w:color="auto" w:fill="' . strtoupper($latar) . '"/>';
        }
        $pr .= '<w:tcMar><w:top w:w="20" w:type="dxa"/><w:bottom w:w="20" w:type="dxa"/>'
            . '<w:left w:w="70" w:type="dxa"/><w:right w:w="70" w:type="dxa"/></w:tcMar>'
            . '<w:vAlign w:val="center"/></w:tcPr>';

        $rpr = '<w:rPr><w:rFonts w:ascii="Times New Roman" w:hAnsi="Times New Roman"/>'
            . '<w:sz w:val="' . $ukuran . '"/><w:szCs w:val="' . $ukuran . '"/>'
            . ($tebal ? '<w:b/>' : '') . ($miring ? '<w:i/>' : '') . '</w:rPr>';
        $jc = $tengah ? 'center' : ((is_array($sel) && !empty($sel['kanan'])) ? 'right' : 'left');

        $par = '';
        foreach (explode("\n", $teks) as $baris) {
            $par .= '<w:p><w:pPr><w:jc w:val="' . $jc . '"/><w:spacing w:before="0" w:after="0"'
                . ' w:line="240" w:lineRule="auto"/></w:pPr><w:r>' . $rpr
                . '<w:t xml:space="preserve">' . docx_esc($baris) . '</w:t></w:r></w:p>';
        }
        return '<w:tc>' . $pr . $par . '</w:tc>';
    }
}

if (!function_exists('docx_tabel')) {
    /**
     * Tabel Word. $kolom = daftar judul kolom, $baris = daftar baris (tiap baris daftar sel).
     * $opsi: lebar (daftar lebar dxa per kolom), border (bool, bawaan true),
     *        tengah (bool/daftar bool per kolom), kepala_latar (warna hex).
     */
    function docx_tabel(array $kolom, array $baris, array $opsi = []): string
    {
        $lebar = array_values((array)($opsi['lebar'] ?? []));
        $border = !isset($opsi['border']) || $opsi['border'];
        $tengah = (array)($opsi['tengah'] ?? []);
        $tengahDefault = isset($opsi['tengah_default']) ? (bool)$opsi['tengah_default'] : false;
        $kepalaLatar = (string)($opsi['kepala_latar'] ?? 'D9D9D9');
        $denganKepala = !isset($opsi['kepala']) || $opsi['kepala'];

        $grid = '<w:tblGrid>';
        foreach ($kolom as $i => $judul) {
            $w = (int)($lebar[$i] ?? 0);
            if ($w > 0) {
                $grid .= '<w:gridCol w:w="' . $w . '"/>';
            }
        }
        $grid .= '</w:tblGrid>';

        $tblPr = '<w:tblPr><w:tblW w:w="0" w:type="auto"/><w:tblLayout w:type="fixed"/>'
            . '<w:tblCellMar><w:top w:w="20" w:type="dxa"/><w:bottom w:w="20" w:type="dxa"/>'
            . '<w:left w:w="70" w:type="dxa"/><w:right w:w="70" w:type="dxa"/></w:tblCellMar>';
        if ($border) {
            $tblPr .= '<w:tblBorders>'
                . '<w:top w:val="single" w:sz="6" w:color="000000"/><w:left w:val="single" w:sz="6" w:color="000000"/>'
                . '<w:bottom w:val="single" w:sz="6" w:color="000000"/><w:right w:val="single" w:sz="6" w:color="000000"/>'
                . '<w:insideH w:val="single" w:sz="6" w:color="000000"/><w:insideV w:val="single" w:sz="6" w:color="000000"/>'
                . '</w:tblBorders>';
        } else {
            $tblPr .= '<w:tblBorders><w:top w:val="nil"/><w:left w:val="nil"/><w:bottom w:val="nil"/>'
                . '<w:right w:val="nil"/><w:insideH w:val="nil"/><w:insideV w:val="nil"/></w:tblBorders>';
        }
        $tblPr .= '</w:tblPr>';

        $isi = '';
        if ($denganKepala) {
            $isi .= '<w:tr>';
            foreach ($kolom as $i => $judul) {
                $isi .= docx_sel(['teks' => (string)$judul, 'tebal' => true, 'latar' => $kepalaLatar,
                    'tengah' => true], (int)($lebar[$i] ?? 0));
            }
            $isi .= '</w:tr>';
        }

        foreach ($baris as $r) {
            $isi .= '<w:tr>';
            foreach (array_values($r) as $i => $sel) {
                $t = $tengah[$i] ?? $tengahDefault;
                $isi .= docx_sel($sel, (int)($lebar[$i] ?? 0), (bool)$t);
            }
            $isi .= '</w:tr>';
        }

        return '<w:tbl>' . $tblPr . $grid . $isi . '</w:tbl>'
            . '<w:p><w:pPr><w:spacing w:after="60" w:line="120" w:lineRule="auto"/></w:pPr></w:p>';
    }
}

if (!function_exists('docx_bangun')) {
    /** Bangun dokumen .docx dari daftar blok. Halaman A4 POTRAIT, margin 2,5 cm. */
    function docx_bangun(array $blok, string $judul = ''): string
    {
        $isi = '';
        foreach ($blok as $b) {
            if (!empty($b['halaman'])) {
                $isi .= '<w:p><w:r><w:br w:type="page"/></w:r></w:p>';
                continue;
            }
            if (isset($b['tabel']) && is_array($b['tabel'])) {
                $isi .= docx_tabel($b['tabel'][0] ?? [], $b['tabel'][1] ?? [], $b['tabel'][2] ?? []);
                continue;
            }
            $isi .= docx_par((string)($b['teks'] ?? ''), $b);
        }
        $dokumen = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            . '<w:body>' . $isi
            . '<w:sectPr><w:pgSz w:w="11906" w:h="16838"/>'
            . '<w:pgMar w:top="1418" w:right="1418" w:bottom="1418" w:left="1418"'
            . ' w:header="708" w:footer="708" w:gutter="0"/>'
            . '</w:sectPr></w:body></w:document>';

        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
            . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            . '</Types>';
        $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            . '</Relationships>';
        $core = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties"'
            . ' xmlns:dc="http://purl.org/dc/elements/1.1/">'
            . '<dc:title>' . docx_esc($judul !== '' ? $judul : APP_NAME) . '</dc:title>'
            . '</cp:coreProperties>';

        return docx_zip([
            '[Content_Types].xml' => $contentTypes,
            '_rels/.rels'       => $rels,
            'docProps/core.xml' => $core,
            'word/document.xml' => $dokumen,
        ]);
    }
}

if (!function_exists('docx_kirim')) {
    /** Kirim berkas .docx ke peramban lalu hentikan skrip. */
    function docx_kirim(string $namaBerkas, string $bytes): void
    {
        if (!str_ends_with(strtolower($namaBerkas), '.docx')) {
            $namaBerkas .= '.docx';
        }
        // nama berkas aman (tanpa karakter yang mengganggu header)
        $namaBerkas = preg_replace('/[^A-Za-z0-9._-]+/', '-', $namaBerkas) ?: 'rekap-pkkm.docx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header('Content-Disposition: attachment; filename="' . $namaBerkas . '"');
        header('Content-Length: ' . strlen($bytes));
        header('Cache-Control: no-store');
        echo $bytes;
        exit;
    }
}
