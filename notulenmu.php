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

include plugin_dir_path(__FILE__) . 'submenu/list_notulen.php';
include plugin_dir_path(__FILE__) . 'submenu/tambah_pengurus.php';
include plugin_dir_path(__FILE__) . 'submenu/tambah_notulen.php';
include plugin_dir_path(__FILE__) . 'submenu/setting_notulen.php';
include plugin_dir_path(__FILE__) . 'submenu/about_notulen.php';
include plugin_dir_path(__FILE__) . 'submenu/tambah_kegiatan.php';
include plugin_dir_path(__FILE__) . 'submenu/list_kegiatan.php';
include plugin_dir_path(__FILE__) . 'includes/styles.php';


add_action('send_headers', 'add_cors_headers');
add_filter('admin_footer_text', '__return_empty_string');
add_filter('update_footer', '__return_empty_string', 9999);

function add_cors_headers()
{
    header("Access-Control-Allow-Origin: *");
}

function notulenmu_menu()
{
    global $pagenow;

    // If we're on the login page, return early
    if ($pagenow === 'wp-login.php') {
        return;
    }

    // Add NotulenMu menu for contributors and administrators
    if (current_user_can('read') || current_user_can('manage_options')) {
        add_menu_page('NotulenMu', 'NotulenMu', 'read', 'notulenmu', 'notulenmu_page', 'dashicons-admin-page');

        // Add submenu pages
        add_submenu_page('notulenmu', 'Setting Notulen', 'Setting Notulen', 'read', 'notulenmu-settings', 'notulenmu_settings_page');
        add_submenu_page('notulenmu', 'Data Pengurus', 'Data Pengurus', 'read', 'pengurus-add', 'pengurus_add_page');
        add_submenu_page('notulenmu', 'Tambah Notulen', 'Tambah Notulen', 'read', 'notulenmu-add', 'notulenmu_add_page');
        add_submenu_page('notulenmu', 'List Notulen', 'List Notulen', 'read', 'notulenmu-list', 'notulenmu_list_page');
        add_submenu_page('notulenmu', 'Tambah Kegiatan', 'Tambah Kegiatan', 'read', 'kegiatanmu-add', 'tambah_kegiatan_page');
        add_submenu_page('notulenmu', 'List Kegiatan', 'List Kegiatan', 'read', 'kegiatanmu-list', 'kegiatanmu_list_page');
    }
}

function ignore_on_login()
{
    global $pagenow;

    // Don't run on the login page
    if ($pagenow === 'wp-login.php') {
        return;
    }
}

add_action('admin_menu', 'notulenmu_menu');
add_action('wp_loaded', 'ignore_on_login');

function notulenmu_install()
{
    global $wpdb;

    $notulen_table_name = $wpdb->prefix . 'salammu_notulenmu';

    // Check if the table already exists
    if ($wpdb->get_var("SHOW TABLES LIKE '$notulen_table_name'") != $notulen_table_name) {
        // Table doesn't exist, so create it
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $notulen_table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id int NOT NULL,
            id_tingkat int NOT NULL,
            tingkat text NOT NULL,
            topik_rapat text NOT NULL,
            tanggal_rapat date DEFAULT '0000-00-00' NOT NULL,
            jam_mulai time NOT NULL,
            jam_selesai time NOT NULL,
            tempat_rapat text NOT NULL,
            peserta_rapat text NOT NULL,
            peserta_hadir text NOT NULL,
            notulen_rapat text NOT NULL,
            image_path text NOT NULL,
            lampiran text NOT NULL,

            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    $kegiatan_table_name = $wpdb->prefix . 'salammu_kegiatanmu';

    // Check if the table already exists
    if ($wpdb->get_var("SHOW TABLES LIKE '$kegiatan_table_name'") != $kegiatan_table_name) {
        // Table doesn't exist, so create it
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $kegiatan_table_name (
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

    $table_name_setting = $wpdb->prefix . 'salammu_notulenmu_setting';

    // Check if the setting table already exists
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name_setting'") != $table_name_setting) {
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

    $table_pengurus = $wpdb->prefix . 'salammu_data_pengurus';
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_pengurus'") != $table_pengurus) {
        // Table doesn't exist, so create it
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_pengurus (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id mediumint(9) NOT NULL,
            tingkat int NOT NULL,
            id_tingkat int NOT NULL,
            nama_lengkap_gelar VARCHAR(40) NOT NULL,
            jabatan VARCHAR(30) NOT NULL,
            no_hp VARCHAR(20) NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}

register_activation_hook(__FILE__, 'notulenmu_install');
add_filter('admin_footer_text', '__return_empty_string');
add_filter('update_footer', '__return_empty_string', 9999);
