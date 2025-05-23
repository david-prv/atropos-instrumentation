<?php
/**
 * functions.php
 *
 * @author David Dewes <dade00003@stud.uni-saarland.de>
 */

use PhpParser\PrettyPrinter\Standard;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use PhpParser\Error;

use App\FuzzCache\CacheOptimizationVisitor;

if (PHP_VERSION_ID < 80000) {
    // do some backward compatibility magic

    if (!function_exists("str_starts_with")) {
        function str_starts_with(string $haystack, string $needle): bool
        {
            return substr($haystack, 0, strlen($needle)) === $needle;
        }
    }

    if (!function_exists("str_contains")) {
        function str_contains(string $haystack, string $needle): bool
        {
            return strpos($haystack, $needle) !== false;
        }
    }
}

if (!function_exists("__parse_ast_from_code")) {
    /**
     * Transforms string representation of php code into an AST.
     *
     * @param string $code The source code to parse.
     * @return PhpParser\Node\Stmt[] The AST as array.
     */
    function __parse_ast_from_code(string $code): array
    {
        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        if (!str_starts_with($code, "<?php")) {
            $code = "<?php\n" . $code;
        }

        try {
            $ast = $parser->parse($code);
        } catch (Error $error) {
            echo "[!] Parse Error: " . $error->getMessage() . PHP_EOL;
            return [];
        }
        return $ast;
    }
}

if (!function_exists("__instrument_ast")) {
    /**
     * Instruments an AST with feedback mechanism.
     *
     * @param array $ast The AST to instrument.
     * @param string $visitor The AST visitor to use.
     * @param string $sourceFile The location of the source file.
     * @return array The instrumented AST as array.
     */
    function __instrument_ast(array $ast, string $visitor, string $sourceFile): array
    {
        $traverser = new NodeTraverser();
        $traverser->addVisitor(new $visitor($sourceFile));

        if (OPTIMIZE_WITH_FUZZ_CACHE) {
            $traverser->addVisitor(new CacheOptimizationVisitor());
        }

        return $traverser->traverse($ast);
    }
}

if (!function_exists("__unparse_ast_to_code")) {
    /**
     * Transforms AST back into string representation.
     *
     * @param array $ast The AST to unparse.
     * @param bool $ignoreTag Toggles the php start-tag.
     * @return string The string representation.
     */
    function __unparse_ast_to_code(array $ast, bool $ignoreTag = false): string
    {
        $prettyPrinter = new Standard();
        if ($ignoreTag) {
            return $prettyPrinter->prettyPrint($ast);
        }
        return (new Standard)->prettyPrintFile($ast);
    }
}

if (!function_exists("__adjust_path_separators")) {
    /**
     * Standardizes location paths by adjusting their separators.
     *
     * @param string $path The path to correct.
     * @return string The standardized path.
     */
    function __adjust_path_separators(string $path): string
    {
        return str_replace(array("/", "\\"), DIRECTORY_SEPARATOR, $path);
    }
}