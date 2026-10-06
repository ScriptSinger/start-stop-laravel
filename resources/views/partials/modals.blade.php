{{-- Окна и всплывающее уведомление — разметка uniModalWindow и uniFlyAlert темы. --}}
<div id="modal-cart" class="modal" tabindex="-1" role="dialog" aria-labelledby="modal-cart-title">
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

<div id="modal-form" class="modal" tabindex="-1" role="dialog" aria-labelledby="modal-form-title">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Закрыть">&times;</button>
                <h4 class="modal-title" id="modal-form-title" x-text="$store.modal.title"></h4>
            </div>
            <div class="modal-body" x-html="$store.modal.html"></div>
        </div>
    </div>
</div>

<template x-if="$store.alerts.current">
    <div class="uni-alert" :class="'alert-' + $store.alerts.current.type" role="alert">
        <i class="uni-alert__icon fa" :class="$store.alerts.current.type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'"></i>
        <div x-text="$store.alerts.current.message"></div>
        <i class="uni-alert__icon fas fa-times" @click="$store.alerts.hide()"></i>
    </div>
</template>
