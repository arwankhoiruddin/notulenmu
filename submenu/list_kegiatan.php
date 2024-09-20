<?php
function get_user_role($user_id) {
    // Get user data
    $user = get_userdata($user_id);
    
    // Check if user data exists and has roles
    if (!empty($user) && is_array($user->roles)) {
        // Return the first role (primary role) of the user
        return $user->roles[0];
    }
    
    // Return null if no role is found
    return null;
}

function kegiatanmu_list_page(){
    $user_id = get_current_user_id();
    if (get_user_role(get_current_user_id()) != 'contributor') {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }
    global $wpdb;
    $table_name = $wpdb->prefix . 'salammu_kegiatanmu';

    $filter = isset($_GET['filter']) ? $_GET['filter'] : '';

    $sql = "SELECT * FROM $table_name where user_id = $user_id";
    if ($filter !== '') {
        $sql .= " AND tingkat = '$filter'";
    }
    $sql .= " order by tingkat";
    $rows = $wpdb->get_results($sql);

    echo "<h1>List Kegiatan</h1>";
    // Create a dropdown for the filter
    echo "<select id='filter' onchange='if (this.value !== null) window.location.href=\"?page=kegiatanmu-list&filter=\"+this.value'>";
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
    echo "<th><strong>Nama Kegiatan</strong></th>";
    echo "<th><strong>Tanggal Kegiatan</strong></th>";
    echo "<th><strong>Tempat Kegiatan</strong></th>";
    echo "<th><strong>Detail</strong></th>";
    echo "</tr>";
    echo "</thead>";
    echo "<tbody>";
    foreach ($rows as $row) {
        echo "<tr>";
        echo "<td>{$row->tingkat}</td>";
        echo "<td>{$row->nama_kegiatan}</td>";
        echo "<td>" . date('Y-m-d', strtotime($row->tanggal_kegiatan)) . "</td>";
        echo "<td>{$row->tempat_kegiatan}</td>";
        echo "<td><a href='" . admin_url('admin.php?page=kegiatanmu-add&edit=true&id=' . $row->id) . "'>View Details</a></td>";
        echo "</tr>";
    }
    echo "</tbody>";
    echo "</table>";
}
?>