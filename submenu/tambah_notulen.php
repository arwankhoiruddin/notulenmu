<?php

function handle_notulen_form() {
    error_log('DEBUG: handle_notulen_form START');
    echo '<!-- DEBUG: handle_notulen_form START -->';
    
    // Enable error reporting for debugging
    if (WP_DEBUG) {
        ini_set('display_errors', 1);
        error_reporting(E_ALL);
    }
    
    // Debug: Check if this function is being called
    if (isset($_POST['action']) && $_POST['action'] === 'handle_notulen_form') {
        error_log('DEBUG: Form action confirmed, processing form...');
    } else {
        error_log('DEBUG: Function called but action not confirmed. Action: ' . (isset($_POST['action']) ? $_POST['action'] : 'NOT SET'));
        return;
    }
    
    // Verify nonce for security
    if (!wp_verify_nonce($_POST['notulenmu_nonce'], 'notulenmu_add_nonce')) {
        error_log('DEBUG: Nonce verification failed');
        set_transient('notulenmu_admin_notice', 'Security check failed. Please try again.', 5);
        set_transient('notulenmu_notice_type', 'error', 5);
        wp_safe_redirect(admin_url('admin.php?page=notulenmu-add'));
        exit;
    }
    
    global $wpdb;
    // Get the data from the form
    $user_id = isset($_POST['user_id']) ? $_POST['user_id'] : null;
    $tingkat = isset($_POST['tingkat']) ? $_POST['tingkat'] : null;
    $topik_rapat = isset($_POST['topik_rapat']) ? $_POST['topik_rapat'] : null;
    $tanggal_rapat = isset($_POST['tanggal_rapat']) ? $_POST['tanggal_rapat'] : null;
    $jam_mulai = isset($_POST['jam_mulai']) ? $_POST['jam_mulai'] : null;
    $jam_selesai = isset($_POST['jam_selesai']) ? $_POST['jam_selesai'] : null;
    $sifat_rapat = isset($_POST['sifat_rapat']) ? json_encode([$_POST['sifat_rapat']]) : json_encode([]);
    $tempat_rapat = isset($_POST['tempat_rapat']) ? $_POST['tempat_rapat'] : null;
    $lampiran = isset($_FILES['lampiran']) ? $_FILES['lampiran'] : null;
    $notulen_rapat = isset($_POST['notulen_rapat']) ? $_POST['notulen_rapat'] : null;
    $image_upload = isset($_FILES['image_upload']) ? $_FILES['image_upload'] : null;
    $selected_peserta = isset($_POST['peserta_rapat']) ? $_POST['peserta_rapat'] : [];
    $peserta_tambahan = isset($_POST['peserta_tambahan']) ? $_POST['peserta_tambahan'] : null;
    
    // Debug log the received data
    error_log('DEBUG: Received POST data - user_id: ' . $user_id . ', tingkat: ' . $tingkat . ', topik: ' . $topik_rapat);
    
    if ($peserta_tambahan) {
        $selected_peserta[] = $peserta_tambahan;
    }
    $peserta_json = json_encode($selected_peserta);
    $table_name = $wpdb->prefix . 'salammu_notulenmu';

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
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $file_ext = strtolower(pathinfo($image_upload['name'], PATHINFO_EXTENSION));
        if (in_array($file_ext, $allowed_types)) {
            $upload_dir = wp_upload_dir();
            $filename = uniqid() . '.' . $file_ext;
            $upload_file = $upload_dir['path'] . '/' . $filename;
            if (move_uploaded_file($image_upload['tmp_name'], $upload_file)) {
                $img_path = $upload_file;
            }
        }
    }

    $setting_table_name = $wpdb->prefix . 'salammu_notulenmu_setting';
    $tingkat_id = null;
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
    }

    // Check if this is an edit
    $is_edit = isset($_POST['edit_id']) && !empty($_POST['edit_id']);
    $edit_id = $is_edit ? intval($_POST['edit_id']) : null;

    if ($is_edit && $edit_id) {
        // Handle edit functionality
        if (!current_user_can('manage_options')) {
            set_transient('notulenmu_admin_notice', 'Anda tidak memiliki akses.', 5);
            set_transient('notulenmu_notice_type', 'error', 5);
            return;
        }

        // Validasi input wajib
        if (empty($topik_rapat) || empty($tanggal_rapat) || empty($notulen_rapat)) {
            set_transient('notulenmu_admin_notice', 'Topik, tanggal, dan notulen wajib diisi.', 5);
            set_transient('notulenmu_notice_type', 'error', 5);
            return;
        }

        // Update the record
        $table_name = $wpdb->prefix . 'salammu_notulenmu';
        $result = $wpdb->update(
            $table_name,
            array(
                'topik_rapat' => $topik_rapat,
                'tanggal_rapat' => $tanggal_rapat,
                'jam_mulai' => $jam_mulai,
                'jam_selesai' => $jam_selesai,
                'sifat_rapat' => $sifat_rapat,
                'tempat_rapat' => $tempat_rapat,
                'peserta_rapat' => $peserta_json,
                'notulen_rapat' => $notulen_rapat,
                'image_path' => $img_path ? $img_path : null,
                'lampiran' => $lampiran_path ? $lampiran_path : null
            ),
            array('id' => $edit_id, 'user_id' => $user_id)
        );

        if ($result !== false) {
            set_transient('notulenmu_admin_notice', 'Notulen berhasil diupdate.', 5);
            set_transient('notulenmu_notice_type', 'success', 5);
            if (!function_exists('wp_safe_redirect')) {
                require_once(ABSPATH . WPINC . '/pluggable.php');
            }
            wp_safe_redirect(admin_url('admin.php?page=notulenmu-list'));
            exit;
        } else {
            set_transient('notulenmu_admin_notice', 'Gagal mengupdate notulen.', 5);
            set_transient('notulenmu_notice_type', 'error', 5);
        }
    } else {
        // Hak akses: hanya user yang punya hak bisa menambah notulen
        if (!current_user_can('manage_options')) {
            set_transient('notulenmu_admin_notice', 'Anda tidak memiliki akses.', 5);
            set_transient('notulenmu_notice_type', 'error', 5);
            return;
        }

        if ($user_id == null) {
            set_transient('notulenmu_admin_notice', 'User ID tidak ditemukan.', 5);
            set_transient('notulenmu_notice_type', 'error', 5);
            return;
        }

        // Validasi input wajib
        if (empty($topik_rapat) || empty($tanggal_rapat) || empty($notulen_rapat)) {
            set_transient('notulenmu_admin_notice', 'Topik, tanggal, dan notulen wajib diisi.', 5);
            set_transient('notulenmu_notice_type', 'error', 5);
            return;
        }

        $setting_table_name = $wpdb->prefix . 'salammu_notulenmu_setting';

        // ...existing code untuk tingkat dan tingkat_id...

        if (is_null($tingkat_id)) {
            if (!function_exists('wp_redirect')) {
                require_once(ABSPATH . WPINC . '/pluggable.php');
            }
            wp_redirect(admin_url('admin.php?page=notulenmu-settings'));
            exit;
        }

        $table_name = $wpdb->prefix . 'salammu_notulenmu';

        // Cek duplikasi notulen berdasarkan topik dan tanggal
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE topik_rapat = %s AND tanggal_rapat = %s",
            $topik_rapat, $tanggal_rapat
        ));
        if ($exists > 0) {
            set_transient('notulenmu_admin_notice', 'Notulen dengan topik dan tanggal ini sudah ada.', 5);
            set_transient('notulenmu_notice_type', 'error', 5);
            return;
        }

        // Validasi file upload (lampiran dan gambar)
        if ($lampiran && $lampiran['error'] === UPLOAD_ERR_OK) {
            if ($lampiran['size'] > 2 * 1024 * 1024) { // 2MB
                set_transient('notulenmu_admin_notice', 'Lampiran terlalu besar (maksimal 2MB).', 5);
                set_transient('notulenmu_notice_type', 'error', 5);
                return;
            }
        }
        if ($image_upload && $image_upload['error'] === UPLOAD_ERR_OK) {
            if ($image_upload['size'] > 2 * 1024 * 1024) {
                set_transient('notulenmu_admin_notice', 'Gambar terlalu besar (maksimal 2MB).', 5);
                set_transient('notulenmu_notice_type', 'error', 5);
                return;
            }
        }

        $result = $wpdb->insert(
            $table_name,
            array(
                'user_id' => $user_id,
                'tingkat' => $tingkat,
                'id_tingkat' => $tingkat_id,
                'topik_rapat' => $topik_rapat,
                'tanggal_rapat' => $tanggal_rapat,
                'jam_mulai' => $jam_mulai,
                'jam_selesai' => $jam_selesai,
                'sifat_rapat' => $sifat_rapat,
                'tempat_rapat' => $tempat_rapat,
                'peserta_rapat' => $peserta_json,
                'notulen_rapat' => $notulen_rapat,
                'image_path' => $img_path ? $img_path : '',
                'lampiran' => $lampiran_path ? $lampiran_path : ''
            )
        );
        if ($result !== false) {
            set_transient('notulenmu_admin_notice', 'Notulen berhasil ditambahkan.', 5);
            set_transient('notulenmu_notice_type', 'success', 5);
            if (!function_exists('wp_safe_redirect')) {
                require_once(ABSPATH . WPINC . '/pluggable.php');
            }
            wp_safe_redirect(admin_url('admin.php?page=notulenmu-list'));
            exit;
        } else {
            // Log database error
            error_log('DEBUG: Database insert failed. Error: ' . $wpdb->last_error);
            set_transient('notulenmu_admin_notice', 'Gagal menambahkan notulen. Error: ' . $wpdb->last_error, 5);
            set_transient('notulenmu_notice_type', 'error', 5);
        }
    }

    add_action('admin_notices', 'notulenmu_admin_notices');
    error_log('DEBUG: handle_notulen_form END');
    echo '<!-- DEBUG: handle_notulen_form END -->';
}

