<?php
/**
 * WordPressSinkVisitor.php
 *
 * @author David Dewes <dade00003@stud.uni-saarland.de>
 */

namespace App\Targets;

class WordPressSinkVisitor extends AbstractSinkVisitor
{
    public function __construct(string $filePath)
    {
        // for performance improvement, provide the file where
        // the sink's definition is located at. If you don't know,
        // just put the function name as an array entry.
        $functions = [
            ["update_option", "/wp-includes/option.php"],
            ["delete_option", "/wp-includes/option.php"],
            ["update_site_option", "/wp-includes/option.php"],
            ["delete_site_option", "/wp-includes/option.php"],
            ["do_shortcode", "/wp-includes/shortcodes.php"],
            ["wp_delete_post", "/wp-includes/post.php"],
            ["wp_insert_post", "/wp-includes/post.php"],
            ["wp_update_post", "/wp-includes/post.php"],
            ["wp_post_meta", "/wp-includes/post.php"],
            ["maybe_unserialize", "/wp-includes/functions.php"],
            ["wp_mail", "/wp-includes/pluggable.php"],
            ["wp_insert_user", "/wp-includes/user.php"],
            ["wp_update_user", "/wp-includes/user.php"],
            ["wp_delete_user", "/wp-includes/user.php"],
            ["get_users", "/wp-includes/user.php"],
            ["update_user_meta", "/wp-includes/user.php"],
            ["query", "/wp-includes/class-wpdb.php"],
            ["get_var", "/wp-includes/class-wpdb.php"],
            ["get_row", "/wp-includes/class-wpdb.php"],
            ["get_col", "/wp-includes/class-wpdb.php"],
            ["get_results", "/wp-includes/class-wpdb.php"],
            ["replace", "/wp-includes/class-wpdb.php"],
            ["esc_like", "/wp-includes/class-wpdb.php"],
            ["like_escape", "/wp-includes/deprecated.php"],
            ["esc_sql", "/wp-includes/formatting.php"],
            ["escape", "/wp-includes/class-wp-xmlrpc-server.php"],
            ["wp_redirect", "/wp-includes/pluggable.php"],
            ["do_action", "/wp-includes/plugin.php"],
            ["install", "/wp-includes/class-plugin-upgrader.php"],
            ["activate_plugin", "/wp-includes/plugin.php"]
        ];

        parent::__construct($filePath, $functions);
    }
}