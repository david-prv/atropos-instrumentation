<?php
use PhpParser\Error;
use PhpParser\Node;
use PhpParser\Node\Stmt;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter;

if (!function_exists("parseAndModifyCode")) {
    $code = '<?php $result = mysqli_query($conn, $query);';

    function parseAndModifyCode($path)
    {
        $code = file_get_contents($path);
        $parser = (new ParserFactory)->createForNewestSupportedVersion();

        try {
            $stmts = $parser->parse($code);

            // Create the node visitor
            $nodeVisitor = new ModifyWhileLoopVisitor();

            // Traverse the AST with the node visitor
            $traverser = new PhpParser\NodeTraverser();
            $traverser->addVisitor($nodeVisitor);
            $stmts = $traverser->traverse($stmts);

            // Print the modified code
            $modifiedCode = (new PrettyPrinter\Standard)->prettyPrintFile($stmts);
            #echo $modifiedCode;
            #copy($path, $path.".bak");
            #echo rtrim($path, ".php") . "-fuzzcache.php";
            echo "[*] Optimized: " . rtrim($path, ".php") . PHP_EOL;
            file_put_contents(rtrim($path, ".php"), $modifiedCode);
        } catch (Error $error) {
            echo 'Parse Error: ', $error->getMessage();
        }
    }
}

if (!function_exists("processFileOrDirectory")) {
    function processFileOrDirectory($path)
    {
        if (is_file($path)) {
            // Process a single file
            #$fileCode = file_get_contents($path);
            parseAndModifyCode($path);
        } elseif (is_dir($path)) {
            // Process all PHP files in a directory
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));
            $phpFiles = new RegexIterator($files, '/\.php$/');

            foreach ($phpFiles as $file) {
                if ($file->isFile()) {
                    #$fileCode = file_get_contents($file->getPathname());
                    parseAndModifyCode($file->getPathname());
                }
            }
        } else {
            echo 'Invalid path: ', $path;
        }
    }
}