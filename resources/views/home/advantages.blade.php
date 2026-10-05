{{-- Модуль html «Наши преимущества» старого сайта (oc_module 39): HTML и стиль
     перенесены как есть, иконки вынесены из base64 в файлы. --}}
<div class="html-module">
    <div class="row">
        @foreach ([
            ['advantage-battery.png', 'Диагностика аккумулятора', 'Проверим Ваш аккумулятор и при необходимости подберем новый', null],
            ['advantage-car.png', 'Диагностика автомобиля', 'Бесплатная проверка генератора, стартера и утечки тока на Вашем автомобиле', null],
            ['advantage-service.png', 'Обслуживание и зарядка', 'Бесплатная зарядка и обслуживание АКБ в течение гарантийного периода', 'width: 50%;'],
        ] as [$icon, $title, $text, $iconStyle])
            <div class="col-md-4">
                <div class="iconblock-8">
                    <div class="icon">
                        <img src="{{ asset("theme/image/home/{$icon}") }}" alt="" @if ($iconStyle) style="{{ $iconStyle }}" @endif>
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

@push('styles')
    <style>
        .iconblock-8 { margin: 10px 0; padding-right:10px; }
        .iconblock-8 .icon { float: left; text-align: center; position: relative; top: 15px; left: 5px; width: 50px; height: 50px; background: #c82a00; color: #fff; transition: all .3s; transform: skew(-10deg); margin-left:30px; border-radius: 18%; }
        .iconblock-8 .icon i, .iconblock-8 .icon img { font-size: 32px; height:auto; width:40px; color: #fff; padding-top:10px; z-index:1; position: relative; box-sizing: content-box; transform: skew(5deg); }
        .iconblock-8:hover .icon { top: 10px; left: 0; }
        .iconblock-8:hover .icon { box-shadow: 8px 8px #cccccc; }
        .iconblock-8 .block { overflow: hidden; padding-left: 30px; }
        .iconblock-8 h3 { transition: all 0.7s ease 0s; color: #000; font-size:18px; margin:10px 0; font-weight: 500; }
        .iconblock-8:hover h3 { color: #c82a00; }
    </style>
@endpush
