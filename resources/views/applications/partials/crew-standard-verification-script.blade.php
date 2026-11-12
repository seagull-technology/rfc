const crewStandardEnabled = {
    jordanian: @js((bool) config('services.gsb.services.cspd_personal_info_standard.enabled', false)),
    foreign: @js((bool) config('services.gsb.services.psd_personal_standard.enabled', false)),
};
const crewCivilNumberLabel = @js(__('app.applications.cast_crew_verification.civil_number'));
const crewStandardFieldsMessage = @js(__('app.applications.cast_crew_verification.verification_fields_required'));
const crewCheckHints = @js(__('app.applications.cast_crew_verification.input_hints'));
const crewCivilNumberHelp = @js(__('app.applications.cast_crew_verification.civil_number_help'));

function updateCrewCheckHint(row, category, ready) {
    const panel = row.querySelector('[data-cast-crew-verification-panel]');
    if (!panel) return;
    let hint = panel.querySelector('[data-cast-crew-input-hint]');
    if (!hint) {
        hint = document.createElement('p');
        hint.className = 'small text-muted mb-0';
        hint.dataset.castCrewInputHint = '';
        hint.setAttribute('aria-live', 'polite');
        panel.append(hint);
    }
    const nationality = String(row.querySelector('[data-cast-crew-nationality]')?.value || '');
    const identity = String(row.querySelector('[data-cast-crew-identity]')?.value || '').trim();
    const individual = String(row.querySelector('[data-cast-crew-individual-number]')?.value || '').trim();
    const values = crewStandardValues(row);
    let message = '';
    if (!ready) {
        if (!nationality) message = crewCheckHints.nationality;
        else if (category === 'jordanian' && !/^\d{10}$/.test(identity)) message = crewCheckHints.national_id;
        else if (!identity) message = crewCheckHints.passport;
        else if (category !== 'jordanian' && !individual) message = crewCheckHints.personal_number;
        else if (crewStandardEnabled[category] && (!values.birth_date || !row.querySelector('[data-cast-crew-birth-date]')?.checkValidity())) message = crewCheckHints.birth_date;
        else if (crewStandardEnabled[category] && category === 'jordanian') message = crewCheckHints.civil_number;
    }
    hint.textContent = message;
    hint.classList.toggle('d-none', !message);
}

function crewStandardValues(row) {
    return {
        birth_date: String(row.querySelector('[data-cast-crew-birth-date], input[name$="[birth_date]"]')?.value || ''),
        civil_number: String(row.querySelector('[data-cast-crew-civil-number]')?.value || '').trim(),
    };
}

function crewStandardKey(row) {
    return JSON.stringify(crewStandardValues(row));
}

function crewStandardReady(row, category) {
    if (!crewStandardEnabled[category]) return true;
    const values = crewStandardValues(row);
    const birthField = row.querySelector('[data-cast-crew-birth-date], input[name$="[birth_date]"]');
    return /^\d{4}-\d{2}-\d{2}$/.test(values.birth_date)
        && Boolean(birthField?.checkValidity())
        && (category !== 'jordanian' || /^[0-9]{3}\/[0-9]{3}$/.test(values.civil_number));
}

function formatCrewCivilNumber(input) {
    const raw = input.value;
    // Preserve invalid/excess input for validation; never truncate an identity number.
    if (!/^[0-9/]*$/.test(raw) || (raw.match(/\//g) || []).length > 1) return;
    const caret = input.selectionStart ?? raw.length;
    const digitsBeforeCaret = raw.slice(0, caret).replace(/\//g, '').length;
    const digits = raw.replace(/\//g, '');
    const formatted = digits.length > 3 ? `${digits.slice(0, 3)}/${digits.slice(3)}` : digits;
    if (formatted === raw) return;
    input.value = formatted;
    const nextCaret = digitsBeforeCaret + (digitsBeforeCaret > 3 ? 1 : 0);
    input.setSelectionRange(nextCaret, nextCaret);
}

function mountCrewStandardFields(row, isJordanian) {
    const identity = row.querySelector('[data-cast-crew-identity]');
    if (!identity) return;
    const birthField = row.querySelector('input[name$="[birth_date]"]');
    if (birthField) {
        birthField.dataset.castCrewBirthDate = '';
        birthField.dataset.castCrewApiField = '';
    }
    let wrapper = row.querySelector('[data-cast-crew-civil-number-wrap]');
    if (!wrapper && crewStandardEnabled.jordanian) {
        wrapper = document.createElement('label');
        wrapper.className = 'd-block mt-2';
        wrapper.dataset.castCrewCivilNumberWrap = '';
        const caption = document.createElement('span');
        caption.className = 'form-label small mb-1';
        caption.textContent = crewCivilNumberLabel;
        const input = document.createElement('input');
        input.type = 'text';
        input.className = 'form-control';
        input.inputMode = 'numeric';
        input.pattern = '[0-9]{3}/[0-9]{3}';
        input.addEventListener('input', () => formatCrewCivilNumber(input));
        input.dir = 'ltr';
        input.autocomplete = 'off';
        input.placeholder = '600/084';
        input.spellcheck = false;
        input.setAttribute('aria-label', crewCivilNumberLabel);
        input.dataset.castCrewCivilNumber = '';
        // Used only for the lookup; the civil number is not saved with the application.
        const help = document.createElement('small');
        help.className = 'form-text text-muted d-block';
        help.textContent = crewCivilNumberHelp;
        wrapper.append(caption, input, help);
        identity.parentElement.append(wrapper);
    }
    if (wrapper) {
        wrapper.classList.toggle('d-none', !isJordanian);
        wrapper.querySelector('input').disabled = !isJordanian;
    }
}
