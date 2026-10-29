/*
 * Живая проверка системного имени.
 *
 * Имена классов, объектов, свойств и методов — латиница, цифры и `_`.
 * Список занятых имён приходит с сервера вместе с формой, поэтому
 * подсветка работает по мере ввода без дополнительного эндпоинта.
 * Серверная проверка остаётся авторитетной: она же ловит гонку.
 */

const PATTERN = /^[A-Za-z_][A-Za-z0-9_]*$/;

export function registerNameCheck(Alpine) {
    Alpine.data('mdNameCheck', (config = {}) => ({
        value: String(config.initial || ''),
        taken: (Array.isArray(config.taken) ? config.taken : []).map((name) => String(name).toLocaleLowerCase()),
        status: 'idle',
        touched: false,

        init() {
            // Без JS имя остаётся обычным полем; статус появляется только
            // после ввода, чтобы не мигать «свободно» на открытой карточке.
            this.value = this.$el.querySelector('input')?.value ?? this.value;
        },

        get message() {
            switch (this.status) {
                case 'empty':
                    return 'Введите системное имя.';
                case 'invalid':
                    return 'Только латиница, цифры и _; первая — буква или _.';
                case 'taken':
                    return 'Это имя уже занято.';
                case 'free':
                    return 'Имя свободно.';
                default:
                    return '';
            }
        },

        get isInvalid() {
            return this.status === 'taken' || this.status === 'invalid' || this.status === 'empty';
        },

        get isFree() {
            return this.status === 'free';
        },

        get tone() {
            if (this.status === 'free') {
                return 'free';
            }
            if (this.status === 'taken') {
                return 'taken';
            }

            return 'invalid';
        },

        get icon() {
            if (this.status === 'free') {
                return 'circle-check';
            }
            if (this.status === 'taken') {
                return 'alert-circle';
            }

            return 'alert-triangle';
        },

        check() {
            this.touched = true;
            const value = this.value.trim();
            if (value === '') {
                this.status = 'empty';
                return;
            }
            if (!PATTERN.test(value)) {
                this.status = 'invalid';
                return;
            }
            this.status = this.taken.includes(value.toLocaleLowerCase()) ? 'taken' : 'free';
        },
    }));
}
