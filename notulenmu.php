<?php

/**
 * Plugin Name: NotulenMu
 * Description: NotulenMu Lembaga Pengembangan Cabang Ranting dan Pembinaan Masjid 
 * Version:     2.1
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
include plugin_dir_path(__FILE__) . 'submenu/kegiatanmu-view.php';
include plugin_dir_path(__FILE__) . 'submenu/notulenmu-view.php';
include plugin_dir_path(__FILE__) . 'includes/styles.php';
include plugin_dir_path(__FILE__) . 'submenu/rekap_topik.php';
include plugin_dir_path(__FILE__) . 'submenu/rekap_nasional.php';
include plugin_dir_path(__FILE__) . 'submenu/pengurus_list.php';


if (!function_exists('add_cors_headers')) {
    function add_cors_headers()
    {
        header("Access-Control-Allow-Origin: *");
    }
}

add_action('send_headers', 'add_cors_headers');
add_filter('admin_footer_text', '__return_empty_string');
add_filter('update_footer', '__return_empty_string', 9999);

function notulenmu_menu()
{
    global $pagenow;

    // If we're on the login page, return early
    if ($pagenow === 'wp-login.php') {
        return;
    }

    $is_pp = false;
    $is_pwm = false;
    $is_pdm = false;
    $is_pcm = false;
    $is_prm = false;

    $user = wp_get_current_user();
    if (strpos($user->user_login, 'arwan') === 0) {
        $is_pp = true;
    } else if (strpos($user->user_login, 'pwm.') === 0) {
        $is_pwm = true;
    } else if (strpos($user->user_login, 'pdm.') === 0) {
        $is_pdm = true;
    } else if (strpos($user->user_login, 'pcm.') === 0) {
        $is_pcm = true;
    } else if (strpos($user->user_login, 'prm.') === 0) {
        $is_prm = true;
    } else {
        // menghilangkan menu NotulenMu dari pengguna yang tidak berwenang
        return;
    }

    // Add NotulenMu menu for contributors and administrators
    if (current_user_can('read') || current_user_can('manage_options')) {
        add_menu_page('NotulenMu', 'NotulenMu', 'read', 'notulenmu', 'notulenmu_page', 'dashicons-admin-page');

        // Add submenu pages
        // add_submenu_page('notulenmu', 'Setting Notulen', 'Setting Notulen', 'read', 'notulenmu-settings', 'notulenmu_settings_page');
        // add_submenu_page('__hidden', 'Data Pengurus', 'Tambah Pengurus', 'read', 'pengurus-add', 'pengurus_add_page');
        // add_submenu_page('notulenmu', 'Pengurus', 'Pengurus', 'read', 'pengurus-list', 'pengurus_list_page');
        add_submenu_page('__hidden', 'Tambah Notulen', 'Tambah Notulen', 'read', 'notulenmu-add', 'notulenmu_add_page');
        add_submenu_page('__hidden', 'Input Notulen', 'Input Notulen', 'read', 'notulenmu-add-step2', 'notulenmu_input_form_page');
        add_submenu_page('notulenmu', 'Notulen', 'Notulen', 'read', 'notulenmu-list', 'notulenmu_list_page');
        add_submenu_page('__hidden', 'Tambah Kegiatan', 'Tambah Kegiatan', 'read', 'kegiatanmu-add', 'tambah_kegiatan_page');
        add_submenu_page('notulenmu', 'Kegiatan', 'Kegiatan', 'read', 'kegiatanmu-list', 'kegiatanmu_list_page');
        add_submenu_page('notulenmu', 'View Notulen', '', 'read', 'notulenmu-view', 'notulenmu_view_page');
        add_submenu_page('notulenmu', 'Rekap Wilayah Kerja', 'Rekap Wilayah Kerja', 'manage_options', 'rekap-topik', 'rekap_topik_page');
        add_submenu_page('notulenmu', 'Rekap Isu', 'Rekap Isu', 'manage_options', 'rekap-isu', 'rekap_isu_page');
        add_submenu_page('notulenmu', 'Rekap Nasional', 'Rekap Nasional', 'manage_options', 'rekap-nasional', 'notulenmu_rekap_nasional_page');
        add_submenu_page('notulenmu', 'View Kegiatan', '', 'read', 'kegiatanmu-view', 'kegiatanmu_view_page');
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

/**
 * Get all accessible id_tingkat values based on organizational hierarchy
 * 
 * @param array $settings User's organizational settings (pwm, pdm, pcm, prm)
 * @param string $user_level User's organizational level (pwm, pdm, pcm, prm)
 * @return array List of all accessible id_tingkat values
 */
