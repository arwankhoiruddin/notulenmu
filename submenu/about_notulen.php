<?php
function notulenmu_page(){
    if (!current_user_can('edit_posts')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }
    
    echo "<h1>Tentang NotulenMu</h1>";
    echo "<p>NotulenMu adalah plugin yang digunakan untuk mencatat notulen rapat di wilayah, daerah, cabang dan ranting</p>";
    echo "<p>Plugin ini dikembangkan oleh <a href='https://mandatech.co.id'>Arwan Ahmad Khoiruddin</a></p>";
    echo "<p>Persembahan dari LPCRPM Pimpinan Pusat Muhammadiyah</p>";
}
