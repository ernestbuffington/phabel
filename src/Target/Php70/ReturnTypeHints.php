<?php

namespace Phabel\Target\Php70;

use Phabel\Plugin;
use Phabel\Plugin\TypeHintReplacer;

/**
 * @author Daniil Gentili <daniil@daniil.it>
 * @license MIT
 */
class ReturnTypeHints extends Plugin
{
    /**
     * Alias.
     *
     * @return array
     */
    public static function runAfter(array $config): array
    {
        return [
            TypeHintReplacer::class => [
                'return' => true
            ]
        ];
    }
}