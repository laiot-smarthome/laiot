/*
 * Дерево классов и объектов.
 *
 * Данные приходят с сервера одним снимком: классы в порядке обхода
 * в глубину и объекты по class_id. Клиент только раскрывает ветки и
 * фильтрует; отдельных JSON-запросов для поиска нет.
 *
 * На ПК объекты — дочерние строки дерева, на мобильном — компактная
 * панель под классом (её видимость решает CSS, логика общая).
 */

export function registerClassesTree(Alpine) {
    Alpine.data('mdClassesTree', (config = {}) => ({
        query: String(config.query || ''),
        filter: '',
        expanded: {},
        classes: Array.isArray(config.classes) ? config.classes : [],
        objects: config.objects && typeof config.objects === 'object' ? config.objects : {},

        init() {
            // Раскрываем корни, чтобы дерево не выглядело пустым при
            // первом открытии: у корневых классов сразу видны объекты.
            this.classes.forEach((item) => {
                if (Number(item.parent_id) === 0) {
                    this.expanded[item.id] = true;
                }
            });
        },

        normalize(value) {
            return String(value || '')
                .toLocaleLowerCase()
                .replace(/\s+/g, ' ')
                .trim();
        },

        klass(id) {
            const numeric = Number(id);
            return this.classes.find((item) => Number(item.id) === numeric) || null;
        },

        object(classId, objectId) {
            const numeric = Number(objectId);
            return this.objectsFor(classId).find((item) => Number(item.id) === numeric) || null;
        },

        get active() {
            return this.query.trim() !== '' || this.filter !== '';
        },

        get parentMap() {
            const map = {};
            this.classes.forEach((item) => {
                map[item.id] = Number(item.parent_id) || 0;
            });
            return map;
        },

        ancestors(id) {
            const map = this.parentMap;
            const result = [];
            let parent = map[id] || 0;
            let guard = 0;
            while (parent > 0 && guard < 100) {
                result.push(parent);
                parent = map[parent] || 0;
                guard++;
            }
            return result;
        },

        objectsFor(id) {
            return this.objects[String(id)] || [];
        },

        classText(item) {
            return this.normalize([item.title, item.description, item.path].join(' '));
        },

        objectText(object) {
            return this.normalize([object.title, object.description, object.location_title].join(' '));
        },

        get matched() {
            const matches = new Set();
            const visible = new Set();
            const query = this.normalize(this.query);
            const filter = this.filter;

            this.classes.forEach((item) => {
                if (filter === 'with' && Number(item.objects_deep_count) === 0) {
                    return;
                }
                if (filter === 'empty' && Number(item.objects_deep_count) > 0) {
                    return;
                }
                const directMatch = query === '' || this.classText(item).includes(query);
                const objectMatch = query !== '' && this.objectsFor(item.id).some(
                    (object) => this.objectText(object).includes(query),
                );
                if (!directMatch && !objectMatch) {
                    return;
                }
                matches.add(item.id);
                visible.add(item.id);
                this.ancestors(item.id).forEach((id) => visible.add(id));
            });

            return {matches, visible};
        },

        get matchedIds() {
            return this.matched.matches;
        },

        get visibleIds() {
            return this.matched.visible;
        },

        isExpanded(id) {
            return this.expanded[id] === true;
        },

        isClassVisible(item) {
            if (!item) {
                return false;
            }
            if (this.active) {
                return this.visibleIds.has(item.id);
            }
            return this.ancestors(item.id).every((id) => this.isExpanded(id));
        },

        isObjectRowVisible(classId, objectId) {
            const item = this.klass(classId);
            const object = this.object(classId, objectId);
            if (!item || !object) {
                return false;
            }

            return this.isObjectVisible(item, object);
        },

        isObjectVisible(item, object) {
            if (!this.isClassVisible(item) || !object) {
                return false;
            }
            if (!this.active) {
                return this.isExpanded(item.id);
            }
            const query = this.normalize(this.query);
            if (query === '' || this.classText(item).includes(query)) {
                return true;
            }
            return this.objectText(object).includes(query);
        },

        visibleObjects(classId) {
            const item = this.klass(classId);
            if (!item) {
                return [];
            }
            const objects = this.objectsFor(item.id);
            if (!this.isClassVisible(item)) {
                return [];
            }
            if (!this.active) {
                return this.isExpanded(item.id) ? objects : [];
            }
            const query = this.normalize(this.query);
            if (query === '' || this.classText(item).includes(query)) {
                return objects;
            }
            return objects.filter((object) => this.objectText(object).includes(query));
        },

        toggle(id) {
            if (this.isExpanded(id)) {
                this.expanded[id] = false;
                return;
            }
            this.ancestors(id).forEach((ancestor) => {
                this.expanded[ancestor] = true;
            });
            this.expanded[id] = true;
        },

        expandAll() {
            this.classes.forEach((item) => {
                this.expanded[item.id] = true;
            });
        },

        collapseAll() {
            this.expanded = {};
        },

        reset() {
            this.query = '';
            this.filter = '';
            this.collapseAll();
        },

        get shownCount() {
            if (!this.active) {
                return this.classes.length;
            }
            return this.visibleIds.size;
        },
    }));
}