function notulenmu_get_accessible_id_tingkat($settings, $user_level) {
    global $wpdb;
    $id_tingkat_list = [];
    
    if ($user_level === 'pwm') {
        // PWM can see: their PWM + all PDM under PWM + all PCM under those PDM + all PRM under those PCM
        $pwm_id = intval($settings['pwm']);
        if ($pwm_id > 0) {
            $id_tingkat_list[] = $pwm_id;
            
            // Get all PDM under this PWM
            $pdm_table = $wpdb->prefix . 'sicara_pdm';
            $pdm_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT id_pdm FROM $pdm_table WHERE id_pwm = %d",
                $pwm_id
            ));
            
            if (!empty($pdm_ids)) {
                $id_tingkat_list = array_merge($id_tingkat_list, $pdm_ids);
                
                // Get all PCM under these PDMs
                $pcm_table = $wpdb->prefix . 'sicara_pcm';
                $pdm_placeholders = implode(',', array_fill(0, count($pdm_ids), '%d'));
                $pcm_ids = $wpdb->get_col($wpdb->prepare(
                    "SELECT id_pcm FROM $pcm_table WHERE id_pdm IN ($pdm_placeholders)",
                    $pdm_ids
                ));
                
                if (!empty($pcm_ids)) {
                    $id_tingkat_list = array_merge($id_tingkat_list, $pcm_ids);
                    
                    // Get all PRM under these PCMs
                    $prm_table = $wpdb->prefix . 'sicara_prm';
                    $pcm_placeholders = implode(',', array_fill(0, count($pcm_ids), '%d'));
                    $prm_ids = $wpdb->get_col($wpdb->prepare(
                        "SELECT id_prm FROM $prm_table WHERE id_pcm IN ($pcm_placeholders)",
                        $pcm_ids
                    ));
                    
                    if (!empty($prm_ids)) {
                        $id_tingkat_list = array_merge($id_tingkat_list, $prm_ids);
                    }
                }
            }
        }
    } else if ($user_level === 'pdm') {
        // PDM can see: their PDM + all PCM under PDM + all PRM under those PCM
        $pdm_id = intval($settings['pdm']);
        if ($pdm_id > 0) {
            $id_tingkat_list[] = $pdm_id;
            
            // Get all PCM under this PDM
            $pcm_table = $wpdb->prefix . 'sicara_pcm';
            $pcm_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT id_pcm FROM $pcm_table WHERE id_pdm = %d",
                $pdm_id
            ));
            
            if (!empty($pcm_ids)) {
                $id_tingkat_list = array_merge($id_tingkat_list, $pcm_ids);
                
                // Get all PRM under these PCMs
                $prm_table = $wpdb->prefix . 'sicara_prm';
                $pcm_placeholders = implode(',', array_fill(0, count($pcm_ids), '%d'));
                $prm_ids = $wpdb->get_col($wpdb->prepare(
                    "SELECT id_prm FROM $prm_table WHERE id_pcm IN ($pcm_placeholders)",
                    $pcm_ids
                ));
                
                if (!empty($prm_ids)) {
                    $id_tingkat_list = array_merge($id_tingkat_list, $prm_ids);
                }
            }
        }
    } else if ($user_level === 'pcm') {
        // PCM can see: their PCM + all PRM under PCM
        $pcm_id = intval($settings['pcm']);
        if ($pcm_id > 0) {
            $id_tingkat_list[] = $pcm_id;
            
            // Get all PRM under this PCM
            $prm_table = $wpdb->prefix . 'sicara_prm';
            $prm_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT id_prm FROM $prm_table WHERE id_pcm = %d",
                $pcm_id
            ));
            
            if (!empty($prm_ids)) {
                $id_tingkat_list = array_merge($id_tingkat_list, $prm_ids);
            }
        }
    } else if ($user_level === 'prm') {
        // PRM can only see their own PRM
        $prm_id = intval($settings['prm']);
        if ($prm_id > 0) {
            $id_tingkat_list[] = $prm_id;
        }
    }
    
    return array_filter(array_unique($id_tingkat_list));
}

/**
 * Get entity name from Sicara tables based on tingkat and id_tingkat
 * 
 * @param string $tingkat The organizational level (wilayah, daerah, cabang, ranting)
 * @param int $id_tingkat The ID of the entity
 * @return string Entity name with full context (e.g., "Pimpinan Wilayah Muhammadiyah D.I. YOGYAKARTA")
 */
