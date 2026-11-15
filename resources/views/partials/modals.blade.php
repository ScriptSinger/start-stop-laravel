{{-- Окна и всплывающее уведомление — разметка uniModalWindow и uniFlyAlert темы. --}}
<div id="modal-cart" class="modal" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="modal-cart-title">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Закрыть">&times;</button>
                <h4 class="modal-title" id="modal-cart-title">Корзина</h4>
            </div>
            <div class="modal-body" x-html="$store.cart.html"></div>
        </div>
    </div>
</div>

<div id="modal-form" class="modal" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="modal-form-title">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Закрыть">&times;</button>
                <h4 class="modal-title" id="modal-form-title" x-text="$store.modal.title"></h4>
            </div>
            <div class="modal-body">
                <p x-show="$store.modal.loading" x-cloak role="status">Загружаем форму…</p>
                <div x-show="$store.modal.error" x-cloak role="alert">
                    <p>Не получилось открыть форму. Попробуйте ещё раз.</p>
                    <button type="button" class="btn btn-default" @click="$store.modal.open($store.modal.lastUrl, $store.modal.title)">Повторить</button>
                </div>
                <div x-html="$store.modal.html"></div>
            </div>
        </div>
    </div>
</div>

<template x-if="$store.alerts.current">
    <div class="uni-alert" :class="'alert-' + $store.alerts.current.type" role="alert">
        <i class="uni-alert__icon fa" :class="$store.alerts.current.type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'"></i>
        <div x-text="$store.alerts.current.message"></div>
        <button type="button" class="uni-alert__icon fa-times" aria-label="Закрыть уведомление" @click="$store.alerts.hide()"><i class="fas fa-times" aria-hidden="true"></i></button>
    </div>
</template>
