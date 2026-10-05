{{-- Порт catalog/view/theme/unishop2/template/extension/module/battery_filter.twig --}}
<div class="battery-wizard-container" id="battery-wizard">
    <div id="w-step-1" class="w-step">
        <h3 class="w-title">Подбор аккумулятора по марке авто</h3>
        <div class="w-grid brands">
            @foreach ($popular_brands as $brand)
                <div class="w-item brand-box" onclick="wLoadModels('{{ $brand['name'] }}')">
                    <img src="{{ $brand['image'] }}" alt="{{ $brand['name'] }}">
                    <span>{{ $brand['name'] }}</span>
                </div>
            @endforeach
        </div>

        <div id="w-more-brands" style="display:none;" class="w-grid brands mt-3">
            @foreach ($other_brands as $brand)
                <div class="w-item brand-box" onclick="wLoadModels('{{ $brand['name'] }}')">
                    <img src="{{ $brand['image'] }}" alt="{{ $brand['name'] }}">
                    <span>{{ $brand['name'] }}</span>
                </div>
            @endforeach
        </div>

        <div class="text-center">
            <button class="w-btn-more" onclick="$('#w-more-brands').slideToggle(); $(this).hide();">Показать все марки</button>
        </div>
    </div>

    <div id="w-step-2" class="w-step" style="display:none;">
        <h3 class="w-title"><span class="w-back" onclick="wBack(1)">←</span> Модели <span id="w-cur-brand" style="color:#e31e24;"></span></h3>
        <div id="w-models" class="w-grid models"></div>
    </div>

    <div id="w-step-3" class="w-step" style="display:none;">
        <h3 class="w-title"><span class="w-back" onclick="wBack(2)">←</span> Выберите поколение / кузов</h3>
        <div id="w-gens" class="w-grid gens"></div>
    </div>
</div>

<style>
.battery-wizard-container { background: #fff; padding: 20px; border: 1px solid #eee; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); margin-bottom: 30px; }
.w-title { font-size: 18px; font-weight: 700; margin-bottom: 20px; text-align: center; }
.w-back { cursor: pointer; color: #e31e24; font-size: 24px; vertical-align: middle; margin-right: 10px; }
.w-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(110px, 1fr)); gap: 12px; }
.w-grid.gens { grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); }
.w-grid.models { grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); }
.w-item { border: 1px solid #f0f0f0; padding: 12px; text-align: center; cursor: pointer; border-radius: 10px; transition: all 0.2s ease-in-out; background: #fff; }
.w-item:hover { border-color: #e31e24; box-shadow: 0 5px 12px rgba(227, 30, 36, 0.1); transform: translateY(-2px); }
.brand-box img { max-width: 60px; height: 50px; object-fit: contain; display: block; margin: 0 auto 8px; }
.gens img { width: 100%; height: auto; border-radius: 6px; margin-bottom: 10px; }
.w-btn-more { margin-top: 20px; background: #f8f8f8; border: 1px solid #ddd; padding: 8px 25px; border-radius: 20px; color: #555; cursor: pointer; }
.mt-3 { margin-top: 15px; }
.text-center { text-align: center; }
</style>

<script>
let selData = { brand: '', model: '' };

function wLoadModels(brandName) {
    selData.brand = brandName;
    $('#w-cur-brand').text(brandName);

    $.ajax({
        url: '{{ route('battery-filter.models') }}',
        data: { brand: brandName },
        dataType: 'json',
        success: function(json) {
            let html = '';
            if (json.length > 0) {
                json.forEach(function(item) {
                    html += '<div class="w-item" onclick="wLoadGens(\'' + item.model + '\')"><b>' + item.model + '</b></div>';
                });
                $('#w-models').html(html);
                $('.w-step').hide();
                $('#w-step-2').fadeIn(300);
            }
        }
    });
}

function wLoadGens(modelName) {
    selData.model = modelName;

    $.ajax({
        url: '{{ route('battery-filter.generations') }}',
        data: { brand: selData.brand, model: modelName },
        dataType: 'json',
        success: function(json) {
            let html = '';
            if (json.length > 0) {
                json.forEach(function(item) {
                    html += '<div class="w-item" onclick="wFinalGo(\'' + item.name + '\')">';
                    html += '<img src="' + item.image + '" alt="' + item.name + '">';
                    html += '<div>' + item.name + '</div></div>';
                });
                $('#w-gens').html(html);
                $('.w-step').hide();
                $('#w-step-3').fadeIn(300);
            }
        }
    });
}

function wFinalGo(genName) {
    $.ajax({
        url: '{{ route('battery-filter.result') }}',
        data: { brand: selData.brand, model: selData.model, gen: genName },
        dataType: 'json',
        success: function(json) {
            if (json['redirect']) {
                location.href = json['redirect'];
            } else {
                alert('К сожалению, параметры для этой модификации не найдены.');
            }
        },
        error: function(xhr, ajaxOptions, thrownError) {
            console.log(thrownError + "\r\n" + xhr.statusText + "\r\n" + xhr.responseText);
        }
    });
}

function wBack(step) {
    $('.w-step').hide();
    $('#w-step-' + step).fadeIn(300);
}
</script>
