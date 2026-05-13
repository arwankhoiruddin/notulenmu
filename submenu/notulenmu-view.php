<?php
function notulenmu_view_page() {
    if (!current_user_can('edit_posts')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }
    global $wpdb;
    $user_id = get_current_user_id();
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    // Get user settings to determine accessible id_tingkat values
    $setting_table = $wpdb->prefix . 'sicara_settings';
    
    // Determine accessible id_tingkat based on user login prefix
    $current_user = wp_get_current_user();
    $user_level = '';
    $is_arwan = false;
    
    // Check if user is arwan (PP - Pimpinan Pusat)
    if (strpos($current_user->user_login, 'arwan') === 0) {
        $is_arwan = true;
    } else if (strpos($current_user->user_login, 'pwm.') === 0) {
        $user_level = 'pwm';
    } else if (strpos($current_user->user_login, 'pdm.') === 0) {
        $user_level = 'pdm';
    } else if (strpos($current_user->user_login, 'pcm.') === 0) {
        $user_level = 'pcm';
    } else if (strpos($current_user->user_login, 'prm.') === 0) {
        $user_level = 'prm';
    }
    
    // Initialize id_tingkat_list
    $id_tingkat_list = array();
    
    // For user arwan, allow access to all notulen (no filtering)
    if (!$is_arwan) {
        // For other users, get settings and filter by organizational hierarchy
        $settings = $wpdb->get_row($wpdb->prepare(
            "SELECT pwm, pdm, pcm, prm FROM $setting_table WHERE user_id = %d",
            $user_id
        ), ARRAY_A);

        if (!$settings) {
            echo '<div class="notulenmu-container"><div class="p-6 bg-white rounded shadow text-center">Data tidak ditemukan.</div></div>';
            return;
        }
        
        // Get all accessible id_tingkat based on organizational hierarchy
        $id_tingkat_list = notulenmu_get_accessible_id_tingkat($settings, $user_level);

        if (empty($id_tingkat_list)) {
            echo '<div class="notulenmu-container"><div class="p-6 bg-white rounded shadow text-center">You do not have sufficient permissions to access this page.</div></div>';
            return;
        }
    }

    // Query notulen with same permission logic as list page
    $table_name = $wpdb->prefix . 'salammu_notulenmu';
    
    if ($is_arwan) {
        // User arwan can see all notulen
        $query = "SELECT * FROM $table_name WHERE id = %d";
        $params = array($id);
    } else {
        // Other users see only notulen from their organizational hierarchy
        $placeholders = implode(',', array_fill(0, count($id_tingkat_list), '%s'));
        $query = "SELECT * FROM $table_name WHERE id = %d AND id_tingkat IN ($placeholders)";
        $params = array_merge([$id], $id_tingkat_list);
    }
    
    $notulen = $wpdb->get_row($wpdb->prepare($query, $params));
    if (!$notulen) {
        echo '<div class="notulenmu-container"><div class="p-6 bg-white rounded shadow text-center">Data tidak ditemukan.</div></div>';
        return;
    }
    
    // Process image and attachment paths
    $image_path = '';
    $lampiran_path = '';
    if ($notulen->image_path) {
        $upload_dir = wp_upload_dir();
        $image_path = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $notulen->image_path);
    }
    if ($notulen->lampiran) {
        $upload_dir = wp_upload_dir();
        $lampiran_path = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $notulen->lampiran);
    }
    
    $sifat_rapat = $notulen->sifat_rapat ? json_decode($notulen->sifat_rapat, true) : [];
    $tempat_rapat = $notulen->tempat_rapat ? $notulen->tempat_rapat : '';
    $peserta_rapat = $notulen->peserta_rapat ? json_decode($notulen->peserta_rapat, true) : [];
    $pdf_id = absint($notulen->id);
    $pdf_topic = sanitize_file_name(!empty($notulen->topik_rapat) ? $notulen->topik_rapat : 'rapat');
    $pdf_filename = 'notulen-' . $pdf_id . '-' . $pdf_topic . '.pdf';
    $share_title = !empty($notulen->topik_rapat) ? $notulen->topik_rapat : 'Notulen Rapat';
    $file_name_js = wp_json_encode($pdf_filename);
    $share_title_js = wp_json_encode($share_title);
    $inline_script = <<<JS
