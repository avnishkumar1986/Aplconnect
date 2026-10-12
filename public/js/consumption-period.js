(function () {
    const displayDate = date => {
        const day = String(date.getDate()).padStart(2, '0');
        const month = String(date.getMonth() + 1).padStart(2, '0');
        return `${day}-${month}-${date.getFullYear()}`;
    };

    const isoDate = date => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;

    const followingDay = date => {
        const next = new Date(date.getFullYear(), date.getMonth(), date.getDate());
        next.setDate(next.getDate() + 1);
        return next;
    };

    const parseDate = value => {
        const displayMatch = String(value || '').match(/^(\d{2})-(\d{2})-(\d{4})$/);
        const isoMatch = String(value || '').match(/^(\d{4})-(\d{2})-(\d{2})$/);
        const parts = displayMatch
            ? [Number(displayMatch[3]), Number(displayMatch[2]), Number(displayMatch[1])]
            : isoMatch ? [Number(isoMatch[1]), Number(isoMatch[2]), Number(isoMatch[3])] : null;
        if (!parts) return null;
        const date = new Date(parts[0], parts[1] - 1, parts[2]);
        return date.getFullYear() === parts[0] && date.getMonth() === parts[1] - 1 && date.getDate() === parts[2]
            ? date : null;
    };

    const refreshBatchMonthlyDays = input => {
        if (!input?.matches('[data-batch-consumption] [name="monthly_date"]')) return;
        const output = input.form?.querySelector('[data-monthly-days]');
        if (!output) return;

        const selectedDate = parseDate(input.value);
        if (!selectedDate) {
            output.value = '';
            return;
        }

        const daysInMonth = new Date(selectedDate.getFullYear(), selectedDate.getMonth() + 1, 0).getDate();
        output.value = String(daysInMonth - selectedDate.getDate() + 1);
    };

    const bindDateMask = input => {
        if (!input || input.dataset.dateMaskReady) return;
        input.dataset.dateMaskReady = 'true';
        input.addEventListener('input', () => {
            const digits = input.value.replace(/\D/g, '').slice(0, 8);
            input.value = [digits.slice(0, 2), digits.slice(2, 4), digits.slice(4, 8)]
                .filter(Boolean).join('-');
        });
        if (!input.parentElement?.classList.contains('formatted-date-picker')) {
            const wrapper = document.createElement('div');
            wrapper.className = 'formatted-date-picker';
            input.parentNode.insertBefore(wrapper, input);
            wrapper.appendChild(input);
            const icon = document.createElement('button');
            icon.type = 'button';
            icon.className = 'formatted-date-picker-icon';
            icon.setAttribute('aria-label', 'Open calendar');
            icon.title = 'Choose date';
            icon.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M7 3v3m10-3v3M4 9h16M5 5h14a1 1 0 0 1 1 1v14H4V6a1 1 0 0 1 1-1Z"/></svg>';
            const native = document.createElement('input');
            native.type = 'date';
            native.lang = 'en-GB';
            native.className = 'formatted-date-picker-native';
            native.tabIndex = -1;
            native.setAttribute('aria-label', 'Choose date');
            native.min = input.dataset.minDate || '';
            const selected = parseDate(input.value);
            native.value = selected ? isoDate(selected) : '';
            native.addEventListener('change', () => {
                const date = parseDate(native.value);
                input.value = date ? displayDate(date) : '';
                input.dispatchEvent(new Event('change', { bubbles: true }));
            });
            input.addEventListener('change', () => {
                const date = parseDate(input.value);
                native.value = date ? isoDate(date) : '';
            });
            const openPicker = () => {
                const date = parseDate(input.value);
                native.value = date ? isoDate(date) : native.value;
                if (typeof native.showPicker === 'function') native.showPicker();
                else native.click();
            };
            icon.addEventListener('click', openPicker);
            input.addEventListener('click', openPicker);
            wrapper.append(native, icon);
        }
    };

    const bind = select => {
        if (select.dataset.periodReady) return;
        select.dataset.periodReady = 'true';
        const form = select.closest('form');
        const rows = form.querySelector('[data-datewise-rows]');
        const addButton = form.querySelector('[data-add-datewise]');
        const monthlyDate = form.querySelector('[data-monthly-date]');
        const monthlyDays = form.querySelector('[data-monthly-days]');
        const monthlyQuantity = form.querySelector('[name="daily_consumption_mt"]');
        const plant = form.querySelector('[data-plant]');
        const material = form.querySelector('[data-material]');
        const monthlyNextDates = JSON.parse(form.dataset.monthlyNextDates || '{}');
        const today = new Date();
        const todayValue = displayDate(today);

        const refreshNextMonthlyDate = () => {
            if (!monthlyDate || !plant?.value || !material?.value || form.querySelector('input[name="_method"]')) return;
            const nextDate = parseDate(monthlyNextDates[`${plant.value}|${material.value}`]);
            if (!nextDate) return;
            monthlyDate.value = displayDate(nextDate);
            monthlyDate.dispatchEvent(new Event('change', { bubbles: true }));
        };

        const refreshMonthlyDays = () => {
            if (!monthlyDate?.value) {
                if (monthlyDays) monthlyDays.value = '';
                return;
            }

            const selectedDate = parseDate(monthlyDate.value);
            if (!selectedDate) { if (monthlyDays) monthlyDays.value = ''; return; }
            const daysInMonth = new Date(selectedDate.getFullYear(), selectedDate.getMonth() + 1, 0).getDate();
            if (monthlyDays) monthlyDays.value = Math.max(0, daysInMonth - selectedDate.getDate() + 1);
        };

        const reindex = () => {
            const allRows = [...rows.querySelectorAll('[data-datewise-row]')];
            allRows.forEach((row, index) => {
                row.querySelector('[data-datewise-from]').name = `datewise_entries[${index}][from]`;
                row.querySelector('[data-datewise-until]').name = `datewise_entries[${index}][until]`;
                row.querySelector('[data-datewise-quantity]').name = `datewise_entries[${index}][daily_consumption_mt]`;
                const remove = row.querySelector('[data-remove-datewise]');
                if (remove) remove.hidden = allRows.length === 1;
            });
        };

        const bindRow = row => {
            const from = row.querySelector('[data-datewise-from]');
            const until = row.querySelector('[data-datewise-until]');
            bindDateMask(from);
            bindDateMask(until);
            from.addEventListener('change', () => {
                const fromDate = parseDate(from.value);
                const untilDate = parseDate(until.value);
                if (fromDate) from.value = displayDate(fromDate);
                const untilPicker = until.parentElement?.querySelector('.formatted-date-picker-native');
                if (untilPicker && fromDate) untilPicker.min = isoDate(fromDate);
                if (fromDate && untilDate && untilDate < fromDate) until.value = '';
            });
            until.addEventListener('change', () => {
                const untilDate = parseDate(until.value);
                if (untilDate) until.value = displayDate(untilDate);
                const nextRow = row.nextElementSibling?.matches('[data-datewise-row]') ? row.nextElementSibling : null;
                if (untilDate && nextRow) {
                    const nextFrom = nextRow.querySelector('[data-datewise-from]');
                    const nextStart = followingDay(untilDate);
                    nextFrom.value = displayDate(nextStart);
                    nextFrom.dispatchEvent(new Event('change', { bubbles: true }));
                }
            });
            row.querySelector('[data-remove-datewise]')?.addEventListener('click', () => {
                if (rows.querySelectorAll('[data-datewise-row]').length > 1) row.remove();
                reindex();
            });
        };

        rows.querySelectorAll('[data-datewise-row]').forEach(bindRow);
        addButton?.addEventListener('click', () => {
            const previousRow = rows.querySelector('[data-datewise-row]:last-child');
            const previousUntil = parseDate(previousRow?.querySelector('[data-datewise-until]')?.value);
            const row = rows.querySelector('[data-datewise-row]').cloneNode(true);
            row.querySelectorAll('.formatted-date-picker').forEach(wrapper => {
                const input = wrapper.querySelector('[data-datewise-from], [data-datewise-until]');
                if (!input) return;
                delete input.dataset.dateMaskReady;
                wrapper.replaceWith(input);
            });
            row.querySelectorAll('input').forEach(input => input.value = '');
            const from = row.querySelector('[data-datewise-from]');
            const nextStart = previousUntil ? followingDay(previousUntil) : today;
            from.value = displayDate(nextStart);
            from.dataset.minDate = isoDate(nextStart);
            const until = row.querySelector('[data-datewise-until]');
            until.dataset.minDate = isoDate(nextStart);
            bindRow(row);
            rows.appendChild(row);
            reindex();
        });

        bindDateMask(monthlyDate);
        monthlyDate?.addEventListener('change', () => {
            const date = parseDate(monthlyDate.value);
            if (date) monthlyDate.value = displayDate(date);
            refreshMonthlyDays();
        });
        plant?.addEventListener('change', refreshNextMonthlyDate);
        material?.addEventListener('change', refreshNextMonthlyDate);

        const refresh = () => {
            const datewise = select.value === 'datewise';
            const monthly = select.value === 'monthly';
            form.querySelectorAll('[data-datewise-period]').forEach(field => field.classList.toggle('hidden', !datewise));
            form.querySelectorAll('[data-monthly-period]').forEach(field => field.classList.toggle('hidden', !monthly));
            rows.querySelectorAll('input').forEach(input => input.required = datewise);
            if (monthlyQuantity) monthlyQuantity.required = monthly;
            if (monthly) {
                if (!monthlyDate.value) monthlyDate.value = todayValue;
                refreshNextMonthlyDate();
                refreshMonthlyDays();
            }
        };

        select.addEventListener('change', refresh);
        reindex();
        refresh();
    };

    const mount = root => root.querySelectorAll?.('[data-consumption-period]').forEach(bind);
    document.addEventListener('input', event => refreshBatchMonthlyDays(event.target));
    document.addEventListener('change', event => refreshBatchMonthlyDays(event.target));
    document.addEventListener('DOMContentLoaded', () => {
        mount(document);
        document.querySelectorAll('[data-batch-consumption] [name="monthly_date"]').forEach(refreshBatchMonthlyDays);
    });
    new MutationObserver(records => records.forEach(record => record.addedNodes.forEach(node => {
        if (node.nodeType !== 1) return;
        mount(node);
        if (node.matches?.('[data-batch-consumption]')) {
            refreshBatchMonthlyDays(node.querySelector('[name="monthly_date"]'));
        }
        node.querySelectorAll?.('[data-batch-consumption] [name="monthly_date"]').forEach(refreshBatchMonthlyDays);
    }))).observe(document.documentElement, { childList: true, subtree: true });
})();
