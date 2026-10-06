/*
 * Раскладка блоков товаров и категорий (сетка или карусель по ширине) —
 * плагин uniModules из common.js темы UniShop2 старого сайта.
 * Блок размечается data-uni-module="carousel|grid" и, при необходимости,
 * data-uni-module-items='{"0":{"items":1},…}'.
 */
import $ from 'jquery';
import { touchSupport, itemsOnMobile, moduleOnMobile } from './settings';

const uni_touch_support = touchSupport, items_on_mobile = itemsOnMobile, module_on_mobile = moduleOnMobile;

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
	
	
	$.fn.uniModules.options = {
		type 	   :'carousel',
		items	   :{0:{items:items_on_mobile},700:{items:3},992:{items:4},1400:{items:5}},
		autoheight :[],
		dots	   :true,
		loop	   :false,
		autoplay   :false
	};

export function initUniModules(root = document) {
	$(root).find('[data-uni-module]').each(function() {
		const options = {type: $(this).data('uni-module')};
		const items = $(this).data('uni-module-items');

		if (items) options.items = items;

		$(this).uniModules(options);
	});
}
