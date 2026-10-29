<?php

declare(strict_types=1);

namespace MajorDoMo\Http;

use Illuminate\Http\Request;
use Throwable;

/**
 * Данные HTML-страницы ошибки.
 *
 * Копия и маскот выбираются по коду ответа, а не по английскому тексту
 * kernel: JSON для API остаётся прежним. Страница не читает БД и не собирает
 * меню, поэтому открывается и тогда, когда сломаны база или сборка оболочки.
 *
 * Изменения:
 * 2026-10-29 — некорректный запрос получает понятную страницу с кодом 400.
 * 2026-10-28 — страницы 404/405/500/503 с маскотом, путём и кодом запроса.
 */
final class ErrorPage
{
    /**
     * @return array{status:int,code:string,title:string,text:string,mascot:string,tone:string,path:string,requestId:string,debug:?string,home:?string,retry:bool,back:bool}
     */
    public static function data(
        Request $request,
        int $status,
        string $code,
        string $requestId,
        ?Throwable $error = null
    ): array {
        [$title, $text, $mascot, $tone] = match ($status) {
            400 => [
                'Некорректный запрос',
                'Данные запроса имеют неверный формат. Вернитесь к форме и повторите действие.',
                'error',
                'warning',
            ],
            404 => [
                'Страница не найдена',
                'Такой страницы нет или она была перемещена. Проверьте адрес или вернитесь в панель.',
                'find',
                'primary',
            ],
            405 => [
                'Метод не поддерживается',
                'Этот адрес не принимает такой способ запроса. Вернитесь в панель и повторите действие.',
                'error',
                'warning',
            ],
            500 => [
                'Внутренняя ошибка',
                'Сервер не смог обработать запрос. Ошибка записана в журнал — повторите попытку или вернитесь в панель.',
                'error',
                'danger',
            ],
            503 => [
                'Идут технические работы',
                'Панель временно недоступна. Обычно это занимает несколько минут — попробуйте обновить страницу позже.',
                'work',
                'warning',
            ],
            default => [
                'Ошибка ' . $status,
                'Запрос не удалось выполнить. Вернитесь в панель и попробуйте ещё раз.',
                'error',
                'danger',
            ],
        };

        return [
            'status' => $status,
            'code' => $code,
            'title' => $title,
            'text' => $text,
            'mascot' => $mascot,
            'tone' => $tone,
            'path' => $request->getPathInfo(),
            'requestId' => $requestId,
            // Текст исключения попадает на страницу только в debug:
            // в обычном режиме он остаётся в журнале.
            'debug' => $error?->getMessage(),
            // Во время обслуживания /admin снова отдаст 503, поэтому
            // единственное осмысленное действие — повторить запрос.
            'home' => $status === 503 ? null : '/admin',
            'retry' => in_array($status, [500, 503], true),
            'back' => in_array($status, [404, 405], true),
        ];
    }
}
