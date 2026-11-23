{{-- Цены на обслуживание по ёмкости: [[block:service-prices]] в тексте страницы (App\Services\Catalog\ServicePriceTable). Стили общие с таблицей трейд-ина. --}}
@if ($prices->isNotEmpty())
    <div class="trade-in-table-wrap">
        <table class="trade-in-table">
            <thead>
                <tr>
                    <th scope="col">Ёмкость аккумулятора</th>
                    <th scope="col">Цена</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($prices as $price)
                    <tr>
                        <td>{{ $price->capacityLabel() }}</td>
                        <td>{{ App\Support\Price::format($price->price) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
