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
    public function __construct(string $filePath, array $functions)
    {
        $this->filePath = __adjust_path_separators($filePath);
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
     * @param $function The sink function to register.
     * @return void
     */
    protected function addSink($function): void
    {
        if (is_array($function)) {
            if (count($function) > 1) {
                // we want the correct directory separator here
                $function[1] = __adjust_path_separators($function[1]);
            } else if (count($function) == 1) {
                // if there is only one element, treat it as non-array
                $function = $function[0];
            } else {
                // if there is no element: abort.
                return;
            }
        } else {
            // we encountered an element with unknown location!
            // thus, we won't skip the traversal of this AST, since
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
        $this->files[] = __adjust_path_separators($filePath);
    }

    /**
     * Checks whether the current node is a sink.
     *
     * @param Node $node The AST node to check.
     * @return bool Flag that indicates the sink state.
     */
    protected function isSink(Node $node): bool
    {
        // we only consider function definitions here: skip.
        if (!($node instanceof Node\Stmt\Function_) && !($node instanceof Node\Stmt\ClassMethod)) {
            return false;
        }

        // this sink definition was already patched: skip.
        if (in_array(strtolower($node->name->toString()), $this->found)) {
            return false;
        }

        foreach ($this->sinks as $sink) {
            // by default, we only know a generic function name.
            // however, the user can still provide a file location, to
            // which the sink detection is then bound.
            $functionName = $sink;
            $boundLocation = NULL;

            // if the sink contains a bound location,
            // unpack the information into two separate variables.
            if (is_array($sink)) {
                [$functionName, $boundLocation] = $sink;
            }

            // if there is a location specified, but they don't match: skip.
            if (!is_null($boundLocation) && (!str_contains($this->filePath, $boundLocation))) {
                continue;
            }

            // if the currently visited function's name is equal to
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

        // if sinks contain elements with unknown locations, we NEVER want to skip traversal.
        // i.e. we enforce traversal here.
        $relevant = $this->enforceTraversal || $relevant;

        if (!$relevant) {
            // if current file does not contain a method to instrument,
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
        // check whether node is a relevant sink.
        if ($this->isSink($node)) {
            // the files used by Atropos.
            $fileEnabled = BUG_ORACLE_ENABLED_LOCATION;
            $fileTriggered = BUG_TRIGGERED_LOCATION;

            // all function components we want to keep.
            $functionComment = $node->getDocComment()->getText();
            $functionName = $node->name->toString();
            $functionBody = __unparse_ast_to_code($node->stmts, true);
            $functionParams = implode(
                ", ",
                array_map(
                    function (Param $param) {
                        $type = $param->type ? $param->type->toString() . ' ' : '';
                        $byRef = $param->byRef ? '&' : '';
                        $variadic = $param->variadic ? '...' : '';
                        $default = $param->default ? ' = ' . __unparse_ast_to_code([$param->default], true) : '';

                        return "{$type}{$byRef}{$variadic}\${$param->var->name}{$default}";
                    },
                    $node->getParams()
                )
            );

            // the instrument's payload.
            // TODO: we might forget some edge cases here.
            $payload = <<<EOT
{$functionComment}
function {$functionName}({$functionParams}) {
    \$isCrash = false;
    \$arg_list = func_get_args();
    \$flagged_arg = null;
    
    for (\$i = 0; \$i < func_num_args(); \$i++) {
        // check if the argument is a string and contains "crash" 
        if (is_string(\$arg_list[\$i]) && strpos(\$arg_list[\$i], "crash") !== false) {
            \$isCrash = true;
            \$flagged_arg = \$arg_list[\$i];
            break;
        }

        // check if the argument is an array and contains an element that contains "crash"
        if (is_array(\$arg_list[\$i])) {
            foreach (\$arg_list[\$i] as \$value) {
                if (is_string(\$value) && strpos(\$value, "crash") !== false) {
                    \$isCrash = true;
                    \$flagged_arg = \$value;
                    break 2; // break out of both loops
                }
            }
        }
    }

    if (\$isCrash && file_exists("{$fileEnabled}")) {
        if(!\$fp = fopen("{$fileTriggered}", "a+")) {
            die("ATROPOS ERROR: Unable to open file '{$fileTriggered}'!");
        }
        \$caller = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function'] ?? "unknown";
        \$caller_file = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['file'] ?? "unknown";
        \$caller_line = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['line'] ?? 0;
        fwrite(\$fp, "bug oracle triggered: '{$functionName}' called with arg '{\$flagged_arg}' by '\$caller' in '\$caller_file' at line \$caller_line\\n");
    }

    {$functionBody}
}
EOT;
            // iff it is a relevant sink, replace it with instrumented version.
            return __parse_ast_from_code($payload)[0];
        }

        // if node is irrelevant, keep it.
        return null;
    }
}