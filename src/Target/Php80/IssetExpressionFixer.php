<?php

namespace Phabel\Target\Php80;

use Phabel\Plugin;
use Phabel\Plugin\IssetExpressionFixer as fixer;

class IssetExpressionFixer extends Plugin
{
    public static function next(array $config): array
    {
        return [
            fixer::class => [
  'PhpParser\\Node\\Expr\\ArrayDimFetch' =>
  [
    'var' =>
    [
      'PhpParser\\Node\\Expr\\Throw_' => true,
    ],
    'dim' =>
    [
      'PhpParser\\Node\\Expr\\Throw_' => true,
    ],
  ],
  'PhpParser\\Node\\Expr\\PropertyFetch' =>
  [
    'var' =>
    [
      'PhpParser\\Node\\Expr\\ConstFetch' => true,
      'PhpParser\\Node\\Expr\\Throw_' => true,
    ],
    'name' =>
    [
      'PhpParser\\Node\\Expr\\Throw_' => true,
    ],
  ],
  'PhpParser\\Node\\Expr\\StaticPropertyFetch' =>
  [
    'name' =>
    [
      'PhpParser\\Node\\Expr\\Throw_' => true,
    ],
  ],
  'PhpParser\\Node\\Expr\\Variable' =>
  [
    'name' =>
    [
      'PhpParser\\Node\\Expr\\Throw_' => true,
    ],
  ],
]
        ];
    }
}
