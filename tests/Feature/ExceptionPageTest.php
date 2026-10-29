<?php

declare(strict_types=1);

namespace MajorDoMo\Tests\Feature;

use Illuminate\Contracts\View\Factory;
use Illuminate\Http\Request;
use MajorDoMo\Http\ErrorResponse;
use MajorDoMo\Http\ExceptionPage;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Изолированные проверки страницы без bootstrap, БД и внешних сервисов.
 *
 * Изменения:
 * 2026-10-29 — стек, причины, экранирование и скрытие деталей вне debug.
 */
final class ExceptionPageTest extends TestCase
{
    public function testDebugShowsSourceAndPreviousExceptionWithoutRequestSecrets(): void
    {
        $private = bin2hex(random_bytes(12));
        $previous = new RuntimeException('previous failure');
        $error = new RuntimeException('<script>alert("failure")</script>', 0, $previous);
        $request = Request::create('/broken?token=' . $private, 'POST', ['password' => $private]);
        $request->headers->set('Authorization', 'Bearer ' . $private);
        $view = $this->createMock(Factory::class);
        $view->expects(self::never())->method('make');
        $response = ErrorResponse::make($request, 500, 'server_error', 'Internal Server Error', 'req-debug', true, $error, $view);
        $html = (string)$response->getContent();

        self::assertSame(500, $response->getStatusCode());
        self::assertStringContainsString('Трассировка', $html);
        self::assertStringContainsString('ExceptionPageTest.php', $html);
        self::assertStringContainsString('md-exception__line is-error', $html);
        self::assertStringContainsString('previous failure', $html);
        self::assertStringContainsString('&lt;script&gt;alert', $html);
        self::assertStringNotContainsString('<script>alert', $html);
        self::assertStringContainsString('POST /broken', $html);
        self::assertStringNotContainsString('POST /broken?token=', $html);
        self::assertStringNotContainsString($private, $html);
        self::assertSame(1, preg_match('/<textarea\b[^>]*>(.*?)<\/textarea>/su', $html, $report));
        $text = html_entity_decode($report[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        self::assertStringContainsString('Запрос: POST /broken', $text);
        self::assertStringContainsString('Request ID: req-debug', $text);
        self::assertStringContainsString('Исходник:', $text);
        self::assertStringContainsString('Трассировка:', $text);
        self::assertStringContainsString('Причина: RuntimeException: previous failure', $text);
        self::assertStringNotContainsString($private, $text);
    }

    public function testProductionFallbackDoesNotReadOrExposeException(): void
    {
        $view = $this->createMock(Factory::class);
        $view->method('make')->willThrowException(new RuntimeException('broken blade'));
        $response = ErrorResponse::make(
            Request::create('/broken'),
            500,
            'server_error',
            'Internal Server Error',
            'req-production',
            false,
            new RuntimeException('private failure'),
            $view
        );
        $html = (string)$response->getContent();

        self::assertStringContainsString('Внутренняя ошибка', $html);
        self::assertStringContainsString('req-production', $html);
        self::assertStringContainsString('/assets/img/maskot/error.png', $html);
        foreach (['private failure', 'RuntimeException', 'ExceptionPageTest.php', 'Трассировка', 'broken blade', '<textarea', 'Скопировать для ИИ'] as $private) {
            self::assertStringNotContainsString($private, $html);
        }
    }

    public function testJsonContractStillContainsOnlyDebugMessage(): void
    {
        $response = ErrorResponse::make(
            Request::create('/broken', server: ['HTTP_ACCEPT' => 'application/json']),
            500,
            'server_error',
            'Internal Server Error',
            'req-json',
            true,
            new RuntimeException('json detail')
        );
        self::assertSame([
            'ok' => false,
            'error' => ['code' => 'server_error', 'message' => 'Internal Server Error', 'debug' => 'json detail'],
            'request_id' => 'req-json',
        ], json_decode((string)$response->getContent(), true));
    }

    public function testSourceOutsideProjectIsNotRead(): void
    {
        $html = ExceptionPage::render(new RuntimeException('outside project'), true, 'req-source', '/', 'GET', sys_get_temp_dir());

        self::assertStringContainsString('Фрагмент исходника недоступен', $html);
        self::assertStringNotContainsString('md-exception__line-number', $html);
    }
}
