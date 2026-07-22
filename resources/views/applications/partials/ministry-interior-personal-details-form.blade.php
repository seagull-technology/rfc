@php
    $ministryInteriorPersonalDetailsReadOnly = (bool) ($ministryInteriorPersonalDetailsReadOnly ?? false);
    $ministryInteriorPersonalDetailsIdPrefix = $ministryInteriorPersonalDetailsIdPrefix ?? 'ministry_interior_personal_details';
    $ministryNationalityOptions = collect($ministryNationalityOptions ?? data_get($nationalityOptions ?? [], 'director', []));
    $ministryGovernorateOptions = \App\Models\Governorate::query()->active()->ordered()->get();
    $submittedDetails = old('ministry_interior_personal_details', $ministryInteriorPersonalDetails ?? []);
    $ministryInteriorPersonalDetailsRows = \App\Support\MinistryInteriorPersonalDetails::rows($submittedDetails);

    if (! $ministryInteriorPersonalDetailsReadOnly && $ministryInteriorPersonalDetailsRows === []) {
        $ministryInteriorPersonalDetailsRows = [[]];
    }
@endphp

@once
    <style>
        .ministry-personal-details-form {
            color: #1f2937;
            width: 100%;
        }

        .ministry-personal-details-form__notices {
            background: #f8f9fb;
            border-inline-start: 4px solid #721f1b;
            margin-bottom: 1.25rem;
            padding: 1rem 1.25rem;
        }

        .ministry-personal-details-form__notice {
            align-items: flex-start;
            display: flex;
            gap: .65rem;
        }

        .ministry-personal-details-form__notice + .ministry-personal-details-form__notice {
            margin-top: .55rem;
        }

        .ministry-personal-details-form__notice i {
            color: #721f1b;
            margin-top: .2rem;
        }

        .ministry-personal-details-form__record {
            background: #fff;
            border: 1px solid #d8dde5;
            border-radius: 4px;
            padding: clamp(1rem, 2.5vw, 2rem);
        }

        .ministry-personal-details-form__record + .ministry-personal-details-form__record {
            margin-top: 1.25rem;
        }

        .ministry-personal-details-form__record-header {
            align-items: center;
            border-bottom: 2px solid #721f1b;
            display: flex;
            justify-content: space-between;
            margin-bottom: 1.5rem;
            padding-bottom: .85rem;
        }

        .ministry-personal-details-form__section {
            border-bottom: 1px solid #e2e6ec;
            padding: .25rem 0 1.5rem;
        }

        .ministry-personal-details-form__section + .ministry-personal-details-form__section {
            padding-top: 1.5rem;
        }

        .ministry-personal-details-form__section:last-child {
            border-bottom: 0;
            padding-bottom: 0;
        }

        .ministry-personal-details-form__section-title {
            color: #721f1b;
            font-size: 1.12rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }

        .ministry-personal-details-form__lookup {
            align-items: flex-end;
            display: grid;
            gap: .75rem;
            grid-template-columns: minmax(220px, 1fr) auto;
        }

        .ministry-personal-details-form__lookup-status {
            font-size: .875rem;
            grid-column: 1 / -1;
            min-height: 1.25rem;
        }

        .ministry-personal-details-form__attachment {
            background: #f8f9fb;
            border: 1px solid #e0e4ea;
            border-radius: 4px;
            padding: 1rem;
        }

        .ministry-personal-details-form__attachment + .ministry-personal-details-form__attachment {
            margin-top: .75rem;
        }

        .ministry-personal-details-form .form-control:disabled,
        .ministry-personal-details-form .form-select:disabled {
            background: #f1f3f6;
            color: #343a40;
            opacity: 1;
        }

        .ministry-personal-details-form__empty {
            border: 1px dashed #c7ccd4;
            border-radius: 4px;
            color: #6b7280;
            padding: 2rem 1rem;
            text-align: center;
        }

        @media (max-width: 767.98px) {
            .ministry-personal-details-form__lookup {
                grid-template-columns: 1fr;
            }

            .ministry-personal-details-form__lookup .btn {
                width: 100%;
            }
        }

        @media print {
            .ministry-personal-details-form__notices,
            .ministry-personal-details-form [data-ministry-personal-details-add],
            .ministry-personal-details-form [data-ministry-personal-details-remove],
            .ministry-personal-details-form [data-ministry-attachment-add],
            .ministry-personal-details-form [data-ministry-attachment-remove],
            .ministry-personal-details-form [data-ministry-personal-details-lookup] {
                display: none !important;
            }

            .ministry-personal-details-form__record {
                break-inside: avoid;
            }
        }
    </style>
