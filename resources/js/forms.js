/**
 * Shared form behaviour for every page:
 * - styled "Are you sure?" modal for forms/buttons with data-confirm (instead of the browser's confirm());
 * - toasts for success/error messages;
 * - read-only "Edit" mode for edit pages;
 * - live password rule checklist and strength meter;
 * - hidden conditional fields never block submission with `required`;
 * - after a validation error, scroll to and focus the first invalid field.
 */

export function registerForms(Alpine) {
    Alpine.store('confirm', {
        open: false,
        title: '',
        message: '',
        button: '',
        danger: false,
        resolver: null,
        ask({ title, message, button, danger }) {
            this.title = title || 'Are you sure?';
            this.message = message;
            this.button = button || 'Yes, continue';
            this.danger = Boolean(danger);
            this.open = true;
            return new Promise((resolve) => {
                this.resolver = resolve;
            });
        },
        answer(confirmed) {
            this.open = false;
            this.resolver?.(confirmed);
            this.resolver = null;
        },
    });

    Alpine.data('toasts', (initial = []) => ({
        items: [],
        init() {
            initial.forEach((toast) => this.add(toast));
            window.addEventListener('toast', (event) => this.add(event.detail));
        },
        add({ type = 'success', message }) {
            if (!message) {
                return;
            }
            const id = Date.now() + Math.random();
            this.items.push({ id, type, message });
            setTimeout(() => this.remove(id), type === 'error' ? 10000 : 5000);
        },
        remove(id) {
            this.items = this.items.filter((item) => item.id !== id);
        },
    }));

    // Edit pages: fields are read-only until "Edit"; "Cancel" restores the saved values.
    Alpine.data('editableForm', (startEditing = false) => ({
        editing: startEditing,
        hadErrors: startEditing,
        edit() {
            this.editing = true;
            this.$nextTick(() => this.$root.querySelector('input:not([type=hidden]):not([disabled]), select, textarea')?.focus());
        },
        cancel() {
            // After a validation error the inputs hold the rejected values; reload to show the saved ones.
            if (this.hadErrors) {
                window.location.reload();
                return;
            }
            // The form that owns the editable fieldset (a section can hold other small forms).
            const fieldset = this.$root.querySelector('fieldset');
            (fieldset?.form ?? this.$root.querySelector('form'))?.reset();
            this.editing = false;
        },
    }));

    Alpine.data('passwordRules', (inputId) => ({
        value: '',
        rules: [
            { key: 'length', label: 'At least 10 characters', test: (v) => v.length >= 10 },
            { key: 'lower', label: 'A lowercase letter', test: (v) => /\p{Ll}/u.test(v) },
            { key: 'upper', label: 'An uppercase letter', test: (v) => /\p{Lu}/u.test(v) },
            { key: 'number', label: 'A number', test: (v) => /\d/.test(v) },
            { key: 'symbol', label: 'A symbol (e.g. ! @ # - _)', test: (v) => /[^\p{L}\p{N}\s]/u.test(v) },
        ],
        init() {
            const input = document.getElementById(inputId);
            input?.addEventListener('input', () => {
                this.value = input.value;
            });
        },
        passed(rule) {
            return rule.test(this.value);
        },
        get score() {
            const met = this.rules.filter((rule) => rule.test(this.value)).length;
            const bonus = this.value.length >= 14 ? 1 : 0;
            return this.value === '' ? 0 : Math.min(5, met + bonus - 1);
        },
        get label() {
            return ['Very weak', 'Weak', 'Fair', 'Good', 'Strong', 'Very strong'][this.score];
        },
    }));

    // Confirm before submitting forms (or specific buttons) that carry data-confirm.
    document.addEventListener('submit', (event) => {
        const form = event.target;
        const submitter = event.submitter;
        const source = submitter?.dataset.confirm ? submitter : form;
        const message = source.dataset.confirm;

        if (!message || form.dataset.confirmed === '1') {
            delete form.dataset.confirmed;
            return;
        }

        event.preventDefault();
        Alpine.store('confirm')
            .ask({
                title: source.dataset.confirmTitle,
                message,
                button: source.dataset.confirmButton,
                danger: source.dataset.confirmDanger !== undefined,
            })
            .then((confirmed) => {
                if (confirmed) {
                    form.dataset.confirmed = '1';
                    form.requestSubmit(submitter && submitter.form === form ? submitter : undefined);
                }
            });
    }, true);

    // Before the browser validates a form, hidden conditional fields must not be `required`.
    document.addEventListener('click', (event) => {
        const button = event.target.closest('button[type=submit], button:not([type]), input[type=submit]');
        if (button?.form) {
            syncRequiredWithVisibility(button.form);
        }
    }, true);

    document.addEventListener('DOMContentLoaded', scrollToFirstError);
}

function isVisible(element) {
    return element.offsetParent !== null || element.getClientRects().length > 0;
}

export function syncRequiredWithVisibility(form) {
    for (const element of form.elements) {
        if (element.type === 'hidden') {
            continue;
        }
        if (element.required && !isVisible(element)) {
            element.dataset.requiredWhenVisible = '1';
            element.required = false;
        } else if (element.dataset.requiredWhenVisible && isVisible(element)) {
            element.required = true;
        }
    }
}

function scrollToFirstError() {
    const error = document.querySelector('[aria-invalid="true"], [data-field-error]');
    if (!error) {
        return;
    }
    const field = error.matches('[aria-invalid="true"]')
        ? error
        : error.closest('div, fieldset')?.querySelector('input:not([type=hidden]), select, textarea');
    (field ?? error).scrollIntoView({ block: 'center', behavior: 'smooth' });
    field?.focus({ preventScroll: true });
}
