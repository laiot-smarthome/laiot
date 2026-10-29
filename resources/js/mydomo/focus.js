/**
 * Фокус диалогов: скрытые и отключённые контролы не участвуют в обходе.
 * 2026-10-29 — общая логика для Alpine-диалогов и панелей оболочки.
 */
export function focusable(root) {
    return [...root.querySelectorAll('a[href], button, input, select, textarea, summary, [tabindex]')]
        .filter(item => item.tabIndex >= 0 && !item.matches(':disabled')
            && !item.closest('[inert]') && item.getClientRects().length
            && getComputedStyle(item).visibility !== 'hidden');
}

export function trapFocus(event, root) {
    if (event.key !== 'Tab') return;
    const items = focusable(root);
    if (!items.length) {
        event.preventDefault();
        root.focus();
        return;
    }
    const first = items[0], last = items[items.length - 1];
    if (!items.includes(document.activeElement) || (event.shiftKey && document.activeElement === first)) {
        event.preventDefault();
        (event.shiftKey ? last : first).focus();
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
    }
}
