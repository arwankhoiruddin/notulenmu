<?php
function notulenmu_page()
{
    $user_id = get_current_user_id();
    $user = get_userdata($user_id);
    if (!empty($user) && is_array($user->roles)) {
        $role = $user->roles[0];
    }
    if ($role != 'contributor' && $role != 'administrator') {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }
?>
    <div class="relative p-6 bg-[#2d3476] shadow-lg rounded-lg m-4 ml-0 text-white overflow-hidden">
        <img src="<?php echo plugin_dir_url(__FILE__) . '../assets/img/image.png'; ?>"
            alt="Notulenmu"
            class="absolute top-0 right-5 w-60">

        <p class="text-white font-bold relative z-10">Tentang NotulenMu</p>
        <p class="mt-2 text-justify relative z-10">
            NotulenMu adalah plugin yang digunakan untuk mencatat notulen rapat di wilayah, daerah, cabang, dan ranting. <br>
            Plugin ini dikembangkan oleh
            <a href="https://mandatech.co.id" class="text-yellow-300 text-inherit">
                Arwan Ahmad Khoiruddin
            </a>
        </p>
        <p class="mt-2 relative z-10">Persembahan dari LPCRPM Pimpinan Pusat Muhammadiyah.</p>

        <!-- <?php
        global $wpdb;
        $table_name = $wpdb->prefix . 'salammu_notulenmu';
        $results = $wpdb->get_results("SELECT id_tingkat FROM $table_name");

        if (!empty($results)) {
            echo '<h2 class="mt-4 text-xl font-semibold text-white relative z-10">Data ID Tingkat</h2>';
            echo '<ul class="mt-2 list-disc list-inside text-white relative z-10">';
            foreach ($results as $row) {
                echo '<li class="py-1 px-3 bg-gray-100 text-gray-800 rounded-md shadow-sm">ID Tingkat: ' . esc_html($row->id_tingkat) . '</li>';
            }
            echo '</ul>';
        } else {
            echo '<p class="mt-4 text-red-400 relative z-10">No data found.</p>';
        }
        ?> -->
    </div>

<?php
}

?>