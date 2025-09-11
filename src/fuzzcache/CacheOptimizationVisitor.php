<?php
/**
 * CacheOptimizationVisitor.php
 *
 * @author Penghui Li <lipenghui315@gmail.com>
 * @author David Dewes <dade00003@stud.uni-saarland.de>
 */

namespace App\FuzzCache;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;
use PhpParser\Node\Name;
use PhpParser\Node\Arg;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Stmt\While_;
use PhpParser\Node\Stmt\Foreach_;
use PhpParser\Node\Expr\Include_;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt;

class CacheOptimizationVisitor extends NodeVisitorAbstract
{
    private string $replacementFunctionName = "PHPSHMCache\\sqlWrapperFunc";
    private bool $requireInserted = false;

    public function beforeTraverse(array $nodes)
    {
        if (!$this->requireInserted) {
            $insertPos = 0;
            foreach ($nodes as $index => $node) {
                if ($node instanceof Stmt\Namespace_) {
                    $insertPos = $index + 1;
                    break;
                }
            }
            array_splice($nodes, $insertPos, 0, [
                new Expression(new Include_(new String_(FUZZ_CACHE_SHM_CLASS), Include_::TYPE_REQUIRE_ONCE))
            ]);
            $this->requireInserted = true;
        }
        return $nodes;
    }

    public function enterNode(Node $node)
    {
        if ($node instanceof While_) {
            if ($this->isMysqliFetchAssocLoop($node)) {
                if (!empty($node->cond->expr) && !empty($node->cond->var)) {
                    return new Foreach_($node->cond->expr, $node->cond->var);
                }
            }
        }

        if ($node instanceof FuncCall && $this->isMysqliQueryCall($node)) {
            return new FuncCall(
                new Name($this->replacementFunctionName),
                [
                    new Arg(new String_($node->name->toString())),
                    new Array_($this->getArgumentsForReplacement($node))
                ]
            );
        }

        return $node;
    }

    private function getArgumentsForReplacement(Expr $expr): array
    {
        $arguments = [];
        if ($expr instanceof FuncCall) {
            foreach ($expr->args as $arg) {
                $arguments[] = new Arg($arg->value);
            }
        }
        return $arguments;
    }

    private function isMysqliQueryCall(FuncCall $node): bool
    {
        return $node->name instanceof Name && in_array($node->name->toString(), [
                "mysqli_connect", "mysqli_query", "mysqli_close", "mysqli_error",
                "mysqli_connect_error", "mysqli_fetch_assoc", "mysqli_num_rows",
                "mysqli_fetch_array", "mysqli_fetch_row", "mysqli_fetch_all"
            ]);
    }

    private function isMysqliFetchAssocLoop(While_ $whileNode): bool
    {
        return (
            $whileNode->cond instanceof Assign &&
            $whileNode->cond->expr instanceof FuncCall &&
            $this->isMysqliFetchAssocCall($whileNode->cond->expr)
        );
    }

    private function isMysqliFetchAssocCall(FuncCall $funcCall): bool
    {
        return (
            $funcCall->name instanceof Name &&
            in_array($funcCall->name->toString(), ["mysqli_fetch_assoc", "mysqli_fetch_row", "mysqli_fetch_array"])
        );
    }
}
