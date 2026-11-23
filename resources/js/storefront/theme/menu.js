/*
 * Меню категорий и главное меню — поведение common.js темы UniShop2 старого
 * сайта (uniMenuAim, uniMenuDropdownHeight, uniMenuDropdownPos,
 * uniMenuMobileToggle, uniMenuUpd) на чистом JS: на него завязана вёрстка темы.
 * «Умное» наведение (меню не закрывается, пока курсор едет к подменю) — алгоритм
 * jquery-menu-aim: курсор «целится» в подменю, если угол к его краям сужается.
 */
import { escapeHtml } from '../utils';

const SUBMENU = '.menu__level-2';
const bound = new WeakSet();
let globalBound = false;

const viewportWidth = () => document.documentElement.clientWidth;
const pageLeft = (el) => el.getBoundingClientRect().left + window.scrollX;
const pageTop = (el) => el.getBoundingClientRect().top + window.scrollY;

/** Ширина содержимого — как .width() в jQuery. */
function contentWidth(el) {
	const style = getComputedStyle(el);

	return el.getBoundingClientRect().width
		- parseFloat(style.paddingLeft) - parseFloat(style.paddingRight)
		- parseFloat(style.borderLeftWidth) - parseFloat(style.borderRightWidth);
}

/** Показать или скрыть подменю у пункта; у первого пункта «открыто по умолчанию» снимается. */
function changeState(item, state) {
	const child = item.querySelector(`:scope > ${SUBMENU}`);
	const first = document.querySelector('.menu__level-1-li');

	first?.classList.remove('open');
	first?.querySelector(SUBMENU)?.classList.remove('open');

	if (state) {
		const wasHidden = child && getComputedStyle(child).display === 'none';

		item.classList.add('open');
		child?.classList.add('open');

		if (wasHidden) child.animate([{opacity: 0}, {opacity: 1}], {duration: 150});
	} else {
		item.classList.remove('open');
		child?.classList.remove('open');
	}
}

/**
 * Наведение на пункты меню с учётом движения курсора к подменю.
 * rowSelector — пункты; activate/deactivate — показать/скрыть подменю.
 */
function menuAim(menu, {rowSelector, activate, deactivate, exitMenu}) {
	const TRACKED = 3, DELAY = 300, TOLERANCE = 75;
	let activeRow = null, timeout = null, lastDelayLoc = null, locations = [];

	const trackMouse = (event) => {
		locations.push({x: event.pageX, y: event.pageY});
		if (locations.length > TRACKED) locations.shift();
	};

	const activationDelay = () => {
		if (!activeRow) return 0;

		const rect = menu.getBoundingClientRect();
		const left = rect.left + window.scrollX, top = rect.top + window.scrollY;
		const upperRight = {x: left + rect.width, y: top - TOLERANCE};
		const lowerRight = {x: left + rect.width, y: top + rect.height + TOLERANCE};
		const loc = locations[locations.length - 1];
		let prev = locations[0];

		if (!loc) return 0;
		if (!prev) prev = loc;

		// Курсор пришёл не из меню — задержка не нужна.
		if (prev.x < left || prev.x > lowerRight.x || prev.y < top || prev.y > lowerRight.y) return 0;
		if (lastDelayLoc && loc.x === lastDelayLoc.x && loc.y === lastDelayLoc.y) return 0;

		const slope = (a, b) => (b.y - a.y) / (b.x - a.x);

		if (slope(loc, upperRight) < slope(prev, upperRight) && slope(loc, lowerRight) > slope(prev, lowerRight)) {
			lastDelayLoc = loc;

			return DELAY;
		}

		lastDelayLoc = null;

		return 0;
	};

	const activateRow = (row) => {
		if (row === activeRow) return;
		if (activeRow) deactivate(activeRow);

		activate(row);
		activeRow = row;
	};

	const possiblyActivate = (row) => {
		const delay = activationDelay();

		if (delay) {
			timeout = setTimeout(() => possiblyActivate(row), delay);
		} else {
			activateRow(row);
		}
	};

	menu.addEventListener('mouseenter', () => document.addEventListener('mousemove', trackMouse));
	menu.addEventListener('mouseleave', () => {
		document.removeEventListener('mousemove', trackMouse);
		clearTimeout(timeout);
		locations = [];

		if (exitMenu(menu)) {
			if (activeRow) deactivate(activeRow);

			activeRow = null;
		}
	});

	menu.querySelectorAll(rowSelector).forEach((row) => {
		row.addEventListener('mouseenter', () => {
			clearTimeout(timeout);
			possiblyActivate(row);
		});
		row.addEventListener('click', () => activateRow(row));
	});
}

