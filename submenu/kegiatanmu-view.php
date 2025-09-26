<?php
function kegiatanmu_view_page() {
    if (!current_user_can('read')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }
    global $wpdb;
    $user_id = get_current_user_id();
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    // Get user settings to determine accessible id_tingkat values (same logic as list_kegiatan.php)
    $setting_table = $wpdb->prefix . 'sicara_settings';
    $settings = $wpdb->get_row($wpdb->prepare(
        "SELECT pwm, pdm, pcm, prm FROM $setting_table WHERE user_id = %d",
        $user_id
    ), ARRAY_A);

    if (!$settings) {
        echo '<div class="p-6 bg-white rounded shadow text-center">Data tidak ditemukan.</div>';
        return;
    }

    $id_tingkat_list = array_filter([$settings['pwm'], $settings['pdm'], $settings['pcm'], $settings['prm']]);

    if (empty($id_tingkat_list)) {
        echo '<div class="p-6 bg-white rounded shadow text-center">You do not have sufficient permissions to access this page.</div>';
        return;
    }

    // Query kegiatan with same permission logic as list page
    $table_name = $wpdb->prefix . 'salammu_kegiatanmu';
    $placeholders = implode(',', array_fill(0, count($id_tingkat_list), '%s'));
    $query = "SELECT * FROM $table_name WHERE id = %d AND id_tingkat IN ($placeholders)";
    $params = array_merge([$id], $id_tingkat_list);
    
    $kegiatan = $wpdb->get_row($wpdb->prepare($query, $params));
    if (!$kegiatan) {
        echo '<div class="p-6 bg-white rounded shadow text-center">Data tidak ditemukan.</div>';
        return;
    }
    if ($kegiatan->image_path) {
        $upload_dir = wp_upload_dir();
        $image_path = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $kegiatan->image_path);
    }
?>
<div class="max-w-2xl mx-auto p-6 mt-7 bg-white shadow-md rounded-lg border-x border-gray-300">
    <h1 class="text-2xl font-semibold mb-6 text-gray-700">Detail Kegiatan</h1>
    <div class="grid gap-6">
        <div>
            <span class="font-semibold">Tingkat:</span> <?php echo esc_html($kegiatan->tingkat); ?>
        </div>
        <div>
            <span class="font-semibold">Nama Kegiatan:</span> <?php echo esc_html($kegiatan->nama_kegiatan); ?>
        </div>
        <div>
            <span class="font-semibold">Tanggal Kegiatan:</span> <?php echo date('Y-m-d', strtotime($kegiatan->tanggal_kegiatan)); ?>
        </div>
        <div>
            <span class="font-semibold">Tempat Kegiatan:</span> <?php echo esc_html($kegiatan->tempat_kegiatan); ?>
        </div>
        <div>
            <span class="font-semibold">Jumlah Peserta:</span> <?php echo esc_html($kegiatan->peserta_kegiatan); ?>
        </div>
        <div>
            <span class="font-semibold">Detail Kegiatan:</span><br>
            <div class="border p-3 rounded bg-gray-50 mt-1"><?php echo wp_kses_post($kegiatan->detail_kegiatan); ?></div>
        </div>
        <?php if (!empty($image_path)) { ?>
        <div>
            <span class="font-semibold">Foto Kegiatan:</span><br>
            <img src="<?php echo esc_url($image_path); ?>" class="mt-2 w-60 rounded shadow">
        </div>
        <?php } ?>
    </div>
    <div class="mt-8 flex justify-end">
        <a href="<?php echo admin_url('admin.php?page=kegiatanmu-list'); ?>" class="bg-gray-400 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded-md">Kembali</a>
    </div>
</div>
<?php
}
