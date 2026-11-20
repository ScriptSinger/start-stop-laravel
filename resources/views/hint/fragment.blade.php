{{-- Подсказка к характеристике: в окне ($store.modal) и на странице /hint/... --}}
<div class="spec-hint">
    <p class="spec-hint__text">{!! nl2br(e($hint)) !!}</p>
    @if ($guideUrl)
        <a class="spec-hint__more" href="{{ $guideUrl }}" target="_blank" rel="noopener">Больше советов по выбору <i class="fas fa-arrow-right" aria-hidden="true"></i><span class="sr-only"> (откроется в новой вкладке)</span></a>
    @endif
</div>
