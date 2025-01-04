<?php

namespace App\Visitor;

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
            ["install", "/includes/class-plugin-upgrader.php"]
        ];

        parent::__construct($filePath, $functions);
    }
}