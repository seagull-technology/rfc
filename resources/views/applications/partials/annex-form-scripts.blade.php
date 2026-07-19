@once
    @push('scripts')
        @php
            $applicationNationalityOptionsHtml = '<option value="">'.__('app.admin.select_placeholder').'</option>'.
                collect(data_get($nationalityOptions ?? [], 'director', []))
                    ->map(fn ($nationality): string => '<option value="'.e($nationality->code).'">'.e($nationality->displayName()).'</option>')
                    ->implode('');
	            $applicationGovernorateOptionsHtml = '<option value="">'.__('app.admin.select_placeholder').'</option>'.
	                collect(data_get($locationLookupOptions ?? [], 'governorates', []))
	                    ->map(fn ($governorate): string => '<option value="'.e($governorate->code).'">'.e($governorate->displayName()).'</option>')
	                    ->implode('');
		            $applicationGenderOptionsHtml = '<option value="">'.__('app.admin.select_placeholder').'</option>'.
		                collect(['male', 'female'])
		                    ->map(fn ($gender): string => '<option value="'.e($gender).'">'.e(__('app.auth.gender_options.'.$gender)).'</option>')
		                    ->implode('');
                $applicationSpecialLocationRequirementOptions = collect($locationRequirementOptions ?? [])
                    ->map(fn ($code): array => ['code' => $code, 'label' => ($locationRequirementLabels[$code] ?? __('app.applications.special_location_requirements.'.$code))])
                    ->values();
                $applicationSupportAuthorityOptions = collect([
                    'public_security' => __('app.applications.support_authorities.public_security'),
                    'military' => __('app.applications.support_authorities.military'),
                ])->map(fn ($label, $code): array => ['code' => $code, 'label' => $label])->values();
                $applicationEquipmentCategoryOptions = collect(data_get($formLookupOptions ?? [], 'equipment_categories', []))
                    ->map(fn ($option): array => ['code' => $option->code, 'label' => $option->displayName()])
                    ->values();
                $applicationEquipmentEntryPointOptions = collect(data_get($formLookupOptions ?? [], 'equipment_entry_points', []))
                    ->map(fn ($option): array => ['code' => $option->code, 'label' => $option->displayName()])
                    ->values();
                $applicationLocationTypeApprovalDays = (array) data_get($locationLookupOptions ?? [], 'location_type_approval_days', []);
                $maxCrewBirthDate = $maxCrewBirthDate ?? now()->subDay()->toDateString();
                $minimumFilmingLocationStartDate = $minimumFilmingLocationStartDate ?? \App\Support\JordanBusinessDays::today()->toDateString();
	        @endphp
	        <script>
		            const applicationNationalityOptionsHtml = @js($applicationNationalityOptionsHtml);
		            const applicationGovernorateOptionsHtml = @js($applicationGovernorateOptionsHtml);
		            const applicationGenderOptionsHtml = @js($applicationGenderOptionsHtml);
                const applicationCrewBirthDateMax = @js($maxCrewBirthDate);
                const applicationFilmingLocationStartMin = @js($minimumFilmingLocationStartDate);
                const applicationSpecialLocationRequirementOptions = @json($applicationSpecialLocationRequirementOptions);
                const applicationSupportAuthorityOptions = @json($applicationSupportAuthorityOptions);
                const applicationLocationCardLabels = {
                    locationNumber: @js(__('app.applications.location_number', ['number' => '__NUMBER__'])),
                    governorate: @js(__('app.scouting.governorate')),
                    locationType: @js(__('app.applications.annex_fields.location_type')),
                    specialRequirement: @js(__('app.applications.special_requirement')),
                    locationName: @js(__('app.applications.annex_fields.location_exact_name')),
                    locationAddress: @js(__('app.applications.annex_fields.location_address')),
                    locationNature: @js(__('app.applications.annex_fields.location_nature')),
                    startDate: @js(__('app.scouting.start_date')),
                    endDate: @js(__('app.scouting.end_date')),
                    notes: @js(__('app.applications.annex_fields.notes')),
                    supportTitle: @js(__('app.applications.location_support_requirements_title')),
                    authority: @js(__('app.applications.annex_fields.authority_name')),
                    requirement: @js(__('app.applications.annex_fields.requirement')),
                    date: @js(__('app.applications.annex_fields.date')),
                    timeFrom: @js(__('app.applications.annex_fields.time_from')),
                    timeTo: @js(__('app.applications.annex_fields.time_to')),
                    deleteLabel: @js(__('app.delete')),
                    addLabel: @js(__('app.add')),
                    addRequirementLabel: @js(__('app.applications.location_support_add_requirement')),
                    supportDateRangeMessage: @js(__('app.applications.location_support_date_range')),
                    supportNotesPrompt: @js(__('app.applications.location_support_notes_prompt')),
                    approvalDaysNotice: @js(__('app.applications.location_type_approval_days_notice')),
                };
                const applicationWorkSummaryMessages = {
                    counter: @js(__('app.applications.work_summary_word_counter')),
                    minWords: @js(__('app.applications.work_summary_min_words_validation', ['min' => 500])),
                    arabicOnly: @js(__('app.applications.work_summary_arabic_only_validation')),
                };
                const applicationCastCrewLabels = {
                    firstName: @js(__('app.applications.annex_fields.first_name')),
                    secondName: @js(__('app.applications.annex_fields.second_name')),
                    thirdName: @js(__('app.applications.annex_fields.third_name')),
                    familyName: @js(__('app.applications.annex_fields.family_name')),
                    nationalId: @js(__('app.applications.annex_fields.national_id')),
                    passportNumber: @js(__('app.applications.annex_fields.passport_number')),
                    nationalIdDigits: @js(__('app.applications.cast_crew_national_id_digits')),
                };

                function refreshProductionTermsForeignApplicant() {
                    const source = document.querySelector('[name="international_producer_name"]');

                    if (! source) {
                        return;
                    }

                    const value = source.value.trim();

                    document.querySelectorAll('[data-production-terms-foreign-applicant]').forEach(function (target) {
                        target.value = value || target.dataset.emptyValue || '';
                    });
                }
                const applicationEquipmentCategoryOptions = @json($applicationEquipmentCategoryOptions);
                const applicationEquipmentEntryPointOptions = @json($applicationEquipmentEntryPointOptions);
	            const applicationLocationTypesByGovernorate = @json((array) data_get($locationLookupOptions ?? [], 'location_types_by_governorate', []));
	            const applicationLocationTypeLabels = @json((array) data_get($locationLookupOptions ?? [], 'location_type_labels', []));
                const applicationLocationTypeApprovalDays = @json($applicationLocationTypeApprovalDays);
	            const applicationLocationTypePlaceholder = @js(__('app.admin.select_placeholder'));

            function applicationLocationTypeOptionsHtml(governorate, selectedValue) {
                const codes = applicationLocationTypesByGovernorate[governorate] || Object.keys(applicationLocationTypeLabels);
                const selected = String(selectedValue || '');

                return '<option value="">' + applicationLocationTypePlaceholder + '</option>'
                    + codes.map(function (code) {
                        const safeCode = String(code).replace(/"/g, '&quot;');
                        const safeLabel = String(applicationLocationTypeLabels[code] || code).replace(/</g, '&lt;').replace(/>/g, '&gt;');
                        const selectedAttribute = selected === String(code) ? ' selected' : '';

                        return '<option value="' + safeCode + '"' + selectedAttribute + '>' + safeLabel + '</option>';
                    }).join('');
            }

            function refreshApplicationLocationTypeSelect(row) {
                if (!row) {
                    return;
                }

                const governorate = row.querySelector('[data-location-governorate]');
                const locationType = row.querySelector('[data-location-type-select]');

                if (!governorate || !locationType) {
                    return;
                }

                const selected = locationType.value || locationType.dataset.selectedType || '';

                locationType.innerHTML = applicationLocationTypeOptionsHtml(governorate.value, selected);
                locationType.value = selected;

                if (locationType.value !== selected) {
                    locationType.value = '';
                }

                locationType.dataset.selectedType = locationType.value;
                refreshApplicationLocationApprovalNote(row);
            }

            function applicationParseYmd(value) {
                const parts = String(value || '').split('-').map(function (part) {
                    return parseInt(part, 10);
                });

                if (parts.length !== 3 || parts.some(Number.isNaN)) {
                    return null;
                }

                return new Date(Date.UTC(parts[0], parts[1] - 1, parts[2]));
            }

            function applicationFormatYmd(date) {
                const year = date.getUTCFullYear();
                const month = String(date.getUTCMonth() + 1).padStart(2, '0');
                const day = String(date.getUTCDate()).padStart(2, '0');

                return year + '-' + month + '-' + day;
            }

            function applicationAddJordanBusinessDays(startDate, days) {
                const date = applicationParseYmd(startDate);
                let remaining = Math.max(0, parseInt(days || '0', 10));

                if (!date) {
                    return startDate;
                }

                while (remaining > 0) {
                    date.setUTCDate(date.getUTCDate() + 1);

                    if (date.getUTCDay() !== 5 && date.getUTCDay() !== 6) {
                        remaining -= 1;
                    }
                }

                return applicationFormatYmd(date);
            }

            function applicationMaxYmd(firstDate, secondDate) {
                if (!firstDate) {
                    return secondDate || '';
                }

                if (!secondDate) {
                    return firstDate;
                }

                return secondDate > firstDate ? secondDate : firstDate;
            }

            function applicationLocationStartMinimumForType(locationType) {
                const days = parseInt(applicationLocationTypeApprovalDays[locationType] || '0', 10);

                if (days <= 0) {
                    return applicationFilmingLocationStartMin;
                }

                return applicationMaxYmd(
                    applicationFilmingLocationStartMin,
                    applicationAddJordanBusinessDays(applicationFilmingLocationStartMin, days)
                );
            }

            function applicationLocationStartMinimumForCard(card) {
                const locationType = card ? card.querySelector('[data-location-type-select]') : null;

                return applicationLocationStartMinimumForType(locationType ? locationType.value : '');
            }

            function refreshApplicationLocationApprovalNote(row) {
                if (!row) {
                    return;
                }

                const locationType = row.querySelector('[data-location-type-select]');
                const note = row.querySelector('[data-location-type-approval-note]');
                const startDate = row.querySelector('[data-location-start-date]');

                if (!locationType || !note) {
                    return;
                }

                const days = parseInt(applicationLocationTypeApprovalDays[locationType.value] || '0', 10);
                const minimumStartDate = applicationLocationStartMinimumForType(locationType.value);

                if (startDate) {
                    startDate.min = minimumStartDate;
                }

                if (days > 0) {
                    note.textContent = applicationLocationCardLabels.approvalDaysNotice
                        .replace(':days', String(days))
                        .replace(':date', minimumStartDate);
                    note.classList.remove('d-none');

                    return;
                }

                note.textContent = '';
                note.classList.add('d-none');
            }

            function refreshApplicationLocationTypeSelects(root) {
                (root || document).querySelectorAll('[data-location-governorate]').forEach(function (governorate) {
                    refreshApplicationLocationTypeSelect(governorate.closest('tr'));
                });
            }

	            function applicationEscapeHtml(value) {
	                return String(value || '')
	                    .replace(/&/g, '&amp;')
	                    .replace(/</g, '&lt;')
	                    .replace(/>/g, '&gt;')
	                    .replace(/"/g, '&quot;')
	                    .replace(/'/g, '&#039;');
	            }

            function applicationSupportRequirementLabel(row) {
                const requirement = row ? row.querySelector('[data-location-support-requirement-select]') : null;

                if (!requirement || !requirement.value) {
                    return '';
                }

                const option = requirement.options[requirement.selectedIndex];

                return option ? String(option.text || '').trim() : String(requirement.value || '').trim();
            }

            function updateFilmingLocationSupportRequirementNotes(row) {
                const notes = row ? row.querySelector('[data-location-support-notes]') : null;
                const marker = row ? row.querySelector('[data-location-support-notes-required-marker]') : null;
                const help = row ? row.querySelector('[data-location-support-notes-help]') : null;
                const requirementLabel = applicationSupportRequirementLabel(row);

                if (!notes) {
                    return;
                }

                if (!requirementLabel) {
                    notes.required = false;
                    notes.placeholder = '';
                    notes.setCustomValidity('');

                    if (marker) {
                        marker.classList.add('d-none');
                    }

                    if (help) {
                        help.textContent = '';
                        help.classList.add('d-none');
                    }

                    return;
                }

                const message = applicationLocationCardLabels.supportNotesPrompt.replace(':requirement', requirementLabel);

                notes.required = true;
                notes.placeholder = message;
                notes.setCustomValidity(String(notes.value || '').trim() ? '' : message);

                if (marker) {
                    marker.classList.remove('d-none');
                }

                if (help) {
                    help.textContent = message;
                    help.classList.remove('d-none');
                }
            }

            function refreshFilmingLocationSupportRequirementNotes(root) {
                (root || document).querySelectorAll('[data-location-support-requirement-row]').forEach(updateFilmingLocationSupportRequirementNotes);
            }

                function applicationLookupOptionsHtml(options, selectedValue) {
                    const selected = String(selectedValue || '');

                    return '<option value="">' + applicationLocationTypePlaceholder + '</option>'
                        + (options || []).map(function (option) {
                            const code = String(option.code || '');
                            const selectedAttribute = selected === code ? ' selected' : '';

                            return '<option value="' + applicationEscapeHtml(code) + '"' + selectedAttribute + '>'
                                + applicationEscapeHtml(option.label || code)
                                + '</option>';
                        }).join('');
                }

            function applicationSpecialLocationRequirementOptionsHtml(selectedValues) {
                const selected = new Set((selectedValues || []).map(function (value) {
                    return String(value || '');
                }));

                return applicationSpecialLocationRequirementOptions.map(function (option) {
                    const code = String(option.code || '');
                    const selectedAttribute = selected.has(code) ? ' selected' : '';

                    return '<option value="' + applicationEscapeHtml(code) + '"' + selectedAttribute + '>'
                        + applicationEscapeHtml(option.label || code)
                        + '</option>';
                }).join('');
            }

            function applicationSupportAuthorityOptionsHtml(selectedValue) {
                const selected = String(selectedValue || '');

                return '<option value="">' + applicationLocationTypePlaceholder + '</option>'
                    + applicationSupportAuthorityOptions.map(function (option) {
                        const code = String(option.code || '');
                        const selectedAttribute = selected === code ? ' selected' : '';

                        return '<option value="' + applicationEscapeHtml(code) + '"' + selectedAttribute + '>'
                            + applicationEscapeHtml(option.label || code)
                        + '</option>';
                }).join('');
            }

            function initializeApplicationEnhancedSelects(root) {
                if (!window.jQuery || !window.jQuery.fn || !window.jQuery.fn.select2) {
                    return;
                }

                const $root = window.jQuery(root || document);

                $root.find('select.select2-basic-single, select.select2-basic-multiple')
                    .add($root.filter('select.select2-basic-single, select.select2-basic-multiple'))
                    .each(function () {
                        const $select = window.jQuery(this);
                        const $offcanvas = $select.closest('.offcanvas');
                        const options = { width: '100%' };

                        if ($select.data('select2')) {
                            $select.select2('destroy');
                        }

                        if ($offcanvas.length) {
                            options.dropdownParent = $offcanvas;
                        }

                        $select.select2(options);
                    });
            }

            function filmingLocationSupportRequirementHtml(locationIndex, supportIndex) {
                return '<div class="application-location-support-row" data-location-support-requirement-row>'
                    + '<div class="d-flex justify-content-between align-items-center gap-2 mb-3">'
                    + '<span class="badge bg-light text-dark border">#' + (supportIndex + 1) + '</span>'
                    + '<button type="button" class="btn btn-sm btn-icon btn-danger-subtle rounded" onclick="removeFilmingLocationSupportRequirement(this)" aria-label="' + applicationEscapeHtml(applicationLocationCardLabels.deleteLabel) + '"><i class="ph-fill ph ph-trash-simple fs-6"></i></button>'
                    + '</div>'
                    + '<div class="row g-3">'
                    + '<div class="col-md-6 col-xl-2"><label class="form-label">' + applicationEscapeHtml(applicationLocationCardLabels.authority) + '</label><select class="form-select select2-basic-single" name="filming_locations[' + locationIndex + '][support_requirements][' + supportIndex + '][authority]">' + applicationSupportAuthorityOptionsHtml('') + '</select></div>'
                    + '<div class="col-md-6 col-xl-3"><label class="form-label">' + applicationEscapeHtml(applicationLocationCardLabels.requirement) + '</label><select class="form-select select2-basic-single" name="filming_locations[' + locationIndex + '][support_requirements][' + supportIndex + '][requirement]" data-location-support-requirement-select><option value="">' + applicationLocationTypePlaceholder + '</option>' + applicationSpecialLocationRequirementOptionsHtml([]) + '</select></div>'
                    + '<div class="col-md-6 col-xl-2"><label class="form-label">' + applicationEscapeHtml(applicationLocationCardLabels.date) + '</label><input type="date" class="form-control" name="filming_locations[' + locationIndex + '][support_requirements][' + supportIndex + '][date]" data-location-support-date></div>'
                    + '<div class="col-md-6 col-xl-2"><label class="form-label">' + applicationEscapeHtml(applicationLocationCardLabels.timeFrom) + '</label><input type="time" class="form-control" name="filming_locations[' + locationIndex + '][support_requirements][' + supportIndex + '][time_from]"></div>'
                    + '<div class="col-md-6 col-xl-2"><label class="form-label">' + applicationEscapeHtml(applicationLocationCardLabels.timeTo) + '</label><input type="time" class="form-control" name="filming_locations[' + locationIndex + '][support_requirements][' + supportIndex + '][time_to]"></div>'
                    + '<div class="col-md-6 col-xl-12"><label class="form-label">' + applicationEscapeHtml(applicationLocationCardLabels.notes) + ' <span class="text-danger d-none" data-location-support-notes-required-marker>*</span></label><textarea class="form-control" name="filming_locations[' + locationIndex + '][support_requirements][' + supportIndex + '][notes]" rows="2" data-location-support-notes></textarea><div class="form-text text-danger fw-semibold d-none" data-location-support-notes-help></div></div>'
                    + '</div>'
                    + '</div>';
            }

            function filmingLocationCardHtml(tableId, index) {
                const displayNumber = index + 1;
                const locationKey = 'location_' + Date.now() + '_' + index;

                return '<td>'
                    + '<div class="application-location-card" data-filming-location-card>'
                    + '<input type="hidden" name="filming_locations[' + index + '][location_key]" value="' + locationKey + '" data-location-key>'
                    + '<div class="application-location-card__header">'
                    + '<h5 class="mb-0">' + applicationEscapeHtml(applicationLocationCardLabels.locationNumber.replace('__NUMBER__', '')) + '<span class="row-number">' + displayNumber + '</span></h5>'
                    + "<button type=\"button\" class=\"btn btn-sm btn-icon btn-danger-subtle rounded\" onclick=\"removeApplicationAnnexRow(this, '#" + tableId + "')\" aria-label=\"" + applicationEscapeHtml(applicationLocationCardLabels.deleteLabel) + "\"><i class=\"ph-fill ph ph-trash-simple fs-6\"></i></button>"
                    + '</div>'
                    + '<div class="application-location-card__section"><div class="row g-3">'
                    + '<div class="col-md-6 col-xl-3"><label class="form-label">' + applicationEscapeHtml(applicationLocationCardLabels.governorate) + '</label><select class="form-select" name="filming_locations[' + index + '][governorate]" data-location-governorate required>' + applicationGovernorateOptionsHtml + '</select></div>'
                    + '<div class="col-md-6 col-xl-3"><label class="form-label">' + applicationEscapeHtml(applicationLocationCardLabels.locationType) + '</label><select class="form-select" name="filming_locations[' + index + '][location_type]" data-location-type-select required>' + applicationLocationTypeOptionsHtml('', '') + '</select><div class="form-text text-warning fw-semibold d-none" data-location-type-approval-note></div></div>'
                    + '<div class="col-md-6 col-xl-3"><label class="form-label">' + applicationEscapeHtml(applicationLocationCardLabels.locationName) + '</label><input type="text" class="form-control" name="filming_locations[' + index + '][location_name]" data-location-name required></div>'
                    + '<div class="col-md-6"><label class="form-label">' + applicationEscapeHtml(applicationLocationCardLabels.locationAddress) + '</label><input type="text" class="form-control" name="filming_locations[' + index + '][address]"></div>'
                    + '<div class="col-md-6"><label class="form-label">' + applicationEscapeHtml(applicationLocationCardLabels.locationNature) + '</label><textarea class="form-control" name="filming_locations[' + index + '][nature]" rows="2" required></textarea></div>'
                    + '<div class="col-md-6 col-xl-3"><label class="form-label">' + applicationEscapeHtml(applicationLocationCardLabels.startDate) + '</label><input type="date" class="form-control" name="filming_locations[' + index + '][start_date]" min="' + applicationEscapeHtml(applicationFilmingLocationStartMin) + '" data-location-start-date required></div>'
                    + '<div class="col-md-6 col-xl-3"><label class="form-label">' + applicationEscapeHtml(applicationLocationCardLabels.endDate) + '</label><input type="date" class="form-control" name="filming_locations[' + index + '][end_date]" data-location-end-date required></div>'
                    + '</div></div>'
                    + '</div>'
                    + '</td>';
            }

            function filmingLocationIndexForCard(card) {
                const field = card ? card.querySelector('[name^="filming_locations["]') : null;
                const match = field && field.name ? field.name.match(/^filming_locations\[([^\]]+)\]/) : null;

                return match ? match[1] : '0';
            }

            function renumberFilmingLocationSupportRequirements(card) {
                const locationIndex = filmingLocationIndexForCard(card);

                card.querySelectorAll('[data-location-support-requirement-row]').forEach(function (row, supportIndex) {
                    const badge = row.querySelector('.badge');

                    if (badge) {
                        badge.textContent = '#' + (supportIndex + 1);
                    }

                    row.querySelectorAll('[name]').forEach(function (field) {
                        field.name = field.name.replace(
                            /^filming_locations\[[^\]]+\]\[support_requirements\]\[[^\]]+\]/,
                            'filming_locations[' + locationIndex + '][support_requirements][' + supportIndex + ']'
                        );
                    });
                });
            }

            function updateFilmingLocationDateConstraints(card) {
                if (!card) {
                    return;
                }

                const startDate = card.querySelector('[data-location-start-date]');
                const endDate = card.querySelector('[data-location-end-date]');
                const startValue = startDate ? String(startDate.value || '') : '';
                const endValue = endDate ? String(endDate.value || '') : '';

                if (startDate) {
                    startDate.min = applicationLocationStartMinimumForCard(card);
                }

                if (endDate) {
                    if (startValue) {
                        endDate.min = startValue;
                    } else {
                        endDate.removeAttribute('min');
                    }
                }

                card.querySelectorAll('[data-location-support-date]').forEach(function (supportDate) {
                    supportDate.setCustomValidity('');

                    if (startValue) {
                        supportDate.min = startValue;
                    } else {
                        supportDate.removeAttribute('min');
                    }

                    if (endValue) {
                        supportDate.max = endValue;
                    } else {
                        supportDate.removeAttribute('max');
                    }

                    if (supportDate.value && startValue && supportDate.value < startValue) {
                        supportDate.setCustomValidity(applicationLocationCardLabels.supportDateRangeMessage);
                    }

                    if (supportDate.value && endValue && supportDate.value > endValue) {
                        supportDate.setCustomValidity(applicationLocationCardLabels.supportDateRangeMessage);
                    }
                });
            }

            function refreshFilmingLocationDateConstraints(root) {
                (root || document).querySelectorAll('.application-location-card').forEach(updateFilmingLocationDateConstraints);
            }

            function addFilmingLocationSupportRequirement(button) {
                const card = button.closest('.application-location-card');
                const container = card ? card.querySelector('[data-location-support-requirements]') : null;

                if (!card || !container) {
                    return;
                }

                const locationIndex = filmingLocationIndexForCard(card);
                const supportIndex = container.querySelectorAll('[data-location-support-requirement-row]').length;

                container.insertAdjacentHTML('beforeend', filmingLocationSupportRequirementHtml(locationIndex, supportIndex));
                renumberFilmingLocationSupportRequirements(card);
                initializeApplicationEnhancedSelects(container.lastElementChild);
                updateFilmingLocationDateConstraints(card);
                refreshFilmingLocationSupportRequirementNotes(container.lastElementChild);
            }

            function removeFilmingLocationSupportRequirement(button) {
                const card = button.closest('.application-location-card');
                const container = card ? card.querySelector('[data-location-support-requirements]') : null;
                const row = button.closest('[data-location-support-requirement-row]');

                if (!card || !container || !row) {
                    return;
                }

                row.remove();

                if (!container.querySelector('[data-location-support-requirement-row]')) {
                    container.insertAdjacentHTML('beforeend', filmingLocationSupportRequirementHtml(filmingLocationIndexForCard(card), 0));
                }

                renumberFilmingLocationSupportRequirements(card);
                updateFilmingLocationDateConstraints(card);
                refreshFilmingLocationSupportRequirementNotes(card);
            }

            function filmingLocationRowKey(row, fallbackIndex) {
                const locationName = row ? row.querySelector('input[name^="filming_locations"][name$="[location_name]"]') : null;
                const match = locationName && locationName.name ? locationName.name.match(/^filming_locations\[([^\]]+)\]/) : null;

                return match ? match[1] : String(fallbackIndex);
            }

            function collectFilmingLocationOptions() {
                const table = document.querySelector('#filmingLocationsRequestTable tbody') || document.querySelector('#filmingLocationsTable tbody');

                if (!table) {
                    return [];
                }

                return Array.from(table.querySelectorAll('tr')).map(function (row, index) {
                    const locationName = row.querySelector('input[name^="filming_locations"][name$="[location_name]"]');
                    const typedName = locationName ? String(locationName.value || '').trim() : '';
                    const label = typedName || @js(__('app.applications.location_number', ['number' => '__NUMBER__'])).replace('__NUMBER__', index + 1);

                    return {
                        key: filmingLocationRowKey(row, index),
                        label: label,
                    };
                });
            }

            function refreshSelect2Control(select) {
                if (!window.jQuery || !window.jQuery.fn || !window.jQuery.fn.select2) {
                    return;
                }

                const control = window.jQuery(select);
                const options = {
                    width: '100%',
                };
                const dropdownParent = control.closest('.offcanvas');

                if (dropdownParent.length) {
                    options.dropdownParent = dropdownParent;
                }

                if (control.data('select2')) {
                    control.select2('destroy');
                }

                control.select2(options);
            }

            function refreshSpecialLocationRequirementSelects() {
                const locations = collectFilmingLocationOptions();

                document.querySelectorAll('[data-special-location-select]').forEach(function (select) {
                    const selectedKeys = Array.from(select.selectedOptions || []).map(function (option) {
                        return option.dataset.locationKey;
                    }).filter(Boolean);
                    const selectedValues = Array.from(select.selectedOptions || []).map(function (option) {
                        return option.value;
                    });

                    select.innerHTML = locations.map(function (location) {
                        const selected = selectedKeys.includes(location.key) || selectedValues.includes(location.label);

                        return '<option value="' + applicationEscapeHtml(location.label) + '" data-location-key="' + applicationEscapeHtml(location.key) + '"' + (selected ? ' selected' : '') + '>' + applicationEscapeHtml(location.label) + '</option>';
                    }).join('');

                    select.disabled = locations.length === 0;
                    refreshSelect2Control(select);
                });
            }

            function equipmentTravelerRowKey(row, fallbackIndex) {
                const travelerName = row ? row.querySelector('input[name^="equipment_travelers"][name$="[traveler_name]"]') : null;
                const match = travelerName && travelerName.name ? travelerName.name.match(/^equipment_travelers\[([^\]]+)\]/) : null;

                return match ? match[1] : String(fallbackIndex);
            }

            function collectEquipmentTravelerOptions() {
                const table = document.querySelector('#equipmentTravelersTable tbody');

                if (!table) {
                    return [];
                }

                return Array.from(table.querySelectorAll('tr')).map(function (row, index) {
                    const travelerName = row.querySelector('input[name^="equipment_travelers"][name$="[traveler_name]"]');
                    const typedName = travelerName ? String(travelerName.value || '').trim() : '';
                    const label = typedName || @js(__('app.applications.traveler_number', ['number' => '__NUMBER__'])).replace('__NUMBER__', index + 1);

                    return {
                        key: equipmentTravelerRowKey(row, index),
                        label: label,
                    };
                });
            }

            function equipmentTravelerOptionsHtml(selectedValue, selectedKey) {
                const travelers = collectEquipmentTravelerOptions();
                const selected = String(selectedValue || '');
                const selectedTravelerKey = String(selectedKey || '');

                return '<option value="">' + applicationLocationTypePlaceholder + '</option>'
                    + travelers.map(function (traveler) {
                        const isSelected = (selectedTravelerKey && selectedTravelerKey === String(traveler.key)) || selected === String(traveler.label);

                        return '<option value="' + applicationEscapeHtml(traveler.label) + '" data-traveler-key="' + applicationEscapeHtml(traveler.key) + '"' + (isSelected ? ' selected' : '') + '>' + applicationEscapeHtml(traveler.label) + '</option>';
                    }).join('');
            }

            function refreshEquipmentTravelerSelects() {
                const travelers = collectEquipmentTravelerOptions();

                document.querySelectorAll('[data-equipment-traveler-select]').forEach(function (select) {
                    const selectedOption = select.selectedOptions && select.selectedOptions.length ? select.selectedOptions[0] : null;
                    const selectedKey = selectedOption ? selectedOption.dataset.travelerKey || '' : '';
                    const selectedValue = selectedOption ? selectedOption.value : select.value;

                    select.innerHTML = equipmentTravelerOptionsHtml(selectedValue, selectedKey);
                    select.disabled = travelers.length === 0;
                });
            }

            function renumberApplicationAnnexRows(selector) {
                document.querySelectorAll(selector + ' tbody tr').forEach(function (row, index) {
                    const cell = row.querySelector('.row-number');

                    if (cell) {
                        cell.textContent = index + 1;
                    }
                });
            }

            function updateEquipmentTotals() {
                document.querySelectorAll('[data-equipment-total]').forEach(function (target) {
                    const table = document.querySelector(target.dataset.equipmentTotal);

                    if (!table) {
                        target.textContent = '0';
                        return;
                    }

                table.querySelectorAll('tbody tr').forEach(function (row) {
                    const quantityField = row.querySelector('[data-equipment-quantity]');
                    const unitValueField = row.querySelector('[data-equipment-unit-value]');
                    const totalField = row.querySelector('[data-equipment-row-total]');

                    if (!quantityField || !unitValueField || !totalField) {
                        return;
                    }

                    const hasQuantity = quantityField.value.trim() !== '';
                    const hasUnitValue = unitValueField.value.trim() !== '';
                    const quantity = Number.parseFloat(quantityField.value);
                    const unitValue = Number.parseFloat(unitValueField.value);

                    totalField.value = hasQuantity && hasUnitValue && Number.isFinite(quantity) && Number.isFinite(unitValue)
                        ? String(Math.round((quantity * unitValue + Number.EPSILON) * 100) / 100)
                        : '';
                });

                const total = Array.from(table.querySelectorAll('[data-equipment-row-total]'))
                    .reduce(function (sum, field) {
                        return sum + (Number.parseFloat(field.value) || 0);
                        }, 0);

                    target.textContent = total.toLocaleString(undefined, {
                        maximumFractionDigits: 2,
                    });
                });
            }

            function countApplicationArabicWords(value) {
                const matches = String(value || '').match(/[\u0600-\u06FF\u0750-\u077F\u08A0-\u08FF\uFB50-\uFDFF\uFE70-\uFEFF0-9٠-٩]+/g);

                return matches ? matches.length : 0;
            }

            function hasInvalidApplicationArabicCharacters(value) {
                return /[^\u0600-\u06FF\u0750-\u077F\u08A0-\u08FF\uFB50-\uFDFF\uFE70-\uFEFF0-9٠-٩\s.,،؛;:!؟?\-()[\]"'\/%&]/u.test(String(value || ''));
            }

            function sanitizeApplicationArabicText(value) {
                return String(value || '').replace(/[^\u0600-\u06FF\u0750-\u077F\u08A0-\u08FF\uFB50-\uFDFF\uFE70-\uFEFF0-9٠-٩\s.,،؛;:!؟?\-()[\]"'\/%&]/gu, '');
            }

            function sanitizeApplicationWorkSummaryInput(field) {
                const original = String(field.value || '');
                const sanitized = sanitizeApplicationArabicText(original);

                if (original === sanitized) {
                    return false;
                }

                const start = field.selectionStart;
                const removedCount = original.length - sanitized.length;

                field.value = sanitized;

                if (typeof start === 'number') {
                    const caret = Math.max(0, start - removedCount);
                    field.setSelectionRange(caret, caret);
                }

                return true;
            }

            function insertApplicationWorkSummaryText(field, text) {
                const sanitized = sanitizeApplicationArabicText(text);

                if (!sanitized) {
                    return;
                }

                const start = field.selectionStart ?? field.value.length;
                const end = field.selectionEnd ?? start;

                field.setRangeText(sanitized, start, end, 'end');
                field.dispatchEvent(new Event('input', { bubbles: true }));
            }

            function validateApplicationWorkSummary(field) {
                if (!field) {
                    return;
                }

                sanitizeApplicationWorkSummaryInput(field);

                const value = String(field.value || '');
                const minWords = Number.parseInt(field.dataset.workSummaryMinWords || '500', 10);
                const wordCount = countApplicationArabicWords(value);
                const hasValue = value.trim() !== '';
                const invalidArabic = hasValue && hasInvalidApplicationArabicCharacters(value);
                const belowMinimum = hasValue && Number.isFinite(minWords) && wordCount < minWords;
                const counter = field.dataset.workSummaryCounter ? document.querySelector(field.dataset.workSummaryCounter) : null;

                if (invalidArabic) {
                    field.setCustomValidity(applicationWorkSummaryMessages.arabicOnly);
                } else if (belowMinimum) {
                    field.setCustomValidity(applicationWorkSummaryMessages.minWords);
                } else {
                    field.setCustomValidity('');
                }

                if (counter) {
                    counter.textContent = applicationWorkSummaryMessages.counter
                        .replace(':count', String(wordCount))
                        .replace(':min', String(minWords));
                    counter.classList.toggle('text-success', hasValue && !invalidArabic && !belowMinimum);
                    counter.classList.toggle('text-danger', hasValue && (invalidArabic || belowMinimum));
                    counter.classList.toggle('text-muted', !hasValue);
                }
            }

            function bindApplicationWorkSummaryValidation(field) {
                if (field.dataset.workSummaryBound === '1') {
                    return;
                }

                field.dataset.workSummaryBound = '1';
                validateApplicationWorkSummary(field);

                field.addEventListener('beforeinput', function (event) {
                    if (event.inputType !== 'insertText' || !event.data || !hasInvalidApplicationArabicCharacters(event.data)) {
                        return;
                    }

                    event.preventDefault();
                });

                field.addEventListener('paste', function (event) {
                    event.preventDefault();

                    const text = event.clipboardData ? event.clipboardData.getData('text') : '';
                    insertApplicationWorkSummaryText(field, text);
                });

                field.addEventListener('input', function () {
                    sanitizeApplicationWorkSummaryInput(field);
                    validateApplicationWorkSummary(field);
                });
            }

            function removeApplicationAnnexRow(button, selector) {
                const table = document.querySelector(selector + ' tbody');

                if (!table || !button.closest('tr')) {
                    return;
                }

                button.closest('tr').remove();
                renumberApplicationAnnexRows(selector);
                refreshApplicationCastCrewPassportColumn(document.querySelector(selector));
                refreshSpecialLocationRequirementSelects();
                refreshEquipmentTravelerSelects();
                refreshFilmingLocationDateConstraints(document);
                updateEquipmentTotals();
            }

            function applicationCastCrewNameInputsHtml(index) {
                return '<input type="hidden" name="cast_crew[' + index + '][name]" data-cast-crew-name-output>'
                    + '<div class="row g-2 cast-crew-jordanian-name d-none" data-cast-crew-jordanian-name>'
                    + '<div class="col-md-6 col-xl-3"><input type="text" class="form-control" name="cast_crew[' + index + '][first_name]" placeholder="' + applicationEscapeHtml(applicationCastCrewLabels.firstName) + '" data-cast-crew-name-part disabled></div>'
                    + '<div class="col-md-6 col-xl-3"><input type="text" class="form-control" name="cast_crew[' + index + '][second_name]" placeholder="' + applicationEscapeHtml(applicationCastCrewLabels.secondName) + '" data-cast-crew-name-part disabled></div>'
                    + '<div class="col-md-6 col-xl-3"><input type="text" class="form-control" name="cast_crew[' + index + '][third_name]" placeholder="' + applicationEscapeHtml(applicationCastCrewLabels.thirdName) + '" data-cast-crew-name-part disabled></div>'
                    + '<div class="col-md-6 col-xl-3"><input type="text" class="form-control" name="cast_crew[' + index + '][family_name]" placeholder="' + applicationEscapeHtml(applicationCastCrewLabels.familyName) + '" data-cast-crew-name-part disabled></div>'
                    + '</div>'
                    + '<input type="text" class="form-control" data-cast-crew-full-name-input required>';
            }

            function applicationCastCrewIdentityInputHtml(index) {
                return '<input type="text" class="form-control" name="cast_crew[' + index + '][identity_number]" placeholder="' + applicationEscapeHtml(applicationCastCrewLabels.passportNumber) + '" data-cast-crew-identity>'
                    + '<div class="invalid-feedback" data-cast-crew-identity-feedback>' + applicationEscapeHtml(applicationCastCrewLabels.nationalIdDigits) + '</div>';
            }

            function applicationCastCrewPassportImageInputHtml(index) {
                return '<div class="d-none" data-cast-crew-passport-image>'
                    + '<input type="file" class="form-control" name="cast_crew[' + index + '][passport_image]" accept="image/jpeg,image/png,.jpg,.jpeg,.png" disabled>'
                    + '</div>';
            }

            function applicationIsCastCrewJordanian(row) {
                const nationality = row?.querySelector('[data-cast-crew-nationality]');

                return String(nationality?.value || '').toLowerCase() === 'jordanian';
            }

            function refreshApplicationCastCrewPassportColumn(table) {
                if (!table) {
                    return;
                }

                const hasForeignMember = Array.from(table.querySelectorAll('tbody tr')).some(function (row) {
                    const nationality = row.querySelector('[data-cast-crew-nationality]');
                    const value = String(nationality?.value || '').trim();

                    return value !== '' && !applicationIsCastCrewJordanian(row);
                });

                table.querySelector('[data-cast-crew-passport-heading]')?.classList.toggle('d-none', !hasForeignMember);
                table.querySelectorAll('[data-cast-crew-passport-cell]').forEach(function (cell) {
                    cell.classList.toggle('d-none', !hasForeignMember);
                });
            }

            function updateApplicationCastCrewMode(row) {
                if (!row) {
                    return;
                }

                const nationality = row.querySelector('[data-cast-crew-nationality]');
                const jordanianName = row.querySelector('[data-cast-crew-jordanian-name]');
                const fullName = row.querySelector('[data-cast-crew-full-name-input]');
                const nameOutput = row.querySelector('[data-cast-crew-name-output]');
                const identity = row.querySelector('[data-cast-crew-identity]');
                const passportImage = row.querySelector('[data-cast-crew-passport-image]');
                const isJordanian = applicationIsCastCrewJordanian(row);
                const hasNationality = String(nationality?.value || '').trim() !== '';

                if (jordanianName) {
                    jordanianName.classList.toggle('d-none', !isJordanian);
                    jordanianName.querySelectorAll('[data-cast-crew-name-part]').forEach(function (input) {
                        input.disabled = !isJordanian;
                        input.required = isJordanian;
                    });
                }

                if (fullName) {
                    fullName.classList.toggle('d-none', isJordanian);
                    fullName.required = hasNationality && !isJordanian;
                }

                if (nameOutput) {
                    nameOutput.value = isJordanian
                        ? Array.from(row.querySelectorAll('[data-cast-crew-name-part]')).map(function (input) {
                            return String(input.value || '').trim();
                        }).filter(Boolean).join(' ')
                        : String(fullName?.value || '').trim();
                }

                if (identity) {
                    identity.placeholder = isJordanian ? applicationCastCrewLabels.nationalId : applicationCastCrewLabels.passportNumber;
                    identity.inputMode = isJordanian ? 'numeric' : 'text';

                    if (isJordanian) {
                        identity.value = String(identity.value || '').replace(/\D/g, '').slice(0, 10);
                        identity.required = true;
                        identity.setAttribute('minlength', '10');
                        identity.setAttribute('maxlength', '10');
                        identity.setAttribute('pattern', '\\d{10}');
                    } else {
                        identity.required = hasNationality;
                        identity.removeAttribute('minlength');
                        identity.removeAttribute('maxlength');
                        identity.removeAttribute('pattern');
                        identity.setCustomValidity('');
                        identity.classList.remove('is-invalid');
                    }
                }

                if (passportImage) {
                    const shouldShowPassport = hasNationality && !isJordanian;
                    passportImage.classList.toggle('d-none', !shouldShowPassport);
                    passportImage.querySelectorAll('input').forEach(function (input) {
                        input.disabled = !shouldShowPassport;
                    });
                }

                refreshApplicationCastCrewPassportColumn(row.closest('table'));
            }

            function refreshApplicationCastCrewModes(root) {
                (root || document).querySelectorAll('[data-cast-crew-nationality]').forEach(function (field) {
                    updateApplicationCastCrewMode(field.closest('tr'));
                });
            }

            function supportScheduleRowHtml(fieldName, index, deleteCell) {
                return '<td class="row-number"></td>'
                    + '<td><input type="text" class="form-control" name="' + fieldName + '[' + index + '][day]"></td>'
                    + '<td><input type="date" class="form-control" name="' + fieldName + '[' + index + '][date]"></td>'
                    + '<td><input type="time" class="form-control" name="' + fieldName + '[' + index + '][time_from]"></td>'
                    + '<td><input type="time" class="form-control" name="' + fieldName + '[' + index + '][time_to]"></td>'
                    + '<td><input type="text" class="form-control" name="' + fieldName + '[' + index + '][location]"></td>'
                    + '<td><input type="text" class="form-control" name="' + fieldName + '[' + index + '][requirement]"></td>'
                    + '<td><textarea class="form-control" name="' + fieldName + '[' + index + '][notes]" rows="2"></textarea></td>'
                    + deleteCell;
            }

            function addApplicationAnnexRow(tableId, fieldName) {
                const table = document.querySelector('#' + tableId + ' tbody');

                if (!table) {
                    return;
                }

                const index = table.querySelectorAll('tr').length;
                const row = document.createElement('tr');
                const deleteCell = '<td><button type="button" class="btn btn-sm btn-icon btn-danger-subtle rounded" onclick="removeApplicationAnnexRow(this, \'#' + tableId + '\')"><i class="ph-fill ph ph-trash-simple fs-6"></i></button></td>';

                if (fieldName === 'cast_crew') {
	                    row.innerHTML = '<td class="row-number"></td>'
	                        + '<td><select class="form-select" name="cast_crew[' + index + '][nationality]" data-cast-crew-nationality required>' + applicationNationalityOptionsHtml + '</select></td>'
	                        + '<td class="cast-crew-name-cell">' + applicationCastCrewNameInputsHtml(index) + '</td>'
	                        + '<td><input type="text" class="form-control" name="cast_crew[' + index + '][role]" required></td>'
	                        + '<td><select class="form-select" name="cast_crew[' + index + '][gender]" required>' + applicationGenderOptionsHtml + '</select></td>'
	                        + '<td><input type="date" class="form-control" name="cast_crew[' + index + '][birth_date]" max="' + applicationCrewBirthDateMax + '" required></td>'
	                        + '<td>' + applicationCastCrewIdentityInputHtml(index) + '</td>'
	                        + '<td class="d-none" data-cast-crew-passport-cell>' + applicationCastCrewPassportImageInputHtml(index) + '</td>'
                        + deleteCell;
	                } else if (fieldName === 'filming_locations') {
	                    row.innerHTML = filmingLocationCardHtml(tableId, index);
                } else if (fieldName === 'imported_equipment') {
                    const rowKey = 'new_' + Date.now() + '_' + index;
                    row.innerHTML = '<td class="row-number"></td>'
                        + '<td><input type="hidden" name="imported_equipment[' + rowKey + '][transport_group]" value="shipping"><input type="text" class="form-control" name="imported_equipment[' + rowKey + '][shipping_company_name]"></td>'
                        + '<td><input type="text" class="form-control" name="imported_equipment[' + rowKey + '][invoice_number]"></td>'
                        + '<td><input type="text" class="form-control" name="imported_equipment[' + rowKey + '][bill_of_lading_number]"></td>'
                        + '<td><input type="date" class="form-control" name="imported_equipment[' + rowKey + '][arrival_date]"></td>'
                        + '<td><input type="date" class="form-control" name="imported_equipment[' + rowKey + '][departure_date]"></td>'
                        + '<td><select class="form-select" name="imported_equipment[' + rowKey + '][customs_center]">' + applicationLookupOptionsHtml(applicationEquipmentEntryPointOptions, '') + '</select></td>'
                        + '<td><input type="file" class="form-control" name="imported_equipment[' + rowKey + '][attachment]" accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.jpg,.jpeg,.png"></td>'
                        + deleteCell;
                } else if (fieldName === 'equipment_flights') {
                    row.innerHTML = '<td class="row-number"></td>'
                        + '<td><select class="form-select" name="equipment_flights[' + index + '][flight_type]"><option value="">{{ __('app.admin.select_placeholder') }}</option><option value="arrival">{{ __('app.applications.flight_types.arrival') }}</option><option value="departure">{{ __('app.applications.flight_types.departure') }}</option></select></td>'
                        + '<td><input type="text" class="form-control" name="equipment_flights[' + index + '][flight_number]"></td>'
                        + '<td><input type="date" class="form-control" name="equipment_flights[' + index + '][flight_date]"></td>'
                        + '<td><input type="time" class="form-control" name="equipment_flights[' + index + '][flight_time]"></td>'
                        + '<td><input type="text" class="form-control" name="equipment_flights[' + index + '][departure_city]"></td>'
                        + '<td><input type="text" class="form-control" name="equipment_flights[' + index + '][arrival_city]"></td>'
                        + deleteCell;
                } else if (fieldName === 'equipment_travelers') {
                    row.innerHTML = '<td class="row-number"></td>'
                        + '<td><input type="text" class="form-control" name="equipment_travelers[' + index + '][traveler_name]"></td>'
                        + '<td><input type="date" class="form-control" name="equipment_travelers[' + index + '][arrival_date]"></td>'
                        + '<td><input type="text" class="form-control" name="equipment_travelers[' + index + '][arrival_flight_number]"></td>'
                        + '<td><input type="date" class="form-control" name="equipment_travelers[' + index + '][departure_date]"></td>'
                        + '<td><input type="text" class="form-control" name="equipment_travelers[' + index + '][departure_flight_number]"></td>'
                        + '<td><input type="file" class="form-control" name="equipment_travelers[' + index + '][passport_image]" accept="image/jpeg,image/png,.jpg,.jpeg,.png"></td>'
                        + deleteCell;
                } else if (fieldName === 'imported_equipment_shipping' || fieldName === 'imported_equipment_traveler') {
                    const rowKey = (fieldName === 'imported_equipment_traveler' ? 'traveler_' : 'shipping_') + index;
                    const group = fieldName === 'imported_equipment_traveler' ? 'traveler' : 'shipping';
                    const referenceField = group === 'traveler' ? 'traveler_name' : 'flight_reference';
                    const referenceControl = group === 'traveler'
                        ? '<select class="form-select" name="imported_equipment[' + rowKey + '][' + referenceField + ']" data-equipment-traveler-select>' + equipmentTravelerOptionsHtml('', '') + '</select>'
                        : '<input type="text" class="form-control" name="imported_equipment[' + rowKey + '][' + referenceField + ']">';

                    row.innerHTML = '<td class="row-number"></td>'
                        + '<td><input type="hidden" name="imported_equipment[' + rowKey + '][transport_group]" value="' + group + '"><input type="text" class="form-control" name="imported_equipment[' + rowKey + '][item]"></td>'
                        + '<td><input type="text" class="form-control" name="imported_equipment[' + rowKey + '][serial_number]"></td>'
                        + '<td>' + referenceControl + '</td>'
                        + '<td><input type="number" min="0" class="form-control" name="imported_equipment[' + rowKey + '][quantity]" data-equipment-quantity></td>'
                        + '<td><input type="number" step="0.01" min="0" class="form-control" name="imported_equipment[' + rowKey + '][unit_value]" data-equipment-unit-value></td>'
                        + '<td><input type="number" step="0.01" min="0" class="form-control" name="imported_equipment[' + rowKey + '][total_value]" data-equipment-row-total readonly></td>'
                        + '<td><select class="form-select" name="imported_equipment[' + rowKey + '][classification]">' + applicationLookupOptionsHtml(applicationEquipmentCategoryOptions, '') + '</select></td>'
                        + '<td><select class="form-select" name="imported_equipment[' + rowKey + '][entry_point]">' + applicationLookupOptionsHtml(applicationEquipmentEntryPointOptions, '') + '</select></td>'
                        + deleteCell;
                } else if (fieldName === 'public_security_support' || fieldName === 'military_support') {
                    row.innerHTML = supportScheduleRowHtml(fieldName, index, deleteCell);
                } else if (fieldName === 'airport_people') {
                    row.innerHTML = '<td class="row-number"></td>'
                        + '<td><input type="text" class="form-control" name="airport_people[' + index + '][full_name]"></td>'
                        + '<td><select class="form-select" name="airport_people[' + index + '][nationality]">' + applicationNationalityOptionsHtml + '</select></td>'
                        + '<td><input type="text" class="form-control" name="airport_people[' + index + '][mother_name]"></td>'
                        + '<td><input type="text" class="form-control" name="airport_people[' + index + '][identity_number]"></td>'
                        + '<td><input type="text" class="form-control" name="airport_people[' + index + '][profession]"></td>'
                        + '<td><input type="text" class="form-control" name="airport_people[' + index + '][address_phone]"></td>'
                        + '<td><input type="text" class="form-control" name="airport_people[' + index + '][entry_reason]"></td>'
                        + '<td><input type="text" class="form-control" name="airport_people[' + index + '][target_area]"></td>'
                        + deleteCell;
                } else if (fieldName === 'governmental_scenes') {
                    row.innerHTML = '<td class="row-number"></td>'
                        + '<td><input type="text" class="form-control" name="governmental_scenes[' + index + '][site_name]"></td>'
                        + '<td><input type="text" class="form-control" name="governmental_scenes[' + index + '][authority]"></td>'
                        + '<td><input type="text" class="form-control" name="governmental_scenes[' + index + '][scene_description]"></td>'
                        + '<td><input type="date" class="form-control" name="governmental_scenes[' + index + '][filming_date]"></td>'
                        + deleteCell;
                }

                table.appendChild(row);
                renumberApplicationAnnexRows('#' + tableId);
                row.querySelectorAll('.select2-basic-multiple, .select2-basic-single').forEach(refreshSelect2Control);
                refreshApplicationLocationTypeSelect(row);
                refreshSpecialLocationRequirementSelects();
                refreshEquipmentTravelerSelects();
                refreshFilmingLocationDateConstraints(row);
                refreshApplicationCastCrewModes(row);
                updateEquipmentTotals();
            }

            document.addEventListener('change', function (event) {
                if (event.target.matches('[name="international_producer_name"]')) {
                    refreshProductionTermsForeignApplicant();
                }

                if (event.target.matches('[data-location-governorate]')) {
                    refreshApplicationLocationTypeSelect(event.target.closest('tr'));
                }

                if (event.target.matches('[data-location-type-select]')) {
                    event.target.dataset.selectedType = event.target.value;
                    refreshApplicationLocationApprovalNote(event.target.closest('tr'));
                }

                if (event.target.matches('input[name^="filming_locations"][name$="[location_name]"]')) {
                    refreshSpecialLocationRequirementSelects();
                }

                if (event.target.matches('[data-location-start-date], [data-location-end-date], [data-location-support-date]')) {
                    updateFilmingLocationDateConstraints(event.target.closest('.application-location-card'));
                }

                if (event.target.matches('[data-location-support-requirement-select], [data-location-support-notes]')) {
                    updateFilmingLocationSupportRequirementNotes(event.target.closest('[data-location-support-requirement-row]'));
                }

                if (event.target.matches('input[name^="equipment_travelers"][name$="[traveler_name]"]')) {
                    refreshEquipmentTravelerSelects();
                }

                if (event.target.matches('[data-cast-crew-nationality]')) {
                    updateApplicationCastCrewMode(event.target.closest('tr'));
                }
            });

            document.addEventListener('input', function (event) {
                if (event.target.matches('[name="international_producer_name"]')) {
                    refreshProductionTermsForeignApplicant();
                }

                if (event.target.matches('input[name^="filming_locations"][name$="[location_name]"]')) {
                    refreshSpecialLocationRequirementSelects();
                }

                if (event.target.matches('input[name^="equipment_travelers"][name$="[traveler_name]"]')) {
                    refreshEquipmentTravelerSelects();
                }

                if (event.target.matches('[data-location-support-notes]')) {
                    updateFilmingLocationSupportRequirementNotes(event.target.closest('[data-location-support-requirement-row]'));
                }

                if (event.target.matches('[data-cast-crew-name-part], [data-cast-crew-full-name-input], [data-cast-crew-identity]')) {
                    updateApplicationCastCrewMode(event.target.closest('tr'));
                }
            });

            if (window.jQuery) {
                window.jQuery(document)
                    .off(
                        'change.applicationLocationSupportNotes select2:select.applicationLocationSupportNotes select2:unselect.applicationLocationSupportNotes select2:clear.applicationLocationSupportNotes',
                        '[data-location-support-requirement-select]'
                    )
                    .on(
                        'change.applicationLocationSupportNotes select2:select.applicationLocationSupportNotes select2:unselect.applicationLocationSupportNotes select2:clear.applicationLocationSupportNotes',
                        '[data-location-support-requirement-select]',
                        function () {
                            updateFilmingLocationSupportRequirementNotes(this.closest('[data-location-support-requirement-row]'));
                        }
                    );
            }

            refreshApplicationLocationTypeSelects(document);
            refreshSpecialLocationRequirementSelects();
            refreshEquipmentTravelerSelects();
            refreshFilmingLocationDateConstraints(document);
            refreshFilmingLocationSupportRequirementNotes(document);
            refreshApplicationCastCrewModes(document);
            refreshProductionTermsForeignApplicant();

            [
                '#castCrewRequestTable',
                '#filmingLocationsRequestTable',
                '#importedEquipmentShipmentTable',
                '#equipmentFlightsTable',
                '#importedEquipmentShippingTable',
                '#equipmentTravelersTable',
                '#importedEquipmentTravelerTable',
                '#airportPeopleTable',
                '#governmentalScenesRequestTable',
            ].forEach(renumberApplicationAnnexRows);

            const annexForm = document.getElementById('applicant-annex-form');

            if (annexForm) {
                annexForm.addEventListener('input', updateEquipmentTotals);
                annexForm.addEventListener('change', updateEquipmentTotals);
            }

            document.querySelectorAll('[data-work-summary-input]').forEach(bindApplicationWorkSummaryValidation);
            updateEquipmentTotals();

            window.addApplicationAnnexRow = addApplicationAnnexRow;
            window.removeApplicationAnnexRow = removeApplicationAnnexRow;
        </script>
    @endpush
@endonce
