/**
 * Соединения цепочки строятся по реальным центрам узлов, а не высотам строк.
 * 2026-10-29 — SVG пересчитывается при сворачивании, переносах и изменении
 * размера; наведение и фокус выделяют только путь к выбранному шагу.
 */
const SVG_NS = 'http://www.w3.org/2000/svg';

export function connectorPath(start, end) {
    const radius = Math.min(6, Math.abs(end.x - start.x), Math.max(0, end.y - start.y));
    const direction = end.x >= start.x ? 1 : -1;
    return `M ${start.x} ${start.y} V ${end.y - radius} Q ${start.x} ${end.y} ${start.x + radius * direction} ${end.y} H ${end.x}`;
}

export function registerCallFlow(Alpine) {
    Alpine.data('mdCallFlow', () => {
        let frame = 0, resize, mutations, hovered = null, focused = null;
        let edges = new Map();
        const path = d => {
            const element = document.createElementNS(SVG_NS, 'path');
            element.setAttribute('d', d);
            return element;
        };
        return {
            init() {
                if (!this.$refs.forest) return;
                resize = new ResizeObserver(() => this.schedule());
                resize.observe(this.$refs.forest);
                mutations = new MutationObserver(() => this.schedule());
                mutations.observe(this.$refs.forest, {subtree: true, childList: true, attributes: true, attributeFilter: ['style', 'class', 'open']});
                this.$nextTick(() => this.schedule());
            },
            destroy() {
                cancelAnimationFrame(frame);
                resize?.disconnect();
                mutations?.disconnect();
            },
            schedule() {
                cancelAnimationFrame(frame);
                frame = requestAnimationFrame(() => this.draw());
            },
            setExpanded(open) {
                this.$refs.forest?.querySelectorAll('.md-call-flow__node--has-children').forEach(node => {
                    node.dispatchEvent(new CustomEvent('call-flow-set', {detail: {open}}));
                });
            },
            highlight(target, mode = 'pointer') {
                const selected = target?.closest('.md-call-flow__node, .md-call-flow__branch') || null;
                if (mode === 'focus') {
                    focused = selected;
                    if (selected) hovered = null;
                } else {
                    hovered = selected;
                }
                this.drawHighlight();
            },
            drawHighlight() {
                const group = this.$refs.activeLinks;
                if (!group) return;
                const paths = [], seen = new Set();
                let current = hovered || focused;
                while (edges.has(current) && !seen.has(current)) {
                    seen.add(current);
                    const edge = edges.get(current);
                    paths.push(path(edge.d));
                    current = edge.parent;
                }
                group.replaceChildren(...paths);
            },
            draw() {
                const body = this.$refs.flowBody, forest = this.$refs.forest, svg = this.$refs.links;
                if (!forest?.isConnected || !forest.getClientRects().length) return;
                const style = getComputedStyle(body);
                svg.setAttribute('width', Math.max(body.clientWidth, forest.offsetLeft + forest.scrollWidth + parseFloat(style.paddingRight)));
                svg.setAttribute('height', forest.offsetTop + forest.offsetHeight + parseFloat(style.paddingBottom));
                const origin = svg.getBoundingClientRect();
                const point = element => {
                    const branch = element.classList.contains('md-call-flow__branch');
                    const anchor = element.querySelector(branch ? ':scope > .md-call-flow__branch-head > .md-call-flow__branch-label' : ':scope > .md-call-flow__row > .md-call-flow__rail .md-call-flow__dot');
                    if (!anchor || !anchor.getClientRects().length) return null;
                    const rect = anchor.getBoundingClientRect();
                    return {x: rect.left - origin.left + (branch ? Math.min(11, rect.width / 2) : rect.width / 2), y: rect.top - origin.top + rect.height / 2};
                };
                edges = new Map();
                const paths = [];
                forest.querySelectorAll('.md-call-flow__node, .md-call-flow__branch').forEach(element => {
                    if (!element.getClientRects().length) return;
                    const parent = element.parentElement.closest('.md-call-flow__node, .md-call-flow__branch');
                    if (!parent || !forest.contains(parent)) return;
                    const start = point(parent), end = point(element);
                    if (!start || !end) return;
                    if (element.classList.contains('md-call-flow__branch')) end.x = element.getBoundingClientRect().left - origin.left;
                    const d = connectorPath(start, end);
                    edges.set(element, {parent, d});
                    paths.push(path(d));
                });
                this.$refs.baseLinks.replaceChildren(...paths);
                this.drawHighlight();
            },
        };
    });
}
