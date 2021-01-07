<?php

namespace Phabel\Target\Php70;

use Phabel\Context;
use Phabel\Plugin;
use Phabel\RootNode;
use PhpParser\Node;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\StaticPropertyFetch;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Const_;

/**
 * Converts define() arrays into const arrays.
 */
class DefineArrayReplacer extends Plugin
{
    public static array $constants = [];
    private array $toReplace = [];
    /**
     * Convert define() arrays into const arrays.
     *
     * @param FuncCall $node Node
     *
     * @return Const_|Assign|null
     */
    public function enter(FuncCall $node, Context $context)
    {
        if (!$node->name instanceof Name || $node->name->toString() != 'define') {
            return null;
        }

        $nameNode = $node->args[0]->value;
        $valueNode = $node->args[1]->value;

        if (!$valueNode instanceof Node\Expr\Array_) {
            return null;
        }

        if (!$context->parents->top() instanceof RootNode) {
            $this->toReplace[$nameNode->value] = true;
            return new Assign(
                new ArrayDimFetch(
                    new StaticPropertyFetch(
                        new FullyQualified(self::class),
                        'constants'
                    ),
                    new String_($nameNode->value)
                ),
                $valueNode
            );
        }

        $constNode = new Node\Const_($nameNode->value, $valueNode);

        return new Node\Stmt\Const_([$constNode]);
    }
    public function enterConst(ConstFetch $const): ?ArrayDimFetch
    {
        if (!isset($this->toReplace[$const->name->toString()])) {
            return new ArrayDimFetch(
                new StaticPropertyFetch(
                    new FullyQualified(self::class),
                    'constants'
                ),
                new String_($const->name->toString())
            );
        }
        return null;
    }
}
