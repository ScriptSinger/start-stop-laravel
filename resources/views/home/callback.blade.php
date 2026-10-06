{{-- Модуль html «Обратный звонок» старого сайта (oc_module 60): HTML и стиль как есть. --}}
<div class="html-module">
    <div class="imageblock">
        <div class="blocktxt">
            <h3>Не знаете какой аккумулятор выбрать?</h3>
            <p>Консультант позвонит и подберёт аккумулятор на Ваш автомобиль. Для этого, оставьте свой номер телефона
                <a href="{{ route('callback.create') }}" class="header-phones__callback" @click.prevent="$store.modal.open($el.href, 'Заказать звонок')"><i class="menu__header-icon fa fa-fw fa-phone"></i><span class="menu__header-title">Заказать звонок</span></a>
            </p>
        </div>
    </div>
</div>
