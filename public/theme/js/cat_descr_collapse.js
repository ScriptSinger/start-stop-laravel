$(() => {
	const cat_desc_collapse = () => {
		const parent_block = '.category-info',
			collapse_block = '.category-info__description',
			height_1 = $(parent_block).outerHeight(), 
			height_2 = $(collapse_block).outerHeight(),
			text_expand = uniJsVars.cat_descr_collapse.text_expand,
			text_collapse = uniJsVars.cat_descr_collapse.text_collapse,
			collapse_btn = 'category-info__btn';
				  
		if(height_2 > height_1) {
			$(parent_block).css({'height': height_1, 'max-height': '100%'}).append('<a class="'+collapse_btn+'"><span class="category-info__span">'+text_expand+'</span></a>');

			$('html body').on('click', '.'+collapse_btn, function() {
				if(!$(this).data('open')) {
					newHeight = height_2 + $('.'+collapse_btn).outerHeight() + ($('.'+collapse_btn).outerHeight()/2);
					$(this).data('open', true).children().text(text_collapse);
				} else {
					newHeight = height_1;
					$(this).data('open', false).children().text(text_expand);
				}

				$(this).parent().animate({height: newHeight}, 300);
			});
		}
	}
				
	setTimeout(() => {
		cat_desc_collapse();
	}, 300);
});