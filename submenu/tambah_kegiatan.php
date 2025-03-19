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
    function kegiatanmu_admin_notice()
    {
        if ($message = get_transient('kegiatanmu_admin_notice')) {
            echo "<div class='notice notice-success is-dismissible'><p>$message</p></div>";
            delete_transient('kegiatanmu_admin_notice');
        }
    }
}

function tambah_kegiatan_page()
{
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
    }

    // Form for adding or editing
?>
    <form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="p-6 mr-4 bg-white p-6 rounded-lg shadow-md">
        <input type="hidden" name="form_name" value="kegiatanmu_add_form">
        <input type="hidden" name="user_id" value="<?php echo $logged_user; ?>">
        <input type="hidden" name="action" value="handle_kegiatan_form">

        <div class="grid gap-7 w-full">
            <div class="space-y-3">
                <label for="tingkat" class="block text-sm font-medium text-gray-700">Tingkat</label>
                <select name="tingkat" id="tingkat" class="w-full mt-1 p-2 border rounded-lg focus:ring focus:ring-blue-300">
                    <option value="wilayah" <?php echo ($kegiatan && $kegiatan->tingkat == 'wilayah') ? 'selected' : ''; ?>>Pimpinan Wilayah</option>
                    <option value="daerah" <?php echo ($kegiatan && $kegiatan->tingkat == 'daerah') ? 'selected' : ''; ?>>Pimpinan Daerah</option>
                    <option value="cabang" <?php echo ($kegiatan && $kegiatan->tingkat == 'cabang') ? 'selected' : ''; ?>>Pimpinan Cabang</option>
                    <option value="ranting" <?php echo ($kegiatan && $kegiatan->tingkat == 'ranting') ? 'selected' : ''; ?>>Pimpinan Ranting</option>
                </select>
            </div>

            <div class="space-y-3">
                <label for="nama_kegiatan" class="block text-sm font-medium text-gray-700">Nama Kegiatan</label>
                <input type="text" name="nama_kegiatan" id="nama_kegiatan" value="<?php echo esc_attr($kegiatan->nama_kegiatan ?? ''); ?>" class="w-full mt-1 p-2 border rounded-lg focus:ring focus:ring-blue-300">
            </div>

            <div class="space-y-3">
                <label for="tanggal_kegiatan" class="block text-sm font-medium text-gray-700">Tanggal Kegiatan</label>
                <input type="date" name="tanggal_kegiatan" id="tanggal_kegiatan" value="<?php echo esc_attr($kegiatan->tanggal_kegiatan ?? ''); ?>" class="w-full mt-1 p-2 border rounded-lg focus:ring focus:ring-blue-300">
            </div>

            <div class="space-y-3">
                <label for="tempat_kegiatan" class="block text-sm font-medium text-gray-700">Tempat Kegiatan</label>
                <input type="text" name="tempat_kegiatan" id="tempat_kegiatan" value="<?php echo esc_attr($kegiatan->tempat_kegiatan ?? ''); ?>" class="w-full mt-1 p-2 border rounded-lg focus:ring focus:ring-blue-300">
            </div>

            <div class="space-y-3">
                <label for="peserta_kegiatan" class="block text-sm font-medium text-gray-700">Jumlah Peserta</label>
                <input type="text" name="peserta_kegiatan" id="peserta_kegiatan" value="<?php echo esc_attr($kegiatan->peserta_kegiatan ?? ''); ?>" class="w-full mt-1 p-2 border rounded-lg focus:ring focus:ring-blue-300">
            </div>

            <label class="flex flex-col space-y-3">
                <label class="font-semibold text-[15px]">Upload Gambar</label>
                <div>
                    <label class="flex flex-col items-center justify-center w-full h-32 border border-dashed rounded-md cursor-pointer hover:bg-gray-100">
                        <svg class="text-blue-500" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-upload">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                            <path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2 -2v-2" />
                            <path d="M7 9l5 -5l5 5" />
                            <path d="M12 4l0 12" />
                        </svg>
                        <span id="image-upload-text" class="mt-2 text-gray-600">Upload Gambar</span>
                        <input name="image_upload" id="image_upload" type="file" class="hidden" onchange="previewImage(this, 'image-preview', 'image-upload-text')" />
                    </label>
                </div>

                <?php if (!empty($image_path)) { ?>
                    <img id="image-preview" src="<?php echo esc_url($image_path); ?>" class="mt-2 w-70">
                <?php } else { ?>
                    <img id="image-preview" src="" class="mt-2 w-40 hidden">
                <?php } ?>
            </label>

            <div class="space-y-3">
                <label for="detail_kegiatan" class="block text-sm font-medium text-gray-700">Detail Kegiatan</label>
                <textarea name="detail_kegiatan" id="detail_kegiatan" rows="5" class="w-full mt-1 p-2 border rounded-lg focus:ring focus:ring-blue-300"><?php echo esc_textarea($kegiatan->detail_kegiatan ?? ''); ?></textarea>
            </div>
        </div>

        <?php if (!$editing) { ?>
            <button type="submit" class="w-full bg-blue-600 mt-7 text-white p-2 rounded-md hover:bg-blue-700">Upload Kegiatan</button>
        <?php } ?>
    </form>

<?php
?>
<script>
    function previewImage(input, imgId, textId) {
            const file = input.files[0];

            if (!file) {
                // Reset jika tidak ada file yang dipilih
                document.getElementById(imgId).classList.add('hidden');
                document.getElementById(imgId).src = "";
                document.getElementById(textId).textContent = "Upload File";
                return;
            }

            if (!file.type.startsWith("image/")) {
                alert("Harap pilih file gambar yang valid.");
                input.value = "";
                return;
            }

            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById(imgId).src = e.target.result;
                document.getElementById(imgId).classList.remove('hidden');
                document.getElementById(textId).textContent = file.name;
            };

            reader.readAsDataURL(file);
        }
</script>
<?php
}
