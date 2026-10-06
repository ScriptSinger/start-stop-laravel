{{-- Начало группы фильтра. На телефоне группа — строка списка: нажатие
     открывает её значения, под названием — что выбрано, «−» снимает выбор. --}}
<div class="catalog-filter__group form-group" data-group="{{ $key }}" :class="{'is-active': group === @js($key), 'is-selected': summary(@js($key)) !== ''}">
    @if ($desktopTitle ?? true)
        <strong class="hidden-xs">{{ $title }}</strong>
    @endif
    <button type="button" class="catalog-filter__toggle" @click="openGroup(@js($key), @js($title))">
        <span class="catalog-filter__toggle-title">{{ $title }}</span>
        <span class="catalog-filter__toggle-summary" x-text="summary(@js($key))"></span>
        <i class="catalog-filter__clear fas fa-minus-circle" x-show="summary(@js($key)) !== ''" x-cloak @click.stop="clearGroup(@js($key))"></i>
        <i class="catalog-filter__chevron fas fa-chevron-right" x-show="summary(@js($key)) === ''"></i>
    </button>
    <div class="catalog-filter__body">
