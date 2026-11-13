{{-- Модуль html «Новая карта» старого сайта (oc_module 46): разметка как есть,
     контакты — из config/shop.php. --}}
<div class="html-module">
<div class="contact-wrap">
	<div class="container-fluid pl-0 pr-0">
		

		
		<div class="row no-gutters">
			<div class="col-md-3 blockontact">
				<div class="infobanner img4 no-link">
					<div class="infobanner-content">

						<h3 class="infobanner-title">Контакты</h3>
						<div class="info-block">
							<span class="info-block-title">Наш адрес:</span>
							<span class="info-block-adres"><i class="fa fa-map-marker fa-fw"></i> {{ config('shop.address') }}</span>
						</div>
						<div class="info-block">
							<span class="info-block-title">Телефон:</span>
							<span class="info-block-adres"><i class="fa-fw fas fa-phone"></i> <a class="phnumb" href="tel:+{{ preg_replace('/\D/', '', config('shop.phone')) }}">{{ config('shop.phone') }}</a></span>
						</div>
						<div class="info-block"><span class="info-block-title">Email:</span> <span class="info-block-adres"><i class="fa fa-envelope fa-fw"></i> {{ config('shop.email') }}</span>
						</div>
					</div>
					<span class="infobanner-overlay"></span>
				</div>
			</div>
			{{-- Интерактивная карта через JavaScript API Яндекса. --}}
			<div class="col-md-12 map-area" data-yandex-map data-coordinates="{{ json_encode(config('shop.map_coordinates')) }}" data-map-key="{{ config('services.yandex_maps.key') }}"></div>
		</div>
	</div>
</div>
</div>
