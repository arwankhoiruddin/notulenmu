<?php
global $pagenow;

// If we're on the login page, return early
if ($pagenow === 'wp-login.php') {
    return;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_name']) && $_POST['form_name'] === 'notulenmu_add_form') {
    global $wpdb;

    // Get the data from the form
    $user_id = isset($_POST['user_id']) ? $_POST['user_id'] : null;
    $tingkat = isset($_POST['tingkat']) ? $_POST['tingkat'] : null;
    $topik_rapat = isset($_POST['topik_rapat']) ? $_POST['topik_rapat'] : null;
    $tanggal_rapat = isset($_POST['tanggal_rapat']) ? $_POST['tanggal_rapat'] : null;
    $tempat_rapat = isset($_POST['tempat_rapat']) ? $_POST['tempat_rapat'] : null;
    $peserta_rapat = isset($_POST['peserta_rapat']) ? $_POST['peserta_rapat'] : null;
    $notulen_rapat = isset($_POST['notulen_rapat']) ? $_POST['notulen_rapat'] : null;
    $image_upload = isset($_FILES['image_upload']) ? $_FILES['image_upload'] : null;

    echo "Hello";

    $img_path = '';
    if ($image_upload !== null && $image_upload['error'] === UPLOAD_ERR_OK) {
        $upload_dir = wp_upload_dir();
        $filename = uniqid() . '.' . pathinfo($image_upload['name'], PATHINFO_EXTENSION);
        $upload_file = $upload_dir['path'] . '/' . $filename;

        if (move_uploaded_file($image_upload['tmp_name'], $upload_file)) {
            // The file has been uploaded successfully
            $img_path = $upload_file;
        } else {
            // There was an error moving the uploaded file
        }
    }

    if ($user_id == null) {
        return;
    }
    
    $setting_table_name = $wpdb->prefix . 'salammu_notulenmu_setting';

    echo "SELECT pwm FROM $setting_table_name WHERE user_id = '$user_id'";

    if ($tingkat == 'wilayah') {
        $tingkat_id = $wpdb->get_var($wpdb->prepare("SELECT pwm FROM $setting_table_name WHERE user_id = %d", $user_id));
    } else if ($tingkat == 'daerah') {
        $tingkat_id = $wpdb->get_var($wpdb->prepare("SELECT pdm FROM $setting_table_name WHERE user_id = %d", $user_id));
    } else if ($tingkat == 'cabang') {
        $tingkat_id = $wpdb->get_var($wpdb->prepare("SELECT pcm FROM $setting_table_name WHERE user_id = %d", $user_id));
    } else if ($tingkat == 'ranting') {
        $tingkat_id = $wpdb->get_var($wpdb->prepare("SELECT prm FROM $setting_table_name WHERE user_id = %d", $user_id));
    } else {
        return;
    }

    if (empty($tingkat_id)) {
        wp_redirect(admin_url('admin.php?page=notulenmu-settings'));
        exit;
    }
    
    $table_name = $wpdb->prefix . 'salammu_notulenmu';
    // Insert the data into the table
    $result = $wpdb->insert(
        $table_name, // Table name
        array( // Data
            'user_id' => $user_id,
            'tingkat' => $tingkat,
            'id_tingkat' => $tingkat_id,
            'topik_rapat' => $topik_rapat,
            'tanggal_rapat' => $tanggal_rapat,
            'tempat_rapat' => $tempat_rapat,
            'peserta_rapat' => $peserta_rapat,
            'notulen_rapat' => $notulen_rapat,
            'image_path' => $img_path,
        ),
        array( // Data format
            '%d', // user_id
            '%s', // tingkat
            '%s', // tingkat_id
            '%s', // topik_rapat
            '%s', // tanggal_rapat
            '%s', // tempat_rapat
            '%s', // peserta_rapat
            '%s', // notulen_rapat
            '%s', // image_path
        )
    );
    if ($result !== false) {
        set_transient('notulenmu_admin_notice', 'The notulen was successfully added.', 5);
        if (!function_exists('wp_redirect')) {
            require_once(ABSPATH . WPINC . '/pluggable.php');
        }
        wp_redirect(admin_url('admin.php?page=notulenmu-list'));
        exit;
    } else {
        add_notice('error', 'There was an error adding the notulen.');
    }

    add_action('admin_notices', 'notulenmu_admin_notices');
    function notulenmu_admin_notices() {
        if ($message = get_transient('notulenmu_admin_notice')) {
            echo "<div class='notice notice-success is-dismissible'><p>$message</p></div>";
            delete_transient('notulenmu_admin_notice');
        }
    }
}
    