@endonce

<div
    class="ministry-personal-details-form"
    @unless($ministryInteriorPersonalDetailsReadOnly)
        data-ministry-personal-details-editor
        data-next-index="{{ count($ministryInteriorPersonalDetailsRows) }}"
    @endunless
>
    <div class="ministry-personal-details-form__notices">
        @foreach (['responsibility', 'required', 'nationality'] as $notice)
            <div class="ministry-personal-details-form__notice">
                <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                <span>{{ __('app.applications.ministry_interior_personal_details.notices.'.$notice) }}</span>
            </div>
        @endforeach
    </div>

    @unless($ministryInteriorPersonalDetailsReadOnly)
        <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap mb-3">
            <button type="button" class="btn btn-success" data-ministry-personal-details-add>
                <i class="fa-solid fa-plus me-2" aria-hidden="true"></i>{{ __('app.applications.ministry_interior_personal_details.add_person') }}
            </button>
            <span class="text-muted" data-ministry-personal-details-count data-count-template="{{ __('app.applications.ministry_interior_personal_details.records_count', ['count' => '__COUNT__']) }}">
                {{ __('app.applications.ministry_interior_personal_details.records_count', ['count' => count($ministryInteriorPersonalDetailsRows)]) }}
            </span>
        </div>
    @endunless

    <div data-ministry-personal-details-rows>
        @forelse ($ministryInteriorPersonalDetailsRows as $rowIndex => $row)
            @include('applications.partials.ministry-interior-personal-details-row', [
                'row' => $row,
                'rowIndex' => $rowIndex,
                'inputIndex' => $rowIndex,
            ])
        @empty
            <div class="ministry-personal-details-form__empty" data-ministry-personal-details-empty>
                {{ __('app.applications.ministry_interior_personal_details.empty_state') }}
            </div>
        @endforelse
    </div>

    @unless($ministryInteriorPersonalDetailsReadOnly)
        <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap mt-3">
            <button type="button" class="btn btn-success" data-ministry-personal-details-add>
                <i class="fa-solid fa-plus me-2" aria-hidden="true"></i>{{ __('app.applications.ministry_interior_personal_details.add_person') }}
            </button>
            <span class="text-muted" data-ministry-personal-details-count data-count-template="{{ __('app.applications.ministry_interior_personal_details.records_count', ['count' => '__COUNT__']) }}">
                {{ __('app.applications.ministry_interior_personal_details.records_count', ['count' => count($ministryInteriorPersonalDetailsRows)]) }}
            </span>
        </div>

        <template data-ministry-personal-details-template>
            @include('applications.partials.ministry-interior-personal-details-row', [
                'row' => [],
                'rowIndex' => 0,
                'inputIndex' => '__INDEX__',
            ])
        </template>
    @endunless
</div>

