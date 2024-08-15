<?php

/**
 * Plugin Name: Disable Comments
 * Description: Disables all WordPress comment functionality on the entire network.
 * Version: 0.0.2
 * GitHub Plugin URI: https://github.com/Open-WP-Club/wp-disabling-comments
 * Author: Open WP Club
 * Author URI: https://github.com/Open-WP-Club
 * License: GPL2
 */

namespace OpenWPClub\DisableComments;

defined('ABSPATH') || exit;

/**
 * Main class for disabling comments functionality.
 */
class Disable_WP_Comments
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        // Initialize the plugin
        add_action('plugins_loaded', [$this, 'init']);
    }

    /**
     * Initialize the plugin.
     */
    public function init()
    {
        // Load options
        $this->options = get_option('owpc_disable_comments_options', [
            'disable_comments' => true,
            'disable_comments_frontend' => true,
            'disable_comments_dashboard' => true,
            'disable_comments_rest_api' => true,
        ]);

        // These need to happen now
        add_action('widgets_init', [$this, 'disable_rc_widget']);
        add_filter('wp_headers', [$this, 'filter_wp_headers']);
        add_action('template_redirect', [$this, 'filter_query'], 9); // before redirect_canonical

        // Admin bar filtering has to happen here since WP 3.6
        add_action('add_admin_bar_menus', [$this, 'filter_admin_bar'], 0);
        add_action('admin_init', [$this, 'filter_admin_bar']);

        // These can happen later
        add_action('wp_loaded', [$this, 'setup_filters']);

        // Add settings page
        add_action('admin_menu', [$this, 'add_options_page']);
        add_action('admin_init', [$this, 'register_settings']);

        if ($this->options['disable_comments_frontend']) {
            add_action('enqueue_block_editor_assets', [$this, 'filter_gutenberg_blocks']);
        }

        if ($this->options['disable_comments_rest_api']) {
            add_filter('rest_endpoints', [$this, 'filter_rest_endpoints']);
            add_filter('xmlrpc_methods', [$this, 'disable_xmlrc_comments']);
            add_filter('rest_pre_insert_comment', [$this, 'disable_rest_api_comments'], 10, 2);
        }

        add_filter('comments_array', '__return_empty_array', 20);
    }

    /**
     * Set up filters based on options.
     */
    protected function setup_filters()
    {
        if ($this->options['disable_comments']) {
            $types = array_keys(get_post_types(['public' => true], 'objects'));
            if (!empty($types)) {
                foreach ($types as $type) {
                    if (post_type_supports($type, 'comments')) {
                        remove_post_type_support($type, 'comments');
                        remove_post_type_support($type, 'trackbacks');
                    }
                }
            }
        }

        // Filters for the admin only
        if (is_admin() && $this->options['disable_comments_dashboard']) {
            add_action('admin_menu', [$this, 'filter_admin_menu'], 9999);    // do this as late as possible
            add_action('admin_print_styles-index.php', [$this, 'admin_css']);
            add_action('admin_print_styles-profile.php', [$this, 'admin_css']);
            add_action('wp_dashboard_setup', [$this, 'filter_dashboard']);
            add_filter('pre_option_default_pingback_flag', '__return_zero');
        }
        // Filters for front end only
        elseif ($this->options['disable_comments_frontend']) {
            add_action('template_redirect', [$this, 'check_comment_template']);
            add_filter('comments_open', '__return_false', 20);
            add_filter('pings_open', '__return_false', 20);
            add_filter('post_comments_feed_link', '__return_false');
            add_filter('comments_link_feed', '__return_false');
            add_filter('comment_link', '__return_false');
            add_filter('get_comments_number', '__return_false');
            add_filter('feed_links_show_comments_feed', '__return_false');
        }
    }

    // ... [All other methods from the original class remain here, 
    //     but with 'protected' visibility where appropriate] ...

    /**
     * Add options page to the settings menu.
     */
    public function add_options_page()
    {
        add_options_page(
            'Disable Comments Settings',
            'Disable Comments',
            'manage_options',
            'disable-comments-settings',
            [$this, 'render_options_page']
        );
    }

    /**
     * Register settings for the options page.
     */
    public function register_settings()
    {
        register_setting('owpc_disable_comments_options', 'owpc_disable_comments_options');

        add_settings_section(
            'owpc_disable_comments_section',
            'Disable Comments Settings',
            [$this, 'settings_section_callback'],
            'disable-comments-settings'
        );

        add_settings_field(
            'disable_comments',
            'Disable Comments',
            [$this, 'render_checkbox'],
            'disable-comments-settings',
            'owpc_disable_comments_section',
            ['label_for' => 'disable_comments']
        );

        add_settings_field(
            'disable_comments_frontend',
            'Disable Comments on Frontend',
            [$this, 'render_checkbox'],
            'disable-comments-settings',
            'owpc_disable_comments_section',
            ['label_for' => 'disable_comments_frontend']
        );

        add_settings_field(
            'disable_comments_dashboard',
            'Disable Comments in Dashboard',
            [$this, 'render_checkbox'],
            'disable-comments-settings',
            'owpc_disable_comments_section',
            ['label_for' => 'disable_comments_dashboard']
        );

        add_settings_field(
            'disable_comments_rest_api',
            'Disable Comments in REST API',
            [$this, 'render_checkbox'],
            'disable-comments-settings',
            'owpc_disable_comments_section',
            ['label_for' => 'disable_comments_rest_api']
        );
    }

    /**
     * Render the options page.
     */
    public function render_options_page()
    {
?>
        <div class="wrap">
            <h1>Disable Comments Settings</h1>
            <form action="options.php" method="post">
                <?php
                settings_fields('owpc_disable_comments_options');
                do_settings_sections('disable-comments-settings');
                submit_button();
                ?>
            </form>
        </div>
    <?php
    }

    /**
     * Render a checkbox field.
     *
     * @param array $args Field arguments.
     */
    public function render_checkbox($args)
    {
        $options = get_option('owpc_disable_comments_options');
    ?>
        <input type="checkbox" id="<?php echo esc_attr($args['label_for']); ?>"
            name="owpc_disable_comments_options[<?php echo esc_attr($args['label_for']); ?>]"
            value="1" <?php checked(isset($options[$args['label_for']]) ? $options[$args['label_for']] : 0, 1); ?>>
<?php
    }

    /**
     * Settings section callback.
     */
    public function settings_section_callback()
    {
        echo '<p>Configure which comment-related features you want to disable.</p>';
    }
}

// Initialize the plugin
new Disable_WP_Comments();
