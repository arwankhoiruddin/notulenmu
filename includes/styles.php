<?php
function notulenmu_enqueue_tailwind()
{
    // Enqueue Tailwind CSS (versi terbaru dari CDN resmi)
    wp_enqueue_script('tailwind-js', 'https://unpkg.com/@tailwindcss/browser@4', [], null, true);
}
add_action('admin_enqueue_scripts', 'notulenmu_enqueue_tailwind');
?>