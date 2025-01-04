<?php

namespace App;

require_once("bootstrap.php");

$sourceFiles = [];
$ignoredFiles = [];

// if files should be excluded, parse them.
if (!is_null(EXCLUDE_LOCATION)) {
    $ignoredFiles = explode("\n", file_get_contents(EXCLUDE_LOCATION));

    // if it was not successful, use an empty array.
    // otherwise, trim the entries to prevent encoding related issues.
    if (!$ignoredFiles) $ignoredFiles = [];
    else $ignoredFiles = array_map("trim", $ignoredFiles);
}

// iterator that iterated over all *.php files recursively.
$fileIterator = new \RegexIterator(
    new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator(TARGET_LOCATION)
    ),
    "/^.+\.php$/i", \RegexIterator::GET_MATCH
);

// collect all source files, ignore the excluded ones.
foreach ($fileIterator as $file) {
    $filePath = realpath(trim($file[0]));
    $dirName = trim(dirname($filePath));
    $fileName = trim(basename($filePath));

    // ignore all files that were listed in the exclusion list.
    if (in_array($filePath, $ignoredFiles)
        || in_array($fileName, $ignoredFiles)
        || in_array($dirName, $ignoredFiles)

        // additionally, ignore all plugins/themes
        || strpos($filePath, "wp-content" . DIRECTORY_SEPARATOR . "plugins")
        || strpos($filePath, "wp-content" . DIRECTORY_SEPARATOR . "themes")) {
        echo "[*] Skipped: " . $filePath . PHP_EOL;
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

    // get AST from source code
    $ast = __parse_ast_from_code($code);
    assert($ast !== []);

    // instrument AST iff relevant
    $instrumented = __instrument_ast($ast, $sourceFile);
    assert($instrumented !== []);

    // transform instrumented AST back to source code
    $finalCode = __unparse_ast_to_code($instrumented);

    // override source code with new version
    if (!file_put_contents($sourceFile, $finalCode)) {
        echo "[!] Instrumented file could not be saved to: " . $sourceFile . PHP_EOL;
        continue;
    }
    echo "[*] Instrumented: " . $sourceFile . PHP_EOL;
}

echo "[*] All done." . PHP_EOL;
exit(0);