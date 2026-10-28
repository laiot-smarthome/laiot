<?php

declare(strict_types=1);

namespace MajorDoMo\Tests\Feature;

use MajorDoMo\Console\ConsoleFactory;
use MajorDoMo\Foundation\Application;
use MajorDoMo\Http\Session\ArraySession;
use PHPUnit\Framework\TestCase;

/**
 * Справочник сверяется с рабочим реестром без выполнения CLI-команд.
 *
 * 2026-10-28 — защита от недокументированных команд и параметров.
 */
final class ConsoleDocumentationTest extends TestCase
{
    public function testEveryCommandAndParameterIsDocumented(): void
    {
        $root = dirname(__DIR__, 2);
        $app = new Application($root, [
            'log' => 'php://stderr',
            'session' => new ArraySession(),
            'cache' => ['driver' => 'array'],
            'database' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
        ]);
        $app->boot();
        $console = ConsoleFactory::make($app);
        $markdown = str_replace("\r\n", "\n", (string)file_get_contents($root . '/docs/mydomo/console.md'));
        preg_match_all('/^### ([a-z][a-z:-]*)$/m', $markdown, $headings);
        $documented = $headings[1];
        $registered = [];
        foreach ($console->all() as $name => $command) {
            if ($command->isHidden()) {
                continue;
            }
            $registered[] = $name;
            self::assertContains($name, $documented, 'Нет документации команды ' . $name);
            preg_match('/^### ' . preg_quote($name, '/') . '\n(.*?)(?=^### |^## |\z)/ms', $markdown, $section);
            $body = $section[1] ?? '';
            self::assertStringContainsString('php bin/mydomo ' . $name, $body);
            if (str_starts_with($command::class, 'MajorDoMo\\')) {
                self::assertStringContainsString($command->getDescription(), $body);
            }
            $parameters = [];
            foreach ($command->getDefinition()->getArguments() as $argument) {
                $parameters[] = $argument->getName();
                self::assertStringContainsString('| `' . $argument->getName() . '` |', $body);
                $default = $argument->getDefault();
                self::assertMatchesRegularExpression(
                    '/^\| `' . preg_quote($argument->getName(), '/') . '` \| .* \| '
                    . ($argument->isRequired() ? 'да' : 'нет') . ' \| '
                    . preg_quote($default === null ? '—' : (string)$default, '/') . ' \|$/m',
                    $body
                );
            }
            foreach ($command->getDefinition()->getOptions() as $option) {
                $parameters[] = '--' . $option->getName();
                self::assertStringContainsString('| `--' . $option->getName() . '` |', $body);
                $default = $option->getDefault();
                self::assertMatchesRegularExpression(
                    '/^\| `--' . preg_quote($option->getName(), '/') . '` \| .* \| нет \| '
                    . preg_quote($default === null ? '—' : ($default === false ? 'нет' : (string)$default), '/') . ' \|$/m',
                    $body
                );
            }
            preg_match_all('/^\| `([^`]+)` \|/m', $body, $rows);
            self::assertEqualsCanonicalizing($parameters, $rows[1], 'Параметры ' . $name . ' устарели');
        }
        self::assertEqualsCanonicalizing($registered, $documented);
    }
}
