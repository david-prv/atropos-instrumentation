# Atropos Instrumentation

This tool will automatically scan through a WordPress instance to instrument so-called "critical sinks"
with crash-reporting code. This instrumentor is meant to be used along with the "Atropos" fuzzer. The goal
is to instrument the core API of a web framework to fuzz its respective web extensions.

## Contents

- [Installation](#installation)
- [Example Usage](#example-usage)
- [Configuration](#configuration)
    - [Targets](#targets)
    - [Performance](#performance)

## Installation
```bash
git clone https://github.com/david-prv/atropos-instrumentation.git
cd atropos-instrumentation
composer install
```

## Example Usage
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

### Targets

To instrument WordPress, you can just use the `WordPressSinkVisitor` class. You can still adjust the
sinks that should be considered by editing the `$functions` array in `src/targets/WordPressSinkVisitor.php`.
Sinks are given either as tuples or as literal strings. Tuples should look like `["function", "file"]`, where
the first component is the sink's function name and the second component is the file where this sink is defined.
If you don't exactly know the location or if there could be potentially multiple occurrences, you can just put the
function name as array item.

To reduce false-positive reports from the fuzzer's crash detection, we introduced a second variable `$ignoredCallers`, which
can be used to provide details about the target CMS' internals. Sometimes, tainted values are propagated to annotated sinks but in
intended scenarios. The assumption here is, that official internal functions do not suffer from typical security-relevant mistakes
using the WordPress APIs. Examples are given in the `WordPressSinkVisitor` class. You may add function names and/or whole classes (i.e. files)
to that list of ignored callers. Everytime, the fuzzer detects a tainted value flowing into a sink originating from any of these sources,
the event will be ignored an no false-positive is generated.

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
    ["install", "/includes/class-plugin-upgrader.php"],
    // ...
];

// example list of trusted/ignored callers
$ignoredCallers = [
    // function names
    [
        "wp_verify_nonce",
        "check_admin_referrer",
        "check_ajax_referer",
        // ...
    ],

    // file names
    [
        "/wp-includes/class-wp-error.php",
        "/wp-includes/class-wp-recovery-mode-email-service.php",
        // ...
    ]
];
```

To create Atropos instrumentation for a new target (only php-based targets allowed), create a new visitor inside of
`src/targets/`:

```php
<?php

namespace App\Targets;

class NewSinkVisitor extends AbstractSinkVisitor
{
    public function __construct(string $filePath)
    {
        $functions = [
            // place sinks here...
        ];

        $ignoredCallers = [
            // place trusted callers here...
        ];

        parent::__construct($filePath, $functions, $ignoredCallers);
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
    define("TARGET_VISITOR_CLASS", \App\Targets\WordPressSinkVisitor::class);
}
```

Replace the Visitor class name with your new class.

### Performance

We integrated the PHPSHMCache implementation as introduced by [FuzzCache](https://peng-hui.github.io/data/paper/ccs24_fuzzcache.pdf) into our instrumentation, as an optional addition. This enables us to speed-up the target application by the factor 3x to 4x. However, you need to toggle the cache optimization using the `OPTIMIZE_WITH_FUZZ_CACHE` constant in `src/constants.php`, since it is **disabled** by default. This is, because our main target is WordPress, which in itself has an own cache implementation that we just extended to be persistent across requests. Wordpress is very slow, especially if plugins are programmed in an inefficient way or if the end-user does not know how to properly use uploaded media, compared to other slim web frameworks (compare [here](https://wordpress.com/support/site-speed/) or [here](https://instawp.com/wordpress-running-slow/)). This is true for many possible targets, thus, consider using FuzzCache.
