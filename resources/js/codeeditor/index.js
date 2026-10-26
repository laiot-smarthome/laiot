/*
 * Редактор кода MyDoMo: CodeMirror 6 отдельным lazy-chunk.
 *
 * Чанк загружается только когда на странице есть textarea[data-code-editor].
 * Исходная textarea остаётся в форме и хранит актуальное значение, поэтому
 * без JS редактор продолжает работать как обычное поле.
 */

import { basicSetup, EditorView } from 'codemirror';
import { EditorState, Compartment } from '@codemirror/state';
import { keymap } from '@codemirror/view';
import { indentWithTab } from '@codemirror/commands';
import { php } from '@codemirror/lang-php';
import { oneDark } from '@codemirror/theme-one-dark';

import '../../css/mydomo-codeeditor.css';

const themeCompartment = new Compartment();

function isDark() {
    return document.documentElement.getAttribute('data-bs-theme') === 'dark';
}

function themeFor(dark) {
    return dark ? oneDark : [];
}

/**
 * @param {HTMLTextAreaElement} textarea
 * @returns {EditorView|null}
 */
export function mount(textarea) {
    if (!(textarea instanceof HTMLTextAreaElement) || textarea.dataset.codeEditorReady === '1') {
        return null;
    }

    const shell = textarea.closest('.md-code-editor') || textarea.parentElement;
    if (!shell) {
        return null;
    }

    const language = textarea.dataset.language === 'python' ? [] : [php()];
    const view = new EditorView({
        state: EditorState.create({
            doc: textarea.value,
            extensions: [
                basicSetup,
                ...language,
                EditorView.lineWrapping,
                keymap.of([indentWithTab]),
                themeCompartment.of(themeFor(isDark())),
                EditorView.updateListener.of((update) => {
                    if (update.docChanged) {
                        textarea.value = update.state.doc.toString();
                    }
                }),
            ],
        }),
        parent: shell,
    });

    textarea.dataset.codeEditorReady = '1';
    textarea.hidden = true;
    textarea.setAttribute('aria-hidden', 'true');
    shell.classList.add('md-code-editor--cm');

    const gutter = shell.querySelector('.md-code-editor__gutter');
    if (gutter) {
        gutter.hidden = true;
    }

    // Тема переключается атрибутом на <html>, без перезагрузки страницы.
    new MutationObserver(() => {
        view.dispatch({ effects: themeCompartment.reconfigure(themeFor(isDark())) });
    }).observe(document.documentElement, { attributes: true, attributeFilter: ['data-bs-theme'] });

    return view;
}

window.MDCodeEditor = {
    mount,
    mountAll(root = document) {
        root.querySelectorAll('textarea[data-code-editor]').forEach((element) => mount(element));
    },
};
