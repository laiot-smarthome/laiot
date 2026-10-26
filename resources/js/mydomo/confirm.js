/*
 * Подтверждение destructive-действий одним md-модалом.
 *
 * Формы помечают себя data-md-confirm="текст". Перехватчик останавливает
 * первый submit, показывает общий modal из layout и после подтверждения
 * отправляет форму повторно. window.confirm() в новом интерфейсе не нужен.
 */

const DIALOG_ID = 'mdConfirmDialog';

export function initConfirm() {
    const state = () => window.Alpine?.store('mdConfirm');

    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) {
            return;
        }
        if (!form.dataset.mdConfirm || form.dataset.mdConfirmed === '1') {
            return;
        }
        event.preventDefault();

        const store = state();
        if (!store) {
            // Без Alpine (например, ранний рендер) форма должна всё равно уйти.
            form.dataset.mdConfirmed = '1';
            form.submit();

            return;
        }

        store.form = form;
        store.message = String(form.dataset.mdConfirm || 'Подтвердить действие?');
        window.Alpine.store('mdModal')?.show(DIALOG_ID);
    }, true);

    return {
        form: null,
        message: '',

        ask(form) {
            this.form = form;
            this.message = String(form.dataset.mdConfirm || 'Подтвердить действие?');
            window.Alpine?.store('mdModal')?.show(DIALOG_ID);
        },

        ok() {
            const form = this.form;
            this.form = null;
            this.message = '';
            window.Alpine?.store('mdModal')?.close();
            if (!form) {
                return;
            }
            form.dataset.mdConfirmed = '1';
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
            } else {
                form.submit();
            }
        },

        cancel() {
            this.form = null;
            this.message = '';
            window.Alpine?.store('mdModal')?.close();
        },
    };
}
