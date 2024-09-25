<?php
function blockbase_child_enqueue_styles() {
    // Enqueue parent theme styles
    wp_enqueue_style('blockbase-parent-style', get_template_directory_uri() . '/style.css');
    
    // Enqueue child theme styles
    wp_enqueue_style('blockbase-child-style', get_stylesheet_uri(), array('blockbase-parent-style'), wp_get_theme()->get('Version'));
}
add_action('wp_enqueue_scripts', 'blockbase_child_enqueue_styles');
?>
