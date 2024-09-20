<?php

function get_user_role($user_id) {
    // Get user data
    $user = get_userdata($user_id);
    
    // Check if user data exists and has roles
    if (!empty($user) && is_array($user->roles)) {
        // Return the first role (primary role) of the user
        return $user->roles[0];
    }
    
    // Return null if no role is found
    return null;
}

function notulenmu_page(){
    if (get_user_role(get_current_user_id()) != 'contributor') {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }
    
    echo "<h1>Tentang NotulenMu</h1>";
    echo "<p>NotulenMu adalah plugin yang digunakan untuk mencatat notulen rapat di wilayah, daerah, cabang dan ranting</p>";
    echo "<p>Plugin ini dikembangkan oleh <a href='https://mandatech.co.id'>Arwan Ahmad Khoiruddin</a></p>";
    echo "<p>Persembahan dari LPCRPM Pimpinan Pusat Muhammadiyah</p>";
}
