<?php
global $pagenow;

// If we're on the login page, return early
if ($pagenow === 'wp-login.php') {
    return;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_name']) && $_POST['form_name'] === 'pengurus_add_form') {
    global $wpdb;

    // Get the data from the form
    $user_id = isset($_POST['user_id']) ? $_POST['user_id'] : null;
    $tingkat_pengurus = isset($_POST['tingkat_pengurus']) ? $_POST['tingkat_pengurus'] : null;
    $valid_tingkat = ['wilayah', 'daerah', 'cabang', 'ranting'];
    if (!in_array($tingkat_pengurus, $valid_tingkat, true)) {
        // Coba ambil tingkat dari database setting user
        global $wpdb;
        $setting_table_name = $wpdb->prefix . 'salammu_notulenmu_setting';
        $row = $wpdb->get_row($wpdb->prepare("SELECT pwm, pdm, pcm, prm FROM $setting_table_name WHERE user_id = %d", $user_id));
        if ($row) {
            if ($row->pwm) $tingkat_pengurus = 'wilayah';
            else if ($row->pdm) $tingkat_pengurus = 'daerah';
            else if ($row->pcm) $tingkat_pengurus = 'cabang';
            else if ($row->prm) $tingkat_pengurus = 'ranting';
        }
    }
    // Jika masih tidak valid, tampilkan pesan error
    if (!in_array($tingkat_pengurus, $valid_tingkat, true)) {
        wp_die('Tingkat pengurus tidak valid. Silakan pilih ulang tingkat pada form.');
    }
    $id_tingkat_pengurus = isset($_POST['id_tingkat_pengurus']) ? $_POST['id_tingkat_pengurus'] : null;
    $nama_lengkap = isset($_POST['nama_lengkap']) ? $_POST['nama_lengkap'] : null;
    $jabatan = isset($_POST['jabatan']) ? $_POST['jabatan'] : null;
    $no_np = isset($_POST['no_hp']) ? $_POST['no_hp'] : null;
    $edit_id = isset($_POST['edit_id']) ? intval($_POST['edit_id']) : null;

    $table_name = $wpdb->prefix . 'salammu_data_pengurus';

    if ($user_id == null) {
        return;
    }

    // Ambil id_tingkat dari POST, bukan dari setting user
    if (!$id_tingkat_pengurus) {
        // fallback lama jika id_tingkat tidak dikirim
        $setting_table_name = $wpdb->prefix . 'salammu_notulenmu_setting';
        if ($tingkat_pengurus == 'wilayah') {
            $row = $wpdb->get_row($wpdb->prepare("SELECT pwm FROM $setting_table_name WHERE user_id = %d", $user_id));
            $id_tingkat_pengurus = $row ? $row->pwm : null;
        } else if ($tingkat_pengurus == 'daerah') {
            $row = $wpdb->get_row($wpdb->prepare("SELECT pdm FROM $setting_table_name WHERE user_id = %d", $user_id));
            $id_tingkat_pengurus = $row ? $row->pdm : null;
        } else if ($tingkat_pengurus == 'cabang') {
            $row = $wpdb->get_row($wpdb->prepare("SELECT pcm FROM $setting_table_name WHERE user_id = %d", $user_id));
            $id_tingkat_pengurus = $row ? $row->pcm : null;
        } else if ($tingkat_pengurus == 'ranting') {
            $row = $wpdb->get_row($wpdb->prepare("SELECT prm FROM $setting_table_name WHERE user_id = %d", $user_id));
            $id_tingkat_pengurus = $row ? $row->prm : null;
        } else {
            return;
        }
    }

    if (is_null($id_tingkat_pengurus)) {
        if (!function_exists('wp_redirect')) {
            require_once(ABSPATH . WPINC . '/pluggable.php');
        }
        wp_redirect(admin_url('admin.php?page=pengurus-add'));
        echo "null";
        exit;
    }

    if ($edit_id) {
        $result = $wpdb->update(
            $table_name,
            array(
                'user_id' => $user_id,
                'tingkat' => $tingkat_pengurus,
                'id_tingkat' => $id_tingkat_pengurus,
                'nama_lengkap_gelar' => $nama_lengkap,
                'jabatan' => $jabatan,
                'no_hp' => $no_np,
            ),
            array('id' => $edit_id),
            array(
                '%d',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
            ),
            array('%d')
        );
    } else {
        $result = $wpdb->insert(
            $table_name,
            array(
                'user_id' => $user_id,
                'tingkat' => $tingkat_pengurus,
                'id_tingkat' => $id_tingkat_pengurus,
                'nama_lengkap_gelar' => $nama_lengkap,
                'jabatan' => $jabatan,
                'no_hp' => $no_np,
            ),
            array(
                '%d',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
            )
        );
    }

    if ($result === false) {
        echo "Error in SQL: " . $wpdb->last_error;
        exit;
    }

    if ($result !== false) {
        set_transient('notulenmu_admin_notice', 'The notulen was successfully ' . ($edit_id ? 'updated' : 'added') . '.', 5);
        if (!function_exists('wp_redirect')) {
            require_once(ABSPATH . WPINC . '/pluggable.php');
        }
        if ($edit_id) {
            wp_redirect(admin_url('admin.php?page=pengurus-list&tingkat_pengurus=' . $id_tingkat_pengurus));
        } else {
            wp_redirect(admin_url('admin.php?page=pengurus-list&tingkat_pengurus=' . $id_tingkat_pengurus));
        }
        exit;
    } else {
        set_transient('notulenmu_admin_notice', 'There was an error ' . ($edit_id ? 'updating' : 'adding') . ' the notulen.', 5);
    }

    add_action('admin_notices', 'notulenmu_admin_notices');
    function notulenmu_admin_notices()
    {
        if ($message = get_transient('notulenmu_admin_notice')) {
            echo "<div class='notice notice-success is-dismissible'><p>$message</p></div>";
            delete_transient('notulenmu_admin_notice');
        }
    }
}