(function () {
    var content = document.getElementById('notulenmu-pdf-content');
    var downloadButton = document.getElementById('notulenmu-download-pdf');
    var shareButton = document.getElementById('notulenmu-share-pdf');
    var status = document.getElementById('notulenmu-share-status');
    var fileName = $file_name_js;
    var shareTitle = $share_title_js;

    if (!content || !downloadButton || !shareButton) {
        return;
    }

    function setStatus(message) {
        if (status) {
            status.textContent = message;
        }
    }

    function setBusy(isBusy, message) {
        downloadButton.disabled = isBusy;
        shareButton.disabled = isBusy;
        downloadButton.style.opacity = isBusy ? '0.7' : '1';
        shareButton.style.opacity = isBusy ? '0.7' : '1';
        downloadButton.style.cursor = isBusy ? 'wait' : 'pointer';
        shareButton.style.cursor = isBusy ? 'wait' : 'pointer';
        setStatus(message || '');
    }

    function downloadBlob(blob) {
        var blobUrl = URL.createObjectURL(blob);
        var link = document.createElement('a');
        link.href = blobUrl;
        link.download = fileName;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        setTimeout(function () {
            URL.revokeObjectURL(blobUrl);
        }, 1000);
    }

    async function generatePdfBlob() {
        if (typeof html2pdf !== 'function') {
            throw new Error('Library html2pdf belum dimuat.');
        }

        var options = {
            margin: 10,
            filename: fileName,
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: {
                scale: 2,
                useCORS: true,
                scrollY: 0,
                onclone: function(clonedDoc) {
                    var svgs = clonedDoc.querySelectorAll('#notulenmu-share-content svg');
                    svgs.forEach(function(svg) {
                        var parent = svg.parentElement;
                        if (parent && parent.children.length === 1) {
                            parent.style.display = 'none';
                        } else {
                            svg.style.display = 'none';
                        }
                    });
                }
            },
            jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
            pagebreak: { mode: ['css', 'legacy'] }
        };
        return await html2pdf().set(options).from(content).outputPdf('blob');
    }

    downloadButton.addEventListener('click', async function () {
        try {
            setBusy(true, 'Mengekspor notulen ke PDF...');
            var blob = await generatePdfBlob();
            downloadBlob(blob);
            setBusy(false, 'PDF berhasil diekspor.');
        } catch (error) {
            console.error(error);
            setBusy(false, error && error.message ? error.message : 'Gagal mengekspor PDF.');
        }
    });

    shareButton.addEventListener('click', async function () {
        try {
            setBusy(true, 'Menyiapkan PDF untuk dibagikan...');
            var blob = await generatePdfBlob();
            var pdfFile = new File([blob], fileName, { type: 'application/pdf' });

            // File sharing via the Web Share API is only available in supported browsers.
            if (navigator.canShare && navigator.canShare({ files: [pdfFile] })) {
                await navigator.share({
                    title: shareTitle,
                    text: 'Notulen rapat terlampir dalam bentuk PDF.',
                    files: [pdfFile]
                });
                setBusy(false, 'Notulen berhasil dibagikan.');
                return;
            }

            downloadBlob(blob);
            setBusy(false, 'Browser tidak mendukung fitur berbagi file. PDF diunduh sebagai gantinya.');
        } catch (error) {
            if (error && error.name === 'AbortError') {
                setBusy(false, 'Proses share dibatalkan.');
                return;
            }

            console.error(error);
            setBusy(false, error && error.message ? error.message : 'Gagal membagikan notulen.');
        }
    });
})();
JS;
    // Bundled html2pdf asset is stored in assets/js for offline WordPress admin usage.
    wp_enqueue_script('notulenmu-html2pdf', plugin_dir_url(__FILE__) . '../assets/js/html2pdf.bundle.min.js', array(), '0.14.0', true);
    wp_add_inline_script('notulenmu-html2pdf', $inline_script);
