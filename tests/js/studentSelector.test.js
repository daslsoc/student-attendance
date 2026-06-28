import { describe, it, expect, beforeEach } from 'vitest';
import { toggleSelection, initStudentSelector } from '../../resources/js/studentSelector.js';

describe('toggleSelection', () => {
    it('adds an id that is not selected', () => {
        expect(toggleSelection(['1'], '2')).toEqual(['1', '2']);
    });

    it('removes an id that is already selected', () => {
        expect(toggleSelection(['1', '2'], '2')).toEqual(['1']);
    });

    it('compares ids as strings', () => {
        expect(toggleSelection(['1'], 1)).toEqual([]);
    });

    it('does not mutate the input array', () => {
        const original = ['1'];
        toggleSelection(original, '2');
        expect(original).toEqual(['1']);
    });
});

describe('initStudentSelector', () => {
    const render = (initial = '[]') => {
        document.body.innerHTML = `
            <input type="hidden" data-selection-input value='${initial}'>
            <button data-student="S001">A</button>
            <button data-student="S002">B</button>
        `;
        return {
            input: document.querySelector('[data-selection-input]'),
            a: document.querySelector('[data-student="S001"]'),
            b: document.querySelector('[data-student="S002"]'),
        };
    };

    beforeEach(() => {
        document.body.innerHTML = '';
    });

    it('returns undefined when there is no selection input', () => {
        document.body.innerHTML = '<div></div>';
        expect(initStudentSelector(document)).toBeUndefined();
    });

    it('selecting a student adds it to the hidden input and paints the button', () => {
        const { input, a } = render();
        initStudentSelector(document);

        a.click();

        expect(JSON.parse(input.value)).toEqual(['S001']);
        expect(a.classList.contains('btn-success')).toBe(true);
        expect(a.classList.contains('btn-outline-primary')).toBe(false);
    });

    it('clicking a selected student unselects it', () => {
        const { input, a } = render();
        initStudentSelector(document);

        a.click();
        a.click();

        expect(JSON.parse(input.value)).toEqual([]);
        expect(a.classList.contains('btn-outline-primary')).toBe(true);
    });

    it('pre-populates from the initial value and normalises numbers to strings', () => {
        const { input, a, b } = render('[1]');
        initStudentSelector(document);

        // Initial value normalised to strings.
        expect(JSON.parse(input.value)).toEqual(['1']);
        // S001 is not pre-selected; clicking it adds it alongside the seeded id.
        a.click();
        expect(JSON.parse(input.value)).toEqual(['1', 'S001']);
        expect(b.classList.contains('btn-outline-primary')).toBe(true);
    });
});

describe('initStudentSelector — teacher helpers', () => {
    const render = (initial = '[]') => {
        document.body.innerHTML = `
            <input type="hidden" data-selection-input value='${initial}'>
            <input data-student-filter>
            <button data-select-all>all</button>
            <button data-clear-all>clear</button>
            <button data-student="S001">Alice Adams</button>
            <button data-student="S002">Bob Brown</button>
            <span data-selection-count>0</span>
            <span data-selection-total>0</span>
        `;
        return {
            input: document.querySelector('[data-selection-input]'),
            a: document.querySelector('[data-student="S001"]'),
            b: document.querySelector('[data-student="S002"]'),
            filter: document.querySelector('[data-student-filter]'),
            selectAll: document.querySelector('[data-select-all]'),
            clearAll: document.querySelector('[data-clear-all]'),
            count: document.querySelector('[data-selection-count]'),
            total: document.querySelector('[data-selection-total]'),
        };
    };

    beforeEach(() => {
        document.body.innerHTML = '';
    });

    it('shows the total and a running selected count', () => {
        const { a, count, total } = render('[1]');
        initStudentSelector(document);

        expect(total.textContent).toBe('2');
        expect(count.textContent).toBe('1'); // seeded id
        a.click();
        expect(count.textContent).toBe('2');
        a.click();
        expect(count.textContent).toBe('1');
    });

    it('select-all selects every student, clear empties it', () => {
        const { input, selectAll, clearAll, count } = render();
        initStudentSelector(document);

        selectAll.click();
        expect(JSON.parse(input.value)).toEqual(['S001', 'S002']);
        expect(count.textContent).toBe('2');

        clearAll.click();
        expect(JSON.parse(input.value)).toEqual([]);
        expect(count.textContent).toBe('0');
    });

    it('the filter hides students whose name does not match', () => {
        const { a, b, filter } = render();
        initStudentSelector(document);

        filter.value = 'bob';
        filter.dispatchEvent(new Event('input'));
        expect(a.classList.contains('d-none')).toBe(true);
        expect(b.classList.contains('d-none')).toBe(false);

        filter.value = '';
        filter.dispatchEvent(new Event('input'));
        expect(a.classList.contains('d-none')).toBe(false);
    });
});
