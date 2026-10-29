<?php

declare(strict_types=1);

namespace LaIOT\Console\Commands;

use LaIOT\Services\Modules\ModuleManifest;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

#[AsCommand(name: 'module:validate', description: 'Проверить все module.json')]
final class ModuleValidateCommand extends Command
{
    public function __construct(private readonly string $root)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $errors = 0;
        $checked = 0;
        foreach ([['/modules/core', 'core'], ['/modules', 'module']] as [$directory, $type]) {
            $base = $this->root . $directory;
            foreach (glob($base . '/*/module.json') ?: [] as $file) {
                if ($type === 'module' && dirname($file) === $this->root . '/modules/core') {
                    continue;
                }
                $checked++;
                try {
                    $manifest = ModuleManifest::fromFile($file, dirname($file), $type);
                    $output->writeln('<info>ok</info> ' . $manifest->id() . ' (' . $manifest->type . ')');
                } catch (Throwable $error) {
                    $errors++;
                    $output->writeln('<error>fail</error> ' . $file . ': ' . $error->getMessage());
                }
            }
        }

        $output->writeln('Проверено: ' . $checked . ', ошибок: ' . $errors);

        return $errors === 0 ? Command::SUCCESS : Command::FAILURE;
    }
}