?>
<div class="notulenmu-container">
    <!-- Header Section with Brand Colors -->
    <div class="relative p-6 bg-[#2d3476] shadow-lg rounded-lg mb-6 ml-0 text-white overflow-hidden">
        <img src="<?php echo plugin_dir_url(__FILE__) . '../assets/img/image.png'; ?>"
            alt="Notulenmu"
            class="absolute top-0 right-5 w-40 opacity-20">
        
        <div class="relative z-10">
            <h1 class="text-2xl font-bold mb-2">Detail Notulen</h1>
            <p class="text-blue-100">Informasi lengkap notulen rapat</p>
        </div>
    </div>

    <!-- Main Content -->
    <div class="bg-white shadow-lg rounded-lg overflow-hidden">
        <div id="notulenmu-share-content">
            <!-- Meeting Overview Section -->
            <div class="bg-gradient-to-r from-blue-50 to-indigo-50 p-6 border-b border-gray-200">
                <div class="flex items-center gap-3 mb-4">
                    <div class="bg-[#2d3476] p-2 rounded-lg">
                        <svg class="w-5 h-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-xl font-bold text-gray-800"><?php echo esc_html($notulen->topik_rapat); ?></h2>
                        <p class="text-gray-600"><?php echo esc_html($notulen->tingkat); ?></p>
                    </div>
                </div>
            </div>

            <!-- Meeting Details Grid -->
            <div class="p-6">
                <div class="grid md:grid-cols-2 gap-6 mb-8">
                    <!-- Date and Time Info -->
                    <div class="space-y-4">
                        <h3 class="text-lg font-semibold text-gray-800 border-b border-gray-200 pb-2">Waktu & Tempat</h3>
                        
                        <div class="flex items-start gap-3">
                            <div class="bg-blue-100 p-2 rounded-lg">
                                <svg class="w-4 h-4 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <div>
                                <p class="font-medium text-gray-800">Tanggal Rapat</p>
                                <p class="text-gray-600"><?php echo date('d F Y', strtotime($notulen->tanggal_rapat)); ?></p>
                            </div>
                        </div>

                        <div class="flex items-start gap-3">
                            <div class="bg-green-100 p-2 rounded-lg">
                                <svg class="w-4 h-4 text-green-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <div>
                                <p class="font-medium text-gray-800">Waktu</p>
                                <p class="text-gray-600"><?php echo esc_html($notulen->jam_mulai); ?> - <?php echo esc_html($notulen->jam_selesai); ?></p>
                            </div>
                        </div>

                        <?php if (!empty($tempat_rapat)) { ?>
                        <div class="flex items-start gap-3">
                            <div class="bg-purple-100 p-2 rounded-lg">
                                <svg class="w-4 h-4 text-purple-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                            </div>
                            <div>
                                <p class="font-medium text-gray-800">Tempat Rapat</p>
                                <p class="text-gray-600"><?php echo esc_html($tempat_rapat); ?></p>
                            </div>
                        </div>
                        <?php } ?>
                    </div>

                    <!-- Meeting Type and Participants -->
                    <div class="space-y-4">
                        <h3 class="text-lg font-semibold text-gray-800 border-b border-gray-200 pb-2">Informasi Rapat</h3>
                        
                        <?php if (!empty($sifat_rapat)) { ?>
                        <div class="flex items-start gap-3">
                            <div class="bg-orange-100 p-2 rounded-lg">
                                <svg class="w-4 h-4 text-orange-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a1.994 1.994 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                                </svg>
                            </div>
                            <div>
                                <p class="font-medium text-gray-800">Sifat Rapat</p>
                                <div class="flex flex-wrap gap-1 mt-1">
                                    <?php foreach ($sifat_rapat as $sifat) { ?>
                                        <span class="bg-orange-100 text-orange-800 text-xs px-2 py-1 rounded-full"><?php echo esc_html($sifat); ?></span>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>
                        <?php } ?>

                        <?php if (!empty($peserta_rapat)) { ?>
                        <div class="flex items-start gap-3">
                            <div class="bg-indigo-100 p-2 rounded-lg">
                                <svg class="w-4 h-4 text-indigo-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z" />
                                </svg>
                            </div>
                            <div class="w-full">
                                <p class="font-medium text-gray-800 mb-2">Peserta Rapat</p>
                                <div class="bg-indigo-50 rounded-lg p-3">
                                    <div class="flex flex-wrap gap-2">
                                        <?php foreach ($peserta_rapat as $peserta) { ?>
                                            <span class="bg-white text-indigo-700 text-sm px-3 py-1 rounded-md border border-indigo-200"><?php echo esc_html($peserta); ?></span>
                                        <?php } ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php } ?>
                    </div>
                </div>

                <!-- Meeting Summary -->
                <div class="mb-8">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="bg-gray-100 p-2 rounded-lg">
                            <svg class="w-5 h-5 text-gray-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-800">Rangkuman Rapat</h3>
                    </div>
                    <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                        <div class="prose prose-sm max-w-none text-gray-700">
                            <?php echo wp_kses_post($notulen->notulen_rapat); ?>
                        </div>
                    </div>
                </div>

                <!-- Attachments Section -->
                <?php if (!empty($image_path) || !empty($lampiran_path)) { ?>
                <div class="border-t border-gray-200 pt-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Lampiran</h3>
                    <div class="grid md:grid-cols-2 gap-6">
                        <?php if (!empty($image_path)) { ?>
                        <div>
                            <div class="flex items-center gap-2 mb-3">
                                <div class="bg-pink-100 p-2 rounded-lg">
                                    <svg class="w-4 h-4 text-pink-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                </div>
                                <p class="font-medium text-gray-800">Foto Kegiatan</p>
                            </div>
                            <div class="bg-gray-50 rounded-lg p-3">
                                <img src="<?php echo esc_url($image_path); ?>" class="w-full max-w-sm rounded-lg shadow-md hover:shadow-lg transition-shadow duration-200" alt="Foto Kegiatan">
                            </div>
                        </div>
                        <?php } ?>

                        <?php if (!empty($lampiran_path)) { ?>
                        <div>
                            <div class="flex items-center gap-2 mb-3">
                                <div class="bg-red-100 p-2 rounded-lg">
                                    <svg class="w-4 h-4 text-red-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                </div>
                                <p class="font-medium text-gray-800">Dokumen Lampiran</p>
                            </div>
                            <div class="bg-gray-50 rounded-lg p-4">
                                <a href="<?php echo esc_url($lampiran_path); ?>" target="_blank" 
                                   class="inline-flex items-center gap-2 bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg transition-colors duration-200">
                                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    Download Lampiran PDF
                                </a>
                            </div>
                        </div>
                        <?php } ?>
                    </div>
                </div>
                <?php } ?>
            </div>
        </div>

        <!-- Hidden PDF export content with h2 field labels -->
        <div id="notulenmu-pdf-content" aria-hidden="true" style="position:absolute;left:-9999px;top:0;width:210mm;font-family:Arial,sans-serif;padding:20px;color:#000;background:#fff;">
            <h1 style="font-size:22px;margin-bottom:16px;"><?php echo esc_html($notulen->topik_rapat); ?></h1>

            <h2 style="font-size:16px;margin-top:16px;margin-bottom:4px;">Tingkat</h2>
            <p style="margin:0 0 8px;"><?php echo esc_html($notulen->tingkat); ?></p>

            <h2 style="font-size:16px;margin-top:16px;margin-bottom:4px;">Tanggal Rapat</h2>
            <p style="margin:0 0 8px;"><?php echo esc_html(date('d F Y', strtotime($notulen->tanggal_rapat))); ?></p>

            <h2 style="font-size:16px;margin-top:16px;margin-bottom:4px;">Waktu</h2>
            <p style="margin:0 0 8px;"><?php echo esc_html($notulen->jam_mulai); ?> - <?php echo esc_html($notulen->jam_selesai); ?></p>

            <?php if (!empty($tempat_rapat)) { ?>
            <h2 style="font-size:16px;margin-top:16px;margin-bottom:4px;">Tempat</h2>
            <p style="margin:0 0 8px;"><?php echo esc_html($tempat_rapat); ?></p>
            <?php } ?>

            <?php if (!empty($sifat_rapat)) { ?>
            <h2 style="font-size:16px;margin-top:16px;margin-bottom:4px;">Sifat Rapat</h2>
            <p style="margin:0 0 8px;"><?php echo esc_html(implode(', ', $sifat_rapat)); ?></p>
            <?php } ?>

            <?php if (!empty($peserta_rapat)) { ?>
            <h2 style="font-size:16px;margin-top:16px;margin-bottom:4px;">Peserta Rapat</h2>
            <p style="margin:0 0 8px;"><?php echo esc_html(implode(', ', $peserta_rapat)); ?></p>
            <?php } ?>

            <h2 style="font-size:16px;margin-top:16px;margin-bottom:4px;">Keputusan Rapat</h2>
            <div style="margin:0 0 8px;"><?php echo wp_kses_post($notulen->notulen_rapat); ?></div>
        </div>

        <!-- Footer Actions -->
        <div class="bg-gray-50 px-6 py-4 border-t border-gray-200">
            <div class="flex justify-between items-center">
                <div class="text-sm text-gray-500">
                    ID Notulen: #<?php echo esc_html($notulen->id); ?>
                </div>
                <div class="flex gap-3 flex-wrap justify-end items-center">
                    <span id="notulenmu-share-status" class="text-sm text-gray-500" aria-live="polite"></span>
                    <button type="button" id="notulenmu-download-pdf"
                            class="inline-flex items-center gap-2 bg-red-600 hover:bg-red-700 text-white font-medium px-6 py-2 rounded-lg transition-colors duration-200">
                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        Ekspor PDF
                    </button>
                    <button type="button" id="notulenmu-share-pdf"
                            class="inline-flex items-center gap-2 bg-[#2d3476] hover:bg-[#1e2355] text-white font-medium px-6 py-2 rounded-lg transition-colors duration-200">
                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.882 13.12 9 12.827 9 12.5s-.118-.62-.316-.842m0 1.684a1.4 1.4 0 01-1.368.158l-3.845-1.922a1.5 1.5 0 010-2.684l3.845-1.922A1.5 1.5 0 019.5 8.5c0 .327-.118.62-.316.842m0 3.316l6.132 3.066A1.5 1.5 0 0017.5 14.5c0-.327-.118-.62-.316-.842m0 0a1.4 1.4 0 00-1.368-.158L9.684 16.566m7.5-5.224c.198-.222.316-.515.316-.842a1.5 1.5 0 00-2.184-1.342L9.184 12.224m8 1.118L9.184 9.158" />
                        </svg>
                        Share Notulen
                    </button>
                    <a href="<?php echo admin_url('admin.php?page=notulenmu-list'); ?>" 
                       class="inline-flex items-center gap-2 bg-[#2d3476] hover:bg-[#1e2355] text-white font-medium px-6 py-2 rounded-lg transition-colors duration-200">
                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                        Kembali ke Daftar
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
}
