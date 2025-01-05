# Atropos Instrumentation

This tool will automatically scan through a WordPress instance to instrument so-called "critical sinks"
with crash-reporting code. This instrumentor is meant to be used along with the "Atropos" fuzzer. The goal
is to instrument the core API of a web framework to fuzz its respective web extensions.

## Installation
```bash
git clone https://github.com/david-prv/atropos-instrumentation.git
cd atropos-instrumentation
composer install
```

## Example
```bash
# Install WordPress CLI tool
curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
chmod +x wp-cli.phar
sudo mv wp-cli.phar /usr/local/bin/wp

# Download test instance
wp core download --path=./target

# Run instrumentation
php ./src/instrumentor.php ../target
```

## Configuration
To instrument WordPress, you can just use the `WordPressSinkVisitor` class. You can still adjust the
sinks that should be considered by editing the `$functions` array in `src/visitors/WordPressSinkVisitor.php`.
Sinks are given either as tuples or as literal strings. Tuples should look like `["function", "file"]`, where
the first component is the sink's function name and the second component is the file where this sink is defined.
If you don't exactly know the location or if there could be potentially multiple occurrences, you can just put the
function name as array item.

```php
// example sinks for WordPress
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
```

To create Atropos instrumentation for a new target (only php-based targets allowed), create a new visitor inside of
`src/visitors/`:

```php
<?php

namespace App\Visitor;

class NewSinkVisitor extends AbstractSinkVisitor
{
    public function __construct(string $filePath)
    {
        $functions = [
            // place sinks here...
        ];

        parent::__construct($filePath, $functions);
    }
}
```

Don't forget to instruct the instrumentor to use the newly created visitor class in `src/constants.php`:

```php
/**
 * The sink visitor used for target instrumentation. Adjust this variable
 * if you want to instrument a different target application.
 */
if (!defined("TARGET_VISITOR_CLASS")) {
    define("TARGET_VISITOR_CLASS", \App\Visitor\WordPressSinkVisitor::class);
}
```

Replace the Visitor class name with your new class.