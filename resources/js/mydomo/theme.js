/**
 * Тема интерфейса: cookie `theme` + `data-bs-theme`.
 *
 * Alpha-визуал сохраняем, реализацию не копируем: только радиокнопки
 * `[data-md-theme-choice]`, без jQuery и глобальных хелперов.
 */

function currentTheme() {
    const match = document.cookie.match(/(?:^|;\s*)theme=([^;]+)/);
    const value = match ? decodeURIComponent(match[1]) : 'light';
    return value === 'dark' ? 'dark' : 'light';
}

function apply(theme) {
    document.documentElement.setAttribute('data-bs-theme', theme);
    document.querySelectorAll('[data-md-theme-choice]').forEach((input) => {
        const value = input.getAttribute('data-md-theme-choice') || input.value;
        input.checked = value === theme;
    });
}

function save(theme) {
    document.cookie = 'theme=' + encodeURIComponent(theme) + '; path=/; max-age=31536000; SameSite=Lax';
}

export function initTheme() {
    apply(currentTheme());
    document.querySelectorAll('[data-md-theme-choice]').forEach((input) => {
        input.addEventListener('change', () => {
            if (!input.checked) {
                return;
            }
            const theme = input.getAttribute('data-md-theme-choice') || input.value;
            save(theme);
            apply(theme);
        });
    });
}
