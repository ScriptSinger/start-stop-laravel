{{-- Габариты Д×Ш×В отдельными плашками, а не строкой через запятую.
     collapsible: на телефоне показываем первые три, остальные раскрывает кнопка. --}}
@if ($dimensions === [])
    —
@elseif (($collapsible ?? false) && count($dimensions) > 3)
    <div x-data="{open: false}">
        <ul class="car-landing__dims" :class="{'is-open': open}">
            @foreach ($dimensions as $dimension)
                <li class="car-landing__dim">{{ $dimension }}</li>
            @endforeach
        </ul>
        <button type="button" class="car-landing__dims-toggle" x-cloak :aria-expanded="open.toString()" @click="open = !open" x-text="open ? 'Свернуть' : 'Ещё {{ count($dimensions) - 3 }}'">Ещё {{ count($dimensions) - 3 }}</button>
    </div>
@else
    <ul class="car-landing__dims">
        @foreach ($dimensions as $dimension)
            <li class="car-landing__dim">{{ $dimension }}</li>
        @endforeach
    </ul>
@endif