function pengurus_add_page()
{
    if (!current_user_can('edit_posts')) {
        wp_die(('You do not have sufficient permissions to access this page.'));
    }

    $tingkat_pengurus = '';
    $id_tingkat_pengurus = '';
    if (isset($_GET['tingkat_pengurus'])) {
        $id_tingkat_pengurus = $_GET['tingkat_pengurus'];
        // Mapping id_tingkat ke nama tingkat
        global $wpdb;
        $setting_table_name = $wpdb->prefix . 'salammu_notulenmu_setting';
        $row = $wpdb->get_row($wpdb->prepare("SELECT pwm, pdm, pcm, prm FROM $setting_table_name WHERE user_id = %d", get_current_user_id()));
        if ($row) {
            if ($row->pwm && $row->pwm == $id_tingkat_pengurus) $tingkat_pengurus = 'wilayah';
            else if ($row->pdm && $row->pdm == $id_tingkat_pengurus) $tingkat_pengurus = 'daerah';
            else if ($row->pcm && $row->pcm == $id_tingkat_pengurus) $tingkat_pengurus = 'cabang';
            else if ($row->prm && $row->prm == $id_tingkat_pengurus) $tingkat_pengurus = 'ranting';
        }
    }

    $editing = isset($_GET['edit']);
    $logged_user = get_current_user_id();

    echo '<h1 class="px-6">' . ($editing ? 'Edit' : 'Tambah') . ' Pengurus</h1>';

    $pengurus = null;
    if ($editing) {
        global $wpdb;
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        $table_name = $wpdb->prefix . 'salammu_data_pengurus';
        $pengurus = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d AND user_id = %d", $id, $logged_user));
        $tingkat_pengurus = $pengurus ? $pengurus->tingkat : $tingkat_pengurus;
    }

?>
    <div class="px-6">
        <div class="mb-4">
            <a href="<?php echo esc_url(admin_url('admin.php?page=pengurus-list')); ?>" class="inline-block bg-gray-300 hover:bg-gray-400 text-gray-800 font-semibold py-2 px-4 rounded-md mr-2">
                &larr; Kembali
            </a>
        </div>
        <form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="p-6 mr-4 bg-white shadow-md rounded-lg">
            <input type="hidden" name="form_name" value="pengurus_add_form">
            <input type="hidden" name="user_id" value="<?php echo $logged_user; ?>">
            <?php if ($editing) : ?>
                <input type="hidden" name="edit_id" value="<?php echo $pengurus->id; ?>">
            <?php endif; ?>
            <input type="hidden" name="action" value="handle_notulen_form">

            <div class="grid gap-7 w-full">
                <div class="flex flex-col space-y-2">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-blue-500" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M7 9.5l-3 1.5l8 4l8 -4l-3 -1.5" /><path d="M4 15l8 4l8 -4" /><path d="M12 11v-7" /><path d="M9 7l3 -3l3 3" /></svg>
                        <label for="tingkat_pengurus" class="block font-semibold text-[15px]">Tingkat</label>
                    </div>
                    <select name="tingkat_pengurus" id="tingkat_pengurus" class="w-full p-2 border rounded-md">
                        <option value="wilayah" <?php echo (($pengurus && $pengurus->tingkat == 'wilayah') || $tingkat_pengurus == 'wilayah') ? 'selected' : ''; ?>>Pimpinan Wilayah</option>
                        <option value="daerah" <?php echo (($pengurus && $pengurus->tingkat == 'daerah') || $tingkat_pengurus == 'daerah') ? 'selected' : ''; ?>>Pimpinan Daerah</option>
                        <option value="cabang" <?php echo (($pengurus && $pengurus->tingkat == 'cabang') || $tingkat_pengurus == 'cabang') ? 'selected' : ''; ?>>Pimpinan Cabang</option>
                        <option value="ranting" <?php echo (($pengurus && $pengurus->tingkat == 'ranting') || $tingkat_pengurus == 'ranting') ? 'selected' : ''; ?>>Pimpinan Ranting</option>
                    </select>
                </div>

                <div class="flex flex-col space-y-2">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-blue-500" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M8 7a4 4 0 1 0 8 0a4 4 0 0 0 -8 0" /><path d="M6 21v-2a4 4 0 0 1 4 -4h4a4 4 0 0 1 4 4v2" /></svg>
                        <label for="nama_lengkap" class="block font-semibold text-[15px]">Nama Lengkap</label>
                    </div>
                    <input name="nama_lengkap" id="nama_lengkap" type="text" value="<?php echo $pengurus ? esc_attr($pengurus->nama_lengkap_gelar) : ''; ?>" class="w-full p-2 border rounded-md" />
                </div>

                <div class="flex flex-col space-y-2">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-blue-500" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M12 12v-7" /><path d="M9 7l3 -3l3 3" /><path d="M7 9.5l-3 1.5l8 4l8 -4l-3 -1.5" /><path d="M4 15l8 4l8 -4" /></svg>
                        <label for="jabatan" class="block font-semibold text-[15px]">Jabatan</label>
                    </div>
                    <input name="jabatan" id="jabatan" type="text" value="<?php echo $pengurus ? esc_attr($pengurus->jabatan) : ''; ?>" class="w-full p-2 border rounded-md" />
                </div>

                <div class="flex flex-col space-y-2">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-blue-500" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2 -2v-2" /><path d="M7 9l5 -5l5 5" /><path d="M12 4l0 12" /></svg>
                        <label for="no_hp" class="block font-semibold text-[15px]">Nomor HP</label>
                    </div>
                    <input name="no_hp" id="no_hp" type="text" value="<?php echo $pengurus ? esc_attr($pengurus->no_hp) : ''; ?>" class="w-full p-2 border rounded-md" />
                </div>
            </div>

            <div class="flex justify-end mt-9">
                <button type="submit" class="bg-gray-400 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded-md">
                    <?php echo $editing ? 'Update' : 'Simpan'; ?> Pengurus
                </button>
            </div>
        </form>
    </div>
    <!-- Handler JS untuk tingkat_pengurus dihapus sesuai permintaan -->
    <?php
}


