<?php
function show_admin_notices() {
    // Check if the transient is set
    if (get_transient('data_saved')) {
        // Delete the transient
        delete_transient('data_saved');

        // Display the notification
        echo '<div class="updated notice is-dismissible"><p>Data successfully saved.</p></div>';
    }
}

function notulenmu_settings_page(){
    if (!current_user_can('edit_posts')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }
    global $wpdb;
    $table_name = $wpdb->prefix . 'salammu_notulenmu_setting';
    $user_id = get_current_user_id();
    $saved_data = $wpdb->get_row("SELECT * FROM $table_name WHERE user_id = $user_id", ARRAY_A);

    echo '<h1>Setting Notulen</h1>';

    $json = file_get_contents('https://sicara.id/api/v0/organisation');
    $data = json_decode($json, true);

    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
    echo '<input type="hidden" name="action" value="handle_setting_form">';
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
        $json_address = 'https://sicara.id/api/v0/organisation/'. $saved_data['pdm'];
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
        $json_address = 'https://sicara.id/api/v0/organisation/'. $saved_data['pcm'];
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
        $json_address = 'https://sicara.id/api/v0/organisation/'. $saved_data['prm'];
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

function handle_setting_form() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'salammu_notulenmu_setting';
    $user_id = get_current_user_id();

    // Check if a row for the current user already exists
    $row = $wpdb->get_row("SELECT * FROM $table_name WHERE user_id = $user_id");

    $data = array(
        'user_id' => $user_id,
        'pwm' => $_POST['pwm'],
        'pdm' => $_POST['pdm'],
        'pcm' => $_POST['pcm']
    );

    if ($row) {
        // If a row for the current user already exists, update it
        $wpdb->update($table_name, $data, array('user_id' => $user_id));
    } else {
        // If no row for the current user exists, insert a new one
        $wpdb->insert($table_name, $data);
    }
    // Set a transient to indicate that the data was saved
    set_transient('data_saved', true, 5);

    // Redirect back to the settings page
    wp_redirect(add_query_arg('page', 'notulenmu-settings', admin_url('admin.php')));
    exit;
}

add_action('admin_post_handle_setting_form', 'handle_setting_form');
add_action('admin_notices', 'show_admin_notices');
?>

<script>
document.addEventListener('DOMContentLoaded', (event) => {
    document.getElementById('pwm').addEventListener('change', function() {
        var id = this.value;
        fetch('https://cors-anywhere.herokuapp.com/https://sicara.id/api/v0/organisation/' + id + '/children', {
            headers: {
                'Origin': 'http://localhost',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            var pdm = document.getElementById('pdm');
            pdm.innerHTML = '';
            data.data.forEach(item => {
                var option = document.createElement('option');
                option.value = item.id;
                option.text = item.nama;
                pdm.appendChild(option);
            });
        });
    });
});

document.addEventListener('DOMContentLoaded', (event) => {
    document.getElementById('pwm').addEventListener('change', function() {
        var id = this.value;
        fetch('https://cors-anywhere.herokuapp.com/https://sicara.id/api/v0/organisation/' + id + '/children', {
            headers: {
                'Origin': 'http://localhost',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            var pdm = document.getElementById('pdm');
            pdm.innerHTML = '';
            data.data.forEach(item => {
                var option = document.createElement('option');
                option.value = item.id;
                option.text = item.nama;
                pdm.appendChild(option);
            });
        });
    });

    document.getElementById('pdm').addEventListener('change', function() {
        var id = this.value;
        fetch('https://cors-anywhere.herokuapp.com/https://sicara.id/api/v0/organisation/' + id + '/children', {
            headers: {
                'Origin': 'http://localhost',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            var pcm = document.getElementById('pcm');
            pcm.innerHTML = '';
            data.data.forEach(item => {
                var option = document.createElement('option');
                option.value = item.id;
                option.text = item.nama;
                pcm.appendChild(option);
            });
        });
    });

    document.getElementById('pcm').addEventListener('change', function() {
    var id = this.value;
    fetch('https://cors-anywhere.herokuapp.com/https://sicara.id/api/v0/organisation/' + id + '/children', {
        headers: {
            'Origin': 'http://localhost',
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        var pr = document.getElementById('prm');
        pr.innerHTML = '';
        data.data.forEach(item => {
            var option = document.createElement('option');
            option.value = item.id;
            option.text = item.nama;
            pr.appendChild(option);
        });
    });
});
});
</script>

<?php