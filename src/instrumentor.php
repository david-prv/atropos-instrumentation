<?php
/**
 * instrumentor.php
 *
 * @author David Dewes <dade00003@stud.uni-saarland.de>
 */

namespace App;

require(__DIR__ . "/../vendor/autoload.php");
require(__DIR__ . "/constants.php");
require(__DIR__ . "/functions.php");

ini_set('xdebug.max_nesting_level', 3000);
ini_set('error_reporting', E_ALL);
ini_set('memory_limit', -1);
set_time_limit(0);

assert(
    count($argv) >= 2,
    "Usage: php src/instrumentor.php <target> [<excluded>]"
);

$sourceFiles = [];
$ignoredFiles = [];

// If files should be excluded, parse them.
if (!is_null(EXCLUDE_LOCATION)) {
    $ignoredFiles = explode("\n", file_get_contents(EXCLUDE_LOCATION));

    // If it was not successful, use an empty array.
    // Otherwise, trim the entries to prevent encoding related issues.
    if (!$ignoredFiles) $ignoredFiles = [];
    else $ignoredFiles = array_map("trim", $ignoredFiles);
}

// Iterator that iterated over all *.php files recursively.
$fileIterator = new \RegexIterator(
    new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator(TARGET_LOCATION)
    ),
    "/^.+\.php$/i", \RegexIterator::GET_MATCH
);

// Collect all source files, ignore the excluded ones.
foreach ($fileIterator as $file) {
    $filePath = realpath(trim($file[0]));
    $dirName = trim(dirname($filePath));
    $fileName = trim(basename($filePath));

    // Ignore all files that were listed in the exclusion list.
    if (in_array($filePath, $ignoredFiles)
        || in_array($fileName, $ignoredFiles)
        || in_array($dirName, $ignoredFiles)

        // additionally, ignore all plugins/themes
        || strpos($filePath, "wp-content" . DIRECTORY_SEPARATOR . "plugins")
        || strpos($filePath, "wp-content" . DIRECTORY_SEPARATOR . "themes")) {
        if (!SILENT_MODE) echo "[*] Skipped: " . $filePath . PHP_EOL;
        continue;
    }

    $sourceFiles[] = $filePath;
}

// Iterate over source files and apply instrumentation
foreach ($sourceFiles as $sourceFile) {
    $code = file_get_contents($sourceFile);
    if (!$code) {
        echo "[!] Source file not found: " . $sourceFile . PHP_EOL;
        continue;
    }

    // Get AST from source code
    $ast = parse_ast_from_code($code);
    assert($ast !== []);

    // Instrument AST iff relevant
    $instrumented = instrument_ast($ast, TARGET_VISITOR_CLASS, $sourceFile);
    assert($instrumented !== []);

    // Transform instrumented AST back to source code
    $finalCode = unparse_ast_to_code($instrumented);

    // Override source code with new version
    if (!file_put_contents($sourceFile, $finalCode)) {
        echo "[!] Instrumented file could not be saved to: " . $sourceFile . PHP_EOL;
        continue;
    }
    if (!SILENT_MODE) echo "[*] Instrumented: " . $sourceFile . PHP_EOL;
}

if (!SILENT_MODE) echo "[*] All done. (" . count($sourceFiles) . " files)" . PHP_EOL;
exit(0);