<?php
// Halaman Rekap Topik (Wordcloud)
function rekap_topik_page() {
    global $wpdb;
    $user_id = get_current_user_id();
    $table_name = $wpdb->prefix . 'salammu_notulenmu';
    $setting_table = $wpdb->prefix . 'salammu_notulenmu_setting';
    $settings = $wpdb->get_row($wpdb->prepare(
        "SELECT pwm, pdm, pcm, prm FROM $setting_table WHERE user_id = %d",
        $user_id
    ), ARRAY_A);
    $id_tingkat_map = [
        'wilayah' => $settings['pwm'] ?? '',
        'daerah' => $settings['pdm'] ?? '',
        'cabang' => $settings['pcm'] ?? '',
        'ranting' => $settings['prm'] ?? ''
    ];
    // Optimasi: Query topik dan jumlah notulen sekaligus
    $tingkat_id_pairs = [];
    foreach ($id_tingkat_map as $tingkat => $id_tingkat) {
        if ($id_tingkat) {
            $tingkat_id_pairs[] = $wpdb->prepare('(tingkat = %s AND id_tingkat = %s)', $tingkat, $id_tingkat);
        }
    }
    $where_clause = implode(' OR ', $tingkat_id_pairs);
    $results = [];
    $jumlah_per_tingkat = [0,0,0,0];
    if (!empty($where_clause)) {
        // Query semua data sekaligus
        $query = "SELECT tingkat, id_tingkat, topik_rapat FROM $table_name WHERE $where_clause";
        $results = $wpdb->get_results($query, ARRAY_A);
        // Hitung jumlah per tingkat
        $tingkat_keys = ['wilayah', 'daerah', 'cabang', 'ranting'];
        $counts = array_fill_keys($tingkat_keys, 0);
        foreach ($results as $row) {
            if (isset($counts[$row['tingkat']])) {
                $counts[$row['tingkat']]++;
            }
        }
        $jumlah_per_tingkat = array_values($counts);
    }
    // Gabungkan semua topik_rapat
    $text = "";
    foreach ($results as $row) {
        $text .= " " . $row['topik_rapat'];
    }
    $text = strtolower(strip_tags($text));
    $words = str_word_count($text, 1);
    $word_counts = array_count_values($words);
    $word_data = [];
    if (!empty($word_counts)) {
        $min_size = 16;
        $max_size = 60;
        $min_count = min($word_counts);
        $max_count = max($word_counts);
        foreach ($word_counts as $word => $count) {
            if ($max_count == $min_count) {
                $size = ($min_size + $max_size) / 2;
            } else {
                $size = $min_size + ($count - $min_count) * ($max_size - $min_size) / ($max_count - $min_count);
            }
            $word_data[] = ['text' => $word, 'size' => round($size)];
        }
    }
    $tingkat_labels = ['Wilayah', 'Daerah', 'Cabang', 'Ranting'];
    ?>
    <div class="pr-4">
        <h2 class="mt-4 text-xl font-semibold text-white relative z-10">Rekap Topik Rapat yang Sering Dibahas di Wilayah Kerja Anda</h2>
        <div id="wordcloud" class="flex items-center justify-center text-center bg-white w-auto h-auto rounded-lg shadow-md"></div>
    </div>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/d3/5.16.0/d3.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/d3-cloud/build/d3.layout.cloud.min.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            var words = <?php echo json_encode($word_data); ?>;
            var width = 400,
                height = 300;
            var svg = d3.select("#wordcloud").append("svg")
                .attr("width", width)
                .attr("height", height)
                .append("g")
                .attr("transform", "translate(" + width / 2 + "," + height / 2 + ")");
            var layout = d3.layout.cloud()
                .size([width, height])
                .words(words)
                .padding(5)
                .rotate(() => (~~(Math.random() * 2) * 90))
                .fontSize(d => d.size)
                .on("end", draw);
            layout.start();
            function draw(words) {
                svg.selectAll("text")
                    .data(words)
                    .enter().append("text")
                    .style("font-size", d => d.size + "px")
                    .style("fill", "#2d3476")
                    .attr("text-anchor", "middle")
                    .attr("transform", d => "translate(" + [d.x, d.y] + ")rotate(" + d.rotate + ")")
                    .text(d => d.text);
            }
        });
    </script>
    <!-- Grafik Jumlah Notulen per Tingkat Dalam Wilayah Kerja Anda (sesuai setting NotulenMu) dipindahkan dari about_notulen.php -->
    <div class="pr-4 mt-8">
        <h2 class="mt-4 text-xl font-semibold text-white relative z-10">Grafik Jumlah Notulen per Tingkat Dalam Wilayah Kerja Anda (sesuai setting NotulenMu)</h2>
        <canvas id="grafikNotulen" width="250" height="120"></canvas>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            var ctx = document.getElementById('grafikNotulen').getContext('2d');
            var chart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($tingkat_labels); ?>,
                    datasets: [{
                        label: 'Jumlah Notulen',
                        data: <?php echo json_encode($jumlah_per_tingkat); ?>,
                        backgroundColor: [
                            '#2d3476', '#4e5ba6', '#6c7fd1', '#a3b0e0'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { display: false },
                        title: {
                            display: false
                        }
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            ticks: { stepSize: 1, font: { size: 18 } }
                        },
                        y: {
                            beginAtZero: true,
                            ticks: { font: { size: 18 } }
                        }
                    }
                }
            });
        });
    </script>
    <?php
}
