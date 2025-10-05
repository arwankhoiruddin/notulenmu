<?php
// Prevent direct access
defined('ABSPATH') || exit;

/**
 * Fetch all descendant organization IDs recursively from the API
 * 
 * @param array $parent_ids Array of parent organization IDs to fetch children for
 * @return array Array of all organization IDs including parents and all descendants
 */
function notulenmu_get_all_descendant_org_ids($parent_ids) {
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
    
    $url_base = 'https://old.sicara.id/api/v0/organisation/';
    $args = array(
        'headers' => array(
            'origin' => get_site_url(),
            'x-requested-with' => 'XMLHttpRequest'
        ),
        'timeout' => 10
    );
    
    // Recursively fetch children
    while (!empty($ids_to_process)) {
        $current_id = array_shift($ids_to_process);
        
        // Skip if already processed
        if (in_array($current_id, $processed_ids)) {
            continue;
        }
        
        $processed_ids[] = $current_id;
        
        // Fetch children for this organization
        $response = wp_remote_get($url_base . $current_id . '/children', $args);
        
        if (is_wp_error($response)) {
            // If API call fails, log error and continue
            error_log('NotulenMu: Failed to fetch children for org ID ' . $current_id . ': ' . $response->get_error_message());
            continue;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        // Check if we have valid data
        if (isset($data['data']) && is_array($data['data'])) {
            foreach ($data['data'] as $child) {
                if (isset($child['id']) && !in_array($child['id'], $all_ids)) {
                    $all_ids[] = $child['id'];
                    $ids_to_process[] = $child['id'];
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
