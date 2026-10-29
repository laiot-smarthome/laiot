<?php

declare(strict_types=1);

namespace LaIOT\Zigbee;

use Closure;
use LaIOT\Services\Locations\LocationRepository;
use LaIOT\Mqtt\RuntimeState;
use LaIOT\Services\Notifications\NotificationService;
use LaIOT\Services\Runtime\CodeRunner;
use LaIOT\Services\Worker\ProcessLock;
use RuntimeException;
use Throwable;

/**
 * Один сохранённый конвейер инцидентов доступности и батареи.
 *
 * Состояние инцидента живёт в `module_zigbee_health_state`, поэтому повторный
 * запуск worker-а не рассылает уведомления заново. Сбой MQTT-транспорта
 * подавляет зависимые события устройств: иначе отключение брокера превращается
 * в лавину «нет связи» по всем датчикам.
 *
 * 2026-10-28 — перенос alpha: MySQL GET_LOCK заменён файловым ProcessLock,
 * уведомление вместо say(), PHP-обработчик — через CodeRunner.
 */
final class HealthMonitor
{
    /** @var Closure(string, int): void */
    private readonly Closure $notify;

    /** @var Closure(string, array<string, mixed>): void */
    private readonly Closure $code;

    /** @var Closure(): ?string */
    private readonly Closure $transport;

    public function __construct(
        private readonly Repository $repository,
        private readonly RuntimeState $runtime,
        private readonly NotificationService $notifications,
        private readonly CodeRunner $codeRunner,
        private readonly ProcessLock $lock,
        private readonly LocationRepository $locations,
        ?callable $transport = null
    ) {
        $this->notify = Closure::fromCallable($notify ?? function (string $message, int $level): void {
            $this->notifications->send(
                'zigbee',
                $message,
                $level >= 3 ? 'danger' : 'warning',
                ['key' => 'zigbee-health-' . substr(hash('sha256', $message), 0, 24)]
            );
        });
        $this->code = function (string $code, array $params): void {
            if ($this->codeRunner->run($code, $params) === false) {
                throw new RuntimeException('PHP-обработчик мониторинга вернул false.');
            }
        };
        $this->transport = Closure::fromCallable($transport ?? function (): ?string {
            $status = $this->runtime->status();
            // Остановленный MQTT-цикл — не инцидент брокера: мониторинг
            // молчит, пока транспорт не запущен.
            if (!$status['worker_alive']) {
                return null;
            }

            return $status['connected'] ? 'online' : 'offline';
        });
    }

    public function tick(?int $now = null): void
    {
        $now ??= time();
        if (!$this->lock->acquire(['zigbee.health'])) {
            // Предыдущий проход ещё выполняет пользовательский обработчик.
            return;
        }
        try {
            $settings = $this->repository->healthSettings();
            $bridges = $this->repository->bridges(true);
            if (!$settings['enabled'] || $bridges === []) {
                return;
            }
            $transport = ($this->transport)();
            if ($transport === null) {
                return;
            }
            $context = [
                'KIND' => 'mqtt',
                'ID' => 0,
                'DEVICE_ID' => 0,
                'DEVICE_TITLE' => 'MQTT-брокер',
                'BRIDGE_ID' => (int)$bridges[0]['id'],
                'LOCATION_ID' => 0,
                'ROOM' => '',
                'SOURCE' => 'zigbee',
                'IEEE_ADDRESS' => '',
                'BASE_TOPIC' => '',
            ];
            $this->observe('mqtt:availability', 'availability', $transport, null, 'mqtt', $settings, $context, $now);
            if ($transport !== 'online') {
                return;
            }
            foreach ($bridges as $bridge) {
                $context = array_replace($context, [
                    'KIND' => 'bridge',
                    'ID' => (int)$bridge['id'],
                    'BRIDGE_ID' => (int)$bridge['id'],
                    'DEVICE_TITLE' => (string)$bridge['title'],
                    'BASE_TOPIC' => (string)$bridge['base_topic'],
                ]);
                if (!in_array($bridge['state'], ['online', 'offline'], true)) {
                    continue;
                }
                $this->observe(
                    'bridge:' . $bridge['id'] . ':availability',
                    'availability',
                    (string)$bridge['state'],
                    null,
                    'bridge',
                    $settings,
                    $context,
                    $now
                );
                if ($bridge['state'] !== 'online') {
                    continue;
                }
                foreach ($this->repository->devicesForBridge((int)$bridge['id'], 'added') as $device) {
                    try {
                        $this->device($bridge, $device, $settings, $now);
                    } catch (Throwable $error) {
                        $this->repository->log(
                            (int)$bridge['id'],
                            (int)$device['id'],
                            'monitor',
                            $error->getMessage(),
                            'error'
                        );
                    }
                }
            }
        } finally {
            $this->lock->release();
        }
    }

