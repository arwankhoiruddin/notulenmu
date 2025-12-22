<?php
function notulenmu_rekap_kegiatan_page() {
    // Check if user is PP (arwan, pp.*, or lpcrpm.ppm)
    $current_user = wp_get_current_user();
    $is_pp = (strpos($current_user->user_login, 'arwan') === 0 || 
              strpos($current_user->user_login, 'pp.') === 0 || 
              $current_user->user_login === 'lpcrpm.ppm');
    
    if (!$is_pp) {
        echo '<div class="notice notice-error"><p>Hanya user PP yang dapat mengakses halaman ini.</p></div>';
        return;
    }
    
    global $wpdb;
    
    // Verify nonce if form was submitted
    if (isset($_GET['tingkat']) || isset($_GET['id_tingkat'])) {
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'rekap_kegiatan_filter')) {
            wp_die('Security check failed');
        }
    }
    
    // Get filter parameters
    $selected_tingkat = isset($_GET['tingkat']) ? sanitize_text_field($_GET['tingkat']) : '';
    $selected_id_tingkat = isset($_GET['id_tingkat']) ? sanitize_text_field($_GET['id_tingkat']) : '';
    
    // Get organizational data from Sicara tables
    $pcm_table = $wpdb->prefix . 'sicara_pcm';
    $prm_table = $wpdb->prefix . 'sicara_prm';
    
    // Get all PCM (cabang)
    $pcm_list = $wpdb->get_results("SELECT id_pcm, cabang FROM $pcm_table ORDER BY cabang ASC");
    
    // Get all PRM (ranting)
    $prm_list = $wpdb->get_results("SELECT id_prm, ranting FROM $prm_table ORDER BY ranting ASC");
    
    // Prepare data for charts if filter is applied
    $chart_data = array();
    $chart_labels = array();
    $total_kegiatan = 0;
    $wordcloud_data = array();
    
    if ($selected_tingkat && !empty($selected_id_tingkat)) {
        $table_kegiatan = $wpdb->prefix . 'salammu_kegiatanmu';
        
        if ($selected_tingkat === 'cabang') {
            // Check if "Semua Cabang" is selected
            if ($selected_id_tingkat === 'semua') {
                // Get all PCM and their kegiatan counts
                foreach ($pcm_list as $pcm) {
                    $count = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM $table_kegiatan WHERE tingkat = 'cabang' AND id_tingkat = %d",
                        $pcm->id_pcm
                    ));
                    if ($count > 0) {
                        $chart_labels[] = 'PCM ' . $pcm->cabang;
                        $chart_data[] = intval($count);
                        $total_kegiatan += intval($count);
                    }
                }
                
                // Get all kegiatan names for wordcloud
                $kegiatan_names = $wpdb->get_col("SELECT nama_kegiatan FROM $table_kegiatan WHERE tingkat = 'cabang'");
                
            } else {
                // Specific PCM selected
                $selected_id_tingkat = intval($selected_id_tingkat);
                
                // Get all PRM under selected PCM
                $prm_under_pcm = $wpdb->get_results($wpdb->prepare(
                    "SELECT id_prm, ranting FROM $prm_table WHERE id_pcm = %d ORDER BY ranting ASC",
                    $selected_id_tingkat
                ));
                
                // Get kegiatan count for selected PCM
                $pcm_count = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM $table_kegiatan WHERE tingkat = 'cabang' AND id_tingkat = %d",
                    $selected_id_tingkat
                ));
                
                if ($pcm_count > 0) {
                    $pcm_name = $wpdb->get_var($wpdb->prepare(
                        "SELECT cabang FROM $pcm_table WHERE id_pcm = %d",
                        $selected_id_tingkat
                    ));
                    $chart_labels[] = 'PCM ' . $pcm_name;
                    $chart_data[] = intval($pcm_count);
                    $total_kegiatan += intval($pcm_count);
                }
                
                // Get kegiatan count for each PRM under the PCM
                foreach ($prm_under_pcm as $prm) {
                    $count = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM $table_kegiatan WHERE tingkat = 'ranting' AND id_tingkat = %d",
                        $prm->id_prm
                    ));
                    if ($count > 0) {
                        $chart_labels[] = 'PRM ' . $prm->ranting;
                        $chart_data[] = intval($count);
                        $total_kegiatan += intval($count);
                    }
                }
                
                // Get kegiatan names for wordcloud (PCM + all PRM under it)
                $prm_ids = array_map(function($prm) { return $prm->id_prm; }, $prm_under_pcm);
                
                // Build query for wordcloud
                if (!empty($prm_ids)) {
                    $prm_placeholders = implode(',', array_fill(0, count($prm_ids), '%d'));
                    $kegiatan_names = $wpdb->get_col($wpdb->prepare(
                        "SELECT nama_kegiatan FROM $table_kegiatan 
                        WHERE (tingkat = 'cabang' AND id_tingkat = %d) 
                        OR (tingkat = 'ranting' AND id_tingkat IN ($prm_placeholders))",
                        array_merge(array($selected_id_tingkat), $prm_ids)
                    ));
                } else {
                    $kegiatan_names = $wpdb->get_col($wpdb->prepare(
                        "SELECT nama_kegiatan FROM $table_kegiatan 
                        WHERE tingkat = 'cabang' AND id_tingkat = %d",
                        $selected_id_tingkat
                    ));
                }
            }
            
        } elseif ($selected_tingkat === 'ranting') {
            // Check if "Semua Ranting" is selected
            if ($selected_id_tingkat === 'semua') {
                // Get all PRM and their kegiatan counts
                foreach ($prm_list as $prm) {
                    $count = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM $table_kegiatan WHERE tingkat = 'ranting' AND id_tingkat = %d",
                        $prm->id_prm
                    ));
                    if ($count > 0) {
                        $chart_labels[] = 'PRM ' . $prm->ranting;
                        $chart_data[] = intval($count);
                        $total_kegiatan += intval($count);
                    }
                }
                
                // Get all kegiatan names for wordcloud
                $kegiatan_names = $wpdb->get_col("SELECT nama_kegiatan FROM $table_kegiatan WHERE tingkat = 'ranting'");
                
            } else {
                // Specific PRM selected
                $selected_id_tingkat = intval($selected_id_tingkat);
                
                // Get kegiatan count for selected PRM
                $prm_count = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM $table_kegiatan WHERE tingkat = 'ranting' AND id_tingkat = %d",
                    $selected_id_tingkat
                ));
                
                if ($prm_count > 0) {
                    $prm_name = $wpdb->get_var($wpdb->prepare(
                        "SELECT ranting FROM $prm_table WHERE id_prm = %d",
                        $selected_id_tingkat
                    ));
                    $chart_labels[] = 'PRM ' . $prm_name;
                    $chart_data[] = intval($prm_count);
                    $total_kegiatan = intval($prm_count);
                }
                
                // Get kegiatan names for wordcloud
                $kegiatan_names = $wpdb->get_col($wpdb->prepare(
                    "SELECT nama_kegiatan FROM $table_kegiatan WHERE tingkat = 'ranting' AND id_tingkat = %d",
                    $selected_id_tingkat
                ));
            }
        }
        
        // Process kegiatan names for wordcloud
        if (!empty($kegiatan_names)) {
            $all_text = strtolower(implode(' ', $kegiatan_names));
            $words = str_word_count($all_text, 1);
            
            // Remove common stopwords
            $stopwords = array_flip([
                'tidak','dan','atau','yang','ini','itu','ada','pada','untuk','dari','dengan','akan','sudah',
                'telah','bisa','dapat','harus','sangat','lebih','juga','satu','dua','tiga','adalah','menjadi',
                'memiliki','dalam','tentang','sebagai','karena','oleh','kepada','tahun','bulan','hari','waktu',
                'cabang','ranting','muhammadiyah','kegiatan','acara'
            ]);
            
            $words = array_filter($words, function($word) use ($stopwords) {
                return !isset($stopwords[$word]) && strlen($word) > 2;
            });
            
            $word_frequencies = array_count_values($words);
            arsort($word_frequencies);
            
            // Prepare wordcloud data
            $wordcloud_data = $word_frequencies;
        }
    }
    
    ?>
    <div class="pr-4">
        <h1 class="text-2xl font-semibold text-white mt-4 mb-4">Rekap Kegiatan</h1>
        
        <!-- Filter Form -->
        <div class="bg-white p-6 rounded-lg shadow-md mb-6">
            <form method="get" action="">
                <input type="hidden" name="page" value="rekap-kegiatan">
                <?php wp_nonce_field('rekap_kegiatan_filter', '_wpnonce', false); ?>
                
                <table class="w-full border-collapse">
                    <tr>
                        <td class="py-2 px-4 font-medium text-gray-700" style="width: 200px;">Pilih Tingkat:</td>
                        <td class="py-2 px-4">
                            <select name="tingkat" id="tingkat" class="w-full p-2 border border-gray-300 rounded-md" onchange="updateIdTingkatOptions()">
                                <option value="">-- Pilih Tingkat --</option>
                                <option value="cabang" <?php echo $selected_tingkat === 'cabang' ? 'selected' : ''; ?>>Cabang (PCM)</option>
                                <option value="ranting" <?php echo $selected_tingkat === 'ranting' ? 'selected' : ''; ?>>Ranting (PRM)</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <td class="py-2 px-4 font-medium text-gray-700">Pilih Cabang/Ranting:</td>
                        <td class="py-2 px-4">
                            <select name="id_tingkat" id="id_tingkat" class="w-full p-2 border border-gray-300 rounded-md">
                                <option value="">-- Pilih Cabang/Ranting --</option>
                                <!-- Options will be populated by JavaScript -->
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <td class="py-2 px-4"></td>
                        <td class="py-2 px-4">
                            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-6 rounded">
                                Tampilkan
                            </button>
                        </td>
                    </tr>
                </table>
            </form>
        </div>
        
        <?php if ($selected_tingkat && !empty($selected_id_tingkat)) { ?>
            <!-- Total Kegiatan -->
            <div class="bg-white p-6 rounded-lg shadow-md mb-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-2">Total Kegiatan</h2>
                <p class="text-4xl font-bold text-blue-600"><?php echo $total_kegiatan; ?></p>
            </div>
            
            <?php if ($total_kegiatan > 0) { ?>
                <!-- Bar Chart -->
                <div class="bg-white p-6 rounded-lg shadow-md mb-6">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4">Grafik Jumlah Kegiatan per <?php echo $selected_tingkat === 'cabang' ? 'Cabang dan Ranting' : 'Ranting'; ?></h2>
                    <canvas id="kegiatanChart" width="400" height="200"></canvas>
                </div>
                
                <!-- Word Cloud -->
                <div class="bg-white p-6 rounded-lg shadow-md mb-6">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4">Word Cloud Nama Kegiatan</h2>
                    <div id="wordcloud" class="flex items-center justify-center" style="min-height: 400px;">
                        <div class="loading-spinner p-4 text-gray-500">Memuat word cloud...</div>
                    </div>
                </div>
            <?php } else { ?>
                <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded">
                    <p>Tidak ada data kegiatan untuk filter yang dipilih.</p>
                </div>
            <?php } ?>
        <?php } ?>
    </div>
    
    <!-- Store PHP data in JavaScript -->
    <script>
        var pcmData = <?php echo json_encode($pcm_list); ?>;
        var prmData = <?php echo json_encode($prm_list); ?>;
        var selectedTingkat = '<?php echo esc_js($selected_tingkat); ?>';
        var selectedIdTingkat = '<?php echo esc_js($selected_id_tingkat); ?>';
        
        function updateIdTingkatOptions() {
            var tingkat = document.getElementById('tingkat').value;
            var idTingkatSelect = document.getElementById('id_tingkat');
            
            // Clear existing options
            idTingkatSelect.innerHTML = '<option value="">-- Pilih Cabang/Ranting --</option>';
            
            if (tingkat === 'cabang') {
                // Add "Semua Cabang" option
                var semuaOption = document.createElement('option');
                semuaOption.value = 'semua';
                semuaOption.textContent = 'Semua Cabang';
                idTingkatSelect.appendChild(semuaOption);
                
                // Add individual PCM options
                pcmData.forEach(function(pcm) {
                    var option = document.createElement('option');
                    option.value = pcm.id_pcm;
                    option.textContent = pcm.cabang;
                    idTingkatSelect.appendChild(option);
                });
            } else if (tingkat === 'ranting') {
                // Add "Semua Ranting" option
                var semuaOption = document.createElement('option');
                semuaOption.value = 'semua';
                semuaOption.textContent = 'Semua Ranting';
                idTingkatSelect.appendChild(semuaOption);
                
                // Add individual PRM options
                prmData.forEach(function(prm) {
                    var option = document.createElement('option');
                    option.value = prm.id_prm;
                    option.textContent = prm.ranting;
                    idTingkatSelect.appendChild(option);
                });
            }
        }
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            updateIdTingkatOptions();
            
            // Restore selected value if exists
            if (selectedIdTingkat) {
                document.getElementById('id_tingkat').value = selectedIdTingkat;
            }
        });
    </script>
    
    <?php if ($selected_tingkat && $selected_id_tingkat > 0 && $total_kegiatan > 0) { ?>
        <!-- Chart.js for Bar Chart -->
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Bar Chart
                var ctx = document.getElementById('kegiatanChart').getContext('2d');
                var chart = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: <?php echo json_encode($chart_labels); ?>,
                        datasets: [{
                            label: 'Jumlah Kegiatan',
                            data: <?php echo json_encode($chart_data); ?>,
                            backgroundColor: '#3b82f6',
                            borderColor: '#2563eb',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    stepSize: 1
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                display: false
                            }
                        }
                    }
                });
            });
        </script>
        
        <!-- D3.js and d3-cloud for Word Cloud -->
        <script>
            window.wordcloudData = <?php echo json_encode($wordcloud_data); ?>;
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
                        wordcloudContainer.innerHTML = '<p class="p-4 text-gray-500">Tidak ada data nama kegiatan untuk ditampilkan</p>';
                        return;
                    }
                    
                    // Convert word frequencies to the required format
                    var formattedWords = Object.keys(words).map(function(word) {
                        return { text: word, size: words[word] };
                    });
                    
                    // Clear loading spinner
                    wordcloudContainer.innerHTML = '';
                    
                    // Responsive dimensions
                    var containerWidth = wordcloudContainer.offsetWidth || 600;
                    var width = Math.min(containerWidth - 20, 800);
                    var height = Math.min(width * 0.6, 500);
                    
                    var svg = d3.select("#wordcloud").append("svg")
                        .attr("width", width)
                        .attr("height", height)
                        .append("g")
                        .attr("transform", "translate(" + width / 2 + "," + height / 2 + ")");
                    
                    var layout = d3.layout.cloud()
                        .size([width, height])
                        .words(formattedWords)
                        .padding(5)
                        .rotate(function() { return (Math.random() - 0.5) * 60; })
                        .fontSize(function(d) { return Math.max(12, Math.min(60, d.size * 10)); })
                        .spiral("archimedean")
                        .on("end", draw);
                    
                    layout.start();
                    
                    function draw(words) {
                        var text = svg.selectAll("text")
                            .data(words)
                            .enter().append("text")
                            .style("font-size", function(d) { return d.size + "px"; })
                            .style("fill", "#3b82f6")
                            .style("font-family", "Arial, sans-serif")
                            .attr("text-anchor", "middle")
                            .attr("transform", function(d) {
                                return "translate(" + [d.x, d.y] + ")rotate(" + d.rotate + ")";
                            })
                            .text(function(d) { return d.text; });
                        
                        // Add hover effects
                        text.on("mouseover", function() {
                            d3.select(this).style("fill", "#1e40af");
                        }).on("mouseout", function() {
                            d3.select(this).style("fill", "#3b82f6");
                        });
                    }
                } catch (error) {
                    console.error('Error rendering wordcloud:', error);
                    document.getElementById('wordcloud').innerHTML = '<p class="p-4 text-red-500">Error memuat wordcloud</p>';
                }
            }
        </script>
    <?php } ?>
    <?php
}
