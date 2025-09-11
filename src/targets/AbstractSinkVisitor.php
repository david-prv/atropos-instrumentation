<?php
/**
 * AbstractSinkVisitor.php
 *
 * @author David Dewes <dade00003@stud.uni-saarland.de>
 */

namespace App\Targets;

use PhpParser\Node;
use PhpParser\Node\Param;
use PhpParser\NodeVisitorAbstract;

abstract class AbstractSinkVisitor extends NodeVisitorAbstract
{
    /**
     * @var array The provided sinks to consider.
     */
    protected array $sinks = [];

    /**
     * @var array Functions that are intended to propagate tainted values to sinks.
     */
    protected array $ignoredCallerFunctions = [];

    /**
     * @var array Files that are involved in intended internal tainted value propagation.
     */
    protected array $ignoredCallerFiles = [];

    /**
     * @var array The files that contain sinks.
     */
    protected array $files = [];

    /**
     * @var array Sinks that were already instrumented (multiple occurrences).
     */
    protected array $found = [];

    /**
     * @var string The path of the currently instrumented source file.
     */
    protected string $filePath;

    /**
     * @var bool Flag to enforce traversal in case of unknown locations.
     */
    protected bool $enforceTraversal;

    /**
     * Constructor.
     *
     * @param string $filePath The source file path.
     * @param array $functions The sinks as array.
     */
    public function __construct(string $filePath, array $functions, array $ignoredCallers)
    {
        if (count($ignoredCallers) === 2) {
            $this->ignoredCallerFunctions = $ignoredCallers[0];
            $this->ignoredCallerFiles = array_map(function ($file) {
                return adjust_path_separators($file, true);
            }, $ignoredCallers[1]);
        }

        $this->filePath = adjust_path_separators($filePath);
        $this->enforceTraversal = false;

        foreach ($functions as $function) {
            if (is_array($function) && count($function) > 1) {
                $this->addFile($function[1]);
            }
            $this->addSink($function);
        }
    }

    /**
     * Registers a new sink.
     *
     * If the passed array contains elements, that are
     * not a tuple, this function also takes special actions:
     * For elements that are literal strings, we enforce traversal
     * of each file's AST. For nested arrays that aren't tuples, we
     * flatten the array. For valid tuples, we standardize the file path.
     *
     * @param $function array|string The sink function to register.
     * @return void
     */
    protected function addSink(array|string $function): void
    {
        if (is_array($function)) {
            if (count($function) > 1) {
                // We want the correct directory separator here.
                $function[1] = adjust_path_separators($function[1]);
            } else if (count($function) == 1) {
                // If there is only one element, treat it as non-array
                $function = $function[0];
            } else {
                // If there is no element: Abort.
                return;
            }
        } else {
            // We encountered an element with unknown location!
            // Thus, we won't skip the traversal of this AST, since
            // we can't know if it's important or not.
            $this->enforceTraversal = true;
        }
        $this->sinks[] = $function;
    }

    /**
     * Marks a file path as relevant for the sink search.
     *
     * @param string $filePath The file path to consider.
     * @return void
     */
    protected function addFile(string $filePath): void
    {
        $this->files[] = adjust_path_separators($filePath);
    }

    /**
     * Checks whether the current node is a sink.
     *
     * @param Node $node The AST node to check.
     * @return bool Flag that indicates the sink state.
     */
    protected function isSink(Node $node): bool
    {
        // We only consider function definitions here: Skip.
        if (!($node instanceof Node\Stmt\Function_) && !($node instanceof Node\Stmt\ClassMethod)) {
            return false;
        }

        // This sink definition was already patched: Skip.
        if (in_array(strtolower($node->name->toString()), $this->found)) {
            return false;
        }

        foreach ($this->sinks as $sink) {
            // By default, we only know a generic function name.
            // However, the user can still provide a file location, to
            // which the sink detection is then bound.
            $functionName = $sink;
            $boundLocation = NULL;

            // If the sink contains a bound location,
            // unpack the information into two separate variables.
            if (is_array($sink)) {
                [$functionName, $boundLocation] = $sink;
            }

            // If there is a location specified, but they don't match: Skip.
            if (!is_null($boundLocation) && (!str_contains($this->filePath, $boundLocation))) {
                continue;
            }

            // If the currently visited function's name is equal to
            // the current sink, report true.
            if (strtolower($functionName) === strtolower($node->name->toString())) {
                $this->found[] = strtolower($functionName);
                return true;
            }
        }

        return false;
    }

