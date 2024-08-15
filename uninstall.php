<?php

/**
 * Uninstall script for Disable Comments plugin.
 *
 * @package OpenWPClub\DisableComments
 */

// If uninstall not called from WordPress, then exit.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete the plugin options.
delete_option('owpc_disable_comments_options');

// If it's a multisite installation, delete options from all sites.
if (is_multisite()) {
    global $wpdb;
    $blog_ids = $wpdb->get_col("SELECT blog_id FROM $wpdb->blogs");
    foreach ($blog_ids as $blog_id) {
        switch_to_blog($blog_id);
        delete_option('owpc_disable_comments_options');
        restore_current_blog();
    }
}
