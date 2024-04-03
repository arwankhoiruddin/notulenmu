<?php
function notulenmu_list_page(){
    global $wpdb;
    $user_id = get_current_user_id();
    $table_name = $wpdb->prefix . 'salammu_notulenmu';
    $rows = $wpdb->get_results("SELECT * FROM $table_name where user_id = $user_id order by tingkat");

    echo "<h1>List Notulen</h1>";
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