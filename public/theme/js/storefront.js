/*
 * Интерфейс витрины. Функции меню взяты из common.js темы UniShop2 старого
 * сайта (uniMenuAim, uniMenuDropdownHeight, uniMenuDropdownPos, uniMenuMobile)
 * почти без изменений. Остальной common.js не подключаем: он ходит в
 * маршруты OpenCart (index.php?route=checkout/cart/add и т.п.).
 */
'use strict';

// Значения из настроек темы старого сайта (uniJsVars в его <head>).
var uni_touch_support = 'ontouchstart' in document.documentElement;
var items_on_mobile = 2, module_on_mobile = 'grid';

$(function() {
	if (uni_touch_support) $('body').addClass('touch-support');

	uniMenuAim();
	uniMenuDropdownHeight();
	uniMenuDropdownPos();
	uniMenuMobile();
	uniMenuUpd('header .menu2 .menu__collapse');
	cookieNotice();
	uniFlyMenu();
	uniShowMore();

	// Поиск на телефоне — копия формы из шапки рядом с «Категориями», как на старом сайте.
	$('#search .header-search').clone().appendTo('#search2');
	phoneMask(document);

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

	// Поля поиска (шапка, телефон, страница поиска) заполняются одинаково,
	// крестик очищает все — clearBtn из common.js темы.
	const searchInputs = 'input[name="search"]';

	$('body').on('input', searchInputs, function() {
		$(searchInputs).not(this).val(this.value);
		$('.search-btn-clear').toggleClass('show', this.value !== '');
	});

	$('body').on('click', '.search-btn-clear', function() {
		$(searchInputs).val('');
		$('.search-btn-clear').removeClass('show');
	});

	$('.breadcrumb').scrollLeft(1000);
});

// Маска телефона, как в формах старого сайта (jquery.maskedinput).
function phoneMask(root) {
	if ($.fn.mask) $(root).find('input[type=tel]').mask('+7 (999) 999-99-99');
}

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

// Раскладка блоков главной (сетка/карусель по ширине экрана) — плагин uniModules из common.js темы.
(function($){
	var Modules = {
		init:function(options, el) {
            var base = this;
			
			base.$elem = $(el);
			base.$elem2 = $(el).children();
			base.options = $.extend({}, $.fn.uniModules.options, options);
			
			base.load();
        },
		load:function() {
			var base = this;
			
			base.wrapper = (base.$elem2.closest('.tab-content').length) ? base.$elem2.closest('.tab-content') : base.$elem;
			
			if((base.options.type == 'grid' && module_on_mobile == 'carousel' && base.wrapper.width()+20 < 768) || base.wrapper.closest('#column-left, #column-right').length) {
				base.options.type = 'carousel';
			}
			
			if(base.wrapper.closest('#column-left, #column-right').length) {
				base.options.items = {0: {items: 1}};
			}
			
			if (base.options.type == 'grid') {
				base.$elem2.children().wrap('<div class="uni-module__item" style="width:'+base.items()+'"></div>');
			} else {
				base.$elem2.addClass('owl-carousel').owlCarousel({
					responsive:base.options.items,
					responsiveBaseElement:base.wrapper,
					dots:base.options.dots,
					mouseDrag:false,
					loop:base.options.loop,
					autoplay:base.options.autoplay,
					nav:true,
					navText:['<i class="fas fa-chevron-left"></i>', '<i class="fas fa-chevron-right"></i>'],
				});
				
				if(base.$elem2.width() == 0) {
					const item = base.$elem2.find('.owl-item'), item_width = base.items();
					
					item.css({width: item_width});
					base.$elem2.find('.owl-stage').css({width:item.length * item_width});
				}
			}
			
			base.$elem2.addClass('load-complete');
			base.responsive();
		},
		items:function() {
			var base = this, match = -1, width = base.wrapper.width();
			
			width += (base.wrapper.attr('class') == 'tab-content' && width < 520) ? 10 : 20;
			
			$.each(base.options.items, (breakpoint) => {
				if (breakpoint <= width && breakpoint > match) {
					match = Number(breakpoint);
				}
			});
			
			const items = base.options.items[match]['items']
			
			return (base.options.type == 'carousel') ? width/items : 100/items+'%';
		},
		responsive:function() {
            var base = this, lastWindowWidth = $(window).width();
			
			base.resizer = () => {
                if ($(window).width() != lastWindowWidth || uni_touch_support) {
					if (base.options.type == 'grid') {	
						base.$elem2.children().css('width', base.items());
					}
                }
            };
			
			$(window).resize(base.resizer);
        }
	};
	
	$.fn.uniModules = function(options) {		
		return this.each(function() {
            if ($(this).data('uni-modules-init') === true) {
                return false;
            }
			
            $(this).data('uni-modules-init', true);
			
            var module = Object.create(Modules);
            module.init(options, this);
        });
	};
	
	if(typeof(items_on_mobile) == 'undefined') items_on_mobile = 2;
	
	$.fn.uniModules.options = {
		type 	   :'carousel',
		items	   :{0:{items:items_on_mobile},700:{items:3},992:{items:4},1400:{items:5}},
		autoheight :[],
		dots	   :true,
		loop	   :false,
		autoplay   :false
	};
})(jQuery);

