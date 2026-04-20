<?php
global $pagenow;

// If we're on the login page, return early
if ($pagenow === 'wp-login.php') {
    return;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_name']) && $_POST['form_name'] === 'jadwal_ied_add_form') {
    global $wpdb;

    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'jadwal_ied_form_nonce')) {
        wp_die(__('Invalid nonce.'));
    }

    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : null;
    $tingkat = isset($_POST['tingkat']) ? sanitize_text_field($_POST['tingkat']) : null;
    $tempat_penyelenggaraan = isset($_POST['tempat_penyelenggaraan']) ? sanitize_text_field($_POST['tempat_penyelenggaraan']) : null;
    $tanggal_pelaksanaan = isset($_POST['tanggal_pelaksanaan']) ? sanitize_text_field($_POST['tanggal_pelaksanaan']) : null;
    $nama_imam = isset($_POST['nama_imam']) ? sanitize_text_field($_POST['nama_imam']) : null;

    if ($user_id === null) {
        return;
    }

    $setting_table_name = $wpdb->prefix . 'sicara_settings';

    if (isset($_POST['id_tingkat_value']) && !empty($_POST['id_tingkat_value'])) {
        $tingkat_id = intval($_POST['id_tingkat_value']);
    } else {
        if ($tingkat == 'wilayah') {
            $tingkat_id = intval($wpdb->get_var($wpdb->prepare("SELECT pwm FROM $setting_table_name WHERE user_id = %d", $user_id)));
        } else if ($tingkat == 'daerah') {
            $tingkat_id = intval($wpdb->get_var($wpdb->prepare("SELECT pdm FROM $setting_table_name WHERE user_id = %d", $user_id)));
        } else if ($tingkat == 'cabang') {
            $tingkat_id = intval($wpdb->get_var($wpdb->prepare("SELECT pcm FROM $setting_table_name WHERE user_id = %d", $user_id)));
        } else if ($tingkat == 'ranting') {
            $tingkat_id = intval($wpdb->get_var($wpdb->prepare("SELECT prm FROM $setting_table_name WHERE user_id = %d", $user_id)));
        } else {
            return;
        }
    }

    if (empty($tingkat_id)) {
        if (!function_exists('wp_redirect')) {
            require_once(ABSPATH . WPINC . '/pluggable.php');
        }
        wp_redirect(admin_url('admin.php?page=notulenmu-settings'));
        exit;
    }

    $table_name = $wpdb->prefix . 'salammu_jadwal_shalat_ied';
    $is_edit = isset($_POST['edit_id']) && !empty($_POST['edit_id']);
    $edit_id = $is_edit ? intval($_POST['edit_id']) : null;

    if ($is_edit && $edit_id) {
        $result = $wpdb->update(
            $table_name,
            array(
                'tingkat' => $tingkat,
                'id_tingkat' => $tingkat_id,
                'tempat_penyelenggaraan' => $tempat_penyelenggaraan,
                'tanggal_pelaksanaan' => $tanggal_pelaksanaan,
                'nama_imam' => $nama_imam,
            ),
            array('id' => $edit_id, 'user_id' => $user_id),
            array('%s', '%d', '%s', '%s', '%s'),
            array('%d', '%d')
        );
        if ($result !== false) {
            set_transient('jadwal_ied_admin_notice', 'Jadwal Shalat Ied berhasil diupdate.', 5);
        } else {
            set_transient('jadwal_ied_admin_notice', 'Gagal update jadwal shalat ied.', 5);
        }
    } else {
        $result = $wpdb->insert(
            $table_name,
            array(
                'user_id' => $user_id,
                'tingkat' => $tingkat,
                'id_tingkat' => $tingkat_id,
                'tempat_penyelenggaraan' => $tempat_penyelenggaraan,
                'tanggal_pelaksanaan' => $tanggal_pelaksanaan,
                'nama_imam' => $nama_imam,
            ),
            array('%d', '%s', '%d', '%s', '%s', '%s')
        );
        if ($result !== false) {
            set_transient('jadwal_ied_admin_notice', 'Jadwal Shalat Ied berhasil ditambahkan.', 5);
        } else {
            set_transient('jadwal_ied_admin_notice', 'Gagal menambahkan jadwal shalat ied.', 5);
        }
    }

    if (!function_exists('wp_redirect')) {
        require_once(ABSPATH . WPINC . '/pluggable.php');
    }
    wp_redirect(admin_url('admin.php?page=kegiatanmu-list'));
    exit;
}

