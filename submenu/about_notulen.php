<?php
// Fungsi ini hanya untuk halaman Tentang NotulenMu
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
    </div>
    <!-- Grafik nasional dipindah ke halaman rekap_nasional -->
<?php
}
?>