    /**
     * @param array<string, mixed> $bridge
     * @param array<string, mixed> $device
     * @param array<string, mixed> $global
     */
    private function device(array $bridge, array $device, array $global, int $now): void
    {
        $settings = trim((string)($device['health_config'] ?? '')) === ''
            ? $global
            : HealthSettings::validate(ValueTransformer::decode((string)$device['health_config']) ?? []);
        if (!$settings['enabled']) {
            return;
        }
        $location = (int)$device['location_id'] > 0 ? $this->locations->find((int)$device['location_id']) : null;
        $context = [
            'KIND' => 'device',
            'ID' => (int)$device['id'],
            'DEVICE_ID' => (int)$device['id'],
            'DEVICE_TITLE' => (string)(($device['title'] ?? '') !== '' ? $device['title'] : $device['friendly_name']),
            'DEVICE_NAME' => (string)$device['friendly_name'],
            'IEEE_ADDRESS' => (string)($device['ieee_address'] ?? ''),
            'BRIDGE_ID' => (int)$bridge['id'],
            'BASE_TOPIC' => (string)$bridge['base_topic'],
            'LOCATION_ID' => (int)$device['location_id'],
            'ROOM' => (string)($location['title'] ?? ''),
            'SOURCE' => 'zigbee',
        ];
        $metadata = ValueTransformer::decode((string)($device['metadata'] ?? '')) ?? [];
        $batteryPowered = str_contains(strtolower((string)($metadata['power_source'] ?? '')), 'battery');
        $timeout = (int)$settings[$batteryPowered ? 'timeout_battery' : 'timeout_mains'];
        $state = (string)$device['available'];
        $reason = 'availability';
        $lastSeen = (string)($device['last_seen'] ?? '');
        if ($timeout > 0 && $lastSeen !== '' && $now - (int)strtotime($lastSeen) >= $timeout * 60) {
            $state = 'offline';
            $reason = 'timeout';
        }
        if (in_array($state, ['online', 'offline'], true)) {
            $this->observe(
                'device:' . $device['id'] . ':availability',
                'availability',
                $state,
                null,
                $reason,
                $settings,
                $context,
                $now
            );
        }
        if ($settings['battery_source'] === 'off') {
            return;
        }
        $values = $this->repository->propertyValues((int)$device['id']);
        $raw = ValueTransformer::decode($values['battery'] ?? null);
        $estimated = false;
        $percent = null;
        if ($settings['battery_source'] === 'auto' && Battery::number($raw) && $raw >= 0 && $raw <= 100) {
            $percent = (float)$raw;
        } elseif (($settings['battery_source'] === 'voltage' || $batteryPowered)
            && $settings['voltage_profile'] !== 'none') {
            $percent = Battery::estimate(ValueTransformer::decode($values[$settings['voltage_path']] ?? null), $settings);
            $estimated = $percent !== null;
        }
        $lowFlag = ValueTransformer::decode($values['battery_low'] ?? null);
        if ($percent === null && !is_bool($lowFlag) && !in_array($lowFlag, [0, 1], true)) {
            return;
        }
        $subject = 'device:' . $device['id'] . ':battery';
        $previous = $this->repository->healthState($subject);
        $level = $percent !== null && $percent <= $settings['critical']
            ? 'critical'
            : (($lowFlag === true || $lowFlag === 1 || ($percent !== null && $percent <= $settings['low']))
                ? 'low'
                : 'normal');
        // Не сбрасываем инцидент из-за небольшого отскока напряжения у порога.
        if ($level === 'normal' && $percent !== null && $percent < $settings['recovery']
            && in_array($previous['current'] ?? '', ['low', 'critical'], true)) {
            $level = 'low';
        }
        $context['ESTIMATED'] = $estimated;
        $context['VOLTAGE'] = ValueTransformer::decode($values[$settings['voltage_path']] ?? null);
        $this->observe($subject, 'battery', $level, $percent, $estimated ? 'voltage' : 'battery', $settings, $context, $now);
    }

