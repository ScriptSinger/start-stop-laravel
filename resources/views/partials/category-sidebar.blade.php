{{-- Реальные CSS-классы из extension/module/category.twig старого проекта --}}
<aside class="col-sm-4 col-md-3">
    <div class="heading">Категории</div>
    <nav id="category-module" class="menu-module">
        <ul class="menu-module__ul">
            @foreach ($menuCategories as $menuCategory)
                <li class="menu-module__li">
                    <a href="{{ route('category.show', $menuCategory) }}"
                       class="menu-module__a {{ ($activeCategory ?? null)?->id === $menuCategory->id ? 'active' : '' }}">
                        {{ $menuCategory->name }}
                    </a>
                </li>
            @endforeach
        </ul>
    </nav>

    @includeWhen(isset($attributeFacets), 'partials.catalog-filter')
</aside>
