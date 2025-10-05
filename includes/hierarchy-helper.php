<?php
// Prevent direct access
defined('ABSPATH') || exit;

/**
 * Fetch all descendant organization IDs recursively from database
 * 
 * @param array $parent_ids Array of parent organization IDs to fetch children for
 * @return array Array of all organization IDs including parents and all descendants
 */
function notulenmu_get_all_descendant_org_ids($parent_ids) {
    global $wpdb;
    
    if (empty($parent_ids)) {
        return [];
    }
    
    // Remove null/empty values and get unique IDs
    $parent_ids = array_filter(array_unique($parent_ids));
    if (empty($parent_ids)) {
        return [];
    }
    
    $all_ids = $parent_ids; // Start with parent IDs
    $ids_to_process = $parent_ids;
    $processed_ids = [];
    
    // Check if sicara_organisasi table exists
    $org_table = $wpdb->prefix . 'sicara_organisasi';
    $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $org_table)) === $org_table;
    
    if (!$table_exists) {
        // Fallback: if table doesn't exist, log error and return original IDs
        error_log('NotulenMu: sicara_organisasi table not found. Returning only parent IDs.');
        return $all_ids;
    }
    
    // Recursively fetch children from database
    while (!empty($ids_to_process)) {
        $current_id = array_shift($ids_to_process);
        
        // Skip if already processed
        if (in_array($current_id, $processed_ids)) {
            continue;
        }
        
        $processed_ids[] = $current_id;
        
        // Query children from database
        // Assuming the table has columns: id, parent_id (or induk_id)
        // Try both common column names
        $children = $wpdb->get_results($wpdb->prepare(
            "SELECT id FROM $org_table WHERE parent_id = %d OR induk_id = %d",
            $current_id,
            $current_id
        ));
        
        if (!empty($children)) {
            foreach ($children as $child) {
                if (isset($child->id) && !in_array($child->id, $all_ids)) {
                    $all_ids[] = $child->id;
                    $ids_to_process[] = $child->id;
                }
            }
        }
    }
    
    return array_unique($all_ids);
}

/**
 * Get accessible organization IDs based on user's hierarchical level
 * 
 * @param int $user_id WordPress user ID
 * @return array Array of accessible organization IDs
 */
function notulenmu_get_accessible_org_ids($user_id) {
    global $wpdb;
    
    $setting_table = $wpdb->prefix . 'sicara_settings';
    $settings = $wpdb->get_row($wpdb->prepare(
        "SELECT pwm, pdm, pcm, prm FROM $setting_table WHERE user_id = %d",
        $user_id
    ), ARRAY_A);
    
    if (!$settings) {
        return [];
    }
    
    $current_user = wp_get_current_user();
    $id_tingkat_list = [];
    
    // Determine which organization IDs to fetch based on user level
    if (strpos($current_user->user_login, 'pwm.') === 0) {
        // PWM can see all levels under their region
        $id_tingkat_list = array_filter([$settings['pwm'], $settings['pdm'], $settings['pcm'], $settings['prm']]);
    } else if (strpos($current_user->user_login, 'pdm.') === 0) {
        // PDM can see PDM, PCM, PRM under their area
        $id_tingkat_list = array_filter([$settings['pdm'], $settings['pcm'], $settings['prm']]);
    } else if (strpos($current_user->user_login, 'pcm.') === 0) {
        // PCM can see PCM and PRM under their branch
        $id_tingkat_list = array_filter([$settings['pcm'], $settings['prm']]);
    } else if (strpos($current_user->user_login, 'prm.') === 0) {
        // PRM can only see their own
        $id_tingkat_list = array_filter([$settings['prm']]);
    }
    
    if (empty($id_tingkat_list)) {
        return [];
    }
    
    // For PRM, no need to fetch descendants (they can only see their own)
    if (strpos($current_user->user_login, 'prm.') === 0) {
        return $id_tingkat_list;
    }
    
    // For PWM, PDM, PCM - fetch all descendants recursively
    return notulenmu_get_all_descendant_org_ids($id_tingkat_list);
}
