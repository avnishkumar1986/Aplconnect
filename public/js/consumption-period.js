(function () {
    const localDate = date => {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    };

    const bind = select => {
        if (select.dataset.periodReady) return;
        select.dataset.periodReady = 'true';
        const form = select.closest('form');
        const rows = form.querySelector('[data-datewise-rows]');
        const addButton = form.querySelector('[data-add-datewise]');
        const monthlyDate = form.querySelector('[data-monthly-date]');
        const monthlyDays = form.querySelector('[data-monthly-days]');
        const monthlyQuantity = form.querySelector('[name="quantity_mt"]');
        const today = new Date();
        const todayValue = localDate(today);

        const refreshMonthlyDays = () => {
            if (!monthlyDate?.value) {
                if (monthlyDays) monthlyDays.value = '';
                return;
            }

            const [year, month, day] = monthlyDate.value.split('-').map(Number);
            const daysInMonth = new Date(year, month, 0).getDate();
            if (monthlyDays) monthlyDays.value = Math.max(0, daysInMonth - day + 1);
        };

        const reindex = () => {
            const allRows = [...rows.querySelectorAll('[data-datewise-row]')];
            allRows.forEach((row, index) => {
                row.querySelector('[data-datewise-from]').name = `datewise_entries[${index}][from]`;
                row.querySelector('[data-datewise-until]').name = `datewise_entries[${index}][until]`;
                row.querySelector('[data-datewise-quantity]').name = `datewise_entries[${index}][quantity_mt]`;
                const remove = row.querySelector('[data-remove-datewise]');
                if (remove) remove.hidden = allRows.length === 1;
            });
        };

        const bindRow = row => {
            const from = row.querySelector('[data-datewise-from]');
            const until = row.querySelector('[data-datewise-until]');
            from.addEventListener('change', () => {
                until.min = from.value;
                if (until.value && until.value < from.value) until.value = '';
            });
            row.querySelector('[data-remove-datewise]')?.addEventListener('click', () => {
                if (rows.querySelectorAll('[data-datewise-row]').length > 1) row.remove();
                reindex();
            });
        };

        rows.querySelectorAll('[data-datewise-row]').forEach(bindRow);
        addButton?.addEventListener('click', () => {
            const row = rows.querySelector('[data-datewise-row]').cloneNode(true);
            row.querySelectorAll('input').forEach(input => input.value = '');
            const from = row.querySelector('[data-datewise-from]');
            from.value = todayValue;
            row.querySelector('[data-datewise-until]').min = todayValue;
            bindRow(row);
            rows.appendChild(row);
            reindex();
        });

        monthlyDate?.addEventListener('change', refreshMonthlyDays);

        const refresh = () => {
            const datewise = select.value === 'datewise';
            const monthly = select.value === 'monthly';
            form.querySelectorAll('[data-datewise-period]').forEach(field => field.classList.toggle('hidden', !datewise));
            form.querySelectorAll('[data-monthly-period]').forEach(field => field.classList.toggle('hidden', !monthly));
            rows.querySelectorAll('input').forEach(input => input.required = datewise);
            if (monthlyQuantity) monthlyQuantity.required = monthly;
            if (monthly) {
                if (!monthlyDate.value) monthlyDate.value = todayValue;
                refreshMonthlyDays();
            }
        };

        select.addEventListener('change', refresh);
        reindex();
        refresh();
    };

    const mount = root => root.querySelectorAll?.('[data-consumption-period]').forEach(bind);
    document.addEventListener('DOMContentLoaded', () => mount(document));
    new MutationObserver(records => records.forEach(record => record.addedNodes.forEach(node => {
        if (node.nodeType === 1) mount(node);
    }))).observe(document.documentElement, { childList: true, subtree: true });
})();
