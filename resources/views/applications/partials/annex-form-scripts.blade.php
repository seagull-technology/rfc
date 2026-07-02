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
	        @endphp
	        <script>
		            const applicationNationalityOptionsHtml = @js($applicationNationalityOptionsHtml);
		            const applicationGovernorateOptionsHtml = @js($applicationGovernorateOptionsHtml);
		            const applicationGenderOptionsHtml = @js($applicationGenderOptionsHtml);
                const applicationEquipmentCategoryOptions = @json($applicationEquipmentCategoryOptions);
                const applicationEquipmentShippingMethodOptions = @json($applicationEquipmentShippingMethodOptions);
                const applicationEquipmentEntryPointOptions = @json($applicationEquipmentEntryPointOptions);
                const applicationMilitaryLocationTypeOptions = @json($applicationMilitaryLocationTypeOptions);
	            const applicationLocationTypesByGovernorate = @json((array) data_get($locationLookupOptions ?? [], 'location_types_by_governorate', []));
	            const applicationLocationTypeLabels = @json((array) data_get($locationLookupOptions ?? [], 'location_type_labels', []));
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
	                        + '<td><input type="date" class="form-control" name="cast_crew[' + index + '][birth_date]"></td>'
	                        + '<td><input type="text" class="form-control" name="cast_crew[' + index + '][identity_number]"></td>'
                        + deleteCell;
	                } else if (fieldName === 'filming_locations') {
	                    row.innerHTML = '<td class="row-number"></td>'
	                        + '<td><select class="form-select" name="filming_locations[' + index + '][governorate]" data-location-governorate>' + applicationGovernorateOptionsHtml + '</select></td>'
	                        + '<td><select class="form-select" name="filming_locations[' + index + '][location_type]" data-location-type-select>' + applicationLocationTypeOptionsHtml('', '') + '</select></td>'
	                        + '<td><input type="text" class="form-control" name="filming_locations[' + index + '][location_name]"></td>'
	                        + '<td><textarea class="form-control" name="filming_locations[' + index + '][nature]" rows="2"></textarea></td>'
	                        + '<td><input type="text" class="form-control" name="filming_locations[' + index + '][address]"></td>'
	                        + '<td><input type="date" class="form-control" name="filming_locations[' + index + '][start_date]"></td>'
	                        + '<td><input type="date" class="form-control" name="filming_locations[' + index + '][end_date]"></td>'
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
                refreshApplicationLocationTypeSelect(row);
                refreshSpecialLocationRequirementSelects();
                refreshEquipmentTravelerSelects();
                updateEquipmentTotals();
            }

            document.addEventListener('change', function (event) {
                if (event.target.matches('[data-location-governorate]')) {
                    refreshApplicationLocationTypeSelect(event.target.closest('tr'));
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

            updateEquipmentTotals();

            window.addApplicationAnnexRow = addApplicationAnnexRow;
            window.removeApplicationAnnexRow = removeApplicationAnnexRow;
        </script>
    @endpush
@endonce
