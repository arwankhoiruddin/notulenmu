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

    $user_id = isset($_POST['user_id']) ? $_POST['user_id'] : null;
    $tingkat = isset($_POST['tingkat']) ? $_POST['tingkat'] : null;
    $id_tingkat = isset($_POST['id_tingkat']) ? $_POST['id_tingkat'] : null;
    $nama_lengkap = isset($_POST['nama_lengkap']) ? $_POST['nama_lengkap'] : null;
    $jabatan = isset($_POST['jabatan']) ? $_POST['jabatan'] : null;
    $no_np = isset($_POST['no_hp']) ? $_POST['no_hp'] : null;

    $result = $wpdb->insert(
        $table_name, // Table name
        array( // Data
            'user_id' => $user_id,
            'tingkat' => $tingkat,
            'id_tingkat' => $tingkat_id,
            'nama_lengkap' => $nama_lengkap,
            'jabatan' => $jabatan,
            'no_hp' => $no_np,
        ),
        array( // Data format
            '%d', // user_id
            '%s', // tingkat
            '%s', // tingkat_id
            '%s', // nama_lengkap
            '%s', // jabatan
            '%s', // no_hp
        )
    );
    if ($result === false) {
        echo "Error in SQL: " . $wpdb->last_error;
        exit;
    }

    if ($result !== false) {
        set_transient('notulenmu_admin_notice', 'The notulen was successfully added.', 5);
        if (!function_exists('wp_redirect')) {
            require_once(ABSPATH . WPINC . '/pluggable.php');
        }
        wp_redirect(admin_url('admin.php?page=notulenmu-list'));
        exit;
    } else {
        set_transient('notulenmu_admin_notice', 'There was an error adding the notulen.', 5);
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
    
    $selected_peserta = $notulen ? json_decode($notulen->peserta_rapat, true) : [];

    if ($notulen && $notulen->image_path) {
        $upload_dir = wp_upload_dir();
        $image_path = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $notulen->image_path);
    }

    // Form for adding or editing
    echo '<form method="post" enctype="multipart/form-data" action="' . esc_url(admin_url('admin-post.php')) . '">';
    echo '<input type="hidden" name="form_name" value="pengurus_add_form">';
    echo '<input type="hidden" name="user_id" value="' . $logged_user . '">';
    echo '<input type="hidden" name="action" value="handle_notulen_form">';
    echo '<table class="form-table">';
    echo '<tbody>';
    echo '<tr>';
    echo '<th scope="row"><label for="tingkat">Tingkat</label></th>';
    echo '<td>';
    echo '<select name="tingkat" id="tingkat">';
    echo '<option>Pilih Tingkat</option>';
    echo '<option value="wilayah"' . ($notulen && $notulen->tingkat == 'wilayah' ? ' selected' : '') . '>Pimpinan Wilayah</option>';
    echo '<option value="daerah"' . ($notulen && $notulen->tingkat == 'daerah' ? ' selected' : '') . '>Pimpinan Daerah</option>';
    echo '<option value="cabang"' . ($notulen && $notulen->tingkat == 'cabang' ? ' selected' : '') . '>Pimpinan Cabang</option>';
    echo '<option value="ranting"' . ($notulen && $notulen->tingkat == 'ranting' ? ' selected' : '') . '>Pimpinan Ranting</option>';
    echo '</select>';
    echo '</td>';
    echo '</tr>';
    echo '<tr>';
    echo '<th scope="row"><label for="nama_lengkap">Nama Lengkap</label></th>';
    echo '<td><input name="nama_lengkap" id="nama_lengkap" type="text" value="' . ($notulen ? esc_attr($notulen->nama_lengkap) : '') . '" class="regular-text" /></td>';
    echo '</tr>';
    echo '<tr>';
    echo '<th scope="row"><label for="jabatan">Jabatan</label></th>';
    echo '<td><input name="jabatan" id="jabatan" type="text" value="' . ($notulen ? esc_attr($notulen->jabatan) : '') . '" class="regular-text" /></td>';
    echo '</tr>';
    echo '<tr>';
    echo '<th scope="row"><label for="no_hp">Nomer HP</label></th>';
    echo '<td><input name="no_hp" id="no_hp" type="text" value="' . ($notulen ? esc_attr($notulen->no_hp) : '') . '" class="regular-text" /></td>';
    echo '</tr>';
    echo '</tbody>';
    echo '</table>';
    if (!$editing) {
        echo '<input type="submit" value="Simpan Pengurus" class="bg-[#007bff] hover:bg-[#0069d9] p-1.5 text-white rounded-sm">';
    }
    echo '</form>';
    echo '<div id="pengurus-list"><p>Pilih tingkat untuk melihat daftar Pengurus</p></div>';
?>
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

            pengurusList.innerHTML = "<p>Loading...</p>";

            fetch('<?php echo admin_url('admin-ajax.php'); ?>?action=get_pengurus_by_tingkat&tingkat=' + tingkat)
                .then(response => response.text())
                .then(data => {
                    pengurusList.innerHTML = data;
                })
                .catch(error => {
                    pengurusList.innerHTML = "<p>Error loading data.</p>";
                });
        });
    </script>
<?php
}


function get_pengurus_by_tingkat()
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

    if (!empty($pengurus)) {
        echo '<table style="border-collapse: collapse; width: 100%;" border="1" cellpadding="5">';
        echo '<tr style="background-color: #f2f2f2; text-align: left;">';
        echo '<th style="border: 1px solid black; padding: 8px; width: 10px; text-align: center;">No</th>';
        echo '<th style="border: 1px solid black; padding: 8px; text-align: center;">Nama</th>';
        echo '<th style="border: 1px solid black; padding: 8px; text-align: center;">Jabatan</th>';
        echo '</tr>';

        $no = 1;
        $selected_peserta = isset($selected_peserta) ? $selected_peserta : [];

        foreach ($pengurus as $p) {
            if (!isset($p->nama_lengkap_gelar)) {
                continue; // Jika data tidak valid, skip ke berikutnya
            }

            echo '<tr>';
            echo '<td style="border: 1px solid black; padding: 8px; text-align: center;">' . esc_html($no) . '</td>';
            echo '<td style="border: 1px solid black; padding: 8px;">' . esc_html($p->nama_lengkap_gelar) . '</td>';
            echo '<td style="border: 1px solid black; padding: 8px;">' . esc_html($p->jabatan) . '</td>';
            echo '</tr>';
            $no++;
        }
        echo '</table>';
    } else {
        echo "<p style='color: red;'>Tidak ada pengurus pada tingkat ini.</p>";
    }

    wp_die();
}

add_action('wp_ajax_get_pengurus_by_tingkat', 'get_pengurus_by_tingkat');
add_action('wp_ajax_nopriv_get_pengurus_by_tingkat', 'get_pengurus_by_tingkat');
?>