// Модальное окно — та же разметка, что uniModalWindow в common.js темы.
function uniModalWindow(id, type, title, data) {
	$('#' + id).remove();

	const html = '<div id="' + id + '" class="modal">'
		+ '<div class="modal-dialog modal-' + type + '"><div class="modal-content">'
		+ '<div class="modal-header"><button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>'
		+ '<h4 class="modal-title"></h4></div>'
		+ '<div class="modal-body"></div>'
		+ '</div></div></div>';

	const modal = $(html).appendTo('body');
	modal.find('.modal-title').text(title);
	modal.find('.modal-body').html(data);
	modal.modal('show');
}

// Ссылки с data-modal-url открывают форму (быстрый заказ, заказ звонка) в
// окне; без JavaScript ведут на обычную страницу с той же формой.
$('body').on('click', '[data-modal-url]', function(e) {
	e.preventDefault();

	const title = $(this).data('modal-title') || '';

	$.get($(this).data('modal-url'), (html) => {
		uniModalWindow('modal-form', '', title, html);
		phoneMask('#modal-form');
	});
});

// Отправка формы в окне: успех — сообщение вместо формы, ошибки — под полями.
$('body').on('submit', '.modal .js-modal-form', function(e) {
	e.preventDefault();

	const form = $(this), button = form.find('[type=submit]');

	button.prop('disabled', true);
	form.find('.has-error').removeClass('has-error');
	form.find('.help-block.js-error').remove();

	$.ajax({url: form.attr('action'), type: 'post', data: form.serialize(), dataType: 'json'})
		.done((json) => form.replaceWith($('<div class="alert alert-success"></div>').text(json.message)))
		.fail((xhr) => {
			const errors = (xhr.responseJSON && xhr.responseJSON.errors) || {};

			if ($.isEmptyObject(errors)) {
				form.prepend($('<div class="help-block js-error text-danger"></div>').text('Не получилось отправить. Попробуйте ещё раз или позвоните нам.'));
			}

			$.each(errors, (field, messages) => {
				const group = form.find('[name="' + field + '"]').closest('.form-group');

				group.addClass('has-error').append($('<span class="help-block js-error"></span>').text(messages[0]));
			});
		})
		.always(() => button.prop('disabled', false));
});

// Всплывающее уведомление — разметка uniFlyAlert темы; 5 секунд, без анимации,
// как в настройках старого сайта.
function uniFlyAlert(type, message) {
	const icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
	const block = $('<div class="uni-alert alert-' + type + '" style="top:50px"><i class="uni-alert__icon fa ' + icon + '"></i><div></div><i class="uni-alert__icon fas fa-times"></i></div>');

	$('.uni-alert').remove();
	block.find('div').text(message);
	block.find('.fa-times').on('click', () => block.remove());
	block.appendTo('body');

	setTimeout(() => block.remove(), 5000);
}

// Закладки и сравнение без перезагрузки: кнопки с formaction на
// /wishlist/… и /compare-products/… (в карточках и на странице товара).
$('body').on('click', '[data-saved-list]', function(e) {
	e.preventDefault();

	const button = $(this), list = button.data('saved-list');

	$.ajax({url: button.attr('formaction'), type: 'post', data: {_token: $('meta[name=csrf-token]').attr('content')}, dataType: 'json'})
		.done((json) => {
			$('.header-' + list + '__total-items, .top-menu__' + list + '-total').text(json.count);
			uniFlyAlert('success', json.message);
		})
		.fail(() => uniFlyAlert('danger', 'Не получилось. Попробуйте ещё раз.'));
});

// Корзина без перезагрузки, как cart.add/uniCartUpd темы: «В корзину» и
// значок в шапке открывают окно с мини-корзиной; в окне можно менять
// количество и удалять. Без JavaScript работают обычные формы.
function uniMiniCartOpen() {
	uniModalWindow('modal-cart', '', 'Корзина', $('header .header-cart__dropdown').html());
}

function uniMiniCartUpdate(json) {
	$('header .header-cart__dropdown').html(json.html);
	$('.header-cart__total-items, .fly-menu__cart-total').text(json.count);
	uniChangeBtn();
}

