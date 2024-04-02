<?php
function notulenmu_install() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'salammu_notulenmu';

    // Check if the table already exists
    if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        // Table doesn't exist, so create it
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id mediumint(9) NOT NULL,
            tingkat text NOT NULL,
            topik_rapat text NOT NULL,
            tanggal_rapat datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            tempat_rapat text NOT NULL,
            peserta_rapat text NOT NULL,
            notulen_rapat text NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    $table_name_setting = $wpdb->prefix . 'salammu_notulenmu_setting';

    // Check if the setting table already exists
    if($wpdb->get_var("SHOW TABLES LIKE '$table_name_setting'") != $table_name_setting) {
        // Table doesn't exist, so create it
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name_setting (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id mediumint(9) NOT NULL,
            pwm text NOT NULL,
            pdm text NOT NULL,
            pcm text NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}

register_activation_hook(__FILE__, 'notulenmu_install');
