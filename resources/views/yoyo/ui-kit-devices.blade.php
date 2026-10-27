<div class="md-table-wrap">
    <div class="md-table__toolbar"><input type="search" class="md-input" name="search" value="{{ $search }}" aria-label="Поиск устройств на сервере" placeholder="Поиск устройств" yoyo:on="keyup delay:300ms changed"></div>
    @if ($rows === [])<x-md.empty-state title="Ничего не найдено" text="Измените поисковый запрос." compact />@else
        <div class="md-table__scroll"><table class="md-table"><thead><tr><th scope="col">Устройство</th><th scope="col">Комната</th><th scope="col">Состояние</th></tr></thead><tbody>@foreach ($rows as $row)<tr><td>{{ $row['name'] }}</td><td>{{ $row['room'] }}</td><td>{{ $row['state'] }}</td></tr>@endforeach</tbody></table></div>
    @endif
</div>
