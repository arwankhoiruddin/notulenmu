<?php
function notulenmu_page(){
    $user_id = get_current_user_id();
    $user = get_userdata($user_id);
    if (!empty($user) && is_array($user->roles)) {
        $role = $user->roles[0];
    }
    if ($role != 'contributor' && $role != 'administrator') {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }
    
    echo "<h1>Tentang NotulenMu</h1>";
    echo "<p>NotulenMu adalah plugin yang digunakan untuk mencatat notulen rapat di wilayah, daerah, cabang dan ranting</p>";
    echo "<p>Plugin ini dikembangkan oleh <a href='https://mandatech.co.id'>Arwan Ahmad Khoiruddin</a></p>";
    echo "<p>Persembahan dari LPCRPM Pimpinan Pusat Muhammadiyah</p>";

    global $wpdb;
    // Fetch data from the database
    $table_name = $wpdb->prefix . 'salammu_notulenmu';
    $results = $wpdb->get_results("SELECT id_tingkat FROM $table_name");

    if (!empty($results)) {
        echo "<h2>Data ID Tingkat</h2>";
        echo "<ul>";
        foreach ($results as $row) {
            echo "<li>ID Tingkat: " . esc_html($row->id_tingkat) . "</li>";
        }
        echo "</ul>";
    } else {
        echo "<p>No data found.</p>";
    }
}
?>
