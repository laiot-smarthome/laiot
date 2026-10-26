<?php

declare(strict_types=1);

namespace MajorDoMo\Http\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Короткие конструкторы ответов для контроллеров.
 *
 * Трейт не содержит состояния и не зависит от контейнера: его можно
 * использовать в базовом Controller и в небольших обработчиках.
 */
trait CreatesResponses
{
    /**
     * @param array<string, string> $headers
     */
    protected function json(mixed $data, int $status = 200, array $headers = []): JsonResponse
    {
        return new JsonResponse($data, $status, $headers);
    }

    /**
     * @param array<string, string> $headers
     */
    protected function html(string $content, int $status = 200, array $headers = []): Response
    {
        return new Response($content, $status, $headers + ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    /**
     * @param array<string, string> $headers
     */
    protected function text(string $content, int $status = 200, array $headers = []): Response
    {
        return new Response($content, $status, $headers + ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    protected function noContent(int $status = 204): Response
    {
        return new Response('', $status);
    }
}
