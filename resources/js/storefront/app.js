/*
 * Витрина. Тема UniShop2 старого сайта держится на jQuery и Bootstrap 3
 * (меню, вкладки, окна, карусели) — эти части перенесены модулями из её
 * common.js. Всё, что написано для нового сайта, — компоненты Alpine.
 */
import './vendor';

import $ from 'jquery';
import Alpine from 'alpinejs';

import { uniMenuAim, uniMenuDropdownHeight, uniMenuDropdownPos, uniMenuMobile, uniMenuUpd } from './theme/menu';
import { initUniModules } from './theme/modules';
import { initBannerSliders } from './theme/carousels';
import { initUniHref, scrollBreadcrumbs } from './theme/links';
import { touchSupport } from './theme/settings';
import { initShare } from './share';
import { initReviewsWidget, initYandexMaps } from './embeds';
import { scrollToElement } from './utils';

import alerts from './stores/alerts';
import cart from './stores/cart';
import saved from './stores/saved';
import modal from './stores/modal';
import search from './stores/search';

import qtySwitch from './components/qty-switch';
import checkout from './components/checkout';
import batteryWizard from './components/battery-wizard';
import showMore from './components/show-more';
import ajaxForm from './components/ajax-form';
import cookieNotice from './components/cookie-notice';
import flyMenu from './components/fly-menu';

// Картинки из resources/images, на которые ссылаются шаблоны через Vite::asset().
import.meta.glob('../../images/**', {eager: true, query: '?url', import: 'default'});

Alpine.store('alerts', alerts);
Alpine.store('cart', cart);
Alpine.store('saved', saved);
Alpine.store('modal', modal);
Alpine.store('search', search);

Alpine.data('qtySwitch', qtySwitch);
Alpine.data('checkout', checkout);
Alpine.data('batteryWizard', batteryWizard);
Alpine.data('showMore', showMore);
Alpine.data('ajaxForm', ajaxForm);
Alpine.data('cookieNotice', cookieNotice);
Alpine.data('flyMenu', flyMenu);

// Маска телефона, как в формах старого сайта: <input type="tel" x-phone-mask>.
Alpine.directive('phone-mask', (el) => {
    $(el).mask('+7 (999) 999-99-99');
});

// Вкладка Bootstrap по ссылке «Все характеристики»: $showTab('#tab-specification').
Alpine.magic('showTab', () => (selector) => {
    $(`a[href="${selector}"]`).tab('show');
    scrollToElement(document.querySelector(`a[href="${selector}"]`).closest('ul'), 20);
});

window.Alpine = Alpine;

if (touchSupport) {
    document.body.classList.add('touch-support');
}

// Порядок — как в common.js темы.
uniMenuAim();
uniMenuDropdownHeight();
uniMenuDropdownPos();
uniMenuMobile();
uniMenuUpd('header .menu2 .menu__collapse');
initUniModules();
initBannerSliders();
initUniHref();
initShare();
initReviewsWidget();
initYandexMaps();
scrollBreadcrumbs();

Alpine.start();