function notulenmu_admin_notices()
{
    if ($message = get_transient('notulenmu_admin_notice')) {
        $notice_type = get_transient('notulenmu_notice_type') ?: 'success';
        $notice_class = ($notice_type === 'success') ? 'notice-success' : 'notice-error';
        echo "<div class='notice $notice_class is-dismissible'><p>$message</p></div>";
        delete_transient('notulenmu_admin_notice');
        delete_transient('notulenmu_notice_type');
    }
}

add_action('admin_post_handle_notulen_form', 'handle_notulen_form');
add_action('admin_post_nopriv_handle_notulen_form', 'handle_notulen_form');

function notulenmu_add_page()
{
    if (!current_user_can('edit_posts')) {
        wp_die(('You do not have sufficient permissions to access this page.'));
    }

    $editing = isset($_GET['edit']);
    $logged_user = get_current_user_id();


    echo '<h1>' . ($editing ? 'Edit' : 'Tambah') . ' Notulen</h1>';
    
    // Display admin notices
    if ($message = get_transient('notulenmu_admin_notice')) {
        $notice_type = get_transient('notulenmu_notice_type') ?: 'error';
        $notice_class = ($notice_type === 'success') ? 'notice-success' : 'notice-error';
        echo "<div class='notice $notice_class is-dismissible'><p>$message</p></div>";
        delete_transient('notulenmu_admin_notice');
        delete_transient('notulenmu_notice_type');
    }
    
    echo '<div class="mb-4">
        <a href="' . esc_url(admin_url('admin.php?page=notulenmu-list')) . '" class="inline-block bg-gray-300 hover:bg-gray-500 text-gray-800 font-semibold py-2 px-4 rounded">Kembali</a>
    </div>';

    $notulen = null;
    if ($editing) {
        global $wpdb;
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        $table_name = $wpdb->prefix . 'salammu_notulenmu';
        $notulen = $wpdb->get_row("SELECT * FROM $table_name WHERE id = $id AND user_id = $logged_user");
    }

    $selected_peserta = $notulen ? json_decode($notulen->peserta_rapat, true) : [];
    echo '<script>var selectedPeserta = ' . json_encode($selected_peserta) . ';</script>';

    if ($notulen && $notulen->image_path) {
        $upload_dir = wp_upload_dir();
        $image_path = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $notulen->image_path);
    }

    ?>
    <?php if (!$editing) { ?>
        <!-- Step 1: Pilih Tingkat -->
        <div id="step-1" class="mb-6">
            <form id="form-step-1" class="p-6 bg-white shadow-md rounded-lg">
                <div class="flex flex-col space-y-2 mb-4">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-blue-500" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-stack-pop">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                            <path d="M7 9.5l-3 1.5l8 4l8 -4l-3 -1.5" />
                            <path d="M4 15l8 4l8 -4" />
                            <path d="M12 11v-7" />
                            <path d="M9 7l3 -3l3 3" />
                        </svg>
                        <label class="block font-semibold text-[15px]">Pilih Tingkat</label>
                    </div>
                    <select name="tingkat" id="tingkat" class="w-full p-2 border rounded-md" style="min-width: 100%;">
                        <option value="">-- Pilih Tingkat --</option>
                        <option value="wilayah">Pimpinan Wilayah</option>
                        <option value="daerah">Pimpinan Daerah</option>
                        <option value="cabang">Pimpinan Cabang</option>
                        <option value="ranting">Pimpinan Ranting</option>
                    </select>
                </div>
                <div class="flex justify-end mt-4">
                    <button type="button" id="next-step" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-md">Lanjut</button>
                </div>
            </form>
        </div>
        <!-- Step 2: Form Lanjutan -->
        <div id="step-2" style="display:none;">
            <form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="p-6 mr-4 bg-white shadow-md rounded-lg" id="notulenmu-form">
                <input type="hidden" name="form_name" value="notulenmu_add_form">
                <input type="hidden" name="user_id" value="<?php echo $logged_user; ?>">
                <input type="hidden" name="action" value="handle_notulen_form">
                <input type="hidden" name="tingkat" id="tingkat-hidden" value="">
                <?php wp_nonce_field('notulenmu_add_nonce', 'notulenmu_nonce'); ?>
                <!-- Main Form -->
                <div class="grid gap-7 w-full">
                    <!-- Peserta -->
                    <div class="flex flex-col space-y-2 ">
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-blue-500" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-user">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                <path d="M8 7a4 4 0 1 0 8 0a4 4 0 0 0 -8 0" />
                                <path d="M6 21v-2a4 4 0 0 1 4 -4h4a4 4 0 0 1 4 4v2" />
                            </svg>
                            <label class="font-semibold text-[15px]">Peserta</label>
                        </div>
                        <div id="pengurus-list">
                            <p>Pilih tingkat untuk melihat daftar peserta.</p>
                        </div>
                        <input type="text" name="peserta_tambahan" placeholder="Tambah peserta (opsional)" class="w-full p-2 border rounded-md">
                    </div>
                    <!-- Topik Rapat -->
                    <div class="flex flex-col space-y-2">
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-blue-500" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-article">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                <path d="M3 4m0 2a2 2 0 0 1 2 -2h14a2 2 0 0 1 2 2v12a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2z" />
                                <path d="M7 8h10" />
                                <path d="M7 12h10" />
                                <path d="M7 16h10" />
                            </svg>
                            <label class="block font-semibold text-[15px]">Topik Rapat</label>
                        </div>
                        <input name="topik_rapat" id="topik_rapat" type="text" value="<?php echo ($notulen ? esc_attr($notulen->topik_rapat) : ''); ?>" class="w-full p-2 border rounded-md" />
                    </div>
                    <!-- Tanggal Rapat -->
                    <div class="flex flex-col space-y-2">
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-blue-500" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-calendar-event">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                <path d="M4 5m0 2a2 2 0 0 1 2 -2h12a2 2 0 0 1 2 2v12a2 2 0 0 1 -2 2h-12a2 2 0 0 1 -2 -2z" />
                                <path d="M16 3l0 4" />
                                <path d="M8 3l0 4" />
                                <path d="M4 11l16 0" />
                                <path d="M8 15h2v2h-2z" />
                            </svg>
                            <label class="block font-semibold text-[15px]">Tanggal Rapat</label>
                        </div>
                        <input name="tanggal_rapat" id="tanggal_rapat" type="date" value="<?php echo ($notulen ? esc_attr($notulen->tanggal_rapat) : ''); ?>" class="w-full p-2 border rounded-md" />
                    </div>
                    <!-- Jam Mulai -->
                    <div class="flex flex-col space-y-2">
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-blue-500" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-clock-hour-1">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                <path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0" />
                                <path d="M12 7v5" />
                                <path d="M12 12l2 -3" />
                            </svg>
                            <label class="block font-semibold text-[15px]">Jam Mulai</label>
                        </div>
                        <input name="jam_mulai" type="time" value="<?php echo ($notulen ? esc_attr($notulen->jam_mulai) : ''); ?>" class="w-full p-2 border rounded-md" />
                    </div>
                    <!-- Jam Berakhir -->
                    <div class="flex flex-col space-y-2">
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-blue-500" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-clock-hour-3">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                <path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0" />
                                <path d="M12 12h3.5" />
                                <path d="M12 7v5" />
                            </svg>
                            <label class="block font-semibold text-[15px]">Jam Berakhir</label>
                        </div>
                        <input name="jam_selesai" type="time" value="<?php echo ($notulen ? esc_attr($notulen->jam_selesai) : ''); ?>" class="w-full p-2 border rounded-md" />
                    </div>
                    <!-- Sifat Rapat -->
                    <div class="flex flex-col space-y-2">
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-blue-500" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-map-pin">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                <path d="M9 11a3 3 0 1 0 6 0a3 3 0 0 0 -6 0" />
                                <path d="M17.657 16.657l-4.243 4.243a2 2 0 0 1 -2.827 0l-4.244 -4.243a8 8 0 1 1 11.314 0z" />
                            </svg>
                            <fieldset class="block font-semibold text-[15px]">Sifat Rapat</fieldset>
                        </div>
                        <div class="flex flex-wrap gap-4">
                            <?php
                            $sifat_options = ['Daring', 'Luring', 'Blended', 'Di luar kantor'];
                            $saved_sifat_rapat = $notulen ? json_decode($notulen->sifat_rapat, true) : [];
                            if (!is_array($saved_sifat_rapat)) {
                                $saved_sifat_rapat = [];
                            }
                            $selected_sifat = !empty($saved_sifat_rapat) ? $saved_sifat_rapat[0] : '';
                            foreach ($sifat_options as $option) {
                                $checked = ($selected_sifat === $option) ? 'checked' : '';
                            ?>
                                <label class="block mt-3">
                                    <input type="radio" name="sifat_rapat" value="<?php echo esc_attr($option); ?>" class="mr-2" <?php echo $checked; ?>> <?php echo esc_html($option); ?>
                                </label>
                            <?php } ?>
                        </div>
                    </div>
                    <!-- Tempat Rapat -->
                    <div class="flex flex-col space-y-2">
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-blue-500" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-building">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                <path d="M3 21v-13a2 2 0 0 1 2 -2h14a2 2 0 0 1 2 2v13" />
                                <path d="M9 21v-6a2 2 0 0 1 2 -2h2a2 2 0 0 1 2 2v6" />
                                <path d="M9 9h6" />
                            </svg>
                            <label class="block font-semibold text-[15px]">Tempat Rapat</label>
                        </div>
                        <input name="tempat_rapat" id="tempat_rapat" type="text" value="<?php echo ($notulen ? esc_attr($notulen->tempat_rapat) : ''); ?>" class="w-full p-2 border rounded-md" />
                    </div>
                    <!-- Foto Kegiatan -->
                    <div class="flex flex-col space-y-2">
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-blue-500" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-photo">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                <path d="M15 8h.01" />
                                <path d="M3 6a3 3 0 0 1 3 -3h12a3 3 0 0 1 3 3v12a3 3 0 0 1 -3 3h-12a3 3 0 0 1 -3 -3v-12z" />
                                <path d="M3 16l5 -5c.928 -.893 2.072 -.893 3 0l5 5" />
                                <path d="M14 14l1 -1c.928 -.893 2.072 -.893 3 0l3 3" />
                            </svg>
                            <label class="font-semibold text-[15px]">Foto Kegiatan</label>
                        </div>
                        <label class="flex flex-col items-center justify-center w-full h-32 border border-dashed rounded-md cursor-pointer hover:bg-gray-100">
                            <svg class="text-blue-500" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-upload">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                <path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2 -2v-2" />
                                <path d="M7 9l5 -5l5 5" />
                                <path d="M12 4l0 12" />
                            </svg>
                            <span id="image-upload-text" class="mt-2 text-gray-600">Upload File</span>
                            <input name="image_upload" id="image_upload" type="file" accept="image/*" class="hidden" onchange="previewImage(this, 'image-preview', 'image-upload-text')" />
                        </label>
                        <?php if (!empty($image_path)) { ?>
                            <img id="image-preview" src="<?php echo esc_url($image_path); ?>" class="mt-2 w-70">
                        <?php } else { ?>
                            <img id="image-preview" src="" class="mt-2 w-40 hidden">
                        <?php } ?>
                    </div>
                    <!-- Lampiran PDF -->
                    <div class="flex flex-col space-y-2">
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-blue-500" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-file-type-pdf">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                <path d="M14 3v4a1 1 0 0 0 1 1h4" />
                                <path d="M5 12v-7a2 2 0 0 1 2 -2h7l5 5v4" />
                                <path d="M5 18h1.5a1.5 1.5 0 0 0 0 -3h-1.5v6" />
                                <path d="M17 18h2" />
                                <path d="M20 15h-3v6" />
                                <path d="M11 15v6h1a2 2 0 0 0 2 -2v-2a2 2 0 0 0 -2 -2h-1z" />
                            </svg>
                            <label class="font-semibold text-[15px]">Upload Lampiran (PDF)</label>
                        </div>
                        <label class="flex flex-col items-center justify-center w-full h-32 border border-dashed rounded-md cursor-pointer hover:bg-gray-100">
                            <svg class="text-blue-500" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-upload">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                <path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2 -2v-2" />
                                <path d="M7 9l5 -5l5 5" />
                                <path d="M12 4l0 12" />
                            </svg>
                            <span id="pdf-upload-text" class="mt-2 text-gray-600">Upload File</span>
                            <input name="lampiran" type="file" accept=".pdf" class="hidden" onchange="previewPDF(this)">
                        </label>
                        <div id="pdf-preview-container" class="mt-2" style="display: none;">
                            <embed id="pdf-preview" type="application/pdf" width="100%" height="500px">
                        </div>
                        <?php if ($notulen && isset($notulen->lampiran) && $notulen->lampiran): ?>
                            <?php
                            $upload_dir = wp_upload_dir();
                            $lampiran_path = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $notulen->lampiran);
                            ?>
                            <div id="existing-pdf-container" class="mt-2">
                                <embed id="existing-pdf-preview" src="<?php echo esc_url($lampiran_path); ?>" type="application/pdf" width="100%" height="500px">
                            </div>
                        <?php endif; ?>
                    </div>
                    <!-- Rangkuman Rapat -->
                    <div class="flex flex-col space-y-2 ">
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-blue-500" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-clipboard">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                <path d="M9 5h-2a2 2 0 0 0 -2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2 -2v-12a2 2 0 0 0 -2 -2h-2" />
                                <path d="M9 3m0 2a2 2 0 0 1 2 -2h2a2 2 0 0 1 2 2v0a2 2 0 0 1 -2 2h-2a2 2 0 0 1 -2 -2z" />
                            </svg>
                            <label class="block font-semibold text-[15px]">Rangkuman Rapat</label>
                        </div>
                        <textarea name="notulen_rapat" id="notulen_rapat" rows="5" class="w-full p-2 border rounded-md"><?php echo ($notulen ? esc_textarea($notulen->notulen_rapat) : ''); ?></textarea>
                    </div>
                </div>
                <?php if (!$editing) { ?>
                    <div class="flex justify-end mt-9 gap-3">
                        <input type="submit" value="Simpan Notulen" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-md" onclick="return validateForm()">
                        <a href="<?php echo esc_url(admin_url('admin.php?page=notulenmu-list')); ?>" class="bg-gray-400 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded-md">Kembali</a>
                    </div>
                <?php } else { ?>
                    <div class="flex justify-end mt-9 gap-3">
                        <input type="submit" value="Update Notulen" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-md" onclick="return validateForm()">
                        <a href="<?php echo esc_url(admin_url('admin.php?page=notulenmu-list')); ?>" class="bg-gray-400 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded-md">Kembali</a>
                    </div>
                <?php } ?>
            </div>
        </form>
    <?php } else { ?>
        <!-- Edit mode: tampilkan form seperti biasa -->
        <form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="p-6 mr-4 bg-white shadow-md rounded-lg" id="notulenmu-form">
            <input type="hidden" name="form_name" value="notulenmu_add_form">
            <input type="hidden" name="user_id" value="<?php echo $logged_user; ?>">
            <input type="hidden" name="action" value="handle_notulen_form">
            <input type="hidden" name="edit_id" value="<?php echo esc_attr($notulen->id); ?>">
            <input type="hidden" name="tingkat" value="<?php echo esc_attr($notulen->tingkat); ?>">
            <?php wp_nonce_field('notulenmu_add_nonce', 'notulenmu_nonce'); ?>>
            <!-- Main Form -->
            <div class="grid gap-7 w-full">
                <!-- Peserta -->
                <div class="flex flex-col space-y-2 ">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-blue-500" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-user">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                            <path d="M8 7a4 4 0 1 0 8 0a4 4 0 0 0 -8 0" />
                            <path d="M6 21v-2a4 4 0 0 1 4 -4h4a4 4 0 0 1 4 4v2" />
                        </svg>
                        <label class="font-semibold text-[15px]">Peserta</label>
                    </div>
                    <div id="pengurus-list">
                        <?php if ($editing && $notulen) {
                            // Ambil daftar pengurus yang ikut dari database
                            global $wpdb;
                            $user_id = get_current_user_id();
                            $tingkat = $notulen->tingkat;
                            $setting_table = $wpdb->prefix . 'salammu_notulenmu_setting';
                            $settings = $wpdb->get_row($wpdb->prepare(
                                "SELECT pwm, pdm, pcm, prm FROM $setting_table WHERE user_id = %d",
                                $user_id
                            ), ARRAY_A);
                            $id_tingkat_list = array_filter([$settings['pwm'], $settings['pdm'], $settings['pcm'], $settings['prm']]);
                            if (!empty($id_tingkat_list)) {
                                $pengurus_table = $wpdb->prefix . 'salammu_data_pengurus';
                                $placeholders = implode(',', array_fill(0, count($id_tingkat_list), '%d'));
                                $query = "SELECT id, nama_lengkap_gelar, tingkat, jabatan FROM $pengurus_table WHERE tingkat = %s AND id_tingkat IN ($placeholders)";
                                $query = $wpdb->prepare($query, array_merge([$tingkat], $id_tingkat_list));
                                $pengurus = $wpdb->get_results($query);
                                $selected_peserta = json_decode($notulen->peserta_rapat, true);
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
                            } else {
                                echo "<p>Tidak ada data pengurus yang tersedia.</p>";
                            }
                        } else {
                            echo '<p>Pilih tingkat untuk melihat daftar peserta.</p>';
                        } ?>
                    </div>
                    <input type="text" name="peserta_tambahan" placeholder="Tambah peserta (opsional)" class="w-full p-2 border rounded-md">
                </div>
                <!-- Topik Rapat -->
                <div class="flex flex-col space-y-2">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-blue-500" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-article">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                            <path d="M3 4m0 2a2 2 0 0 1 2 -2h14a2 2 0 0 1 2 2v12a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2z" />
                            <path d="M7 8h10" />
                            <path d="M7 12h10" />
                            <path d="M7 16h10" />
                        </svg>
                        <label class="block font-semibold text-[15px]">Topik Rapat</label>
                    </div>
                    <input name="topik_rapat" id="topik_rapat" type="text" value="<?php echo ($notulen ? esc_attr($notulen->topik_rapat) : ''); ?>" class="w-full p-2 border rounded-md" />
                </div>
                <!-- Tanggal Rapat -->
                <div class="flex flex-col space-y-2">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-blue-500" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-calendar-event">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                            <path d="M4 5m0 2a2 2 0 0 1 2 -2h12a2 2 0 0 1 2 2v12a2 2 0 0 1 -2 2h-12a2 2 0 0 1 -2 -2z" />
                            <path d="M16 3l0 4" />
                            <path d="M8 3l0 4" />
                            <path d="M4 11l16 0" />
                            <path d="M8 15h2v2h-2z" />
                        </svg>
                        <label class="block font-semibold text-[15px]">Tanggal Rapat</label>
                    </div>
                    <input name="tanggal_rapat" id="tanggal_rapat" type="date" value="<?php echo ($notulen ? esc_attr($notulen->tanggal_rapat) : ''); ?>" class="w-full p-2 border rounded-md" />
                </div>
                <!-- Jam Mulai -->
                <div class="flex flex-col space-y-2">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-blue-500" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-clock-hour-1">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                            <path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0" />
                            <path d="M12 7v5" />
                            <path d="M12 12l2 -3" />
                        </svg>
                        <label class="block font-semibold text-[15px]">Jam Mulai</label>
                    </div>
                    <input name="jam_mulai" type="time" value="<?php echo ($notulen ? esc_attr($notulen->jam_mulai) : ''); ?>" class="w-full p-2 border rounded-md" />
                </div>
                <!-- Jam Berakhir -->
                <div class="flex flex-col space-y-2">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-blue-500" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-clock-hour-3">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                            <path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0" />
                            <path d="M12 12h3.5" />
                            <path d="M12 7v5" />
                        </svg>
                        <label class="block font-semibold text-[15px]">Jam Berakhir</label>
                    </div>
                    <input name="jam_selesai" type="time" value="<?php echo ($notulen ? esc_attr($notulen->jam_selesai) : ''); ?>" class="w-full p-2 border rounded-md" />
                </div>
                <!-- Sifat Rapat -->
                <div class="flex flex-col space-y-2">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-blue-500" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-map-pin">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                            <path d="M9 11a3 3 0 1 0 6 0a3 3 0 0 0 -6 0" />
                            <path d="M17.657 16.657l-4.243 4.243a2 2 0 0 1 -2.827 0l-4.244 -4.243a8 8 0 1 1 11.314 0z" />
                        </svg>
                        <fieldset class="block font-semibold text-[15px]">Sifat Rapat</fieldset>
                    </div>
                    <div class="flex flex-wrap gap-4">
                        <?php
                        $sifat_options = ['Daring', 'Luring', 'Blended', 'Di luar kantor'];
                        $saved_sifat_rapat = $notulen ? json_decode($notulen->sifat_rapat, true) : [];
                        if (!is_array($saved_sifat_rapat)) {
                            $saved_sifat_rapat = [];
                        }
                        $selected_sifat = !empty($saved_sifat_rapat) ? $saved_sifat_rapat[0] : '';
                        foreach ($sifat_options as $option) {
                            $checked = ($selected_sifat === $option) ? 'checked' : '';
                        ?>
                            <label class="block mt-3">
                                <input type="radio" name="sifat_rapat" value="<?php echo esc_attr($option); ?>" class="mr-2" <?php echo $checked; ?>> <?php echo esc_html($option); ?>
                            </label>
                        <?php } ?>
                    </div>
                </div>
                <!-- Tempat Rapat -->
                <div class="flex flex-col space-y-2">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-blue-500" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-building">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                            <path d="M3 21v-13a2 2 0 0 1 2 -2h14a2 2 0 0 1 2 2v13" />
                            <path d="M9 21v-6a2 2 0 0 1 2 -2h2a2 2 0 0 1 2 2v6" />
                            <path d="M9 9h6" />
                        </svg>
                        <label class="block font-semibold text-[15px]">Tempat Rapat</label>
                    </div>
                    <input name="tempat_rapat" id="tempat_rapat" type="text" value="<?php echo ($notulen ? esc_attr($notulen->tempat_rapat) : ''); ?>" class="w-full p-2 border rounded-md" />
                </div>
                <!-- Foto Kegiatan -->
                <div class="flex flex-col space-y-2">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-blue-500" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-photo">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                            <path d="M15 8h.01" />
                            <path d="M3 6a3 3 0 0 1 3 -3h12a3 3 0 0 1 3 3v12a3 3 0 0 1 -3 3h-12a3 3 0 0 1 -3 -3v-12z" />
                            <path d="M3 16l5 -5c.928 -.893 2.072 -.893 3 0l5 5" />
                            <path d="M14 14l1 -1c.928 -.893 2.072 -.893 3 0l3 3" />
                        </svg>
                        <label class="font-semibold text-[15px]">Foto Kegiatan</label>
                    </div>
                    <label class="flex flex-col items-center justify-center w-full h-32 border border-dashed rounded-md cursor-pointer hover:bg-gray-100">
                        <svg class="text-blue-500" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-upload">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                            <path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2 -2v-2" />
                            <path d="M7 9l5 -5l5 5" />
                            <path d="M12 4l0 12" />
                        </svg>
                        <span id="image-upload-text" class="mt-2 text-gray-600">Upload File</span>
                        <input name="image_upload" id="image_upload" type="file" accept="image/*" class="hidden" onchange="previewImage(this, 'image-preview', 'image-upload-text')" />
                    </label>
                    <?php if (!empty($image_path)) { ?>
                        <img id="image-preview" src="<?php echo esc_url($image_path); ?>" class="mt-2 w-70">
                    <?php } else { ?>
                        <img id="image-preview" src="" class="mt-2 w-40 hidden">
                    <?php } ?>
                </div>
                <!-- Lampiran PDF -->
                <div class="flex flex-col space-y-2">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-blue-500" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-file-type-pdf">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                            <path d="M14 3v4a1 1 0 0 0 1 1h4" />
                            <path d="M5 12v-7a2 2 0 0 1 2 -2h7l5 5v4" />
                            <path d="M5 18h1.5a1.5 1.5 0 0 0 0 -3h-1.5v6" />
                            <path d="M17 18h2" />
                            <path d="M20 15h-3v6" />
                            <path d="M11 15v6h1a2 2 0 0 0 2 -2v-2a2 2 0 0 0 -2 -2h-1z" />
                        </svg>
                        <label class="font-semibold text-[15px]">Upload Lampiran (PDF)</label>
                    </div>
                    <label class="flex flex-col items-center justify-center w-full h-32 border border-dashed rounded-md cursor-pointer hover:bg-gray-100">
                        <svg class="text-blue-500" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-upload">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                            <path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2 -2v-2" />
                            <path d="M7 9l5 -5l5 5" />
                            <path d="M12 4l0 12" />
                        </svg>
                        <span id="pdf-upload-text" class="mt-2 text-gray-600">Upload File</span>
                        <input name="lampiran" type="file" accept=".pdf" class="hidden" onchange="previewPDF(this)">
                    </label>
                    <div id="pdf-preview-container" class="mt-2" style="display: none;">
                        <embed id="pdf-preview" type="application/pdf" width="100%" height="500px">
                    </div>
                    <?php if ($notulen && isset($notulen->lampiran) && $notulen->lampiran): ?>
                        <?php
                        $upload_dir = wp_upload_dir();
                        $lampiran_path = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $notulen->lampiran);
                        ?>
                        <div id="existing-pdf-container" class="mt-2">
                            <embed id="existing-pdf-preview" src="<?php echo esc_url($lampiran_path); ?>" type="application/pdf" width="100%" height="500px">
                        </div>
                    <?php endif; ?>
                </div>
                <!-- Rangkuman Rapat -->
                <div class="flex flex-col space-y-2 ">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-blue-500" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-clipboard">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                            <path d="M9 5h-2a2 2 0 0 0 -2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2 -2v-12a2 2 0 0 0 -2 -2h-2" />
                            <path d="M9 3m0 2a2 2 0 0 1 2 -2h2a2 2 0 0 1 2 2v0a2 2 0 0 1 -2 2h-2a2 2 0 0 1 -2 -2z" />
                        </svg>
                        <label class="block font-semibold text-[15px]">Rangkuman Rapat</label>
                    </div>
                    <textarea name="notulen_rapat" id="notulen_rapat" rows="5" class="w-full p-2 border rounded-md"><?php echo ($notulen ? esc_textarea($notulen->notulen_rapat) : ''); ?></textarea>
                </div>
            </div>
            <?php if (!$editing) { ?>
                <div class="flex justify-end mt-9 gap-3">
                    <input type="submit" value="Simpan Notulen" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-md" onclick="return validateForm()">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=notulenmu-list')); ?>" class="bg-gray-400 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded-md">Kembali</a>
                </div>
            <?php } else { ?>
                <div class="flex justify-end mt-9 gap-3">
                    <input type="submit" value="Update Notulen" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-md" onclick="return validateForm()">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=notulenmu-list')); ?>" class="bg-gray-400 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded-md">Kembali</a>
                </div>
            <?php } ?>
        </form>
    <?php } ?>
    <script>
        // Stepper logic
        document.getElementById('next-step').addEventListener('click', function() {
            var tingkat = document.getElementById('tingkat').value;
            if (!tingkat || tingkat === 'Pilih Tingkat') {
                alert('Silakan pilih tingkat terlebih dahulu.');
                return;
            }
            // Set the hidden field value
            document.getElementById('tingkat-hidden').value = tingkat;
            
            document.getElementById('step-1').style.display = 'none';
            document.getElementById('step-2').style.display = 'block';
            // Load peserta list for selected tingkat
            loadPengurusList(tingkat, selectedPeserta);
        });

        // ...existing code...
        function validateForm() {
            var topik = document.getElementById('topik_rapat').value;
            var tanggal = document.getElementById('tanggal_rapat').value;
            var notulen = document.getElementById('notulen_rapat').value;
            
            if (!topik || !tanggal || !notulen) {
                alert('Mohon lengkapi field yang wajib diisi: Topik Rapat, Tanggal Rapat, dan Rangkuman Rapat');
                return false;
            }
            
            // Check if sifat rapat is selected
            var sifatRapat = document.querySelector('input[name="sifat_rapat"]:checked');
            if (!sifatRapat) {
                alert('Mohon pilih sifat rapat');
                return false;
            }
            
            // Check if tingkat is set (for non-editing mode)
            var tingkatHidden = document.getElementById('tingkat-hidden');
            if (tingkatHidden && !tingkatHidden.value) {
                alert('Mohon pilih tingkat terlebih dahulu');
                return false;
            }
            
            return true;
        }
        
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
        function previewPDF(input) {
            const file = input.files[0];
            if (file) {
                const objectURL = URL.createObjectURL(file);
                document.getElementById('pdf-preview').src = objectURL;
                document.getElementById('pdf-preview-container').style.display = 'block';
                document.getElementById('pdf-upload-text').textContent = file.name;
                const existingContainer = document.getElementById('existing-pdf-container');
                if (existingContainer) {
                    existingContainer.style.display = 'none';
                }
            }
        }
        function loadPengurusList(tingkat, selectedPeserta) {
            let pengurusList = document.getElementById('pengurus-list');
            pengurusList.innerHTML = "<p>Loading...</p>";
            fetch('<?php echo admin_url('admin-ajax.php'); ?>?action=get_pengurus_by_tingkat&tingkat=' + tingkat, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'selected_peserta=' + encodeURIComponent(JSON.stringify(selectedPeserta))
            })
            .then(response => response.text())
            .then(data => {
                pengurusList.innerHTML = data;
            })
            .catch(error => {
                pengurusList.innerHTML = "<p>Error loading data.</p>";
            });
        }
        document.getElementById('tingkat').addEventListener('change', function() {
            let tingkat = this.value;
            if (document.getElementById('step-2').style.display === 'block') {
                loadPengurusList(tingkat, selectedPeserta);
            }
        });
        <?php if ($editing && $notulen) { ?>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('step-1').style.display = 'none';
            document.getElementById('step-2').style.display = 'block';
            let tingkat = document.getElementById('tingkat').value;
            loadPengurusList(tingkat, selectedPeserta);
        });
        <?php } ?>
    </script>
