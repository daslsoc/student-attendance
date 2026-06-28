// Shared "tap a student to toggle them" logic for the attendance and
// book-distribution forms. The toggle rule is identical on both pages, so it
// lives here once and is unit-tested in tests/js/studentSelector.test.js
// rather than copy-pasted into each Blade template.

/**
 * Toggle an id's membership in the selected list. Pure — returns a new array,
 * never mutates the input. Ids are compared as strings (student numbers arrive
 * from data-* attributes as strings).
 *
 * @param {string[]} selected
 * @param {string} id
 * @returns {string[]}
 */
export function toggleSelection(selected, id) {
    id = String(id);

    return selected.includes(id)
        ? selected.filter((existing) => existing !== id)
        : [...selected, id];
}

/**
 * Wire up a group of toggle buttons to the hidden input that carries the
 * selected student numbers as a JSON array.
 *
 * Expects, inside `root`:
 *   - one `[data-selection-input]` hidden input whose value is the initial
 *     JSON array, optionally with `data-selected-class` / `data-unselected-class`
 *   - any number of `[data-student]` buttons
 *
 * @param {Document|HTMLElement} root
 * @returns {{ selected: string[] } | undefined}
 */
export function initStudentSelector(root = document) {
    const input = root.querySelector('[data-selection-input]');
    if (!input) {
        return undefined;
    }

    const selectedClass = input.dataset.selectedClass || 'btn-success';
    const unselectedClass = input.dataset.unselectedClass || 'btn-outline-primary';

    let selected = JSON.parse(input.value || '[]').map(String);
    input.value = JSON.stringify(selected);

    const buttons = Array.from(root.querySelectorAll('[data-student]'));

    const paint = (button) => {
        const on = selected.includes(String(button.getAttribute('data-student')));
        button.classList.toggle(selectedClass, on);
        button.classList.toggle(unselectedClass, !on);
    };

    // Optional helpers for teachers on a phone: a live "selected / total" count,
    // select-all / clear, and a name filter. Each is a no-op when its element
    // isn't on the page, so the markup stays opt-in.
    const countEl = root.querySelector('[data-selection-count]');
    const totalEl = root.querySelector('[data-selection-total]');
    if (totalEl) {
        totalEl.textContent = String(buttons.length);
    }
    const refreshCount = () => {
        if (countEl) {
            countEl.textContent = String(selected.length);
        }
    };

    const commit = () => {
        input.value = JSON.stringify(selected);
        buttons.forEach(paint);
        refreshCount();
    };

    buttons.forEach((button) => {
        paint(button);
        button.addEventListener('click', () => {
            selected = toggleSelection(selected, button.getAttribute('data-student'));
            commit();
        });
    });
    refreshCount();

    const setAll = (on) => {
        selected = on ? buttons.map((b) => String(b.getAttribute('data-student'))) : [];
        commit();
    };
    root.querySelector('[data-select-all]')?.addEventListener('click', () => setAll(true));
    root.querySelector('[data-clear-all]')?.addEventListener('click', () => setAll(false));

    const filter = root.querySelector('[data-student-filter]');
    filter?.addEventListener('input', () => {
        const query = filter.value.trim().toLowerCase();
        buttons.forEach((button) => {
            const match = button.textContent.toLowerCase().includes(query);
            button.classList.toggle('d-none', query !== '' && !match);
        });
    });

    return {
        get selected() {
            return selected;
        },
    };
}
