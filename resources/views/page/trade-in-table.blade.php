{{-- Таблица скидок за сдачу старого АКБ: [[block:trade-in]] в тексте страницы (App\Services\Catalog\TradeInTable). --}}
@if ($types->isNotEmpty())
    <div class="trade-in-table-wrap">
        <table class="trade-in-table">
            <thead>
                <tr>
                    <th scope="col">Тип АКБ</th>
                    <th scope="col">Скидка при покупке АКБ</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($types as $type)
                    <tr>
                        <td>{{ $type->name }}</td>
                        <td>{{ App\Support\Price::format((float) $type->amount) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
