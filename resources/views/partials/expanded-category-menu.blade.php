{{-- Как на старом сайте: на главной и инфостраницах меню категорий в шапке
     раскрыто и занимает левую колонку (aside#column-left оставляют пустым). --}}
@push('styles')
    <style>@media (min-width:992px) {header .menu1 .menu__collapse {display:block !important}}</style>
@endpush
