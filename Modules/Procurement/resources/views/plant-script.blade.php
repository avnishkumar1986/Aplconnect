@push('scripts')
    <script>
        document.querySelectorAll('[data-procurement-form]').forEach(form => {
            const company = form.querySelector('[data-company]'),
                plant = form.querySelector('[data-plant]'),
                options = [...plant.options].slice(1);
            const refresh = () => {
                const selected = company.value;
                const selectedPlant = plant.dataset.selected || plant.value;
                plant.innerHTML = '<option value="">— Select plant —</option>';
                options.filter(option => option.dataset.companyId === selected).forEach(option => plant.append(
                    option.cloneNode(true)));
                if ([...plant.options].some(option => option.value === selectedPlant)) plant.value = selectedPlant;
                plant.dataset.selected = '';
                window.jQuery?.(plant).trigger('change.select2');
            };
            company.addEventListener('change', refresh);
            refresh();
        });
    </script>
@endpush
