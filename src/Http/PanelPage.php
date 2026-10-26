<?php

declare(strict_types=1);

namespace MajorDoMo\Http;

/**
 * Страница нового ядра внутри alpha-layout.
 *
 * Модуль/контроллер отдаёт title, готовый HTML и активный пункт меню;
 * оболочка, пользователь, CSRF и меню остаются у ядра.
 */
final readonly class PanelPage
{
    /**
     * @param list<array{label:string,items:list<array{id:string,label:string,href:string,icon?:string}>}> $menu
     */
    public function __construct(
        public string $title,
        public string $content,
        public string $active = '',
        public array $menu = []
    ) {
    }
}
