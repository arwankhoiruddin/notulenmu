<?php
function notulenmu_view_page() {
    if (!current_user_can('edit_posts')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }
    global $wpdb;
    $user_id = get_current_user_id();
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    $table_name = $wpdb->prefix . 'salammu_notulenmu';
    $notulen = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d AND user_id = %d", $id, $user_id));
    if (!$notulen) {
        echo '<div class="p-6 bg-white rounded shadow text-center">Data tidak ditemukan.</div>';
        return;
    }
    if ($notulen->image_path) {
        $upload_dir = wp_upload_dir();
        $image_path = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $notulen->image_path);
    }
    if ($notulen->lampiran) {
        $upload_dir = wp_upload_dir();
        $lampiran_path = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $notulen->lampiran);
    }
    $sifat_rapat = $notulen->sifat_rapat ? json_decode($notulen->sifat_rapat, true) : [];
    $tempat_rapat = $notulen->tempat_rapat ? $notulen->tempat_rapat : '';
    $peserta_rapat = $notulen->peserta_rapat ? json_decode($notulen->peserta_rapat, true) : [];
?>
<div class="max-w-2xl mx-auto p-6 mt-7 bg-white shadow-md rounded-lg border-x border-gray-300">
    <h1 class="text-2xl font-semibold mb-6 text-gray-700">Detail Notulen</h1>
    <div class="grid gap-6">
        <div>
            <span class="font-semibold">Tingkat:</span> <?php echo esc_html($notulen->tingkat); ?>
        </div>
        <div>
            <span class="font-semibold">Topik Rapat:</span> <?php echo esc_html($notulen->topik_rapat); ?>
        </div>
        <div>
            <span class="font-semibold">Tanggal Rapat:</span> <?php echo date('Y-m-d', strtotime($notulen->tanggal_rapat)); ?>
        </div>
        <div>
            <span class="font-semibold">Jam Mulai:</span> <?php echo esc_html($notulen->jam_mulai); ?>
        </div>
        <div>
            <span class="font-semibold">Jam Selesai:</span> <?php echo esc_html($notulen->jam_selesai); ?>
        </div>
        <div>
            <span class="font-semibold">Sifat Rapat:</span> <?php echo esc_html(implode(', ', $sifat_rapat)); ?>
        </div>
        <div>
            <span class="font-semibold">Tempat Rapat:</span> <?php echo esc_html($tempat_rapat); ?>
        </div>
        <div>
            <span class="font-semibold">Peserta Rapat:</span> <?php echo esc_html(implode(', ', $peserta_rapat)); ?>
        </div>
        <div>
            <span class="font-semibold">Rangkuman Rapat:</span><br>
            <div class="border p-3 rounded bg-gray-50 mt-1"><?php echo wp_kses_post($notulen->notulen_rapat); ?></div>
        </div>
        <?php if (!empty($image_path)) { ?>
        <div>
            <span class="font-semibold">Foto Kegiatan:</span><br>
            <img src="<?php echo esc_url($image_path); ?>" class="mt-2 w-60 rounded shadow">
        </div>
        <?php } ?>
        <?php if (!empty($lampiran_path)) { ?>
        <div>
            <span class="font-semibold">Lampiran (PDF):</span><br>
            <a href="<?php echo esc_url($lampiran_path); ?>" target="_blank" class="text-blue-600 underline">Download Lampiran</a>
        </div>
        <?php } ?>
    </div>
    <div class="mt-8 flex justify-end">
        <a href="<?php echo admin_url('admin.php?page=notulenmu-list'); ?>" class="bg-gray-400 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded-md">Kembali</a>
    </div>
</div>
<?php
}
