{{-- Окна и всплывающее уведомление. Окна — нативный <dialog>: Esc, фокус, возврат
     фокуса и фон даёт браузер; закрывает форма method="dialog" или клик по фону. --}}
<dialog id="modal-cart" class="store-dialog store-dialog--cart" aria-labelledby="modal-cart-title" @click.self="$el.close()">
    <div class="store-dialog__header">
        <h2 class="store-dialog__title" id="modal-cart-title">Корзина</h2>
        <form method="dialog"><button class="store-dialog__close" aria-label="Закрыть"><i class="fas fa-times" aria-hidden="true"></i></button></form>
    </div>
    <div class="store-dialog__body">
        <p style="display: none" role="status" data-cart-progress>Обновляем корзину…</p>
        <div x-html="$store.cart.html"></div>
    </div>
</dialog>

<dialog id="modal-form" class="store-dialog store-dialog--form" aria-labelledby="modal-form-title" @click.self="$el.close()">
    <div class="store-dialog__header">
        <h2 class="store-dialog__title" id="modal-form-title" x-text="$store.modal.title"></h2>
        <form method="dialog"><button class="store-dialog__close" aria-label="Закрыть"><i class="fas fa-times" aria-hidden="true"></i></button></form>
    </div>
    <div class="store-dialog__body">
        <p x-show="$store.modal.loading" x-cloak role="status">Загружаем…</p>
        <div x-show="$store.modal.error" x-cloak role="alert">
            <p>Не получилось открыть форму. Попробуйте ещё раз.</p>
            <button type="button" class="btn btn-default" @click="$store.modal.open($store.modal.lastUrl, $store.modal.title)">Повторить</button>
        </div>
        <div x-html="$store.modal.html"></div>
    </div>
</dialog>

<template x-if="$store.alerts.current">
    <div class="uni-alert" :class="'alert-' + $store.alerts.current.type" role="alert">
        <i class="uni-alert__icon fa" :class="$store.alerts.current.type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'"></i>
        <div x-text="$store.alerts.current.message"></div>
        <button type="button" class="uni-alert__icon fa-times" aria-label="Закрыть уведомление" @click="$store.alerts.hide()"><i class="fas fa-times" aria-hidden="true"></i></button>
    </div>
</template>
