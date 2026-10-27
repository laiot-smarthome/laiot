<div class="md-ui-page md-ui-page--stack">
    <h1>SDK Example</h1>
    <p>Модуль установлен через MyDoMo SDK. Таблица: <code>module_example_sdk_notes</code>.</p>

    <h2>Настройки</h2>
    <dl>
        @foreach ($settings as $name => $value)
            <div><dt>{{ $name }}</dt><dd>{{ is_bool($value) ? ($value ? 'да' : 'нет') : $value }}</dd></div>
        @endforeach
    </dl>
    <p><a href="/admin/modules/example_sdk/settings">Изменить настройки и подписки</a></p>

    <h2>Подписки</h2>
    @if ($subscriptions === [])
        <p>Модуль пока не подписан ни на одно свойство.</p>
    @else
        <ul>
            @foreach ($subscriptions as $subscription)
                <li>{{ $subscription['object_title'] }}.{{ $subscription['property_title'] }}</li>
            @endforeach
        </ul>
    @endif
</div>
