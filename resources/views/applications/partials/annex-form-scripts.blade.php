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
                $applicationEquipmentShippingMethodOptions = collect(data_get($formLookupOptions ?? [], 'equipment_shipping_methods', []))
                    ->map(fn ($option): array => ['code' => $option->code, 'label' => $option->displayName()])
                    ->values();
                $applicationEquipmentEntryPointOptions = collect(data_get($formLookupOptions ?? [], 'equipment_entry_points', []))
                    ->map(fn ($option): array => ['code' => $option->code, 'label' => $option->displayName()])
                    ->values();
                $applicationMilitaryLocationTypeOptions = collect($militaryLocationTypeOptions ?? ['military_area', 'border_area'])
                    ->map(fn ($code): array => ['code' => $code, 'label' => ($militaryLocationTypeLabels[$code] ?? __('app.applications.military_location_types.'.$code))])
                    ->values();
                $applicationLocationTypeApprovalDays = (array) data_get($locationLookupOptions ?? [], 'location_type_approval_days', []);
                $maxCrewBirthDate = $maxCrewBirthDate ?? now()->subDay()->toDateString();
	        @endphp
	        <script>
		            const applicationNationalityOptionsHtml = @js($applicationNationalityOptionsHtml);
		            const applicationGovernorateOptionsHtml = @js($applicationGovernorateOptionsHtml);
		            const applicationGenderOptionsHtml = @js($applicationGenderOptionsHtml);
                const applicationCrewBirthDateMax = @js($maxCrewBirthDate);
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
                    approvalDaysNotice: @js(__('app.applications.location_type_approval_days_notice')),
                };
                const applicationWorkSummaryMessages = {
                    counter: @js(__('app.applications.work_summary_word_counter')),
                    minWords: @js(__('app.applications.work_summary_min_words_validation', ['min' => 500])),
                    arabicOnly: @js(__('app.applications.work_summary_arabic_only_validation')),
                };
                const applicationEquipmentCategoryOptions = @json($applicationEquipmentCategoryOptions);
                const applicationEquipmentShippingMethodOptions = @json($applicationEquipmentShippingMethodOptions);
                const applicationEquipmentEntryPointOptions = @json($applicationEquipmentEntryPointOptions);
                const applicationMilitaryLocationTypeOptions = @json($applicationMilitaryLocationTypeOptions);
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

            function refreshApplicationLocationApprovalNote(row) {
                if (!row) {
                    return;
                }

                const locationType = row.querySelector('[data-location-type-select]');
                const note = row.querySelector('[data-location-type-approval-note]');

                if (!locationType || !note) {
                    return;
                }

                const days = parseInt(applicationLocationTypeApprovalDays[locationType.value] || '0', 10);

                if (days > 0) {
                    note.textContent = applicationLocationCardLabels.approvalDaysNotice.replace(':days', String(days));
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
                    + '<div class="col-md-6 col-xl-3"><label class="form-label">' + applicationEscapeHtml(applicationLocationCardLabels.requirement) + '</label><select class="form-select select2-basic-single" name="filming_locations[' + locationIndex + '][support_requirements][' + supportIndex + '][requirement]"><option value="">' + applicationLocationTypePlaceholder + '</option>' + applicationSpecialLocationRequirementOptionsHtml([]) + '</select></div>'
                    + '<div class="col-md-6 col-xl-2"><label class="form-label">' + applicationEscapeHtml(applicationLocationCardLabels.date) + '</label><input type="date" class="form-control" name="filming_locations[' + locationIndex + '][support_requirements][' + supportIndex + '][date]"></div>'
                    + '<div class="col-md-6 col-xl-2"><label class="form-label">' + applicationEscapeHtml(applicationLocationCardLabels.timeFrom) + '</label><input type="time" class="form-control" name="filming_locations[' + locationIndex + '][support_requirements][' + supportIndex + '][time_from]"></div>'
                    + '<div class="col-md-6 col-xl-2"><label class="form-label">' + applicationEscapeHtml(applicationLocationCardLabels.timeTo) + '</label><input type="time" class="form-control" name="filming_locations[' + locationIndex + '][support_requirements][' + supportIndex + '][time_to]"></div>'
                    + '<div class="col-md-6 col-xl-12"><label class="form-label">' + applicationEscapeHtml(applicationLocationCardLabels.notes) + '</label><textarea class="form-control" name="filming_locations[' + locationIndex + '][support_requirements][' + supportIndex + '][notes]" rows="2"></textarea></div>'
                    + '</div>'
                    + '</div>';
            }

            function filmingLocationCardHtml(tableId, index) {
                const displayNumber = index + 1;

                return '<td>'
                    + '<div class="application-location-card">'
                    + '<div class="application-location-card__header">'
                    + '<h5 class="mb-0">' + applicationEscapeHtml(applicationLocationCardLabels.locationNumber.replace('__NUMBER__', '')) + '<span class="row-number">' + displayNumber + '</span></h5>'
                    + "<button type=\"button\" class=\"btn btn-sm btn-icon btn-danger-subtle rounded\" onclick=\"removeApplicationAnnexRow(this, '#" + tableId + "')\" aria-label=\"" + applicationEscapeHtml(applicationLocationCardLabels.deleteLabel) + "\"><i class=\"ph-fill ph ph-trash-simple fs-6\"></i></button>"
                    + '</div>'
                    + '<div class="application-location-card__section"><div class="row g-3">'
                    + '<div class="col-md-6 col-xl-3"><label class="form-label">' + applicationEscapeHtml(applicationLocationCardLabels.governorate) + '</label><select class="form-select" name="filming_locations[' + index + '][governorate]" data-location-governorate>' + applicationGovernorateOptionsHtml + '</select></div>'
                    + '<div class="col-md-6 col-xl-3"><label class="form-label">' + applicationEscapeHtml(applicationLocationCardLabels.locationType) + '</label><select class="form-select" name="filming_locations[' + index + '][location_type]" data-location-type-select>' + applicationLocationTypeOptionsHtml('', '') + '</select><div class="form-text text-warning fw-semibold d-none" data-location-type-approval-note></div></div>'
                    + '<div class="col-md-6 col-xl-3"><label class="form-label">' + applicationEscapeHtml(applicationLocationCardLabels.locationName) + '</label><input type="text" class="form-control" name="filming_locations[' + index + '][location_name]"></div>'
                    + '<div class="col-md-6"><label class="form-label">' + applicationEscapeHtml(applicationLocationCardLabels.locationAddress) + '</label><input type="text" class="form-control" name="filming_locations[' + index + '][address]"></div>'
                    + '<div class="col-md-6"><label class="form-label">' + applicationEscapeHtml(applicationLocationCardLabels.locationNature) + '</label><textarea class="form-control" name="filming_locations[' + index + '][nature]" rows="2"></textarea></div>'
                    + '<div class="col-md-6 col-xl-3"><label class="form-label">' + applicationEscapeHtml(applicationLocationCardLabels.startDate) + '</label><input type="date" class="form-control" name="filming_locations[' + index + '][start_date]"></div>'
                    + '<div class="col-md-6 col-xl-3"><label class="form-label">' + applicationEscapeHtml(applicationLocationCardLabels.endDate) + '</label><input type="date" class="form-control" name="filming_locations[' + index + '][end_date]"></div>'
                    + '<div class="col-xl-6"><label class="form-label">' + applicationEscapeHtml(applicationLocationCardLabels.notes) + '</label><input type="text" class="form-control" name="filming_locations[' + index + '][notes]"></div>'
                    + '</div></div>'
                    + '<div class="application-location-card__section application-location-card__section--requirements">'
                    + '<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"><h6 class="mb-0">' + applicationEscapeHtml(applicationLocationCardLabels.supportTitle) + '</h6><button type="button" class="btn btn-sm btn-success" onclick="addFilmingLocationSupportRequirement(this)"><i class="fa-solid fa-plus me-1"></i>' + applicationEscapeHtml(applicationLocationCardLabels.addLabel) + '</button></div>'
                    + '<div class="d-grid gap-3" data-location-support-requirements>' + filmingLocationSupportRequirementHtml(index, 0) + '</div>'
                    + '</div>'
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

                    const total = Array.from(table.querySelectorAll('input[name*="[total_value]"]'))
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
                refreshSpecialLocationRequirementSelects();
                refreshEquipmentTravelerSelects();
                updateEquipmentTotals();
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
	                        + '<td><input type="text" class="form-control" name="cast_crew[' + index + '][name]"></td>'
	                        + '<td><input type="text" class="form-control" name="cast_crew[' + index + '][role]"></td>'
	                        + '<td><select class="form-select" name="cast_crew[' + index + '][nationality]">' + applicationNationalityOptionsHtml + '</select></td>'
	                        + '<td><select class="form-select" name="cast_crew[' + index + '][gender]">' + applicationGenderOptionsHtml + '</select></td>'
	                        + '<td><input type="date" class="form-control" name="cast_crew[' + index + '][birth_date]" max="' + applicationCrewBirthDateMax + '"></td>'
	                        + '<td><input type="text" class="form-control" name="cast_crew[' + index + '][identity_number]"></td>'
                        + deleteCell;
	                } else if (fieldName === 'filming_locations') {
	                    row.innerHTML = filmingLocationCardHtml(tableId, index);
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
                        + '<td><input type="number" min="0" class="form-control" name="imported_equipment[' + rowKey + '][quantity]"></td>'
                        + '<td><input type="number" step="0.01" min="0" class="form-control" name="imported_equipment[' + rowKey + '][unit_value]"></td>'
                        + '<td><input type="number" step="0.01" min="0" class="form-control" name="imported_equipment[' + rowKey + '][total_value]"></td>'
                        + '<td><select class="form-select" name="imported_equipment[' + rowKey + '][classification]">' + applicationLookupOptionsHtml(applicationEquipmentCategoryOptions, '') + '</select></td>'
                        + '<td><select class="form-select" name="imported_equipment[' + rowKey + '][shipping_method]">' + applicationLookupOptionsHtml(applicationEquipmentShippingMethodOptions, '') + '</select></td>'
                        + '<td><select class="form-select" name="imported_equipment[' + rowKey + '][entry_point]">' + applicationLookupOptionsHtml(applicationEquipmentEntryPointOptions, '') + '</select></td>'
                        + deleteCell;
                } else if (fieldName === 'military_border_locations') {
                    row.innerHTML = '<td class="row-number"></td>'
                        + '<td><select class="form-select" name="military_border_locations[' + index + '][governorate]">' + applicationGovernorateOptionsHtml + '</select></td>'
                        + '<td><input type="text" class="form-control" name="military_border_locations[' + index + '][location_name]"></td>'
                        + '<td><input type="text" class="form-control" name="military_border_locations[' + index + '][address]"></td>'
                        + '<td><textarea class="form-control" name="military_border_locations[' + index + '][nature]" rows="2"></textarea></td>'
                        + '<td><select class="form-select" name="military_border_locations[' + index + '][location_type]">' + applicationLookupOptionsHtml(applicationMilitaryLocationTypeOptions, '') + '</select></td>'
                        + '<td><input type="date" class="form-control" name="military_border_locations[' + index + '][start_date]"></td>'
                        + '<td><input type="date" class="form-control" name="military_border_locations[' + index + '][end_date]"></td>'
                        + deleteCell;
                } else if (fieldName === 'military_border_equipment') {
                    row.innerHTML = '<td class="row-number"></td>'
                        + '<td><input type="text" class="form-control" name="military_border_equipment[' + index + '][item]"></td>'
                        + '<td><input type="text" class="form-control" name="military_border_equipment[' + index + '][serial_number]"></td>'
                        + '<td><input type="text" class="form-control" name="military_border_equipment[' + index + '][location_reference]"></td>'
                        + '<td><input type="number" min="0" class="form-control" name="military_border_equipment[' + index + '][quantity]"></td>'
                        + '<td><input type="number" step="0.01" min="0" class="form-control" name="military_border_equipment[' + index + '][unit_value]"></td>'
                        + '<td><input type="number" step="0.01" min="0" class="form-control" name="military_border_equipment[' + index + '][total_value]"></td>'
                        + '<td><select class="form-select" name="military_border_equipment[' + index + '][classification]">' + applicationLookupOptionsHtml(applicationEquipmentCategoryOptions, '') + '</select></td>'
                        + '<td><input type="text" class="form-control" name="military_border_equipment[' + index + '][entry_method]"></td>'
                        + '<td><select class="form-select" name="military_border_equipment[' + index + '][entry_point]">' + applicationLookupOptionsHtml(applicationEquipmentEntryPointOptions, '') + '</select></td>'
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
                updateEquipmentTotals();
            }

            document.addEventListener('change', function (event) {
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

                if (event.target.matches('input[name^="equipment_travelers"][name$="[traveler_name]"]')) {
                    refreshEquipmentTravelerSelects();
                }
            });

            document.addEventListener('input', function (event) {
                if (event.target.matches('input[name^="filming_locations"][name$="[location_name]"]')) {
                    refreshSpecialLocationRequirementSelects();
                }

                if (event.target.matches('input[name^="equipment_travelers"][name$="[traveler_name]"]')) {
                    refreshEquipmentTravelerSelects();
                }
            });

            refreshApplicationLocationTypeSelects(document);
            refreshSpecialLocationRequirementSelects();
            refreshEquipmentTravelerSelects();

            [
                '#castCrewRequestTable',
                '#filmingLocationsRequestTable',
                '#equipmentFlightsTable',
                '#importedEquipmentShippingTable',
                '#equipmentTravelersTable',
                '#importedEquipmentTravelerTable',
                '#militaryBorderLocationsTable',
                '#militaryBorderEquipmentRequestTable',
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
