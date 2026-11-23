/*
 * Витрина. Тема UniShop2 старого сайта держится на jQuery и Bootstrap 3
 * (меню, вкладки, окна, карусели) — эти части перенесены модулями из её
 * common.js. Всё, что написано для нового сайта, — компоненты Alpine.
 */
import './vendor';

import $ from 'jquery';
import Alpine from 'alpinejs';

import { uniMenuAim, uniMenuDropdownHeight, uniMenuDropdownPos, uniMenuMobileToggle, uniMenuUpd } from './theme/menu';
import { initUniModules } from './theme/modules';
import { initUniHref, scrollBreadcrumbs } from './theme/links';
import { touchSupport } from './theme/settings';
import { initShare } from './share';
import { initJivoChat, initMetrika, initReviewsWidget, initYandexMaps, trackPlacedOrder } from './embeds';
import { scrollToElement } from './utils';

import alerts from './stores/alerts';
import cart from './stores/cart';
import saved from './stores/saved';
import modal from './stores/modal';
import garage from './stores/garage';
import search from './stores/search';

import qtySwitch from './components/qty-switch';
import checkout from './components/checkout';
import batteryWizard from './components/battery-wizard';
import scrollCarousel from './components/scroll-carousel';
import showMore from './components/show-more';
import ajaxForm from './components/ajax-form';
import cookieNotice from './components/cookie-notice';
import flyMenu from './components/fly-menu';
import livePrice from './components/live-price';
import catalogFilter from './components/catalog-filter';
import guideToc from './components/guide-toc';
import phoneMask from './phone-mask';

// Картинки из resources/images, на которые ссылаются шаблоны через Vite::asset().
import.meta.glob('../../images/**', {eager: true, query: '?url', import: 'default'});

Alpine.store('alerts', alerts);
Alpine.store('cart', cart);
Alpine.store('saved', saved);
Alpine.store('modal', modal);
Alpine.store('garage', garage);
Alpine.store('search', search);

Alpine.data('qtySwitch', qtySwitch);
Alpine.data('checkout', checkout);
Alpine.data('batteryWizard', batteryWizard);
Alpine.data('scrollCarousel', scrollCarousel);
Alpine.data('showMore', showMore);
Alpine.data('ajaxForm', ajaxForm);
Alpine.data('cookieNotice', cookieNotice);
Alpine.data('flyMenu', flyMenu);
Alpine.data('livePrice', livePrice);
Alpine.data('catalogFilter', catalogFilter);
Alpine.data('guideToc', guideToc);

// Маска телефона, как в формах старого сайта: <input type="tel" x-phone-mask>.
Alpine.directive('phone-mask', (el) => phoneMask(el));

// Вкладка товара по ссылке «Все характеристики»: $showTab('specification').
Alpine.magic('showTab', () => (name) => {
    window.dispatchEvent(new CustomEvent('show-tab', {detail: name}));

    const tabs = document.querySelector('.product-page-tabs');

    if (tabs) scrollToElement(tabs, 20);
});

window.Alpine = Alpine;

if (touchSupport) {
    document.body.classList.add('touch-support');
}

// Порядок — как в common.js темы.
uniMenuAim();
uniMenuMobileToggle();
uniMenuDropdownHeight();
uniMenuDropdownPos();
uniMenuUpd('header .menu2 .menu__collapse');
initUniModules();
initUniHref();
initShare();
initReviewsWidget();
initYandexMaps();
initJivoChat();
initMetrika();
trackPlacedOrder();
scrollBreadcrumbs();

Alpine.start();
