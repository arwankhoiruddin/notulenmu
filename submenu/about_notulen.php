<?php
// Ambil nama dari API eksternal
if (!function_exists('get_nama_organisasi')) {
    function get_nama_organisasi($id) {
        if (empty($id) || $id === '0') return 'Tidak diketahui';
        $url = 'https://old.sicara.id/api/v0/organisation/' . $id;
        $response = wp_remote_get($url, ['timeout' => 3]);
        if (is_wp_error($response)) return 'ID:'.$id;
        $data = json_decode(wp_remote_retrieve_body($response), true);
        return isset($data['data']['nama']) && $data['data']['nama'] ? $data['data']['nama'] : 'ID:'.$id;
    }
}

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

    // Ambil topik_rapat hanya dari tingkat dan id_tingkat sesuai setting
    $topik_query_parts = [];
    $params = [];
    foreach ($id_tingkat_map as $tingkat => $id_tingkat) {
        if ($id_tingkat) {
            $topik_query_parts[] = "(tingkat = %s AND id_tingkat = %s AND user_id = %d)";
            $params[] = $tingkat;
            $params[] = $id_tingkat;
            $params[] = $user_id;
        }
    }
    $where_clause = implode(' OR ', $topik_query_parts);
    if (!empty($where_clause)) {
        $query = "SELECT topik_rapat FROM $table_name WHERE $where_clause";
        $results = $wpdb->get_results($wpdb->prepare($query, ...$params), ARRAY_A);
    } else {
        $results = [];
    }

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

    // --- AMBIL DATA JUMLAH KEGIATAN PER TINGKAT ---
    $kegiatan_table = $wpdb->prefix . 'salammu_kegiatanmu';
    $kegiatan_group = [
        'wilayah' => [],
        'daerah' => [],
        'cabang' => [],
        'ranting' => []
    ];
    foreach (['wilayah', 'daerah', 'cabang', 'ranting'] as $tingkat) {
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT id_tingkat, COUNT(*) as jumlah FROM $kegiatan_table WHERE tingkat = %s GROUP BY id_tingkat",
            $tingkat
        ));
        foreach ($results as $row) {
            $kegiatan_group[$tingkat][$row->id_tingkat] = (int)$row->jumlah;
        }
    }
    // --- PASTIKAN VARIABEL LABEL/DATA TIDAK NULL ---
    $wilayah_labels = $wilayah_data = [];
    if (!empty($tingkat_group['wilayah'])) {
        foreach ($tingkat_group['wilayah'] as $id => $jumlah) {
            $wilayah_labels[] = get_nama_organisasi($id);
            $wilayah_data[] = $jumlah;
        }
    }
    $daerah_labels = $daerah_data = [];
    if (!empty($tingkat_group['daerah'])) {
        foreach ($tingkat_group['daerah'] as $id => $jumlah) {
            $daerah_labels[] = get_nama_organisasi($id);
            $daerah_data[] = $jumlah;
        }
    }
    $cabang_labels = $cabang_data = [];
    if (!empty($tingkat_group['cabang'])) {
        foreach ($tingkat_group['cabang'] as $id => $jumlah) {
            $cabang_labels[] = get_nama_organisasi($id);
            $cabang_data[] = $jumlah;
        }
    }
    $ranting_labels = $ranting_data = [];
    if (!empty($tingkat_group['ranting'])) {
        foreach ($tingkat_group['ranting'] as $id => $jumlah) {
            $ranting_labels[] = get_nama_organisasi($id);
            $ranting_data[] = $jumlah;
        }
    }
    // --- PASTIKAN VARIABEL LABEL/DATA KEGIATAN TIDAK NULL ---
    $kegiatan_wilayah_labels = $kegiatan_wilayah_data = [];
    if (!empty($kegiatan_group['wilayah'])) {
        foreach ($kegiatan_group['wilayah'] as $id => $jumlah) {
            $kegiatan_wilayah_labels[] = get_nama_organisasi($id);
            $kegiatan_wilayah_data[] = $jumlah;
        }
    }
    $kegiatan_daerah_labels = $kegiatan_daerah_data = [];
    if (!empty($kegiatan_group['daerah'])) {
        foreach ($kegiatan_group['daerah'] as $id => $jumlah) {
            $kegiatan_daerah_labels[] = get_nama_organisasi($id);
            $kegiatan_daerah_data[] = $jumlah;
        }
    }
    $kegiatan_cabang_labels = $kegiatan_cabang_data = [];
    if (!empty($kegiatan_group['cabang'])) {
        foreach ($kegiatan_group['cabang'] as $id => $jumlah) {
            $kegiatan_cabang_labels[] = get_nama_organisasi($id);
            $kegiatan_cabang_data[] = $jumlah;
        }
    }
    $kegiatan_ranting_labels = $kegiatan_ranting_data = [];
    if (!empty($kegiatan_group['ranting'])) {
        foreach ($kegiatan_group['ranting'] as $id => $jumlah) {
            $kegiatan_ranting_labels[] = get_nama_organisasi($id);
            $kegiatan_ranting_data[] = $jumlah;
        }
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
        <h2 class="mt-4 text-xl font-semibold text-white relative z-10">Topik Rapat yang Sering Dibahas di Wilayah Kerja Anda</h2>
        <div id="wordcloud" class="flex items-center justify-center text-center bg-white w-auto h-auto rounded-lg shadow-md"></div>
    </div>

    <div class="pr-4">
        <h2 class="mt-4 text-xl font-semibold text-white relative z-10">Grafik Jumlah Notulen per Tingkat Dalam Wilayah Kerja Anda (sesuai setting NotulenMu)</h2>
        <canvas id="grafikNotulen" width="400" height="250"></canvas>
    </div>
    <div class="pr-4">
        <h2 class="mt-4 text-xl font-semibold text-white relative z-10">Grafik Jumlah Notulen per Wilayah (nasional), Daerah, Cabang, dan Ranting</h2>
        <div style="display: flex; gap: 16px; flex-wrap: wrap;">
            <div style="flex:1; min-width:200px; text-align:center;">
                <span class="font-semibold">Wilayah</span>
                <canvas id="pieWilayah" width="200" height="200"></canvas>
            </div>
            <div style="flex:1; min-width:200px; text-align:center;">
                <span class="font-semibold">Daerah</span>
                <canvas id="pieDaerah" width="200" height="200"></canvas>
            </div>
            <div style="flex:1; min-width:200px; text-align:center;">
                <span class="font-semibold">Cabang</span>
                <canvas id="pieCabang" width="200" height="200"></canvas>
            </div>
            <div style="flex:1; min-width:200px; text-align:center;">
                <span class="font-semibold">Ranting</span>
                <canvas id="pieRanting" width="200" height="200"></canvas>
            </div>
        </div>
    </div>
    <div class="pr-4">
        <h2 class="mt-4 text-xl font-semibold text-white relative z-10">Grafik Jumlah Kegiatan per Wilayah (nasional), Daerah, Cabang, dan Ranting</h2>
        <div style="display: flex; gap: 16px; flex-wrap: wrap;">
            <div style="flex:1; min-width:200px; text-align:center;">
                <span class="font-semibold">Wilayah</span>
                <canvas id="pieKegiatanWilayah" width="200" height="200"></canvas>
            </div>
            <div style="flex:1; min-width:200px; text-align:center;">
                <span class="font-semibold">Daerah</span>
                <canvas id="pieKegiatanDaerah" width="200" height="200"></canvas>
            </div>
            <div style="flex:1; min-width:200px; text-align:center;">
                <span class="font-semibold">Cabang</span>
                <canvas id="pieKegiatanCabang" width="200" height="200"></canvas>
            </div>
            <div style="flex:1; min-width:200px; text-align:center;">
                <span class="font-semibold">Ranting</span>
                <canvas id="pieKegiatanRanting" width="200" height="200"></canvas>
            </div>
        </div>
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

            // Pie Chart Notulen per Wilayah/Daerah/Cabang/Ranting
            new Chart(document.getElementById('pieWilayah').getContext('2d'), {
                type: 'pie',
                data: {
                    labels: <?php echo json_encode($wilayah_labels); ?>,
                    datasets: [{
                        data: <?php echo json_encode($wilayah_data); ?>,
                        backgroundColor: Chart.helpers.color('#2d3476').alpha(0.7).rgbString(),
                    }]
                },
                options: { plugins: { legend: {position: 'bottom'} } }
            });
            new Chart(document.getElementById('pieDaerah').getContext('2d'), {
                type: 'pie',
                data: {
                    labels: <?php echo json_encode($daerah_labels); ?>,
                    datasets: [{
                        data: <?php echo json_encode($daerah_data); ?>,
                        backgroundColor: Chart.helpers.color('#4e5ba6').alpha(0.7).rgbString(),
                    }]
                },
                options: { plugins: { legend: {position: 'bottom'} } }
            });
            new Chart(document.getElementById('pieCabang').getContext('2d'), {
                type: 'pie',
                data: {
                    labels: <?php echo json_encode($cabang_labels); ?>,
                    datasets: [{
                        data: <?php echo json_encode($cabang_data); ?>,
                        backgroundColor: Chart.helpers.color('#6c7fd1').alpha(0.7).rgbString(),
                    }]
                },
                options: { plugins: { legend: {position: 'bottom'} } }
            });
            new Chart(document.getElementById('pieRanting').getContext('2d'), {
                type: 'pie',
                data: {
                    labels: <?php echo json_encode($ranting_labels); ?>,
                    datasets: [{
                        data: <?php echo json_encode($ranting_data); ?>,
                        backgroundColor: Chart.helpers.color('#a3b0e0').alpha(0.7).rgbString(),
                    }]
                },
                options: { plugins: { legend: {position: 'bottom'} } }
            });

            // Pie Chart Kegiatan per Wilayah/Daerah/Cabang/Ranting
            new Chart(document.getElementById('pieKegiatanWilayah').getContext('2d'), {
                type: 'pie',
                data: {
                    labels: <?php echo json_encode($kegiatan_wilayah_labels); ?>,
                    datasets: [{
                        data: <?php echo json_encode($kegiatan_wilayah_data); ?>,
                        backgroundColor: Chart.helpers.color('#f59e42').alpha(0.7).rgbString(),
                    }]
                },
                options: { plugins: { legend: {position: 'bottom'} } }
            });
            new Chart(document.getElementById('pieKegiatanDaerah').getContext('2d'), {
                type: 'pie',
                data: {
                    labels: <?php echo json_encode($kegiatan_daerah_labels); ?>,
                    datasets: [{
                        data: <?php echo json_encode($kegiatan_daerah_data); ?>,
                        backgroundColor: Chart.helpers.color('#f7c873').alpha(0.7).rgbString(),
                    }]
                },
                options: { plugins: { legend: {position: 'bottom'} } }
            });
            new Chart(document.getElementById('pieKegiatanCabang').getContext('2d'), {
                type: 'pie',
                data: {
                    labels: <?php echo json_encode($kegiatan_cabang_labels); ?>,
                    datasets: [{
                        data: <?php echo json_encode($kegiatan_cabang_data); ?>,
                        backgroundColor: Chart.helpers.color('#fbe6b2').alpha(0.7).rgbString(),
                    }]
                },
                options: { plugins: { legend: {position: 'bottom'} } }
            });
            new Chart(document.getElementById('pieKegiatanRanting').getContext('2d'), {
                type: 'pie',
                data: {
                    labels: <?php echo json_encode($kegiatan_ranting_labels); ?>,
                    datasets: [{
                        data: <?php echo json_encode($kegiatan_ranting_data); ?>,
                        backgroundColor: Chart.helpers.color('#fbe6b2').alpha(0.7).rgbString(),
                    }]
                },
                options: { plugins: { legend: {position: 'bottom'} } }
            });
        });
    </script>

    <!-- Debug output for chart data -->
    <!--
    Wilayah: <?php echo json_encode($wilayah_labels); ?> | <?php echo json_encode($wilayah_data); ?>
    Daerah: <?php echo json_encode($daerah_labels); ?> | <?php echo json_encode($daerah_data); ?>
    Cabang: <?php echo json_encode($cabang_labels); ?> | <?php echo json_encode($cabang_data); ?>
    Ranting: <?php echo json_encode($ranting_labels); ?> | <?php echo json_encode($ranting_data); ?>
    Kegiatan Wilayah: <?php echo json_encode($kegiatan_wilayah_labels); ?> | <?php echo json_encode($kegiatan_wilayah_data); ?>
    Kegiatan Daerah: <?php echo json_encode($kegiatan_daerah_labels); ?> | <?php echo json_encode($kegiatan_daerah_data); ?>
    Kegiatan Cabang: <?php echo json_encode($kegiatan_cabang_labels); ?> | <?php echo json_encode($kegiatan_cabang_data); ?>
    Kegiatan Ranting: <?php echo json_encode($kegiatan_ranting_labels); ?> | <?php echo json_encode($kegiatan_ranting_data); ?>
    Wordcloud: <?php echo json_encode($word_data); ?>
    -->
<?php
}

?>