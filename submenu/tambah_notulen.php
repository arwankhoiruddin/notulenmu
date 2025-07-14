<?php
require_once plugin_dir_path(__FILE__) . 'pilih_tingkat.php';
require_once plugin_dir_path(__FILE__) . 'input_notulen.php';

function notulenmu_add_page() {
    if (!current_user_can('edit_posts')) {
        wp_die(('You do not have sufficient permissions to access this page.'));
    }
    if (isset($_GET['edit'])) {
        notulenmu_input_form_page();
    } else {
        notulenmu_pilih_tingkat_page();
    }
}


function handle_notulen_form() {
    if (!isset($_POST['notulenmu_nonce']) || !wp_verify_nonce($_POST['notulenmu_nonce'], 'notulenmu_add_nonce')) {
        wp_die('Nonce verification failed.');
    }
    // Debug: log POST data for troubleshooting
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('handle_notulen_form POST: ' . print_r($_POST, true));
    }
    global $wpdb;
    $user_id = get_current_user_id();
    $tingkat = sanitize_text_field($_POST['tingkat'] ?? '');
    $topik_rapat = sanitize_text_field($_POST['topik_rapat'] ?? '');
    $tanggal_rapat = sanitize_text_field($_POST['tanggal_rapat'] ?? '');
    $jam_mulai = sanitize_text_field($_POST['jam_mulai'] ?? '');
    $jam_selesai = sanitize_text_field($_POST['jam_selesai'] ?? '');
    $sifat_rapat = isset($_POST['sifat_rapat']) ? json_encode([sanitize_text_field($_POST['sifat_rapat'])]) : json_encode([]);
    $tempat_rapat = sanitize_text_field($_POST['tempat_rapat'] ?? '');
    // Gabungkan peserta_rapat[] dan peserta_tambahan
    $peserta_rapat_arr = isset($_POST['peserta_rapat']) ? (array)$_POST['peserta_rapat'] : [];
    $peserta_tambahan = sanitize_text_field($_POST['peserta_tambahan'] ?? '');
    if (!empty($peserta_tambahan)) {
        // Pisahkan dengan koma, hapus spasi ekstra
        $tambahan_arr = array_map('trim', explode(',', $peserta_tambahan));
        foreach ($tambahan_arr as $t) {
            if ($t !== '') {
                $peserta_rapat_arr[] = $t;
            }
        }
    }
    // Pastikan tidak ada duplikat
    $peserta_rapat_arr = array_unique($peserta_rapat_arr);
    $peserta_rapat = json_encode(array_values($peserta_rapat_arr));
    $notulen_rapat = sanitize_textarea_field($_POST['notulen_rapat'] ?? '');
    $image_path = '';
    $lampiran_path = '';
    $table = $wpdb->prefix . 'salammu_notulenmu';
    // If edit mode, get previous image_path and lampiran
    $is_edit = isset($_POST['edit_id']) && $_POST['edit_id'];
    if ($is_edit) {
        $old = $wpdb->get_row($wpdb->prepare("SELECT image_path, lampiran FROM $table WHERE id = %d", intval($_POST['edit_id'])), ARRAY_A);
        if ($old) {
            $image_path = $old['image_path'];
            $lampiran_path = $old['lampiran'];
        }
    }
    // Handle image upload
    if (!empty($_FILES['image_upload']['name'])) {
        $uploaded = media_handle_upload('image_upload', 0);
        if (!is_wp_error($uploaded)) {
            $image_path = wp_get_attachment_url($uploaded);
        }
    }
    // Handle PDF upload
    if (!empty($_FILES['lampiran']['name'])) {
        $uploaded_pdf = media_handle_upload('lampiran', 0);
        if (!is_wp_error($uploaded_pdf)) {
            $lampiran_path = get_attached_file($uploaded_pdf);
        }
    }

    $table = $wpdb->prefix . 'salammu_notulenmu';
    // Ambil id_tingkat dari setting user sesuai tingkat yang dipilih
    $setting_table = $wpdb->prefix . 'salammu_notulenmu_setting';
    $settings = $wpdb->get_row($wpdb->prepare(
        "SELECT pwm, pdm, pcm, prm FROM $setting_table WHERE user_id = %d",
        $user_id
    ), ARRAY_A);
    $id_tingkat = 0;
    if ($settings) {
        if ($tingkat === 'wilayah') $id_tingkat = intval($settings['pwm']);
        elseif ($tingkat === 'daerah') $id_tingkat = intval($settings['pdm']);
        elseif ($tingkat === 'cabang') $id_tingkat = intval($settings['pcm']);
        elseif ($tingkat === 'ranting') $id_tingkat = intval($settings['prm']);
    }
    $data = [
        'user_id' => $user_id,
        'id_tingkat' => $id_tingkat,
        'tingkat' => $tingkat,
        'topik_rapat' => $topik_rapat,
        'tanggal_rapat' => $tanggal_rapat,
        'jam_mulai' => $jam_mulai,
        'jam_selesai' => $jam_selesai,
        'sifat_rapat' => $sifat_rapat,
        'tempat_rapat' => $tempat_rapat,
        'peserta_rapat' => $peserta_rapat,
        'notulen_rapat' => $notulen_rapat,
        'image_path' => $image_path,
        'lampiran' => $lampiran_path
    ];

    if (isset($_POST['edit_id']) && $_POST['edit_id']) {
        $result = $wpdb->update($table, $data, ['id' => intval($_POST['edit_id'])]);
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('handle_notulen_form UPDATE result: ' . print_r($result, true));
        }
        $redirect_url = admin_url('admin.php?page=notulenmu-list&updated=1');
    } else {
        $result = $wpdb->insert($table, $data);
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('handle_notulen_form INSERT result: ' . print_r($result, true));
        }
        $redirect_url = admin_url('admin.php?page=notulenmu-list&added=1');
    }
    wp_redirect($redirect_url);
    exit;
}

