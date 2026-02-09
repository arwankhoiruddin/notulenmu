<?php
function notulenmu_pilih_tingkat_page() {
    if (!current_user_can('edit_posts')) {
        wp_die(('You do not have sufficient permissions to access this page.'));
    }

    $logged_user = get_current_user_id();
    $user_info = get_userdata($logged_user);
    $is_pwm = false;
    $is_pdm = false;
    if ($user_info && isset($user_info->user_login)) {
        if (strpos($user_info->user_login, 'pwm.') === 0) {
            $is_pwm = true;
        } elseif (strpos($user_info->user_login, 'pdm.') === 0) {
            $is_pdm = true;
        }
    }

    echo '<h1>Tambah Notulen</h1>';
    
    // Display admin notices
    if ($message = get_transient('notulenmu_admin_notice')) {
        $notice_type = get_transient('notulenmu_notice_type') ?: 'error';
        $notice_class = ($notice_type === 'success') ? 'notice-success' : 'notice-error';
        echo "<div class='notice $notice_class is-dismissible'><p>$message</p></div>";
        delete_transient('notulenmu_admin_notice');
        delete_transient('notulenmu_notice_type');
    }
    
    ?>
<div class="notulenmu-container">
    <div class="mb-4">
        <a href="<?php echo esc_url(admin_url('admin.php?page=notulenmu-list')); ?>" class="inline-block bg-gray-300 hover:bg-gray-500 text-gray-800 font-semibold py-2 px-4 rounded">Kembali</a>
    </div>
    
    <!-- Step 1: Pilih Tingkat -->
    <div id="step-1" class="mb-6">
        <form id="form-step-1" action="<?php echo esc_url(admin_url('admin.php?page=notulenmu-add-step2')); ?>" method="post" class="p-6 bg-white shadow-md rounded-lg">
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
                <select name="tingkat" id="tingkat" class="w-full p-2 border rounded-md" style="min-width: 100%;" required>
                    <option value="">-- Pilih Tingkat --</option>
                    <?php if ($is_pdm): ?>
                        <option value="daerah">Pimpinan Daerah</option>
                        <option value="cabang">Pimpinan Cabang</option>
                        <option value="ranting">Pimpinan Ranting</option>
                    <?php else: ?>
                        <option value="wilayah">Pimpinan Wilayah</option>
                        <option value="daerah">Pimpinan Daerah</option>
                        <option value="cabang">Pimpinan Cabang</option>
                        <option value="ranting">Pimpinan Ranting</option>
                    <?php endif; ?>
                </select>
            </div>
            
            <!-- Dynamic selection for specific organizational unit -->
            <div id="specific-tingkat-container" class="flex flex-col space-y-2 mb-4" style="display: none;">
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-blue-500" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                        <path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0" />
                        <path d="M12 7l0 5" />
                        <path d="M10 12l4 0" />
                    </svg>
                    <label class="block font-semibold text-[15px]" id="specific-tingkat-label">Pilih Unit</label>
                </div>
                <select name="id_tingkat" id="id_tingkat" class="w-full p-2 border rounded-md" style="min-width: 100%;">
                    <option value="">-- Pilih Unit --</option>
                </select>
            </div>
            
            <div class="flex justify-end mt-4">
                <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-md">Lanjut</button>
            </div>
            <?php wp_nonce_field('notulenmu_tingkat_nonce', 'notulenmu_tingkat_nonce'); ?>
        </form>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        var is_pdm = <?php echo $is_pdm ? 'true' : 'false'; ?>;
        var user_id = <?php echo $logged_user; ?>;
        
        $('#tingkat').on('change', function() {
            var tingkat = $(this).val();
            var needsSelection = false;
            
            // Determine if we need to show the specific unit selector
            if (is_pdm && (tingkat === 'cabang' || tingkat === 'ranting')) {
                needsSelection = true;
            }
            
            if (needsSelection) {
                // Update label
                var label = tingkat === 'cabang' ? 'Pilih Cabang' : 'Pilih Ranting';
                $('#specific-tingkat-label').text(label);
                
                // Load options via AJAX
                $('#id_tingkat').html('<option value="">Loading...</option>');
                $('#specific-tingkat-container').show();
                $('#id_tingkat').prop('required', true);
                
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
                            $('#id_tingkat').html(options);
                        } else {
                            $('#id_tingkat').html('<option value="">Tidak ada data</option>');
                        }
                    },
                    error: function() {
                        $('#id_tingkat').html('<option value="">Error loading data</option>');
                    }
                });
            } else {
                $('#specific-tingkat-container').hide();
                $('#id_tingkat').prop('required', false);
            }
        });
    });
    </script>
</div>
    <?php
}
?>