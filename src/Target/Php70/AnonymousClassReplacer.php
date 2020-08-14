<?php

namespace Phabel\Target\Php70;

use Phabel\Context;
use Phabel\Plugin;
use Phabel\RootNode;
use PhpParser\Node;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Stmt\Namespace_;

class AnonymousClassReplacer extends Plugin
{
    /**
     * Anonymous class count.
     */
    private int $count = 0;
    /**
     * Current file name hash.
     */
    private string $fileName = '';

    /**
     * {@inheritDoc}
     */
    public function shouldRunFile(string $file): bool
    {
        $this->fileName = \hash('sha256', $file);
        return parent::shouldRunFile($file);
    }

    /**
     * Leave new.
     *
     * @param New_    $node New stmt
     * @param Context $ctx  Context
     *
     * @return void
     */
    public function leaveNew(New_ $node, Context $ctx): void
    {
        $classNode = $node->class;
        if (!$classNode instanceof Node\Stmt\Class_) {
            return;
        }

        $classNode->name = 'PhabelAnonymousClass'.$this->fileName.($this->count++);

        $node->class = new Node\Expr\ConstFetch(
            new Node\Name($classNode->name)
        );

        $prevNode = $node;
        foreach ($ctx->parents as $node) {
            if ($node instanceof Namespace_ || $node instanceof RootNode) {
                $foundIndex = -1;
                foreach ($node->stmts as $index => $curNode) {
                    if ($curNode === $prevNode) {
                        $foundIndex = $index;
                        break;
                    }
                }
                if ($foundIndex >= 0) {
                    \array_splice($node->stmts, $foundIndex, 0, [$classNode]);
                    return;
                }
            }
            $prevNode = $node;
        }
        throw new \RuntimeException('Could not find hook for inserting anonymous class!');
    }
}
