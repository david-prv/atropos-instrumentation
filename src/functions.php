<?php

use App\Visitor\WordPressSinkVisitor;

use PhpParser\PrettyPrinter\Standard;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;

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
            echo "Parse error: {$error->getMessage()}\n";
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
     * @param string $sourceFile The location of the source file.
     * @return array The instrumented AST as array.
     */
    function __instrument_ast(array $ast, string $sourceFile): array
    {
        $traverser = new NodeTraverser();
        $traverser->addVisitor(new WordPressSinkVisitor($sourceFile));
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