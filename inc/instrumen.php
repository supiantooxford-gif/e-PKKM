<?php
/**
 * Data instrumen PKKM (Penilaian Kinerja Kepala Madrasah) - GLOBAL, satu daftar
 * untuk semua madrasah. Teks butir diambil apa adanya dari berkas asli
 * 'Aplikasi PKKM.xls' (sheet PGS_1).
 *
 * Bagian A-D berlaku Tahun Pertama s.d. Keempat; bagian E (Hasil Kinerja
 * Kepala Madrasah) hanya Tahun Keempat.
 * JANGAN menyunting teks di berkas ini secara manual - jaga kesamaannya dengan
 * instrumen resmi.
 */

if (!defined('PKKM_INSTRUMEN')) {
    define('PKKM_INSTRUMEN', 1);
}

/** Skor tertinggi tiap butir. */
const PKKM_SKOR_MAKS = 4;

/** Judul tiap bagian + tahun yang memakainya. */
function instrumen_bagian(): array
{
    return [
        'A' => ['judul' => 'Usaha Pengembangan Madrasah', 'tahun' => [1, 2, 3, 4]],
        'B' => ['judul' => 'Pelaksanaan Tugas Manajerial', 'tahun' => [1, 2, 3, 4]],
        'C' => ['judul' => 'Pengembangan Kewirausahaan', 'tahun' => [1, 2, 3, 4]],
        'D' => ['judul' => 'Supervisi Kepada Guru dan Tenaga Kependidikan', 'tahun' => [1, 2, 3, 4]],
        'E' => ['judul' => 'Hasil Kinerja Kepala Madrasah (Empat Tahunan)', 'tahun' => [4]],
    ];
}

/** Unsur Tugas Utama (kode => judul). */
function instrumen_unsur(): array
{
    return [
        '1.1' => 'Mengembangkan madrasah sesuai dengan kebutuhan.',
        '1.2' => 'Mengelola perubahan dan pengembangan madrasah menuju organisasi pembelajar yang efektif.',
        '1.3' => 'Mengelola hubungan antara madrasah dan masyarakat dalam rangka pencarian dukungan ide, sumber belajar, dan pembiayaan.',
        '1.4' => 'Mengelola proses pencapaian 8 SNP sesuai dengan arah dan tujuan Pendidikan nasional',
        '1.5' => 'Mengelola unit layanan khusus madrasah dalam mendukung kegiatan pembelajaran dan kegiatan peserta didik di madrasah.',
        '1.6' => 'Mengelola sistem informasi madrasah dalam mendukung penyusunan program dan pengambilan keputusan.',
        '1.7' => 'Memanfaatkan kemajuan teknologi informasi bagi peningkatan pembelajaran dan manajemen madrasah',
        '2.1' => 'Menyusun perencanaan madrasah untuk berbagai tingkatan perencanaan.',
        '2.2' => 'Memimpin madrasah dalam rangka pendayagunaan sumber daya madrasah secara optimal.',
        '2.3' => 'Menciptakan budaya dan iklim madrasah yang kondusif dan inovatif bagi pembelajaran peserta didik.',
        '2.4' => 'Mengelola guru dan staf dalam rangka pendayagunaan sumber daya manusia secara optimal.',
        '2.5' => 'Mengelola sarana dan prasarana madrasah dalam rangka pendayagunaan secara optimal',
        '2.6' => 'Mengelola peserta didik dalam rangka penerimaan peserta didik baru, dan penempatan dan pengembangan kapasitas peserta didik',
        '2.7' => 'Mengelola pengembangan kurikulum dan kegiatan pembelajaran sesuai dengan arah dan tujuan pendidikan nasional',
        '2.8' => 'Mengelola keuangan madrasah sesuai dengan prinsip pengelolaan yang akuntabel, transparan, dan efisien',
        '2.9' => 'Mengelola ketatausahaan madrasah dalam mendukung pencapaian tujuan madrasah',
        '2.10' => 'Melakukan monitoring, evaluasi, dan pelaporan pelaksanaan program kegiatan madrasah dengan prosedur yang tepat, serta merencanakan tindak lanjutnya.',
        '3.1' => 'Menciptakan inovasi yang bermanfaat dan tepat bagi pengembangan madrasah',
        '3.2' => 'Bekerja keras untuk mencapai keberhasilan madrasah sebagai organisasi pembelajaran yang efektif',
        '3.3' => 'Memiliki motivasi yang kuat untuk sukses dalam melaksanakan tugas pokok dan fungsinya sebagai pemimpin madrasah',
        '3.4' => 'Pantang menyerah dan selalu mencari solusi terbaik dalam menghadapi kendala yang dihadapi madrasah',
        '3.5' => 'Memiliki naluri kewirausahaan dalam mengelola kegiatan produksi/jasa madrasah sebagai sumber pembelajaran peserta didik',
        '4.1' => 'Menyusun program supervisi akademik dalam rangka peningkatan profesionalisme guru',
        '4.2' => 'Melaksanakan supervisi akademik terhadap guru dengan menggunakan pendekatan dan teknik supervisi yang tepat.',
        '4.3' => 'Menilai dan menindaklanjuti kegiatan supervisi akademik dalam rangka peningkatan profesionalisme guru.',
        '5.1' => 'Prestasi peserta didik',
        '5.2' => 'Prestasi Pendidik dan Tenaga Kependidikan',
        '5.3' => 'Prestasi Madrasah',
        '5.4' => 'Prestasi Kepala Madrasah',
    ];
}

