@once
    @push('scripts')
        <script nonce="{{ $cspNonce ?? '' }}">
            (() => {
                const lookupUrl = @js(route('applications.personal-details.lookup'));
                const lookupLoadingLabel = @js(__('app.applications.ministry_interior_personal_details.lookup_loading'));
                const lookupUnavailableLabel = @js(__('app.applications.ministry_interior_personal_details.lookup_unavailable'));
                const lookupSelectCategoryLabel = @js(__('app.applications.ministry_interior_personal_details.lookup_select_category'));
                const lookupInvalidPersonalNumberLabel = @js(__('app.applications.ministry_interior_personal_details.lookup_invalid_personal_number'));

                const normalizeOptionText = (value) => String(value || '')
                    .normalize('NFKC')
                    .replace(/[\u064B-\u065F\u0670]/g, '')
                    .replace(/\s+/g, ' ')
                    .trim()
                    .toLocaleLowerCase();

                const setControlValue = (control, value) => {
                    if (!control || value === null || value === undefined || String(value).trim() === '') return;

                    if (control instanceof HTMLSelectElement) {
                        const expected = normalizeOptionText(value);
                        const option = [...control.options].find((candidate) => (
                            normalizeOptionText(candidate.value) === expected
                            || normalizeOptionText(candidate.textContent) === expected
                        ));

                        if (!option) return;
                        control.value = option.value;
                    } else {
                        control.value = value;
                    }

                    control.dispatchEvent(new Event('input', { bubbles: true }));
                    control.dispatchEvent(new Event('change', { bubbles: true }));
                };

                const setSectionVisible = (section, visible) => {
                    if (!section) return;
                    section.hidden = !visible;
                    section.querySelectorAll('input, select, textarea').forEach((control) => {
                        control.disabled = !visible;
                    });
                };

                const setRequired = (control, required) => {
                    if (!control) return;
                    control.required = required;
                    if (!required) control.setCustomValidity('');
                };

                const initializeMinistryPersonalDetails = () => {
                    document.querySelectorAll('[data-ministry-personal-details-editor]').forEach((editor) => {
                        if (editor.dataset.initialized === 'true') return;
                        editor.dataset.initialized = 'true';

                        const rowsContainer = editor.querySelector('[data-ministry-personal-details-rows]');
                        const template = editor.querySelector('[data-ministry-personal-details-template]');
                        if (!rowsContainer || !template) return;

                        const palestinianNationalityCodes = JSON.parse(editor.dataset.palestinianNationalityCodes || '[]')
                            .map((value) => normalizeOptionText(value));
                        const rowControl = (row, field, { interactive = false } = {}) => {
                            const controls = [...row.querySelectorAll(`[name$="[${field}]"]`)];

                            if (!interactive) return controls[0] || null;

                            return controls.find((control) => control.type !== 'hidden' && !control.disabled)
                                || controls.find((control) => control.type !== 'hidden')
                                || controls[0]
                                || null;
                        };
                        const rows = () => [...rowsContainer.querySelectorAll(':scope > [data-ministry-personal-details-row]')];

                        const urgentModal = editor.querySelector('[data-ministry-urgent-modal]');
                        const urgentAcceptance = editor.querySelector('[name="ministry_interior_personal_details_request[urgent_fee_accepted]"]');
                        const urgentConfirm = editor.querySelector('[data-ministry-urgent-confirm]');
                        const urgentCancel = editor.querySelector('[data-ministry-urgent-cancel]');
                        const normalRequestType = editor.querySelector('[name="ministry_interior_personal_details_request[type]"][value="normal"]');

                        const setUrgentModalVisible = (visible) => {
                            if (!urgentModal) return;
                            urgentModal.hidden = !visible;
                            if (urgentConfirm) {
                                urgentConfirm.disabled = !urgentAcceptance?.checked;
                                urgentConfirm.classList.toggle('disabled', !urgentAcceptance?.checked);
                            }

                            if (visible) {
                                window.setTimeout(() => urgentAcceptance?.focus(), 0);
                            }
                        };

                        const syncUrgency = ({ promptForConfirmation = false } = {}) => {
                            const requestType = editor.querySelector('[name="ministry_interior_personal_details_request[type]"]:checked');
                            const urgentWarning = editor.querySelector('[data-ministry-urgent-warning]');
                            const urgent = requestType?.value === 'urgent';
                            const hasStartedRows = rows().some(rowHasContent);

                            setRequired(urgentAcceptance, urgent && hasStartedRows);
                            if (urgentAcceptance && (!urgent || !hasStartedRows)) urgentAcceptance.checked = false;
                            if (urgentConfirm) {
                                urgentConfirm.disabled = !urgentAcceptance?.checked;
                                urgentConfirm.classList.toggle('disabled', !urgentAcceptance?.checked);
                            }
                            if (urgentWarning) urgentWarning.hidden = !urgent || !hasStartedRows || !urgentAcceptance?.checked;

                            const urgentModalIsOpen = urgentModal && !urgentModal.hidden;

                            if (urgent && hasStartedRows && !urgentAcceptance?.checked && promptForConfirmation) {
                                setUrgentModalVisible(true);
                            } else if (!urgent || !hasStartedRows || (urgentAcceptance?.checked && !urgentModalIsOpen)) {
                                setUrgentModalVisible(false);
                            }
                        };

                        const syncFullName = (row) => {
                            const fullName = ['first_name', 'father_name', 'grandfather_name', 'family_name']
                                .map((field) => rowControl(row, field)?.value?.trim() || '')
                                .filter(Boolean)
                                .join(' ');
                            const hidden = rowControl(row, 'current_full_name');
                            if (hidden) hidden.value = fullName;
                        };

                        const attachmentRequiresFile = (attachment) => {
                            if (!attachment || attachment.hidden) return false;
                            const stored = attachment.dataset.stored === 'true';
                            const removeFlag = attachment.querySelector('[data-ministry-attachment-remove-flag]');
                            return !stored || removeFlag?.value === '1';
                        };

                        const syncAttachmentRequirement = (attachment, required) => {
                            if (!attachment) return;
                            const file = attachment.querySelector('input[type="file"]');
                            setRequired(file, required && attachmentRequiresFile(attachment));
                        };

                        const refreshAttachmentNumbers = (row) => {
                            row.querySelectorAll('[data-ministry-attachment-row]:not([hidden])').forEach((attachment, index) => {
                                const number = attachment.querySelector('[data-ministry-attachment-number]');
                                if (number) number.textContent = String(index + 1);
                            });
                        };

                        const rowHasContent = (row) => Array.from(
                            row.querySelectorAll('input, select, textarea'),
                        ).some((control) => {
                            if (
                                control.disabled
                                || ['hidden', 'button', 'submit', 'reset'].includes(control.type)
                            ) {
                                return false;
                            }
                            if (
                                control.name?.endsWith('[confirmed]')
                                || control.name?.endsWith('[signature]')
                            ) {
                                return false;
                            }
                            if (control.type === 'file') return control.files?.length > 0;
                            if (control.type === 'checkbox' || control.type === 'radio') return control.checked;
                            return String(control.value || '').trim() !== '';
                        });

                        const attachmentHasContent = (attachment) => {
                            if (!attachment || attachment.hidden) return false;
                            if (attachment.dataset.stored === 'true') return true;

                            return Array.from(attachment.querySelectorAll('input, select, textarea'))
                                .some((control) => {
                                    if (
                                        control.disabled
                                        || ['hidden', 'button', 'submit', 'reset'].includes(control.type)
                                    ) {
                                        return false;
                                    }
                                    if (control.type === 'file') return control.files?.length > 0;
                                    return String(control.value || '').trim() !== '';
                                });
                        };

                        const syncRow = (row) => {
                            const category = rowControl(row, 'nationality_category');
                            const currentNationality = rowControl(row, 'current_nationality');
                            const originalNationality = rowControl(row, 'original_nationality');
                            const travelDocumentHolder = category?.value === 'travel_document';
                            const started = rowHasContent(row);
                            const currentSection = row.querySelector('[data-ministry-current-nationality]');
                            const originalSection = row.querySelector('[data-ministry-original-nationality]');

                            setSectionVisible(currentSection, !travelDocumentHolder);
                            setRequired(currentNationality, started && !travelDocumentHolder);
                            setSectionVisible(originalSection, travelDocumentHolder);
                            ['travel_document_type', 'original_nationality', 'original_document_country', 'original_first_name', 'original_father_name', 'original_grandfather_name', 'original_family_name']
                                .forEach((field) => setRequired(rowControl(row, field), started && travelDocumentHolder));

                            [
                                'nationality_category',
                                'first_name',
                                'father_name',
                                'grandfather_name',
                                'family_name',
                                'birth_place',
                                'birth_date',
                                'gender',
                                'marital_status',
                                'mother_full_name',
                                'mother_nationality',
                                'education_qualification',
                                'country_of_arrival',
                                'country_of_residence',
                                'schengen_us_visa',
                                'passport_type',
                                'passport_number',
                                'passport_issue_place',
                                'passport_issue_date',
                                'passport_expiry_date',
                                'signature',
                            ].forEach((field) => setRequired(
                                rowControl(row, field, { interactive: field === 'signature' }),
                                started,
                            ));
                            setRequired(rowControl(row, 'confirmed', { interactive: true }), started);

                            const personalNumber = rowControl(row, 'personal_number');
                            const lookupButton = row.querySelector('[data-ministry-personal-details-lookup]');
                            const personalNumberValue = (personalNumber?.value || '').replace(/\D/g, '').slice(0, 10);
                            const validPersonalNumber = /^\d{10}$/.test(personalNumberValue);
                            if (personalNumber) {
                                personalNumber.value = personalNumberValue;
                                personalNumber.setCustomValidity(
                                    personalNumber.value !== '' && !/^\d{10}$/.test(personalNumber.value)
                                        ? lookupInvalidPersonalNumberLabel
                                        : '',
                                );
                            }
                            if (lookupButton) lookupButton.disabled = !category?.value || !validPersonalNumber;

                            const effectiveNationality = travelDocumentHolder ? originalNationality : currentNationality;
                            const residenceCountry = rowControl(row, 'country_of_residence');
                            const residenceMismatch = Boolean(
                                effectiveNationality?.value
                                && residenceCountry?.value
                                && normalizeOptionText(effectiveNationality.value) !== normalizeOptionText(residenceCountry.value),
                            );
                            const residenceExtra = row.querySelector('[data-ministry-residency-extra]');
                            setSectionVisible(residenceExtra, started && residenceMismatch);
                            setRequired(rowControl(row, 'residence_expiry_date'), started && residenceMismatch);

                            const passportAttachment = row.querySelector('[data-ministry-attachment-row][data-document-type="passport"]');
                            const residenceAttachment = row.querySelector('[data-ministry-residence-attachment]');
                            syncAttachmentRequirement(passportAttachment, started);
                            setSectionVisible(residenceAttachment, started && residenceMismatch);
                            syncAttachmentRequirement(residenceAttachment, started && residenceMismatch);

                            const visaAnswer = rowControl(row, 'schengen_us_visa');
                            const visaSection = row.querySelector('[data-ministry-visa-expiry]');
                            const hasVisa = visaAnswer?.value === 'yes';
                            setSectionVisible(visaSection, hasVisa);
                            setRequired(rowControl(row, 'schengen_us_visa_expiry_date'), started && hasVisa);

                            const palestinian = palestinianNationalityCodes.includes(normalizeOptionText(effectiveNationality?.value));
                            const palestinianSection = row.querySelector('[data-ministry-palestinian-passport-id]');
                            setSectionVisible(palestinianSection, palestinian);
                            setRequired(rowControl(row, 'palestinian_passport_id'), started && palestinian);

                            const maritalStatus = rowControl(row, 'marital_status');
                            const married = maritalStatus?.value === 'married';
                            setSectionVisible(row.querySelector('[data-ministry-spouse-section]'), married);
                            ['spouse_nationality', 'spouse_full_name', 'spouse_birth_date', 'spouse_mother_full_name']
                                .forEach((field) => setRequired(rowControl(row, field), started && married));

                            row.querySelectorAll('[data-ministry-attachment-row]').forEach((attachment) => {
                                const documentType = attachment.querySelector('[name$="[document_type]"]');
                                if (documentType && !attachment.dataset.documentType) {
                                    const attachmentStarted = started && attachmentHasContent(attachment);
                                    setRequired(documentType, attachmentStarted);
                                    syncAttachmentRequirement(attachment, attachmentStarted);
                                }
                            });

                            syncFullName(row);
                            refreshAttachmentNumbers(row);
                            window.syncApplicationRequiredFieldMarkers?.();
                        };

                        const initializeRow = (row) => {
                            if (!row) return;
                            syncRow(row);
                        };

                        const refresh = () => {
                            const currentRows = rows();
                            currentRows.forEach((row, index) => {
                                initializeRow(row);
                                const number = row.querySelector('[data-ministry-personal-details-number]');
                                if (number) number.textContent = String(index + 1);
                            });

                            const emptyState = editor.querySelector('[data-ministry-personal-details-empty]');
                            if (emptyState) emptyState.hidden = currentRows.length > 0;
                            editor.querySelectorAll('[data-ministry-personal-details-count]').forEach((counter) => {
                                counter.textContent = (counter.dataset.countTemplate || '__COUNT__')
                                    .replace('__COUNT__', String(currentRows.length));
                            });
                            syncUrgency();
                        };

                        const addRow = () => {
                            const index = Number.parseInt(editor.dataset.nextIndex || '0', 10);
                            rowsContainer.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', String(index)));
                            editor.dataset.nextIndex = String(index + 1);
                            refresh();
                            rowsContainer.lastElementChild?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        };

                        editor.querySelectorAll('[data-ministry-personal-details-add]')
                            .forEach((button) => button.addEventListener('click', addRow));
                        editor.querySelectorAll('[name="ministry_interior_personal_details_request[type]"]')
                            .forEach((control) => control.addEventListener('change', () => {
                                syncUrgency({
                                    promptForConfirmation: control.checked && control.value === 'urgent',
                                });
                            }));
                        urgentAcceptance?.addEventListener('change', () => syncUrgency());
                        urgentConfirm?.addEventListener('click', () => {
                            if (!urgentAcceptance?.checked) return;
                            syncUrgency();
                            setUrgentModalVisible(false);
                        });
                        urgentCancel?.addEventListener('click', () => {
                            if (normalRequestType) normalRequestType.checked = true;
                            if (urgentAcceptance) urgentAcceptance.checked = false;
                            syncUrgency();
                        });
                        urgentModal?.addEventListener('click', (event) => {
                            if (event.target === urgentModal) urgentCancel?.click();
                        });

                        rowsContainer.addEventListener('click', async (event) => {
                            const removePerson = event.target.closest('[data-ministry-personal-details-remove]');
                            if (removePerson) {
                                removePerson.closest('[data-ministry-personal-details-row]')?.remove();
                                refresh();
                                return;
                            }

                            const addAttachment = event.target.closest('[data-ministry-attachment-add]');
                            if (addAttachment) {
                                const row = addAttachment.closest('[data-ministry-personal-details-row]');
                                const attachmentContainer = row?.querySelector('[data-ministry-attachment-rows]');
                                const attachmentTemplate = row?.querySelector('[data-ministry-attachment-template]');
                                const nextIndex = Number.parseInt(row?.dataset.nextAttachmentIndex || '0', 10);
                                if (row && attachmentContainer && attachmentTemplate) {
                                    attachmentContainer.insertAdjacentHTML(
                                        'beforeend',
                                        attachmentTemplate.innerHTML.replaceAll('__ATTACHMENT_INDEX__', String(nextIndex)),
                                    );
                                    row.dataset.nextAttachmentIndex = String(nextIndex + 1);
                                    initializeRow(row);
                                }
                                return;
                            }

                            const removeAttachment = event.target.closest('[data-ministry-attachment-remove]');
                            if (removeAttachment) {
                                const attachment = removeAttachment.closest('[data-ministry-attachment-row]');
                                const removeFlag = attachment?.querySelector('[data-ministry-attachment-remove-flag]');
                                const row = removeAttachment.closest('[data-ministry-personal-details-row]');
                                if (attachment?.dataset.stored === 'true' && removeFlag) {
                                    removeFlag.value = '1';
                                    attachment.hidden = true;
                                } else {
                                    attachment?.remove();
                                }
                                if (row) syncRow(row);
                                return;
                            }

                            const lookupButton = event.target.closest('[data-ministry-personal-details-lookup]');
                            if (!lookupButton) return;

                            const row = lookupButton.closest('[data-ministry-personal-details-row]');
                            const personalNumber = rowControl(row, 'personal_number');
                            const category = rowControl(row, 'nationality_category');
                            const status = row?.querySelector('[data-ministry-personal-details-lookup-status]');
                            if (!row || !personalNumber || !category) return;

                            if (!category.value) {
                                category.setCustomValidity(lookupSelectCategoryLabel);
                                category.reportValidity();
                                return;
                            }
                            category.setCustomValidity('');

                            const personalNumberValue = personalNumber.value.trim();
                            if (!/^\d{10}$/.test(personalNumberValue)) {
                                personalNumber.setCustomValidity(lookupInvalidPersonalNumberLabel);
                                personalNumber.reportValidity();
                                return;
                            }

                            personalNumber.setCustomValidity('');
                            lookupButton.disabled = true;
                            const originalLabel = lookupButton.innerHTML;
                            lookupButton.textContent = lookupLoadingLabel;
                            if (status) status.textContent = '';

                            try {
                                const response = await fetch(lookupUrl, {
                                    method: 'POST',
                                    headers: {
                                        Accept: 'application/json',
                                        'Content-Type': 'application/json',
                                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                                    },
                                    body: JSON.stringify({
                                        personal_number: personalNumberValue,
                                        nationality_category: category.value,
                                    }),
                                });
                                const payload = await response.json().catch(() => null);
                                if (!payload) throw new Error(lookupUnavailableLabel);
                                if (!response.ok || !payload.ok) throw new Error(payload.message || lookupUnavailableLabel);

                                const fieldMap = {
                                    first_name: payload.data?.first_name,
                                    father_name: payload.data?.father_name,
                                    grandfather_name: payload.data?.grandfather_name,
                                    family_name: payload.data?.family_name,
                                    birth_date: payload.data?.birth_date,
                                    birth_place: payload.data?.birth_place,
                                    gender: payload.data?.gender,
                                    mother_full_name: payload.data?.mother_full_name,
                                    mother_nationality: payload.data?.mother_nationality,
                                    marital_status: payload.data?.marital_status,
                                    passport_number: payload.data?.passport_number,
                                    country_of_residence: payload.data?.country_of_residence,
                                };
                                fieldMap[category.value === 'travel_document'
                                    ? 'original_nationality'
                                    : 'current_nationality'] = payload.data?.nationality;
                                Object.entries(fieldMap).forEach(([field, value]) => {
                                    setControlValue(rowControl(row, field), value);
                                });
                                syncRow(row);
                                if (status) {
                                    status.className = 'ministry-personal-details-form__lookup-status text-success';
                                    status.textContent = payload.message || '';
                                }
                            } catch (error) {
                                if (status) {
                                    status.className = 'ministry-personal-details-form__lookup-status text-danger';
                                    status.textContent = error.message || lookupUnavailableLabel;
                                }
                            } finally {
                                lookupButton.innerHTML = originalLabel;
                                syncRow(row);
                            }
                        });

                        ['input', 'change'].forEach((eventName) => {
                            rowsContainer.addEventListener(eventName, (event) => {
                                const row = event.target.closest('[data-ministry-personal-details-row]');
                                if (row) {
                                    syncRow(row);
                                    syncUrgency();
                                }
                            });
                        });

                        editor.closest('form')?.addEventListener('submit', (event) => {
                            const requestType = editor.querySelector('[name="ministry_interior_personal_details_request[type]"]:checked');
                            if (rows().some(rowHasContent) && requestType?.value === 'urgent' && !urgentAcceptance?.checked) {
                                event.preventDefault();
                                syncUrgency({ promptForConfirmation: true });
                                return;
                            }

                            syncUrgency();
                            rows().forEach(syncRow);
                        });

                        refresh();
                        const selectedRequestType = editor.querySelector('[name="ministry_interior_personal_details_request[type]"]:checked');
                        syncUrgency({
                            promptForConfirmation: selectedRequestType?.value === 'urgent' && !urgentAcceptance?.checked,
                        });
                    });
                };

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', initializeMinistryPersonalDetails, { once: true });
                } else {
                    initializeMinistryPersonalDetails();
                }
                document.addEventListener('shown.bs.modal', initializeMinistryPersonalDetails);
            })();
        </script>
    @endpush
@endonce
