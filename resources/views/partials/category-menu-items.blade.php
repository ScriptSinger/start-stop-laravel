{{-- Пункты меню категорий (menu1 темы): в шапке и в прилипающей шапке. --}}
@foreach ($categoryMenu as ['category' => $menuCategory, 'children' => $children])
    <li class="menu__level-1-li {{ $children !== [] ? 'has-children' : '' }}">
        <a class="menu__level-1-a" href="{{ route('category.show', $menuCategory) }}">
            @if ($menuCategory->hasFontIcon())
                <i class="menu__level-1-icon {{ $menuCategory->icon }} fa-fw"></i>
            @elseif ($menuCategory->icon)
                <img src="{{ Illuminate\Support\Facades\Storage::disk('public')->url($menuCategory->icon) }}" alt="{{ $menuCategory->name }}" class="menu__level-1-img" loading="lazy" />
            @endif
            {{ $menuCategory->name }}
        </a>
        @if ($children !== [])
            <span class="menu__pm menu__level-1-pm visible-xs visible-sm"><i class="fa fa-plus"></i><i class="fa fa-minus"></i></span>
            <div class="menu__level-2 {{ count($children) > 12 ? 'column-3' : 'column-1' }}">
                @foreach ($children as $child)
                    <div class="menu__level-2-ul {{ count($children) > 12 ? 'col-md-4' : 'col-md-12' }}">
                        <a class="menu__level-2-a" href="{{ $child['url'] }}">{{ $child['title'] }}</a>
                    </div>
                @endforeach
            </div>
        @endif
    </li>
@endforeach
