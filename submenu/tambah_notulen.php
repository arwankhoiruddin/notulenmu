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
    $jam_mulai = isset($_POST['jam_mulai']) ? $_POST['jam_mulai'] : null;
    $jam_selesai = isset($_POST['jam_selesai']) ? $_POST['jam_selesai'] : null;
    $tempat_rapat = isset($_POST['tempat_rapat']) ? json_encode($_POST['tempat_rapat']) : json_encode([]);
    $lampiran = isset($_FILES['lampiran']) ? $_FILES['lampiran'] : null;
    $notulen_rapat = isset($_POST['notulen_rapat']) ? $_POST['notulen_rapat'] : null;
    $image_upload = isset($_FILES['image_upload']) ? $_FILES['image_upload'] : null;
    $selected_peserta = isset($_POST['peserta_rapat']) ? $_POST['peserta_rapat'] : [];
    $peserta_tambahan = isset($_POST['peserta_tambahan']) ? $_POST['peserta_tambahan'] : null;
    if ($peserta_tambahan) {
        $selected_peserta[] = $peserta_tambahan;
    }
    $peserta_json = json_encode($selected_peserta);
    $table_name = $wpdb->prefix . 'salammu_notulenmu';

    echo "Hello";
    $lampiran_path = '';
    if ($lampiran && $lampiran['error'] === UPLOAD_ERR_OK) {
        $file_ext = pathinfo($lampiran['name'], PATHINFO_EXTENSION);
        if (strtolower($file_ext) === 'pdf') {
            $upload_dir = wp_upload_dir();
            $filename = uniqid() . '.pdf';
            $upload_file = $upload_dir['path'] . '/' . $filename;
            if (move_uploaded_file($lampiran['tmp_name'], $upload_file)) {
                $lampiran_path = $upload_file;
            }
        }
    }
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

    $query = $wpdb->prepare(
        "SELECT pwm FROM $setting_table_name WHERE user_id = %d",
        $user_id
    );

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
        echo "Bismillah";
        if (!function_exists('wp_redirect')) {
            require_once(ABSPATH . WPINC . '/pluggable.php');
        }
        wp_redirect(admin_url('admin.php?page=notulenmu-settings'));
        echo "null";
        exit;
    }

    $table_name = $wpdb->prefix . 'salammu_notulenmu';
    $result = $wpdb->insert(
        $table_name, // Table name
        array( // Data
            'user_id' => $user_id,
            'tingkat' => $tingkat,
            'id_tingkat' => $tingkat_id,
            'topik_rapat' => $topik_rapat,
            'tanggal_rapat' => $tanggal_rapat,
            'jam_mulai' => $jam_mulai,
            'jam_selesai' => $jam_selesai,
            'tempat_rapat' => $tempat_rapat,
            'peserta_rapat' => $peserta_json,
            'notulen_rapat' => $notulen_rapat,
            'image_path' => $img_path,
            'lampiran' => $lampiran_path
        ),
        array( // Data format
            '%d', // user_id
            '%s', // tingkat
            '%s', // tingkat_id
            '%s', // topik_rapat
            '%s', // tanggal_rapat
            '%s', // jam_mulai
            '%s', // jam_selesai
            '%s', // tempat_rapat
            '%s', // peserta_rapat
            '%s', // notulen_rapat
            '%s', // image_path
            '%s', // lampiran
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

function notulenmu_add_page()
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
    echo '<input type="hidden" name="form_name" value="notulenmu_add_form">';
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
    echo '<th scope="row"><label for="topik_rapat">Topik Rapat</label></th>';
    echo '<td><input name="topik_rapat" id="topik_rapat" type="text" value="' . ($notulen ? esc_attr($notulen->topik_rapat) : '') . '" class="regular-text" /></td>';
    echo '</tr>';
    echo '<tr>';
    echo '<th scope="row"><label for="tanggal_rapat">Tanggal Rapat</label></th>';
    echo '<td><input name="tanggal_rapat" id="tanggal_rapat" type="date" value="' . ($notulen ? esc_attr($notulen->tanggal_rapat) : '') . '" class="regular-text" /></td>';
    echo '</tr>';
    echo '<tr><th><label for="jam_mulai">Jam Mulai</label></th>';
    echo '<td><input name="jam_mulai" type="time" value="' . ($notulen ? esc_attr($notulen->jam_mulai) : '') . '" class="regular-text" /></td></tr>';
    echo '<tr><th><label for="jam_selesai">Jam Berakhir</label></th>';
    echo '<td><input name="jam_selesai" type="time" value="' . ($notulen ? esc_attr($notulen->jam_selesai) : '') . '" class="regular-text" /></td></tr>';
    echo '<tr>';
    echo '<th scope="row">Tempat Rapat</th>';
    echo '<td>';

    $tempat_options = ['Daring', 'Luring', 'Blended', 'Di luar kantor'];
    $saved_tempat_rapat = $notulen ? json_decode($notulen->tempat_rapat, true) : [];

    foreach ($tempat_options as $option) {
        $checked = in_array($option, $saved_tempat_rapat) ? 'checked' : '';
        echo '<label><input type="checkbox" name="tempat_rapat[]" value="' . esc_attr($option) . '" ' . $checked . '> ' . esc_html($option) . '</label><br>';
    }

    echo '</td>';
    echo '</tr>';

    echo '<tr>';
    echo '<th scope="row"><label for="image_upload">Foto Kegiatan</label></th>';
    echo '<td>';
    echo '<input name="image_upload" id="image_upload" type="file" class="regular-text" />';
    if ($notulen && $notulen->image_path) {
        echo '<img src="' . esc_url($image_path) . '" alt="Image for ' . esc_attr($notulen->topik_rapat) . '" style="width: 200px; height: auto; margin-top: 20px;" />';
    }
    echo '</td>';
    echo '</tr>';

    echo '<tr>';
    echo '<th><label for="lampiran">Upload Lampiran (PDF)</label></th>';
    echo '<td>';
    echo '<input name="lampiran" type="file" accept=".pdf" class="regular-text" onchange="previewPDF(event)">';
    echo '<div id="pdf-preview-container" style="margin-top: 20px;">';

    if ($notulen && $notulen->lampiran) {
        $upload_dir = wp_upload_dir();
        $lampiran_path = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $notulen->lampiran);
        echo '<div id="pdf-preview-container">';
        echo '<embed id="pdf-preview" src="' . esc_url($lampiran_path) . '" type="application/pdf" width="500px" height="500px">';
        echo '<p><a href="' . esc_url($lampiran_path) . '" target="_blank">Download PDF</a></p>';
        echo '</div>';
    } else {
        echo '<div id="pdf-preview-container" style="display:none;">
            <embed id="pdf-preview" type="application/pdf" width="300px" height="200px">
            <p><a id="pdf-download-link" href="#" target="_blank" style="display:none;">Download PDF</a></p>
          </div>';
    }

    echo '</div>';
    echo '</td>';
    echo '</tr>';

    echo '<tr><th>Peserta</th>';
    echo '<td><div id="pengurus-list"><p>Pilih tingkat untuk melihat daftar peserta.</p></div>';
    echo '<input type="text" value="' . ($notulen ? esc_attr($notulen->peserta_rapat) : '') . '" name="peserta_tambahan" placeholder="Tambah peserta (opsional)" class="regular-text" style="margin-top: 20px; margin-left: 0;">';
    echo '</td></tr>';
    echo '<th scope="row"><label for="notulen_rapat">Rangkuman Rapat</label></th>';
    echo '<td><textarea name="notulen_rapat" id="notulen_rapat" rows="10" class="regular-text">' . ($notulen ? esc_textarea($notulen->notulen_rapat) : '') . '</textarea></td>';
    echo '</tr>';
    echo '</tbody>';
    echo '</table>';
    if (!$editing) {
        echo '<input type="submit" value="Simpan Notulen" class="bg-[#007bff] hover:bg-[#0069d9] p-1.5 text-white rounded-sm">';
    }
    echo '</form>';

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
    $tingkat = $_GET['tingkat'] ?? '';

    if (!$tingkat) {
        echo "<p>Pilih tingkat terlebih dahulu.</p>";
        wp_die();
    }

    $selected_peserta = isset($_POST['peserta_rapat']) ? $_POST['peserta_rapat'] : [];

    $pengurus_table = $wpdb->prefix . 'data_pengurus';
    $pengurus = $wpdb->get_results($wpdb->prepare("SELECT id, nama_lengkap_gelar, tingkat, jabatan FROM $pengurus_table WHERE tingkat = %s", $tingkat));

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
        echo "<p>Tidak ada pengurus pada tingkat ini.</p>";
    }

    wp_die();
}

add_action('wp_ajax_get_pengurus_by_tingkat', 'get_pengurus_by_tingkat');
add_action('wp_ajax_nopriv_get_pengurus_by_tingkat', 'get_pengurus_by_tingkat');
?>