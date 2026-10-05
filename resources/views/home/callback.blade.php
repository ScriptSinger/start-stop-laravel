{{-- Модуль html «Обратный звонок» старого сайта (oc_module 60): HTML и стиль как есть. --}}
<div class="html-module">
    <div class="imageblock">
        <div class="blocktxt">
            <h3>Не знаете какой аккумулятор выбрать?</h3>
            <p>Консультант позвонит и подберёт аккумулятор на Ваш автомобиль. Для этого, оставьте свой номер телефона
                <a href="{{ route('callback.create') }}" class="header-phones__callback" data-modal-url="{{ route('callback.create') }}" data-modal-title="Заказать звонок"><i class="menu__header-icon fa fa-fw fa-phone"></i><span class="menu__header-title">Заказать звонок</span></a>
            </p>
        </div>
    </div>
</div>

@push('styles')
    <style>
        .imageblock .header-phones__callback { display: inline-block; background: #c82a00; border: none; border-radius: 12px; color: white !important; text-decoration: none; margin-left: 30px; padding: 6px 12px; }
        .imageblock { border-radius: 25px; background-image: url({{ Illuminate\Support\Facades\Storage::disk('public')->url('catalog/revslider_media_folder/smartphone.jpg') }}); background-size: cover; background-repeat: no-repeat; background-position: 50% 30%; }
        .blocktxt { background: rgb(0, 0, 0, 0.7); padding: 20px; border-radius: 25px; color: #fff !important; }
        .imageblock h3 { transition: all 0.7s ease 0s; color: #fff; font-size:36px; margin:10px 0; font-weight: 500; }
        .imageblock:hover h3 { color: #c82a00; }
    </style>
@endpush
