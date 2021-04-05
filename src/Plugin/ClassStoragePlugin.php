<?php

namespace Phabel\Plugin;

use Phabel\ClassStorage;
use Phabel\ClassStorage\Builder;
use Phabel\ClassStorage\Storage;
use Phabel\ClassStorageProvider;
use Phabel\Context;
use Phabel\Plugin;
use Phabel\RootNode;
use PhpParser\Builder\Class_;
use PhpParser\Builder\Method;
use PhpParser\Builder\Param;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Trait_;
use ReflectionClass;

final class ClassStoragePlugin extends Plugin
{
    /**
     * Storage.
     *
     * @var array<string, array<string, Builder>>
     */
    public array $classes = [];
    /**
     * Storage.
     *
     * @var array<string, array<string, Builder>>
     */
    public array $traits = [];

    /**
     * Count.
     */
    private array $count = [];
    /**
     * Plugins to call during final iteration.
     *
     * @var array<class-string<ClassStorageProvider>, true>
     */
    private array $finalPlugins = [];

    /**
     * Set configuration array.
     *
     * @param array $config
     * @return void
     */
    public function setConfigArray(array $config): void
    {
        $this->finalPlugins += $config;
    }

    /**
     * Enter file.
     *
     * @param RootNode $_
     * @return void
     */
    public function enterRoot(RootNode $_, Context $context): void
    {
        $file = $context->getOutputFile();
        $this->count[$file] = [];
        foreach ($this->traits as $trait => $traits) {
            if (isset($traits[$file])) {
                unset($this->traits[$trait][$file]);
            }
        }
        foreach ($this->classes as $class => $classes) {
            if (isset($classes[$file])) {
                unset($this->classes[$class][$file]);
            }
        }/*
        if (!isset($this->classes[''])) {
            foreach (get_declared_classes() as $class) {
                $refl = new ReflectionClass($class);
                if (!$refl->getExtension()) {
                    continue;
                }
                $class = new Class_($class);
                if ($extends = $refl->getParentClass()) {
                    $class->extend($extends->getName());
                }
                $class->implement(...$refl->getInterfaceNames());
                foreach ($refl->getMethods() as $method) {
                    $builder = new Method($method->name);
                    foreach ($method->getParameters() as $parameter) {
                        $param = new Param($parameter->name);
                        if ($type = $parameter->getType()) {
                            $param->setType((string) $parameter->getType());
                        }
                        if ($parameter->isVariadic()) {
                            $param->makeVariadic();
                        }
                        if ($parameter->isPassedByReference()) {
                            $param->makeByRef();
                        }
                        $builder->addParam($param->getNode());
                    }
                    if ($method->isAbstract()) {
                        $builder->makeAbstract();
                    }
                    if ($method->isFinal()) {
                        $builder->makeFinal();
                    }
                    if ($method->isPrivate()) {
                        $builder->makePrivate();
                    }
                    if ($method->isProtected()) {
                        $builder->makeProtected();
                    }
                    if ($method->isPublic()) {
                        $builder->makePublic();
                    }
                    if ($method->isStatic()) {
                        $builder->makeStatic();
                    }
                    $class->addStmt($builder->getNode());
                }
                $this->classes[$refl->getName()][''] = new Builder($class->getNode());
            }
        }*/
    }
    /**
     * Add method.
     *
     * @param ClassLike $class
     *
     * @return void
     */
    public function enter(ClassLike $class, Context $context): void
    {
        $file = $context->getOutputFile();
        if ($class->name) {
            $name = self::getFqdn($class);
        } else {
            $name = "class@anonymous$file";
            $this->count[$file][$name] ??= 0;
            $name .= "@".$this->count[$file][$name]++;
        }

        $class = clone $class;
        $stmts = [];
        foreach ($class->stmts as $stmt) {
            if (!$stmt instanceof ClassMethod) {
                continue;
            }
            $stmts []= $stmt;
        }
        $class->stmts = $stmts;
        $class->setAttribute(ClassStorage::FILE_KEY, $file);

        if ($class instanceof Trait_) {
            $this->traits[$name][$file] = new Builder($class);
        } else {
            $this->classes[$name][$file] = new Builder($class, $name);
        }
    }

    /**
     * Merge storage with another.
     *
     * @param self $other
     * @return void
     */
    public function merge($other): void
    {
        $this->classes = \array_merge_recursive($this->classes, $other->classes);
        $this->traits = \array_merge_recursive($this->traits, $other->traits);
        $this->finalPlugins += $other->finalPlugins;
    }

    /**
     * Resolve all classes, optionally fixing up a few methods.
     *
     * @return array Config to pass to new Traverser instance
     */
    public function finish(): array
    {
        $storage = new ClassStorage($this);
        $changed = false;
        foreach ($this->finalPlugins as $class => $_) {
            if ($class::processClassGraph($storage)) {
                $changed = true;
            }
        }
        $result = \array_fill_keys(\array_keys($this->finalPlugins), [ClassStorage::class => $storage]);
        if ($changed) {
            $result[self::class] = $this->finalPlugins;
        }
        return $result;
    }
}
