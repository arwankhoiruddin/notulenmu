<?php
global $pagenow;

// If we're on the login page, return early
if ($pagenow === 'wp-login.php') {
    return;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_name']) && $_POST['form_name'] === 'notulenmu_setting_form') {
    global $wpdb;
    $table_name = $wpdb->prefix . 'salammu_notulenmu_setting';
    // Verify nonce for security
    // check_admin_referer('handle_notulenmu_form');

    $user_id = $_POST['user_id'];
    // Sanitize and store the form data
    $pimpinan_wilayah = sanitize_text_field($_POST['pimpinan_wilayah']);
    $pimpinan_daerah = sanitize_text_field($_POST['pimpinan_daerah']);
    $pimpinan_cabang = sanitize_text_field($_POST['pimpinan_cabang']);
    $pimpinan_ranting = sanitize_text_field($_POST['pimpinan_ranting']);

    // Prepare data for insertion
    $data = array(
        'user_id' => $user_id,
        'pwm' => $pimpinan_wilayah,
        'pdm' => $pimpinan_daerah,
        'pcm' => $pimpinan_cabang,
        'prm' => $pimpinan_ranting
    );

    $existing = $wpdb->get_row("SELECT * FROM $table_name WHERE user_id = $user_id");

    if ($existing) {
        // Update the existing row
        $wpdb->update($table_name, $data, array('user_id' => $user_id));
    } else {
        // Insert a new row
        $wpdb->insert($table_name, $data);
    }
    if (!function_exists('wp_redirect')) {
        require_once(ABSPATH . WPINC . '/pluggable.php');
    }
    // Redirect back to the settings page
    wp_redirect(admin_url('admin.php?page=notulenmu-settings'));
    exit;
}

function notulenmu_settings_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'salammu_notulenmu_setting';
    $user_id = get_current_user_id();
    $settings = $wpdb->get_row("SELECT * FROM $table_name where user_id='$user_id'", ARRAY_A);

    $url = 'https://old.sicara.id/api/v0/organisation/';
    $args = array(
        'headers' => array(
            'origin' => get_site_url(),
            'x-requested-with' => 'XMLHttpRequest'
        )
    );

    // Make the API request
    $response = wp_remote_get($url);

    // Check for errors
    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        echo "Something went wrong: $error_message";
    } else {
        // Decode the JSON response
        $data = json_decode(wp_remote_retrieve_body($response), true);
    }
    ?>
    <h1>Notulenmu Settings</h1>
	<h2>
		Sebelum mengisi NotulenMu, silakan sesuaikan isian di bawah berdasarkan tempat kerja Anda	
	</h2>
    <form method="POST" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <input type="hidden" name="form_name" value="notulenmu_setting_form">
        <input type="hidden" name="action" value="handle_notulenmu_form">
        <input type="hidden" name="user_id" value="<?php echo esc_attr(get_current_user_id()); ?>">
        <?php wp_nonce_field('handle_notulenmu_form'); ?>

        <table class="form-table">
            <tr>
                <th scope="row"><label for="pimpinan_wilayah">Pimpinan Wilayah:</label></th>
                <td>
                <select name="pimpinan_wilayah" id="pimpinan_wilayah">
                    <?php 
                    foreach ($data['data'] as $item): 
                    ?>
                        <option value="<?php echo $item['id']; ?>" 
                            <?php 
                                $selectedValue = $settings !== null ? $settings['pwm'] : 0;
                                selected($selectedValue, $item['id']); ?>>
                            <?php echo $item['nama']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="pimpinan_daerah">Pimpinan Daerah:</label></th>
                <td>
                <select name="pimpinan_daerah" id="pimpinan_daerah">
                    <?php
                        if (is_null($settings)) {
                            echo '<option value="">No data</option>';
                        } else {
                            $response = wp_remote_get($url . $settings['pdm'], $args);
                            $data = json_decode(wp_remote_retrieve_body($response), true);
                            echo '<option value="' . $data['data']['id'] . '">' . $data['data']['nama'] . '</option>';
                        }
                    ?>
                </select> 
               </td>
            </tr>
            <tr>
                <th scope="row"><label for="pimpinan_cabang">Pimpinan Cabang:</label></th>
                <td>
                <select name="pimpinan_cabang" id="pimpinan_cabang">
                <?php
                    if (is_null($settings)) {
                        echo '<option value="">No data</option>';
                    } else {
                        $response = wp_remote_get($url . $settings['pcm'], $args);
                        $data = json_decode(wp_remote_retrieve_body($response), true);
                        echo '<option value="' . $data['data']['id'] . '">' . $data['data']['nama'] . '</option>';
                    }
                ?>
                </select> 
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="pimpinan_ranting">Pimpinan Ranting:</label></th>
                <td>
                <select name="pimpinan_ranting" id="pimpinan_ranting">
                <?php
                    if (is_null($settings)) {
                        echo '<option value="">No data</option>';
                    } else {
                        $response = wp_remote_get($url . $settings['prm'], $args);
                        $data = json_decode(wp_remote_retrieve_body($response), true);
                        echo '<option value="' . $data['data']['id'] . '">' . $data['data']['nama'] . '</option>';
                    }
                ?>
                </select> 
                </td>
            </tr>
        </table>

        <?php submit_button(); ?>
    </form>

    <script>
    function updateDropdowns(pimpinan_wilayah_id, pimpinan_daerah_id) {
        document.getElementById(pimpinan_wilayah_id).addEventListener('change', function() {
            var id = this.value;
            fetch(`https://api.allorigins.win/raw?url=${encodeURIComponent('https://old.sicara.id/api/v0/organisation/' + id + '/children')}`)
            .then(response => response.json())
            .then(data => {
                var pimpinan_daerah = document.getElementById(pimpinan_daerah_id);
                pimpinan_daerah.innerHTML = '';
                data.data.forEach(item => {
                    var option = document.createElement('option');
                    option.value = item.id;
                    option.text = item.nama;
                    pimpinan_daerah.appendChild(option);
                });
            });
        });
    }

    document.addEventListener('DOMContentLoaded', (event) => {
        updateDropdowns('pimpinan_wilayah', 'pimpinan_daerah');
        updateDropdowns('pimpinan_daerah', 'pimpinan_cabang');
        updateDropdowns('pimpinan_cabang', 'pimpinan_ranting');
    });

    </script>
    <?php
}