    /**
     * @param array<string, mixed> $settings
     * @param array<string, mixed> $context
     */
    private function observe(
        string $subject,
        string $kind,
        string|int|float $value,
        mixed $reading,
        string $reason,
        array $settings,
        array $context,
        int $now
    ): void {
        $state = $this->repository->healthState($subject);
        $normal = $kind === 'battery' ? 'normal' : 'online';
        $state['reason'] = $reason;
        if (($state['pending'] ?? null) !== $value) {
            $state['pending'] = $value;
            $state['since'] = $now;
        }
        if ($value === 'offline' && $now - (int)($state['since'] ?? $now) < (int)$settings['offline_delay']) {
            $this->repository->saveHealthState($subject, $state);

            return;
        }
        $previous = $state['current'] ?? null;
        if ($previous === null && $value === $normal) {
            // Первое здоровое состояние — не событие: молчим.
            $state['current'] = $value;
            $state['previous'] = null;
            $state['revision'] = 1;
            $state['notify'] = ['revision' => 1, 'time' => $now];
            $state['code'] = ['revision' => 1, 'time' => $now];
            $state['journal'] = 1;
            $state['reading'] = $reading;
            $this->repository->saveHealthState($subject, $state);

            return;
        }
        if ($previous !== $value) {
            $state['current'] = $value;
            $state['previous'] = $previous;
            $state['previous_reading'] = $state['reading'] ?? null;
            $state['changed_at'] = $now;
            $state['revision'] = ((int)($state['revision'] ?? 0)) + 1;
        }
        $revision = (int)$state['revision'];
        $event = $kind === 'battery'
            ? ['normal' => 'battery_ok', 'low' => 'battery_low', 'critical' => 'battery_critical'][$value]
            : (string)$value;
        $rule = $settings['events'][$event];
        $params = $context + [
            'EVENT' => $event,
            'OLD_STATE' => $state['previous'] ?? null,
            'NEW_STATE' => $value,
            'VALUE' => $reading,
            'OLD_VALUE' => $state['previous_reading'] ?? null,
            'REASON' => $reason,
            'TIME' => $now,
            'INCIDENT_ID' => $subject . ':' . $revision,
        ];
        $state['reading'] = $reading;
        if (($state['journal'] ?? null) !== $revision) {
            $suffix = $reading !== null ? ' (' . $reading . '%)' : '';
            $this->repository->log(
                (int)$context['BRIDGE_ID'],
                (int)$context['DEVICE_ID'] > 0 ? (int)$context['DEVICE_ID'] : null,
                'monitor/' . $event,
                $context['DEVICE_TITLE'] . ': ' . HealthSettings::EVENTS[$event] . $suffix,
                $value === $normal ? 'info' : 'warning'
            );
            $state['journal'] = $revision;
        }
        $this->repository->saveHealthState($subject, $state);
        foreach (['notify', 'code'] as $channel) {
            $active = !empty($rule['enabled'])
                && ($channel === 'notify' ? !empty($rule['notify']) : trim((string)$rule['code']) !== '');
            $ack = is_array($state[$channel] ?? null) ? $state[$channel] : [];
            $repeat = $value !== $normal && (int)$rule['repeat_hours'] > 0
                && $now - (int)($ack['time'] ?? 0) >= (int)$rule['repeat_hours'] * 3600;
            if (($ack['revision'] ?? null) === $revision && !$repeat) {
                continue;
            }
            if ($active) {
                $params['REPEAT'] = ((int)($ack['revision'] ?? 0)) === $revision;
                try {
                    if ($channel === 'notify') {
                        $message = strtr((string)$rule['message'], [
                            '{device}' => (string)$context['DEVICE_TITLE'],
                            '{room}' => (string)$context['ROOM'],
                            '{value}' => $reading === null ? 'неизвестно' : (string)$reading,
                            '{event}' => $event,
                        ]);
                        ($this->notify)($message, (int)$rule['level']);
                    } else {
                        ($this->code)((string)$rule['code'], $params);
                    }
                } catch (Throwable $error) {
                    $this->repository->log(
                        (int)$context['BRIDGE_ID'],
                        (int)$context['DEVICE_ID'] > 0 ? (int)$context['DEVICE_ID'] : null,
                        'monitor/' . $channel,
                        $error->getMessage(),
                        'error'
                    );
                    // Повторяется только сбойный канал, второй не дублируется.
                    continue;
                }
            }
            $state[$channel] = ['revision' => $revision, 'time' => $now];
            $this->repository->saveHealthState($subject, $state);
        }
    }

}
