<?php
/**
 * Fungsi untuk mengoptimasi database indexes
 * Panggil fungsi ini saat aktivasi plugin untuk performa optimal
 */
function optimize_rekap_topik_database() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'salammu_notulenmu';
    $setting_table = $wpdb->prefix . 'salammu_notulenmu_setting';
    
    // Cek dan buat indexes untuk performa optimal
    $indexes_to_create = [
        // Index untuk query utama rekap topik
        "CREATE INDEX IF NOT EXISTS idx_tingkat_id_tingkat ON $table_name (tingkat, id_tingkat)",
        "CREATE INDEX IF NOT EXISTS idx_topik_rapat ON $table_name (topik_rapat(100))", // Partial index
        "CREATE INDEX IF NOT EXISTS idx_id_desc ON $table_name (id DESC)", // Untuk ORDER BY
        
        // Index untuk tabel setting
        "CREATE INDEX IF NOT EXISTS idx_user_id ON $setting_table (user_id)"
    ];
    
    foreach ($indexes_to_create as $index_query) {
        $wpdb->query($index_query);
        if ($wpdb->last_error) {
            error_log("Index creation error: " . $wpdb->last_error);
        }
    }
}

// Halaman Rekap Topik (Wordcloud)
function rekap_topik_page() {
    global $wpdb;
    $user_id = get_current_user_id();
    
    // Validasi user ID
    if (!$user_id || $user_id <= 0) {
        wp_die('Akses tidak valid. Silakan login terlebih dahulu.');
    }
    
    // Optimasi: Caching untuk mengurangi database load
    $cache_key = 'rekap_topik_data_' . $user_id;
    $cache_duration = 300; // 5 menit
    $cached_data = get_transient($cache_key);
    
    if ($cached_data !== false) {
        extract($cached_data);
    } else {
        $table_name = $wpdb->prefix . 'salammu_notulenmu';
        $setting_table = $wpdb->prefix . 'salammu_notulenmu_setting';
        
        $settings = $wpdb->get_row($wpdb->prepare(
            "SELECT pwm, pdm, pcm, prm FROM $setting_table WHERE user_id = %d",
            $user_id
        ), ARRAY_A);
        
        // Validasi apakah settings ditemukan
        if (!$settings || !is_array($settings)) {
            $settings = ['pwm' => '', 'pdm' => '', 'pcm' => '', 'prm' => ''];
        }
        
        $id_tingkat_map = [
            'wilayah' => $settings['pwm'] ?? '',
            'daerah' => $settings['pdm'] ?? '',
            'cabang' => $settings['pcm'] ?? '',
            'ranting' => $settings['prm'] ?? ''
        ];
        
        // Optimasi: Process data dalam satu kali query
        $tingkat_keys = ['wilayah', 'daerah', 'cabang', 'ranting'];
        $jumlah_per_tingkat = array_fill(0, count($tingkat_keys), 0);
        $results = [];
        
        // Optimasi: Gunakan prepared statement dengan placeholders yang benar
        $placeholders = array();
        $values = array();
        
        foreach ($id_tingkat_map as $tingkat => $id_tingkat) {
            if (!empty($id_tingkat) && is_string($id_tingkat)) {
                $placeholders[] = '(tingkat = %s AND id_tingkat = %s)';
                $values[] = $tingkat;
                $values[] = $id_tingkat;
            }
        }
        
        if (!empty($placeholders)) {
            try {
                $where_clause = implode(' OR ', $placeholders);
                
                // Query dengan proper prepared statement
                $query = $wpdb->prepare(
                    "SELECT tingkat, id_tingkat, topik_rapat 
                     FROM $table_name 
                     WHERE ($where_clause) 
                     ORDER BY id DESC 
                     LIMIT 1000",
                    $values
                );
                
                $results = $wpdb->get_results($query, ARRAY_A);
                
                if ($wpdb->last_error) {
                    error_log('Database error in rekap_topik_page: ' . $wpdb->last_error);
                    $results = [];
                }
            } catch (Exception $e) {
                error_log('Exception in rekap_topik_page: ' . $e->getMessage());
                $results = [];
            }
        }
        
        // Optimasi: Process data dalam satu loop untuk efisiensi memory
        $text_parts = [];
        $counts = array_fill_keys($tingkat_keys, 0);
        
        foreach ($results as $row) {
            // Hitung jumlah per tingkat
            if (isset($row['tingkat']) && isset($counts[$row['tingkat']])) {
                $counts[$row['tingkat']]++;
            }
            
            // Kumpulkan text untuk wordcloud
            if (isset($row['topik_rapat']) && !empty(trim($row['topik_rapat']))) {
                $clean_text = sanitize_text_field($row['topik_rapat']);
                if (strlen($clean_text) > 3) { // Skip text yang terlalu pendek
                    $text_parts[] = $clean_text;
                }
            }
        }
        
        $jumlah_per_tingkat = array_values($counts);
        
        // Optimasi text processing dengan memory yang lebih efisien
        $text = implode(' ', $text_parts);
        $text = strtolower(strip_tags($text));
        
        // Validasi text tidak kosong sebelum processing
        if (empty(trim($text))) {
            $word_data = [];
        } else {
            // Optimasi: Gunakan regex untuk performance yang lebih baik
            preg_match_all('/\b\w{3,}\b/u', $text, $matches);
            $words = $matches[0];
            
            // Optimasi: Filter stopwords dengan array flip untuk O(1) lookup
            $stopwords = array_flip([
                'dan', 'atau', 'yang', 'ini', 'itu', 'ada', 'pada', 'untuk', 'dari', 'dengan',
                'akan', 'sudah', 'telah', 'bisa', 'dapat', 'harus', 'sangat', 'lebih', 'juga',
                'satu', 'dua', 'tiga', 'adalah', 'menjadi', 'memiliki', 'dalam', 'tentang',
                'sebagai', 'karena', 'oleh', 'kepada', 'tahun', 'bulan', 'hari', 'waktu'
            ]);
            
            $words = array_filter($words, function($word) use ($stopwords) {
                return !isset($stopwords[$word]) && strlen($word) > 2;
            });
            
            if (empty($words)) {
                $word_data = [];
            } else {
                $word_counts = array_count_values($words);
                
                if (!empty($word_counts)) {
                    // Optimasi: Sort dan limit dalam satu operasi
                    arsort($word_counts);
                    $word_counts = array_slice($word_counts, 0, 30, true); // Kurangi dari 50 ke 30
                    
                    $min_size = 16;
                    $max_size = 60;
                    $min_count = min($word_counts);
                    $max_count = max($word_counts);
                    
                    $word_data = [];
                    foreach ($word_counts as $word => $count) {
                        if ($max_count > $min_count) {
                            $size = $min_size + ($count - $min_count) * ($max_size - $min_size) / ($max_count - $min_count);
                        } else {
                            $size = ($min_size + $max_size) / 2;
                        }
                        $word_data[] = [
                            'text' => esc_html($word), 
                            'size' => round($size)
                        ];
                    }
                } else {
                    $word_data = [];
                }
            }
        }
        
        // Simpan hasil ke cache
        $cache_data = compact('word_data', 'jumlah_per_tingkat');
        set_transient($cache_key, $cache_data, $cache_duration);
    } // Penutup untuk blok else cache
    
    $tingkat_labels = ['Wilayah', 'Daerah', 'Cabang', 'Ranting'];
    ?>
    <div class="flex flex-row gap-4 pr-4">
        <div class="w-1/2">
            <h2 class="mt-4 text-xl font-semibold text-white relative z-10">Rekap Topik Rapat yang Sering Dibahas</h2>
            <div id="wordcloud" class="flex items-center justify-center text-center bg-white w-auto h-auto rounded-lg shadow-md p-4" style="height: 300px;">
                <div class="loading-spinner p-4 text-gray-500">Memuat wordcloud...</div>
            </div>
        </div>
        <div class="w-1/2">
            <h2 class="mt-4 text-xl font-semibold text-white relative z-10">Grafik Jumlah Notulen per Tingkat</h2>
            <div class="bg-white rounded-lg shadow-md p-4">
                <canvas id="grafikNotulen" width="400" height="300"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Store PHP data in JavaScript variables -->
    <script>
        window.wordcloudData = <?php echo wp_json_encode($word_data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        window.tingkatLabels = <?php echo wp_json_encode($tingkat_labels, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        window.jumlahData = <?php echo wp_json_encode($jumlah_per_tingkat, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    </script>
    
    <!-- Optimasi: Lazy load scripts -->
    <script>
        // Load D3 and wordcloud scripts only when needed
        function loadWordcloudScripts() {
            return new Promise((resolve, reject) => {
                if (window.d3 && window.d3.layout && window.d3.layout.cloud) {
                    resolve();
                    return;
                }
                
                const d3Script = document.createElement('script');
                d3Script.src = 'https://cdnjs.cloudflare.com/ajax/libs/d3/5.16.0/d3.min.js';
                d3Script.onload = () => {
                    const cloudScript = document.createElement('script');
                    cloudScript.src = 'https://cdn.jsdelivr.net/npm/d3-cloud/build/d3.layout.cloud.min.js';
                    cloudScript.onload = resolve;
                    cloudScript.onerror = reject;
                    document.head.appendChild(cloudScript);
                };
                d3Script.onerror = reject;
                document.head.appendChild(d3Script);
            });
        }
        
        loadWordcloudScripts().then(() => {
            renderWordcloud();
        }).catch(error => {
            console.error('Error loading wordcloud scripts:', error);
            document.getElementById('wordcloud').innerHTML = '<p class="p-4 text-red-500">Error memuat library wordcloud</p>';
        });
        
        function renderWordcloud() {
            try {
                var words = window.wordcloudData;
                var wordcloudContainer = document.getElementById('wordcloud');
                
                if (!words || words.length === 0) {
                    wordcloudContainer.innerHTML = '<p class="p-4 text-gray-500">Tidak ada data topik untuk ditampilkan</p>';
                    return;
                }
                
                // Clear loading spinner
                wordcloudContainer.innerHTML = '';
                
                // Optimasi: Responsive dimensions
                var containerWidth = wordcloudContainer.offsetWidth || 400;
                var width = Math.min(containerWidth - 20, 400);
                var height = Math.min(width * 0.75, 300);
                
                var svg = d3.select("#wordcloud").append("svg")
                    .attr("width", width)
                    .attr("height", height)
                    .append("g")
                    .attr("transform", "translate(" + width / 2 + "," + height / 2 + ")");
                
                // Optimasi: Reduce iterations dan improve performance
                var layout = d3.layout.cloud()
                    .size([width, height])
                    .words(words)
                    .padding(3) // Reduced from 5
                    .rotate(function() { return (Math.random() - 0.5) * 60; }) // Limited rotation
                    .fontSize(function(d) { return d.size; })
                    .spiral("archimedean") // More efficient spiral
                    .on("end", draw);
                
                layout.start();
                
                function draw(words) {
                    var text = svg.selectAll("text")
                        .data(words)
                        .enter().append("text")
                        .style("font-size", function(d) { return d.size + "px"; })
                        .style("fill", "#2d3476")
                        .style("font-family", "Arial, sans-serif")
                        .attr("text-anchor", "middle")
                        .attr("transform", function(d) {
                            return "translate(" + [d.x, d.y] + ")rotate(" + d.rotate + ")";
                        })
                        .text(function(d) { return d.text; });
                    
                    // Optimasi: Add hover effects efficiently
                    text.on("mouseover", function() {
                        d3.select(this).style("fill", "#1a237e");
                    }).on("mouseout", function() {
                        d3.select(this).style("fill", "#2d3476");
                    });
                }
            } catch (error) {
                console.error('Error rendering wordcloud:', error);
                document.getElementById('wordcloud').innerHTML = '<p class="p-4 text-red-500">Error memuat wordcloud</p>';
            }
        }
    </script>
    
    <!-- Optimasi: Lazy load Chart.js -->
    <script>
        let chartInstance = null; // Variabel untuk menyimpan instance chart

        function loadChartScript() {
            return new Promise((resolve, reject) => {
                if (window.Chart) {
                    resolve();
                    return;
                }
                
                const script = document.createElement('script');
                script.src = 'https://cdn.jsdelivr.net/npm/chart.js';
                script.onload = resolve;
                script.onerror = reject;
                document.head.appendChild(script);
            });
        }
        
        // Load and render chart when page loads
        loadChartScript().then(() => {
            renderChart();
        }).catch(error => {
            console.error('Error loading Chart.js:', error);
            var canvas = document.getElementById('grafikNotulen');
            if (canvas) {
                canvas.style.display = 'none';
            }
        });
        
        function renderChart() {
            try {
                var ctx = document.getElementById('grafikNotulen');
                if (!ctx) {
                    console.error('Chart canvas not found');
                    return;
                }
                
                // Hancurkan instance chart sebelumnya jika ada
                const existingChart = Chart.getChart("grafikNotulen");
                if (existingChart) {
                    existingChart.destroy();
                }

                var tingkatLabels = window.tingkatLabels;
                var jumlahData = window.jumlahData;
                
                // Optimasi: Check if Chart.js is loaded
                if (typeof Chart === 'undefined') {
                    console.error('Chart.js not loaded');
                    ctx.style.display = 'none';
                    return;
                }
                
                chartInstance = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: tingkatLabels,
                        datasets: [{
                            label: 'Jumlah Notulen',
                            data: jumlahData,
                            backgroundColor: [
                                '#2d3476', '#4e5ba6', '#6c7fd1', '#a3b0e0'
                            ],
                            borderWidth: 1,
                            borderColor: '#1a237e'
                        }]
                    },
                    options: {
                        indexAxis: 'y',
                        responsive: true,
                        maintainAspectRatio: false, // Optimasi: Better responsive behavior
                        interaction: {
                            intersect: false,
                            mode: 'index'
                        },
                        plugins: {
                            legend: { display: false },
                            title: { display: false }
                        },
                        scales: {
                            x: {
                                beginAtZero: true,
                                ticks: { 
                                    stepSize: 1, 
                                    font: { size: 16 }, // Reduced from 18
                                    maxRotation: 45 // Better label display
                                }
                            },
                            y: {
                                beginAtZero: true,
                                ticks: { 
                                    font: { size: 16 }, // Reduced from 18
                                    precision: 0 // Show only integers
                                }
                            }
                        },
                        animation: {
                            duration: 1000 // Faster animation
                        }
                    }
                });
            } catch (error) {
                console.error('Error rendering chart:', error);
                var canvas = document.getElementById('grafikNotulen');
                if (canvas) {
                    canvas.style.display = 'none';
                }
            }
        }
    </script>
    <?php
}

// Panggil optimasi database jika diperlukan (uncomment jika ingin mengaktifkan)
// optimize_rekap_topik_database();
