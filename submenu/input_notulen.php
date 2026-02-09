<?php
function notulenmu_handle_input_form_redirect() {
    // We only want to check this on the 'notulenmu-input' page.
    if (isset($_GET['page']) && $_GET['page'] === 'notulenmu-input') {
        // Verify if we came from the first step, if not, redirect.
        if (!isset($_POST['tingkat']) || !isset($_POST['notulenmu_tingkat_nonce']) ||
            !wp_verify_nonce($_POST['notulenmu_tingkat_nonce'], 'notulenmu_tingkat_nonce')) {
            if (!headers_sent()) {
                wp_safe_redirect(admin_url('admin.php?page=notulenmu-add'));
                exit;
            }
        }
    }
}
add_action('admin_init', 'notulenmu_handle_input_form_redirect');

function notulenmu_input_form_page() {
    if (!current_user_can('edit_posts')) {
        wp_die(('You do not have sufficient permissions to access this page.'));
    }

    global $wpdb;
    $notulen_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    $is_edit_mode = $notulen_id > 0;
    $notulen_data = null;

    if ($is_edit_mode) {
        $table_name = $wpdb->prefix . 'salammu_notulenmu';
        $notulen_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $notulen_id), ARRAY_A);
    }

    // Check if 'tingkat' is set before accessing it.
    if (!isset($_POST['tingkat']) && !$is_edit_mode) {
        // This should ideally not be reached if the redirect logic in admin_init works correctly.
        // We can show an error or just stop execution for this page.
        echo "<div class='error'><p>Tingkat tidak valid atau sesi telah berakhir. Silakan kembali dan coba lagi.</p></div>";
        return;
    }

    $tingkat = isset($_POST['tingkat']) ? sanitize_text_field($_POST['tingkat']) : ($notulen_data ? $notulen_data['tingkat'] : '');
    $id_tingkat_override = isset($_POST['id_tingkat']) ? intval($_POST['id_tingkat']) : 0;
    $logged_user = get_current_user_id();

    echo '<h1>' . ($is_edit_mode ? 'Edit Notulen' : 'Input Notulen') . '</h1>';

    $selected_peserta = [];
    if ($is_edit_mode && $notulen_data && !empty($notulen_data['peserta_rapat'])) {
        $decoded = json_decode($notulen_data['peserta_rapat'], true);
        if (is_array($decoded)) {
            // Pastikan array numerik agar tidak ada warning undefined array key
            $selected_peserta = array_values($decoded);
        } else {
            $selected_peserta = [];
        }
    }
    ?>
