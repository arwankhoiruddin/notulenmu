<?php
function notulenmu_enqueue_tailwind()
{
    // Only load on NotulenMu plugin pages to avoid conflicts
    $screen = get_current_screen();
    if ($screen && (strpos($screen->id, 'notulenmu') !== false || strpos($screen->id, 'kegiatanmu') !== false)) {
        // Enqueue the built CSS file instead of CDN to have better control
        wp_enqueue_style('notulenmu-tailwind', plugin_dir_url(__FILE__) . '../assets/css/dist/styles.css', [], '2.1');
        
        // Add custom CSS to fix WordPress admin conflicts
        $custom_css = '
        /* Ensure WordPress admin body remains scrollable */
        #wpwrap, #wpcontent, #wpbody-content {
            position: static !important;
            overflow: visible !important;
            height: auto !important;
        }
        
        /* Scope Tailwind styles to plugin containers only */
        .notulenmu-container * {
            box-sizing: border-box;
        }
        ';
        wp_add_inline_style('notulenmu-tailwind', $custom_css);
    }
}
add_action('admin_enqueue_scripts', 'notulenmu_enqueue_tailwind');
?>