$('body').on('click', '[data-mini-cart-open]', function(e) {
	e.preventDefault();
	uniMiniCartOpen();
});

$('body').on('submit', 'form[data-add-to-cart]', function(e) {
	e.preventDefault();

	const form = $(this);

	$.ajax({url: form.attr('action'), type: 'post', data: form.serialize(), dataType: 'json'})
		.done((json) => {
			uniMiniCartUpdate(json);
			uniMiniCartOpen();
		})
		.fail(() => uniFlyAlert('danger', 'Не получилось добавить в корзину. Попробуйте ещё раз.'));
});

$('body').on('submit', 'form[data-mini-cart]', function(e) {
	e.preventDefault();

	$.ajax({url: $(this).attr('action'), type: 'post', data: $(this).serialize(), dataType: 'json'})
		.done((json) => {
			// На странице оформления пересчитать надо и её.
			if ($('#unicheckout__form').length) {
				location.reload();

				return;
			}

			uniMiniCartUpdate(json);
			$('#modal-cart .modal-body').html(json.html);
		})
		.fail(() => uniFlyAlert('danger', 'Не получилось. Попробуйте ещё раз.'));
});

$('body').on('click', '.header-cart__quantity .qty-switch__btn', function() {
	const input = $(this).siblings('.qty-switch__input');
	const value = (parseInt(input.val(), 10) || 1) + Number($(this).data('step'));

	input.val(Math.min(Math.max(value, input.data('minimum')), input.data('maximum')));
	input.closest('form').trigger('submit');
});

$('body').on('change', '.header-cart__quantity .qty-switch__input', function() {
	$(this).closest('form').trigger('submit');
});

// Товары, которые уже в корзине: кнопка «В корзине» с галочкой — uniChangeBtn темы.
function uniChangeBtn() {
	const products = String($('header .header-cart__wrapper').data('products') || '').split(',').map(Number);

	$('.add_to_cart[data-pid]').each(function() {
		const inCart = products.indexOf($(this).data('pid')) !== -1;

		if (inCart && !$(this).hasClass('in_cart')) {
			$(this).data('original', $(this).html());
			$(this).addClass('in_cart').html('<i class="fa fa-check"></i><span>В корзине</span>');
		} else if (!inCart && $(this).hasClass('in_cart')) {
			$(this).removeClass('in_cart').html($(this).data('original'));
		}
	});
}

$(uniChangeBtn);

$(function() {
	const notice = $('body').data('notice');

	if (notice) uniFlyAlert('success', notice);
});

// Лишние пункты главного меню на узком экране складываются в «…» — uniMenuUpd из common.js темы.
function uniMenuUpd(block) {
	
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
							new_child_items += '" href="'+$(this).attr('href')+'">';
						} else {
							new_child_items += ' disabled">';
						}
						
						new_child_items += $(this).text()+'</a></li>';
					});
					
					new_child_items += '</ul></div>';
				}
				
				new_items += '<div class="menu__level-2-ul col-md-12"><a class="menu__level-2-a';
				
				if(new_child_items) {
					new_items += ' has-children';
				}
				
				if(typeof(item.attr('href')) != 'undefined') {
					new_items += '" href="'+item.attr('href')+'">';
				} else {
					new_items += ' disabled">';
				}

				new_items += item.text()+'</a>'+new_child_items+'</div>';

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

function uniScrollTo(target, time = 200) {
	$('html, body').animate({scrollTop: $(target).offset().top - 150}, time);
}

// Кнопка «наверх» появляется после прокрутки — fly-block темы.
$('body').on('click', '[data-scroll-top]', () => uniScrollTo('body'));
$(window).on('scroll', function() {
	$('.fly-block__scrollup').toggleClass('visible', $(this).scrollTop() > 190);
});

