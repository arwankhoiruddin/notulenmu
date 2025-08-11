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

    $search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';

    $sql = $wpdb->prepare("SELECT * FROM $table_name WHERE user_id = %d", $user_id);
    if ($search !== '') {
        $like = '%' . $wpdb->esc_like($search) . '%';
        $sql .= $wpdb->prepare(" AND (nama_kegiatan LIKE %s OR tempat_kegiatan LIKE %s OR tingkat LIKE %s)", $like, $like, $like);
    }
    $sql .= " ORDER BY tingkat";
    $rows = $wpdb->get_results($sql);

    // Notifikasi
    if ($message = get_transient('kegiatanmu_admin_notice')) {
        echo "<div class='notice notice-success is-dismissible'><p>$message</p></div>";
        delete_transient('kegiatanmu_admin_notice');
    }
?>
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
        <!-- Tabel -->
        <table class="min-w-full border border-gray-300">
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
                    <?php foreach ($rows as $row) { ?>
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
        </div>
    </div>
<?php } ?>