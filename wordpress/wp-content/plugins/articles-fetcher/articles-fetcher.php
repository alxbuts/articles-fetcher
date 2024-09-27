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

class ArticlesApiFetcher
{
    public function __construct()
    {
        add_action('init', [$this, 'create_custom_post_type']);
        add_action('admin_menu', [$this, 'create_options_page']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_shortcode('articles_api_articles', [$this, 'display_articles_shortcode']);

        add_action('wp_ajax_nopriv_articles_api_fetcher_ajax_pagination', [$this, 'handle_ajax_pagination']);
        add_action('wp_ajax_articles_api_fetcher_ajax_pagination', [$this, 'handle_ajax_pagination']);

        // Schedule the fetch function using WordPress CRON
        add_action('articles_api_fetch_cron', [$this, 'fetch_and_store_articles']);

        // Schedule a daily cron job
        if (!wp_next_scheduled('articles_api_fetch_cron')) {
            wp_schedule_event(time(), 'daily', 'articles_api_fetch_cron');
        }
    }

    // Register custom post type to store API data
    public function create_custom_post_type()
    {
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
    public function create_options_page()
    {
        add_options_page(
            'Articles API Fetcher Settings',
            'API Fetcher',
            'manage_options',
            'articles-api-fetcher',
            [$this, 'render_settings_page']
        );

        register_setting('articles_api_settings_group', 'articles_api_key');
        register_setting('articles_api_settings_group', 'articles_endpoint');
        register_setting('articles_api_settings_group', 'articles_search_query_everything');
        register_setting('articles_api_settings_group', 'articles_search_query_headlines');
        register_setting('articles_api_settings_group', 'articles_top_headlines_category');
    }

    public function render_settings_page() {
        // Get the current option values
        $api_key = get_option('articles_api_key');
        $selected_endpoint = get_option('articles_endpoint', 'everything'); // default to 'everything'
        $search_query_everything = get_option('articles_search_query_everything', '');
        $search_query_headlines = get_option('articles_search_query_headlines', '');
        $top_headlines_category = get_option('articles_headlines_category', 'general');
    
        ?>
        <div class="wrap">
            <h1>API Settings</h1>
            <form method="post" action="options.php">
                <?php settings_fields('articles_api_settings_group'); ?>
                <?php do_settings_sections('articles_api_settings_group'); ?>

                <h2>API Key</h2>
                <input type="text" name="articles_api_key" value="<? echo esc_attr($api_key); ?>" />
    
                <h2>Select Endpoint</h2>
                <label>
                    <input type="radio" name="articles_endpoint" value="everything" 
                    <?php checked($selected_endpoint, 'everything'); ?> 
                    id="endpoint_everything"> Everything
                </label>
                <label>
                    <input type="radio" name="articles_endpoint" value="top_headlines" 
                    <?php checked($selected_endpoint, 'top_headlines'); ?> 
                    id="endpoint_top_headlines"> Top Headlines
                </label>
    
                <div id="everything_settings" style="display: <?php echo ($selected_endpoint === 'everything') ? 'block' : 'none'; ?>;">
                    <h3>Search Query (for Everything)</h3>
                    <input type="text" name="articles_search_query_everything" value="<?php echo esc_attr($search_query_everything); ?>" />
                </div>
    
                <div id="top_headlines_settings" style="display: <?php echo ($selected_endpoint === 'top_headlines') ? 'block' : 'none'; ?>;">
                    <h3>Select Category (for Top Headlines)</h3>
                    <select name="articles_headlines_category">
                        <option value="general" <?php selected($top_headlines_category, 'general'); ?>>General</option>
                        <option value="entertainment" <?php selected($top_headlines_category, 'entertainment'); ?>>Entertainment</option>
                        <option value="health" <?php selected($top_headlines_category, 'health'); ?>>Health</option>
                        <option value="science" <?php selected($top_headlines_category, 'science'); ?>>Science</option>
                        <option value="sports" <?php selected($top_headlines_category, 'sports'); ?>>Sports</option>
                        <option value="technology" <?php selected($top_headlines_category, 'technology'); ?>>Technology</option>
                    </select>
                    <h3>Search Query (for Top Headlines)</h3>
                    <input type="text" name="articles_search_query_headlines" value="<?php echo esc_attr($search_query_headlines); ?>" />
                </div>
    
                <?php submit_button(); ?>
            </form>
        </div>
    
        <script type="text/javascript">
            // JavaScript to toggle the visibility of the search or category settings
            document.addEventListener('DOMContentLoaded', function () {
                var apiTypeEverything = document.getElementById('endpoint_everything');
                var apiTypeTopHeadlines = document.getElementById('endpoint_top_headlines');
                var everythingSettings = document.getElementById('everything_settings');
                var topHeadlinesSettings = document.getElementById('top_headlines_settings');
    
                // Toggle based on initial selection
                toggleSettings();
    
                // Add event listeners
                apiTypeEverything.addEventListener('change', toggleSettings);
                apiTypeTopHeadlines.addEventListener('change', toggleSettings);
    
                function toggleSettings() {
                    if (apiTypeEverything.checked) {
                        everythingSettings.style.display = 'block';
                        topHeadlinesSettings.style.display = 'none';
                    } else if (apiTypeTopHeadlines.checked) {
                        everythingSettings.style.display = 'none';
                        topHeadlinesSettings.style.display = 'block';
                    }
                }
            });
        </script>
        <?php
    }

    // Options page HTML
    public function render_options_page()
    {
?>
        <div class="wrap">
            <h1>API Fetcher Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('articles_api_fetcher_options');
                do_settings_sections('articles-api-fetcher');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public function enqueue_scripts()
    {
        wp_enqueue_style('articles-api-fetcher-style', plugins_url('style.css', __FILE__));
        wp_enqueue_script('articles-api-fetcher-ajax-pagination', plugin_dir_url(__FILE__) . 'articles-api-fetcher-ajax-pagination.js', ['jquery'], null, true);

        // Pass the admin-ajax.php URL to the JavaScript
        wp_localize_script('articles-api-fetcher-ajax-pagination', 'articlesApiFetcherAjax', [
            'ajaxurl' => admin_url('admin-ajax.php'),
        ]);
    }

    public function handle_ajax_pagination() {
        $paged = isset($_POST['page']) ? sanitize_text_field($_POST['page']) : 1;

        $this->get_articles($paged);

        wp_die();
    }

    public function display_articles() {
        $paged = (get_query_var('paged')) ? get_query_var('paged') : 1;

        $this->get_articles($paged);
    }

    private function get_articles($paged) {
        $args = [
            'post_type' => 'api_article',
            'posts_per_page' => get_option('articles_api_pagination_setting'),
            'paged' => $paged,
        ];

        $query = new WP_Query($args);

        // Open a container to replace with AJAX content
        if ( ! wp_doing_ajax() ) {
            echo '<div id="articles-api-fetcher-container">';
        }

        if ($query->have_posts()) :
            while ($query->have_posts()) : $query->the_post();
                echo '<div class="article">';
                the_title('<h2>', '</h2>');
                the_content();
                echo '</div>';
            endwhile;

            // Output pagination links
            echo paginate_links([
                'total'        => $query->max_num_pages,
                'current'      => $paged,
            ]);

        else :
            echo '<p>No articles found.</p>';
        endif;

        // Close the container
        if ( ! wp_doing_ajax() ) {
            echo '</div>';
        }

        wp_reset_postdata();
    }


    public function display_articles_shortcode() {
        ob_start();
        $this->display_articles();
        return ob_get_clean();
    }


    // Fetch data from API and store in custom post type
    public function fetch_and_store_articles()
    {
        $api_key = get_option('articles_api_key'); // Get API URL from settings
        $endpoint = get_option('articles_endpoint', 'everything');
        $search_query_everything = get_option('articles_search_query_everything', '');
        $search_query_headlines = get_option('articles_search_query_headlines', '');
        $top_headlines_category = get_option('articles_headlines_category', 'general');

        // Set up the API URL depending on the selected option
        if ($endpoint === 'everything') {
            $api_url = "https://newsapi.org/v2/everything?q=" . urlencode($search_query_everything) . "&apiKey=$api_key";
        } else if ($endpoint === 'top_headlines') {
            $api_url = "https://newsapi.org/v2/top-headlines?category=" . urlencode($top_headlines_category) . "&q=" . urlencode($search_query_headlines) . "&apiKey=$api_key";
        }

        $site_url = get_site_url();

        // Set up the headers for the request, including the User-Agent
        $args = [
            'headers' => [
                'User-Agent' => "ArticlesApiFetcher/1.0 ($site_url)",
            ],
        ];

        $response = wp_remote_get($api_url, $args);

        if (is_wp_error($response)) {
            return; // Handle error if needed
        }

        $response = json_decode(wp_remote_retrieve_body($response));

        error_log(print_r($response, true));

        // Load the file that contains post_exists() if it's not already available
        if (!function_exists('post_exists')) {
            require_once(ABSPATH . 'wp-admin/includes/post.php');
        }

        foreach ($response->articles as $article) {
            // Check if article already exists to avoid duplicates
            if (!post_exists($article->title)) {
                wp_insert_post([
                    'post_type' => 'api_article',
                    'post_title' => sanitize_text_field($article->title),
                    'post_author' => sanitize_text_field($article->author),
                    'post_excerpt' => sanitize_text_field($article->description),
                    'post_date' => sanitize_text_field($article->publishedAt),
                    'post_content' => wp_kses_post($article->content),
                    'post_status' => 'publish',
                ]);
            }
        }
    }
}

// Initialize the plugin
new ArticlesApiFetcher();
