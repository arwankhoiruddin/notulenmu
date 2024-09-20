<?php
global $pagenow;

// If we're on the login page, return early
if ($pagenow === 'wp-login.php') {
    return;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_name']) && $_POST['form_name'] === 'kegiatanmu_add_form') {
    global $wpdb;

    // Get the data from the form
    $user_id = isset($_POST['user_id']) ? $_POST['user_id'] : null;
    $tingkat = isset($_POST['tingkat']) ? $_POST['tingkat'] : null;
    $nama_kegiatan = isset($_POST['nama_kegiatan']) ? $_POST['nama_kegiatan'] : null;
    $tanggal_kegiatan = isset($_POST['tanggal_kegiatan']) ? $_POST['tanggal_kegiatan'] : null;
    $tempat_kegiatan = isset($_POST['tempat_kegiatan']) ? $_POST['tempat_kegiatan'] : null;
    $peserta_kegiatan = isset($_POST['peserta_kegiatan']) ? $_POST['peserta_kegiatan'] : null;
    $detail_kegiatan = isset($_POST['detail_kegiatan']) ? $_POST['detail_kegiatan'] : null;
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
        if (!function_exists('wp_redirect')) {
            require_once(ABSPATH . WPINC . '/pluggable.php');
        }
        wp_redirect(admin_url('admin.php?page=notulenmu-settings'));
        exit;
    }
    
    $table_name = $wpdb->prefix . 'salammu_kegiatanmu';
    // Insert the data into the table
    $result = $wpdb->insert(
        $table_name, // Table name
        array( // Data
            'user_id' => $user_id,
            'tingkat' => $tingkat,
            'id_tingkat' => $tingkat_id,
            'nama_kegiatan' => $nama_kegiatan,
            'tanggal_kegiatan' => $tanggal_kegiatan,
            'tempat_kegiatan' => $tempat_kegiatan,
            'peserta_kegiatan' => $peserta_kegiatan,
            'detail_kegiatan' => $detail_kegiatan,
            'image_path' => $img_path,
        ),
        array( // Data format
            '%d', // user_id
            '%s', // tingkat
            '%s', // tingkat_id
            '%s', // nama_kegiatan
            '%s', // tanggal_kegiatan
            '%s', // tempat_kegiatan
            '%s', // peserta_kegiatan
            '%s', // detail_kegiatan
            '%s', // image_path
        )
    );
    if ($result !== false) {
        set_transient('kegiatanmu_admin_notice', 'The kegiatan was successfully added.', 5);
        if (!function_exists('wp_redirect')) {
            require_once(ABSPATH . WPINC . '/pluggable.php');
        }
        wp_redirect(admin_url('admin.php?page=kegiatanmu-list'));
        exit;
    } else {
        set_transient('kegiatanmu_admin_notice', 'There was an error adding the kegiatan.', 5);
    }

    add_action('admin_notices', 'kegiatanmu_admin_notice');
    function kegiatanmu_admin_notice() {
        if ($message = get_transient('kegiatanmu_admin_notice')) {
            echo "<div class='notice notice-success is-dismissible'><p>$message</p></div>";
            delete_transient('kegiatanmu_admin_notice');
        }
    }
}
    