export function uniMenuAim() {
	if (viewportWidth() <= 992) return;

	// Главное меню (menu2): подменю открывается сразу по наведению.
	document.querySelectorAll('.menu.menu2 li.has-children').forEach((item) => {
		if (bound.has(item)) return;

		bound.add(item);
		item.addEventListener('mouseenter', () => changeState(item, 1));
		item.addEventListener('mouseleave', () => changeState(item, 0));
	});

	document.querySelectorAll('.menu.menu1 .menu__collapse').forEach((menu) => {
		if (bound.has(menu)) return;

		bound.add(menu);
		menuAim(menu, {
			rowSelector: '.menu__level-1-li',
			activate: (row) => changeState(row, 1),
			deactivate: (row) => changeState(row, 0),
			// Меню в прилипающей шапке (.new) на выходе курсора не закрывается.
			exitMenu: (element) => !element.parentElement.classList.contains('new'),
		});
	});

	if (globalBound) return;

	globalBound = true;

	// Кнопка «Каталог» в прилипающей шапке.
	document.querySelectorAll('.header-menu__btn').forEach((button) => {
		button.addEventListener('click', () => {
			const scope = button.classList.contains('fly-menu__menu-btn') ? '.fly-menu' : 'header';
			const rows = [...document.querySelectorAll(`${scope} .menu1.new .menu__level-1-li`)];

			button.classList.toggle('show');
			rows.slice(1).forEach((row) => changeState(row, 0));
			if (rows[0]) changeState(rows[0], 1);

			document.querySelector(`${scope} .menu-wrapper.new`)?.classList.toggle('show');
		});
	});

	// Клик по странице закрывает меню.
	document.querySelectorAll('main, footer').forEach((area) => {
		['click', 'touchstart'].forEach((type) => area.addEventListener(type, () => {
			document.querySelectorAll(SUBMENU).forEach((submenu) => submenu.classList.remove('open'));
			document.querySelectorAll('.menu__pm').forEach((toggle) => toggle.classList.remove('open'));
			document.querySelectorAll('.menu .collapse').forEach((collapse) => collapse.classList.remove('in'));

			document.body.classList.remove('scroll-disabled');
			document.querySelectorAll('.menu-wrapper, .header-menu__btn').forEach((element) => element.classList.remove('show'));
		}, {passive: true}));
	});
}

/**
 * Мобильное меню: кнопка «Категории», крестик и «+» у разделов. В теме они
 * навешивались только если страница открылась на узком экране — после
 * поворота планшета или перехода с широкого окна кнопка не работала.
 * Кнопки и так видны только на узком экране, поэтому навешиваем всегда, один раз.
 */
export function uniMenuMobileToggle() {
	document.body.addEventListener('click', (event) => {
		const toggle = event.target.closest('.menu__pm');

		if (!toggle) return;

		toggle.classList.toggle('open');
		toggle.nextElementSibling?.classList.toggle('in');
	});

	document.querySelectorAll('.menu-open, .menu-close').forEach((button) => {
		button.addEventListener('click', () => {
			document.body.classList.toggle('scroll-disabled');
			document.querySelectorAll('.menu-wrapper').forEach((wrapper) => wrapper.classList.toggle('show'));
			document.querySelectorAll('.fly-menu__block').forEach((block) => block.classList.remove('show'));
		});
	});
}