<?php
}


function get_pengurus_by_tingkat()
{
    global $wpdb;
    $user_id = get_current_user_id();
    $tingkat = $_GET['tingkat'] ?? '';

    // Ambil peserta yang sudah hadir dari POST
    $selected_peserta = [];
    if (isset($_POST['selected_peserta'])) {
        $selected_peserta = json_decode(stripslashes($_POST['selected_peserta']), true);
        if (!is_array($selected_peserta)) $selected_peserta = [];
    }

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

    $query = "SELECT id, nama_lengkap_gelar, tingkat, jabatan 
              FROM $pengurus_table 
              WHERE tingkat = %s AND id_tingkat IN ($placeholders)";

    $query = $wpdb->prepare($query, array_merge([$tingkat], $id_tingkat_list));
    $pengurus = $wpdb->get_results($query);
    // echo "<pre>";
    // print_r($pengurus);
    // echo "</pre>";

    // error_log("Pengurus ditemukan: " . print_r($pengurus, true));

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
        $selected_peserta = isset($selected_peserta) ? $selected_peserta : [];

        foreach ($pengurus as $p) {
            if (!isset($p->nama_lengkap_gelar)) {
                continue; // Jika data tidak valid, skip ke berikutnya
            }

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

add_action('wp_ajax_get_pengurus_by_tingkat', 'get_pengurus_by_tingkat');
add_action('wp_ajax_nopriv_get_pengurus_by_tingkat', 'get_pengurus_by_tingkat');