<?php
$sicara_url = 'https://cors-anywhere.herokuapp.com/https://sicara.id/api/v0/organisation/';
function notulenmu_settings_page() {
    // Check user permissions
    if (!current_user_can('edit_posts')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        handle_setting_form();
    }

    // Fetch saved data
    $saved_data = fetch_saved_data();

    // Fetch data from external API
    $data = fetch_external_data();

    // Render the settings page
    render_settings_page($saved_data, $data);
}

function handle_setting_form() {
    echo "In handle_setting_form";
    // Check the nonce for security
    check_admin_referer('handle_setting_form');

    // Get the form data
    $user_id = $POST['user_id'];
    $pwm = sanitize_text_field($_POST['pwm']);
    $pdm = sanitize_text_field($_POST['pdm']);
    $pcm = sanitize_text_field($_POST['pcm']);
    $prm = sanitize_text_field($_POST['prm']);

    // Save the data to the database
    global $wpdb;
    $table_name = $wpdb->prefix . 'salammu_notulenmu_setting';
    $data = array(
        'user_id' => $user_id,
        'pwm' => $pwm,
        'pdm' => $pdm,
        'pcm' => $pcm,
        'prm' => $prm
    );
    $wpdb->replace($table_name, $data);
}

function fetch_saved_data() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'salammu_notulenmu_setting';
    $user_id = get_current_user_id();
    return $wpdb->get_row("SELECT * FROM $table_name WHERE user_id = $user_id", ARRAY_A);
}

function fetch_external_data() {
    global $sicara_url;
    $context = stream_context_create(array(
        'http' => array(
            'method' => 'GET',
            'header' => 'Origin: http://localhost'
        )
    ));

    // Pass the context to file_get_contents
    $json = file_get_contents($sicara_url, false, $context);
    return json_decode($json, true);
}

function render_settings_page($saved_data, $data) {
    global $sicara_url;
    $user_id = get_current_user_id();
    echo '<h1>Setting Notulen</h1>';
    wp_nonce_field('handle_setting_form');
    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
    echo '<input type="hidden" name="action" value="handle_setting_form">';
    echo '<input type="hidden" name="user_id" value="' . $user_id . '">';
    echo '<table class="form-table">';
    echo '<tbody>';
    echo '<tr>';
    echo '<th scope="row"><label for="pwm">Pimpinan Wilayah</label></th>';
    echo '<td><select name="pwm" id="pwm">';
    if (empty($data)) {
        echo '<option value="">No data</option>';
    } else {
        foreach ($data['data'] as $item) {
            $selected = ($saved_data && $saved_data['pwm'] == $item['id']) ? 'selected' : '';
            echo '<option value="' . $item['id'] . '" ' . $selected . '>' . $item['nama'] . '</option>';
        }
    }
    echo '</select></td>';
    echo '</tr>';

    echo '<tr>';
    echo '<th scope="row"><label for="pdm">Pimpinan Daerah</label></th>';
    echo '<td><select name="pdm" id="pdm">';
    if (empty($saved_data)) {
        echo '<option value="">No data</option>';
    } else {
        $json_address = $sicara_url . $saved_data['pdm'];
        echo $json_address;
        $json = file_get_contents($json_address);
        $data = json_decode($json, true);
        echo '<option value="' . $data['data']['id'] . '">' . $data['data']['nama'] . '</option>';
    }
    echo '</select></td>';
    echo '</tr>';

    echo '<tr>';

    echo '<th scope="row"><label for="pcm">Pimpinan Cabang</label></th>';
    echo '<td><select name="pcm" id="pcm">';
    if (empty($saved_data)) {
        echo '<option value="">No data</option>';
    } else {
        $json_address = $sicara_url . $saved_data['pcm'];
        $json = file_get_contents($json_address);
        $data = json_decode($json, true);
        echo '<option value="' . $data['data']['id'] . '">' . $data['data']['nama'] . '</option>';
    }
    echo '</select></td>';
    echo '</tr>';
    echo '<tr>';
    echo '<th scope="row"><label for="prm">Pimpinan Ranting</label></th>';
    echo '<td><select name="prm" id="prm">';
    if (empty($saved_data)) {
        echo '<option value="">No data</option>';
    } else {
        $json_address = $sicara_url . $saved_data['prm'];
        $json = file_get_contents($json_address);
        $data = json_decode($json, true);
        echo '<option value="' . $data['data']['id'] . '">' . $data['data']['nama'] . '</option>';
    }
    echo '</select></td>';
    echo '</tr>';
    echo '</tbody>';
    echo '</table>';
    echo '<input type="submit" value="Save Settings" class="button-primary">';
    echo '</form>';
}

function enqueue_notulenmu_scripts() {
    // Register the script
    wp_register_script('notulenmu_script', plugins_url('/script.js', __FILE__), array('jquery'), '1.0', true);

    // Enqueue the script
    wp_enqueue_script('notulenmu_script');
}
add_action('admin_enqueue_scripts', 'enqueue_notulenmu_scripts');