/** Daftar butir: kode => bagian/unsur/indikator/data/tahun. */
function instrumen_butir(): array
{
    return [
        '1.1.1' => ['bagian' => 'A', 'unsur' => '1.1', 'indikator' => 'Mampu menggembangkan struktur organisasi yang sesuai dengan kebutuhan program.', 'data' => 'Dokumen struktur madrasah', 'tahun' => [1, 2, 3, 4]],
        '1.1.2' => ['bagian' => 'A', 'unsur' => '1.1', 'indikator' => 'Mampu menempatkan personalia yang sesuai dengan kebutuhan', 'data' => 'Dokumen uraian tugas personalia di madrasah', 'tahun' => [1, 2, 3, 4]],
        '1.1.3' => ['bagian' => 'A', 'unsur' => '1.1', 'indikator' => 'Mampu mengembangkan pedoman dan prosedur kerja organisasi madrasah', 'data' => 'Pedoman dan prosedur kerja organisasi madrasah', 'tahun' => [1, 2, 3, 4]],
        '1.2.1' => ['bagian' => 'A', 'unsur' => '1.2', 'indikator' => 'Mampu mengembangkan program baru untuk meningkatkan pencapaian target yang lebih tinggi.', 'data' => 'Program pengembangan madrasah mengandung target pencapaian pada indikator keunggulan khas satuan pendidikan, kerja sama tim, dan data realisasi target yang meningkat daripada pencapaian sebelumnya.', 'tahun' => [1, 2, 3, 4]],
        '1.2.2' => ['bagian' => 'A', 'unsur' => '1.2', 'indikator' => 'Mampu dan terampil dalam membangun tim kerja yang efektif untuk mendapatkan produk kinerja yang lebih unggul.', 'data' => 'Terdapat struktur organisasi yang dilengkapi dengan distribusi dan deskripsi pembagian tugas', 'tahun' => [1, 2, 3, 4]],
        '1.2.3' => ['bagian' => 'A', 'unsur' => '1.2', 'indikator' => 'Mampu menerapkan berbagai teknik pembaharuan dalam pengelolaan pembelajaran.', 'data' => 'Terdapat penerapan strategi pembaharuan dengan strategi yang terprogram', 'tahun' => [1, 2, 3, 4]],
        '1.2.4' => ['bagian' => 'A', 'unsur' => '1.2', 'indikator' => 'Mampu mengembangkan potensi dan meningkatkan prestasi madrasah', 'data' => 'Dokumen bukti perkembanganpotensi dan prestasi peserta didik, yang meningkat dari waktu ke waktu.', 'tahun' => [1, 2, 3, 4]],
        '1.3.1' => ['bagian' => 'A', 'unsur' => '1.3', 'indikator' => 'Merencanakan kerjasama dengan lembaga pemerintah, swasta dan masyarakat', 'data' => 'Data kerjasama dengan lembaga pemerintah, swasta, dan masyarakat', 'tahun' => [1, 2, 3, 4]],
        '1.3.2' => ['bagian' => 'A', 'unsur' => '1.3', 'indikator' => 'Melakukan pendekatan-pendekatan dalam rangka mendapatkan dukungan dari lembaga pemerintah, swasta dan masyarakat', 'data' => 'Dokumen pragram kerjasama dengan pemerintah, swasta, dan masyarakat', 'tahun' => [1, 2, 3, 4]],
        '1.3.3' => ['bagian' => 'A', 'unsur' => '1.3', 'indikator' => 'Memanfaatkan dan memelihara hubungan kerjasama dengan lembaga pemerintah, swasta dan masyarakat', 'data' => 'Data hasil kerjasama dengan lembega pemerintah, swasta, dan masyarakat', 'tahun' => [1, 2, 3, 4]],
        '1.4.1' => ['bagian' => 'A', 'unsur' => '1.4', 'indikator' => 'Mengaplikasikan pengembangan kurikulum yang mengacu kepada standar isi', 'data' => 'Dokumen analisis 8 SNP dalam hal standar ini', 'tahun' => [1, 2, 3, 4]],
        '1.4.2' => ['bagian' => 'A', 'unsur' => '1.4', 'indikator' => 'Mengaplikasikan pengembangan proses pembelajaran yang mengacu kepada standar proses', 'data' => 'Dokumen analisis 8 SNP dalam hal standar proses', 'tahun' => [1, 2, 3, 4]],
        '1.4.3' => ['bagian' => 'A', 'unsur' => '1.4', 'indikator' => 'Mengaplikasikan sistem penilaian pembelajaran yang mengacu kepada standar penilaian', 'data' => 'Dokumen analisis 8 SNP dalam hal standar penilaian', 'tahun' => [1, 2, 3, 4]],
        '1.4.4' => ['bagian' => 'A', 'unsur' => '1.4', 'indikator' => 'Melaksanakan penjaminan mutu pencapaian standar kompetensi lulusan', 'data' => 'Dokumen analisis 8 SNP dalam hal standar kompetensi lulusan', 'tahun' => [1, 2, 3, 4]],
        '1.5.1' => ['bagian' => 'A', 'unsur' => '1.5', 'indikator' => 'Mampu mengelola laboratorium madrasah agar dapat dimanfaatkan secara optimal untuk kepentingan pembelajaran peerta didik', 'data' => 'Adanya bukti pemanfaatan laboratorium dalam pembelajaran, jadwal, kegiatan, dll', 'tahun' => [1, 2, 3, 4]],
        '1.5.2' => ['bagian' => 'A', 'unsur' => '1.5', 'indikator' => 'Mampu mengelola perpustakaan madrasah dalam menyiiapkan sumber belajar yang diperlukan oleh peserta didik.', 'data' => 'Adanya bukti pemanfaatan lperpustakaan dalam pembelajaran, jadwal, kegiatan, dll', 'tahun' => [1, 2, 3, 4]],
        '1.5.3' => ['bagian' => 'A', 'unsur' => '1.5', 'indikator' => 'Mampu mengelola usaha madrasah untuk pembelajaran pesera didik dan pemasukan tambahan dana bagi madrasah.', 'data' => 'Adanya bukti kegiatan usaha madrasah dalam pembelajaran.', 'tahun' => [1, 2, 3, 4]],
        '1.5.4' => ['bagian' => 'A', 'unsur' => '1.5', 'indikator' => 'Mampu mengelola koperasi madrasah baik sebagai media praktik maupun sebagai sumber belajar bagi peserta didik.', 'data' => 'Adanya bukti pemanfaatan koperasi dalam pembelajaran, jadwal, kegiatan, dll', 'tahun' => [1, 2, 3, 4]],
        '1.6.1' => ['bagian' => 'A', 'unsur' => '1.6', 'indikator' => 'Memanfaatkan teknologi informasi dan komunikasi dalam manajemen madrasah', 'data' => 'Adanya bukti pemanfaatan teknologi indformasi dan komunikasi dalam manajemen madrasah', 'tahun' => [1, 2, 3, 4]],
        '1.6.2' => ['bagian' => 'A', 'unsur' => '1.6', 'indikator' => 'Memanfaatkan teknologi informasi dan komunikasi dalam pembelajaran, baik sebagai sumber belajar maupun sebagai alat/media pembelajaran.', 'data' => 'Adanya bukti pemanfaatan teknologi indformasi dan komunikasi sebagai sumber belajar dan media pembelajaran', 'tahun' => [1, 2, 3, 4]],
        '1.6.3' => ['bagian' => 'A', 'unsur' => '1.6', 'indikator' => 'Memanfaatkan teknologi teknologi informasi dan komunikasi dalam menjalin kerjasama dengan pihak lain.', 'data' => 'Adanya bukti pemanfaatan teknologi informasi dan komunikasi dalam menjalin kerjasama dengan pihak lain', 'tahun' => [1, 2, 3, 4]],
        '1.6.4' => ['bagian' => 'A', 'unsur' => '1.6', 'indikator' => 'Memanfaatkan teknologi teknologi informasi dan komunikasi dalam promosi program madrasah dan prestasi yang telah dicapai.', 'data' => 'Adanya bukti pemanfaatan teknologi informasi dan komunikasi dalam promosi program madrasah dan prestasi yang dicapai', 'tahun' => [1, 2, 3, 4]],
        '1.7.1' => ['bagian' => 'A', 'unsur' => '1.7', 'indikator' => 'Mampu mengembangkan sistem administrasi pengelolaan secara efektif dengan dukungan penerapan teknologi informasi dan komunikasi.', 'data' => 'Terdapat penerapan TIK (berbasis komputer, CD, jejaring intranet, internet) dalam pengelolaan administrasi administrasi persuratan, sarana prasarana, kepegawaian, kepeserta didikan, dan keuangan.', 'tahun' => [1, 2, 3, 4]],
        '1.7.2' => ['bagian' => 'A', 'unsur' => '1.7', 'indikator' => 'Mengelola adminsistasi pembelajaran secara efektif dengan dukungan penerapan teknologi informasi dan komunikasi.', 'data' => 'Model penerapan TIK dalam pengelolaan adminsitrasi kurikulum dan pembelajaran, misalnya, pengelolaan kurikulum berbasis komputer, intranet, dan internet.', 'tahun' => [1, 2, 3, 4]],
        '1.7.3' => ['bagian' => 'A', 'unsur' => '1.7', 'indikator' => 'Mampu mengembangkan sistem pengelolaan perpustakaan secara efektif dengan dukungan penerapan teknologi informasi dan komunikasi.', 'data' => 'Model pemanfaatan TIK dalam sistem pengelolaan perpustakaan berbasis komputer, intranet, atau internet.', 'tahun' => [1, 2, 3, 4]],
        '2.1.1' => ['bagian' => 'B', 'unsur' => '2.1', 'indikator' => 'Mampu mengembangkan RKJM, RKT/RKAM dengan program lainnya berdasarkan data hasil evaluasi dalam pemenuhan 8 SNP', 'data' => 'Dokumen RKJM, RKT/RKAM yang meliputi SNP', 'tahun' => [1, 2, 3, 4]],
        '2.1.2' => ['bagian' => 'B', 'unsur' => '2.1', 'indikator' => 'Mampu merumuskan visi-misi sebagai arah pengembangan program RKJM, RKT/RKAS dan program lainnya.', 'data' => 'Visi-misi madrasah merupakan rumusan hasil keputusan bersama.', 'tahun' => [1, 2, 3, 4]],
        '2.1.3' => ['bagian' => 'B', 'unsur' => '2.1', 'indikator' => 'Mampu menentukan strategi pencapaian tujuan madrasah, dilengkapi dengan indikator pencapaian yang terukur', 'data' => 'Dokumen program yang memuat strategi pencapaian tujuan madrasah', 'tahun' => [1, 2, 3, 4]],
        '2.1.4' => ['bagian' => 'B', 'unsur' => '2.1', 'indikator' => 'Mampu menyusun program dengan rencana evaluasi keterlaksanaan dan pencapaian program', 'data' => 'Dokumen rencana evaluasi keterlaksanaan dan pencapaian program.', 'tahun' => [1, 2, 3, 4]],
        '2.2.1' => ['bagian' => 'B', 'unsur' => '2.2', 'indikator' => 'Mampu memberi contoh berdisiplin; hadir tepat waktu, disiplin menggunakan waktu, dan tepat waktu mengakhiri pekerjaan.', 'data' => 'Dokumen daftar hadir semua kegiatan madrasah', 'tahun' => [1, 2, 3, 4]],
        '2.2.2' => ['bagian' => 'B', 'unsur' => '2.2', 'indikator' => 'Mampu melaksanakan peraturan sesuai dengan ketentuan yang berlaku', 'data' => 'Menjadi contoh dan mengarahkan guru, staf administrasi, dan peserta didik melaksanakan kegiatan sesuai dengan peraturan', 'tahun' => [1, 2, 3, 4]],
        '2.2.3' => ['bagian' => 'B', 'unsur' => '2.2', 'indikator' => 'Mampu menunjukkan keteladanan dalam memanfaatkan sumber daya secara efektif dan efisien.', 'data' => 'Memecahkan masalah madrasah secara bersama-sama, merencankan pemanfaatan sumber belajar dan sumber informasi, memantau penggunaan sumber daya, dan menilai pemanfaatan sumber daya', 'tahun' => [1, 2, 3, 4]],
        '2.2.4' => ['bagian' => 'B', 'unsur' => '2.2', 'indikator' => 'Mampu menunjukkan kedisiplinan sebagai insan pembelajar.', 'data' => 'Rajin membaca dan pendengar yang baik, mengekspresikan pikiran secara tertulis, mengkomunikasikan ilmu pengetahuan baru, dan menyediakan berbagai media untuk mengembangkan gagasan warga madrasah', 'tahun' => [1, 2, 3, 4]],
        '2.3.1' => ['bagian' => 'B', 'unsur' => '2.3', 'indikator' => 'Mampu menjadi contoh dan berbudaya mutu yang kompetitif dalam mendorong peningkatan prestasi akademik dan nonakademik peserta didik', 'data' => 'Dokumen peningkatan KKM, target hasil ulangan, hasil UN dan target keunggulan nonakademik peserta didik yang terprogram, terlaksana, dan meningkat hasilnya', 'tahun' => [1, 2, 3, 4]],
        '2.3.2' => ['bagian' => 'B', 'unsur' => '2.3', 'indikator' => 'Mampu melengkapi sarana dan prasarana untuk menciptakan suasana belajar kondusif dan inovatif bagi peserta didik', 'data' => 'Suasana lingkungan madrasah yang asri, bersih, rindang, aman, dan menyenangkan peserta didik', 'tahun' => [1, 2, 3, 4]],
        '2.3.3' => ['bagian' => 'B', 'unsur' => '2.3', 'indikator' => 'Mampu memfasilitasi kegiatan-kegiatan untuk meningkatkan budaya baca dan budaya tulis peserta didik.', 'data' => 'Data kunjungan perpustakaan, peminjaman buku oleh peserta didik, pembaharuan buku dan bahan bacaan, ketersediaan sumber belajar berbasis TIK, dan sarana publikasi karya tulis, dan mengembangkan kompetisi karya tulis peserta didik tingkat madrasah.', 'tahun' => [1, 2, 3, 4]],
        '2.3.4' => ['bagian' => 'B', 'unsur' => '2.3', 'indikator' => 'Mampu memfasilitasi kegiatan-kegiatan lomba di bidang akademik dan nonakademik bagi peserta didik', 'data' => 'Dokumen penyelenggaran kegiatan kompetisi yang dimulai dari tingkat madrasah, perolehan piagam penghargaan, piala, trofi perlombaan bidang akademik dan nonakademik.', 'tahun' => [1, 2, 3, 4]],
        '2.4.1' => ['bagian' => 'B', 'unsur' => '2.4', 'indikator' => 'Mampu menyusun perencanaan pengembangan pendidik dan tenaga kependidikan', 'data' => 'Dokumen program pembinaan pendidik dan tenaga kependidikan di madrasah', 'tahun' => [1, 2, 3, 4]],
        '2.4.2' => ['bagian' => 'B', 'unsur' => '2.4', 'indikator' => 'Mampu melakukan pembinaan berkala untuk meningkatkan mutu SDM madrasah', 'data' => 'Dokumen pelaksanaan kegiatan pembinaan guru.', 'tahun' => [1, 2, 3, 4]],
        '2.4.3' => ['bagian' => 'B', 'unsur' => '2.4', 'indikator' => 'Memfasilitasi guru dan staf administrasi untuk meningkatkan kegiatan pembinaan kompetensi', 'data' => 'Data dukungan Kepala Madrasah dalam memfasilitasi staf administrasi untuk meningkatkan mutu profesi.', 'tahun' => [1, 2, 3, 4]],
        '2.4.4' => ['bagian' => 'B', 'unsur' => '2.4', 'indikator' => 'Memantau dan menilai penerapan hasil pelatihan dalam pekerjaan di madrasah', 'data' => 'Dokumen program evaluasi pelatihan atau pengembangan profesi pendidik dan tenaga kependidikan', 'tahun' => [1, 2, 3, 4]],
        '2.5.1' => ['bagian' => 'B', 'unsur' => '2.5', 'indikator' => 'Mampu mengelola fasilitas prasarana, perabot dan sarana madrasah (gedung, bangunan, dan lahan meja, kursi, lemari, peralatan kantor, dan alat kebersihan)', 'data' => 'Data fasilitas prasarana, perabot, dan sarana marasah dikelola dengan baik', 'tahun' => [1, 2, 3, 4]],
        '2.5.2' => ['bagian' => 'B', 'unsur' => '2.5', 'indikator' => 'Mampu mengelola perpustakaan madrasah', 'data' => 'Data perpustakaan dikelola dengan baik', 'tahun' => [1, 2, 3, 4]],
        '2.5.3' => ['bagian' => 'B', 'unsur' => '2.5', 'indikator' => 'Mampu mengelola laboratirium madrasah', 'data' => 'Data laboratorium dikelola dengan baik', 'tahun' => [1, 2, 3, 4]],
        '2.5.4' => ['bagian' => 'B', 'unsur' => '2.5', 'indikator' => 'Mampu mengelola fasilitas penunjang madrasah lainnya (bengkel, toko, koperasi, kebun, dsb)', 'data' => 'Data fasilitas penunjang terkola dengan baik', 'tahun' => [1, 2, 3, 4]],
        '2.6.1' => ['bagian' => 'B', 'unsur' => '2.6', 'indikator' => 'Menyusun perencanaan penerimaan, pengelolaan dan pengembangan kompetensi peserta didik.', 'data' => 'Dokumen program penerimaan peserta didik baru, kriteria penerimaan peserta didik, data hasil analisis bekal ajar peserta didik awal,', 'tahun' => [1, 2, 3, 4]],
        '2.6.2' => ['bagian' => 'B', 'unsur' => '2.6', 'indikator' => 'Memiliki program pengembangan potensi diri dan prestasi peserta didik.', 'data' => 'Dokumen program pengembangan potensi diri dan prestasi peserta didik.', 'tahun' => [1, 2, 3, 4]],
        '2.6.3' => ['bagian' => 'B', 'unsur' => '2.6', 'indikator' => 'Memfasilitasi kegiatan-kegiatan untuk meningkatkan pembiasaan melalui penanaman nilai-nilai.', 'data' => 'Data program kegiatan akademik dan nonakademik melalui penanaman nilai - nilai.', 'tahun' => [1, 2, 3, 4]],
        '2.6.4' => ['bagian' => 'B', 'unsur' => '2.6', 'indikator' => 'Memfasilitasi kegiatan pengembangan diri bagi peserta didik, pendidik, dan tenaga kependidikan lainnya secara optimal', 'data' => 'Data keterlaksanaan program pengembangan diri peserta didik, pendidik, dan tenaga kependidikan', 'tahun' => [1, 2, 3, 4]],
        '2.7.1' => ['bagian' => 'B', 'unsur' => '2.7', 'indikator' => 'Mampu mengarahkan secara efektif dalam menerapkan prinsip-prinsip pengengembangan KTSP dalam kegiatan IHT, Workshop, Rapat Koordinasi, dan kegiatan MGMP/KKG.', 'data' => 'Dokumen hasil pengembangan kurikulum yang disusun melalui rapat kerja, IHT, Workshop, Rakor, atau kegiatan MGMP/KKG', 'tahun' => [1, 2, 3, 4]],
        '2.7.2' => ['bagian' => 'B', 'unsur' => '2.7', 'indikator' => 'Mampu mengendalikan pelaksanaan KTSP berlandaskan kalender pendidikan, menerbitkan surat keputusan pembagian tugas mengajar, dan menerapkan aturan akademik.', 'data' => 'Pelaksanaan kurikulum sesuai dengan kalender pendidikan tingkat madrasah, surat keputusan pembagian tugas mengajar, dan aturan akademik.', 'tahun' => [1, 2, 3, 4]],
        '2.7.3' => ['bagian' => 'B', 'unsur' => '2.7', 'indikator' => 'Memfasilitasi efektivitas tim kerja guru dalam rangka meningkatkan mutu pembelajaran.', 'data' => 'Bukti pelaksanaan kerja sama guru pada tingkat satuan pendidikan, antar satuan pendidikan dalam meningkatkan mutu perencanaan, proses, pembelajaran', 'tahun' => [1, 2, 3, 4]],
        '2.7.4' => ['bagian' => 'B', 'unsur' => '2.7', 'indikator' => 'Mampu mengembangkan pelayanan belajar yang inovatif melalui pengembangan perangkat dan sumber belajar yang terbarukan.', 'data' => 'Bukti penggunaan metode hasil pelatihan paling akhir, memanfaatkan teknologi informasi dan komunikasi, penggunaan alat peraga, teknik evaluasi baru yang menghasilkan produk belajar peserta didik yang dipublikasikan di lingkungan madrasah atau media lain', 'tahun' => [1, 2, 3, 4]],
        '2.7.5' => ['bagian' => 'B', 'unsur' => '2.7', 'indikator' => 'Memfasilitasi peserta didik dalam mengembangkan kolaborasi dan kompetisi bidang akademik dan nonakademik', 'data' => 'Data kegiatan kolaborasi dan kompetisi peserta didik tingkat madrasah, baik akademik dan non akademik.', 'tahun' => [1, 2, 3, 4]],
        '2.8.1' => ['bagian' => 'B', 'unsur' => '2.8', 'indikator' => 'Mampu merencanakan kebutuhan keuangan madrasah sesuai dengan rencana pengembangan madrasah, baik jangka pendek maupun jangka panjang', 'data' => 'Dokumen RKJM dan RKTM', 'tahun' => [1, 2, 3, 4]],
        '2.8.2' => ['bagian' => 'B', 'unsur' => '2.8', 'indikator' => 'Mampu mengupayaan sumber-sumber keuangan terutama dari luar madrasah dan dari unit usaha madrasah', 'data' => 'Dokumen RKJM dan RKTM', 'tahun' => [1, 2, 3, 4]],
        '2.8.3' => ['bagian' => 'B', 'unsur' => '2.8', 'indikator' => 'Mampu mengkoordinasikan pembelajaran keuangan sesuai dengan peraturan dan perundang-undangan berdasarkan asas prioritas dan efisiensi.', 'data' => 'Dokumen RKJM dan RKTM', 'tahun' => [1, 2, 3, 4]],
        '2.8.4' => ['bagian' => 'B', 'unsur' => '2.8', 'indikator' => 'Mampu membuat laporan dan evaluasi pengelolaan keuangan madrasah sesuai dengan prinsip efisien, tranparan, dan akuntabel.', 'data' => 'Dokumen RKJM dan RKTM', 'tahun' => [1, 2, 3, 4]],
        '2.9.1' => ['bagian' => 'B', 'unsur' => '2.9', 'indikator' => 'Mampu mengelola administrasi surat masuk dan surat keluar sesuai dengan pedoman persuratan yang berlaku', 'data' => 'Adanya bukti dokumen surat masuk dan keluar', 'tahun' => [1, 2, 3, 4]],
        '2.9.2' => ['bagian' => 'B', 'unsur' => '2.9', 'indikator' => 'Mampu mengelola administrasi madrasah yang meliputi administrasi akademik, kesiswaan, sarana/prasarana, keuangan, dan hubungan madrasah dengan masyarakat', 'data' => 'Adanya bukti dokumen administrasi madrasah', 'tahun' => [1, 2, 3, 4]],
        '2.9.3' => ['bagian' => 'B', 'unsur' => '2.9', 'indikator' => 'Mampu mengelola administrasi kearsipan madrasah baik arsip dinamis maupun arsip lainnya', 'data' => 'Adanya bukti dokumen administrasi madrasah', 'tahun' => [1, 2, 3, 4]],
        '2.9.4' => ['bagian' => 'B', 'unsur' => '2.9', 'indikator' => 'Mampu mengelola administrasi akreditasi madrasah sesuai dengan prinsip-prinsip tersedianya dokumen pendukung dan bukti fisik', 'data' => 'Adanya bukti dokumen administrasi madrasah', 'tahun' => [1, 2, 3, 4]],
        '2.10.1' => ['bagian' => 'B', 'unsur' => '2.10', 'indikator' => 'Menyusun standar kinerja program pendidikan yang dapat diukur dan dinilai', 'data' => 'Dokumen SKP yang terukur', 'tahun' => [1, 2, 3, 4]],
        '2.10.2' => ['bagian' => 'B', 'unsur' => '2.10', 'indikator' => 'Melakukan monitoring dan evaluasi kinerja program pendidikan dengan menggunakan teknik yang sesuai', 'data' => 'Dokumen pelaksanaan monitoring dan evaluasi yang sesuai', 'tahun' => [1, 2, 3, 4]],
        '2.10.3' => ['bagian' => 'B', 'unsur' => '2.10', 'indikator' => 'Menyusun laporan sesuai dengan standar pelaporan monitoring dan evaluasi', 'data' => 'Dokumen laporan pelaksanaan monitoring dan evaluasi', 'tahun' => [1, 2, 3, 4]],
        '2.10.4' => ['bagian' => 'B', 'unsur' => '2.10', 'indikator' => 'Menyusun laporan sesuai dengan standar pelaporan monitoring dan evaluasi', 'data' => 'Dokumen laporan pelaksanaan monitoring dan evaluasi', 'tahun' => [1, 2, 3, 4]],
        '2.10.5' => ['bagian' => 'B', 'unsur' => '2.10', 'indikator' => 'Merumuskan program tindak lanjut berdasarkan hasil evaluasi pelaksanaan program sebelumnya', 'data' => 'Dokumen program tindak lanjut berdasarkan hasil monitring dan evaluasi', 'tahun' => [1, 2, 3, 4]],
        '3.1.1' => ['bagian' => 'C', 'unsur' => '3.1', 'indikator' => 'Memahami dan menghayati arti dan tujuan perubahan (inovasi) madrasah', 'data' => 'Adanya bukti perubahan madrasah yang lebih baik dari tahun ke tahun', 'tahun' => [1, 2, 3, 4]],
        '3.1.2' => ['bagian' => 'C', 'unsur' => '3.1', 'indikator' => 'Menggunakan metode, teknik dan proses perubahan madrasah', 'data' => 'Adanya bukti strategi dalam perubahan madrasah yang lebih baik', 'tahun' => [1, 2, 3, 4]],
        '3.1.3' => ['bagian' => 'C', 'unsur' => '3.1', 'indikator' => 'Menumbuhkan iklim yang mendorong kebebasan berfikir untuk menciptakan kreativitas dan inovasi', 'data' => 'Adanya bukti iklim yang mendorong kebebasan berpikir kreatif dan inovatif', 'tahun' => [1, 2, 3, 4]],
        '3.1.4' => ['bagian' => 'C', 'unsur' => '3.1', 'indikator' => 'Mendorong warga madrasah untuk melakukan prakarsa/keberanian moral untuk melakukan hal-hal baru', 'data' => 'Adanya bukti warga madrasah yang memiliki keberanian utuk melakukan hal – hal baru', 'tahun' => [1, 2, 3, 4]],
        '3.2.1' => ['bagian' => 'C', 'unsur' => '3.2', 'indikator' => 'Mampu bertindak kratif dan inovatif dalam melaksanakan pekerjaan melalui cara berfikir dan cara bertindak', 'data' => 'Adanya bukti kegiatan yang kratif dan inovatif dalam melaksanakan pekerjaan melalui cara berfikir dan cara bertindak', 'tahun' => [1, 2, 3, 4]],
        '3.2.2' => ['bagian' => 'C', 'unsur' => '3.2', 'indikator' => 'Mampu memberdayakan potensi madrasah secara optimal kedalam berbagai kegiatan-kegiatan produktif yang menguntungkan madrasah', 'data' => 'Adanya bukti kegiatan pemberdayaanpotensi madrasah secara optimal kedalam berbagai kegiatan-kegiatan produktif yang menguntungkan madrasah', 'tahun' => [1, 2, 3, 4]],
        '3.2.3' => ['bagian' => 'C', 'unsur' => '3.2', 'indikator' => 'Mampu menumbuhkan jiwa kewirausahaan (kreatif, inovatif dan produktif) di kalangan warga madrasah', 'data' => 'Adanya bukti kegiatan yang membubuhkan jiwa kewirausahaan (kreatif, inovatif dan produktif) di kalangan warga madrasah', 'tahun' => [1, 2, 3, 4]],
        '3.2.4' => ['bagian' => 'C', 'unsur' => '3.2', 'indikator' => 'Mampu mencatat ide-ide baru, kemudian mengembangkannya', 'data' => 'Adanya bukti kegiatan mencatat ide-ide baru, kemudian mengembangkannya', 'tahun' => [1, 2, 3, 4]],
        '3.3.1' => ['bagian' => 'C', 'unsur' => '3.3', 'indikator' => 'Bersedia belajar dari orang lain', 'data' => 'Adanya bukti kemauan belajar dari orang lain', 'tahun' => [1, 2, 3, 4]],
        '3.3.2' => ['bagian' => 'C', 'unsur' => '3.3', 'indikator' => 'Ingin selalu melakukan yang terbaik', 'data' => 'Adanya bukti keinginan selalu elakukan yang terbaik', 'tahun' => [1, 2, 3, 4]],
        '3.3.3' => ['bagian' => 'C', 'unsur' => '3.3', 'indikator' => 'Menciptakan perubahan yang kuat', 'data' => 'Adanya bukti keinginan untuk melakkukan perubahan yang kuat', 'tahun' => [1, 2, 3, 4]],
        '3.4.1' => ['bagian' => 'C', 'unsur' => '3.4', 'indikator' => 'Mampu melibatkan tokoh agama, masyarakat, dan pemerintah dalam memecahkan masalah kelembagaan', 'data' => 'Adanya bukti kegiatan yang melibatkan tokoh agama, masyarakat dan pemerintah dalam memecahkan masalah kelembagaan', 'tahun' => [1, 2, 3, 4]],
        '3.4.2' => ['bagian' => 'C', 'unsur' => '3.4', 'indikator' => 'Mampu bersikap obyektif/tidak memihak dalam mengatasi konflik internal madrasah', 'data' => 'Adanya bukti kagiatan yang menunjukkan sikap obyektif/tidak memihak dalam mengatasi konflik internal madrasah', 'tahun' => [1, 2, 3, 4]],
        '3.4.3' => ['bagian' => 'C', 'unsur' => '3.4', 'indikator' => 'Mampu bersikap simpatik/tenggang rasa terhadap orang lain', 'data' => 'Adanya bukti sikap simpatik/ tenggang rasa terhadap orang lain', 'tahun' => [1, 2, 3, 4]],
        '3.5.1' => ['bagian' => 'C', 'unsur' => '3.5', 'indikator' => 'Mampu merencanakan kegiatan produksi /jasa sesuai dengan potensi madrasah', 'data' => 'Dokumen perencanaan kegiatan produksi sesuai potensi madrasah', 'tahun' => [1, 2, 3, 4]],
        '3.5.2' => ['bagian' => 'C', 'unsur' => '3.5', 'indikator' => 'Mampu membina kegiatan produksi /jasa sesuai dengan prinsip-prinsip pengelolaan yang rofessional dan akuntabel', 'data' => 'Adanya dokumen pembinaan kegiatan produksi /jasa sesuai dengan prinsip-prinsip pengelolaan yang professional dan akuntabel', 'tahun' => [1, 2, 3, 4]],
        '3.5.3' => ['bagian' => 'C', 'unsur' => '3.5', 'indikator' => 'Mampu melaksanakan pengawasan kegiatan produksi/jasa dan menyusun laporan', 'data' => 'Adanya dokumen pelaksanakan pengawasan kegiatan produksi/jasa dan menyusun laporan', 'tahun' => [1, 2, 3, 4]],
        '3.5.4' => ['bagian' => 'C', 'unsur' => '3.5', 'indikator' => 'Mampu mengembangkan kegiatan produksi/jasa dan pemasarannya', 'data' => 'Adanya dokumen pengembangan kegiatan produksi/jasa dan pemasarannya', 'tahun' => [1, 2, 3, 4]],
        '4.1.1' => ['bagian' => 'D', 'unsur' => '4.1', 'indikator' => 'Mengidentifikasi masalah yang guru hadapi dalam pelaksanaan pembelajaran.', 'data' => 'Terdapat rumusan masalah yang Kepala Madrasah peroleh dari pemantauan perencanaan, pelaksanaan, dan penilaian pembelajaran.', 'tahun' => [1, 2, 3, 4]],
        '4.1.2' => ['bagian' => 'D', 'unsur' => '4.1', 'indikator' => 'Mampu merumuskan tujuan yang dilengkapi dengan target pencapaian yang terukur.', 'data' => 'Terdapat rumusan tujuan supervisi yang dilengkapi dengan target pencapaian yang terukur.', 'tahun' => [1, 2, 3, 4]],
        '4.1.3' => ['bagian' => 'D', 'unsur' => '4.1', 'indikator' => 'Mampu mengembangkan instrumen supervisi.', 'data' => 'Instrumen yang digunakan relevan dengan target indikator pecapaian tujuan madrasah.', 'tahun' => [1, 2, 3, 4]],
        '4.2.1' => ['bagian' => 'D', 'unsur' => '4.2', 'indikator' => 'Mengadakan pertemuan awal untuk menjaring data rencana pembelajaran dan menetapkan fokus kegiatan supervisi.', 'data' => 'Terdapat data hasil pertemuan awal seperti; masalah, tujuan, fokus utama supervisi, dan instrumen yang disepakati', 'tahun' => [1, 2, 3, 4]],
        '4.2.2' => ['bagian' => 'D', 'unsur' => '4.2', 'indikator' => 'Melaksanakan kegiatan pemantauan pembelajaran dan membuat catatan yang objektif dan selektif sebagai bahan pemecahan masalah supervisi.', 'data' => 'Dokumen hasil observasi pembelajaran, lengkap, objektif dan selektif serta relevan dengan masalah yang menjadi fokus supervisi.', 'tahun' => [1, 2, 3, 4]],
        '4.2.3' => ['bagian' => 'D', 'unsur' => '4.2', 'indikator' => 'Melakukan pertemuan refleksi, menganalisis catatan hasil observasi, dan menyimpulkan hasil observasi', 'data' => 'Dokumen catatan pelaksanaan kegiatan, melaksanakan refleksi, himpunan data hasil superivisi, analisis data, penafsiran, penilaian keunggulan dan kelemahan, serta rekomendasi perbaikan.', 'tahun' => [1, 2, 3, 4]],
        '4.2.4' => ['bagian' => 'D', 'unsur' => '4.2', 'indikator' => 'Bersama guru menyusun rekomendasi tindaklanjut perbaikan dalam bentuk kegiatan analisis butir soal, remedial, dan pengayaan.', 'data' => 'Data tindak lanjut pelaksanaan supervisi penilaian, bukti analisis butir soal, kegiatan remedial dan pengayaan.', 'tahun' => [1, 2, 3, 4]],
        '4.3.1' => ['bagian' => 'D', 'unsur' => '4.3', 'indikator' => 'Memfasilitasi guru dalam merencanakan tindak lanjut perbaikan sistem penilaian hasil belajar.', 'data' => 'Terdapat bukti tindak lanjut perbaikan sistem penilaian hasil belajar', 'tahun' => [1, 2, 3, 4]],
        '4.3.2' => ['bagian' => 'D', 'unsur' => '4.3', 'indikator' => 'Mengecek ulang keterlaksanaan rekomendasi oleh guru', 'data' => 'dokumen rekomendasi perbaikan sistem penilaian hasil belajar secara berkala.', 'tahun' => [1, 2, 3, 4]],
        '4.3.3' => ['bagian' => 'D', 'unsur' => '4.3', 'indikator' => 'Melaksanakan pembinaan dan pengembangan guru sebagai tindak lanjut kegiatan supervisi.', 'data' => 'Terdapat bukti, berupa laporan tindak lanjut hasil supervisi sebagai dasar pelaksanaan pembinaan guru.', 'tahun' => [1, 2, 3, 4]],
        '4.3.4' => ['bagian' => 'D', 'unsur' => '4.3', 'indikator' => 'Menggunakan data hasil supervisi untuk pemetaan ketercapaian program sebagai dasar perbaikan siklus berikutnya.', 'data' => 'Hasil supervisi keterlaksanaan dan ketercapaian program sebagai bahan penilaian kinerja dan pemetaan profil madrasah sebagai dasar perencanaan siklus berikutnya.', 'tahun' => [1, 2, 3, 4]],
        '5.1.1' => ['bagian' => 'E', 'unsur' => '5.1', 'indikator' => 'Prestasi akademik peserta didik', 'data' => 'Terdapat prestai akademik peserta didik pada berbagai tingkat', 'tahun' => [4]],
        '5.1.2' => ['bagian' => 'E', 'unsur' => '5.1', 'indikator' => 'Prestasi non akademik peserta didik', 'data' => 'Terdapat prestai non akademik peserta didik pada berbagai tingkat', 'tahun' => [4]],
        '5.2.1' => ['bagian' => 'E', 'unsur' => '5.2', 'indikator' => 'Prestasi akademik pendidik dan tenaga kependidikan', 'data' => 'Terdapat prestai akademik pendidik dan tenaga kependidikan pada berbagai tingkat', 'tahun' => [4]],
        '5.2.2' => ['bagian' => 'E', 'unsur' => '5.2', 'indikator' => 'Prestasi non akademik pendidik dan tenaga kependidikan', 'data' => 'Terdapat prestai non akademik pendidik dan tenaga kependidikan pada berbagai tingkat', 'tahun' => [4]],
        '5.3.1' => ['bagian' => 'E', 'unsur' => '5.3', 'indikator' => 'Kelebihan prestasi akademik dari madrasah/sekolah lainnya', 'data' => 'Terdapat bukti keunggulam prestasi akademik madrasah', 'tahun' => [4]],
        '5.3.2' => ['bagian' => 'E', 'unsur' => '5.3', 'indikator' => 'Kelebihan prestasi non akademik dari madrasah/sekolah lainnya', 'data' => 'Terdapat bukti keunggulam prestasi non akademik madrasah', 'tahun' => [4]],
        '5.4.1' => ['bagian' => 'E', 'unsur' => '5.4', 'indikator' => 'Ijazah yang dimiliki kepala madrasah', 'data' => 'Terdapat bukti ijazah kepala madrasah', 'tahun' => [4]],
        '5.4.2' => ['bagian' => 'E', 'unsur' => '5.4', 'indikator' => 'Pendidikan dan pelatihan yang pernah diikuti oleh kepala madrasah', 'data' => 'Terdapat bukti keikutsertaan dalam diklat', 'tahun' => [4]],
        '5.4.3' => ['bagian' => 'E', 'unsur' => '5.4', 'indikator' => 'Penguasaan ICT kepala madrasah', 'data' => 'Terdapat bukti penguasaan ICT', 'tahun' => [4]],
        '5.4.4' => ['bagian' => 'E', 'unsur' => '5.4', 'indikator' => 'Prestasi yang diraih oleh kepala madrasah', 'data' => 'Terdapat bukti prestasi kepala madrasah', 'tahun' => [4]],
        '5.4.5' => ['bagian' => 'E', 'unsur' => '5.4', 'indikator' => 'Kegiatan penelitian kependidikan yang telah dilakukan oleh kepala madrasah', 'data' => 'Terdapat bukti karya penelitian kependidikan', 'tahun' => [4]],
        '5.4.6' => ['bagian' => 'E', 'unsur' => '5.4', 'indikator' => 'Kegiatan pelibatan komite dalam mendukung program madrasah', 'data' => 'Terdapat bukti pelibatan komite madrasah dalam mendukung program madrasah', 'tahun' => [4]],
        '5.4.7' => ['bagian' => 'E', 'unsur' => '5.4', 'indikator' => 'Kegiatan kemitraan dengan stakeholder pendidikan dalam meningkatkan kompetensi guru madrasah.', 'data' => 'Terdapat bukti kerjasama kemitraan untuk peningkatan kompetensi guru madrasah', 'tahun' => [4]],
    ];
}
