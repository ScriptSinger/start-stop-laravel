$(function() {	
	const select_country = 'select[name="country_id"]',
		  select_region = 'select[name="zone_id"]',
		  input_city = 'input[name="city"]',
		  input_addr_1 = 'input[name="address_1"]',
		  input_addr_2 = 'input[name="address_2"]',
		  input_postcode = 'input[name="postcode"]',
		  block = '.dadata-suggestions',
		  backdrop = '.dadata-suggestions-backdrop',
		  limit = 20,
		  min_length = 2;
		  
	$(input_city+', '+input_addr_1+', '+input_addr_2).attr('autocomplete', 'off');
			
	$('body').on('keyup', input_city+', '+input_addr_1+', '+input_addr_2, function() {
			
		const $this = $(this);
			
		$(block).remove();
			
		if($this.val().length >= min_length) {

			let query = $this.val().replace('г. ', '').replace('г.', ''),
				query_type = ($this.attr('name') == 'city') ? 'city' : ($this.attr('name') == 'address_1' ? 'address_1' : ($this.attr('name') == 'address_2' ? 'address_2' : '')),
				top = $this.position().top + $this.outerHeight(), 
				left = $this.position().left + ($('#unicheckout').length ? 8 : 0),
				country_code = '*',
				region_code = '',
				city = ($(input_city).is(':visible')) ? $(input_city).val() : '',
				locations = [{'country_iso_code': country_code}];
				
			if($(select_country+' option:selected').data('country-code')) {
				country_code = $(select_country+' option:selected').data('country-code');
			}

			if(country_code && country_code != '*' && $(select_region).is(':visible') && $(select_region+' option:selected').data('region-code')) {
				region_code = country_code+'-'+$(select_region+' option:selected').data('region-code');
			}
			
			if(query_type == 'city') {
				locations = [{'country_iso_code': country_code, 'region_iso_code': region_code}];
			}
			
			if(city && (query_type == 'address_1' || query_type == 'address_2')) {
				locations = [{'country_iso_code': country_code, 'region_iso_code': region_code, 'city': city}];
			}
			
			if(city == '' && $(input_city).is(':visible')) {
				uniFlyAlert('warning', uniJsVars.dadata.text_error_city);
				return;
			}
			
			console.log(query)
			
			$.ajax({
				url: 'https://suggestions.dadata.ru/suggestions/api/4_1/rs/suggest/address',
				headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'Authorization': 'Token '+uniJsVars.dadata.token},
				data: JSON.stringify({query: query, locations: locations, 'restrict_value': true, 'count': limit}),
				dataType: 'json',
				type: 'post',				
				success: function(json) {
					if(json.suggestions) {	
						let city_arr = [],
							html = '<ul class="dadata-suggestions" style="top:'+top+'px;left:'+left+'px">';
								
						for (i = 0; i < json.suggestions.length; i++) {
							const result = json.suggestions[i],
								  city = result.data.city,
								  settl = result.data.settlement;
									
							if(query_type == 'city') {
								if(city != null && city_arr.indexOf(city) == -1 && (city.indexOf(query) !== -1 || city.toLowerCase().indexOf(query) !== -1)) {
									html += '<li data-type="city">'+city+'</li>';
										
									city_arr.push(city);
								}
										
								if(settl != null && city_arr.indexOf(settl) == -1 && (settl.indexOf(query) !== -1 || settl.toLowerCase().indexOf(query) !== -1)) {
									const item = (region_code) ? settl : (result.data.region_with_type+', '+result.data.settlement_with_type);
	
									if(city_arr.indexOf(result.data.settlement) == -1){
										html += '<li data-type="city">'+item+'</li>';
									}
								
									city_arr.push(item);
								}
							}
					
							if(query_type == 'address_1') {
								html += '<li data-type="address_1" data-postcode="'+result.data.postal_code+'">'+result.value+'</li>';
							}
							
							if(query_type == 'address_2') {
								html += '<li data-type="address_2" data-postcode="'+result.data.postal_code+'">'+result.value+'</li>';
							}
						}
						
						html += '</ul>';
								
						if(html.indexOf('<li') > -1) {
							$this.after(html);
							
							if(!$(backdrop).length) {
								$('body').append('<div class="'+backdrop.substr(1)+'"></div>');
							}
						}
						
						if($(html).find('li').length == 1 && ($(html).find('li').text() == query)) {
							$(block).remove();
							$(backdrop).remove();
							
							if ($('#unicheckout').length) {
								uniCheckoutUpdate();
							}
						}
						
					}
				},
				error: function(xhr, ajaxOptions, thrownError) {
					console.log(thrownError + "\r\n" + xhr.statusText + "\r\n" + xhr.responseText);
				}
			});
		}
	});	
	
	$('body').on('click', '.dadata-suggestions li', function() {
		const $type = $(this).data('type'), $text = $(this).text();
							
		if($type == 'city') {
			$(input_city).val($text);
			$(input_addr_1+', '+input_addr_2).val('');
		}
								
		if($type == 'address_1') {
			$(input_addr_1).val($text).keyup();
		}
					
		if($type == 'address_2') {
			$(input_addr_2).val($text).keyup();
		}
					
		if($(this).data('postcode') && $(input_postcode).length)  {
			$(input_postcode).val($(this).data('postcode'));
		} else {
			$(input_postcode).val('');
		}
				
		$(block).remove();
		$(backdrop).remove();
	});
	
	$('body').on('click', backdrop, function() {
		$(block).remove();
		$(backdrop).remove();
		if ($('#unicheckout').length) {
			uniCheckoutUpdate();
		}
	});
	
	$(document).ajaxStop(() => {
		$(input_city+', '+input_addr_1+', '+input_addr_2).attr('autocomplete', 'off');
	});
});