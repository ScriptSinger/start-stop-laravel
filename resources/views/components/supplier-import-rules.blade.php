<div
    x-data="{ suppliers: {{ Js::from($supplierRules) }}, selected: '' }"
    x-init="const field = document.querySelector('form select[name=supplier_id]'); if (field) { selected = field.value; field.addEventListener('change', () => selected = field.value); }"
    x-show="suppliers[selected]"
    x-cloak
    aria-live="polite"
>
    <div class="alert alert-info">
        <div class="alert-content">
            <strong x-text="suppliers[selected]?.name"></strong>
            <p>Формат: <span x-text="suppliers[selected]?.format"></span>.</p>
            <p x-text="suppliers[selected]?.identity"></p>
            <p><span x-text="suppliers[selected]?.quantity"></span> · Наценка: <span x-text="suppliers[selected]?.markup"></span>% · Срок поставки, рабочих дней: <span x-text="suppliers[selected]?.days"></span>.</p>
            <p x-show="suppliers[selected] && !suppliers[selected].active">Поставщик выключен: его предложения не участвуют в продаже под заказ.</p>
            <p x-show="suppliers[selected]?.description" x-text="suppliers[selected]?.description"></p>
            <p><strong>Важно:</strong> позиции, отсутствующие в файле, исключаются из актуального прайса.</p>
        </div>
    </div>
</div>