function notulenmu_admin_notices() {
    if (isset($_GET['added'])) {
        echo '<div class="notice notice-success is-dismissible"><p>Notulen berhasil ditambahkan.</p></div>';
    }
    if (isset($_GET['updated'])) {
        echo '<div class="notice notice-success is-dismissible"><p>Notulen berhasil diupdate.</p></div>';
    }
}

function get_pengurus_by_tingkat() {
    global $wpdb;
    $user_id = get_current_user_id();
    $tingkat = $_GET['tingkat'] ?? '';
    $selected_peserta = [];
    if (isset($_POST['selected_peserta'])) {
        $selected_peserta = json_decode(stripslashes($_POST['selected_peserta']), true);
        if (!is_array($selected_peserta)) $selected_peserta = [];
    }
    if (!$tingkat) {
        echo "<p>Pilih tingkat terlebih dahulu.</p>";
        wp_die();
    }
    $setting_table = $wpdb->prefix . 'salammu_notulenmu_setting';
    $settings = $wpdb->get_row($wpdb->prepare(
        "SELECT pwm, pdm, pcm, prm FROM $setting_table WHERE user_id = %d",
        $user_id
    ), ARRAY_A);
    if (!$settings) {
        echo "<p>Pengaturan wilayah tidak ditemukan.</p>";
        wp_die();
    }
    $id_tingkat_list = array_filter([$settings['pwm'], $settings['pdm'], $settings['pcm'], $settings['prm']]);
    if (empty($id_tingkat_list)) {
        echo "<p>Tidak ada data pengurus yang tersedia.</p>";
        wp_die();
    }
    $pengurus_table = $wpdb->prefix . 'salammu_data_pengurus';
    $placeholders = implode(',', array_fill(0, count($id_tingkat_list), '%d'));
    $query = "SELECT id, nama_lengkap_gelar, tingkat, jabatan FROM $pengurus_table WHERE tingkat = %s AND id_tingkat IN ($placeholders)";
    $query = $wpdb->prepare($query, array_merge([$tingkat], $id_tingkat_list));
    $pengurus = $wpdb->get_results($query);
    if (!empty($pengurus)) {
        echo '<table style="border-collapse: collapse; width: 100%;" border="1" cellpadding="5">';
        echo '<tr style="background-color: #f2f2f2; text-align: left;">';
        echo '<th style="border: 1px solid black; padding: 8px; width: 10px; text-align: center;">No</th>';
        echo '<th style="border: 1px solid black; padding: 8px; text-align: center;">Nama</th>';
        echo '<th style="border: 1px solid black; padding: 8px; text-align: center;">Tingkat</th>';
        echo '<th style="border: 1px solid black; padding: 8px; text-align: center;">Jabatan</th>';
        echo '<th style="border: 1px solid black; padding: 8px; text-align: center;">Kehadiran</th>';
        echo '</tr>';
        $no = 1;
        foreach ($pengurus as $p) {
            if (!isset($p->nama_lengkap_gelar)) continue;
            $checked = in_array($p->nama_lengkap_gelar, $selected_peserta) ? 'checked' : '';
            echo '<tr>';
            echo '<td style="border: 1px solid black; padding: 8px; text-align: center;">' . esc_html($no) . '</td>';
            echo '<td style="border: 1px solid black; padding: 8px;">' . esc_html($p->nama_lengkap_gelar) . '</td>';
            echo '<td style="border: 1px solid black; padding: 8px;">' . esc_html($p->tingkat) . '</td>';
            echo '<td style="border: 1px solid black; padding: 8px;">' . esc_html($p->jabatan) . '</td>';
            echo '<td style="border: 1px solid black; padding: 8px; text-align: center;">';
            echo '<input type="checkbox" name="peserta_rapat[]" value="' . esc_attr($p->nama_lengkap_gelar) . '" ' . $checked . '>';
            echo '</td>';
            echo '</tr>';
            $no++;
        }
        echo '</table>';
    } else {
        echo "<p style='color: red;'>Tidak ada pengurus pada tingkat ini.</p>";
    }
    wp_die();
}


add_action('admin_post_handle_notulen_form', 'handle_notulen_form');
add_action('admin_post_nopriv_handle_notulen_form', 'handle_notulen_form');
add_action('wp_ajax_get_pengurus_by_tingkat', 'get_pengurus_by_tingkat');
add_action('wp_ajax_nopriv_get_pengurus_by_tingkat', 'get_pengurus_by_tingkat');