function tambah_kegiatan_page() {
    if (!current_user_can('edit_posts')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    // Check if we are editing an existing kegiatan
    $editing = isset($_GET['edit']);
    $logged_user = get_current_user_id();

    echo '<h1>' . ($editing ? 'View' : 'Tambah') . ' Kegiatan</h1>';

    $kegiatan = null;
    if ($editing) {
        global $wpdb;
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        $table_name = $wpdb->prefix . 'salammu_kegiatanmu';
        $kegiatan = $wpdb->get_row("SELECT * FROM $table_name WHERE id = $id AND user_id = $logged_user");
    }
    
    if ($kegiatan && $kegiatan->image_path) {
        $upload_dir = wp_upload_dir();
        $image_path = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $kegiatan->image_path);
        echo '<img src="' . esc_url($image_path) . '" alt="Image for ' . esc_attr($kegiatan->nama_kegiatan) . '" style="width: 200px; height: auto;" />';
    }
    
    // Form for adding or editing
    echo '<form method="post" enctype="multipart/form-data" action="' . esc_url(admin_url('admin-post.php')) . '">';
    echo '<input type="hidden" name="form_name" value="kegiatanmu_add_form">';
    echo '<input type="hidden" name="user_id" value="'. $logged_user. '">';
    echo '<input type="hidden" name="action" value="handle_kegiatan_form">';
    echo '<table class="form-table">';
    echo '<tbody>';
    echo '<tr>';
    echo '<th scope="row"><label for="tingkat">Tingkat</label></th>';
    echo '<td>';
    echo '<select name="tingkat" id="tingkat">';
    echo '<option value="wilayah"' . ($kegiatan && $kegiatan->tingkat == 'wilayah' ? ' selected' : '') . '>Pimpinan Wilayah</option>';
    echo '<option value="daerah"' . ($kegiatan && $kegiatan->tingkat == 'daerah' ? ' selected' : '') . '>Pimpinan Daerah</option>';
    echo '<option value="cabang"' . ($kegiatan && $kegiatan->tingkat == 'cabang' ? ' selected' : '') . '>Pimpinan Cabang</option>';
    echo '<option value="ranting"' . ($kegiatan && $kegiatan->tingkat == 'ranting' ? ' selected' : '') . '>Pimpinan Ranting</option>';
    echo '</select>';
    echo '</td>';
    echo '</tr>';
    echo '<tr>';
    echo '<th scope="row"><label for="nama_kegiatan">Nama Kegiatan</label></th>';
    echo '<td><input name="nama_kegiatan" id="nama_kegiatan" type="text" value="' . ($kegiatan ? esc_attr($kegiatan->nama_kegiatan) : '') . '" class="regular-text" /></td>';
    echo '</tr>';
    echo '<tr>';
    echo '<th scope="row"><label for="tanggal_kegiatan">Tanggal Kegiatan</label></th>';
    echo '<td><input name="tanggal_kegiatan" id="tanggal_kegiatan" type="date" value="' . ($kegiatan ? esc_attr($kegiatan->tanggal_kegiatan) : '') . '" class="regular-text" /></td>';
    echo '</tr>';
    echo '<tr>';
    echo '<th scope="row"><label for="tempat_kegiatan">Tempat Kegiatan</label></th>';
    echo '<td><input name="tempat_kegiatan" id="tempat_kegiatan" type="text" value="' . ($kegiatan ? esc_attr($kegiatan->tempat_kegiatan) : '') . '" class="regular-text" /></td>';
    echo '</tr>';
    echo '<tr>';
    echo '<th scope="row"><label for="peserta_kegiatan">Jumlah Peserta</label></th>';
    echo '<td><input name="peserta_kegiatan" id="peserta_kegiatan" type="text" value="' . ($kegiatan ? esc_attr($kegiatan->peserta_kegiatan) : '') . '" class="regular-text" /></td>';
    echo '</tr>';
    echo '<tr>';
    echo '<th scope="row"><label for="image_upload">Upload Image</label></th>';
    echo '<td><input name="image_upload" id="image_upload" type="file" class="regular-text" /></td>';
    echo '</tr>';
    echo '<tr>';
    echo '<th scope="row"><label for="detail_kegiatan">Detail Kegiatan</label></th>';
    echo '<td><textarea name="detail_kegiatan" id="detail_kegiatan" rows="10" class="regular-text">' . ($kegiatan ? esc_textarea($kegiatan->detail_kegiatan) : '') . '</textarea></td>';
    echo '</tr>';
    echo '</tbody>';
    echo '</table>';
    if (!$editing) {
        echo '<input type="submit" value="Upload Kegiatan" class="button-primary">';
    }
    echo '</form>';

}