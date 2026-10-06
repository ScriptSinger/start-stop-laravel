{{-- Поиск: в шапке, на телефоне и в прилипающей шапке. Поля связаны
     ($store.search), крестик очищает все — как clearBtn темы. --}}
<form class="header-search" action="{{ route('search') }}" method="get">
    <div class="header-search__form">
        <input type="text" name="search" value="{{ $search ?? '' }}" placeholder="Поиск" aria-label="Поиск" autocomplete="off" class="header-search__input form-control" x-model="$store.search.query" />
        <button type="button" class="search-btn-clear {{ filled($search ?? null) ? 'show' : '' }}" aria-label="Очистить" :class="{show: $store.search.query !== ''}" @click="$store.search.query = ''">&times;</button>
        <button type="submit" class="header-search__btn search-btn" title="Поиск"><i class="fa fa-search"></i></button>
    </div>
</form>
