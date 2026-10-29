<?php

declare(strict_types=1);

namespace LaIOT\Zigbee\Support;

use LaIOT\Zigbee\HealthSettings;
use LaIOT\Zigbee\ValueTransformer;

/**
 * Перевод правил преобразования между формой и массивом.
 *
 * Форма остаётся простой: режим, пара чисел и замены по одной строке
 * `из=в`. Значения замен разбираются как JSON, если это возможно, поэтому
 * `1`, `true`, `"1"`, `""` и `null` различаются так же, как в Zigbee2MQTT.
 * Хранится всегда нормализованный массив: контроллер не пишет JSON сам.
 *
 * 2026-10-28 — общая форма правила для приёма и отправки.
 */
final class RuleForm
{
    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>|null
     */
    public static function parse(array $input): ?array
    {
        $mode = (string)($input['mode'] ?? 'none');
        if ($mode === 'none' || $mode === '') {
            return null;
        }
        $precision = trim((string)($input['precision'] ?? ''));
        $rule = [
            'mode' => $mode,
            'case_sensitive' => !empty($input['case_sensitive']),
            'factor' => trim((string)($input['factor'] ?? '')) === '' ? 1 : (float)$input['factor'],
            'offset' => trim((string)($input['offset'] ?? '')) === '' ? 0 : (float)$input['offset'],
            'precision' => $precision === '' ? null : (int)$precision,
            'pairs' => self::pairs((string)($input['pairs'] ?? '')),
        ];

        return ValueTransformer::validate($rule);
    }

    /**
     * @return list<array{from:mixed,to:mixed}>
     */
    private static function pairs(string $text): array
    {
        $pairs = [];
        foreach (preg_split('/\R/u', $text) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || !str_contains($line, '=')) {
                continue;
            }
            [$from, $to] = explode('=', $line, 2);
            $pairs[] = [
                'from' => self::value(trim($from)),
                'to' => self::value(trim($to)),
            ];
        }

        return $pairs;
    }

    private static function value(string $raw): mixed
    {
        if ($raw === '') {
            return '';
        }
        try {
            $decoded = json_decode($raw, true, 4, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $raw;
        }

        return is_scalar($decoded) || $decoded === null ? $decoded : $raw;
    }

    /**
     * @param array<string, mixed>|null $rule
     */
    public static function pairsText(?array $rule): string
    {
        if ($rule === null || !is_array($rule['pairs'] ?? null)) {
            return '';
        }
        $lines = [];
        foreach ($rule['pairs'] as $pair) {
            if (!is_array($pair) || !array_key_exists('from', $pair) || !array_key_exists('to', $pair)) {
                continue;
            }
            $lines[] = ValueTransformer::json($pair['from']) . '=' . ValueTransformer::json($pair['to']);
        }

        return implode("\n", $lines);
    }

    /**
     * @param array<string, mixed>|null $rule
     * @return array<string, mixed>
     */
    public static function fields(?array $rule): array
    {
        $rule ??= [];
        $precision = $rule['precision'] ?? null;

        return [
            'mode' => (string)($rule['mode'] ?? 'none'),
            'case_sensitive' => (bool)($rule['case_sensitive'] ?? true),
            'factor' => (string)($rule['factor'] ?? 1),
            'offset' => (string)($rule['offset'] ?? 0),
            'precision' => $precision === null ? '' : (string)$precision,
            'pairs' => self::pairsText($rule),
        ];
    }

    /**
     * @param array<string, mixed> $settings
     */
    public static function pointsText(array $settings): string
    {
        $lines = [];
        foreach ($settings['voltage_points'] ?? [] as $point) {
            if (is_array($point) && isset($point['voltage'], $point['percent'])) {
                $lines[] = $point['voltage'] . '=' . $point['percent'];
            }
        }

        return implode("\n", $lines);
    }

    /**
     * @return list<array{voltage:float,percent:float}>
     */
    public static function parsePoints(string $text): array
    {
        $points = [];
        foreach (preg_split('/\R/u', $text) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || !str_contains($line, '=')) {
                continue;
            }
            [$voltage, $percent] = explode('=', $line, 2);
            $points[] = ['voltage' => (float)trim($voltage), 'percent' => (float)trim($percent)];
        }

        return $points;
    }

    /**
     * Строит настройки мониторинга из POST-формы.
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public static function health(array $input): array
    {
        $defaults = HealthSettings::defaults();
        $settings = [
            'enabled' => !empty($input['enabled']),
            'offline_delay' => (int)($input['offline_delay'] ?? 0),
            'timeout_mains' => (int)($input['timeout_mains'] ?? 0),
            'timeout_battery' => (int)($input['timeout_battery'] ?? 0),
            'low' => (int)($input['low'] ?? 0),
            'critical' => (int)($input['critical'] ?? 0),
            'recovery' => (int)($input['recovery'] ?? 0),
            'battery_source' => (string)($input['battery_source'] ?? 'auto'),
            'voltage_path' => trim((string)($input['voltage_path'] ?? 'voltage')),
            'voltage_unit' => (string)($input['voltage_unit'] ?? 'mV'),
            'voltage_profile' => (string)($input['voltage_profile'] ?? 'none'),
            'voltage_points' => self::parsePoints((string)($input['voltage_points'] ?? '')),
            'events' => [],
        ];
        $events = is_array($input['events'] ?? null) ? $input['events'] : [];
        foreach (HealthSettings::EVENTS as $event => $label) {
            $rule = is_array($events[$event] ?? null) ? $events[$event] : [];
            $settings['events'][$event] = [
                'enabled' => !empty($rule['enabled']),
                'notify' => !empty($rule['notify']),
                'level' => (int)($rule['level'] ?? 2),
                'repeat_hours' => (int)($rule['repeat_hours'] ?? 0),
                'message' => (string)($rule['message'] ?? $defaults['events'][$event]['message']),
                'code' => (string)($rule['code'] ?? ''),
            ];
        }

        return HealthSettings::validate($settings);
    }
}
