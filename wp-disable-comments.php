<?php

/**
 * Plugin Name: Disable Comments
 * Description: Disables all WordPress comment functionality on the entire network.
 * Version: 0.0.4
 * Author: Open WP Club
 * Author URI: https://github.com/Open-WP-Club
 * License: GPL2
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class OWPC_Disable_Comments
{
    private $options;

    public function __construct()
    {
        add_action('plugins_loaded', array($this, 'init'));
    }

    public function init()
    {
        $this->options = get_option('owpc_disable_comments_options', array(
            'disable_comments' => true,
            'disable_comments_frontend' => true,
            'disable_comments_dashboard' => true,
            'disable_comments_rest_api' => true,
        ));

        // Core filters
        add_filter('comments_array', '__return_empty_array', 20);
        add_filter('comments_open', '__return_false', 20);
        add_filter('pings_open', '__return_false', 20);

        // Admin-side filters
        if (is_admin()) {
            add_action('admin_init', array($this, 'admin_init'));
            add_action('admin_menu', array($this, 'admin_menu'), 9999);
        }
        // Frontend filters
        else {
            add_action('template_redirect', array($this, 'frontend_filters'));
        }

        // Common filters
        add_filter('wp_headers', array($this, 'filter_wp_headers'));
        add_action('widgets_init', array($this, 'disable_rc_widget'));

        // REST API filters
        add_filter('rest_endpoints', array($this, 'filter_rest_endpoints'));
        add_filter('rest_pre_insert_comment', array($this, 'disable_rest_api_comments'), 10, 2);

        // XML-RPC
        add_filter('xmlrpc_methods', array($this, 'disable_xmlrpc_comments'));
    }

    public function admin_init()
    {
        // Remove comments metabox from dashboard
        remove_meta_box('dashboard_recent_comments', 'dashboard', 'normal');

        // Disable support for comments and trackbacks in post types
        foreach (get_post_types() as $post_type) {
            if (post_type_supports($post_type, 'comments')) {
                remove_post_type_support($post_type, 'comments');
                remove_post_type_support($post_type, 'trackbacks');
            }
        }
    }

    public function admin_menu()
    {
        // Remove comments and discussion settings from admin menu
        remove_menu_page('edit-comments.php');
        remove_submenu_page('options-general.php', 'options-discussion.php');
    }

    public function frontend_filters()
    {
        // Remove comment-reply script for themes that include it indiscriminately
        wp_deregister_script('comment-reply');
        // Feed removals
        remove_action('wp_head', 'feed_links_extra', 3);
        add_filter('feed_links_show_comments_feed', '__return_false');
    }

    public function filter_wp_headers($headers)
    {
        unset($headers['X-Pingback']);
        return $headers;
    }

    public function disable_rc_widget()
    {
        unregister_widget('WP_Widget_Recent_Comments');
    }

    public function filter_rest_endpoints($endpoints)
    {
        if ($this->options['disable_comments_rest_api']) {
            foreach ($endpoints as $route => $endpoint) {
                if (stripos($route, '/comments') !== false) {
                    unset($endpoints[$route]);
                }
            }
        }
        return $endpoints;
    }

    public function disable_rest_api_comments($prepared_comment, $request)
    {
        if ($this->options['disable_comments_rest_api']) {
            return new WP_Error(
                'rest_comment_disabled',
                __('Comments are disabled on this site.', 'disable-comments'),
                array('status' => 403)
            );
        }
        return $prepared_comment;
    }

    public function disable_xmlrpc_comments($methods)
    {
        unset($methods['wp.newComment']);
        unset($methods['wp.getCommentCount']);
        unset($methods['wp.getComment']);
        unset($methods['wp.getComments']);
        unset($methods['wp.deleteComment']);
        unset($methods['wp.editComment']);
        unset($methods['wp.newComment']);
        unset($methods['wp.getCommentStatusList']);
        return $methods;
    }
}

new OWPC_Disable_Comments();
