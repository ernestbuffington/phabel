<?php

namespace Phabel\Target\Php71;

use Phabel\Plugin;
use Phabel\Plugin\TypeHintStripper;

/**
 * @author Daniil Gentili <daniil@daniil.it>
 * @license MIT
 */
class VoidReturnType extends Plugin
{
    /**
     * Remove void return typehint.
     *
     * @return array
     */
    public static function runAfter(): array
    {
        return [
            TypeHintStripper::class => [
                'void' => true
            ]
        ];
    }
}
