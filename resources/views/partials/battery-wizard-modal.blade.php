{{-- Подбор другой машины в окне на текущей странице: не уводит на главную
     и не теряет контекст. Нативный <dialog>: Esc, фокус и фон даёт браузер.
     Открывается кнопкой @click="$refs.wizardDialog.showModal()" из родительского x-data. --}}
<dialog class="wizard-dialog" x-ref="wizardDialog" aria-labelledby="wizard-dialog-title" @click.self="$el.close()">
    <div class="wizard-dialog__header">
        <h2 class="wizard-dialog__title" id="wizard-dialog-title">Подбор другой машины</h2>
        <form method="dialog"><button class="wizard-dialog__close" aria-label="Закрыть"><i class="fas fa-times" aria-hidden="true"></i></button></form>
    </div>
    <div class="wizard-dialog__body">
        @include('partials.battery-filter', ['inModal' => true])
    </div>
</dialog>
