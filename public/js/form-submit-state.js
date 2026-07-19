(function () {
    'use strict';

    if (window.RfcSubmitState) {
        return;
    }

    const config = window.RfcSubmitStateConfig || {};
    const loadingLabel = config.loadingLabel || 'Submitting...';
    const formStates = new WeakMap();
    const lastSubmitters = new WeakMap();
    const nativeSubmit = HTMLFormElement.prototype.submit;

    const isSubmitControl = function (control) {
        if (control instanceof HTMLButtonElement) {
            return (control.getAttribute('type') || 'submit').toLowerCase() === 'submit';
        }

        return control instanceof HTMLInputElement
            && ['submit', 'image'].includes((control.getAttribute('type') || '').toLowerCase());
    };

    const getSubmitControls = function (form) {
        return Array.from(form.elements).filter(isSubmitControl);
    };

    const getActualSubmitter = function (form, explicitSubmitter) {
        if (explicitSubmitter && explicitSubmitter.form === form && isSubmitControl(explicitSubmitter)) {
            return explicitSubmitter;
        }

        const rememberedSubmitter = lastSubmitters.get(form);

        if (rememberedSubmitter && rememberedSubmitter.form === form && isSubmitControl(rememberedSubmitter)) {
            return rememberedSubmitter;
        }

        return null;
    };

    const preserveSubmitterOverrides = function (form, submitter, state) {
        if (!submitter) {
            return;
        }

        const overrides = [
            ['formaction', 'action'],
            ['formmethod', 'method'],
            ['formenctype', 'enctype'],
            ['formtarget', 'target'],
        ];

        overrides.forEach(function (override) {
            const submitterAttribute = override[0];
            const formAttribute = override[1];

            if (!submitter.hasAttribute(submitterAttribute)) {
                return;
            }

            state.formOverrides.push({
                attribute: formAttribute,
                value: form.getAttribute(formAttribute),
            });
            form.setAttribute(formAttribute, submitter.getAttribute(submitterAttribute));
        });
    };

    const preserveSubmitterValue = function (form, submitter, state) {
        if (!submitter || !submitter.name || submitter.disabled) {
            return;
        }

        const hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.name = submitter.name;
        hiddenInput.value = submitter.value;
        hiddenInput.dataset.rfcSubmitterValue = 'true';
        form.appendChild(hiddenInput);
        state.hiddenInput = hiddenInput;
    };

    const showLoadingState = function (submitter, state) {
        if (!submitter) {
            return;
        }

        const rect = submitter.getBoundingClientRect();
        state.submitter = submitter;
        state.submitterMinWidth = submitter.style.minWidth;
        state.submitterMinHeight = submitter.style.minHeight;

        if (rect.width > 0) {
            submitter.style.minWidth = rect.width + 'px';
        }

        if (rect.height > 0) {
            submitter.style.minHeight = rect.height + 'px';
        }

        submitter.classList.add('rfc-submit-loading');
        submitter.setAttribute('aria-busy', 'true');

        if (submitter instanceof HTMLButtonElement) {
            state.submitterContent = submitter.innerHTML;
            submitter.replaceChildren();

            const spinner = document.createElement('span');
            spinner.className = 'rfc-submit-spinner';
            spinner.setAttribute('aria-hidden', 'true');

            const label = document.createElement('span');
            label.textContent = loadingLabel;

            submitter.append(spinner, label);
            return;
        }

        state.submitterContent = submitter.value;
        submitter.value = loadingLabel;
    };

    const start = function (form, explicitSubmitter) {
        if (!(form instanceof HTMLFormElement) || formStates.has(form)) {
            return false;
        }

        const submitter = getActualSubmitter(form, explicitSubmitter);
        const controls = getSubmitControls(form);
        const displaySubmitter = submitter || controls.find(function (control) {
            return !control.disabled;
        }) || null;
        const state = {
            controls: controls.map(function (control) {
                return {
                    control: control,
                    disabled: control.disabled,
                    ariaBusy: control.getAttribute('aria-busy'),
                };
            }),
            formAriaBusy: form.getAttribute('aria-busy'),
            formOverrides: [],
            hiddenInput: null,
            submitter: null,
            submitterContent: null,
            submitterMinWidth: '',
            submitterMinHeight: '',
        };

        preserveSubmitterOverrides(form, submitter, state);
        preserveSubmitterValue(form, submitter, state);
        showLoadingState(displaySubmitter, state);

        controls.forEach(function (control) {
            control.disabled = true;
        });

        form.dataset.rfcSubmitting = 'true';
        form.setAttribute('aria-busy', 'true');
        formStates.set(form, state);

        return true;
    };

    const restore = function (form) {
        const state = formStates.get(form);

        if (!state) {
            return;
        }

        state.hiddenInput?.remove();

        state.formOverrides.forEach(function (override) {
            if (override.value === null) {
                form.removeAttribute(override.attribute);
            } else {
                form.setAttribute(override.attribute, override.value);
            }
        });

        state.controls.forEach(function (item) {
            item.control.disabled = item.disabled;

            if (item.ariaBusy === null) {
                item.control.removeAttribute('aria-busy');
            } else {
                item.control.setAttribute('aria-busy', item.ariaBusy);
            }
        });

        if (state.submitter) {
            state.submitter.classList.remove('rfc-submit-loading');
            state.submitter.style.minWidth = state.submitterMinWidth;
            state.submitter.style.minHeight = state.submitterMinHeight;

            if (state.submitter instanceof HTMLButtonElement) {
                state.submitter.innerHTML = state.submitterContent;
            } else {
                state.submitter.value = state.submitterContent;
            }
        }

        if (state.formAriaBusy === null) {
            form.removeAttribute('aria-busy');
        } else {
            form.setAttribute('aria-busy', state.formAriaBusy);
        }

        delete form.dataset.rfcSubmitting;
        formStates.delete(form);
    };

    document.addEventListener('click', function (event) {
        const eventTarget = event.target instanceof Element
            ? event.target
            : event.target?.parentElement;
        const control = eventTarget?.closest('button, input');

        if (control && isSubmitControl(control) && control.form) {
            lastSubmitters.set(control.form, control);
        }
    }, true);

    document.addEventListener('submit', function (event) {
        const form = event.target;

        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        if (formStates.has(form)) {
            event.preventDefault();
            return;
        }

        if (event.defaultPrevented) {
            return;
        }

        start(form, event.submitter || lastSubmitters.get(form));

        queueMicrotask(function () {
            if (event.defaultPrevented) {
                restore(form);
            }
        });
    });

    HTMLFormElement.prototype.submit = function () {
        if (formStates.has(this)) {
            return;
        }

        start(this, lastSubmitters.get(this));
        nativeSubmit.call(this);
    };

    window.addEventListener('pageshow', function () {
        document.querySelectorAll('form[data-rfc-submitting="true"]').forEach(restore);
    });

    window.RfcSubmitState = {
        start: start,
        restore: restore,
        isSubmitting: function (form) {
            return formStates.has(form);
        },
    };
})();
