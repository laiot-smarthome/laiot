import test from 'node:test';
import assert from 'node:assert/strict';
import {createReleaseSlider} from '../../resources/js/laiot/release-slider.js';

function fixture(ids = ['beta-0.0.3', 'beta-0.0.2', 'beta-0.0.0']) {
    const created = [], destroyed = [];
    const viewport = {slides: ids.map(id => ({dataset: {release: id}})), querySelectorAll() { return this.slides; }};
    const state = createReleaseSlider((element, options) => {
        const instance = {
            options, slides: element.slides, activeIndex: options.initialSlide, enabled: options.enabled,
            get isBeginning() { return this.activeIndex === 0; },
            get isEnd() { return this.activeIndex === this.slides.length - 1; },
            slideTo(index) { this.activeIndex = Math.max(0, Math.min(this.slides.length - 1, index)); options.on.slideChange(this); },
            slidePrev() { this.slideTo(this.activeIndex - 1); },
            slideNext() { this.slideTo(this.activeIndex + 1); },
            enable() { this.enabled = true; }, disable() { this.enabled = false; },
            destroy() { destroyed.push(this); },
        };
        created.push(instance);
        options.on.init(instance);
        return instance;
    }, () => true);
    state.$el = {isConnected: true};
    state.$refs = {viewport};
    state.$nextTick = callback => callback();
    state.init();
    return {state, viewport, created, destroyed};
}

test('карусель обновляет счётчик, границы и переходит к текущей версии', () => {
    const {state, created} = fixture();
    assert.equal(state.total, 3);
    assert.equal(state.atStart, true);
    state.next();
    assert.equal(state.index, 2);
    state.goTo('beta-0.0.0');
    assert.equal(state.atEnd, true);
    state.next();
    assert.equal(state.index, 3);
    state.previous();
    assert.equal(state.activeId, 'beta-0.0.2');
    assert.equal(created[0].options.speed, 0);
});

test('после получения новых релизов сохраняется выбранный выпуск, старый Swiper уничтожается', () => {
    const {state, viewport, created, destroyed} = fixture();
    state.goTo('beta-0.0.2');
    viewport.slides = [{dataset: {release: 'beta-0.0.4'}}, ...viewport.slides];
    state.mount();
    assert.equal(created.length, 2);
    assert.equal(destroyed.length, 1);
    assert.equal(state.index, 3);
    assert.equal(state.activeId, 'beta-0.0.2');
    assert.equal(state.total, 4);
});

test('после смены канала карусель выбирает первый доступный выпуск', () => {
    const {state, viewport} = fixture();
    state.next();
    viewport.slides = [{dataset: {release: 'main-0.0.4'}}, {dataset: {release: 'beta-0.0.0'}}];
    state.mount();
    assert.equal(state.index, 1);
    assert.equal(state.activeId, 'main-0.0.4');
});

test('карусель блокируется на время запроса и не запускается после удаления компонента', () => {
    const {state, created, destroyed} = fixture(['beta-0.0.0']);
    assert.equal(state.atStart, true);
    assert.equal(state.atEnd, true);
    state.setEnabled(false);
    assert.equal(created[0].enabled, false);
    state.mount();
    assert.equal(created[1].enabled, false);
    state.setEnabled(true);
    assert.equal(created[1].enabled, true);
    state.destroy();
    state.mount();
    assert.equal(created.length, 2);
    assert.equal(destroyed.length, 2);
});
