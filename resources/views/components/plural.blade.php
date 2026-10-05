{{-- Русское склонение по числу: <x-plural :count="5" forms="страница|страницы|страниц" /> → «страниц».
     trans_choice() тут не подходит: для строки, которой нет в переводах, он берёт
     правила языка по умолчанию, а не русские. --}}
@props(['count', 'forms'])
{{ app('translator')->getSelector()->choose($forms, (int) $count, 'ru') }}