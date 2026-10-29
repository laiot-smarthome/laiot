/*
 * Редактор кода LaIOT: CodeMirror 6 отдельным lazy-chunk.
 *
 * Чанк загружается только когда на странице есть textarea[data-code-editor].
 * Исходная textarea остаётся в форме и хранит актуальное значение, поэтому
 * без JS редактор продолжает работать как обычное поле.
 */

import { basicSetup, EditorView } from 'codemirror';
import { EditorState, Compartment } from '@codemirror/state';
import { keymap } from '@codemirror/view';
import { indentWithTab, undo, redo, toggleComment, indentSelection } from '@codemirror/commands';
import { openSearchPanel, gotoLine } from '@codemirror/search';
import { startCompletion } from '@codemirror/autocomplete';
import { ensureSyntaxTree, HighlightStyle, syntaxHighlighting } from '@codemirror/language';
import { linter, lintGutter, openLintPanel, forceLinting } from '@codemirror/lint';
import { tags, classHighlighter, highlightTree } from '@lezer/highlight';
import { php } from '@codemirror/lang-php';
import { javascript } from '@codemirror/lang-javascript';
import { oneDark } from '@codemirror/theme-one-dark';

import '../../css/laiot-codeeditor.css';

const themeCompartment = new Compartment();
const syntaxColors = HighlightStyle.define([
    {tag: tags.keyword, color: '#8b40bf'},
    {tag: tags.string, color: '#168457'},
    {tag: [tags.number, tags.bool, tags.null], color: '#bd6a16'},
    {tag: tags.comment, color: '#788b9b', fontStyle: 'italic'},
    {tag: [tags.function(tags.variableName), tags.function(tags.propertyName)], color: '#246ab2'},
    {tag: tags.variableName, color: '#246ab2'},
    {tag: tags.typeName, color: '#137e87'},
]);

function syntaxDiagnostics(view) {
    const tree = ensureSyntaxTree(view.state, view.state.doc.length, 500);
    if (!tree) return [{from: 0, to: 0, severity: 'warning', message: 'Не удалось проверить весь код'}];
    const errors = [];
    tree.iterate({enter(node) {
        if (!node.type.isError) return;
        const from = Math.min(node.from, Math.max(0, view.state.doc.length - 1));
        errors.push({from, to: Math.min(view.state.doc.length, Math.max(from + 1, node.to)), severity: 'error', message: 'Возможная синтаксическая ошибка PHP'});
    }});
    return errors;
}

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

    const language = textarea.dataset.language === 'python' ? [] : [php({plain: true}), syntaxHighlighting(syntaxColors), linter(syntaxDiagnostics), lintGutter()];
    const view = new EditorView({
        state: EditorState.create({
            doc: textarea.value,
            extensions: [
                basicSetup,
                ...language,
                EditorState.phrases.of({'Diagnostics': 'Проверка синтаксиса', 'No diagnostics': 'Ошибок не обнаружено', 'Go to line': 'Перейти к строке', 'go': 'Перейти', 'Find': 'Поиск', 'Replace': 'Замена', 'next': 'Далее', 'previous': 'Назад', 'all': 'Все', 'replace': 'Заменить', 'replace all': 'Заменить все', 'match case': 'Учитывать регистр', 'regexp': 'Регулярное выражение', 'by word': 'Слово целиком', 'close': 'Закрыть'}),
                EditorView.lineWrapping,
                keymap.of([indentWithTab]),
                themeCompartment.of(themeFor(isDark())),
                EditorView.updateListener.of((update) => {
                    if (update.docChanged) {
                        textarea.value = update.state.doc.toString();
                        textarea.dispatchEvent(new Event('input', {bubbles: true}));
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

    // Серверный валидатор сообщил строку с ошибкой: ставим курсор туда,
    // чтобы не искать место вручную в длинном сценарии.
    const errorLine = Number.parseInt(textarea.dataset.errorLine || '', 10);
    if (Number.isFinite(errorLine) && errorLine > 0) {
        const line = view.state.doc.line(Math.min(errorLine, view.state.doc.lines));
        view.dispatch({selection: {anchor: line.from}, scrollIntoView: true});
    }

    const toolbar = shell.querySelector('[data-editor-toolbar]');
    if (toolbar) {
        toolbar.hidden = false;
        toolbar.addEventListener('click', (event) => {
            const button = event.target.closest('[data-editor-action]');
            if (!button) return;
            const actions = {undo, redo, search: openSearchPanel, goto: gotoLine, comment: toggleComment, indent: indentSelection, complete: startCompletion, check: (editor) => { forceLinting(editor); openLintPanel(editor); }};
            actions[button.dataset.editorAction]?.(view);
            view.focus();
        });
    }

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
    highlightSamples(root = document) {
        root.querySelectorAll('code[data-code-sample]:not([data-highlighted])').forEach((element) => {
            const source = element.textContent;
            const language = element.dataset.language;
            if (!['php', 'json', 'javascript'].includes(language)) return;
            const parser = language === 'php' ? php({plain: !source.trimStart().startsWith('<?')}).language.parser : javascript().language.parser;
            const fragment = document.createDocumentFragment();
            let position = 0;
            highlightTree(parser.parse(source), classHighlighter, (from, to, classes) => {
                fragment.append(document.createTextNode(source.slice(position, from)));
                const span = document.createElement('span');
                span.className = classes;
                span.textContent = source.slice(from, to);
                fragment.append(span);
                position = to;
            });
            fragment.append(document.createTextNode(source.slice(position)));
            element.replaceChildren(fragment);
            element.dataset.highlighted = '1';
        });
    },
    mountAll(root = document) {
        root.querySelectorAll('textarea[data-code-editor]').forEach((element) => mount(element));
        this.highlightSamples(root);
    },
};
