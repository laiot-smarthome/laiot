/* Общие списки оформляются локально; поиск нужен только для длинного списка. */
import TomSelect from 'tom-select';

export function initSelects(root = document) {
    const controls = new Map();
    const pending = new Set();
    let scheduled = false;
    const observation = {childList: true, subtree: true, characterData: true, attributes: true, attributeFilter: ['disabled', 'multiple', 'value', 'label']};
    const signatureOf = select => JSON.stringify([...select.options].map(option => [option.value, option.text, option.disabled, option.parentElement.label]));

    function enhance(select) {
        if (!select.isConnected || select.closest('[data-md-native-select]')) return;
        const searchable = select.dataset.mdSearchable === 'true'
            || [...select.options].filter(option => option.value !== '').length > 4;
        const signature = signatureOf(select);
        const previous = controls.get(select);
        if (previous && previous.signature === signature && previous.searchable === searchable && previous.multiple === select.multiple) {
            if (select.disabled !== previous.control.isDisabled) {
                if (select.disabled) previous.control.disable();
                else previous.control.enable();
            }
            return;
        }
        // Пересоздаём только изменившийся список: x-for и Yoyo могут заменить его варианты.
        const values = [...select.selectedOptions].map(option => option.value);
        if (previous) {
            const children = [...select.childNodes];
            previous.control.destroy();
            select.replaceChildren(...children);
        }
        if (select.tomselect) return;
        [...select.options].forEach(option => { option.selected = values.includes(option.value); });
        const control = new TomSelect(select, {
            plugins: select.multiple ? ['remove_button'] : [],
            allowEmptyOption: true,
            maxOptions: null,
            searchField: searchable ? ['text'] : [],
            controlInput: searchable ? '<input type="text" autocomplete="off" size="1" placeholder="Поиск…">' : null,
            render: {no_results: () => '<div class="no-results" role="status">Ничего не найдено</div>'},
        });
        control.wrapper.classList.toggle('md-select--searchable', searchable);
        controls.set(select, {control, signature: signatureOf(select), searchable, multiple: select.multiple});
    }

    function schedule(select) {
        if (select?.matches('select.md-select, select.form-select')) pending.add(select);
        if (scheduled) return;
        scheduled = true;
        queueMicrotask(() => {
            scheduled = false;
            // Tom Select сам переставляет option и отражает disabled в исходном поле.
            // Эти изменения не должны снова запускать его инициализацию.
            observer.disconnect();
            for (const [node, {control}] of controls) {
                if (!node.isConnected) { control.destroy(); controls.delete(node); }
            }
            const batch = [...pending];
            pending.clear();
            try { batch.forEach(enhance); }
            finally { observer.observe(root, observation); }
        });
    }

    const observer = new MutationObserver(records => {
        for (const record of records) {
            schedule(record.target.closest?.('select'));
            for (const node of record.addedNodes) {
                if (node.nodeType !== 1) continue;
                if (node.matches('select')) schedule(node);
                node.querySelectorAll('select.md-select, select.form-select').forEach(schedule);
            }
        }
        if ([...controls.keys()].some(node => !node.isConnected)) schedule();
    });
    observer.observe(root, observation);
    root.querySelectorAll('select.md-select, select.form-select').forEach(schedule);
    root.addEventListener('reset', event => {
        setTimeout(() => event.target.querySelectorAll('select').forEach(select => select.tomselect?.sync()), 0);
    });
}
