<?php
// File: pengurus_list.php
// Menu untuk menampilkan tabel pengurus

function pengurus_list_page() {
    if (!current_user_can('edit_posts')) {
        wp_die('You do not have sufficient permissions to access this page.');
    }
    global $wpdb;
    $user_id = get_current_user_id();
    $tingkat_pengurus = isset($_GET['tingkat_pengurus']) ? $_GET['tingkat_pengurus'] : '';

    // Ambil pengaturan wilayah user
    $setting_table = $wpdb->prefix . 'salammu_notulenmu_setting';
    $settings = $wpdb->get_row($wpdb->prepare(
        "SELECT pwm, pdm, pcm, prm FROM $setting_table WHERE user_id = %d",
        $user_id
    ), ARRAY_A);

    if (!$settings) {
        echo "<p>Pengaturan wilayah tidak ditemukan.</p>";
        return;
    }

    $id_tingkat_list = array_filter([$settings['pwm'], $settings['pdm'], $settings['pcm'], $settings['prm']]);
    if (empty($id_tingkat_list)) {
        echo "<p>Tidak ada data pengurus yang tersedia.</p>";
        return;
    }

    // Mapping tingkat dan id_tingkat dari setting user

    $pengurus_table = $wpdb->prefix . 'salammu_data_pengurus';
    $tingkat_options = array();
    if ($settings['pwm']) $tingkat_options[] = (object)[ 'tingkat' => 'Wilayah', 'id_tingkat' => $settings['pwm'] ];
    if ($settings['pdm']) $tingkat_options[] = (object)[ 'tingkat' => 'Daerah', 'id_tingkat' => $settings['pdm'] ];
    if ($settings['pcm']) $tingkat_options[] = (object)[ 'tingkat' => 'Cabang', 'id_tingkat' => $settings['pcm'] ];
    if ($settings['prm']) $tingkat_options[] = (object)[ 'tingkat' => 'Ranting', 'id_tingkat' => $settings['prm'] ];

    // Default: tampilkan pengurus wilayah (PWM)
    $default_tingkat = $settings['pwm'];
    $default_label = 'Wilayah';
    if ($tingkat_options) {
        foreach ($tingkat_options as $t) {
            if ($t->id_tingkat == $tingkat_pengurus) {
                $selected_label = $t->tingkat;
                break;
            }
        }
    }
    if (!isset($selected_label)) {
        $selected_label = $default_label;
    }

    if ($tingkat_pengurus !== '') {
        $query = $wpdb->prepare(
            "SELECT id, nama_lengkap_gelar, jabatan, tingkat FROM $pengurus_table WHERE id_tingkat = %d AND tingkat = %s",
            $tingkat_pengurus,
            $selected_label
        );
    } else {
        $query = $wpdb->prepare(
            "SELECT id, nama_lengkap_gelar, jabatan, tingkat FROM $pengurus_table WHERE id_tingkat = %d AND tingkat = %s",
            $default_tingkat,
            $default_label
        );
        $tingkat_pengurus = $default_tingkat;
    }
    $pengurus = $wpdb->get_results($query);

    echo '<h1 class="px-6">Daftar Pengurus</h1>';
    // Tombol tambah pengurus
    echo '<div class="px-6 mb-4">';
    $tingkat_pengurus_url = isset($_GET['tingkat_pengurus']) ? '&tingkat_pengurus=' . urlencode($_GET['tingkat_pengurus']) : '';
    echo '<a href="' . esc_url(admin_url('admin.php?page=pengurus-add' . $tingkat_pengurus_url)) . '" class="inline-block bg-gray-400 hover:bg-gray-600 text-white font-semibold py-2 px-4 rounded mb-2">+ Tambah Pengurus</a>';
    echo '</div>';
    // Dropdown filter tingkat tanpa "Semua Tingkat"
    echo '<form method="get" class="mb-4 px-6">';
    foreach ($_GET as $key => $val) {
        if ($key !== 'tingkat_pengurus') {
            echo '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr($val) . '">';
        }
    }
    echo '<label for="tingkat_pengurus" class="mr-2">Filter Tingkat:</label>';
    echo '<select name="tingkat_pengurus" id="tingkat_pengurus" class="border rounded px-2 py-1" onchange="this.form.submit()">';
    if ($tingkat_options) {
        foreach ($tingkat_options as $t) {
            $selected = ($tingkat_pengurus == $t->id_tingkat) ? 'selected' : '';
            echo '<option value="' . esc_attr($t->id_tingkat) . '" ' . $selected . '>' . esc_html($t->tingkat) . '</option>';
        }
    }
    echo '</select>';
    echo '</form>';
    if (!empty($pengurus)) :
?>
    <div class="overflow-x-auto bg-white p-6 rounded-lg shadow-md">
        <table class="min-w-full border border-gray-200">
            <thead class="bg-gray-100">
                <tr>
                    <th class="border border-gray-300 px-4 py-2 text-center">No</th>
                    <th class="border border-gray-300 px-4 py-2 text-center">Nama</th>
                    <th class="border border-gray-300 px-4 py-2 text-center">Jabatan</th>
                    <th class="border border-gray-300 px-4 py-2 text-center">Tingkat</th>
                    <th class="border border-gray-300 px-4 py-2 text-center">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php
                $no = 1;
                foreach ($pengurus as $p) :
                    if (!isset($p->nama_lengkap_gelar)) continue;
                ?>
                    <tr class="hover:bg-gray-50">
                        <td class="border border-gray-300 px-4 py-2 text-center"><?php echo esc_html($no); ?></td>
                        <td class="border border-gray-300 px-4 py-2"><?php echo esc_html($p->nama_lengkap_gelar); ?></td>
                        <td class="border border-gray-300 px-4 py-2"><?php echo esc_html($p->jabatan); ?></td>
                        <td class="border border-gray-300 px-4 py-2 text-center"><?php echo esc_html($p->tingkat); ?></td>
                        <td class="border border-gray-300 px-4 py-2 text-center space-x-2">
                            <a href="<?php echo esc_url(admin_url('admin.php?page=pengurus-add&edit=1&id=' . $p->id)); ?>"
                                class="text-green-500 hover:text-green-700 mr-2">
                                Edit
                            </a>
                            <a href="<?php echo esc_url(admin_url('admin-post.php?action=delete_pengurus&id=' . $p->id . '&tingkat_pengurus=' . $p->tingkat)); ?>"
                                class="text-red-500 hover:text-red-700"
                                onclick="return confirm('Yakin ingin menghapus pengurus ini?');">
                                Delete
                            </a>
                        </td>
                    </tr>
                <?php
                    $no++;
                endforeach;
                ?>
            </tbody>
        </table>
    </div>
<?php
    else :
        echo "<p class='text-red-500'>Tidak ada pengurus pada tingkat ini.</p>";
    endif;
}

// Untuk menambahkan menu di admin, tambahkan kode berikut di file utama plugin:
// add_menu_page('Pengurus', 'Pengurus', 'edit_posts', 'pengurus-list', 'pengurus_list_page');
