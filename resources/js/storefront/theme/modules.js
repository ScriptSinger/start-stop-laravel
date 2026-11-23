/*
 * Раскладка блоков категорий сеткой по ширине — плагин uniModules из common.js
 * темы UniShop2 старого сайта. Блок размечается data-uni-module="grid" и,
 * при необходимости, data-uni-module-items='{"0":{"items":1},…}'.
 * Карусели — отдельный компонент Alpine scrollCarousel.
 */
import { touchSupport, itemsOnMobile } from './settings';

const DEFAULT_ITEMS = {0: {items: itemsOnMobile}, 700: {items: 3}, 992: {items: 4}, 1400: {items: 5}};

/** Ширина содержимого — как .width() в jQuery. */
function contentWidth(el) {
	const style = getComputedStyle(el);

	return el.getBoundingClientRect().width
		- parseFloat(style.paddingLeft) - parseFloat(style.paddingRight)
		- parseFloat(style.borderLeftWidth) - parseFloat(style.borderRightWidth);
}

function initModule(element, items) {
	if (element.dataset.uniModulesInit) return;

	element.dataset.uniModulesInit = 'true';

	const containers = [...element.children];
	const wrapper = containers[0]?.closest('.tab-content') ?? element;

	// Ширина карточки — доля контейнера по ближайшей подходящей точке.
	const itemWidth = () => {
		let width = contentWidth(wrapper);
		let match = -1;

		width += (wrapper.className === 'tab-content' && width < 520) ? 10 : 20;

		Object.keys(items).forEach((breakpoint) => {
			if (Number(breakpoint) <= width && Number(breakpoint) > match) match = Number(breakpoint);
		});

		return `${100 / items[match].items}%`;
	};

	containers.forEach((container) => {
		[...container.children].forEach((child) => {
			const item = document.createElement('div');

			item.className = 'uni-module__item';
			item.style.width = itemWidth();
			child.replaceWith(item);
			item.append(child);
		});

		container.classList.add('load-complete');
	});

	let lastWidth = document.documentElement.clientWidth;

	window.addEventListener('resize', () => {
		if (document.documentElement.clientWidth !== lastWidth || touchSupport) {
			containers.forEach((container) => {
				[...container.children].forEach((item) => { item.style.width = itemWidth(); });
			});
		}
	});
}

export function initUniModules(root = document) {
	root.querySelectorAll('[data-uni-module]').forEach((element) => {
		let items = DEFAULT_ITEMS;

		if (element.dataset.uniModuleItems) {
			items = JSON.parse(element.dataset.uniModuleItems);
		}

		initModule(element, items);
	});
}
