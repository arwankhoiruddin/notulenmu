<?php
/**
 * Plugin Name: NotulenMu
 * Description: NotulenMu LPCR.
 * Version:     1.0
 * Author:      Arwan LPCR
 * Author URI:  http://mandatech.co.id/
 * License:     GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: notulenmu
 * Domain Path: /languages
 */

include plugin_dir_path(__FILE__) . 'submenu/list_notulen.php';
include plugin_dir_path(__FILE__) . 'submenu/tambah_notulen.php';
include plugin_dir_path(__FILE__) . 'submenu/setting_notulen.php';
include plugin_dir_path(__FILE__) . 'submenu/about_notulen.php';

function notulenmu_menu() {
    add_menu_page('Tentang', 'NotulenMu', 'manage_options', 'notulenmu', 'notulenmu_page', 'dashicons-admin-page' );

    // Add submenu pages
    add_submenu_page('notulenmu', 'Setting Notulen', 'Setting Notulen', 'manage_options', 'notulenmu-settings', 'notulenmu_settings_page');
    add_submenu_page('notulenmu', 'Tambah Notulen', 'Tambah Notulen', 'manage_options', 'notulenmu-add', 'notulenmu_add_page');
    add_submenu_page('notulenmu', 'List Notulen', 'List Notulen', 'manage_options', 'notulenmu-list', 'notulenmu_list_page');
}

add_action('admin_menu', 'notulenmu_menu');

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
?>