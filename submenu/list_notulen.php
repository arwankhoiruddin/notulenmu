<?php
function notulenmu_list_page(){
    global $wpdb;
    $user_id = get_current_user_id();

    $setting_table = $wpdb->prefix . 'salammu_notulenmu_setting';
    $settings = $wpdb->get_row($wpdb->prepare(
        "SELECT pwm, pdm, pcm, prm FROM $setting_table WHERE user_id = %d",
        $user_id
    ), ARRAY_A);

    if (!$settings) {
        echo "<p>Data tidak ditemukan.</p>";
        return;
    }

    $id_tingkat_list = array_filter([$settings['pwm'], $settings['pdm'], $settings['pcm'], $settings['prm']]);

    if (empty($id_tingkat_list)) {
        echo "<p>You do not have sufficient permissions to access this page.</p>";
        return;
    }

    $filter = isset($_GET['filter']) ? sanitize_text_field($_GET['filter']) : '';

    $table_name = $wpdb->prefix . 'salammu_notulenmu';
    $placeholders = implode(',', array_fill(0, count($id_tingkat_list), '%d'));

    $query = "SELECT * FROM $table_name WHERE user_id = %d AND id_tingkat IN ($placeholders)";

    if (!empty($filter)) {
        $query .= " AND tingkat = %s";
        $sql = $wpdb->prepare($query, array_merge([$user_id], $id_tingkat_list, [$filter]));
    } else {
        $sql = $wpdb->prepare($query, array_merge([$user_id], $id_tingkat_list));
    }

    $rows = $wpdb->get_results($sql);

    echo "<h1>List Notulen</h1>";
    // Create a dropdown for the filter
    echo "<select id='filter' onchange='if (this.value !== null) window.location.href=\"?page=notulenmu-list&filter=\"+this.value'>";
    echo "<option value=''>All</option>";
    echo "<option value='ranting'" . ($filter === 'ranting' ? ' selected' : '') . ">Ranting</option>";
    echo "<option value='cabang'" . ($filter === 'cabang' ? ' selected' : '') . ">Cabang</option>";
    echo "<option value='daerah'" . ($filter === 'daerah' ? ' selected' : '') . ">Daerah</option>";
    echo "<option value='wilayah'" . ($filter === 'wilayah' ? ' selected' : '') . ">Wilayah</option>";
    echo "</select>";

    echo "<table class='widefat'>";
    echo "<thead>";
    echo "<tr>";
    echo "<th><strong>Tingkat</strong></th>";
    echo "<th><strong>Topik Rapat</strong></th>";
    echo "<th><strong>Tanggal Rapat</strong></th>";
    echo "<th><strong>Tempat Rapat</strong></th>";
    echo "<th><strong>Detail</strong></th>";
    echo "</tr>";
    echo "</thead>";
    echo "<tbody>";
    foreach ($rows as $row) {
        echo "<tr>";
        echo "<td>{$row->tingkat}</td>";
        echo "<td>{$row->topik_rapat}</td>";
        echo "<td>" . date('Y-m-d', strtotime($row->tanggal_rapat)) . "</td>";
        echo "<td>{$row->tempat_rapat}</td>";
        echo "<td><a href='" . admin_url('admin.php?page=notulenmu-add&edit=true&id=' . $row->id) . "'>View Details</a></td>";
        echo "</tr>";
    }
    echo "</tbody>";
    echo "</table>";
}
?>