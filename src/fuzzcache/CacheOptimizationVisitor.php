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

class CacheOptimizationVisitor extends NodeVisitorAbstract
{
    private string $replacementFunctionName = "PHPSHMCache\sqlWrapperFunc";

    public function enterNode(Node $node)
    {
        if ($node instanceof While_) {
            // check for a while loop with mysqli_fetch_assoc
            if ($this->isMysqliFetchAssocLoop($node)) {
                // modify the while loop as needed,
                // for example, change it to a foreach loop
                if (!empty($node->cond->expr) && !empty($node->cond->var)) {
                    return new Foreach_(
                        $node->cond->expr,
                        $node->cond->var
                    );
                }
            }
        }

        if ($node instanceof FuncCall && $this->isMysqliQueryCall($node)) {
            // replace the mysqli_query call with wrapped function call
            return new FuncCall(
                new Name($this->replacementFunctionName),
                [
                    new Arg(new String_($node->name->toString())),
                    new Array_(
                        $this->getArgumentsForReplacement($node)
                    )
                ]
            );
        }


        return $node;
    }

    private function getArgumentsForReplacement(Expr $expr): array
    {
        // return the arguments for the replacement function call
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
        return $node->name instanceof Name
            && in_array($node->name->toString(),
                [
                    "mysqli_connect",
                    "mysqli_query",
                    "mysqli_close",
                    "mysqli_error",
                    "mysqli_connect_error",
                    "mysqli_fetch_assoc",
                    "mysqli_num_rows",
                    "mysqli_fetch_array",
                    "mysqli_fetch_row",
                    "mysqli_fetch_all"
                ]);
    }

    private function isMysqliFetchAssocLoop(While_ $whileNode): bool
    {
        // check if it's a while loop with mysqli_fetch_assoc
        return (
            $whileNode->cond instanceof Assign &&
            $whileNode->cond->expr instanceof FuncCall &&
            $this->isMysqliFetchAssocCall($whileNode->cond->expr)
        );
    }

    private function isMysqliFetchAssocCall(FuncCall $funcCall): bool
    {
        // check if it's a mysqli_fetch_assoc() function call
        return (
            $funcCall->name instanceof Name &&
            in_array($funcCall->name->toString(), [
                "mysqli_fetch_assoc",
                "mysqli_fetch_row",
                "mysqli_fetch_array"
            ])
        );
    }
}
