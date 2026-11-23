{{-- Подбор другой машины в окне на текущей странице: не уводит на главную
     и не теряет контекст. Нативный <dialog>: Esc, фокус и фон даёт браузер.
     Открывается кнопкой @click="$refs.wizardDialog.showModal()" из родительского x-data. --}}
<dialog class="store-dialog store-dialog--wizard" x-ref="wizardDialog" aria-labelledby="wizard-dialog-title" @click.self="$el.close()">
    <div class="store-dialog__header">
        <h2 class="store-dialog__title" id="wizard-dialog-title">Подбор другой машины</h2>
        <form method="dialog"><button class="store-dialog__close" aria-label="Закрыть"><i class="fas fa-times" aria-hidden="true"></i></button></form>
    </div>
    <div class="store-dialog__body">
        @include('partials.battery-filter', ['inModal' => true])
    </div>
</dialog>
