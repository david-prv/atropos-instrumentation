<?php
/**
 * WordPressSinkVisitor.php
 *
 * @author David Dewes <dade00003@stud.uni-saarland.de>
 */

namespace App\Targets;

class WordPressSinkVisitor extends AbstractSinkVisitor
{
    /**
     * Constructor.
     *
     * Contains a list of potentially dangerous functions from the WordPress
     * Core APIs. These functions are known to be involved in plugin/theme vulnerabilities,
     * which are introduced by inexperienced developers. WordPress depends heavily on defensive
     * programming and safe use of its API functions.
     *
     * Sources for sinks:
     * - https://wpctf.org/guides/sinks/
     * - https://patchstack.com/academy/wordpress/vulnerabilities/
     * - https://github.com/stealthcopter/wordpress-hacking/tree/plugin/
     * - https://blog.criticalthinkingpodcast.io/p/hn-55-wordpress-plugins-common-design-flaws-code-review-methodology/
     * - https://github.com/wpscanteam/wpscan/wiki/WordPress-Plugin-Security-Testing-Cheat-Sheet/
     * - And more!
     *
     * @param string $filePath The currently investigated file.
     */
    public function __construct(string $filePath)
    {
        // Contains a list of critical sink <b>function names</b>.
        // For performance improvement, provide the <b>file</b> where
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
            ["wp_delete_file", "/wp-includes/functions.php"],
            ["wp_delete_file_from_directory", "/wp-includes/functions.php"],
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
            ["activate_plugin", "/wp-includes/plugin.php"],
            ["wp_remote_get", "/wp-includes/http.php"],
            ["wp_remote_request", "/wp-includes/http.php"],
            // ...
        ];

        // This array contains a list of internal <b>function names</b> and entire <b>file names</b> associated
        // with intended propagation of tainted values into security-relevant sinks. These events will be ignored
        // by the crash detection to reduce false-positive reports. E.g. if the fuzzer provides an invalid
        // nonce to the instance, the nonce will be passed to `do_action` by `wp_verify_nonce`
        // to fire an error action.
        $ignoredCallers = [
            // Functions
            [
                "wp_verify_nonce",
                "check_admin_referrer",
                "check_ajax_referrer",
                // ...
            ],

            // Files
            [
                "/wp-includes/class-wp-error.php",
                "/wp-includes/class-wp-recovery-mode-email-service.php",
                // ...
            ]
        ];

        parent::__construct($filePath, $functions, $ignoredCallers);
    }
}