<div class="md-ui-page md-ui-page--stack">
    <x-md.page-hero
        eyebrow="Система"
        icon="shield"
        title="Роли и права"
        lede="Роль — набор прав. Пользователь получает права через роли; администратор имеет полный доступ независимо от ролей."
        :meta="[
            ['icon' => 'shield', 'label' => 'Ролей', 'value' => count($roles)],
            ['icon' => 'key', 'label' => 'Прав', 'value' => count($permissions)],
        ]">
        <x-slot:actions>
            <x-md.button href="/users" variant="secondary" icon="users">Назначить роли</x-md.button>
        </x-slot:actions>
    </x-md.page-hero>

    <x-md.hint tone="info" title="Как это работает"
               text="Права проверяются middleware can: в маршрутах. Роли назначаются в карточке пользователя и применяются при следующем запросе." />

    <div class="row g-3">
        <div class="col-lg-6">
            <x-md.card title="Роли" :subtitle="'Всего: ' . count($roles)">
                @if ($roles === [])
                    <x-md.empty-state icon="shield" title="Ролей нет"
                                      text="Роли создаются миграцией; по умолчанию доступны admin и user." />
                @else
                    <x-md.table label="Роли">
                        <x-slot:head>
                            <th scope="col">Роль</th>
                            <th scope="col">Код</th>
                        </x-slot:head>
                        <x-slot:body>
                            @foreach ($roles as $role)
                                <tr>
                                    <td><strong>{{ $role['title'] }}</strong></td>
                                    <td><code class="md-table__code">{{ $role['name'] }}</code></td>
                                </tr>
                            @endforeach
                        </x-slot:body>
                    </x-md.table>
                @endif
            </x-md.card>
        </div>
        <div class="col-lg-6">
            <x-md.card title="Права" :subtitle="'Всего: ' . count($permissions)">
                @if ($permissions === [])
                    <x-md.empty-state icon="key" title="Прав нет"
                                      text="Каталог прав создаётся миграцией вместе с ролями." />
                @else
                    <x-md.table label="Права">
                        <x-slot:head>
                            <th scope="col">Право</th>
                            <th scope="col">Код</th>
                        </x-slot:head>
                        <x-slot:body>
                            @foreach ($permissions as $permission)
                                <tr>
                                    <td><strong>{{ $permission['title'] }}</strong></td>
                                    <td><code class="md-table__code">{{ $permission['name'] }}</code></td>
                                </tr>
                            @endforeach
                        </x-slot:body>
                    </x-md.table>
                @endif
            </x-md.card>
        </div>
    </div>
</div>
