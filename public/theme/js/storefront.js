/*
 * Интерфейс витрины. Функции меню взяты из common.js темы UniShop2 старого
 * сайта (uniMenuAim, uniMenuDropdownHeight, uniMenuDropdownPos, uniMenuMobile)
 * почти без изменений. Остальной common.js не подключаем: он ходит в
 * маршруты OpenCart (index.php?route=checkout/cart/add и т.п.).
 */
'use strict';

$(function() {
	if ('ontouchstart' in document.documentElement) $('body').addClass('touch-support');

	uniMenuAim();
	uniMenuDropdownHeight();
	uniMenuDropdownPos();
	uniMenuMobile();
	cookieNotice();

	// Блоки-ссылки темы: <div class="uni-href" data-href="…">.
	$('body').on('click', '.uni-href', function() {
		const href = $(this).data('href');

		if (typeof href === 'undefined' || href === '') return;

		if (($(this).attr('target') || $(this).data('target')) === '_blank') {
			window.open(href, '_blank');
		} else {
			location = href;
		}
	});

	$('.breadcrumb').scrollLeft(1000);
});

// Плашка про cookie: согласие помним в cookie notificationOffTime, как старый сайт.
function cookieNotice() {
	const notice = $('#uni-notification');

	if (!notice.length || document.cookie.match('notificationOffTime')) return;

	notice.removeClass('hidden');

	notice.find('.notification__button').on('click', () => {
		const hours = parseInt(notice.data('remember-hours'), 10) || 24;

		document.cookie = 'notificationOffTime=1; path=/; max-age=' + (hours * 3600) + '; SameSite=Lax';
		notice.fadeOut('fast', () => notice.remove());
	});
}

function uniMenuAim() {
	if($(window).width() > 992) {
		let blur_blocks = $('main, footer'), items = '.menu__level-2', timer = '', delay = 0, delay2 = 150;
		
		$('.menu.menu2 li.has-children').on('mouseenter', function() {
			timer = setTimeout(() => { 
				changeState($(this), 1);
			}, delay);
		}).on('mouseleave', function() {
			clearTimeout(timer);
			changeState($(this), 0);
		});
		
		$('.menu.menu1 .menu__collapse').menuAim({
			rowSelector:'.menu__level-1-li',
			submenuSelector:'*',
			activate:function(data) {
				changeState($(data), 1);
			},
			deactivate:function(data) {
				changeState($(data), 0);
			},
			exitMenu:function(data) {
				if(!$(data).parent().hasClass('new')) {
					return true;
				}
			}
		});

		const changeState = (elem, state) => {
			const child = elem.children(items);
			
			if(child.css('display') == 'none') child.css('opacity', 0);
			
			$('.menu__level-1-li:first, .menu__level-1-li:first .menu__level-2').removeClass('open');
			
			if(state) {
				elem.addClass('open');
				child.addClass('open').animate({opacity: 1}, delay2);
			} else {
				elem.removeClass('open');
				child.removeClass('open');
			}
		};
		
		const menuBlur = () => {
			if(typeof(uniJsVars) == 'undefined' || !uniJsVars.menu_blur) return;
			
			let blur_delay = 110, blur_timer = '';
		
			$('.menu1:not(.new), .menu2').on('mouseenter', () => {
				blur_timer = setTimeout(() => { 
					blur_blocks.addClass('blur');
				}, blur_delay);
			}).on('mouseleave', () => {
				clearTimeout(blur_timer);
				blur_blocks.removeClass('blur');
			});
			
			if($('.menu-wrapper.new').hasClass('show')) {
				blur_blocks.addClass('blur');
			} else {
				blur_blocks.removeClass('blur');
			}
		};
		
		menuBlur();
		
		const btn = '.header-menu__btn';
		
		$(btn).unbind('click');
	
		$(btn).on('click', function() {
			
			const elem = $(this).hasClass('fly-menu__menu-btn') ? '.fly-menu' : 'header', wrapper = elem+' .menu-wrapper.new', li = elem+' .menu1.new .menu__level-1-li';
				
			$(this).toggleClass('show');
			
			changeState($(li).not(':first-child'), 0);
			changeState($(li).first(), 1);
			
			$(wrapper).toggleClass('show');
		
			menuBlur();
		});
		
		$('main, footer').on('click touchstart', () => {
			$(items).removeClass('open');
			$('.menu__pm').removeClass('open');
			$('.menu .collapse').collapse('hide');
			
			$('body').removeClass('scroll-disabled');
			$('.menu-wrapper, .header-menu__btn').removeClass('show');
			
			blur_blocks.removeClass('blur');
		});
	} else {
		$('body').on('click', '.menu__pm', function() {
			$(this).toggleClass('open');
			$(this).next().collapse('toggle');
		});
	
		$('.menu-open, .menu-close').on('click', () => {
			$('body').toggleClass('scroll-disabled');
			$('.menu-wrapper').toggleClass('show');
			$('.fly-menu__block').removeClass('show');
		});
	}
};

function uniMenuDropdownHeight() {
	const menu_block = $('header .menu1:not(.new) .menu__level-2, header .menu2 .menu__level-2');
	
	if(menu_block.length) {
		const init = () => {
			if($(window).width() > 992) {
				menu_block.css('max-height', ($(window).height() - $('header .menu').offset().top - 100));
			} else {
				menu_block.css('max-height', '');
			}
		}
	
		init();
	
		$(window).resize(init);
	}
};

function uniMenuDropdownPos() {
	const menu_block = $('header .menu2');
	
	if(menu_block.length) {
		const init = () => {
			if($(window).width() > 992) {
				menu_block.find('.menu__level-2').each(function() {
					const child_pos = ($(this).parent().offset().left + $(this).outerWidth()) - (menu_block.offset().left + menu_block.outerWidth());
			
					if (child_pos > 0){
						$(this).css('margin-left', '-'+child_pos+'px');
					}
				});
			}
		}
		
		init();
	
		$(window).resize(init);
	}
};

function uniMenuMobile() {
	const menu1 = $('.menu1 .menu__collapse'), menu2 = $('.menu-right .menu__collapse');
	let lastWindowWidth = $(window).width();
		
	if(menu1.length && menu2.length) {
		const init = () => {
			let windowWidth = $(window).width();
		
			if(windowWidth < 992) {
				menu2.find('>li').addClass('new-items').appendTo(menu1);
			} else {
				menu1.find('.new-items').removeClass('new-items').appendTo(menu2);
			}
		
			lastWindowWidth = windowWidth;
		}
	
		init();
	
		$(window).resize(() => {
			if ($(window).width() != lastWindowWidth) {
				init();
			}
		});
	}
}
