<?php
/**
 * constants.php
 *
 * @author David Dewes <dade00003@stud.uni-saarland.de>
 */

/**
 * The location of the WordPress instance.
 * (Passed via command-line)
 */
if (!defined("TARGET_LOCATION")) {
    define("TARGET_LOCATION", $argv[1] ?? NULL);
}

/**
 * The sink visitor used for target instrumentation. Adjust this variable
 * if you want to instrument a different target application.
 */
if (!defined("TARGET_VISITOR_CLASS")) {
    define("TARGET_VISITOR_CLASS", \App\Targets\WordPressSinkVisitor::class);
}

/**
 * The location of an optional exclusion list.
 * (Passed via command-line)
 */
if (!defined("EXCLUDE_LOCATION")) {
    define("EXCLUDE_LOCATION", $argv[2] ?? NULL);
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
 * Disables the output of the instrumentor code.
 * Errors are still reported.
 */
if (!defined("SILENT_MODE")) {
    define("SILENT_MODE", false);
}

/**
 * Toggles the use of "FuzzCache" to improve the performance
 * of the target application, as e.g. WordPress.
 */
if (!defined("OPTIMIZE_WITH_FUZZ_CACHE")) {
    define("OPTIMIZE_WITH_FUZZ_CACHE", false);
}

/**
 * Defines the location of the fuzzcache shared memory
 * cache support.
 * 
 * NOTE:    Only needed if optimization is used. Path is
 *          relative from target's root folder!
 */
if (!defined("FUZZ_CACHE_SHM_CLASS")) {
    define("FUZZ_CACHE_SHM_CLASS", __DIR__ . "/fuzzcache/PHPSHMCache.php");
}
