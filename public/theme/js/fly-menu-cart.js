function uniFlyMenu() {
	if($('#unicheckout').length) return;
	
	const prodPage = uniJsVars.fly_menu.product && $('#product').length;
	
	const init = () => {		
		$('#fly-menu').remove();
		
		let windowWidth = $(window).width(),
			breakpoint = 992,
			desktop_menu = (uniJsVars.fly_menu.desktop && windowWidth > breakpoint) ? true : false,
			mobile_menu = (uniJsVars.fly_menu.mobile != 0 && windowWidth <= breakpoint) ? true : false;
			
		if(!desktop_menu && !mobile_menu) return;
		
		const new_class = (mobile_menu && uniJsVars.fly_menu.mobile == 'bottom') ? 'bottom show' : '',
			  show_label = mobile_menu && uniJsVars.fly_menu.label ? true : false;
		
		let html = '<div id="fly-menu" class="fly-menu '+new_class+' '+(show_label ? 'show-label' : ' ')+'">';
			
		html += '<div class="container"><div class="row">';
			
		if(desktop_menu) {
			if(prodPage) {
				html += '<div class="fly-menu__product">';
				html += '<div class="fly-menu__product-name"><span>'+$('.breadcrumb-h1 h1').text()+'</span></div>';
				html += '<div class="fly-menu__product-price price">'+$('.product-page__price').html()+'</div>';
				
				const btn = $('#product').find('.product-page__add-to-cart');
				
				if(btn.length) html += '<button type="button" class="fly-menu__product-btn '+btn.attr('class').replace('btn-xl', '')+'" data-pid="'+btn.data('pid')+'">'+btn.html()+'</button>';
				
				html += '</div>';
			} else {
				html += '<div class="fly-menu__menu">';
				html += '<button class="fly-menu__menu-btn header-menu__btn">'+$('.menu__header').html()+'</button>';
				html += '<div class="container" style="position:absolute"><div class="menu-wrapper new"><nav class="menu menu1 new">'+$('header #menu').html()+'</nav></div></div>';
				html += '</div>';
				html += '<div class="fly-menu__search">'+$('header #search').html()+'</div>';
			}
			
			const telephone = {'number': $('.header-phones__main').html(), 'href': $('.header-phones__main').attr('href')};
			
			html += '<div class="fly-menu__phone' +(telephone.href ? ' uni-href" data-href="'+telephone.href+'"' : '"')+'>'+telephone.number+'</div>';
		}
		
		if(mobile_menu){
			if(uniJsVars.fly_menu.home) {
				html += '<div class="fly-menu__block fly-menu__home uni-href" data-href="/" >';
				html += '<i class="fly-menu__icon fly-menu__icon-menu fas fa-home"></i>';
				if(show_label) html += '<div class="fly-menu__label">'+uniJsVars.fly_menu.text_home+'</div>';
				html += '</div>';
			}
			
			html += '<div class="fly-menu__block fly-menu__menu-m">';
			html += '<i class="fly-menu__icon fly-menu__icon-menu fas fa-bars"></i>';
			if(show_label) html += '<div class="fly-menu__label">'+uniJsVars.fly_menu.text_catalog+'</div>';
			html += '</div>';
			
			html += '<div class="fly-menu__block fly-menu__search-m">';
			html += '<i class="fly-menu__icon fly-menu__icon-search fas fa-search"></i>';
			if(show_label) html += '<div class="fly-menu__label">'+uniJsVars.fly_menu.text_search+'</div>';
			html += $('header #search').html();
			html += '</div>';
		}
		
		let show_phone = 0;
		
		if(mobile_menu && show_phone){
			html += '<div class="fly-menu__block fly-menu__telephone">';
			html += '<i class="fly-menu__icon fa fa-phone"></i>';
			html += '<ul class="fly-menu__telephone-dropdown dropdown-menu dropdown-menu-right">'+$('.header-phones__ul').html()+'</ul>';
			html += '</div>';
		} else {
			html += '<div class="fly-menu__block fly-menu__account">';
			html += '<i class="fly-menu__icon fly-menu__icon-account far fa-user"></i>';
			html += '<ul class="fly-menu__account-dropdown dropdown-menu">'+$('#top #account ul').html()+'</ul>';
			if(show_label) html += '<div class="fly-menu__label">'+uniJsVars.fly_menu.text_account+'</div>';
			html += '</div>';
		}
		
		if(uniJsVars.fly_menu.wishlist == 1 && desktop_menu || uniJsVars.fly_menu.wishlist == 2 && mobile_menu || uniJsVars.fly_menu.wishlist == 3){
			html += '<div class="fly-menu__block fly-menu__wishlist uni-href" data-href="'+$('.top-menu__wishlist-btn').data('href')+'">';
			html += '<i class="fly-menu__icon fly-menu__icon-wishlist far fa-heart"></i>';
			html += '<span class="fly-menu__wishlist-total fly-menu__total">'+$('.top-menu__wishlist-total').html()+'</span>';
			if(show_label) html += '<div class="fly-menu__label">'+uniJsVars.fly_menu.text_wishlist+'</div>';
			html += '</div>';
		}
			
		if(uniJsVars.fly_menu.compare == 1 && desktop_menu || uniJsVars.fly_menu.compare == 2 && mobile_menu || uniJsVars.fly_menu.compare == 3){
			html += '<div class="fly-menu__block fly-menu__compare uni-href" data-href="'+$('.top-menu__compare-btn').data('href')+'">';
			html += '<i class="fly-menu__icon fly-menu__icon-compare fas fa-align-right"></i>';
			html += '<span class="fly-menu__compare-total fly-menu__total">'+$('.top-menu__compare-total').html()+'</span>';
			if(show_label) html += '<div class="fly-menu__label">'+uniJsVars.fly_menu.text_compare+'</div>';
			html += '</div>';
		}

		html += '<div class="fly-menu__block fly-menu__cart">';
		html += '<i class="fly-menu__icon fly-menu__icon-cart fa fa-shopping-bag" onclick="uniModalWindow(\'modal-cart\', \'\', \''+uniJsVars.modal_cart.text_heading+'\', $(\'header\').find(\'.header-cart__dropdown\').html())"></i>';
		html += '<span class="fly-menu__cart-total fly-menu__total">'+$('header .header-cart__total-items').text()+'</span>';
		if(show_label) html += '<div class="fly-menu__label">'+uniJsVars.fly_menu.text_cart+'</div>';
		html += '</div>';
		
		html += '</div></div></div>';
		
		if(!$('#fly-menu').length) {
			$('html body').append(html);
				
			const menuBlock = $('.fly-menu__block'), menuBlockIcon = $('.fly-menu__block .fly-menu__icon')
					
			menuBlockIcon.on('click', function() {
				const $parent = $(this).parent();
				
				menuBlock.toggleClass('show').not($parent).removeClass('show');
				
				if(mobile_menu) {
					$('body').removeClass('scroll-disabled');
					
					if(($parent.hasClass('fly-menu__search-m') || $parent.hasClass('fly-menu__account')) && !$('.fly-menu-backdrop').length) {
						$('.fly-menu').before('<div class="fly-menu-backdrop"></div>');
					}
					
					if($parent.hasClass('show')) {
						if($parent.hasClass('fly-menu__menu-m')) {
							$('.menu-open').click();
						}
						
						if($parent.hasClass('fly-menu__search-m') || $parent.hasClass('fly-menu__account')) {
							$('body').addClass('scroll-disabled');
						}
					
						if($parent.hasClass('fly-menu__search-m')) {
							$('.fly-menu__search-m .form-control').focus();
						}
					} else {
						$('.fly-menu-backdrop').remove();
					}
					
					const account_ul = $('.fly-menu__account-dropdown');
					
					if(account_ul.offset().left + account_ul.outerWidth() > $(window).width()) {
						account_ul.css('margin-left', -((account_ul.offset().left + account_ul.outerWidth()) - $(window).width()) - 1)
					}
				}
			});
			
			uniSearch.clearBtn();
			
			$('.fly-menu__account li').on('click', function() {
				$(this).closest('.fly-menu__block').removeClass('show');
			});
			
			$('body').on('hide.bs.modal', '.modal', () => {
				$('.fly-menu-backdrop').click();
			});
			
			$('html body').on('click', '.fly-menu-backdrop, .menu-close, main, footer', () => {
				$('body').removeClass('scroll-disabled');
				menuBlock.removeClass('show');
				$('.fly-menu-backdrop').remove();
			});
						
			if(desktop_menu) {
				if(prodPage) {
					$(document).on('change', '.product-page input, .product-page select', () => {
						setTimeout(() => { 
							$('.fly-menu__product-price').html($('.product-page__price').html());
						}, 350);
					});
					
					$('.fly-menu__product-btn').css('cursor', 'pointer').click(() => {
						$('#button-cart').click();
					});
					
					$('.fly-menu__product-name').mouseover(function() {
						var boxWidth = $(this).width();
			
						$text = $('.fly-menu__product-name span');
						$textWidth = $('.fly-menu__product-name span').width();

						if ($textWidth > boxWidth) {
							$($text).animate({left: -(($textWidth+20) - boxWidth)}, 500);
						}
					}).mouseout(function() {
						$($text).stop().animate({left: 0}, 500);
					});
				} else {
					$(document).on('click', '.fly-menu__menu-btn', () => {
						$('header .menu-wrapper.new, header .header-menu__btn').removeClass('show');
					});
					
					uniMenuAim();
				}
			}
		}
	};
	
	init();
	
	let windowWidth = $(window).width();

	$(window).resize(function() {
		if($(this).width() != windowWidth) {
			windowWidth = $(this).width();
			init();
		}
	});
	
	$(window).scroll(function(){
		if($(this).scrollTop() > 200) {
			$('#fly-menu').addClass('show');
		} else {
			$('#fly-menu, #fly-menu .row > div').removeClass('show');
			if($('.fly-menu__menu-btn').hasClass('show')) {
				$('.fly-menu__menu-btn').click();
			}
		}
	});
}

$(() => {
	uniFlyMenu();
});