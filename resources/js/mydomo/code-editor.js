/*
 * Ленивая загрузка редактора кода.
 *
 * Основной бандл не тянет CodeMirror: чанк mydomo-codeeditor.js/css
 * подключается только если на странице есть textarea[data-code-editor].
 * Если чанк не загрузился, поле остаётся обычной textarea и форма работает.
 */

const SCRIPT_URL = '/assets/admin/mydomo-codeeditor.js';
const STYLE_URL = '/assets/admin/mydomo-codeeditor.css';

let loading = null;

function load() {
    if (window.MDCodeEditor) {
        return Promise.resolve(window.MDCodeEditor);
    }
    if (loading) {
        return loading;
    }

    loading = new Promise((resolve, reject) => {
        if (!document.querySelector('link[data-md-codeeditor]')) {
            const link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = STYLE_URL;
            link.dataset.mdCodeeditor = '1';
            document.head.appendChild(link);
        }

        const script = document.createElement('script');
        script.src = SCRIPT_URL;
        script.async = true;
        script.onload = () => {
            if (window.MDCodeEditor) {
                resolve(window.MDCodeEditor);

                return;
            }
            loading = null;
            reject(new Error('CodeMirror chunk loaded without API'));
        };
        script.onerror = () => {
            loading = null;
            reject(new Error('CodeMirror chunk failed to load'));
        };
        document.head.appendChild(script);
    });

    return loading;
}

function enhance(root = document) {
    const fields = root.querySelectorAll('textarea[data-code-editor]:not([data-code-editor-mounted])');
    if (!fields.length) {
        return;
    }
    fields.forEach((field) => {
        field.dataset.codeEditorMounted = '1';
    });

    load()
        .then((api) => api.mountAll(root))
        .catch(() => {
            fields.forEach((field) => {
                delete field.dataset.codeEditorMounted;
            });
        });
}

export function initCodeEditors() {
    if (!document.querySelector('textarea[data-code-editor]')) {
        return;
    }

    // Ранний фокус загружает чанк сразу, иначе ждём простоя страницы.
    document.addEventListener('focusin', (event) => {
        const target = event.target;
        if (target instanceof Element && target.matches('textarea[data-code-editor]')) {
            enhance(document);
        }
    });

    if ('requestIdleCallback' in window) {
        window.requestIdleCallback(() => enhance(document), { timeout: 1500 });
    } else {
        window.setTimeout(() => enhance(document), 300);
    }
}