function notulenmu_add_page() {
    if (!current_user_can('edit_posts')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    // Check if we are editing an existing notulen
    $editing = isset($_GET['edit']);
    $logged_user = get_current_user_id();

    echo '<h1>' . ($editing ? 'View' : 'Tambah') . ' Notulen</h1>';

    $notulen = null;
    if ($editing) {
        global $wpdb;
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        $table_name = $wpdb->prefix . 'salammu_notulenmu';
        $notulen = $wpdb->get_row("SELECT * FROM $table_name WHERE id = $id AND user_id = $logged_user");
    }
    
    if ($notulen && $notulen->image_path) {
        $upload_dir = wp_upload_dir();
        $image_path = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $notulen->image_path);
        echo '<img src="' . esc_url($image_path) . '" alt="Image for ' . esc_attr($notulen->topik_rapat) . '" style="width: 200px; height: auto;" />';
    }
    
    // Form for adding or editing
    echo '<form method="post" enctype="multipart/form-data" action="' . esc_url(admin_url('admin-post.php')) . '">';
    echo '<input type="hidden" name="form_name" value="notulenmu_add_form">';
    echo '<input type="hidden" name="user_id" value="'. $logged_user. '">';
    echo '<input type="hidden" name="action" value="handle_notulen_form">';
    echo '<table class="form-table">';
    echo '<tbody>';
    echo '<tr>';
    echo '<th scope="row"><label for="tingkat">Tingkat</label></th>';
    echo '<td>';
    echo '<select name="tingkat" id="tingkat">';
    echo '<option value="wilayah"' . ($notulen && $notulen->tingkat == 'wilayah' ? ' selected' : '') . '>Pimpinan Wilayah</option>';
    echo '<option value="daerah"' . ($notulen && $notulen->tingkat == 'daerah' ? ' selected' : '') . '>Pimpinan Daerah</option>';
    echo '<option value="cabang"' . ($notulen && $notulen->tingkat == 'cabang' ? ' selected' : '') . '>Pimpinan Cabang</option>';
    echo '<option value="ranting"' . ($notulen && $notulen->tingkat == 'ranting' ? ' selected' : '') . '>Pimpinan Ranting</option>';
    echo '</select>';
    echo '</td>';
    echo '</tr>';
    echo '<tr>';
    echo '<th scope="row"><label for="topik_rapat">Topik Rapat</label></th>';
    echo '<td><input name="topik_rapat" id="topik_rapat" type="text" value="' . ($notulen ? esc_attr($notulen->topik_rapat) : '') . '" class="regular-text" /></td>';
    echo '</tr>';
    echo '<tr>';
    echo '<th scope="row"><label for="tanggal_rapat">Tanggal Rapat</label></th>';
    echo '<td><input name="tanggal_rapat" id="tanggal_rapat" type="date" value="' . ($notulen ? esc_attr($notulen->tanggal_rapat) : '') . '" class="regular-text" /></td>';
    echo '</tr>';
    echo '<tr>';
    echo '<th scope="row"><label for="tempat_rapat">Tempat Rapat</label></th>';
    echo '<td><input name="tempat_rapat" id="tempat_rapat" type="text" value="' . ($notulen ? esc_attr($notulen->tempat_rapat) : '') . '" class="regular-text" /></td>';
    echo '</tr>';
    echo '<tr>';
    echo '<th scope="row"><label for="peserta_rapat">Jumlah Peserta Rapat</label></th>';
    echo '<td><input name="peserta_rapat" id="peserta_rapat" type="text" value="' . ($notulen ? esc_attr($notulen->peserta_rapat) : '') . '" class="regular-text" /></td>';
    echo '</tr>';
    echo '<tr>';
    echo '<th scope="row"><label for="image_upload">Foto Kegiatan</label></th>';
    echo '<td><input name="image_upload" id="image_upload" type="file" class="regular-text" /></td>';
    echo '</tr>';
    echo '<tr>';
    echo '<th scope="row"><label for="notulen_rapat">Notulen Rapat</label></th>';
    echo '<td><textarea name="notulen_rapat" id="notulen_rapat" rows="10" class="regular-text">' . ($notulen ? esc_textarea($notulen->notulen_rapat) : '') . '</textarea></td>';
    echo '</tr>';
    echo '</tbody>';
    echo '</table>';
    if (!$editing) {
        echo '<input type="submit" value="Simpan Notulen" class="button-primary">';
    }
    echo '</form>';

}