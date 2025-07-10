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

    global $wpdb;

    $table_name = $wpdb->prefix . 'salammu_notulenmu';
    $user_id = get_current_user_id();
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
    $tingkat_labels = ['Wilayah', 'Daerah', 'Cabang', 'Ranting'];
    $tingkat_keys = ['wilayah', 'daerah', 'cabang', 'ranting'];
    $jumlah_per_tingkat = [];
    foreach ($tingkat_keys as $tingkat) {
        $id_tingkat = $id_tingkat_map[$tingkat];
        if ($id_tingkat) {
            $jumlah = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name WHERE tingkat = %s AND id_tingkat = %s AND user_id = %d",
                $tingkat, $id_tingkat, $user_id
            ));
        } else {
            $jumlah = 0;
        }
        $jumlah_per_tingkat[] = (int)$jumlah;
    }

    $results = $wpdb->get_results("SELECT topik_rapat FROM $table_name", ARRAY_A);

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

    // Ambil data jumlah notulen per wilayah/daerah/cabang/ranting
    $tingkat_group = [
        'wilayah' => [],
        'daerah' => [],
        'cabang' => [],
        'ranting' => []
    ];
    foreach (['wilayah', 'daerah', 'cabang', 'ranting'] as $tingkat) {
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT id_tingkat, COUNT(*) as jumlah FROM $table_name WHERE tingkat = %s GROUP BY id_tingkat",
            $tingkat
        ));
        foreach ($results as $row) {
            $tingkat_group[$tingkat][$row->id_tingkat] = (int)$row->jumlah;
        }
    }
    // Ambil nama dari API eksternal
    function get_nama_organisasi($id) {
        $url = 'https://old.sicara.id/api/v0/organisation/' . $id;
        $response = wp_remote_get($url);
        if (is_wp_error($response)) return $id;
        $data = json_decode(wp_remote_retrieve_body($response), true);
        return $data['data']['nama'] ?? $id;
    }
    $wilayah_labels = $wilayah_data = [];
    foreach ($tingkat_group['wilayah'] as $id => $jumlah) {
        $wilayah_labels[] = get_nama_organisasi($id);
        $wilayah_data[] = $jumlah;
    }
    $daerah_labels = $daerah_data = [];
    foreach ($tingkat_group['daerah'] as $id => $jumlah) {
        $daerah_labels[] = get_nama_organisasi($id);
        $daerah_data[] = $jumlah;
    }
    $cabang_labels = $cabang_data = [];
    foreach ($tingkat_group['cabang'] as $id => $jumlah) {
        $cabang_labels[] = get_nama_organisasi($id);
        $cabang_data[] = $jumlah;
    }
    $ranting_labels = $ranting_data = [];
    foreach ($tingkat_group['ranting'] as $id => $jumlah) {
        $ranting_labels[] = get_nama_organisasi($id);
        $ranting_data[] = $jumlah;
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

    <div class="pr-4">
        <h2 class="mt-4 text-xl font-semibold text-white relative z-10">Topik Rapat yang Sering Dibahas</h2>
        <div id="wordcloud" class="flex items-center justify-center text-center bg-white w-auto h-auto rounded-lg shadow-md"></div>
    </div>

    <div class="pr-4">
        <h2 class="mt-4 text-xl font-semibold text-white relative z-10">Grafik Jumlah Notulen per Tingkat Dalam Wilayah Kerja Anda (sesuai setting NotulenMu)</h2>
        <canvas id="grafikNotulen" width="400" height="250"></canvas>
    </div>
    <div class="pr-4">
        <h2 class="mt-4 text-xl font-semibold text-white relative z-10">Grafik Jumlah Notulen per Wilayah (nasional)</h2>
        <canvas id="grafikWilayah" width="400" height="<?php echo max(250, count($wilayah_labels)*40); ?>"></canvas>
    </div>
    <div class="pr-4">
        <h2 class="mt-4 text-xl font-semibold text-white relative z-10">Grafik Jumlah Notulen per Daerah (nasional)</h2>
        <canvas id="grafikDaerah" width="400" height="<?php echo max(250, count($daerah_labels)*40); ?>"></canvas>
    </div>
    <div class="pr-4">
        <h2 class="mt-4 text-xl font-semibold text-white relative z-10">Grafik Jumlah Notulen per Cabang (nasional)</h2>
        <canvas id="grafikCabang" width="400" height="<?php echo max(250, count($cabang_labels)*40); ?>"></canvas>
    </div>
    <div class="pr-4">
        <h2 class="mt-4 text-xl font-semibold text-white relative z-10">Grafik Jumlah Notulen per Ranting (nasional)</h2>
        <canvas id="grafikRanting" width="400" height="<?php echo max(250, count($ranting_labels)*40); ?>"></canvas>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/d3/5.16.0/d3.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/d3-cloud/build/d3.layout.cloud.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

            // Grafik Notulen per Tingkat (Horizontal Bar)
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
                            ticks: { stepSize: 1 }
                        }
                    }
                }
            });

            // Wilayah
            new Chart(document.getElementById('grafikWilayah').getContext('2d'), {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($wilayah_labels); ?>,
                    datasets: [{
                        label: 'Jumlah Notulen',
                        data: <?php echo json_encode($wilayah_data); ?>,
                        backgroundColor: '#2d3476',
                    }]
                },
                options: { plugins: { legend: {display: false} }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
            });
            // Daerah
            new Chart(document.getElementById('grafikDaerah').getContext('2d'), {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($daerah_labels); ?>,
                    datasets: [{
                        label: 'Jumlah Notulen',
                        data: <?php echo json_encode($daerah_data); ?>,
                        backgroundColor: '#4e5ba6',
                    }]
                },
                options: { plugins: { legend: {display: false} }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
            });
            // Cabang
            new Chart(document.getElementById('grafikCabang').getContext('2d'), {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($cabang_labels); ?>,
                    datasets: [{
                        label: 'Jumlah Notulen',
                        data: <?php echo json_encode($cabang_data); ?>,
                        backgroundColor: '#6c7fd1',
                    }]
                },
                options: { plugins: { legend: {display: false} }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
            });
            // Ranting
            new Chart(document.getElementById('grafikRanting').getContext('2d'), {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($ranting_labels); ?>,
                    datasets: [{
                        label: 'Jumlah Notulen',
                        data: <?php echo json_encode($ranting_data); ?>,
                        backgroundColor: '#a3b0e0',
                    }]
                },
                options: { plugins: { legend: {display: false} }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
            });
        });
    </script>
<?php
}

?>