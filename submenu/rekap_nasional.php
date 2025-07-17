<?php
function notulenmu_rekap_nasional_page() {
    if (!current_user_can('manage_options')) {
        echo '<div class="notice notice-error"><p>Hanya administrator yang dapat mengakses halaman ini.</p></div>';
        return;
    }
    global $wpdb;
    // --- Optimized Data Notulen & Kegiatan per Wilayah/Daerah/Cabang/Ranting (nasional) ---
    $table_notulen = $wpdb->prefix . 'salammu_notulenmu';
    $table_kegiatan = $wpdb->prefix . 'salammu_kegiatanmu';
    $tingkat_list = ['wilayah', 'daerah', 'cabang', 'ranting'];
    $tingkat_group = $kegiatan_group = [];
    
    // Initialize arrays
    foreach ($tingkat_list as $tingkat) {
        $tingkat_group[$tingkat] = [];
        $kegiatan_group[$tingkat] = [];
    }
    
    // Single optimized query using UNION untuk gabungkan notulen dan kegiatan
    $placeholders = implode(',', array_fill(0, count($tingkat_list), '%s'));
    $combined_query = $wpdb->prepare("
        SELECT 'notulen' as source, tingkat, id_tingkat, COUNT(*) as jumlah 
        FROM $table_notulen 
        WHERE tingkat IN ($placeholders) 
        GROUP BY tingkat, id_tingkat
        UNION ALL
        SELECT 'kegiatan' as source, tingkat, id_tingkat, COUNT(*) as jumlah 
        FROM $table_kegiatan 
        WHERE tingkat IN ($placeholders) 
        GROUP BY tingkat, id_tingkat
        ORDER BY source, tingkat, id_tingkat
    ", array_merge($tingkat_list, $tingkat_list));
    
    $combined_results = $wpdb->get_results($combined_query);
    
    // Process results
    foreach ($combined_results as $row) {
        if ($row->source === 'notulen') {
            $tingkat_group[$row->tingkat][$row->id_tingkat] = (int)$row->jumlah;
        } else {
            $kegiatan_group[$row->tingkat][$row->id_tingkat] = (int)$row->jumlah;
        }
    }
    // --- Helper for organization name with caching dan batch loading ---
    if (!function_exists('notulenmu_get_nama_organisasi')) {
        function notulenmu_get_nama_organisasi($id) {
            if (empty($id) || $id === '0') return 'Tidak diketahui';
            
            // Validasi ID harus numerik
            if (!is_numeric($id)) return 'ID tidak valid';
            
            $cache_key = 'org_nama_' . $id;
            $cached = get_transient($cache_key);
            if ($cached !== false) return $cached;
            
            $url = 'https://old.sicara.id/api/v0/organisation/' . intval($id);
            $response = wp_remote_get($url, [
                'timeout' => 5,
                'headers' => [
                    'User-Agent' => 'NotulenMu Plugin'
                ]
            ]);
            
            // Handle berbagai jenis error
            if (is_wp_error($response)) {
                $error_name = 'Error: ' . $response->get_error_message();
                set_transient($cache_key, $error_name, 5 * MINUTE_IN_SECONDS);
                return $error_name;
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            if ($response_code !== 200) {
                $error_name = 'HTTP Error: ' . $response_code;
                set_transient($cache_key, $error_name, 5 * MINUTE_IN_SECONDS);
                return $error_name;
            }
            
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            // Handle JSON parsing error
            if (json_last_error() !== JSON_ERROR_NONE) {
                $error_name = 'JSON Error: ' . json_last_error_msg();
                set_transient($cache_key, $error_name, 5 * MINUTE_IN_SECONDS);
                return $error_name;
            }
            
            $nama = isset($data['data']['nama']) && !empty($data['data']['nama']) ? 
                    sanitize_text_field($data['data']['nama']) : 'ID: ' . $id;
            
            set_transient($cache_key, $nama, 12 * HOUR_IN_SECONDS);
            return $nama;
        }
    }
    
    // Batch load untuk organisasi - kumpulkan semua ID unik terlebih dahulu
    if (!function_exists('notulenmu_batch_load_organisasi')) {
        function notulenmu_batch_load_organisasi($all_ids) {
            $uncached_ids = [];
            $results = [];
            
            // Check cache terlebih dahulu
            foreach ($all_ids as $id) {
                if (empty($id) || $id === '0') {
                    $results[$id] = 'Tidak diketahui';
                    continue;
                }
                
                if (!is_numeric($id)) {
                    $results[$id] = 'ID tidak valid';
                    continue;
                }
                
                $cache_key = 'org_nama_' . $id;
                $cached = get_transient($cache_key);
                if ($cached !== false) {
                    $results[$id] = $cached;
                } else {
                    $uncached_ids[] = $id;
                }
            }
            
            // Batch load yang belum di-cache dengan batching (max 10 per batch untuk avoid timeout)
            $batches = array_chunk($uncached_ids, 10);
            foreach ($batches as $batch) {
                foreach ($batch as $id) {
                    $results[$id] = notulenmu_get_nama_organisasi($id);
                    
                    // Small delay untuk avoid rate limiting
                    if (count($batch) > 1) {
                        usleep(100000); // 0.1 second delay
                    }
                }
            }
            
            return $results;
        }
    }
    
    // Kumpulkan semua ID organisasi yang unik
    $all_org_ids = [];
    foreach (['wilayah', 'daerah', 'cabang', 'ranting'] as $tingkat) {
        if (!empty($tingkat_group[$tingkat])) {
            $all_org_ids = array_merge($all_org_ids, array_keys($tingkat_group[$tingkat]));
        }
        if (!empty($kegiatan_group[$tingkat])) {
            $all_org_ids = array_merge($all_org_ids, array_keys($kegiatan_group[$tingkat]));
        }
    }
    $all_org_ids = array_unique($all_org_ids);
    
    // Batch load semua nama organisasi sekaligus
    $org_names = notulenmu_batch_load_organisasi($all_org_ids);
    // --- Prepare labels/data dengan batch loaded names ---
    $wilayah_labels = $wilayah_data = [];
    if (!empty($tingkat_group['wilayah'])) {
        foreach ($tingkat_group['wilayah'] as $id => $jumlah) {
            $wilayah_labels[] = isset($org_names[$id]) ? $org_names[$id] : 'ID: ' . $id;
            $wilayah_data[] = $jumlah;
        }
    }
    
    $daerah_labels = $daerah_data = [];
    if (!empty($tingkat_group['daerah'])) {
        foreach ($tingkat_group['daerah'] as $id => $jumlah) {
            $daerah_labels[] = isset($org_names[$id]) ? $org_names[$id] : 'ID: ' . $id;
            $daerah_data[] = $jumlah;
        }
    }
    
    $cabang_labels = $cabang_data = [];
    if (!empty($tingkat_group['cabang'])) {
        foreach ($tingkat_group['cabang'] as $id => $jumlah) {
            $cabang_labels[] = isset($org_names[$id]) ? $org_names[$id] : 'ID: ' . $id;
            $cabang_data[] = $jumlah;
        }
    }
    
    $ranting_labels = $ranting_data = [];
    if (!empty($tingkat_group['ranting'])) {
        foreach ($tingkat_group['ranting'] as $id => $jumlah) {
            $ranting_labels[] = isset($org_names[$id]) ? $org_names[$id] : 'ID: ' . $id;
            $ranting_data[] = $jumlah;
        }
    }
    
    // --- Kegiatan dengan batch loaded names ---
    $kegiatan_wilayah_labels = $kegiatan_wilayah_data = [];
    if (!empty($kegiatan_group['wilayah'])) {
        foreach ($kegiatan_group['wilayah'] as $id => $jumlah) {
            $kegiatan_wilayah_labels[] = isset($org_names[$id]) ? $org_names[$id] : 'ID: ' . $id;
            $kegiatan_wilayah_data[] = $jumlah;
        }
    }
    
    $kegiatan_daerah_labels = $kegiatan_daerah_data = [];
    if (!empty($kegiatan_group['daerah'])) {
        foreach ($kegiatan_group['daerah'] as $id => $jumlah) {
            $kegiatan_daerah_labels[] = isset($org_names[$id]) ? $org_names[$id] : 'ID: ' . $id;
            $kegiatan_daerah_data[] = $jumlah;
        }
    }
    
    $kegiatan_cabang_labels = $kegiatan_cabang_data = [];
    if (!empty($kegiatan_group['cabang'])) {
        foreach ($kegiatan_group['cabang'] as $id => $jumlah) {
            $kegiatan_cabang_labels[] = isset($org_names[$id]) ? $org_names[$id] : 'ID: ' . $id;
            $kegiatan_cabang_data[] = $jumlah;
        }
    }
    
    $kegiatan_ranting_labels = $kegiatan_ranting_data = [];
    if (!empty($kegiatan_group['ranting'])) {
        foreach ($kegiatan_group['ranting'] as $id => $jumlah) {
            $kegiatan_ranting_labels[] = isset($org_names[$id]) ? $org_names[$id] : 'ID: ' . $id;
            $kegiatan_ranting_data[] = $jumlah;
        }
    }
    ?>
    <div class="pr-4">
        <h2 class="mt-4 text-xl font-semibold text-white relative z-10">Grafik Jumlah Notulen per Wilayah (nasional), Daerah, Cabang, dan Ranting</h2>
        <?php if (empty($wilayah_data) && empty($daerah_data) && empty($cabang_data) && empty($ranting_data)): ?>
            <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded mt-4">
                <p>Belum ada data notulen untuk ditampilkan.</p>
            </div>
        <?php else: ?>
        <div style="display: flex; gap: 16px; flex-wrap: wrap;">
            <div style="flex:1; min-width:200px; text-align:center;">
                <span style="font-weight: bold;">Wilayah</span>
                <?php if (empty($wilayah_data)): ?>
                    <div style="height: 200px; display: flex; align-items: center; justify-content: center; border: 1px dashed #ccc;">
                        <span style="color: #666;">Tidak ada data</span>
                    </div>
                <?php else: ?>
                    <canvas id="pieWilayah" width="200" height="200"></canvas>
                <?php endif; ?>
            </div>
            <div style="flex:1; min-width:200px; text-align:center;">
                <span style="font-weight: bold;">Daerah</span>
                <?php if (empty($daerah_data)): ?>
                    <div style="height: 200px; display: flex; align-items: center; justify-content: center; border: 1px dashed #ccc;">
                        <span style="color: #666;">Tidak ada data</span>
                    </div>
                <?php else: ?>
                    <canvas id="pieDaerah" width="200" height="200"></canvas>
                <?php endif; ?>
            </div>
            <div style="flex:1; min-width:200px; text-align:center;">
                <span style="font-weight: bold;">Cabang</span>
                <?php if (empty($cabang_data)): ?>
                    <div style="height: 200px; display: flex; align-items: center; justify-content: center; border: 1px dashed #ccc;">
                        <span style="color: #666;">Tidak ada data</span>
                    </div>
                <?php else: ?>
                    <canvas id="pieCabang" width="200" height="200"></canvas>
                <?php endif; ?>
            </div>
            <div style="flex:1; min-width:200px; text-align:center;">
                <span style="font-weight: bold;">Ranting</span>
                <?php if (empty($ranting_data)): ?>
                    <div style="height: 200px; display: flex; align-items: center; justify-content: center; border: 1px dashed #ccc;">
                        <span style="color: #666;">Tidak ada data</span>
                    </div>
                <?php else: ?>
                    <canvas id="pieRanting" width="200" height="200"></canvas>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <div class="pr-4">
        <h2 class="mt-4 text-xl font-semibold text-white relative z-10">Grafik Jumlah Kegiatan per Wilayah (nasional), Daerah, Cabang, dan Ranting</h2>
        <?php if (empty($kegiatan_wilayah_data) && empty($kegiatan_daerah_data) && empty($kegiatan_cabang_data) && empty($kegiatan_ranting_data)): ?>
            <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded mt-4">
                <p>Belum ada data kegiatan untuk ditampilkan.</p>
            </div>
        <?php else: ?>
        <div style="display: flex; gap: 16px; flex-wrap: wrap;">
            <div style="flex:1; min-width:200px; text-align:center;">
                <span style="font-weight: bold;">Wilayah</span>
                <?php if (empty($kegiatan_wilayah_data)): ?>
                    <div style="height: 200px; display: flex; align-items: center; justify-content: center; border: 1px dashed #ccc;">
                        <span style="color: #666;">Tidak ada data</span>
                    </div>
                <?php else: ?>
                    <canvas id="pieKegiatanWilayah" width="200" height="200"></canvas>
                <?php endif; ?>
            </div>
            <div style="flex:1; min-width:200px; text-align:center;">
                <span style="font-weight: bold;">Daerah</span>
                <?php if (empty($kegiatan_daerah_data)): ?>
                    <div style="height: 200px; display: flex; align-items: center; justify-content: center; border: 1px dashed #ccc;">
                        <span style="color: #666;">Tidak ada data</span>
                    </div>
                <?php else: ?>
                    <canvas id="pieKegiatanDaerah" width="200" height="200"></canvas>
                <?php endif; ?>
            </div>
            <div style="flex:1; min-width:200px; text-align:center;">
                <span style="font-weight: bold;">Cabang</span>
                <?php if (empty($kegiatan_cabang_data)): ?>
                    <div style="height: 200px; display: flex; align-items: center; justify-content: center; border: 1px dashed #ccc;">
                        <span style="color: #666;">Tidak ada data</span>
                    </div>
                <?php else: ?>
                    <canvas id="pieKegiatanCabang" width="200" height="200"></canvas>
                <?php endif; ?>
            </div>
            <div style="flex:1; min-width:200px; text-align:center;">
                <span style="font-weight: bold;">Ranting</span>
                <?php if (empty($kegiatan_ranting_data)): ?>
                    <div style="height: 200px; display: flex; align-items: center; justify-content: center; border: 1px dashed #ccc;">
                        <span style="color: #666;">Tidak ada data</span>
                    </div>
                <?php else: ?>
                    <canvas id="pieKegiatanRanting" width="200" height="200"></canvas>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            // Function to generate multiple colors for pie charts
            function generateColors(count, baseColor) {
                const colors = [];
                const colorVariations = [
                    baseColor,
                    baseColor + '88', // Semi-transparent
                    baseColor + 'cc', // More opaque
                    baseColor + '44', // Very transparent
                ];
                
                for (let i = 0; i < count; i++) {
                    colors.push(colorVariations[i % colorVariations.length]);
                }
                return colors;
            }
            
            // Notulen Charts - Bar Chart
            <?php if (!empty($wilayah_data)): ?>
            new Chart(document.getElementById('pieWilayah').getContext('2d'), {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($wilayah_labels); ?>,
                    datasets: [{
                        label: 'Jumlah Notulen',
                        data: <?php echo json_encode($wilayah_data); ?>,
                        backgroundColor: generateColors(<?php echo count($wilayah_data); ?>, '#2d3476'),
                        borderWidth: 1,
                        borderColor: '#ffffff'
                    }]
                },
                options: {
                    indexAxis: 'x',
                    responsive: true,
                    plugins: {
                        legend: {display: false},
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.label + ': ' + context.parsed.y + ' notulen';
                                }
                            }
                        }
                    },
                    scales: {
                        x: {title: {display: true, text: 'Wilayah'}},
                        y: {title: {display: true, text: 'Jumlah Notulen'}, beginAtZero: true}
                    }
                }
            });
            <?php endif; ?>

            <?php if (!empty($daerah_data)): ?>
            new Chart(document.getElementById('pieDaerah').getContext('2d'), {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($daerah_labels); ?>,
                    datasets: [{
                        label: 'Jumlah Notulen',
                        data: <?php echo json_encode($daerah_data); ?>,
                        backgroundColor: generateColors(<?php echo count($daerah_data); ?>, '#4e5ba6'),
                        borderWidth: 1,
                        borderColor: '#ffffff'
                    }]
                },
                options: {
                    indexAxis: 'x',
                    responsive: true,
                    plugins: {
                        legend: {display: false},
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.label + ': ' + context.parsed.y + ' notulen';
                                }
                            }
                        }
                    },
                    scales: {
                        x: {title: {display: true, text: 'Daerah'}},
                        y: {title: {display: true, text: 'Jumlah Notulen'}, beginAtZero: true}
                    }
                }
            });
            <?php endif; ?>

            <?php if (!empty($cabang_data)): ?>
            new Chart(document.getElementById('pieCabang').getContext('2d'), {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($cabang_labels); ?>,
                    datasets: [{
                        label: 'Jumlah Notulen',
                        data: <?php echo json_encode($cabang_data); ?>,
                        backgroundColor: generateColors(<?php echo count($cabang_data); ?>, '#6c7fd1'),
                        borderWidth: 1,
                        borderColor: '#ffffff'
                    }]
                },
                options: {
                    indexAxis: 'x',
                    responsive: true,
                    plugins: {
                        legend: {display: false},
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.label + ': ' + context.parsed.y + ' notulen';
                                }
                            }
                        }
                    },
                    scales: {
                        x: {title: {display: true, text: 'Cabang'}},
                        y: {title: {display: true, text: 'Jumlah Notulen'}, beginAtZero: true}
                    }
                }
            });
            <?php endif; ?>

            <?php if (!empty($ranting_data)): ?>
            new Chart(document.getElementById('pieRanting').getContext('2d'), {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($ranting_labels); ?>,
                    datasets: [{
                        label: 'Jumlah Notulen',
                        data: <?php echo json_encode($ranting_data); ?>,
                        backgroundColor: generateColors(<?php echo count($ranting_data); ?>, '#a3b0e0'),
                        borderWidth: 1,
                        borderColor: '#ffffff'
                    }]
                },
                options: {
                    indexAxis: 'x',
                    responsive: true,
                    plugins: {
                        legend: {display: false},
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.label + ': ' + context.parsed.y + ' notulen';
                                }
                            }
                        }
                    },
                    scales: {
                        x: {title: {display: true, text: 'Ranting'}},
                        y: {title: {display: true, text: 'Jumlah Notulen'}, beginAtZero: true}
                    }
                }
            });
            <?php endif; ?>

            // Kegiatan Charts - Bar Chart
            <?php if (!empty($kegiatan_wilayah_data)): ?>
            new Chart(document.getElementById('pieKegiatanWilayah').getContext('2d'), {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($kegiatan_wilayah_labels); ?>,
                    datasets: [{
                        label: 'Jumlah Kegiatan',
                        data: <?php echo json_encode($kegiatan_wilayah_data); ?>,
                        backgroundColor: generateColors(<?php echo count($kegiatan_wilayah_data); ?>, '#f59e42'),
                        borderWidth: 1,
                        borderColor: '#ffffff'
                    }]
                },
                options: {
                    indexAxis: 'x',
                    responsive: true,
                    plugins: {
                        legend: {display: false},
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.label + ': ' + context.parsed.y + ' kegiatan';
                                }
                            }
                        }
                    },
                    scales: {
                        x: {title: {display: true, text: 'Wilayah'}},
                        y: {title: {display: true, text: 'Jumlah Kegiatan'}, beginAtZero: true}
                    }
                }
            });
            <?php endif; ?>

            <?php if (!empty($kegiatan_daerah_data)): ?>
            new Chart(document.getElementById('pieKegiatanDaerah').getContext('2d'), {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($kegiatan_daerah_labels); ?>,
                    datasets: [{
                        label: 'Jumlah Kegiatan',
                        data: <?php echo json_encode($kegiatan_daerah_data); ?>,
                        backgroundColor: generateColors(<?php echo count($kegiatan_daerah_data); ?>, '#f7c873'),
                        borderWidth: 1,
                        borderColor: '#ffffff'
                    }]
                },
                options: {
                    indexAxis: 'x',
                    responsive: true,
                    plugins: {
                        legend: {display: false},
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.label + ': ' + context.parsed.y + ' kegiatan';
                                }
                            }
                        }
                    },
                    scales: {
                        x: {title: {display: true, text: 'Daerah'}},
                        y: {title: {display: true, text: 'Jumlah Kegiatan'}, beginAtZero: true}
                    }
                }
            });
            <?php endif; ?>

            <?php if (!empty($kegiatan_cabang_data)): ?>
            new Chart(document.getElementById('pieKegiatanCabang').getContext('2d'), {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($kegiatan_cabang_labels); ?>,
                    datasets: [{
                        label: 'Jumlah Kegiatan',
                        data: <?php echo json_encode($kegiatan_cabang_data); ?>,
                        backgroundColor: generateColors(<?php echo count($kegiatan_cabang_data); ?>, '#e8aa3b'),
                        borderWidth: 1,
                        borderColor: '#ffffff'
                    }]
                },
                options: {
                    indexAxis: 'x',
                    responsive: true,
                    plugins: {
                        legend: {display: false},
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.label + ': ' + context.parsed.y + ' kegiatan';
                                }
                            }
                        }
                    },
                    scales: {
                        x: {title: {display: true, text: 'Cabang'}},
                        y: {title: {display: true, text: 'Jumlah Kegiatan'}, beginAtZero: true}
                    }
                }
            });
            <?php endif; ?>

            <?php if (!empty($kegiatan_ranting_data)): ?>
            new Chart(document.getElementById('pieKegiatanRanting').getContext('2d'), {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($kegiatan_ranting_labels); ?>,
                    datasets: [{
                        label: 'Jumlah Kegiatan',
                        data: <?php echo json_encode($kegiatan_ranting_data); ?>,
                        backgroundColor: generateColors(<?php echo count($kegiatan_ranting_data); ?>, '#d4934a'),
                        borderWidth: 1,
                        borderColor: '#ffffff'
                    }]
                },
                options: {
                    indexAxis: 'x',
                    responsive: true,
                    plugins: {
                        legend: {display: false},
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.label + ': ' + context.parsed.y + ' kegiatan';
                                }
                            }
                        }
                    },
                    scales: {
                        x: {title: {display: true, text: 'Ranting'}},
                        y: {title: {display: true, text: 'Jumlah Kegiatan'}, beginAtZero: true}
                    }
                }
            });
            <?php endif; ?>
        });
    </script>
    <?php
}