<div class="notulenmu-container">
    <div class="mb-4">
        <a href="<?php echo esc_url(admin_url('admin.php?page=notulenmu-list')); ?>" class="inline-block bg-gray-300 hover:bg-gray-500 text-gray-800 font-semibold py-2 px-4 rounded">Kembali</a>
    </div>
    <form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="p-6 mr-4 bg-white shadow-md rounded-lg" id="notulenmu-form">
        <?php if ($is_edit_mode) : ?>
            <input type="hidden" name="edit_id" value="<?php echo esc_attr($notulen_id); ?>">
        <?php endif; ?>
        <input type="hidden" name="form_name" value="notulenmu_add_form">
        <input type="hidden" name="user_id" value="<?php echo $logged_user; ?>">
        <input type="hidden" name="action" value="handle_notulen_form">
        <input type="hidden" name="tingkat" value="<?php echo esc_attr($tingkat); ?>">
        <?php if ($id_tingkat_override > 0): ?>
            <input type="hidden" name="id_tingkat_override" value="<?php echo esc_attr($id_tingkat_override); ?>">
        <?php endif; ?>
        <?php wp_nonce_field('notulenmu_add_nonce', 'notulenmu_nonce'); ?>

        <!-- Main Form -->
        <div class="grid gap-7 w-full">
            <!-- Peserta -->
            <div class="flex flex-col space-y-2 ">
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-blue-500" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-user">
                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                        <path d="M8 7a4 4 0 1 0 8 0a4 4 0 0 0 -8 0" />
                        <path d="M6 21v-2a4 4 0 0 1 4 -4h4a4 4 0 0 1 4 4v2" />
                    </svg>
                    <label class="font-semibold text-[15px]">Peserta</label>
                </div>
                <div id="pengurus-list">
                    <?php
                    global $wpdb;
                    $user_id = get_current_user_id();
                    $setting_table = $wpdb->prefix . 'sicara_settings';
                    $settings = $wpdb->get_row($wpdb->prepare(
                        "SELECT pwm, pdm, pcm, prm FROM $setting_table WHERE user_id = %d",
                        $user_id
                    ), ARRAY_A);
                    
                    // Determine which id_tingkat to use for pengurus query
                    $query_id_tingkat = [];
                    if ($id_tingkat_override > 0) {
                        // Use the specific selected id_tingkat
                        $query_id_tingkat = [$id_tingkat_override];
                    } else if ($settings) {
                        // Use user's settings
                        $query_id_tingkat = array_filter([$settings['pwm'], $settings['pdm'], $settings['pcm'], $settings['prm']]);
                    }
                    
                    if (!empty($query_id_tingkat)) {
                        $pengurus_table = $wpdb->prefix . 'sicara_pengurus';
                        $placeholders = implode(',', array_fill(0, count($query_id_tingkat), '%d'));
                        $query = "SELECT id, nama, tingkat, jabatan FROM $pengurus_table WHERE tingkat = %s AND id_tingkat IN ($placeholders)";
                        $query = $wpdb->prepare($query, array_merge([$tingkat], $query_id_tingkat));
                        $pengurus = $wpdb->get_results($query);
                        if (!empty($pengurus)) {
                            echo '<table style="border-collapse: collapse; width: 100%;" border="1" cellpadding="5">';
                            echo '<tr style="background-color: #f2f2f2; text-align: left;">';
                            echo '<th style="border: 1px solid black; padding: 8px; width: 10px; text-align: center;">No</th>';
                            echo '<th style="border: 1px solid black; padding: 8px; text-align: center;">Nama</th>';
                            echo '<th style="border: 1px solid black; padding: 8px; text-align: center;">Tingkat</th>';
                            echo '<th style="border: 1px solid black; padding: 8px; text-align: center;">Jabatan</th>';
                            echo '<th style="border: 1px solid black; padding: 8px; text-align: center;">Kehadiran</th>';
                            echo '</tr>';
                            $no = 1;
                            foreach ($pengurus as $p) {
                                if (!isset($p->nama)) continue;
                                $checked = '';
                                if (is_array($selected_peserta) && isset($p->nama)) {
                                    $checked = in_array($p->nama, $selected_peserta) ? 'checked' : '';
                                }
                                echo '<tr>';
                                echo '<td style="border: 1px solid black; padding: 8px; text-align: center;">' . esc_html($no) . '</td>';
                                echo '<td style="border: 1px solid black; padding: 8px;">' . esc_html($p->nama) . '</td>';
                                echo '<td style="border: 1px solid black; padding: 8px;">' . esc_html($p->tingkat) . '</td>';
                                echo '<td style="border: 1px solid black; padding: 8px;">' . esc_html($p->jabatan) . '</td>';
                                echo '<td style="border: 1px solid black; padding: 8px; text-align: center;">';
                                echo '<input type="checkbox" name="peserta_rapat[]" value="' . esc_attr($p->nama) . '" ' . $checked . '>';
                                echo '</td>';
                                echo '</tr>';
                                $no++;
                            }
                            echo '</table>';
                        } else {
                            echo "<p style='color: red;'>Tidak ada pengurus pada tingkat ini.</p>";
                        }
                    } else {
                        echo "<p>Tidak ada data pengurus yang tersedia.</p>";
                    }
                    ?>
                </div>
                <!-- Peserta tambahan: diisi dari peserta_rapat yang tidak masuk ke daftar pengurus -->
                <?php
                // Ambil daftar nama pengurus untuk membandingkan
                $pengurus_nama = [];
                if (!empty($pengurus)) {
                    foreach ($pengurus as $p) {
                        if (isset($p->nama)) {
                            $pengurus_nama[] = $p->nama;
                        }
                    }
                }
                // Cari peserta tambahan
                $peserta_tambahan = [];
                if (is_array($selected_peserta)) {
                    foreach ($selected_peserta as $peserta) {
                        if (!in_array($peserta, $pengurus_nama)) {
                            $peserta_tambahan[] = $peserta;
                        }
                    }
                }
                ?>
                <div class="flex flex-col space-y-2 mt-4">
                    <label class="block font-semibold text-[15px]">Peserta Tambahan (jika ada, pisahkan dengan koma)</label>
                    <input type="text" name="peserta_tambahan" class="w-full p-2 border rounded-md" value="<?php echo esc_attr(implode(', ', $peserta_tambahan)); ?>" placeholder="Nama peserta tambahan" />
                </div>
            </div>

            <!-- Topik Rapat -->
            <div class="flex flex-col space-y-2">
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-blue-500" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-article">
                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                        <path d="M3 4m0 2a2 2 0 0 1 2 -2h14a2 2 0 0 1 2 2v12a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2z" />
                        <path d="M7 8h10" />
                        <path d="M7 12h10" />
                        <path d="M7 16h10" />
                    </svg>
                    <label class="block font-semibold text-[15px]">Topik Rapat</label>
                </div>
                <input name="topik_rapat" id="topik_rapat" type="text" required class="w-full p-2 border rounded-md" value="<?php echo $is_edit_mode && $notulen_data ? esc_attr($notulen_data['topik_rapat']) : ''; ?>" />
            </div>

            <!-- Tanggal Rapat -->
            <div class="flex flex-col space-y-2">
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-blue-500" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-calendar-event">
                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                        <path d="M4 5m0 2a2 2 0 0 1 2 -2h12a2 2 0 0 1 2 2v12a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2z" />
                        <path d="M16 3l0 4" />
                        <path d="M8 3l0 4" />
                        <path d="M4 11l16 0" />
                        <path d="M8 15h2v2h-2z" />
                    </svg>
                    <label class="block font-semibold text-[15px]">Tanggal Rapat</label>
                </div>
                <input name="tanggal_rapat" id="tanggal_rapat" type="date" required class="w-full p-2 border rounded-md" value="<?php echo $is_edit_mode && $notulen_data ? esc_attr($notulen_data['tanggal_rapat']) : ''; ?>" />
            </div>

            <!-- Jam Mulai -->
            <div class="flex flex-col space-y-2">
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-blue-500" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-clock-hour-1">
                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                        <path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0" />
                        <path d="M12 7v5" />
                        <path d="M12 12l2 -3" />
                    </svg>
                    <label class="block font-semibold text-[15px]">Jam Mulai</label>
                </div>
                <input name="jam_mulai" type="time" class="w-full p-2 border rounded-md" value="<?php echo $is_edit_mode && $notulen_data ? esc_attr($notulen_data['jam_mulai']) : ''; ?>" />
            </div>

            <!-- Jam Berakhir -->
            <div class="flex flex-col space-y-2">
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-blue-500" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-clock-hour-3">
                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                        <path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0" />
                        <path d="M12 12h3.5" />
                        <path d="M12 7v5" />
                    </svg>
                    <label class="block font-semibold text-[15px]">Jam Berakhir</label>
                </div>
                <input name="jam_selesai" type="time" class="w-full p-2 border rounded-md" value="<?php echo $is_edit_mode && $notulen_data ? esc_attr($notulen_data['jam_selesai']) : ''; ?>" />
            </div>

            <!-- Sifat Rapat -->
            <div class="flex flex-col space-y-2">
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-blue-500" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-map-pin">
                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                        <path d="M9 11a3 3 0 1 0 6 0a3 3 0 0 0 -6 0" />
                        <path d="M17.657 16.657l-4.243 4.243a2 2 0 0 1 -2.827 0l-4.244 -4.243a8 8 0 1 1 11.314 0z" />
                    </svg>
                    <fieldset class="block font-semibold text-[15px]">Sifat Rapat</fieldset>
                </div>
                <div class="flex flex-wrap gap-4">
                    <?php
                    $sifat_options = ['Daring', 'Luring', 'Blended', 'Di luar kantor'];
                    if ($is_edit_mode && $notulen_data) {
                        // sifat_rapat di DB disimpan json, ambil stringnya
                        $decoded = json_decode($notulen_data['sifat_rapat'], true);
                        $selected_sifat = is_array($decoded) && count($decoded) ? $decoded[0] : '';
                    } else {
                        $selected_sifat = isset($_POST['sifat_rapat']) ? sanitize_text_field($_POST['sifat_rapat']) : '';
                    }
                    foreach ($sifat_options as $option) {
                        ?>
                        <label class="block mt-3">
                            <input type="radio" name="sifat_rapat" value="<?php echo esc_attr($option); ?>" class="mr-2" <?php checked($selected_sifat, $option); ?>> <?php echo esc_html($option); ?>
                        </label>
                    <?php } ?>
                </div>
            </div>

            <!-- Tempat Rapat -->
            <div class="flex flex-col space-y-2">
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-blue-500" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-building">
                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                        <path d="M3 21v-13a2 2 0 0 1 2 -2h14a2 2 0 0 1 2 2v13" />
                        <path d="M9 21v-6a2 2 0 0 1 2 -2h2a2 2 0 0 1 2 2v6" />
                        <path d="M9 9h6" />
                    </svg>
                    <label class="block font-semibold text-[15px]">Tempat Rapat</label>
                </div>
                <input name="tempat_rapat" id="tempat_rapat" type="text" class="w-full p-2 border rounded-md" value="<?php echo $is_edit_mode && $notulen_data ? esc_attr($notulen_data['tempat_rapat']) : ''; ?>" />
            </div>

            <!-- Foto Kegiatan -->
            <div class="flex flex-col space-y-2">
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-blue-500" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-photo">
                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                        <path d="M15 8h.01" />
                        <path d="M3 6a3 3 0 0 1 3 -3h12a3 3 0 0 1 3 3v12a3 3 0 0 1 -3 3h-12a3 3 0 0 1 -3 -3v-12z" />
                        <path d="M3 16l5 -5c.928 -.893 2.072 -.893 3 0l5 5" />
                        <path d="M14 14l1 -1c.928 -.893 2.072 -.893 3 0l3 3" />
                    </svg>
                    <label class="font-semibold text-[15px]">Foto Kegiatan</label>
                </div>
                <label class="flex flex-col items-center justify-center w-full h-32 border border-dashed rounded-md cursor-pointer hover:bg-gray-100">
                    <svg class="text-blue-500" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-upload">
                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                        <path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2 -2v-2" />
                        <path d="M7 9l5 -5l5 5" />
                        <path d="M12 4l0 12" />
                    </svg>
                    <span id="image-upload-text" class="mt-2 text-gray-600">Upload File</span>
                    <input name="image_upload" id="image_upload" type="file" accept="image/*" class="hidden" onchange="previewImage(this, 'image-preview-foto', 'image-upload-text')" />
                </label>
                <?php $foto_url = ($is_edit_mode && !empty($notulen_data['foto_kegiatan'])) ? esc_url($notulen_data['foto_kegiatan']) : ''; ?>
                <?php 
                // Gunakan kolom image_path dari DB untuk preview gambar
                $foto_url = ($is_edit_mode && !empty($notulen_data['image_path'])) ? esc_url($notulen_data['image_path']) : '';
                ?>
                <img id="image-preview-foto" src="<?php echo $foto_url; ?>" class="mt-2 w-40<?php echo $foto_url ? '' : ' hidden'; ?>" style="max-width:200px;max-height:200px;object-fit:contain;" />
            </div>

            <!-- Lampiran PDF -->
            <div class="flex flex-col space-y-2">
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-blue-500" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-file-type-pdf">
                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                        <path d="M14 3v4a1 1 0 0 0 1 1h4" />
                        <path d="M5 12v-7a2 2 0 0 1 2 -2h7l5 5v4" />
                        <path d="M5 18h1.5a1.5 1.5 0 0 0 0 -3h-1.5v6" />
                        <path d="M17 18h2" />
                        <path d="M20 15h-3v6" />
                        <path d="M11 15v6h1a2 2 0 0 0 2 -2v-2a2 2 0 0 0 -2 -2h-1z" />
                    </svg>
                    <label class="font-semibold text-[15px]">Upload Lampiran (PDF)</label>
                </div>
                <label class="flex flex-col items-center justify-center w-full h-32 border border-dashed rounded-md cursor-pointer hover:bg-gray-100">
                    <svg class="text-blue-500" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-upload">
                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                        <path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2 -2v-2" />
                        <path d="M7 9l5 -5l5 5" />
                        <path d="M12 4l0 12" />
                    </svg>
                    <span id="pdf-upload-text" class="mt-2 text-gray-600">
                        <?php 
                        if ($is_edit_mode && !empty($notulen_data['lampiran'])) {
                            echo esc_html(basename($notulen_data['lampiran']));
                        } else {
                            echo 'Upload File';
                        }
                        ?>
                    </span>
                    <input name="lampiran" id="lampiran" type="file" accept=".pdf" class="hidden" onchange="updateFileLabel(this, 'pdf-upload-text')" />
                </label>
            </div>

            <!-- Notulen Rapat -->
            <div class="flex flex-col space-y-2">
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-blue-500" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-file-text">
                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                        <path d="M14 3v4a1 1 0 0 0 1 1h4" />
                        <path d="M17 21h-10a2 2 0 0 1 -2 -2v-14a2 2 0 0 1 2 -2h7l5 5v11a2 2 0 0 1 -2 2z" />
                        <path d="M9 9h1" />
                        <path d="M9 13h6" />
                        <path d="M9 17h6" />
                    </svg>
                    <label class="block font-semibold text-[15px]">Notulen Rapat</label>
                </div>
                <?php
                $notulen_rapat_val = $is_edit_mode && $notulen_data ? $notulen_data['notulen_rapat'] : '';
                wp_editor($notulen_rapat_val, 'notulen_rapat', [
                    'textarea_name' => 'notulen_rapat',
                    'media_buttons' => true,
                    'textarea_rows' => 10,
                    'editor_class' => 'w-full',
                ]);
                ?>
            </div>
        </div>

        <div class="flex justify-end mt-6">
            <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-md"><?php echo $is_edit_mode ? 'Update Notulen' : 'Simpan Notulen'; ?></button>
        </div>
    </form>
</div>

    <script>
    function previewImage(input, previewId, textId) {
        const preview = document.getElementById(previewId);
        const text = document.getElementById(textId);
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.src = e.target.result;
                preview.classList.remove('hidden');
                text.textContent = input.files[0].name;
            };
            reader.readAsDataURL(input.files[0]);
        }
    }

    function updateFileLabel(input, textId) {
        const text = document.getElementById(textId);
        if (input.files && input.files[0]) {
            text.textContent = input.files[0].name;
        }
    }
    </script>
    <?php
}
?>