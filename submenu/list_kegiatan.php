<?php
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

    $filter = isset($_GET['filter']) ? $_GET['filter'] : '';

    $sql = "SELECT * FROM $table_name where user_id = $user_id";
    if ($filter !== '') {
        $sql .= " AND tingkat = '$filter'";
    }
    $sql .= " order by tingkat";
    $rows = $wpdb->get_results($sql);

?>
    <div class="max-w-5xl mx-auto p-6 mt-7 bg-white shadow-md rounded-lg border-x border-gray-300">
        <div class="flex justify-between items-center">
            <h1 class="text-2xl font-semibold  text-gray-700">List Kegiatan</h1>

            <div>
                <select id="filter" class="p-2 border rounded-md w-full" onchange="if (this.value !== null) window.location.href='?page=notulenmu-list&filter='+this.value">
                    <option value="">Semua</option>
                    <option value="ranting" <?php echo ($filter === 'ranting' ? 'selected' : ''); ?>>Ranting</option>
                    <option value="cabang" <?php echo ($filter === 'cabang' ? 'selected' : ''); ?>>Cabang</option>
                    <option value="daerah" <?php echo ($filter === 'daerah' ? 'selected' : ''); ?>>Daerah</option>
                    <option value="wilayah" <?php echo ($filter === 'wilayah' ? 'selected' : ''); ?>>Wilayah</option>
                </select>
            </div>
        </div>


        <!-- Tabel -->
        <div class="overflow-x-auto">
            <table class="w-full border border-gray-300 rounded-md">
                <thead class="bg-gray-200 text-gray-700">
                    <tr>
                        <th class="py-2 px-4 border border-gray-300">Tingkat</th>
                        <th class="py-2 px-4 border border-gray-300">Nama Kegiatan</th>
                        <th class="py-2 px-4 border border-gray-300">Tanggal Kegiatan</th>
                        <th class="py-2 px-4 border border-gray-300">Tempat Kegiatan</th>
                        <th class="py-2 px-4 border border-gray-300">Detail</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row) { ?>
                        <tr class="hover:bg-gray-100">
                            <td class="py-2 px-4 border border-gray-300"><?php echo esc_html($row->tingkat); ?></td>
                            <td class="py-2 px-4 border border-gray-300"><?php echo esc_html($row->nama_kegiatan); ?></td>
                            <td class="py-2 px-4 border border-gray-300"><?php echo date('Y-m-d', strtotime($row->tanggal_kegiatan)); ?></td>
                            <td class="py-2 px-4 border border-gray-300"><?php echo esc_html($row->tempat_kegiatan); ?></td>
                            <td class="py-2 px-4 border border-gray-300 text-center">
                                <a href="<?php echo admin_url('admin.php?page=kegiatanmu-add&edit=true&id=' . $row->id); ?>" class="text-blue-500 hover:text-blue-700">View Details</a>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>

<?php } ?>