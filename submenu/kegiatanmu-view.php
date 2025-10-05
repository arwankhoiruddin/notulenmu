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

    // Determine user organizational level
    $current_user = wp_get_current_user();
    $user_level = '';
    
    if (strpos($current_user->user_login, 'pwm.') === 0) {
        $user_level = 'pwm';
    } else if (strpos($current_user->user_login, 'pdm.') === 0) {
        $user_level = 'pdm';
    } else if (strpos($current_user->user_login, 'pcm.') === 0) {
        $user_level = 'pcm';
    } else if (strpos($current_user->user_login, 'prm.') === 0) {
        $user_level = 'prm';
    }
    
    // Get all accessible id_tingkat based on organizational hierarchy
    $id_tingkat_list = notulenmu_get_accessible_id_tingkat($settings, $user_level);

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
<div class="notulenmu-container">
    <!-- Header Section with Brand Colors -->
    <div class="relative p-6 bg-[#2d3476] shadow-lg rounded-lg mb-6 ml-0 text-white overflow-hidden">
        <img src="<?php echo plugin_dir_url(__FILE__) . '../assets/img/image.png'; ?>"
            alt="KegiatanMu"
            class="absolute top-0 right-5 w-40 opacity-20">
        
        <div class="relative z-10">
            <h1 class="text-2xl font-bold mb-2">Detail Kegiatan</h1>
            <p class="text-blue-100">Informasi lengkap kegiatan</p>
        </div>
    </div>

    <!-- Main Content -->
    <div class="bg-white shadow-lg rounded-lg overflow-hidden">
        <!-- Activity Overview Section -->
        <div class="bg-gradient-to-r from-blue-50 to-indigo-50 p-6 border-b border-gray-200">
            <div class="flex items-center gap-3 mb-4">
                <div class="bg-[#2d3476] p-2 rounded-lg">
                    <svg class="w-5 h-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 15.546c-.523 0-1.046.151-1.5.454a2.704 2.704 0 01-3 0 2.704 2.704 0 00-3 0 2.704 2.704 0 01-3 0 2.704 2.704 0 00-3 0 2.704 2.704 0 01-3 0A1.5 1.5 0 013 13.5V9c0-.621.504-1.125 1.125-1.125h15.75c.621 0 1.125.504 1.125 1.125v4.5c0 .414-.168.789-.454 1.046z" />
                    </svg>
                </div>
                <div>
                    <h2 class="text-xl font-bold text-gray-800"><?php echo esc_html($kegiatan->nama_kegiatan); ?></h2>
                    <p class="text-gray-600"><?php echo esc_html($kegiatan->tingkat); ?></p>
                </div>
            </div>
        </div>

        <!-- Activity Details Grid -->
        <div class="p-6">
            <div class="grid md:grid-cols-2 gap-6 mb-8">
                <!-- Date and Location Info -->
                <div class="space-y-4">
                    <h3 class="text-lg font-semibold text-gray-800 border-b border-gray-200 pb-2">Waktu & Tempat</h3>
                    
                    <div class="flex items-start gap-3">
                        <div class="bg-blue-100 p-2 rounded-lg">
                            <svg class="w-4 h-4 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <div>
                            <p class="font-medium text-gray-800">Tanggal Kegiatan</p>
                            <p class="text-gray-600"><?php echo date('d F Y', strtotime($kegiatan->tanggal_kegiatan)); ?></p>
                        </div>
                    </div>

                    <div class="flex items-start gap-3">
                        <div class="bg-purple-100 p-2 rounded-lg">
                            <svg class="w-4 h-4 text-purple-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                        </div>
                        <div>
                            <p class="font-medium text-gray-800">Tempat Kegiatan</p>
                            <p class="text-gray-600"><?php echo esc_html($kegiatan->tempat_kegiatan); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Activity Information -->
                <div class="space-y-4">
                    <h3 class="text-lg font-semibold text-gray-800 border-b border-gray-200 pb-2">Informasi Kegiatan</h3>
                    
                    <div class="flex items-start gap-3">
                        <div class="bg-indigo-100 p-2 rounded-lg">
                            <svg class="w-4 h-4 text-indigo-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z" />
                            </svg>
                        </div>
                        <div>
                            <p class="font-medium text-gray-800">Jumlah Peserta</p>
                            <div class="bg-indigo-50 rounded-lg p-2 mt-1">
                                <span class="bg-white text-indigo-700 text-sm px-3 py-1 rounded-md border border-indigo-200"><?php echo esc_html($kegiatan->peserta_kegiatan); ?> Peserta</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Activity Description -->
            <div class="mb-8">
                <div class="flex items-center gap-3 mb-4">
                    <div class="bg-gray-100 p-2 rounded-lg">
                        <svg class="w-5 h-5 text-gray-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-800">Detail Kegiatan</h3>
                </div>
                <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                    <div class="prose prose-sm max-w-none text-gray-700">
                        <?php echo wp_kses_post($kegiatan->detail_kegiatan); ?>
                    </div>
                </div>
            </div>

            <!-- Photo Section -->
            <?php if (!empty($image_path)) { ?>
            <div class="border-t border-gray-200 pt-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Dokumentasi</h3>
                <div>
                    <div class="flex items-center gap-2 mb-3">
                        <div class="bg-pink-100 p-2 rounded-lg">
                            <svg class="w-4 h-4 text-pink-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <p class="font-medium text-gray-800">Foto Kegiatan</p>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-3">
                        <img src="<?php echo esc_url($image_path); ?>" class="w-full max-w-sm rounded-lg shadow-md hover:shadow-lg transition-shadow duration-200" alt="Foto Kegiatan">
                    </div>
                </div>
            </div>
            <?php } ?>
        </div>

        <!-- Footer Actions -->
        <div class="bg-gray-50 px-6 py-4 border-t border-gray-200">
            <div class="flex justify-between items-center">
                <div class="text-sm text-gray-500">
                    ID Kegiatan: #<?php echo esc_html($kegiatan->id); ?>
                </div>
                <a href="<?php echo admin_url('admin.php?page=kegiatanmu-list'); ?>" 
                   class="inline-flex items-center gap-2 bg-[#2d3476] hover:bg-[#1e2355] text-white font-medium px-6 py-2 rounded-lg transition-colors duration-200">
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Kembali ke Daftar
                </a>
            </div>
        </div>
    </div>
</div>
<?php
}