function get_data_pengurus()
{
    global $wpdb;
    $user_id = get_current_user_id();
    $tingkat_pengurus = $_GET['tingkat_pengurus'] ?? '';

    if (!$tingkat_pengurus) {
        echo "<p>Pilih tingkat terlebih dahulu.</p>";
        wp_die();
    }

    // Ambil pengaturan wilayah user
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

    // Query ke data_pengurus
    $pengurus_table = $wpdb->prefix . 'salammu_data_pengurus';
    $placeholders = implode(',', array_fill(0, count($id_tingkat_list), '%d'));

    $query = "SELECT id, nama_lengkap_gelar, jabatan 
              FROM $pengurus_table 
              WHERE tingkat = %s AND id_tingkat IN ($placeholders)";

    $query = $wpdb->prepare($query, array_merge([$tingkat_pengurus], $id_tingkat_list));
    echo $query;

    $pengurus = $wpdb->get_results($query);

    // Tabel pengurus dihapus dari sini. Silakan gunakan fungsi ini di menu terpisah "Pengurus" untuk menampilkan data pengurus.
    if (!empty($pengurus)) {
        echo "<p class='text-green-500'>Data pengurus tersedia. Silakan lihat di menu Pengurus.</p>";
    } else {
        echo "<p class='text-red-500'>Tidak ada pengurus pada tingkat ini.</p>";
    }

    wp_die();
}

add_action('wp_ajax_get_data_pengurus', 'get_data_pengurus');
add_action('wp_ajax_nopriv_get_data_pengurus', 'get_data_pengurus');

function handle_delete_pengurus()
{
    if (!isset($_GET['id']) || !isset($_GET['tingkat_pengurus'])) {
        wp_die('Invalid request.');
    }

    $id = intval($_GET['id']);
    $tingkat_pengurus = sanitize_text_field($_GET['tingkat_pengurus']);
    global $wpdb;
    $table_name = $wpdb->prefix . 'salammu_data_pengurus';

    $result = $wpdb->delete($table_name, array('id' => $id), array('%d'));

    if ($result === false) {
        wp_die('Error deleting record: ' . $wpdb->last_error);
    }

    if (!function_exists('wp_redirect')) {
        require_once(ABSPATH . WPINC . '/pluggable.php');
    }
    wp_redirect(admin_url('admin.php?page=pengurus-add&tingkat_pengurus=' . $tingkat_pengurus));
    exit;
}

add_action('admin_post_delete_pengurus', 'handle_delete_pengurus');
?>