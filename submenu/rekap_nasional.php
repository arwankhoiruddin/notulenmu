<?php
/*
Plugin submenu page: Rekap Notulen dan Kegiatan (nasional)
*/
function notulenmu_rekap_nasional_page() {
    global $wpdb;
    // --- Data Notulen per Wilayah/Daerah/Cabang/Ranting (nasional) ---
    $table_name = $wpdb->prefix . 'salammu_notulenmu';
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
    // --- Data Kegiatan per Wilayah/Daerah/Cabang/Ranting (nasional) ---
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
    // --- Helper for organization name ---
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
    // --- Prepare labels/data ---
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
    // --- Kegiatan ---
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
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
    <?php
}
