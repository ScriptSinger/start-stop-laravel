{{-- Модуль html «Наши преимущества» старого сайта (oc_module 39): HTML и стиль
     перенесены как есть, иконки вынесены из base64 в файлы. --}}
<div class="html-module">
    <div class="row">
        @foreach ([
            ['advantage-battery.png', 'Диагностика аккумулятора', 'Проверим Ваш аккумулятор и при необходимости подберем новый', null],
            ['advantage-car.png', 'Диагностика автомобиля', 'Бесплатная проверка генератора, стартера и утечки тока на Вашем автомобиле', null],
            ['advantage-service.png', 'Обслуживание и зарядка', 'Бесплатная зарядка и обслуживание АКБ в течение гарантийного периода', 'iconblock-8__img_small'],
        ] as [$icon, $title, $text, $iconClass])
            <div class="col-md-4">
                <div class="iconblock-8">
                    <div class="icon">
                        <img src="{{ Vite::asset("resources/images/home/{$icon}") }}" alt="" @class([$iconClass])>
                    </div>
                    <div class="block">
                        <h3>{{ $title }}</h3>
                        <p>{{ $text }}</p>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
