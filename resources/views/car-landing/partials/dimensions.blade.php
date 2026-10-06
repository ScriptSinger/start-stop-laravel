{{-- Габариты Д×Ш×В отдельными плашками, а не строкой через запятую. --}}
@if ($dimensions === [])
    —
@else
    <ul class="car-landing__dims">
        @foreach ($dimensions as $dimension)
            <li class="car-landing__dim">{{ $dimension }}</li>
        @endforeach
    </ul>
@endif
