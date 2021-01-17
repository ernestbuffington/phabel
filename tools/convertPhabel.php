<?php

use Phabel\Target\Php;
use Phabel\Traverser;

if (!\file_exists('composer.json')) {
    echo "This script must be run from package root".PHP_EOL;
    die(1);
}
require 'vendor/autoload.php';

if ($argc !== 2) {
    $help = <<<EOF
Usage: {$argv[0]} target [ dry ]

target - Target version
dry - 0 or 1, whether to dry-run conversion

EOF;
    echo $help;
    die(1);
}
$target = $argv[1];
$dry = (bool) ($argv[2] ?? '');

if (!\file_exists('phabelConverted')) {
    \mkdir('phabelConverted');
}

\passthru("git stash");

foreach ($target === 'all' ? Php::VERSIONS : [$target] as $target) {
    $coverage = \getenv('PHABEL_COVERAGE') ?: '';
    if ($coverage) {
        $coverage .= "-$target";
    }

    $packages = [];
    foreach (['tools', 'src', 'bin'] as $dir) {
        if (!\file_exists($dir)) {
            continue;
        }
        $packages += Traverser::run([Php::class => ['target' => $target]], $dir, $dir, $coverage);
    }

    if (!empty($packages)) {
        $cmd = "composer require ";
        foreach ($packages as $package => $constraint) {
            $cmd .= \escapeshellarg("{$package}:{$constraint}")." ";
        }
        \passthru($cmd);
    }

    if (!$dry) {
        $branch = \trim(\shell_exec("git rev-parse --abbrev-ref HEAD"));
        $oldHash = \trim(\shell_exec("git log -1 --pretty=%H"));

        \passthru("git add -A");
        \passthru("git commit -m ".\escapeshellarg("Convert to $target"));

        $hash = \trim(\shell_exec("git log -1 --pretty=%H"));
        \passthru("git push -f origin ".\escapeshellarg("$hash:refs/heads/{$branch}-{$target}"));
        \passthru("git reset $oldHash");
    }
    \passthru("git reset --hard");
}

\passthru("git stash pop");
