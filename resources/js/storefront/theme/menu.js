/*
 * Меню категорий и главное меню — функции из common.js темы UniShop2 старого
 * сайта почти без изменений (uniMenuAim, uniMenuDropdownHeight,
 * uniMenuDropdownPos, uniMenuMobile, uniMenuUpd): на их поведение
 * завязана вёрстка темы.
 */
import $ from 'jquery';
import { escapeHtml } from '../utils';

export function uniMenuAim() {
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
	}
};

/**
 * Мобильное меню: кнопка «Категории», крестик и «+» у разделов. В теме они
 * навешивались только если страница открылась на узком экране — после
 * поворота планшета или перехода с широкого окна кнопка не работала.
 * Кнопки и так видны только на узком экране, поэтому навешиваем всегда, один раз.
 */
export function uniMenuMobileToggle() {
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

export function uniMenuDropdownHeight() {
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

export function uniMenuDropdownPos() {
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

export function uniMenuMobile() {
	const menu1 = $('header .menu1 .menu__collapse'), menu2 = $('header .menu-right .menu__collapse');
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

// Лишние пункты главного меню на узком экране складываются в «…» — uniMenuUpd из common.js темы.
export function uniMenuUpd(block) {
	
	if(!$(block).length) return;
	
	const init = () => {
		let menu_block = $(block), menu_items = menu_block.children('.menu__level-1-li:not(.menu__additional)');
			
		menu_items.css('display', '');
			
		if($(window).width() < 992) return;
		
		menu_block.find('.menu__additional').remove();
		
		let coord = menu_block.offset().left + menu_block.width(), flag = false, new_items = '';
			
		if(!menu_items.length || Math.floor(menu_items.last().offset().left + menu_items.last().width()) <= coord) return;
		
		menu_items.each(function() {
			if($(this).offset().left + $(this).width() > coord - 60) {
				let item = $(this).find('> a'), item_child = $(this).find('.menu__level-2-a'), new_child_items = '';
				
				if(item_child.length) {
					new_child_items = '<div class="menu__level-3"><ul class="menu__level-3-ul">';
				
					item_child.each(function() {
						new_child_items += '<li class="menu__level-3-li"><a class="menu__level-3-a';
						
						if(typeof($(this).attr('href')) != 'undefined') {
							new_child_items += '" href="'+escapeHtml($(this).attr('href'))+'">';
						} else {
							new_child_items += ' disabled">';
						}
						
						new_child_items += escapeHtml($(this).text())+'</a></li>';
					});
					
					new_child_items += '</ul></div>';
				}
				
				new_items += '<div class="menu__level-2-ul col-md-12"><a class="menu__level-2-a';
				
				if(new_child_items) {
					new_items += ' has-children';
				}
				
				if(typeof(item.attr('href')) != 'undefined') {
					new_items += '" href="'+escapeHtml(item.attr('href'))+'">';
				} else {
					new_items += ' disabled">';
				}

				new_items += escapeHtml(item.text())+'</a>'+new_child_items+'</div>';

				$(this).hide();
				
				flag = true;
			} else {
				$(this).show();
			}
		});
		
		if (flag) {
			if (!menu_block.find('.menu__additional').length) {
				let html = '<li class="menu__level-1-li menu__additional has-children">';
				    html += '<a class="menu__level-1-a additional"><i class="fa fa-ellipsis-h"></i></a>';
				    html += '<div class="menu__level-2 column-1"></div>';
				    html += '</li>';
					
				menu_block.append(html);
				
				uniMenuAim();
			}
				
			menu_block.find('.menu__additional .menu__level-2').html(new_items);
		}
	}
	
	init();
	
	$(window).resize(init);
};
