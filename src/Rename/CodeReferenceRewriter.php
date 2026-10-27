<?php

declare(strict_types=1);

namespace MajorDoMo\Rename;

/**
 * Переписывание ссылок на имена в пользовательском PHP.
 *
 * 2026-10-27 — при переименовании объекта, свойства или метода нужно
 * сохранить работающий код сценариев, методов, webhook и таймеров. Меняем
 * только строковые литералы (и узкие вызовы `$this->get/set/history/call`),
 * поэтому комментарии и произвольный текст не затрагиваются.
 */
final class CodeReferenceRewriter
{
    /**
     * Ссылки на объект: точное имя и префикс `Object.`.
     *
     * @return array{code:string,count:int}
     */
    public function object(string $code, string $old, string $new): array
    {
        return $this->rewriteLiterals($code, static function (string $literal) use ($old, $new): string {
            if ($literal === $old) {
                return $new;
            }

            return str_replace($old . '.', $new . '.', $literal);
        });
    }

    /**
     * Ссылки на свойство: `Object.Property` для объектов области и
     * `$this->get/set/history('Property')` в методах области.
     *
     * @param list<string> $objects
     * @return array{code:string,count:int}
     */
    public function property(string $code, array $objects, string $old, string $new, bool $selfCalls = true): array
    {
        $objects = $this->names($objects);
        $result = $this->rewriteLiterals(
            $code,
            function (string $literal) use ($objects, $old, $new): string {
                foreach ($objects as $object) {
                    $literal = str_replace($object . '.' . $old, $object . '.' . $new, $literal);
                }

                return $literal;
            }
        );
        $code = $result['code'];
        $count = $result['count'];

        if ($selfCalls) {
            [$code, $selfCount] = $this->replaceSelfCall($code, '(?:get|set|history)', $old, $new);
            $count += $selfCount;
        }

        return ['code' => $code, 'count' => $count];
    }

    /**
     * Ссылки на метод: `Object.Method`, `callMethod('Object', 'Method')` и
     * `$this->call('Method')` в методах области.
     *
     * @param list<string> $objects
     * @return array{code:string,count:int}
     */
    public function method(string $code, array $objects, string $old, string $new, bool $selfCalls = true): array
    {
        $objects = $this->names($objects);
        $result = $this->rewriteLiterals(
            $code,
            function (string $literal) use ($objects, $old, $new): string {
                foreach ($objects as $object) {
                    $literal = str_replace($object . '.' . $old, $object . '.' . $new, $literal);
                }

                return $literal;
            }
        );
        $code = $result['code'];
        $count = $result['count'];

        [$code, $secondArgumentCount] = $this->replaceCallMethodArgument($code, $objects, $old, $new);
        $count += $secondArgumentCount;
        if ($selfCalls) {
            [$code, $selfCount] = $this->replaceSelfCall($code, 'call', $old, $new);
            $count += $selfCount;
        }

        return ['code' => $code, 'count' => $count];
    }

    /**
     * Точное имя класса в строковом литерале (класс не участвует в
     * `getGlobal`, поэтому замена намеренно узкая).
     *
     * @return array{code:string,count:int}
     */
    public function className(string $code, string $old, string $new): array
    {
        return $this->rewriteLiterals($code, static fn (string $literal): string => $literal === $old ? $new : $literal);
    }

    /**
     * @param callable(string):string $transform
     * @return array{code:string,count:int}
     */
    private function rewriteLiterals(string $code, callable $transform): array
    {
        $hasOpenTag = preg_match('/^\s*<\?/', $code) === 1;
        $tokens = token_get_all($hasOpenTag ? $code : '<?php ' . $code);
        $count = 0;
        $output = '';
        $skipOpenTag = !$hasOpenTag;
        foreach ($tokens as $token) {
            if ($skipOpenTag && is_array($token) && $token[0] === T_OPEN_TAG) {
                $skipOpenTag = false;
                continue;
            }
            $skipOpenTag = false;
            if (!is_array($token)) {
                $output .= $token;
                continue;
            }
            if ($token[0] !== T_CONSTANT_ENCAPSED_STRING || strlen($token[1]) < 2) {
                $output .= $token[1];
                continue;
            }

            $text = $token[1];
            $quote = $text[0];
            $literal = substr($text, 1, -1);
            $rewritten = $transform($literal);
            if ($rewritten !== $literal) {
                $count++;
                $text = $quote . $rewritten . $quote;
            }
            $output .= $text;
        }

        return ['code' => $output, 'count' => $count];
    }

    /**
     * `$this->get('old')`, `set`, `history` или `call` — только строковый
     * литерал первым аргументом.
     *
     * @return array{0:string,1:int}
     */
    private function replaceSelfCall(string $code, string $methods, string $old, string $new): array
    {
        $pattern = '/(\$this\s*->\s*' . $methods . '\s*\(\s*)([\'"])(?:'
            . preg_quote($old, '/') . ')\2/u';
        $count = 0;
        $result = preg_replace_callback(
            $pattern,
            static function (array $match) use ($new, &$count): string {
                $count++;

                return $match[1] . $match[2] . $new . $match[2];
            },
            $code
        );

        return [$result ?? $code, $count];
    }

    /**
     * Второй аргумент `callMethod('Object', 'Method')`.
     *
     * @param list<string> $objects
     * @return array{0:string,1:int}
     */
    private function replaceCallMethodArgument(string $code, array $objects, string $old, string $new): array
    {
        $count = 0;
        foreach ($objects as $object) {
            $pattern = '/(callMethod(?:Safe)?\s*\(\s*)([\'"])('
                . preg_quote($object, '/') . ')\2(\s*,\s*)([\'"])(?:'
                . preg_quote($old, '/') . ')\5/u';
            $code = preg_replace_callback(
                $pattern,
                static function (array $match) use ($new, &$count): string {
                    $count++;

                    return $match[1] . $match[2] . $match[3] . $match[2]
                        . $match[4] . $match[5] . $new . $match[5];
                },
                $code
            ) ?? $code;
        }

        return [$code, $count];
    }

    /**
     * @param list<string> $objects
     * @return list<string>
     */
    private function names(array $objects): array
    {
        $names = [];
        foreach ($objects as $object) {
            $object = trim((string)$object);
            if ($object !== '') {
                $names[$object] = true;
            }
        }

        return array_keys($names);
    }
}
