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

    $editing = isset($_GET['edit']);
    $logged_user = get_current_user_id();

    echo '<h1>' . ($editing ? 'Edit' : 'Tambah') . ' Kegiatan</h1>';

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
?>
    <form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="p-6 mr-4 bg-white shadow-md rounded-lg">
        <input type="hidden" name="form_name" value="kegiatanmu_add_form">
        <input type="hidden" name="user_id" value="<?php echo $logged_user; ?>">
        <input type="hidden" name="action" value="handle_kegiatan_form">
        <?php if ($editing && $kegiatan) { ?>
            <input type="hidden" name="edit_id" value="<?php echo esc_attr($kegiatan->id); ?>">
        <?php } ?>

        <div class="grid gap-7 w-full">
            <div class="flex flex-col space-y-2">
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-blue-500" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M7 9.5l-3 1.5l8 4l8 -4l-3 -1.5" /><path d="M4 15l8 4l8 -4" /><path d="M12 11v-7" /><path d="M9 7l3 -3l3 3" /></svg>
                    <label class="block font-semibold text-[15px]">Tingkat</label>
                </div>
                <select name="tingkat" id="tingkat" class="w-full p-2 border rounded-md" style="min-width: 100%;">
                    <option class="w-full">Pilih Tingkat</option>
                    <option value="wilayah" <?php echo ($kegiatan && $kegiatan->tingkat == 'wilayah' ? 'selected' : ''); ?>>Pimpinan Wilayah</option>
                    <option value="daerah" <?php echo ($kegiatan && $kegiatan->tingkat == 'daerah' ? 'selected' : ''); ?>>Pimpinan Daerah</option>
                    <option value="cabang" <?php echo ($kegiatan && $kegiatan->tingkat == 'cabang' ? 'selected' : ''); ?>>Pimpinan Cabang</option>
                    <option value="ranting" <?php echo ($kegiatan && $kegiatan->tingkat == 'ranting' ? 'selected' : ''); ?>>Pimpinan Ranting</option>
                </select>
            </div>

            <div class="flex flex-col space-y-2">
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-blue-500" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M3 4m0 2a2 2 0 0 1 2 -2h14a2 2 0 0 1 2 2v12a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2z" /><path d="M7 8h10" /><path d="M7 12h10" /><path d="M7 16h10" /></svg>
                    <label class="block font-semibold text-[15px]">Nama Kegiatan</label>
                </div>
                <input name="nama_kegiatan" id="nama_kegiatan" type="text" value="<?php echo ($kegiatan ? esc_attr($kegiatan->nama_kegiatan) : ''); ?>" class="w-full p-2 border rounded-md" />
            </div>

            <div class="flex flex-col space-y-2">
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-blue-500" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M4 5m0 2a2 2 0 0 1 2 -2h12a2 2 0 0 1 2 2v12a2 2 0 0 1 -2 2h-12a2 2 0 0 1 -2 -2z" /><path d="M16 3l0 4" /><path d="M8 3l0 4" /><path d="M4 11l16 0" /><path d="M8 15h2v2h-2z" /></svg>
                    <label class="block font-semibold text-[15px]">Tanggal Kegiatan</label>
                </div>
                <input name="tanggal_kegiatan" id="tanggal_kegiatan" type="date" value="<?php echo ($kegiatan ? esc_attr($kegiatan->tanggal_kegiatan) : ''); ?>" class="w-full p-2 border rounded-md" />
            </div>

            <div class="flex flex-col space-y-2">
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-blue-500" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M9 11a3 3 0 1 0 6 0a3 3 0 0 0 -6 0" /><path d="M17.657 16.657l-4.243 4.243a2 2 0 0 1 -2.827 0l-4.244 -4.243a8 8 0 1 1 11.314 0z" /></svg>
                    <label class="block font-semibold text-[15px]">Tempat Kegiatan</label>
                </div>
                <input name="tempat_kegiatan" id="tempat_kegiatan" type="text" value="<?php echo ($kegiatan ? esc_attr($kegiatan->tempat_kegiatan) : ''); ?>" class="w-full p-2 border rounded-md" />
            </div>

            <div class="flex flex-col space-y-2">
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-blue-500" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M8 7a4 4 0 1 0 8 0a4 4 0 0 0 -8 0" /><path d="M6 21v-2a4 4 0 0 1 4 -4h4a4 4 0 0 1 4 4v2" /></svg>
                    <label class="font-semibold text-[15px]">Jumlah Peserta</label>
                </div>
                <input type="text" name="peserta_kegiatan" id="peserta_kegiatan" value="<?php echo esc_attr($kegiatan->peserta_kegiatan ?? ''); ?>" class="w-full p-2 border rounded-md">
            </div>

            <div class="flex flex-col space-y-2">
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-blue-500" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M15 8h.01" /><path d="M3 6a3 3 0 0 1 3 -3h12a3 3 0 0 1 3 3v12a3 3 0 0 1 -3 3h-12a3 3 0 0 1 -3 -3v-12z" /><path d="M3 16l5 -5c.928 -.893 2.072 -.893 3 0l5 5" /><path d="M14 14l1 -1c.928 -.893 2.072 -.893 3 0l3 3" /></svg>
                    <label class="font-semibold text-[15px]">Foto Kegiatan</label>
                </div>
                <label class="flex flex-col items-center justify-center w-full h-32 border border-dashed rounded-md cursor-pointer hover:bg-gray-100">
                    <svg class="text-blue-500" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2 -2v-2" /><path d="M7 9l5 -5l5 5" /><path d="M12 4l0 12" /></svg>
                    <span id="image-upload-text" class="mt-2 text-gray-600">Upload File</span>
                    <input name="image_upload" id="image_upload" type="file" class="hidden" onchange="previewImage(this, 'image-preview', 'image-upload-text')" />
                </label>
                <?php if (!empty($image_path)) { ?>
                    <img id="image-preview" src="<?php echo esc_url($image_path); ?>" class="mt-2 w-70">
                <?php } else { ?>
                    <img id="image-preview" src="" class="mt-2 w-40 hidden">
                <?php } ?>
            </div>

            <div class="flex flex-col space-y-2 ">
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-blue-500" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M9 5h-2a2 2 0 0 0 -2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2 -2v-12a2 2 0 0 0 -2 -2h-2" /><path d="M9 3m0 2a2 2 0 0 1 2 -2h2a2 2 0 0 1 2 2v0a2 2 0 0 1 -2 2h-2a2 2 0 0 1 -2 -2z" /></svg>
                    <label class="block font-semibold text-[15px]">Detail Kegiatan</label>
                </div>
                <textarea name="detail_kegiatan" id="detail_kegiatan" rows="5" class="w-full p-2 border rounded-md"><?php echo ($kegiatan ? esc_textarea($kegiatan->detail_kegiatan) : ''); ?></textarea>
            </div>
        </div>

        <?php if (!$editing) { ?>
            <div class="flex justify-end mt-9">
                <input type="submit" value="Simpan Kegiatan" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-md">
            </div>
        <?php } else { ?>
            <div class="flex justify-end mt-9">
                <input type="submit" value="Update Kegiatan" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-md">
            </div>
        <?php } ?>
    </form>

    <script>
        function previewImage(input, imgId, textId) {
            const file = input.files[0];

            if (!file) {
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
