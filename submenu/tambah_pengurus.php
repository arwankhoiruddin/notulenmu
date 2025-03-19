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
    $tingkat = isset($_POST['tingkat']) ? $_POST['tingkat'] : null;
    $id_tingkat = isset($_POST['id_tingkat']) ? $_POST['id_tingkat'] : null;
    $nama_lengkap = isset($_POST['nama_lengkap']) ? $_POST['nama_lengkap'] : null;
    $jabatan = isset($_POST['jabatan']) ? $_POST['jabatan'] : null;
    $no_np = isset($_POST['no_hp']) ? $_POST['no_hp'] : null;
    $edit_id = isset($_POST['edit_id']) ? intval($_POST['edit_id']) : null;

    $table_name = $wpdb->prefix . 'salammu_data_pengurus';

    if ($user_id == null) {
        return;
    }

    $setting_table_name = $wpdb->prefix . 'salammu_notulenmu_setting';

    if ($tingkat == 'wilayah') {
        $row = $wpdb->get_row($wpdb->prepare("SELECT pwm FROM $setting_table_name WHERE user_id = %d", $user_id));
        $tingkat_id = $row ? $row->pwm : null;
    } else if ($tingkat == 'daerah') {
        $row = $wpdb->get_row($wpdb->prepare("SELECT pdm FROM $setting_table_name WHERE user_id = %d", $user_id));
        $tingkat_id = $row ? $row->pdm : null;
    } else if ($tingkat == 'cabang') {
        $row = $wpdb->get_row($wpdb->prepare("SELECT pcm FROM $setting_table_name WHERE user_id = %d", $user_id));
        $tingkat_id = $row ? $row->pcm : null;
    } else if ($tingkat == 'ranting') {
        $row = $wpdb->get_row($wpdb->prepare("SELECT prm FROM $setting_table_name WHERE user_id = %d", $user_id));
        $tingkat_id = $row ? $row->prm : null;
    } else {
        return;
    }

    if (is_null($tingkat_id)) {
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
                'tingkat' => $tingkat,
                'id_tingkat' => $tingkat_id,
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
                'tingkat' => $tingkat,
                'id_tingkat' => $tingkat_id,
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
        wp_redirect(admin_url('admin.php?page=pengurus-add&tingkat=' . $tingkat));
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

    $tingkat = isset($_GET['tingkat']) ? $_GET['tingkat'] : '';

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
                <label for="tingkat" class="block text-gray-700 font-medium">Tingkat</label>
                <select name="tingkat" id="tingkat" class="w-full mt-1 p-2 border rounded-md" style="min-width: 100%;">
                    <option>Pilih Tingkat</option>
                    <option value="wilayah" <?php echo (($pengurus && $pengurus->tingkat == 'wilayah') || $tingkat == 'wilayah') ? 'selected' : ''; ?>>Pimpinan Wilayah</option>
                    <option value="daerah" <?php echo (($pengurus && $pengurus->tingkat == 'daerah') || $tingkat == 'daerah') ? 'selected' : ''; ?>>Pimpinan Daerah</option>
                    <option value="cabang" <?php echo (($pengurus && $pengurus->tingkat == 'cabang') || $tingkat == 'cabang') ? 'selected' : ''; ?>>Pimpinan Cabang</option>
                    <option value="ranting" <?php echo (($pengurus && $pengurus->tingkat == 'ranting') || $tingkat == 'ranting') ? 'selected' : ''; ?>>Pimpinan Ranting</option>
                </select>
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

        document.getElementById('tingkat').addEventListener('change', function() {
            let tingkat = this.value;
            let pengurusList = document.getElementById('pengurus-list');
            console.log(tingkat);

            pengurusList.innerHTML = "<p>Loading...</p>";

            fetch('<?php echo admin_url('admin-ajax.php'); ?>?action=get_data_pengurus&tingkat=' + tingkat)
                .then(response => response.text())
                .then(data => {
                    pengurusList.innerHTML = data;
                })
                .catch(error => {
                    pengurusList.innerHTML = "<p>Error loading data.</p>";
                });
        });

        // If tingkat is not empty, trigger change event to load the table
        document.addEventListener('DOMContentLoaded', function() {
            let tingkat = '<?php echo $tingkat; ?>';
            if (tingkat) {
                document.getElementById('tingkat').value = tingkat;
                document.getElementById('tingkat').dispatchEvent(new Event('change'));
            }
        });
    </script>
    <?php
}


function get_data_pengurus()
{
    global $wpdb;
    $user_id = get_current_user_id();
    $tingkat = $_GET['tingkat'] ?? '';

    if (!$tingkat) {
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

    $query = $wpdb->prepare($query, array_merge([$tingkat], $id_tingkat_list));
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
                                <a href="<?php echo esc_url(admin_url('admin-post.php?action=delete_pengurus&id=' . $p->id . '&tingkat=' . $tingkat)); ?>"
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
    if (!isset($_GET['id']) || !isset($_GET['tingkat'])) {
        wp_die('Invalid request.');
    }

    $id = intval($_GET['id']);
    $tingkat = sanitize_text_field($_GET['tingkat']);
    global $wpdb;
    $table_name = $wpdb->prefix . 'salammu_data_pengurus';

    $result = $wpdb->delete($table_name, array('id' => $id), array('%d'));

    if ($result === false) {
        wp_die('Error deleting record: ' . $wpdb->last_error);
    }

    if (!function_exists('wp_redirect')) {
        require_once(ABSPATH . WPINC . '/pluggable.php');
    }
    wp_redirect(admin_url('admin.php?page=pengurus-add&tingkat=' . $tingkat));
    exit;
}

add_action('admin_post_delete_pengurus', 'handle_delete_pengurus');
?>