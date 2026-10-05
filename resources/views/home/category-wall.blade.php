{{-- Модуль uni_category_wall_v2 (тип 2) старого сайта: разметка 1:1. --}}
@if ($categoryWall->isNotEmpty())
    <div class="heading">Популярные категории</div>
    <div class="uni-module category-wall category-wall_v2-0">
        <div class="uni-module__wrapper">
            @foreach ($categoryWall as ['category' => $wallCategory, 'links' => $links])
                @php($categoryUrl = route('category.show', $wallCategory))
                <div class="category-wall__item uni-item type2" @if ($wallCategory->image) style="background: url({{ Illuminate\Support\Facades\Storage::disk('public')->url($wallCategory->image) }});background-size: cover; background-repeat: no-repeat; background-position: center;" @endif>
                    <a href="{{ $categoryUrl }}" class="category-wall__image child type2"> </a>
                    <ul class="category-wall__ul child type2">
                        <li class="category-wall__title child type2"><a href="{{ $categoryUrl }}">{{ $wallCategory->name }}</a></li>
                        @foreach ($links as $link)
                            <li class="category-wall__li type2"><a href="{{ $link['url'] }}">{{ $link['title'] }}</a></li>
                        @endforeach
                        <li><a class="category-wall__more uni-href type2" data-href="{{ $categoryUrl }}"><span>Все категории</span></a></li>
                    </ul>
                </div>
            @endforeach
        </div>
    </div>

    @push('scripts')
        <script>
            $('.category-wall_v2-0').uniModules({type: 'grid', items: {0: {items: 1}, 700: {items: 2}, 993: {items: 2}, 1050: {items: 2}, 1400: {items: 4}}});
        </script>
    @endpush
@endif
