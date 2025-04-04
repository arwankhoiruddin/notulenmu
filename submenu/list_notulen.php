<?php
function notulenmu_list_page()
{
    global $wpdb;
    $user_id = get_current_user_id();

    $setting_table = $wpdb->prefix . 'salammu_notulenmu_setting';
    $settings = $wpdb->get_row($wpdb->prepare(
        "SELECT pwm, pdm, pcm, prm FROM $setting_table WHERE user_id = %d",
        $user_id
    ), ARRAY_A);

    if (!$settings) {
        echo "<p>Data tidak ditemukan.</p>";
        return;
    }

    $id_tingkat_list = array_filter([$settings['pwm'], $settings['pdm'], $settings['pcm'], $settings['prm']]);

    if (empty($id_tingkat_list)) {
        echo "<p>You do not have sufficient permissions to access this page.</p>";
        return;
    }

    $filter = isset($_GET['filter']) ? sanitize_text_field($_GET['filter']) : '';

    $table_name = $wpdb->prefix . 'salammu_notulenmu';
    $placeholders = implode(',', array_fill(0, count($id_tingkat_list), '%d'));

    $query = "SELECT * FROM $table_name WHERE user_id = %d AND id_tingkat IN ($placeholders)";

    if (!empty($filter)) {
        $query .= " AND tingkat = %s";
        $sql = $wpdb->prepare($query, array_merge([$user_id], $id_tingkat_list, [$filter]));
    } else {
        $sql = $wpdb->prepare($query, array_merge([$user_id], $id_tingkat_list));
    }

    $rows = $wpdb->get_results($sql);

?>
    <div class="max-w-5xl mx-auto p-6 mt-7 bg-white shadow-md rounded-lg border-x border-gray-300">
        <div class="flex justify-between items-center">
            <h1 class="text-2xl font-semibold  text-gray-700">List Notulen</h1>

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
                        <th class="py-2 px-4 border border-gray-300">Topik Rapat</th>
                        <th class="py-2 px-4 border border-gray-300">Tanggal Rapat</th>
                        <th class="py-2 px-4 border border-gray-300">Tempat Rapat</th>
                        <th class="py-2 px-4 border border-gray-300">Detail</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row) { ?>
                        <tr class="hover:bg-gray-100">
                            <td class="py-2 px-4 border border-gray-300"><?php echo esc_html($row->tingkat); ?></td>
                            <td class="py-2 px-4 border border-gray-300"><?php echo esc_html($row->topik_rapat); ?></td>
                            <td class="py-2 px-4 border border-gray-300"><?php echo date('Y-m-d', strtotime($row->tanggal_rapat)); ?></td>
                            <td class="py-2 px-4 border border-gray-300"><?php echo esc_html($row->tempat_rapat); ?></td>
                            <td class="py-2 px-4 border border-gray-300 text-center">
                                <a href="<?php echo admin_url('admin.php?page=notulenmu-add&edit=true&id=' . $row->id); ?>" class="text-blue-500 hover:text-blue-700">View Details</a>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>

<?php } ?>