    /**
     * Overridden `enterNode` function.
     *
     * @see AbstractSinkVisitor::enterNode()
     * @link https://github.com/nikic/PHP-Parser/blob/master/doc/component/Walking_the_AST.markdown#node-visitors
     */
    public function enterNode(Node $node)
    {
        $relevant = false;

        foreach ($this->files as $file) {
            $relevant = $relevant || str_contains($this->filePath, $file);
        }

        // If sinks contain elements with unknown locations, we NEVER want to skip traversal.
        // I.e. we enforce traversal here.
        $relevant = $this->enforceTraversal || $relevant;

        if (!$relevant) {
            // If current file does not contain a method to instrument,
            // we stop traversal by returning a stop signal.
            return self::STOP_TRAVERSAL;
        }
        // otherwise, traverse the AST.
        return $node;
    }

    /**
     * Overridden `leaveNode` function.
     *
     * @see AbstractSinkVisitor::leaveNode()
     * @link https://github.com/nikic/PHP-Parser/blob/master/doc/component/Walking_the_AST.markdown#node-visitors
     */
    public function leaveNode(Node $node): ?Node\Stmt
    {
        // Check whether node is a relevant sink.
        if ($this->isSink($node)) {
            // The files used by Atropos.
            $fileEnabled = BUG_ORACLE_ENABLED_LOCATION;
            $fileTriggered = BUG_TRIGGERED_LOCATION;

            // All function components we want to keep.
            $functionComment = $node->getDocComment()->getText();
            $functionName = $node->name->toString();
            $functionBody = unparse_ast_to_code($node->stmts, true);
            $functionParams = implode(
                ", ",
                array_map(
                    function (Param $param) {
                        $type = $param->type ? $param->type->toString() . ' ' : '';
                        $byRef = $param->byRef ? '&' : '';
                        $variadic = $param->variadic ? '...' : '';
                        $default = $param->default ? ' = ' . unparse_ast_to_code([$param->default], true) : '';

                        return "{$type}{$byRef}{$variadic}\${$param->var->name}{$default}";
                    },
                    $node->getParams()
                )
            );

            // Reduce FPR by ignoring internal behavior that may trigger crash oracles otherwise
            $ignoredCallerFunctionsJSON = json_encode($this->ignoredCallerFunctions, JSON_UNESCAPED_SLASHES);
            $ignoredCallerFilesJSON = stripslashes(json_encode($this->ignoredCallerFiles, JSON_UNESCAPED_SLASHES));

            if (!$ignoredCallerFunctionsJSON || !$ignoredCallerFilesJSON) {
                // Something went wrong, maybe malformed configuration.
                // TODO: Better error handling! So far, we're just ignoring the node.
                return null;
            }

            // The instrument's payload.
            $payload = <<<EOT
{$functionComment}
function {$functionName}({$functionParams}) {
    \$isCrash = false;
    \$arg_list = func_get_args();
    for (\$i = 0; \$i < func_num_args(); \$i++) {
        if (is_string(\$arg_list[\$i]) && strpos(\$arg_list[\$i], "crash") !== false) {
            \$isCrash = true;
            break;
        }
    }
    if (\$isCrash && file_exists("{$fileEnabled}")) {
        if(!\$fp = fopen("{$fileTriggered}", "a+")) {
            die("ATROPOS ERROR: Unable to open file '{$fileTriggered}'!");
        }
        \$caller = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function'] ?? "unknown";
        \$caller_file = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['file'] ?? "unknown";
        \$caller_line = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['line'] ?? 0;
        
        if (!in_array(\$caller, {$ignoredCallerFunctionsJSON}) && !in_array(\$caller_file, {$ignoredCallerFilesJSON})) {
            fwrite(\$fp, "bug oracle triggered: '{$functionName}' called by '\$caller' in '\$caller_file' at line \$caller_line\\n");
        }
    }

    {$functionBody}
}
EOT;
            // Iff it is a relevant sink, replace it with instrumented version.
            return parse_ast_from_code($payload)[0];
        }

        // If node is irrelevant, keep it.
        return null;
    }
}