export function uniMenuDropdownHeight() {
	const blocks = [...document.querySelectorAll('header .menu1:not(.new) .menu__level-2, header .menu2 .menu__level-2')];

	if (!blocks.length) return;

	const init = () => {
		const top = pageTop(document.querySelector('header .menu'));
		const height = viewportWidth() > 992 ? `${document.documentElement.clientHeight - top - 100}px` : '';

		blocks.forEach((block) => { block.style.maxHeight = height; });
	};

	init();
	window.addEventListener('resize', init);
}

export function uniMenuDropdownPos() {
	const menu = document.querySelector('header .menu2');

	if (!menu) return;

	const init = () => {
		if (viewportWidth() <= 992) return;

		menu.querySelectorAll(SUBMENU).forEach((submenu) => {
			// Скрытое подменю измеряем, показав его невидимым.
			const saved = submenu.style.cssText;

			submenu.style.visibility = 'hidden';
			submenu.style.display = 'flex';

			const overflow = (pageLeft(submenu.parentElement) + submenu.offsetWidth) - (pageLeft(menu) + menu.offsetWidth);

			submenu.style.cssText = saved;

			if (overflow > 0) submenu.style.marginLeft = `-${overflow}px`;
		});
	};

	init();
	window.addEventListener('resize', init);
}

// Лишние пункты главного меню на узком экране складываются в «…» — uniMenuUpd из common.js темы.
export function uniMenuUpd(selector) {
	const block = document.querySelector(selector);

	if (!block) return;

	const init = () => {
		const items = [...block.children].filter((child) => child.matches('.menu__level-1-li:not(.menu__additional)'));

		items.forEach((item) => { item.style.display = ''; });

		if (viewportWidth() < 992) return;

		block.querySelectorAll('.menu__additional').forEach((element) => element.remove());

		const edge = pageLeft(block) + contentWidth(block);
		const last = items[items.length - 1];

		if (!last || Math.floor(pageLeft(last) + contentWidth(last)) <= edge) return;

		let newItems = '', hasHidden = false;

		items.forEach((item) => {
			if (pageLeft(item) + contentWidth(item) <= edge - 60) {
				item.style.display = '';

				return;
			}

			const link = item.querySelector(':scope > a');
			const children = [...item.querySelectorAll('.menu__level-2-a')];
			let childItems = '';

			if (children.length) {
				childItems = '<div class="menu__level-3"><ul class="menu__level-3-ul">';

				children.forEach((child) => {
					childItems += '<li class="menu__level-3-li"><a class="menu__level-3-a'
						+ (child.hasAttribute('href') ? `" href="${escapeHtml(child.getAttribute('href'))}">` : ' disabled">')
						+ `${escapeHtml(child.textContent)}</a></li>`;
				});

				childItems += '</ul></div>';
			}

			newItems += '<div class="menu__level-2-ul col-md-12"><a class="menu__level-2-a'
				+ (childItems ? ' has-children' : '')
				+ (link?.hasAttribute('href') ? `" href="${escapeHtml(link.getAttribute('href'))}">` : ' disabled">')
				+ `${escapeHtml(link?.textContent)}</a>${childItems}</div>`;

			item.style.display = 'none';
			hasHidden = true;
		});

		if (!hasHidden) return;

		if (!block.querySelector('.menu__additional')) {
			block.insertAdjacentHTML('beforeend', '<li class="menu__level-1-li menu__additional has-children">'
				+ '<a class="menu__level-1-a additional"><i class="fa fa-ellipsis-h"></i></a>'
				+ '<div class="menu__level-2 column-1"></div></li>');

			uniMenuAim();
		}

		block.querySelector('.menu__additional .menu__level-2').innerHTML = newItems;
	};

	init();
	window.addEventListener('resize', init);
}
