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
        wp_redirect(admin_url('admin.php?page=pengurus-add&tingkat_pengurus=' . $tingkat_pengurus));
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

    $tingkat_pengurus = isset($_GET['tingkat_pengurus']) ? $_GET['tingkat_pengurus'] : '';

    $editing = isset($_GET['edit']);
    $logged_user = get_current_user_id();

    echo '<h1 class="px-6">' . ($editing ? 'Edit' : 'Tambah') . ' Pengurus</h1>';

    $pengurus = null;
    if ($editing) {
        global $wpdb;
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        $table_name = $wpdb->prefix . 'salammu_data_pengurus';
        $pengurus = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d AND user_id = %d", $id, $logged_user));
    }

?>
    <div class="px-6">
        <form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="bg-white p-6 mr-6 rounded-lg shadow-md">
            <input type="hidden" name="form_name" value="pengurus_add_form">
            <input type="hidden" name="user_id" value="<?php echo $logged_user; ?>">
            <?php if ($editing) : ?>
                <input type="hidden" name="edit_id" value="<?php echo $pengurus->id; ?>">
            <?php endif; ?>
            <input type="hidden" name="action" value="handle_notulen_form">

            <div class="mb-4 space-y-3">
                <label class="block text-gray-700 font-medium">Tingkat</label>
                <div class="flex flex-col space-y-2">
                    <label><input type="radio" name="tingkat_pengurus" value="wilayah" <?php echo (($pengurus && $pengurus->tingkat == 'wilayah') || $tingkat_pengurus == 'wilayah') ? 'checked' : ''; ?>> Pimpinan Wilayah</label>
                    <label><input type="radio" name="tingkat_pengurus" value="daerah" <?php echo (($pengurus && $pengurus->tingkat == 'daerah') || $tingkat_pengurus == 'daerah') ? 'checked' : ''; ?>> Pimpinan Daerah</label>
                    <label><input type="radio" name="tingkat_pengurus" value="cabang" <?php echo (($pengurus && $pengurus->tingkat == 'cabang') || $tingkat_pengurus == 'cabang') ? 'checked' : ''; ?>> Pimpinan Cabang</label>
                    <label><input type="radio" name="tingkat_pengurus" value="ranting" <?php echo (($pengurus && $pengurus->tingkat == 'ranting') || $tingkat_pengurus == 'ranting') ? 'checked' : ''; ?>> Pimpinan Ranting</label>
                </div>
            </div>

            <div class="mb-4 space-y-3">
                <label for="nama_lengkap" class="block text-gray-700 font-medium">Nama Lengkap</label>
                <input name="nama_lengkap" id="nama_lengkap" type="text" value="<?php echo $pengurus ? esc_attr($pengurus->nama_lengkap_gelar) : ''; ?>" class="w-full mt-1 p-2 border rounded-md" />
            </div>

            <div class="mb-4 space-y-3">
                <label for="jabatan" class="block text-gray-700 font-medium">Jabatan</label>
                <input name="jabatan" id="jabatan" type="text" value="<?php echo $pengurus ? esc_attr($pengurus->jabatan) : ''; ?>" class="w-full mt-1 p-2 border rounded-md" />
            </div>

            <div class="mb-4 space-y-3">
                <label for="no_hp" class="block text-gray-700 font-medium">Nomor HP</label>
                <input name="no_hp" id="no_hp" type="text" value="<?php echo $pengurus ? esc_attr($pengurus->no_hp) : ''; ?>" class="w-full mt-1 p-2 border rounded-md" />
            </div>

            <button type="submit" class="w-full bg-blue-500 hover:bg-blue-600 text-white py-2 rounded-md font-medium">
                <?php echo $editing ? 'Update' : 'Simpan'; ?> Pengurus
            </button>
        </form>

        <div id="pengurus-list" class="mt-4 text-gray-700 text-center  rounded-md">
            <p>Pilih tingkat untuk melihat daftar Pengurus</p>
        </div>
    </div>
    <script>
        function previewImage(event) {
            const file = event.target.files[0];
            if (file && file.type.startsWith("image/")) {
                const objectURL = URL.createObjectURL(file);
                document.getElementById("image-preview").src = objectURL;
                document.getElementById("image-preview-container").style.display = "block";
                document.getElementById("image-view-link").href = objectURL;
                document.getElementById("image-view-link").style.display = "block";
            }
        }

        function previewPDF(event) {
            const file = event.target.files[0];
            if (file && file.type === "application/pdf") {
                const objectURL = URL.createObjectURL(file);
                document.getElementById("pdf-preview").src = objectURL;
                document.getElementById("pdf-preview-container").style.display = "block";
                document.getElementById("pdf-download-link").href = objectURL;
                document.getElementById("pdf-download-link").style.display = "block";
            }
        }

        // Handler untuk radio button tingkat_pengurus
        document.querySelectorAll('input[name="tingkat_pengurus"]').forEach(function(radio) {
            radio.addEventListener('change', function() {
                let tingkat_pengurus = this.value;
                let pengurusList = document.getElementById('pengurus-list');
                pengurusList.innerHTML = "<p>Loading...</p>";
                fetch('<?php echo admin_url('admin-ajax.php'); ?>?action=get_data_pengurus&tingkat_pengurus=' + tingkat_pengurus)
                    .then(response => response.text())
                    .then(data => {
                        pengurusList.innerHTML = data;
                    })
                    .catch(error => {
                        pengurusList.innerHTML = "<p>Error loading data.</p>";
                    });
            });
        });

        document.addEventListener('DOMContentLoaded', function() {
            let tingkat_pengurus = '<?php echo $tingkat_pengurus; ?>';
            if (tingkat_pengurus) {
                let radio = document.querySelector('input[name="tingkat_pengurus"][value="' + tingkat_pengurus + '"]');
                if (radio) {
                    radio.checked = true;
                    radio.dispatchEvent(new Event('change'));
                }
            }
        });
    </script>
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
    $pengurus = $wpdb->get_results($query);

    if (!empty($pengurus)) :
    ?>
        <div class="overflow-x-auto bg-white p-6 rounded-lg shadow-md">
            <table class="min-w-full border border-gray-200">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="border border-gray-300 px-4 py-2 text-center">No</th>
                        <th class="border border-gray-300 px-4 py-2 text-center">Nama</th>
                        <th class="border border-gray-300 px-4 py-2 text-center">Jabatan</th>
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
                            <td class="border border-gray-300 px-4 py-2 text-center space-x-2">
                                <a href="<?php echo esc_url(admin_url('admin.php?page=pengurus-add&edit=1&id=' . $p->id)); ?>"
                                    class="px-3 border border-blue-500 text-blue-500 rounded-md hover:text-white transition">
                                    Edit
                                </a>
                                <a href="<?php echo esc_url(admin_url('admin-post.php?action=delete_pengurus&id=' . $p->id . '&tingkat_pengurus=' . $tingkat_pengurus)); ?>"
                                    class="px-3 border border-red-500 text-red-500 rounded-md  hover:text-white transition"
                                    onclick="return confirm('Are you sure you want to delete this item?');">
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