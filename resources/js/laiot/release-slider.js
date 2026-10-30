/*
 * 2026-10-30 — карусель выпусков: свайп, клавиатура и повторная инициализация после Yoyo.
 * Экземпляр Swiper хранится вне реактивного объекта Alpine, чтобы DOM не попадал в Proxy.
 */
import Swiper from 'swiper';
import {A11y} from 'swiper/modules';

export function createReleaseSlider(
    makeSwiper = (element, options) => new Swiper(element, {...options, modules: [A11y]}),
    reducedMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches,
) {
    let slider = null;
    let disposed = false;
    return {
        index: 1, total: 0, atStart: true, atEnd: true, activeId: '', enabled: true,
        init() {
            this.$nextTick(() => this.mount());
        },
        mount() {
            if (disposed || !this.$el.isConnected) return;
            const viewport = this.$refs.viewport;
            const ids = Array.from(viewport.querySelectorAll('.swiper-slide'), slide => slide.dataset.release);
            const initialSlide = Math.max(0, ids.indexOf(this.activeId));
            slider?.destroy(true, true);
            slider = makeSwiper(viewport, {
                slidesPerView: 1, spaceBetween: 16, initialSlide, autoHeight: true,
                speed: reducedMotion() ? 0 : 280, enabled: this.enabled, watchOverflow: true,
                a11y: {containerRoleDescriptionMessage: 'Карусель релизов', itemRoleDescriptionMessage: 'Релиз', slideLabelMessage: '{{index}} из {{slidesLength}}'},
                on: {init: instance => this.sync(instance), slideChange: instance => this.sync(instance)},
            });
            this.sync(slider);
        },
        sync(instance) {
            this.total = instance.slides.length;
            this.index = instance.activeIndex + 1;
            this.atStart = instance.isBeginning;
            this.atEnd = instance.isEnd;
            this.activeId = instance.slides[instance.activeIndex]?.dataset.release || '';
        },
        previous() { slider?.slidePrev(); },
        next() { slider?.slideNext(); },
        goTo(id) {
            const index = Array.from(slider?.slides || []).findIndex(slide => slide.dataset.release === id);
            if (index >= 0) slider.slideTo(index);
        },
        setEnabled(value) {
            this.enabled = value;
            if (slider) slider[value ? 'enable' : 'disable']();
        },
        destroy() {
            disposed = true;
            slider?.destroy(true, true);
            slider = null;
        },
    };
}

export function registerReleaseSlider(Alpine) {
    Alpine.data('mdReleaseSlider', () => createReleaseSlider());
}
