<?php

namespace App\Visitor;

use PhpParser\Error;
use PhpParser\Node;
use PhpParser\Node\Stmt;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter;
use PhpParser\NodeVisitorAbstract;

class ModifyWhileLoopVisitor extends NodeVisitorAbstract
{
    private $replacementFunctionName = 'PHPSHMCache\sqlWrapperFunc';

    public function enterNode(Node $node)
    {
        #echo get_class($node) . "\n";
        if ($node instanceof Stmt\While_) {
            #var_dump($node);
            // Check for a while loop with mysqli_fetch_assoc
            if ($this->isMysqliFetchAssocLoop($node)) {
                // Modify the while loop as needed
                // For example, change it to a foreach loop
                #echo "is while loop???\n";
                #var_dump($node->cond);
                $foreachLoop = new Node\Stmt\Foreach_($node->cond->expr,
                    $node->cond->var         
                );

                return $foreachLoop;
            }
        }

        if ($node instanceof Node\Expr\FuncCall && $this->isMysqliQueryCall($node)) {
            // Replace the mysqli_query call with PHPSHMCache\sqlWrapperFunc
            $newFuncCall = new Node\Expr\FuncCall(
                new Node\Name($this->replacementFunctionName), 
                [
                    new Node\Arg(new Node\Scalar\String_($node->name->toString())),
                    new Node\Expr\Array_(
                        $this->getArgumentsForReplacement($node)
                    )
                ]
            );

            return $newFuncCall;
        }
        
        
        return $node;
    }
    private function getArgumentsForReplacement(Node\Expr $expr)
    {
        // Return the arguments for the replacement function call
        $arguments = [];
        if ($expr instanceof Node\Expr\FuncCall) {
            foreach ($expr->args as $arg) {
                $arguments[] = new Node\Arg($arg->value);
            }
        }

        return $arguments;
    }
    private function isMysqliQueryCall(Node\Expr\FuncCall $node)
    {
        return $node->name instanceof Node\Name && in_array($node->name->toString(), ['mysqli_connect', 'mysqli_query', "mysqli_close", "mysqli_error", "mysqli_connect_error", "mysqli_fetch_assoc", 'mysqli_num_rows', "mysqli_fetch_array", "mysqli_fetch_row", "mysqli_fetch_all"]);
    }

    private function isMysqliFetchAssocLoop(Node\Stmt\While_ $whileNode)
    {
        // Check if it's a while loop with mysqli_fetch_assoc
        #var_dump($whileNode);
        return (
            $whileNode->cond instanceof Node\Expr\Assign &&
            $whileNode->cond->expr instanceof Node\Expr\FuncCall &&
            $this->isMysqliFetchAssocCall($whileNode->cond->expr)
        );
    }

    private function isMysqliFetchAssocCall(Node\Expr\FuncCall $funcCall)
    {
        // Check if it's a mysqli_fetch_assoc() function call
        #echo $funcCall->name->toString() . "\n";
        return (
            $funcCall->name instanceof Node\Name &&
            in_array($funcCall->name->toString(), array('mysqli_fetch_assoc', 'mysqli_fetch_row', 'mysqli_fetch_array'))
        );
    }
}
