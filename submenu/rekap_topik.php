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
    $table_name = $wpdb->prefix . 'salammu_notulenmu';
    $setting_table = $wpdb->prefix . 'sicara_settings';
    // Validasi user ID
    if (!$user_id || $user_id <= 0) {
        wp_die('Akses tidak valid. Silakan login terlebih dahulu.');
    }

    $user_info = get_userdata($user_id);
    if ($user_info && strpos($user_info->user_login, 'pwm.') === 0) {
        $id_tingkat = $wpdb->get_var(
            $wpdb->prepare("SELECT pwm FROM $setting_table WHERE user_id = %d", $user_id)
        );
    } else if ($user_info && strpos($user_info->user_login, 'pdm.') === 0) {
        $id_tingkat = $wpdb->get_var(
            $wpdb->prepare("SELECT pdm FROM $setting_table WHERE user_id = %d", $user_id)
        );
    } else if ($user_info && strpos($user_info->user_login, 'pcm.') === 0) {
        $id_tingkat = $wpdb->get_var(
            $wpdb->prepare("SELECT pcm FROM $setting_table WHERE user_id = %d", $user_id)
        );
    } else if ($user_info && strpos($user_info->user_login, 'prm.') === 0) {
        $id_tingkat = $wpdb->get_var(
            $wpdb->prepare("SELECT prm FROM $setting_table WHERE user_id = %d", $user_id)
        );
    } else {
        wp_die('Akses tidak valid. Hanya untuk akun organisasi.');
    }

    $results = $wpdb->get_results($wpdb->prepare("SELECT notulen_rapat FROM $table_name WHERE id_tingkat = %d", $id_tingkat), ARRAY_A);

    if (empty($results)) {
        echo '<p>Tidak ada data untuk menghasilkan word cloud.</p>';
        return;
    }

    $text = '';
    foreach ($results as $row) {
        $text .= ' ' . $row['notulen_rapat'];
    }

    $words = str_word_count(strtolower($text), 1);
    $stopwords = array_flip([
        'tidak','dan','atau','yang','ini','itu','ada','pada','untuk','dari','dengan','akan','sudah',
        'telah','bisa','dapat','harus','sangat','lebih','juga','satu','dua','tiga','adalah','menjadi',
        'memiliki','dalam','tentang','sebagai','karena','oleh','kepada','tahun','bulan','hari','waktu',
        'cabang','ranting','muhammadiyah','lpcr','lpcrpm','pusat','nbsp','rapat','seperti',
        // HTML tags as stopwords
        'div','span','p','br','ul','li','ol','table','tr','td','th','thead','tbody','tfoot',
        'h1','h2','h3','h4','h5','h6','strong','em','b','i','u','a','img','hr','blockquote',
        'pre','code','sup','sub'
    ]);
    $words = array_filter($words, function($word) use ($stopwords) {
        return !isset($stopwords[$word]) && strlen($word) > 2;
    });
    $word_frequencies = array_count_values($words);

    arsort($word_frequencies);

    ?>
    <div class="flex flex-row gap-4 pr-4">
        <div class="w-full">
            <h2 class="mt-4 text-xl font-semibold text-white relative z-10">Rekap Topik Rapat yang Sering Dibahas</h2>
            <div id="wordcloud" class="flex items-start justify-start text-left bg-white 800 800 rounded-lg shadow-md p-4">
                <div class="loading-spinner p-4 text-gray-500">Memuat wordcloud...</div>
            </div>
        </div>
    </div>
    
    <!-- Store PHP data in JavaScript variables -->
    <script>
        window.wordcloudData = <?php echo wp_json_encode($word_frequencies, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
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

                if (!words || Object.keys(words).length === 0) {
                    wordcloudContainer.innerHTML = '<p class="p-4 text-gray-500">Tidak ada data topik untuk ditampilkan</p>';
                    return;
                }

                // Convert word frequencies to the required format for d3.layout.cloud
                var formattedWords = Object.keys(words).map(function(word) {
                    return { text: word, size: words[word] };
                });

                // Clear loading spinner
                wordcloudContainer.innerHTML = '';

                // Optimasi: Responsive dimensions
                var containerWidth = wordcloudContainer.offsetWidth || 400;
                var width = Math.min(containerWidth - 20, 800);
                var height = Math.min(width * 0.75, 800);

                var svg = d3.select("#wordcloud").append("svg")
                    .attr("width", width)
                    .attr("height", height)
                    .append("g")
                    .attr("transform", "translate(" + width / 2 + "," + height / 2 + ")");

                // Optimasi: Reduce iterations dan improve performance
                var layout = d3.layout.cloud()
                    .size([width, height])
                    .words(formattedWords)
                    .padding(3) // Reduced from 5
                    .rotate(function() { return (Math.random() - 0.5) * 60; }) // Limited rotation
                    .fontSize(function(d) { return d.size * 10; }) // Further increased scaling factor
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
    <?php
}

