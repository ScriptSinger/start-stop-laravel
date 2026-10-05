$(function() {
	$('body').on('mouseenter', '.product-thumb', function() {
		
		if($(window).width() < 767 || uni_touch_support) return;
		
		const product = $(this),
			container = product.parent(),
			carousel = product.closest('.owl-carousel'),
			descr = uniJsVars.descr_hover ? product.find('.description') : '',
			descr_h = descr.length ? descr.height() : 0,
			attr = uniJsVars.attr_hover ? product.find('.attribute') : '',
			attr_h = attr.length ? attr.height() : 0,
			options = uniJsVars.option_hover ? product.find('.option') : '',
			options_h = options.length ? options.height() : 0,
			product_w = product[0].getBoundingClientRect().width,
			product_h = product[0].getBoundingClientRect().height;
		
		if((!descr.length && !attr.length && !options.length) || (!descr_h && !attr_h && !options_h)) return;
		if(container.hasClass('product-list') || container.hasClass('product-price') || product.data('hover')) return;
			
		container.css({height: product_h+20});
			
		if(carousel.length) {
			if(container.hasClass('owl-item')) container.addClass('select');
			if(container.hasClass('owl-carousel')) container = container.find('.owl-item.select');
			
			product.css({left: product.offset().left - carousel.offset().left, width: product_w}).detach().addClass('hover').prependTo(carousel).data('hover', true);
		} else {
			product.css({width: product_w}).addClass('hover').data('hover', true);
		}
			
		if(descr) descr.show();
		if(attr) attr.show();
		if(options && options.find('[name^="option"]').length) options.show();

		product.on('mouseleave', (e) => {
			if(descr) descr.hide();
			if(attr) attr.hide();
			if(options && options.children().length) options.hide();
			if(carousel.length) product.detach().appendTo(container);
			
			product.css({left: '', width: ''}).removeClass('hover').unbind('mouseleave').data('hover', false);
			container.css({height: ''}).removeClass('select');
		});
		
		product.on('mouseleave', 'select', (e) => {
			e.stopPropagation();
		});
	});	
});