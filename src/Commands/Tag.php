<?php

namespace Phabel\Commands;

use Exception;
use Phabel\Cli\Formatter;
use Phabel\Version;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class Tag extends Command
{
    protected static $defaultName = 'tag';

    protected function configure(): void
    {
        $tags = new Process(['git', 'describe', '--tags', '--abbrev=0']);
        $tags->run();
        $tag = $tags->isSuccessful() ? \trim($tags->getOutput()) : null;

        $this
            ->setDescription('Transpile a release.')
            ->setHelp('This command transpiles the specified (or the latest) git tag.')

            ->addOption("remote", 'r', InputOption::VALUE_OPTIONAL, 'Remote where to push tags', 'origin')
            ->addArgument('source', $tag ? InputArgument::OPTIONAL : InputArgument::REQUIRED, 'Source tag name', $tag);
    }

    private function exec(array $command): string
    {
        $proc = new Process($command);
        $proc->run();
        if (!$proc->isSuccessful()) {
            throw new ProcessFailedException($proc);
        }
        return $proc->getOutput();
    }

    private function prepare(string $src, string $dest, callable $cb): void
    {
        $this->exec(['git', 'checkout', $src]);
        $message = $this->exec(['git', 'log', '--format=%B', '-n', '1', $src]);

        if (!\file_exists('composer.json')) {
            throw new Exception("composer.json doesn't exist!");
        }

        $json = \json_decode(\file_get_contents('composer.json'), true);
        $json = $cb($json);
        \file_put_contents('composer.json', \json_encode($json, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));

        $this->exec(['git', 'commit', '-am', $message."\nRelease transpiled using https://phabel.io, the PHP transpiler"]);
        $this->exec(['git', 'tag', $dest]);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $src = $input->getArgument('source');
        $remote = $input->getOption('remote');
        $output->setFormatter(Formatter::getFormatter());

        $branch = \trim($this->exec(["git", 'rev-parse', '--abbrev-ref', 'HEAD']));
        $stashed = \trim($this->exec(['git', 'stash'])) !== 'No local changes to save';

        $output->write("<phabel>Tagging transpiled release <bold>$src.9998</bold>...</phabel>".PHP_EOL);
        $this->prepare($src, "$src.9998", function (array $json): array {
            $json['phabel'] ??= [];
            $json['phabel']['extra'] ??= [];
            $json['phabel']['extra']['require'] = $json['require'];
            $json['require'] = [
                'phabel/phabel' => Version::VERSION,
                'php' => '>=5.6'
            ];
            return $json;
        });
        $output->write("<phabel>Tagging original release as <bold>$src.9999</bold>...</phabel>".PHP_EOL);
        $this->prepare($src, "$src.9999", fn (array $json): array => $json);

        $this->exec(['git', 'checkout', $branch]);
        if ($stashed) {
            $this->exec(['git', 'stash', 'pop']);
        }

        $output->write("<phabel>Pushing <bold>$src.9998</bold>, <bold>$src.9999</bold> to <bold>$remote</bold>...</phabel>".PHP_EOL);
        $this->exec(['git', 'push', $remote, "$src.9998", "$src.9999"]);

        $output->write("<phabel>Done!</phabel>
<phabel>Tell users to require <bold>^$src</bold> in their <bold>composer.json</bold> to automatically load the correct transpiled version!</phabel>

<bold>Tip</bold>: Add the following badge to your README to let users know about your minimum supported PHP version, as it won't be shown on packagist.
<phabel>![phabel.io](https://phabel.io/badge/5.6)</phabel>
");

        return Command::SUCCESS;
    }
}
