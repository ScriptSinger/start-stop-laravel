{{-- Подбор другой машины в окне на текущей странице: не уводит на главную
     и не теряет контекст. Открывается кнопкой с data-target="#modal-battery-wizard". --}}
<div id="modal-battery-wizard" class="modal" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="modal-battery-wizard-title">
    <div class="modal-dialog modal-battery-wizard">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Закрыть">&times;</button>
                <h4 class="modal-title" id="modal-battery-wizard-title">Подбор другой машины</h4>
            </div>
            <div class="modal-body">
                @include('partials.battery-filter', ['inModal' => true])
            </div>
        </div>
    </div>
</div>
