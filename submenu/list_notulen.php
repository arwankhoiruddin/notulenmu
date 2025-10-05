<?php
// Prevent direct access
defined('ABSPATH') || exit;

function notulenmu_list_page()
{
    global $wpdb;
    $user_id = get_current_user_id();

    // Handle delete action only after WordPress is fully loaded
    add_action('admin_init', function() {
        if (isset($_GET['delete_notulen']) && !empty($_GET['delete_notulen'])) {
            global $wpdb;
            $user_id = get_current_user_id();
            $delete_id = intval($_GET['delete_notulen']);
            if (isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'delete_notulen_' . $delete_id)) {
                $table_name = $wpdb->prefix . 'salammu_notulenmu';
                $deleted = $wpdb->delete($table_name, array('id' => $delete_id, 'user_id' => $user_id));
                if ($deleted) {
                    set_transient('notulenmu_admin_notice', 'Notulen berhasil dihapus.', 5);
                } else {
                    set_transient('notulenmu_admin_notice', 'Gagal menghapus notulen.', 5);
                }
                if (!function_exists('wp_redirect')) {
                    require_once(ABSPATH . WPINC . '/pluggable.php');
                }
                wp_redirect(admin_url('admin.php?page=notulenmu-list'));
                exit;
            }
        }
    });

    // $setting_table = $wpdb->prefix . 'salammu_notulenmu_setting';
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

    $table_name = $wpdb->prefix . 'salammu_notulenmu';

    $placeholders = implode(',', array_fill(0, count($id_tingkat_list), '%s'));
    $query = "SELECT * FROM $table_name WHERE id_tingkat IN ($placeholders)";
    $params = $id_tingkat_list;

    if (!empty($search)) {
        $query .= " AND (tingkat LIKE %s OR topik_rapat LIKE %s OR tempat_rapat LIKE %s)";
        $search_term = '%' . $wpdb->esc_like($search) . '%';
        $params[] = $search_term;
    }

    $sql = $wpdb->prepare($query, $params);
    $rows = $wpdb->get_results($sql);

?>
<div class="notulenmu-container">
    <div class="overflow-x-auto bg-white p-6 rounded-lg shadow-md mt-7">
        <div class="flex justify-between items-center">
            <h1 class="text-2xl font-semibold text-gray-700">List Notulen</h1>
        </div>
        <div class="mb-4 flex flex-col gap-3">
            <div>
                <a href="<?php echo esc_url(admin_url('admin.php?page=notulenmu-add')); ?>" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded" style="color:#fff !important;">+ Tambah Notulen</a>
            </div>
            <div class="flex flex-col md:flex-row md:items-center gap-2">
                <!-- Hapus dropdown filter tingkat -->
                <form method="get" class="flex items-center gap-2" action="">
                    <input type="hidden" name="page" value="notulenmu-list">
                    <input type="text" name="search" id="search" class="p-2 border rounded-md" style="width: 320px;" placeholder="Cari tingkat/topik/tempat rapat..." value="<?php echo esc_attr($search); ?>">
                    <button type="submit" class="bg-gray-600 hover:bg-gray-700 text-white font-semibold py-2 px-4 rounded">Cari</button>
                </form>
            </div>
        </div>

        <!-- Tabel -->
        <table class="min-w-full border border-gray-300">
                <thead class="bg-gray-100 text-gray-700">
                    <tr>
                        <th class="py-2 px-4 border border-gray-300">Tingkat</th>
                        <th class="py-2 px-4 border border-gray-300">Topik Rapat</th>
                        <th class="py-2 px-4 border border-gray-300">Tanggal Rapat</th>
                        <th class="py-2 px-4 border border-gray-300">Tempat Rapat</th>
                        <th class="py-2 px-4 border border-gray-300">Detail</th>
                        <th class="py-2 px-4 border border-gray-300">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php foreach ($rows as $row) { ?>
                        <tr class="hover:bg-gray-100">
                            <td class="py-2 px-4 border border-gray-300"><?php echo esc_html($row->tingkat); ?></td>
                            <td class="py-2 px-4 border border-gray-300"><?php echo esc_html($row->topik_rapat); ?></td>
                            <td class="py-2 px-4 border border-gray-300"><?php echo date('Y-m-d', strtotime($row->tanggal_rapat)); ?></td>
                            <td class="py-2 px-4 border border-gray-300"><?php echo esc_html($row->tempat_rapat); ?></td>
                            <td class="py-2 px-4 border border-gray-300 text-center">
                                <a href="<?php echo admin_url('admin.php?page=notulenmu-view&id=' . $row->id); ?>" class="text-blue-500 hover:text-blue-700">View Details</a>
                            </td>
                            <td class="py-2 px-4 border border-gray-300 text-center">
                                <a href="<?php echo admin_url('admin.php?page=notulenmu-add&edit=true&id=' . $row->id); ?>" class="text-green-500 hover:text-green-700 mr-2">Edit</a>
                                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=notulenmu-list&delete_notulen=' . $row->id), 'delete_notulen_' . $row->id); ?>" class="text-red-500 hover:text-red-700" onclick="return confirm('Yakin ingin menghapus notulen ini?');">Delete</a>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php } ?>