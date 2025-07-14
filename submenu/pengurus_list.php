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

    echo '<div class="bg-white rounded-lg shadow-md p-6 mb-6">';
    echo '<div class="mb-4">';
    echo '<h1 class="text-3xl font-bold mb-2">Daftar Pengurus</h1>';
    $tingkat_pengurus_url = isset($_GET['tingkat_pengurus']) ? '&tingkat_pengurus=' . urlencode($_GET['tingkat_pengurus']) : '';
    echo '<a href="' . esc_url(admin_url('admin.php?page=pengurus-add' . $tingkat_pengurus_url)) . '" class="inline-block bg-blue-600 hover:bg-blue-700 font-semibold py-2 px-4 rounded shadow mb-2 text-white" style="color:#fff !important;">+ Tambah Pengurus</a>';
    echo '</div>';
    // Dropdown filter tingkat
    echo '<form method="get" class="mb-4">';
    foreach ($_GET as $key => $val) {
        if ($key !== 'tingkat_pengurus') {
            echo '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr($val) . '">';
        }
    }
    echo '<label for="tingkat_pengurus" class="mr-2 font-semibold">Filter Tingkat:</label>';
    echo '<select name="tingkat_pengurus" id="tingkat_pengurus" class="border rounded px-2 py-1" onchange="this.form.submit()">';
    if ($tingkat_options) {
        foreach ($tingkat_options as $t) {
            $selected = ($tingkat_pengurus == $t->id_tingkat) ? 'selected' : '';
            echo '<option value="' . esc_attr($t->id_tingkat) . '" ' . $selected . '>' . esc_html($t->tingkat) . '</option>';
        }
    }
    echo '</select>';
    echo '</form>';
    echo '</div>';
    if (!empty($pengurus)) :
        echo '<div class="overflow-x-auto bg-white p-6 rounded-lg shadow-md">';
        echo '<table class="min-w-full border border-gray-200">';
        echo '<thead class="bg-gray-100">';
        echo '<tr>';
        echo '<th class="border border-gray-300 px-4 py-2 text-center">No</th>';
        echo '<th class="border border-gray-300 px-4 py-2 text-center">Nama</th>';
        echo '<th class="border border-gray-300 px-4 py-2 text-center">Jabatan</th>';
        echo '<th class="border border-gray-300 px-4 py-2 text-center">Tingkat</th>';
        echo '<th class="border border-gray-300 px-4 py-2 text-center">Aksi</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody class="divide-y divide-gray-200">';
        $no = 1;
        foreach ($pengurus as $p) :
            if (!isset($p->nama_lengkap_gelar)) continue;
            echo '<tr class="hover:bg-gray-50">';
            echo '<td class="border border-gray-300 px-4 py-2 text-center">' . esc_html($no) . '</td>';
            echo '<td class="border border-gray-300 px-4 py-2">' . esc_html($p->nama_lengkap_gelar) . '</td>';
            echo '<td class="border border-gray-300 px-4 py-2">' . esc_html($p->jabatan) . '</td>';
            echo '<td class="border border-gray-300 px-4 py-2 text-center">' . esc_html($p->tingkat) . '</td>';
            echo '<td class="border border-gray-300 px-4 py-2 text-center space-x-2">';
            echo '<a href="' . esc_url(admin_url('admin.php?page=pengurus-add&edit=1&id=' . $p->id)) . '" class="text-green-600 hover:text-green-800 font-semibold mr-4">Edit</a>';
            echo '<a href="' . esc_url(admin_url('admin-post.php?action=delete_pengurus&id=' . $p->id . '&tingkat_pengurus=' . $p->tingkat)) . '" class="text-red-600 hover:text-red-800 font-semibold" onclick="return confirm(\'Yakin ingin menghapus pengurus ini?\');">Delete</a>';
            echo '</td>';
            echo '</tr>';
            $no++;
        endforeach;
        echo '</tbody>';
        echo '</table>';
        echo '</div>';
    else :
        echo "<div class='bg-white rounded-lg shadow-md p-6'><p class='text-red-500'>Tidak ada pengurus pada tingkat ini.</p></div>";
    endif;
}

// Untuk menambahkan menu di admin, tambahkan kode berikut di file utama plugin:
// add_menu_page('Pengurus', 'Pengurus', 'edit_posts', 'pengurus-list', 'pengurus_list_page');
