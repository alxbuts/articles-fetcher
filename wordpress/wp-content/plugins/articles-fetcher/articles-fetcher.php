<?php
/**
 * Plugin Name: Articles API Fetcher
 * Description: Fetches articles from an external API and displays it in a user-friendly format.
 * Version: 1.0
 * Author: alxbuts
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class ArticlesApiFetcher {
    public function __construct() {
        add_action('init', [$this, 'create_custom_post_type']);
        add_action('admin_menu', [$this, 'create_options_page']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_styles']);
        add_shortcode('articles_api_articles', [$this, 'display_articles']);

        // Schedule the fetch function using WordPress CRON
        add_action('articles_api_fetch_cron', [$this, 'fetch_and_store_articles']);

        // Schedule a daily cron job
        if (!wp_next_scheduled('articles_api_fetch_cron')) {
            wp_schedule_event(time(), 'daily', 'articles_api_fetch_cron');
        }
    }

    // Register custom post type to store API data
    public function create_custom_post_type() {
        register_post_type('api_article', [
            'labels' => [
                'name' => __('API Articles'),
                'singular_name' => __('API Article')
            ],
            'public' => true,
            'has_archive' => true,
            'supports' => ['title', 'editor', 'custom-fields'],
        ]);
    }

    // Create settings page
    public function create_options_page() {
        add_options_page(
            'Articles API Fetcher Settings',
            'API Fetcher',
            'manage_options',
            'articles-fetcher',
            [$this, 'render_options_page']
        );

        // Register settings for the plugin
        add_action('admin_init', function() {
            register_setting('articles_api_fetcher_options', 'articles_api_url');
            add_settings_section('articles_api_fetcher_section', 'API Settings', null, 'articles-api-fetcher');
            add_settings_field('articles_api_url', 'API URL', function() {
                echo '<input type="text" name="articles_api_url" value="' . esc_attr(get_option('articles_api_url')) . '" />';
            }, 'articles-api-fetcher', 'articles_api_fetcher_section');
        });
    }

    // Options page HTML
    public function render_options_page() {
        ?>
        <div class="wrap">
            <h1>API Fetcher Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('articles_api_fetcher_options');
                do_settings_sections('articles-fetcher');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    // Enqueue styles if needed
    public function enqueue_styles() {
        wp_enqueue_style('articles-fetcher-style', plugins_url('style.css', __FILE__));
    }

    // Display fetched articles using a shortcode
    public function display_articles($atts) {
        $args = [
            'post_type' => 'api_article',
            'posts_per_page' => 10,
            'paged' => (get_query_var('paged')) ? get_query_var('paged') : 1,
        ];
        $query = new WP_Query($args);
        if ($query->have_posts()) {
            ob_start();
            while ($query->have_posts()) {
                $query->the_post();
                ?>
                <div class="api-article">
                    <h2><?php the_title(); ?></h2>
                    <div><?php the_content(); ?></div>
                </div>
                <?php
            }
            echo paginate_links([
                'total' => $query->max_num_pages,
            ]);
            return ob_get_clean();
        } else {
            return 'No articles found.';
        }
    }

    // Fetch data from API and store in custom post type
    public function fetch_and_store_articles() {
      $api_url = get_option('articles_api_url'); // Get API URL from settings
      $response = wp_remote_get($api_url);
      
      if (is_wp_error($response)) {
          return; // Handle error if needed
      }

      $articles = json_decode(wp_remote_retrieve_body($response));

      foreach ($articles as $article) {
          // Check if article already exists to avoid duplicates
          if (!post_exists($article->title)) {
              wp_insert_post([
                  'post_type' => 'api_article',
                  'post_title' => sanitize_text_field($article->title),
                  'post_content' => wp_kses_post($article->content),
                  'post_status' => 'publish',
              ]);
          }
      }
    }
}

// Initialize the plugin
new ArticlesApiFetcher();
