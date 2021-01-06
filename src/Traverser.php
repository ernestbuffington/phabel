<?php

namespace Phabel;

use PhpParser\Node;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;
use SplQueue;

/**
 * AST traverser.
 *
 * @author Daniil Gentili <daniil@daniil.it>
 * @license MIT
 */
class Traverser
{
    /**
     * Plugin queue.
     *
     * @return SplQueue<SplQueue<Plugin>>
     */
    private SplQueue $queue;
    /**
     * Parser instance.
     */
    private Parser $parser;
    /**
     * Plugin queue for specific package.
     *
     * @return SplQueue<SplQueue<Plugin>>|null
     */
    private ?SplQueue $packageQueue;
    /**
     * Current file
     */
    private ?string $file;
    /**
     * Generate traverser from basic plugin instances.
     *
     * @param Plugin ...$plugin Plugins
     *
     * @return self
     */
    public static function fromPlugin(Plugin ...$plugin): self
    {
        $queue = new SplQueue;
        foreach ($plugin as $p) {
            $queue->enqueue($p);
        }
        $final = new SplQueue;
        $final->enqueue($queue);
        return new self($final);
    }
    /**
     * AST traverser.
     *
     * @return SplQueue<SplQueue<Plugin>> $queue Plugin queue
     */
    public function __construct(SplQueue $queue = null)
    {
        $this->queue = $queue ?? new SplQueue;
        $this->parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);
    }
    /**
     * Set package name.
     *
     * @param string $package Package name
     *
     * @return void
     */
    public function setPackage(string $package): void
    {
        $this->packageQueue = new SplQueue;
        $newQueue = new SplQueue;
        foreach ($this->queue as $queue) {
            if ($newQueue->count()) {
                $this->packageQueue->enqueue($newQueue);
                $newQueue = new SplQueue;
            }
            /** @var Plugin */
            foreach ($queue as $plugin) {
                if ($plugin->shouldRun($package)) {
                    $newQueue->enqueue($plugin);
                }
            }
        }
        if ($newQueue->count()) {
            $this->packageQueue->enqueue($newQueue);
        }
    }
    /**
     * Traverse AST of file.
     *
     * @param string $file File
     * @param string $output Output file
     *
     * @return void
     */
    public function traverse(string $file, string $output): void
    {
        /** @var SplQueue<SplQueue<Plugin>> */
        $reducedQueue = new SplQueue;
        $newQueue = new SplQueue;
        foreach ($this->packageQueue ?? $this->queue as $queue) {
            if ($newQueue->count()) {
                $reducedQueue->enqueue($newQueue);
                $newQueue = new SplQueue;
            }
            /** @var Plugin */
            foreach ($queue as $plugin) {
                if ($plugin->shouldRunFile($file)) {
                    $newQueue->enqueue($plugin);
                }
            }
        }
        if ($newQueue->count()) {
            $reducedQueue->enqueue($newQueue);
        } elseif (!$reducedQueue->count()) {
            return;
        }

        $this->file = $file;
        $ast = new RootNode($this->parser->parse(\file_get_contents($file)) ?? []);
        $this->traverseAstInternal($ast, $reducedQueue);
        $printer = new Standard();
        \file_put_contents($output, $printer->prettyPrintFile($ast->stmts));
    }
    /**
     * Traverse AST.
     *
     * @param Node     $node        Initial node
     * @param SplQueue $pluginQueue Plugin queue (optional)
     *
     * @return Context
     */
    public function traverseAst(Node &$node, SplQueue $pluginQueue = null): Context
    {
        $this->file = null;
        $n = new RootNode([&$node]);
        return $this->traverseAstInternal($n, $pluginQueue);
    }
    /**
     * Traverse AST.
     *
     * @param RootNode &$node        Initial node
     * @param SplQueue $pluginQueue Plugin queue (optional)
     *
     * @return Context
     */
    private function traverseAstInternal(RootNode &$node, SplQueue $pluginQueue = null): Context
    {
        try {
            foreach ($pluginQueue ?? $this->packageQueue ?? $this->queue as $queue) {
                $context = new Context($this->file);
                $context->push($node);
                $this->traverseNode($node, $queue, $context);
            }
        } catch (\Throwable $e) {
            throw new Exception($e->getMessage(), $e->getLine(), null, $this->file, $context->getCurrentChild($context->parents[0])->getStartLine());
        }
        return $context;
    }
    /**
     * Traverse node.
     *
     * @param Node             &$node   Node
     * @param SplQueue<Plugin> $plugins Plugins
     * @param Context          $context Context
     *
     * @return void
     */
    private function traverseNode(Node &$node, SplQueue $plugins, Context $context): void
    {
        foreach ($plugins as $plugin) {
            foreach (PluginCache::enterMethods(\get_class($plugin)) as $type => $methods) {
                if (!$node instanceof $type) {
                    continue;
                }
                foreach ($methods as $method) {
                    $result = $plugin->{$method}($node, $context);
                    if ($result instanceof Node) {
                        if (!$result instanceof $node) {
                            $node = $result;
                            continue 2;
                        }
                        $node = $result;
                    }
                }
            }
        }
        $context->push($node);
        foreach ($node->getSubNodeNames() as $name) {
            $node->setAttribute('currentNode', $name);

            $subNode = &$node->{$name};
            if (\is_array($subNode)) {
                for ($index = 0; $index < \count($subNode);) {
                    $node->setAttribute('currentNodeIndex', $index);
                    if ($subNode[$index] instanceof Node) {
                        $this->traverseNode($subNode[$index], $plugins, $context);
                    }
                    $index = $node->getAttribute('currentNodeIndex');
                    do {
                        $index++;
                    } while (\in_array($index, $node->getAttribute('skipNodes', [])));
                }
                $node->setAttribute('skipNodes', []);
            } elseif ($subNode instanceof Node) {
                $this->traverseNode($subNode, $plugins, $context);
            }
        }
        $context->pop();
        foreach ($plugins as $plugin) {
            foreach (PluginCache::leaveMethods(\get_class($plugin)) as $type => $methods) {
                if (!$node instanceof $type) {
                    continue;
                }
                foreach ($methods as $method) {
                    $result = $plugin->{$method}($node, $context);
                    if ($result instanceof Node) {
                        if (!$result instanceof $node) {
                            $node = $result;
                            continue 2;
                        }
                        $node = $result;
                    }
                }
            }
        }
    }
}
