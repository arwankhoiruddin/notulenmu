<?php
/**
 * Plugin Name: NotulenMu
 * Description: NotulenMu Lembaga Pengembangan Cabang Ranting dan Pembinaan Masjid 
 * Version:     1.1
 * Author:      Arwan LPCR
 * Author URI:  http://mandatech.co.id/
 * License:     GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: notulenmu
 * Domain Path: /languages
 */

// NotulenMu
include plugin_dir_path(__FILE__) . 'submenu/list_notulen.php';
include plugin_dir_path(__FILE__) . 'submenu/tambah_notulen.php';
include plugin_dir_path(__FILE__) . 'submenu/setting_notulen.php';
include plugin_dir_path(__FILE__) . 'submenu/about_notulen.php';

// KegiatanMu
include plugin_dir_path(__FILE__) . 'submenu/list_kegiatan.php';
include plugin_dir_path(__FILE__) . 'submenu/tambah_kegiatan.php';

add_action('send_headers', 'add_cors_headers');

function add_cors_headers() {
    header("Access-Control-Allow-Origin: *"); 
}

function notulenmu_menu() {    
    global $pagenow;

    // If we're on the login page, return early
    if ($pagenow === 'wp-login.php') {
        return;
    }
    add_menu_page('Tentang', 'NotulenMu', 'edit_posts', 'notulenmu', 'notulenmu_page', 'dashicons-admin-page' );

    // Add submenu pages
    add_submenu_page('notulenmu', 'Tambah Notulen', 'Tambah Notulen', 'edit_posts', 'notulenmu-add', 'notulenmu_add_page');
    add_submenu_page('notulenmu', 'List Notulen', 'List Notulen', 'edit_posts', 'notulenmu-list', 'notulenmu_list_page');
    add_submenu_page('notulenmu', 'Setting Notulen', 'Setting Notulen', 'edit_posts', 'notulenmu-settings', 'notulenmu_settings_page');

    add_submenu_page('notulenmu', 'Tambah Kegiatan', 'Tambah Kegiatan', 'edit_posts', 'kegiatanmu-add', 'kegiatanmu_add_page');
    add_submenu_page('notulenmu', 'Daftar Kegiatan', 'Daftar Kegiatan', 'edit_posts', 'kegiatanmu-list', 'kegiatanmu_list_page');
}

function ignore_on_login() {
    global $pagenow;

    // Don't run on the login page
    if ($pagenow === 'wp-login.php') {
        return;
    }
}

add_action('admin_menu', 'notulenmu_menu');
add_action('wp_loaded', 'ignore_on_login');

function notulenmu_install() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'salammu_notulenmu';

    $wpdb->query("DROP TABLE IF EXISTS $table_name");

    // Check if the table already exists
    if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        // Table doesn't exist, so create it
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id int NOT NULL,
            id_tingkat int NOT NULL,
            tingkat text NOT NULL,
            topik_rapat text NOT NULL,
            tanggal_rapat date DEFAULT '0000-00-00' NOT NULL,
            tempat_rapat text NOT NULL,
            peserta_rapat text NOT NULL,
            notulen_rapat text NOT NULL,
            image_path text NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    $table_name_setting = $wpdb->prefix . 'salammu_notulenmu_setting';

    $wpdb->query("DROP TABLE IF EXISTS $table_name_setting");

    // Check if the setting table already exists
    if($wpdb->get_var("SHOW TABLES LIKE '$table_name_setting'") != $table_name_setting) {
        // Table doesn't exist, so create it
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name_setting (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id mediumint(9) NOT NULL,
            pwm int NOT NULL,
            pdm int NOT NULL,
            pcm int NOT NULL,
            prm int NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    $table_name_kegiatan = $wpdb->prefix . 'salammu_kegiatanmu';

    $wpdb->query("DROP TABLE IF EXISTS $table_name_kegiatan");

    // Check if the table already exists
    if($wpdb->get_var("SHOW TABLES LIKE '$table_name_kegiatan'") != $table_name_kegiatan) {
        // Table doesn't exist, so create it
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name_kegiatan (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id int NOT NULL,
            id_tingkat int NOT NULL,
            tingkat text NOT NULL,
            nama_kegiatan text NOT NULL,
            tanggal_kegiatan date DEFAULT '0000-00-00' NOT NULL,
            tempat_kegiatan text NOT NULL,
            peserta_kegiatan text NOT NULL,
            detail_kegiatan text NOT NULL,
            image_path text NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}

function deactivate_plugin_name() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'salammu_notulenmu';
    $wpdb->query("DROP TABLE IF EXISTS $table_name");

    $table_name_setting = $wpdb->prefix . 'salammu_notulenmu_setting';
    $wpdb->query("DROP TABLE IF EXISTS $table_name_setting");
}

register_deactivation_hook( __FILE__, 'deactivate_plugin_name' );

register_activation_hook(__FILE__, 'notulenmu_install');
?>