function tambah_jadwal_ied_page()
{
    if (!current_user_can('edit_posts')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    $editing = isset($_GET['edit']);
    $logged_user = get_current_user_id();

    $is_pwm = false;
    $is_pdm = false;
    $is_pcm = false;
    $is_prm = false;

    $user_info = get_userdata($logged_user);
    if ($user_info) {
        if (strpos($user_info->user_login, 'pwm.') === 0 ||
            strpos($user_info->user_login, 'pp.') === 0 ||
            strpos($user_info->user_login, 'arwan') === 0) {
            $is_pwm = true;
        } else if (strpos($user_info->user_login, 'pdm.') === 0) {
            $is_pdm = true;
        } else if (strpos($user_info->user_login, 'pcm.') === 0) {
            $is_pcm = true;
        } else if (strpos($user_info->user_login, 'prm.') === 0) {
            $is_prm = true;
        }
    }

    echo '<h1>' . ($editing ? 'Edit' : 'Tambah') . ' Jadwal Shalat Ied</h1>';
    echo '<div class="mb-4">'
        . '<a href="' . esc_url(admin_url('admin.php?page=kegiatanmu-list')) . '" class="inline-block bg-gray-300 hover:bg-gray-500 text-gray-800 font-semibold py-2 px-4 rounded">Kembali</a>'
        . '</div>';

    $jadwal = null;
    if ($editing) {
        global $wpdb;
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        $table_name = $wpdb->prefix . 'salammu_jadwal_shalat_ied';
        $jadwal = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d AND user_id = %d", $id, $logged_user));
    }
?>
<div class="notulenmu-container">
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="p-6 mr-4 bg-white shadow-md rounded-lg" id="jadwal-ied-form">
        <?php wp_nonce_field('jadwal_ied_form_nonce'); ?>
        <input type="hidden" name="form_name" value="jadwal_ied_add_form">
        <input type="hidden" name="user_id" value="<?php echo esc_attr($logged_user); ?>">
        <input type="hidden" name="action" value="handle_jadwal_ied_form">
        <input type="hidden" name="id_tingkat_value" id="id_tingkat_ied_value" value="">
        <?php if ($editing && $jadwal) { ?>
            <input type="hidden" name="edit_id" value="<?php echo esc_attr($jadwal->id); ?>">
        <?php } ?>

        <div class="grid gap-7 w-full">
            <div class="flex flex-col space-y-2">
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-blue-500" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M7 9.5l-3 1.5l8 4l8 -4l-3 -1.5" /><path d="M4 15l8 4l8 -4" /><path d="M12 11v-7" /><path d="M9 7l3 -3l3 3" /></svg>
                    <label class="block font-semibold text-[15px]">Tingkat</label>
                </div>
                <select name="tingkat" id="tingkat_ied" class="w-full p-2 border rounded-md" style="min-width: 100%;">
                    <option value="">Pilih Tingkat</option>
                    <?php if ($is_pwm) { ?>
                        <option value="wilayah" <?php echo ($jadwal && $jadwal->tingkat == 'wilayah' ? 'selected' : ''); ?>>Pimpinan Wilayah</option>
                        <option value="daerah" <?php echo ($jadwal && $jadwal->tingkat == 'daerah' ? 'selected' : ''); ?>>Pimpinan Daerah</option>
                        <option value="cabang" <?php echo ($jadwal && $jadwal->tingkat == 'cabang' ? 'selected' : ''); ?>>Pimpinan Cabang</option>
                        <option value="ranting" <?php echo ($jadwal && $jadwal->tingkat == 'ranting' ? 'selected' : ''); ?>>Pimpinan Ranting</option>
                    <?php } else if ($is_pdm) { ?>
                        <option value="daerah" <?php echo ($jadwal && $jadwal->tingkat == 'daerah' ? 'selected' : ''); ?>>Pimpinan Daerah</option>
                        <option value="cabang" <?php echo ($jadwal && $jadwal->tingkat == 'cabang' ? 'selected' : ''); ?>>Pimpinan Cabang</option>
                        <option value="ranting" <?php echo ($jadwal && $jadwal->tingkat == 'ranting' ? 'selected' : ''); ?>>Pimpinan Ranting</option>
                    <?php } else if ($is_pcm) { ?>
                        <option value="cabang" <?php echo ($jadwal && $jadwal->tingkat == 'cabang' ? 'selected' : ''); ?>>Pimpinan Cabang</option>
                        <option value="ranting" <?php echo ($jadwal && $jadwal->tingkat == 'ranting' ? 'selected' : ''); ?>>Pimpinan Ranting</option>
                    <?php } else if ($is_prm) { ?>
                        <option value="ranting" <?php echo ($jadwal && $jadwal->tingkat == 'ranting' ? 'selected' : ''); ?>>Pimpinan Ranting</option>
                    <?php } ?>
                </select>
            </div>

            <!-- Dynamic selection for specific organizational unit -->
            <div id="specific-tingkat-ied-container" class="flex flex-col space-y-2" style="display: none;">
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-blue-500" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                        <path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0" />
                        <path d="M12 7l0 5" />
                        <path d="M10 12l4 0" />
                    </svg>
                    <label class="block font-semibold text-[15px]" id="specific-tingkat-ied-label">Pilih Unit</label>
                </div>
                <select name="id_tingkat_select_ied" id="id_tingkat_select_ied" class="w-full p-2 border rounded-md" style="min-width: 100%;">
                    <option value="">-- Pilih Unit --</option>
                </select>
            </div>

            <div class="flex flex-col space-y-2">
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-blue-500" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M9 11a3 3 0 1 0 6 0a3 3 0 0 0 -6 0" /><path d="M17.657 16.657l-4.243 4.243a2 2 0 0 1 -2.827 0l-4.244 -4.243a8 8 0 1 1 11.314 0z" /></svg>
                    <label class="block font-semibold text-[15px]">Tempat Penyelenggaraan</label>
                </div>
                <input name="tempat_penyelenggaraan" id="tempat_penyelenggaraan" type="text" value="<?php echo ($jadwal ? esc_attr($jadwal->tempat_penyelenggaraan) : ''); ?>" class="w-full p-2 border rounded-md" required />
            </div>

            <div class="flex flex-col space-y-2">
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-blue-500" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M4 5m0 2a2 2 0 0 1 2 -2h12a2 2 0 0 1 2 2v12a2 2 0 0 1 -2 2h-12a2 2 0 0 1 -2 -2z" /><path d="M16 3l0 4" /><path d="M8 3l0 4" /><path d="M4 11l16 0" /><path d="M8 15h2v2h-2z" /></svg>
                    <label class="block font-semibold text-[15px]">Tanggal Pelaksanaan</label>
                </div>
                <input name="tanggal_pelaksanaan" id="tanggal_pelaksanaan" type="date" value="<?php echo ($jadwal ? esc_attr($jadwal->tanggal_pelaksanaan) : ''); ?>" class="w-full p-2 border rounded-md" required />
            </div>

            <div class="flex flex-col space-y-2">
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-blue-500" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M8 7a4 4 0 1 0 8 0a4 4 0 0 0 -8 0" /><path d="M6 21v-2a4 4 0 0 1 4 -4h4a4 4 0 0 1 4 4v2" /></svg>
                    <label class="block font-semibold text-[15px]">Nama Imam / Penceramah</label>
                </div>
                <input name="nama_imam" id="nama_imam" type="text" value="<?php echo ($jadwal ? esc_attr($jadwal->nama_imam) : ''); ?>" class="w-full p-2 border rounded-md" required />
            </div>
        </div>

        <?php if (!$editing) { ?>
            <div class="flex justify-end mt-9">
                <input type="submit" value="Simpan Jadwal Ied" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-md">
            </div>
        <?php } else { ?>
            <div class="flex justify-end mt-9">
                <input type="submit" value="Update Jadwal Ied" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-md">
            </div>
        <?php } ?>
    </form>
</div>

    <script>
        jQuery(document).ready(function($) {
            var is_pwm = <?php echo $is_pwm ? 'true' : 'false'; ?>;
            var is_pdm = <?php echo $is_pdm ? 'true' : 'false'; ?>;
            var is_pcm = <?php echo $is_pcm ? 'true' : 'false'; ?>;
            var user_id = <?php echo (int)$logged_user; ?>;

            $('#tingkat_ied').on('change', function() {
                var tingkat = $(this).val();
                var needsSelection = false;

                if (is_pdm && (tingkat === 'cabang' || tingkat === 'ranting')) {
                    needsSelection = true;
                } else if (is_pcm && tingkat === 'ranting') {
                    needsSelection = true;
                }

                if (needsSelection) {
                    var label = tingkat === 'cabang' ? 'Pilih Cabang' : 'Pilih Ranting';
                    $('#specific-tingkat-ied-label').text(label);

                    $('#id_tingkat_select_ied').html('<option value="">Loading...</option>');
                    $('#specific-tingkat-ied-container').show();
                    $('#id_tingkat_select_ied').prop('required', true);

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'get_lower_level_options',
                            tingkat: tingkat,
                            user_id: user_id,
                            nonce: '<?php echo wp_create_nonce('get_lower_level_options'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                var options = '<option value="">-- Pilih Unit --</option>';
                                $.each(response.data, function(id, name) {
                                    options += '<option value="' + id + '">' + name + '</option>';
                                });
                                $('#id_tingkat_select_ied').html(options);
                            } else {
                                $('#id_tingkat_select_ied').html('<option value="">Tidak ada data</option>');
                            }
                        },
                        error: function() {
                            $('#id_tingkat_select_ied').html('<option value="">Error loading data</option>');
                        }
                    });
                } else {
                    $('#specific-tingkat-ied-container').hide();
                    $('#id_tingkat_select_ied').prop('required', false);
                    $('#id_tingkat_ied_value').val('');
                }
            });

            $('#id_tingkat_select_ied').on('change', function() {
                $('#id_tingkat_ied_value').val($(this).val());
            });

            $('#jadwal-ied-form').on('submit', function(e) {
                var tingkat = $('#tingkat_ied').val();
                var needsSelection = false;

                if (is_pdm && (tingkat === 'cabang' || tingkat === 'ranting')) {
                    needsSelection = true;
                } else if (is_pcm && tingkat === 'ranting') {
                    needsSelection = true;
                }

                if (needsSelection && !$('#id_tingkat_ied_value').val()) {
                    e.preventDefault();
                    alert('Harap pilih unit terlebih dahulu');
                    return false;
                }
            });
        });
    </script>
<?php
}
