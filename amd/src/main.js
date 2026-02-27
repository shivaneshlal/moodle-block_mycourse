// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * My Course block interactions.
 *
 * @module     block_mycourse/main
 */

/**
 * Initialise My Course blocks.
 */
export const init = () => {
    const blocks = document.querySelectorAll('[data-region="block_mycourse"]');

    blocks.forEach((block) => {
        const sortSelect = block.querySelector('[data-role="block_mycourse_sort"]');
        const statusFilter = block.querySelector('[data-role="block_mycourse_status_filter"]');
        if (!sortSelect || !statusFilter) {
            return;
        }

        const uncategorizedLabel = block.dataset.uncategorizedLabel || 'Uncategorized';
        const noFilteredLabel = block.dataset.nofilteredLabel || 'No courses match your current filters.';
        const toggleCategoryTemplateLabel = block.dataset.toggleCategoryTemplateLabel || 'Toggle category {category}';
        const expandAllLabel = block.dataset.expandAllLabel || 'Expand all';
        const collapseAllLabel = block.dataset.collapseAllLabel || 'Collapse all';
        const instanceId = block.dataset.instanceid || '0';
        const sortStorageKey = `block_mycourse_sort_mode_${instanceId}`;
        const filterStorageKey = `block_mycourse_status_filter_${instanceId}`;
        const expandStateKey = `block_mycourse_category_expand_state_${instanceId}`;
        const expandAllButton = block.querySelector('[data-role="block_mycourse_expand_all"]');

        const readStoredValue = (key, fallback) => {
            try {
                const stored = window.localStorage.getItem(key);
                return stored !== null ? stored : fallback;
            } catch (e) {
                return fallback;
            }
        };

        const storeValue = (key, value) => {
            try {
                window.localStorage.setItem(key, value);
            } catch (e) {
                // Ignore storage access errors.
            }
        };

        const readExpandState = () => {
            try {
                const stored = window.localStorage.getItem(expandStateKey);
                if (!stored) {
                    return {};
                }
                const parsed = JSON.parse(stored);
                if (parsed && typeof parsed === 'object') {
                    return parsed;
                }
            } catch (e) {
                // Ignore parse and storage errors.
            }
            return {};
        };

        const storeExpandState = (state) => {
            try {
                window.localStorage.setItem(expandStateKey, JSON.stringify(state));
            } catch (e) {
                // Ignore storage access errors.
            }
        };

        const getGroupKey = (listId, categoryLabel) => `${listId}::${categoryLabel.toLowerCase()}`;

        let expandState = readExpandState();

        const filterCard = (card, statusMode) => {
            const isStarted = card.dataset.courseStarted === '1';
            if (statusMode === 'started' && !isStarted) {
                return false;
            }
            if (statusMode === 'upcoming' && isStarted) {
                return false;
            }
            return true;
        };

        const sortByStartDate = (a, b) => {
            const aName = a.dataset.sortName || '';
            const bName = b.dataset.sortName || '';
            const aStart = Number(a.dataset.sortStartdate || 0);
            const bStart = Number(b.dataset.sortStartdate || 0);

            if (aStart !== bStart) {
                return bStart - aStart;
            }
            return aName.localeCompare(bName);
        };

        const sortByRecentAccess = (a, b) => {
            const aName = a.dataset.sortName || '';
            const bName = b.dataset.sortName || '';
            const aAccess = Number(a.dataset.sortLastaccess || 0);
            const bAccess = Number(b.dataset.sortLastaccess || 0);

            if (aAccess !== bAccess) {
                return bAccess - aAccess;
            }
            return aName.localeCompare(bName);
        };

        const sortByCategory = (a, b) => {
            const aCategory = a.dataset.sortCategory || '';
            const bCategory = b.dataset.sortCategory || '';
            const categoryCompare = aCategory.localeCompare(bCategory);
            if (categoryCompare !== 0) {
                return categoryCompare;
            }
            return sortByStartDate(a, b);
        };

        const setExpanded = (button, coursesList, expanded) => {
            button.setAttribute('aria-expanded', expanded ? 'true' : 'false');
            button.classList.toggle('is-expanded', expanded);
            const icon = button.querySelector('.block_mycourse__category-toggle-icon');
            if (icon) {
                icon.classList.toggle('fa-chevron-down', expanded);
                icon.classList.toggle('fa-chevron-right', !expanded);
            }
            coursesList.hidden = !expanded;
        };

        const setAllGroupsExpanded = (expanded) => {
            const groups = block.querySelectorAll('.block_mycourse__category-group');
            groups.forEach((group) => {
                const button = group.querySelector('.block_mycourse__category-toggle');
                const coursesList = group.querySelector('.block_mycourse__category-courses');
                if (!button || !coursesList) {
                    return;
                }
                const list = group.closest('[data-role="block_mycourse_course_list"]');
                const categoryLabel = group.dataset.categoryLabel || '';
                const listId = (list && list.id) ? list.id : 'block_mycourse-list';
                const groupKey = getGroupKey(listId, categoryLabel);
                setExpanded(button, coursesList, expanded);
                expandState[groupKey] = expanded;
            });
            storeExpandState(expandState);
        };

        const updateExpandAllButton = (mode) => {
            if (!expandAllButton) {
                return;
            }
            const isCategoryMode = mode === 'category';
            const groups = block.querySelectorAll('.block_mycourse__category-group');
            const hasGroups = groups.length > 0;
            expandAllButton.classList.toggle('d-none', !isCategoryMode || !hasGroups);
            if (!isCategoryMode || !hasGroups) {
                return;
            }
            const allExpanded = Array.from(groups).every((group) => {
                const button = group.querySelector('.block_mycourse__category-toggle');
                return button && button.getAttribute('aria-expanded') === 'true';
            });
            expandAllButton.textContent = allExpanded ? collapseAllLabel : expandAllLabel;
        };

        const createCategoryGroup = (categoryLabel, cards, listId) => {
            const groupItem = document.createElement('li');
            groupItem.className = 'list-group-item block_mycourse__category-group';
            groupItem.dataset.categoryLabel = categoryLabel;

            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'btn btn-link p-0 block_mycourse__category-toggle';
            button.setAttribute('aria-label', toggleCategoryTemplateLabel.replace('{category}', categoryLabel));

            const icon = document.createElement('i');
            icon.className = 'icon fa fa-chevron-right block_mycourse__category-toggle-icon';
            icon.setAttribute('aria-hidden', 'true');

            const label = document.createElement('span');
            label.className = 'block_mycourse__category-toggle-label';
            label.textContent = `${categoryLabel} (${cards.length})`;
            button.appendChild(icon);
            button.appendChild(label);

            const coursesList = document.createElement('ul');
            coursesList.className = 'list-group list-group-flush block_mycourse__category-courses';

            const groupKey = getGroupKey(listId, categoryLabel);
            const isExpanded = Object.prototype.hasOwnProperty.call(expandState, groupKey) ? !!expandState[groupKey] : false;
            setExpanded(button, coursesList, isExpanded);

            button.addEventListener('click', () => {
                const expanded = button.getAttribute('aria-expanded') === 'true';
                const newState = !expanded;
                setExpanded(button, coursesList, newState);
                expandState[groupKey] = newState;
                storeExpandState(expandState);
                updateExpandAllButton('category');
            });

            cards.forEach((card) => {
                coursesList.appendChild(card);
            });

            groupItem.appendChild(button);
            groupItem.appendChild(coursesList);
            return groupItem;
        };

        const sortList = (list, mode, statusMode) => {
            if (!list._mycourseAllCards) {
                list._mycourseAllCards = Array.from(list.querySelectorAll('li[data-sort-startdate]'));
            }
            const cards = list._mycourseAllCards;
            if (!cards.length) {
                return;
            }

            const sortFn = mode === 'category' ? sortByCategory : sortByStartDate;
            const finalSortFn = mode === 'recentlyaccessed' ? sortByRecentAccess : sortFn;

            const sortedCards = cards
                .slice()
                .sort(finalSortFn)
                .filter((card) => filterCard(card, statusMode));

            list.innerHTML = '';

            if (!sortedCards.length) {
                const emptyItem = document.createElement('li');
                emptyItem.className = 'list-group-item';
                const message = document.createElement('div');
                message.className = 'alert alert-info mb-0';
                message.setAttribute('role', 'alert');
                message.textContent = noFilteredLabel;
                emptyItem.appendChild(message);
                list.appendChild(emptyItem);
                return;
            }

            if (mode !== 'category') {
                sortedCards.forEach((card) => {
                    list.appendChild(card);
                });
                updateExpandAllButton(mode);
                return;
            }

            const grouped = new Map();
            sortedCards.forEach((card) => {
                const label = (card.dataset.sortCategoryLabel || '').trim() || uncategorizedLabel;
                if (!grouped.has(label)) {
                    grouped.set(label, []);
                }
                grouped.get(label).push(card);
            });

            const listId = list.id || 'block_mycourse-list';
            Array.from(grouped.keys())
                .sort((a, b) => a.localeCompare(b))
                .forEach((label) => {
                    list.appendChild(createCategoryGroup(label, grouped.get(label), listId));
                });
            updateExpandAllButton(mode);
        };

        const applySort = () => {
            const mode = sortSelect.value;
            const statusMode = statusFilter.value;
            block.classList.toggle('block_mycourse--mode-category', mode === 'category');
            const lists = block.querySelectorAll('[data-role="block_mycourse_course_list"]');
            lists.forEach((list) => {
                sortList(list, mode, statusMode);
            });
        };

        const storedMode = readStoredValue(sortStorageKey, '');
        if (storedMode && Array.from(sortSelect.options).some((option) => option.value === storedMode)) {
            sortSelect.value = storedMode;
        }

        const storedFilter = readStoredValue(filterStorageKey, 'all');
        if (Array.from(statusFilter.options).some((option) => option.value === storedFilter)) {
            statusFilter.value = storedFilter;
        }

        sortSelect.addEventListener('change', () => {
            storeValue(sortStorageKey, sortSelect.value);
            applySort();
        });

        statusFilter.addEventListener('change', () => {
            storeValue(filterStorageKey, statusFilter.value);
            applySort();
        });

        if (expandAllButton) {
            expandAllButton.addEventListener('click', () => {
                const expand = expandAllButton.textContent === expandAllLabel;
                setAllGroupsExpanded(expand);
                updateExpandAllButton('category');
            });
        }

        applySort();
    });
};
