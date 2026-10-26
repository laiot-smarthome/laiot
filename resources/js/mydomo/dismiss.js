/**
 * Закрытие alert/hint по кнопке и переход по ссылкам-закрывашкам.
 *
 * Компоненты помечают кнопку `data-md-dismiss="alert|hint"`, без Bootstrap JS.
 */

export function initDismiss() {
    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-md-dismiss]');
        if (!button) {
            return;
        }
        const kind = button.getAttribute('data-md-dismiss');
        const target = button.closest(kind === 'hint' ? '.md-hint' : '.md-alert');
        if (!target) {
            return;
        }
        event.preventDefault();
        if (kind === 'hint') {
            target.hidden = true;
        } else {
            target.remove();
        }
    });
}
