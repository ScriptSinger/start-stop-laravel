{{-- Модуль html «Наши преимущества» старого сайта (oc_module 39): HTML и стиль
     перенесены как есть, иконки вынесены из base64 в файлы. --}}
<div class="html-module home-advantages">
    <div class="row">
        @foreach ([
            ['advantage-battery.png', 'Диагностика аккумулятора', 'Проверим Ваш аккумулятор и при необходимости подберем новый', null, 180, 136],
            ['advantage-car.png', 'Диагностика автомобиля', 'Бесплатная проверка генератора, стартера и утечки тока на Вашем автомобиле', null, 221, 196],
            ['advantage-service.png', 'Обслуживание и зарядка', 'Бесплатная зарядка и обслуживание АКБ в течение гарантийного периода', 'iconblock-8__img_small', 162, 190],
        ] as [$icon, $title, $text, $iconClass, $width, $height])
            <div class="col-md-4">
                <div class="iconblock-8">
                    <div class="icon">
                        <img src="{{ Vite::asset("resources/images/home/{$icon}") }}" alt="" width="{{ $width }}" height="{{ $height }}" @class([$iconClass])>
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