function notulenmu_get_entity_name($tingkat, $id_tingkat) {
    global $wpdb;
    
    $tingkat = strtolower(trim($tingkat));
    $id_tingkat = intval($id_tingkat);
    
    if ($id_tingkat <= 0) {
        return '';
    }
    
    if ($tingkat === 'wilayah') {
        $table = $wpdb->prefix . 'sicara_pwm';
        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT wilayah FROM $table WHERE id_pwm = %d",
            $id_tingkat
        ));
        return $result ? "Pimpinan Wilayah Muhammadiyah " . $result : '';
    } else if ($tingkat === 'daerah') {
        $table = $wpdb->prefix . 'sicara_pdm';
        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT daerah FROM $table WHERE id_pdm = %d",
            $id_tingkat
        ));
        return $result ? "Pimpinan Daerah Muhammadiyah " . $result : '';
    } else if ($tingkat === 'cabang') {
        $table = $wpdb->prefix . 'sicara_pcm';
        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT cabang FROM $table WHERE id_pcm = %d",
            $id_tingkat
        ));
        return $result ? "Pimpinan Cabang Muhammadiyah " . $result : '';
    } else if ($tingkat === 'ranting') {
        $table = $wpdb->prefix . 'sicara_prm';
        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT ranting FROM $table WHERE id_prm = %d",
            $id_tingkat
        ));
        return $result ? "Pimpinan Ranting Muhammadiyah " . $result : '';
    }
    
    return '';
}

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
            sifat_rapat text NOT NULL,
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
            tingkat varchar(20) NOT NULL,
            id_tingkat int NOT NULL,
            nama_lengkap_gelar VARCHAR(40) NOT NULL,
            jabatan VARCHAR(30) NOT NULL,
            no_hp VARCHAR(20) NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    // Helper function to add or modify columns if type is different
    function add_missing_columns($table, $expected_columns) {
        global $wpdb;
        $columns_info = $wpdb->get_results("DESC $table");
        $existing_columns = array();
        $existing_types = array();
        foreach ($columns_info as $colinfo) {
            $existing_columns[] = $colinfo->Field;
            $existing_types[$colinfo->Field] = strtolower($colinfo->Type);
        }
        foreach ($expected_columns as $col => $type) {
            $type_clean = strtolower(preg_replace('/\s+NOT NULL|\s+DEFAULT.+/', '', $type));
            if (!in_array($col, $existing_columns)) {
                $wpdb->query("ALTER TABLE $table ADD $col $type");
            } else {
                // Check if type is different
                if (strpos($type_clean, 'varchar') !== false) {
                    $expected_type = preg_replace('/\s+NOT NULL|\s+DEFAULT.+/', '', $type_clean);
                } else {
                    $expected_type = $type_clean;
                }
                $db_type = $existing_types[$col];
                // Normalize varchar type for comparison
                if (strpos($db_type, 'varchar') !== false && strpos($expected_type, 'varchar') !== false) {
                    $db_type = preg_replace('/varchar\((\d+)\)/', 'varchar($1)', $db_type);
                    $expected_type = preg_replace('/varchar\((\d+)\)/', 'varchar($1)', $expected_type);
                }
                if ($db_type !== $expected_type) {
                    // Modify column type
                    $wpdb->query("ALTER TABLE $table MODIFY $col $type");
                }
            }
        }
    }

    // Check and add missing columns for each table
    add_missing_columns($notulen_table_name, array(
        'user_id' => 'int NOT NULL',
        'id_tingkat' => 'int NOT NULL',
        'tingkat' => 'text NOT NULL',
        'topik_rapat' => 'text NOT NULL',
        'tanggal_rapat' => "date DEFAULT '0000-00-00' NOT NULL",
        'jam_mulai' => 'time NOT NULL',
        'jam_selesai' => 'time NOT NULL',
        'sifat_rapat' => 'text NOT NULL',
        'tempat_rapat' => 'text NOT NULL',
        'peserta_rapat' => 'text NOT NULL',
        'peserta_hadir' => 'text NOT NULL',
        'notulen_rapat' => 'longtext NOT NULL',
        'image_path' => 'text NOT NULL',
        'lampiran' => 'text NOT NULL',
    ));
    add_missing_columns($kegiatan_table_name, array(
        'user_id' => 'int NOT NULL',
        'id_tingkat' => 'int NOT NULL',
        'tingkat' => 'text NOT NULL',
        'nama_kegiatan' => 'text NOT NULL',
        'tanggal_kegiatan' => "date DEFAULT '0000-00-00' NOT NULL",
        'tempat_kegiatan' => 'text NOT NULL',
        'peserta_kegiatan' => 'text NOT NULL',
        'detail_kegiatan' => 'longtext NOT NULL',
        'image_path' => 'text NOT NULL',
    ));
    add_missing_columns($table_name_setting, array(
        'user_id' => 'mediumint(9) NOT NULL',
        'pwm' => 'int NOT NULL',
        'pdm' => 'int NOT NULL',
        'pcm' => 'int NOT NULL',
        'prm' => 'int NOT NULL',
    ));
    add_missing_columns($table_pengurus, array(
        'user_id' => 'mediumint(9) NOT NULL',
        'tingkat' => 'VARCHAR(20) NOT NULL',
        'id_tingkat' => 'int NOT NULL',
        'nama_lengkap_gelar' => 'VARCHAR(40) NOT NULL',
        'jabatan' => 'VARCHAR(30) NOT NULL',
        'no_hp' => 'VARCHAR(20) NOT NULL',
    ));
}

register_activation_hook(__FILE__, 'notulenmu_install');
add_filter('admin_footer_text', '__return_empty_string');
add_filter('update_footer', '__return_empty_string', 9999);
