<?php
// Handle delete action before any output
if (isset($_GET['delete_kegiatan']) && !empty($_GET['delete_kegiatan'])) {
    if (!function_exists('wp_verify_nonce')) {
        require_once(ABSPATH . WPINC . '/pluggable.php');
    }
    global $wpdb;
    $user_id = get_current_user_id();
    $delete_id = intval($_GET['delete_kegiatan']);
    if (wp_verify_nonce($_GET['_wpnonce'], 'delete_kegiatan_' . $delete_id)) {
        $table_name = $wpdb->prefix . 'salammu_kegiatanmu';
        $deleted = $wpdb->delete($table_name, array('id' => $delete_id, 'user_id' => $user_id));
        if ($deleted) {
            set_transient('kegiatanmu_admin_notice', 'Kegiatan berhasil dihapus.', 5);
        } else {
            set_transient('kegiatanmu_admin_notice', 'Gagal menghapus kegiatan.', 5);
        }
        if (!function_exists('wp_redirect')) {
            require_once(ABSPATH . WPINC . '/pluggable.php');
        }
        wp_redirect(admin_url('admin.php?page=kegiatanmu-list'));
        exit;
    }
}

function kegiatanmu_list_page()
{
    $user_id = get_current_user_id();
    $user = get_userdata($user_id);
    if (!empty($user) && is_array($user->roles)) {
        $role = $user->roles[0];
    }
    if ($role != 'contributor' && $role != 'administrator') {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'salammu_kegiatanmu';

    // Ambil id tingkat dari settings user
    $setting_table = $wpdb->prefix . 'sicara_settings';
    $settings = $wpdb->get_row($wpdb->prepare(
        "SELECT pwm, pdm, pcm, prm FROM $setting_table WHERE user_id = %d",
        $user_id
    ), ARRAY_A);

    if (!$settings) {
        echo "<p>Data tidak ditemukan.</p>";
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
        echo "<p>You do not have sufficient permissions to access this page.</p>";
        return;
    }

    $search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';

    $placeholders = implode(',', array_fill(0, count($id_tingkat_list), '%s'));
    $query = "SELECT * FROM $table_name WHERE id_tingkat IN ($placeholders) order by tanggal_kegiatan DESC";
    $params = $id_tingkat_list;

    if (!empty($search)) {
        $query .= " AND (tingkat LIKE %s OR nama_kegiatan LIKE %s OR tempat_kegiatan LIKE %s)";
        $search_term = '%' . $wpdb->esc_like($search) . '%';
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
    }

    $sql = $wpdb->prepare($query, $params);
    $rows = $wpdb->get_results($sql);

    // Group kegiatan by organizational level (tingkat), then by entity (tingkat + id_tingkat)
    $grouped_kegiatan = array();
    foreach ($rows as $row) {
        $tingkat = $row->tingkat;
        $entity_key = $row->tingkat . '_' . $row->id_tingkat;
        
        if (!isset($grouped_kegiatan[$tingkat])) {
            $grouped_kegiatan[$tingkat] = array();
        }
        
        if (!isset($grouped_kegiatan[$tingkat][$entity_key])) {
            $grouped_kegiatan[$tingkat][$entity_key] = array(
                'tingkat' => $row->tingkat,
                'id_tingkat' => $row->id_tingkat,
                'entity_name' => notulenmu_get_entity_name($row->tingkat, $row->id_tingkat),
                'kegiatan' => array()
            );
        }
        $grouped_kegiatan[$tingkat][$entity_key]['kegiatan'][] = $row;
    }
    
    // Sort by organizational hierarchy: wilayah > daerah > cabang > ranting
    $tingkat_order = array('wilayah' => 1, 'daerah' => 2, 'cabang' => 3, 'ranting' => 4);
    uksort($grouped_kegiatan, function($a, $b) use ($tingkat_order) {
        $order_a = isset($tingkat_order[$a]) ? $tingkat_order[$a] : 999;
        $order_b = isset($tingkat_order[$b]) ? $tingkat_order[$b] : 999;
        return $order_a - $order_b;
    });

    // Notifikasi
    if ($message = get_transient('kegiatanmu_admin_notice')) {
        echo "<div class='notice notice-success is-dismissible'><p>$message</p></div>";
        delete_transient('kegiatanmu_admin_notice');
    }
?>
<div class="notulenmu-container">
    <div class="overflow-x-auto bg-white p-6 rounded-lg shadow-md mt-7">
        <div class="flex justify-between items-center">
            <h1 class="text-2xl font-semibold  text-gray-700">List Kegiatan</h1>
        </div>
        <div class="mb-4">
            <a href="<?php echo esc_url(admin_url('admin.php?page=kegiatanmu-add')); ?>" class="inline-block bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded" style="color:#fff !important;">+ Tambah Kegiatan</a>
        </div>
        <div class="mb-4 flex flex-col md:flex-row md:items-center gap-2">
            <form method="get" class="flex items-center gap-2 w-full md:w-auto" action="">
                <input type="hidden" name="page" value="kegiatanmu-list">
                <input
                    type="text"
                    name="search"
                    id="search"
                    class="p-2 border rounded-md w-full md:w-auto min-w-[320px]"
                    placeholder="Cari nama/tempat/tingkat kegiatan..."
                    value="<?php echo esc_attr($search); ?>"
                >
                <button type="submit" class="bg-gray-600 hover:bg-gray-700 text-white font-semibold py-2 px-4 rounded">Cari</button>
            </form>
        </div>

        <?php if (empty($grouped_kegiatan)) { ?>
            <p class="text-gray-600 text-center py-4">Tidak ada data kegiatan yang ditemukan.</p>
        <?php } else { ?>
            <?php 
            // Define tingkat labels for display
            $tingkat_labels = array(
                'wilayah' => 'PWM (Pimpinan Wilayah Muhammadiyah)',
                'daerah' => 'PDM (Pimpinan Daerah Muhammadiyah)',
                'cabang' => 'PCM (Pimpinan Cabang Muhammadiyah)',
                'ranting' => 'PRM (Pimpinan Ranting Muhammadiyah)'
            );
            
            foreach ($grouped_kegiatan as $tingkat => $entities) { ?>
                <!-- Organizational Level Header -->
                <div class="mt-8 mb-4">
                    <h2 class="text-2xl font-bold text-gray-900 border-b-4 border-blue-500 pb-3">
                        <?php echo esc_html(isset($tingkat_labels[$tingkat]) ? $tingkat_labels[$tingkat] : ucfirst($tingkat)); ?>
                    </h2>
                </div>

                <?php foreach ($entities as $entity_key => $entity_data) { ?>
                    <!-- Entity Header -->
                    <div class="mt-6 mb-3">
                        <h3 class="text-xl font-semibold text-gray-800 border-b-2 border-gray-300 pb-2">
                            <?php echo esc_html($entity_data['entity_name']); ?>
                        </h3>
                    </div>

                    <!-- Tabel for this entity -->
                    <table class="min-w-full border border-gray-300 mb-6">
                        <thead class="bg-gray-100 text-gray-700">
                            <tr>
                                <th class="py-2 px-4 border border-gray-300">Tingkat</th>
                                <th class="py-2 px-4 border border-gray-300">Nama Kegiatan</th>
                                <th class="py-2 px-4 border border-gray-300">Tanggal Kegiatan</th>
                                <th class="py-2 px-4 border border-gray-300">Tempat Kegiatan</th>
                                <th class="py-2 px-4 border border-gray-300">Detail</th>
                                <th class="py-2 px-4 border border-gray-300">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($entity_data['kegiatan'] as $row) { ?>
                                <tr class="hover:bg-gray-100">
                                    <td class="py-2 px-4 border border-gray-300"><?php echo esc_html($row->tingkat); ?></td>
                                    <td class="py-2 px-4 border border-gray-300"><?php echo esc_html($row->nama_kegiatan); ?></td>
                                    <td class="py-2 px-4 border border-gray-300"><?php echo date('Y-m-d', strtotime($row->tanggal_kegiatan)); ?></td>
                                    <td class="py-2 px-4 border border-gray-300"><?php echo esc_html($row->tempat_kegiatan); ?></td>
                                    <td class="py-2 px-4 border border-gray-300 text-center">
                                        <a href="<?php echo admin_url('admin.php?page=kegiatanmu-view&id=' . $row->id); ?>" class="text-blue-500 hover:text-blue-700">View Details</a>
                                    </td>
                                    <td class="py-2 px-4 border border-gray-300 text-center">
                                        <a href="<?php echo admin_url('admin.php?page=kegiatanmu-add&edit=true&id=' . $row->id); ?>" class="text-green-500 hover:text-green-700 mr-2">Edit</a>
                                        <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=kegiatanmu-list&delete_kegiatan=' . $row->id), 'delete_kegiatan_' . $row->id); ?>" class="text-red-500 hover:text-red-700" onclick="return confirm('Yakin ingin menghapus kegiatan ini?');">Delete</a>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                <?php } ?>
            <?php } ?>
        <?php } ?>
        </div>
    </div>
</div>
<?php } ?>