// Halaman Rekap Isu (Wordcloud Notulen & Kegiatan)
function rekap_isu_page() {
    global $wpdb;
    $user_id = get_current_user_id();
    if (!$user_id || $user_id <= 0) {
        wp_die('Akses tidak valid. Silakan login terlebih dahulu.');
    }

    $table_notulen = $wpdb->prefix . 'salammu_notulenmu';
    $table_kegiatan = $wpdb->prefix . 'salammu_kegiatanmu'; // Asumsi nama tabel, sesuaikan jika berbeda


    // Wordcloud Notulen (dari notulen_rapat)
    $notulen_results = $wpdb->get_results("SELECT notulen_rapat FROM $table_notulen WHERE notulen_rapat IS NOT NULL AND notulen_rapat != '' ORDER BY id DESC LIMIT 2000", ARRAY_A);
    $notulen_texts = array();
    foreach ($notulen_results as $row) {
        $clean = sanitize_text_field($row['notulen_rapat']);
        if (strlen($clean) > 3) $notulen_texts[] = $clean;
    }
    $notulen_text = strtolower(strip_tags(implode(' ', $notulen_texts)));
    if (empty(trim($notulen_text))) {
        $wordcloud_notulen = [];
    } else {
        preg_match_all('/\b\w{3,}\b/u', $notulen_text, $matches);
        $words = $matches[0];
        $stopwords = array_flip([
            'dan','atau','yang','ini','itu','ada','pada','untuk','dari','dengan','akan','sudah','telah','bisa','dapat','harus','sangat','lebih','juga','satu','dua','tiga','adalah','menjadi','memiliki','dalam','tentang','sebagai','karena','oleh','kepada','tahun','bulan','hari','waktu'
        ]);
        $words = array_filter($words, function($word) use ($stopwords) {
            return !isset($stopwords[$word]) && strlen($word) > 2;
        });

        // Debug: log $words after removing stopwords
        error_log('Filtered $words: ' . print_r($words, true));
        
        $word_counts = array_count_values($words);
        arsort($word_counts);
        $word_counts = array_slice($word_counts, 0, 30, true);
        $min_size = 16; $max_size = 60;
        $min_count = $word_counts ? min($word_counts) : 1;
        $max_count = $word_counts ? max($word_counts) : 1;
        $wordcloud_notulen = [];
        foreach ($word_counts as $word => $count) {
            $size = ($max_count > $min_count) ? $min_size + ($count - $min_count) * ($max_size - $min_size) / ($max_count - $min_count) : ($min_size + $max_size) / 2;
            $wordcloud_notulen[] = [ 'text' => esc_html($word), 'size' => round($size) ];
        }
    }

    // Wordcloud Kegiatan (dari detail_kegiatan)
    $kegiatan_results = $wpdb->get_results("SELECT detail_kegiatan FROM $table_kegiatan WHERE detail_kegiatan IS NOT NULL AND detail_kegiatan != '' ORDER BY id DESC LIMIT 2000", ARRAY_A);
    $kegiatan_texts = array();
    foreach ($kegiatan_results as $row) {
        $clean = sanitize_text_field($row['detail_kegiatan']);
        if (strlen($clean) > 3) $kegiatan_texts[] = $clean;
    }
    $kegiatan_text = strtolower(strip_tags(implode(' ', $kegiatan_texts)));
    if (empty(trim($kegiatan_text))) {
        $wordcloud_kegiatan = [];
    } else {
        preg_match_all('/\b\w{3,}\b/u', $kegiatan_text, $matches);
        $words = $matches[0];
        $words = array_filter($words, function($word) use ($stopwords) {
            return !isset($stopwords[$word]) && strlen($word) > 2;
        });
        $word_counts = array_count_values($words);
        arsort($word_counts);
        $word_counts = array_slice($word_counts, 0, 30, true);
        $min_size = 16; $max_size = 60;
        $min_count = $word_counts ? min($word_counts) : 1;
        $max_count = $word_counts ? max($word_counts) : 1;
        $wordcloud_kegiatan = [];
        foreach ($word_counts as $word => $count) {
            $size = ($max_count > $min_count) ? $min_size + ($count - $min_count) * ($max_size - $min_size) / ($max_count - $min_count) : ($min_size + $max_size) / 2;
            $wordcloud_kegiatan[] = [ 'text' => esc_html($word), 'size' => round($size) ];
        }
    }

    ?>
    <div class="flex flex-col md:flex-row gap-4 pr-4">
        <div class="w-full md:w-1/2">
            <h2 class="mt-4 text-xl font-semibold text-white relative z-10">Wordcloud Isu Notulen</h2>
            <div id="wordcloud-notulen" class="flex items-center justify-center text-center bg-white w-auto h-auto rounded-lg shadow-md p-4" style="height: 300px;">
                <div class="loading-spinner p-4 text-gray-500">Memuat wordcloud...</div>
            </div>
        </div>
        <div class="w-full md:w-1/2">
            <h2 class="mt-4 text-xl font-semibold text-white relative z-10">Wordcloud Isu Kegiatan</h2>
            <div id="wordcloud-kegiatan" class="flex items-center justify-center text-center bg-white w-auto h-auto rounded-lg shadow-md p-4" style="height: 300px;">
                <div class="loading-spinner p-4 text-gray-500">Memuat wordcloud...</div>
            </div>
        </div>
    </div>

    <script>
        window.wordcloudNotulen = <?php echo wp_json_encode($wordcloud_notulen, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        window.wordcloudKegiatan = <?php echo wp_json_encode($wordcloud_kegiatan, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    </script>
    <script>
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
            renderWordcloud('wordcloud-notulen', window.wordcloudNotulen);
            renderWordcloud('wordcloud-kegiatan', window.wordcloudKegiatan);
        }).catch(error => {
            document.getElementById('wordcloud-notulen').innerHTML = '<p class="p-4 text-red-500">Error memuat library wordcloud</p>';
            document.getElementById('wordcloud-kegiatan').innerHTML = '<p class="p-4 text-red-500">Error memuat library wordcloud</p>';
        });
        function renderWordcloud(containerId, words) {
            try {
                var wordcloudContainer = document.getElementById(containerId);
                if (!words || words.length === 0) {
                    wordcloudContainer.innerHTML = '<p class="p-4 text-gray-500">Tidak ada data isu untuk ditampilkan</p>';
                    return;
                }
                wordcloudContainer.innerHTML = '';
                var containerWidth = wordcloudContainer.offsetWidth || 400;
                var width = Math.min(containerWidth - 20, 400);
                var height = Math.min(width * 0.75, 300);
                var svg = d3.select('#' + containerId).append('svg')
                    .attr('width', width)
                    .attr('height', height)
                    .append('g')
                    .attr('transform', 'translate(' + width / 2 + ',' + height / 2 + ')');
                var layout = d3.layout.cloud()
                    .size([width, height])
                    .words(words)
                    .padding(3)
                    .rotate(function() { return (Math.random() - 0.5) * 60; })
                    .fontSize(function(d) { return d.size * 3; }) // Further increased scaling factor
                    .spiral('archimedean')
                    .on('end', draw);
                layout.start();
                function draw(words) {
                    var text = svg.selectAll('text')
                        .data(words)
                        .enter().append('text')
                        .style('font-size', function(d) { return d.size + 'px'; })
                        .style('fill', '#2d3476')
                        .style('font-family', 'Arial, sans-serif')
                        .attr('text-anchor', 'middle')
                        .attr('transform', function(d) {
                            return 'translate(' + [d.x, d.y] + ')rotate(' + d.rotate + ')';
                        })
                        .text(function(d) { return d.text; });
                    text.on('mouseover', function() {
                        d3.select(this).style('fill', '#1a237e');
                    }).on('mouseout', function() {
                        d3.select(this).style('fill', '#2d3476');
                    });
                }
            } catch (error) {
                wordcloudContainer.innerHTML = '<p class="p-4 text-red-500">Error memuat wordcloud</p>';
            }
        }
    </script>
    <?php
}