// Шапка, прилипающая при прокрутке, — uniFlyMenu из fly-menu-cart.js темы
// с настройками старого сайта: на компьютере меню категорий, поиск, телефон
// и корзина; на телефоне сверху — «Главная», меню, поиск и корзина.
// Личного кабинета у нас нет, поэтому его значка тоже нет.
function uniFlyMenu() {
	if ($('#unicheckout__form').length) return;

	const breakpoint = 992;

	const init = () => {
		$('#fly-menu').remove();

		const desktop = $(window).width() > breakpoint;
		let html = '<div id="fly-menu" class="fly-menu"><div class="container"><div class="row">';

		if (desktop) {
			html += '<div class="fly-menu__menu">';
			html += '<button class="fly-menu__menu-btn header-menu__btn">' + $('header .menu__header').html() + '</button>';
			html += '<div class="container" style="position:absolute"><div class="menu-wrapper new"><nav class="menu menu1 new">' + $('header #menu').html() + '</nav></div></div>';
			html += '</div>';
			html += '<div class="fly-menu__search">' + $('header #search').html() + '</div>';

			const phone = $('header .header-phones__main');
			html += '<div class="fly-menu__phone uni-href" data-href="' + phone.attr('href') + '">' + phone.html() + '</div>';
		} else {
			html += '<div class="fly-menu__block fly-menu__home uni-href" data-href="/"><i class="fly-menu__icon fly-menu__icon-menu fas fa-home"></i></div>';
			html += '<div class="fly-menu__block fly-menu__menu-m"><i class="fly-menu__icon fly-menu__icon-menu fas fa-bars"></i></div>';
			html += '<div class="fly-menu__block fly-menu__search-m"><i class="fly-menu__icon fly-menu__icon-search fas fa-search"></i>' + $('header #search').html() + '</div>';
		}

		html += '<div class="fly-menu__block fly-menu__cart">';
		html += '<i class="fly-menu__icon fly-menu__icon-cart fa fa-shopping-bag" data-mini-cart-open></i>';
		html += '<span class="fly-menu__cart-total fly-menu__total">' + $('header .header-cart__total-items').text() + '</span>';
		html += '</div>';
		html += '</div></div></div>';

		$('body').append(html);

		const blocks = $('.fly-menu__block');

		$('.fly-menu__block .fly-menu__icon').on('click', function() {
			const parent = $(this).parent();

			blocks.toggleClass('show').not(parent).removeClass('show');

			if (desktop) return;

			$('body').removeClass('scroll-disabled');

			if (parent.hasClass('fly-menu__search-m') && !$('.fly-menu-backdrop').length) {
				$('.fly-menu').before('<div class="fly-menu-backdrop"></div>');
			}

			if (parent.hasClass('show')) {
				if (parent.hasClass('fly-menu__menu-m')) $('.menu-open').trigger('click');

				if (parent.hasClass('fly-menu__search-m')) {
					$('body').addClass('scroll-disabled');
					parent.find('.form-control').trigger('focus');
				}
			} else {
				$('.fly-menu-backdrop').remove();
			}
		});

		if (desktop) {
			uniMenuAim();
		}
	};

	$('body').on('hide.bs.modal', '.modal', () => $('.fly-menu-backdrop').trigger('click'));
	$('body').on('click', '.fly-menu-backdrop, .menu-close, main, footer', () => {
		$('body').removeClass('scroll-disabled');
		$('.fly-menu__block').removeClass('show');
		$('.fly-menu-backdrop').remove();
	});
	$(document).on('click', '.fly-menu__menu-btn', () => {
		$('header .menu-wrapper.new, header .header-menu__btn').removeClass('show');
	});

	init();

	let windowWidth = $(window).width();

	$(window).on('resize', function() {
		if ($(this).width() !== windowWidth) {
			windowWidth = $(this).width();
			init();
		}
	});

	$(window).on('scroll', function() {
		if ($(this).scrollTop() > 200) {
			$('#fly-menu').addClass('show');
		} else {
			$('#fly-menu, #fly-menu .row > div').removeClass('show');

			if ($('.fly-menu__menu-btn').hasClass('show')) $('.fly-menu__menu-btn').trigger('click');
		}
	});
}

// «Показать ещё» под списком товаров — showmore-ajaxpagination.js темы:
// следующая страница дописывается к текущей, адрес меняется на неё.
function uniShowMore() {
	if (!$('.product-layout').length || !$('.pagination').length) return;

	const nextUrl = () => $('.pagination').find('.active').next().find('a').attr('href');
	const button = $('<div class="show-more" style="margin:15px 0 30px;text-align:center"><button type="button" class="show-more__btn btn btn-xl btn-default"><i class="show-more__icon fas fa-sync-alt"></i><span>Показать еще</span></button></div>');

	if (!nextUrl()) return;

	$('.pagination').first().before(button);

	button.on('click', '.show-more__btn', function() {
		const url = nextUrl(), icon = $(this).find('.show-more__icon');

		if (!url) return;

		icon.addClass('spin');

		$.get(url, (data) => {
			const result = $('<div>').html(data);

			$('.products-block').append(result.find('.products-block').html());
			$('.pagination').html(result.find('.pagination').html());
			$('.pagination-text').text(result.find('.pagination-text').text());
			button.toggle(!!nextUrl());
			uniChangeBtn();
			window.history.pushState('', '', url);
		}).always(() => icon.removeClass('spin'));
	});
}
