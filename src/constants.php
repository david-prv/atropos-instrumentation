<?php
/**
 * The location of the WordPress instance.
 */
if (!defined("TARGET_LOCATION")) {
    define("TARGET_LOCATION", $argv[1]);
}

/**
 * The location of an optional exclusion list.
 */
if (!defined("EXCLUDE_LOCATION")) {
    if (isset($argv[2])) {
        define("EXCLUDE_LOCATION", $argv[2]);
    } else {
        define("EXCLUDE_LOCATION", NULL);
    }
}

/**
 * The location of the `bug_oracle_enabled` file, as
 * used by Atropos to indicate whether bug oracles are used.
 */
if (!defined("BUG_ORACLE_ENABLED_LOCATION")) {
    define("BUG_ORACLE_ENABLED_LOCATION", "/tmp/bug_oracle_enabled");
}

/**
 * The location of the `bug_triggered` file, as
 * used by Atropos to report that a crash was triggered.
 */
if (!defined("BUG_TRIGGERED_LOCATION")) {
    define("BUG_TRIGGERED_LOCATION", "/tmp/bug_triggered");
}

/**
 * The sink visitor used for instrumentation. Adjust this variable
 * if you want to instrument a different target application.
 */
if (!defined("SINK_VISITOR_CLASS")) {
    define("SINK_VISITOR_CLASS", \App\Visitor\WordPressSinkVisitor::class);
}