@unless($ministryInteriorPersonalDetailsReadOnly)
    @once
        @push('scripts')
            <script>
                (() => {
                    const lookupUrl = @js(route('applications.personal-details.lookup'));
                    const lookupLoadingLabel = @js(__('app.applications.ministry_interior_personal_details.lookup_loading'));
                    const lookupUnavailableLabel = @js(__('app.applications.ministry_interior_personal_details.lookup_unavailable'));
                    const lookupSelectCategoryLabel = @js(__('app.applications.ministry_interior_personal_details.lookup_select_category'));
                    const lookupInvalidJordanianLabel = @js(__('app.applications.ministry_interior_personal_details.lookup_invalid_jordanian'));
                    const lookupInvalidNonJordanianLabel = @js(__('app.applications.ministry_interior_personal_details.lookup_invalid_non_jordanian'));
                    const personalNumberLabels = {
                        default: @js(__('app.applications.ministry_interior_personal_details.fields.personal_number')),
                        jordanian: @js(__('app.applications.ministry_interior_personal_details.fields.national_number')),
                        nonJordanian: @js(__('app.applications.ministry_interior_personal_details.fields.individual_number')),
                    };

                    const initializeMinistryPersonalDetails = () => {
                        document.querySelectorAll('[data-ministry-personal-details-editor]').forEach((editor) => {
                            if (editor.dataset.initialized === 'true') return;
                            editor.dataset.initialized = 'true';

                            const rowsContainer = editor.querySelector('[data-ministry-personal-details-rows]');
                            const template = editor.querySelector('[data-ministry-personal-details-template]');
                            if (!rowsContainer || !template) return;

                            const rowControl = (row, field) => row.querySelector(`[name$="[${field}]"]`);
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

                            const syncFullName = (row) => {
                                const fullName = ['first_name', 'father_name', 'grandfather_name', 'family_name']
                                    .map((field) => rowControl(row, field)?.value?.trim() || '')
                                    .filter(Boolean)
                                    .join(' ');
                                const hidden = rowControl(row, 'current_full_name');
                                if (hidden) hidden.value = fullName;
                            };

                            const refreshAttachmentNumbers = (row) => {
                                row.querySelectorAll('[data-ministry-attachment-row]').forEach((attachment, index) => {
                                    const number = attachment.querySelector('[data-ministry-attachment-number]');
                                    if (number) number.textContent = String(index + 1);
                                });
                            };

                            const initializeRow = (row) => {
                                if (!row || row.dataset.rowInitialized === 'true') return;
                                row.dataset.rowInitialized = 'true';

                                ['first_name', 'father_name', 'grandfather_name', 'family_name'].forEach((field) => {
                                    rowControl(row, field)?.addEventListener('input', () => syncFullName(row));
                                });

                                const maritalStatus = rowControl(row, 'marital_status');
                                const spouseSection = row.querySelector('[data-ministry-spouse-section]');
                                const syncSpouse = () => setSectionVisible(spouseSection, maritalStatus?.value === 'married');
                                maritalStatus?.addEventListener('change', syncSpouse);
                                syncSpouse();

                                const nationalityCategory = rowControl(row, 'nationality_category');
                                const personalNumber = rowControl(row, 'personal_number');
                                const personalNumberLabel = row.querySelector('[data-ministry-personal-number-label]');
                                const residencyExtras = row.querySelector('[data-ministry-residency-extra]');
                                const syncResidency = () => setSectionVisible(residencyExtras, nationalityCategory?.value !== 'jordanian');
                                const syncPersonalNumber = () => {
                                    const category = nationalityCategory?.value || '';
                                    const jordanian = category === 'jordanian';
                                    const nonJordanian = category === 'arab' || category === 'foreign';

                                    if (personalNumberLabel) {
                                        personalNumberLabel.textContent = jordanian
                                            ? personalNumberLabels.jordanian
                                            : (nonJordanian ? personalNumberLabels.nonJordanian : personalNumberLabels.default);
                                    }

                                    if (!personalNumber) return;
                                    personalNumber.maxLength = jordanian ? 10 : 20;
                                    if (jordanian) {
                                        personalNumber.pattern = '[0-9]{10}';
                                    } else if (nonJordanian) {
                                        personalNumber.pattern = '[0-9]{1,20}';
                                    } else {
                                        personalNumber.removeAttribute('pattern');
                                    }
                                    personalNumber.setCustomValidity('');
                                };
                                nationalityCategory?.addEventListener('change', () => {
                                    nationalityCategory.setCustomValidity('');
                                    syncResidency();
                                    syncPersonalNumber();
                                });
                                syncResidency();
                                syncPersonalNumber();

                                const previousJordanResidence = rowControl(row, 'previous_jordan_residence');
                                const residenceDocumentNotice = row.querySelector('[data-ministry-residence-document-notice]');
                                const syncResidenceDocumentNotice = () => setSectionVisible(
                                    residenceDocumentNotice,
                                    previousJordanResidence?.value === 'yes',
                                );
                                previousJordanResidence?.addEventListener('change', syncResidenceDocumentNotice);
                                syncResidenceDocumentNotice();

                                refreshAttachmentNumbers(row);
                            };

                            const refresh = () => {
                                const rows = [...rowsContainer.querySelectorAll(':scope > [data-ministry-personal-details-row]')];
                                rows.forEach((row, index) => {
                                    initializeRow(row);
                                    const number = row.querySelector('[data-ministry-personal-details-number]');
                                    if (number) number.textContent = String(index + 1);
                                });
                                editor.querySelectorAll('[data-ministry-personal-details-count]').forEach((counter) => {
                                    counter.textContent = (counter.dataset.countTemplate || '__COUNT__').replace('__COUNT__', String(rows.length));
                                });
                            };

                            const addRow = () => {
                                const index = Number.parseInt(editor.dataset.nextIndex || '0', 10);
                                rowsContainer.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', String(index)));
                                editor.dataset.nextIndex = String(index + 1);
                                refresh();
                                rowsContainer.lastElementChild?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                            };

                            editor.querySelectorAll('[data-ministry-personal-details-add]').forEach((button) => button.addEventListener('click', addRow));

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
                                        attachmentContainer.insertAdjacentHTML('beforeend', attachmentTemplate.innerHTML.replaceAll('__ATTACHMENT_INDEX__', String(nextIndex)));
                                        row.dataset.nextAttachmentIndex = String(nextIndex + 1);
                                        refreshAttachmentNumbers(row);
                                    }
                                    return;
                                }

                                const removeAttachment = event.target.closest('[data-ministry-attachment-remove]');
                                if (removeAttachment) {
                                    const attachmentRow = removeAttachment.closest('[data-ministry-attachment-row]');
                                    const removeFlag = attachmentRow?.querySelector('[data-ministry-attachment-remove-flag]');
                                    const row = removeAttachment.closest('[data-ministry-personal-details-row]');
                                    if (removeFlag?.value !== undefined && attachmentRow?.dataset.stored === 'true') {
                                        removeFlag.value = '1';
                                        attachmentRow.hidden = true;
                                    } else {
                                        attachmentRow?.remove();
                                    }
                                    if (row) refreshAttachmentNumbers(row);
                                    return;
                                }

                                const lookupButton = event.target.closest('[data-ministry-personal-details-lookup]');
                                if (!lookupButton) return;

                                const row = lookupButton.closest('[data-ministry-personal-details-row]');
                                const personalNumber = rowControl(row, 'personal_number');
                                const nationalityCategory = rowControl(row, 'nationality_category');
                                const status = row?.querySelector('[data-ministry-personal-details-lookup-status]');

                                if (!row || !personalNumber || !nationalityCategory) return;

                                const category = nationalityCategory.value;
                                if (!category) {
                                    nationalityCategory.setCustomValidity(lookupSelectCategoryLabel);
                                    nationalityCategory.reportValidity();
                                    return;
                                }

                                nationalityCategory.setCustomValidity('');
                                const personalNumberValue = personalNumber.value.trim();
                                const validPersonalNumber = category === 'jordanian'
                                    ? /^\d{10}$/.test(personalNumberValue)
                                    : /^\d{1,20}$/.test(personalNumberValue);
                                if (!validPersonalNumber) {
                                    personalNumber.setCustomValidity(
                                        category === 'jordanian' ? lookupInvalidJordanianLabel : lookupInvalidNonJordanianLabel,
                                    );
                                    personalNumber?.reportValidity();
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
                                            'Accept': 'application/json',
                                            'Content-Type': 'application/json',
                                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                                        },
                                        body: JSON.stringify({
                                            personal_number: personalNumberValue,
                                            nationality_category: category,
                                        }),
                                    });
                                    const payload = await response.json().catch(() => null);
                                    if (!payload) throw new Error(lookupUnavailableLabel);
                                    if (!response.ok || !payload.ok) throw new Error(payload.message || 'Lookup failed');

                                    const fieldMap = {
                                        first_name: payload.data?.first_name,
                                        father_name: payload.data?.father_name,
                                        grandfather_name: payload.data?.grandfather_name,
                                        family_name: payload.data?.family_name,
                                        birth_date: payload.data?.birth_date,
                                        birth_place: payload.data?.birth_place,
                                        gender: payload.data?.gender,
                                        current_nationality: payload.data?.nationality,
                                        mother_full_name: payload.data?.mother_full_name,
                                        mother_nationality: payload.data?.mother_nationality,
                                        marital_status: payload.data?.marital_status,
                                        passport_number: payload.data?.passport_number,
                                        country_of_residence: payload.data?.country_of_residence,
                                    };
                                    if (category === 'jordanian') {
                                        fieldMap.jordan_residence_address = payload.data?.address;
                                    }
                                    Object.entries(fieldMap).forEach(([field, value]) => {
                                        const control = rowControl(row, field);
                                        setControlValue(control, value);
                                    });
                                    syncFullName(row);
                                    if (status) {
                                        status.className = 'ministry-personal-details-form__lookup-status text-success';
                                        status.textContent = payload.message || '';
                                    }
                                } catch (error) {
                                    if (status) {
                                        status.className = 'ministry-personal-details-form__lookup-status text-danger';
                                        status.textContent = error.message;
                                    }
                                } finally {
                                    lookupButton.disabled = false;
                                    lookupButton.innerHTML = originalLabel;
                                }
                            });

                            refresh();
                        });
                    };

                    if (document.readyState === 'loading') {
                        document.addEventListener('DOMContentLoaded', initializeMinistryPersonalDetails, { once: true });
                    } else {
                        initializeMinistryPersonalDetails();
                    }
                })();
            </script>
        @endpush
    @endonce
@endunless
