/*
 * Раскладка блоков категорий сеткой по ширине — плагин uniModules из common.js
 * темы UniShop2 старого сайта. Блок размечается data-uni-module="grid" и,
 * при необходимости, data-uni-module-items='{"0":{"items":1},…}'.
 * Карусели — отдельный компонент Alpine scrollCarousel.
 */
import $ from 'jquery';
import { touchSupport, itemsOnMobile } from './settings';

const uni_touch_support = touchSupport, items_on_mobile = itemsOnMobile;

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
			
			base.$elem2.children().wrap('<div class="uni-module__item" style="width:'+base.items()+'"></div>');
			
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
			
			return 100/items+'%';
		},
		responsive:function() {
            var base = this, lastWindowWidth = $(window).width();
			
			base.resizer = () => {
                if ($(window).width() != lastWindowWidth || uni_touch_support) {
					base.$elem2.children().css('width', base.items());
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
		items	   :{0:{items:items_on_mobile},700:{items:3},992:{items:4},1400:{items:5}}
	};

export function initUniModules(root = document) {
	$(root).find('[data-uni-module]').each(function() {
		const options = {type: $(this).data('uni-module')};
		const items = $(this).data('uni-module-items');

		if (items) options.items = items;

		$(this).uniModules(options);
	});
}
