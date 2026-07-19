@once
    @push('styles')
        <style>
            .location-support-editor {
                border-top: 1px solid var(--bs-border-color);
                padding-top: 1.5rem;
            }

            .location-support-editor__row {
                background: #f8f9fa;
                border: 1px solid var(--bs-border-color);
                border-radius: 6px;
                padding: 1rem;
            }

            .location-support-editor__assignment {
                background: #fff;
                border: 1px solid var(--bs-border-color);
                border-radius: 6px;
                padding: .85rem;
            }

            .location-support-editor .btn-group .btn {
                min-height: 42px;
            }

            @media (max-width: 767.98px) {
                .location-support-editor .btn-group {
                    display: grid;
                    width: 100%;
                }

                .location-support-editor .btn-group .btn {
                    border-radius: 4px !important;
                }
            }
        </style>
    @endpush

    @php
        $locationSupportRequirementScriptOptions = collect($locationRequirementOptions ?? [])
            ->map(fn ($code) => [
                'code' => $code,
                'label' => $locationRequirementLabels[$code] ?? __('app.applications.special_location_requirements.'.$code),
                'authorityCodes' => array_values($locationRequirementAuthorityCodes[$code] ?? []),
                'notesPrompt' => $locationRequirementPrompts[$code] ?? null,
            ])
            ->values()
            ->all();
    @endphp

    @push('scripts')
        <script>
            (function () {
                const requirements = @json($locationSupportRequirementScriptOptions);
                const messages = {
                    placeholder: @json(__('app.admin.select_placeholder')),
                    unnamedLocation: @json(__('app.applications.location_support_unnamed_location', ['number' => '__NUMBER__'])),
                    range: @json(__('app.applications.location_support_location_range', ['start' => '__START__', 'end' => '__END__'])),
                    noLocations: @json(__('app.applications.location_support_no_locations')),
                    notes: @json(__('app.applications.location_support_notes_prompt', ['requirement' => '__REQUIREMENT__'])),
                    dateRange: @json(__('app.applications.location_support_date_range')),
                    authority: @json(__('app.applications.annex_fields.authority_name')),
                    requirement: @json(__('app.applications.annex_fields.requirement')),
                    scheduleMode: @json(__('app.applications.location_support_schedule_mode')),
                    sharedMode: @json(__('app.applications.location_support_schedule_shared')),
                    perLocationMode: @json(__('app.applications.location_support_schedule_per_location')),
                    locations: @json(__('app.applications.location_support_locations')),
                    date: @json(__('app.applications.annex_fields.date')),
                    timeFrom: @json(__('app.applications.annex_fields.time_from')),
                    timeTo: @json(__('app.applications.annex_fields.time_to')),
                    notesLabel: @json(__('app.applications.annex_fields.notes')),
                    deleteLabel: @json(__('app.delete')),
                };

                function escapeHtml(value) {
                    return String(value ?? '')
                        .replaceAll('&', '&amp;')
                        .replaceAll('<', '&lt;')
                        .replaceAll('>', '&gt;')
                        .replaceAll('"', '&quot;')
                        .replaceAll("'", '&#039;');
                }

                function optionsHtml(options) {
                    return '<option value="">' + escapeHtml(messages.placeholder) + '</option>'
                        + options.map(function (option) {
                            return '<option value="' + escapeHtml(option.code) + '" data-authority-codes="' + escapeHtml((option.authorityCodes || []).join(',')) + '" data-notes-prompt="' + escapeHtml(option.notesPrompt || '') + '">' + escapeHtml(option.label) + '</option>';
                        }).join('');
                }

                function syncRequirementOptions(row, preserveSelection = false) {
                    const authority = row.querySelector('[data-location-support-authority]')?.value || '';
                    const requirement = row.querySelector('[data-location-support-requirement]');

                    if (!requirement) return;

                    const selected = preserveSelection ? requirement.value : '';
                    const allowed = authority
                        ? requirements.filter(function (option) {
                            return (option.authorityCodes || []).includes(authority);
                        })
                        : [];

                    requirement.innerHTML = optionsHtml(allowed);

                    if (selected && allowed.some(function (option) { return option.code === selected; })) {
                        requirement.value = selected;
                    }

                    if (window.jQuery?.fn?.select2 && window.jQuery(requirement).data('select2')) {
                        window.jQuery(requirement).trigger('change.select2');
                    }

                    updateNotes(row);
                }

                function editorLocations(editor) {
                    const table = document.getElementById(editor.dataset.locationTable || '');

                    if (!table) {
                        return [];
                    }

                    return Array.from(table.querySelectorAll('[data-filming-location-card]')).map(function (card, index) {
                        const key = card.querySelector('[data-location-key]');
                        const name = card.querySelector('[data-location-name]');
                        const start = card.querySelector('[data-location-start-date]');
                        const end = card.querySelector('[data-location-end-date]');
                        const fallbackKey = 'location_' + (index + 1);

                        if (key && !String(key.value || '').trim()) {
                            key.value = fallbackKey;
                        }

                        return {
                            key: String(key?.value || fallbackKey),
                            label: String(name?.value || '').trim() || messages.unnamedLocation.replace('__NUMBER__', String(index + 1)),
                            start: String(start?.value || ''),
                            end: String(end?.value || ''),
                        };
                    });
                }

                function assignmentState(row) {
                    const state = new Map();

                    row.querySelectorAll('[data-location-support-assignment]').forEach(function (assignment) {
                        const key = String(assignment.dataset.locationKey || '');
                        state.set(key, {
                            selected: Boolean(assignment.querySelector('[data-location-support-selected]')?.checked),
                            date: assignment.querySelector('[data-assignment-date]')?.value || '',
                            timeFrom: assignment.querySelector('input[name$="[time_from]"]')?.value || '',
                            timeTo: assignment.querySelector('input[name$="[time_to]"]')?.value || '',
                        });
                    });

                    return state;
                }

                function assignmentHtml(requirementIndex, assignmentIndex, location, state) {
                    const id = 'location_support_assignment_' + requirementIndex + '_' + assignmentIndex + '_' + location.key.replace(/[^A-Za-z0-9_-]/g, '_');
                    const selected = state?.selected ? ' checked' : '';
                    const range = messages.range
                        .replace('__START__', location.start || '-')
                        .replace('__END__', location.end || '-');

                    return '<div class="location-support-editor__assignment" data-location-support-assignment data-location-key="' + escapeHtml(location.key) + '">'
                        + '<input type="hidden" name="location_support_requirements[' + requirementIndex + '][assignments][' + assignmentIndex + '][location_key]" value="' + escapeHtml(location.key) + '" data-assignment-location-key>'
                        + '<input type="hidden" name="location_support_requirements[' + requirementIndex + '][assignments][' + assignmentIndex + '][selected]" value="0">'
                        + '<div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="location_support_requirements[' + requirementIndex + '][assignments][' + assignmentIndex + '][selected]" value="1" id="' + escapeHtml(id) + '"' + selected + ' data-location-support-selected>'
                        + '<label class="form-check-label fw-semibold" for="' + escapeHtml(id) + '" data-assignment-label>' + escapeHtml(location.label) + '</label></div>'
                        + '<div class="small text-muted mb-2" data-assignment-range>' + escapeHtml(range) + '</div>'
                        + '<div class="row g-2" data-location-support-location-schedule>'
                        + '<div class="col-md-4"><input type="date" class="form-control" name="location_support_requirements[' + requirementIndex + '][assignments][' + assignmentIndex + '][date]" value="' + escapeHtml(state?.date || '') + '"' + (location.start ? ' min="' + escapeHtml(location.start) + '"' : '') + (location.end ? ' max="' + escapeHtml(location.end) + '"' : '') + ' data-assignment-date></div>'
                        + '<div class="col-md-4"><input type="time" class="form-control" name="location_support_requirements[' + requirementIndex + '][assignments][' + assignmentIndex + '][time_from]" value="' + escapeHtml(state?.timeFrom || '') + '"></div>'
                        + '<div class="col-md-4"><input type="time" class="form-control" name="location_support_requirements[' + requirementIndex + '][assignments][' + assignmentIndex + '][time_to]" value="' + escapeHtml(state?.timeTo || '') + '"></div>'
                        + '</div></div>';
                }

                function cleanupClonedSelect(select) {
                    select.classList.remove('select2-hidden-accessible');
                    select.removeAttribute('data-select2-id');
                    select.removeAttribute('tabindex');
                    select.removeAttribute('aria-hidden');
                    select.removeAttribute('style');
                    Array.from(select.options).forEach(function (option) {
                        option.removeAttribute('data-select2-id');
                    });
                    select.selectedIndex = 0;
                }

                function initializeSelects(root) {
                    if (!window.jQuery?.fn?.select2) {
                        return;
                    }

                    window.jQuery(root).find('select.select2-basic-single').each(function () {
                        const select = window.jQuery(this);
                        const options = { width: '100%' };
                        const offcanvas = select.closest('.offcanvas');

                        if (select.data('select2')) {
                            select.select2('destroy');
                        }

                        if (offcanvas.length) {
                            options.dropdownParent = offcanvas;
                        }

                        select.select2(options);
                    });
                }

                function reindexRows(editor) {
                    editor.querySelectorAll('[data-location-support-row]').forEach(function (row, index) {
                        row.dataset.requirementIndex = String(index);
                        const number = row.querySelector('[data-location-support-number]');
                        if (number) number.textContent = '#' + (index + 1);

                        row.querySelectorAll('[name^="location_support_requirements["]').forEach(function (field) {
                            field.name = field.name.replace(/^location_support_requirements\[[^\]]+\]/, 'location_support_requirements[' + index + ']');
                        });

                        row.querySelectorAll('[data-location-support-mode]').forEach(function (radio) {
                            const mode = radio.value;
                            const id = 'location_support_mode_' + mode + '_' + index + '_' + (editor.dataset.locationTable || 'locations');
                            radio.id = id;
                            row.querySelector('label[for*="location_support_mode_' + mode + '"]')?.setAttribute('for', id);
                        });
                    });
                }

                function syncAssignments(editor) {
                    const locations = editorLocations(editor);

                    editor.querySelectorAll('[data-location-support-row]').forEach(function (row, requirementIndex) {
                        const state = assignmentState(row);
                        const container = row.querySelector('[data-location-support-assignments]');

                        if (!container) return;

                        container.innerHTML = locations.length
                            ? locations.map(function (location, assignmentIndex) {
                                return assignmentHtml(requirementIndex, assignmentIndex, location, state.get(location.key));
                            }).join('')
                            : '<div class="alert alert-light border mb-0" data-location-support-empty>' + escapeHtml(messages.noLocations) + '</div>';

                        applyRowState(row);
                    });
                }

                function updateSharedDateLimits(row) {
                    const sharedDate = row.querySelector('[data-location-support-shared-date]');
                    if (!sharedDate) return;

                    const selected = Array.from(row.querySelectorAll('[data-location-support-assignment]'))
                        .filter(function (assignment) {
                            return assignment.querySelector('[data-location-support-selected]')?.checked;
                        });
                    const starts = selected.map(function (assignment) {
                        return assignment.querySelector('[data-assignment-date]')?.min || '';
                    }).filter(Boolean).sort();
                    const ends = selected.map(function (assignment) {
                        return assignment.querySelector('[data-assignment-date]')?.max || '';
                    }).filter(Boolean).sort();
                    const minimum = starts.length ? starts[starts.length - 1] : '';
                    const maximum = ends.length ? ends[0] : '';

                    sharedDate.min = minimum;
                    sharedDate.max = maximum;
                    sharedDate.setCustomValidity('');

                    if ((minimum && sharedDate.value && sharedDate.value < minimum)
                        || (maximum && sharedDate.value && sharedDate.value > maximum)
                        || (minimum && maximum && minimum > maximum)) {
                        sharedDate.setCustomValidity(messages.dateRange);
                    }
                }

                function updateNotes(row) {
                    const requirement = row.querySelector('[data-location-support-requirement]');
                    const notes = row.querySelector('[data-location-support-notes]');
                    const marker = row.querySelector('[data-location-support-notes-required]');
                    const helper = row.querySelector('[data-location-support-notes-help]');
                    const label = requirement?.selectedOptions?.[0]?.textContent?.trim() || '';
                    const hasRequirement = Boolean(requirement?.value);
                    const configuredPrompt = requirement?.selectedOptions?.[0]?.dataset?.notesPrompt?.trim() || '';
                    const prompt = hasRequirement
                        ? (configuredPrompt || messages.notes.replace('__REQUIREMENT__', label))
                        : '';

                    if (notes) {
                        notes.required = hasRequirement;
                        notes.placeholder = prompt;
                    }
                    marker?.classList.toggle('d-none', !hasRequirement);
                    if (helper) {
                        helper.textContent = prompt;
                        helper.classList.toggle('d-none', !hasRequirement);
                    }
                }

                function applyRowState(row) {
                    const mode = row.querySelector('[data-location-support-mode]:checked')?.value || 'shared';
                    const shared = row.querySelector('[data-location-support-shared-schedule]');
                    const sharedFields = shared?.querySelectorAll('input') || [];

                    shared?.classList.toggle('d-none', mode !== 'shared');
                    sharedFields.forEach(function (field) {
                        field.disabled = mode !== 'shared';
                    });

                    row.querySelectorAll('[data-location-support-assignment]').forEach(function (assignment) {
                        const selected = Boolean(assignment.querySelector('[data-location-support-selected]')?.checked);
                        const schedule = assignment.querySelector('[data-location-support-location-schedule]');
                        schedule?.classList.toggle('d-none', mode !== 'per_location');
                        schedule?.querySelectorAll('input').forEach(function (field) {
                            field.disabled = mode !== 'per_location' || !selected;
                        });
                    });

                    updateSharedDateLimits(row);
                    updateNotes(row);
                }

                function resetClone(row) {
                    row.querySelectorAll('.select2-container').forEach(function (node) { node.remove(); });
                    row.querySelectorAll('select').forEach(cleanupClonedSelect);
                    row.querySelectorAll('input, textarea').forEach(function (field) {
                        if (field.matches('[data-requirement-key]')) {
                            field.value = 'requirement_' + Date.now() + '_' + Math.random().toString(36).slice(2, 8);
                        } else if (field.matches('[data-location-support-mode]')) {
                            field.checked = field.value === 'shared';
                        } else if (field.type === 'checkbox') {
                            field.checked = false;
                        } else if (field.type !== 'hidden' || !field.name.endsWith('[selected]')) {
                            field.value = '';
                        }
                    });
                    const assignments = row.querySelector('[data-location-support-assignments]');
                    if (assignments) assignments.innerHTML = '';
                }

                function addRequirement(editor) {
                    const container = editor.querySelector('[data-location-support-rows]');
                    const source = container?.querySelector('[data-location-support-row]');
                    if (!container || !source) return;

                    const row = source.cloneNode(true);
                    resetClone(row);
                    container.appendChild(row);
                    reindexRows(editor);
                    syncAssignments(editor);
                    initializeSelects(row);
                    syncRequirementOptions(row);
                }

                function removeRequirement(editor, row) {
                    const rows = editor.querySelectorAll('[data-location-support-row]');

                    if (rows.length === 1) {
                        resetClone(row);
                    } else {
                        row.remove();
                    }

                    reindexRows(editor);
                    syncAssignments(editor);
                    initializeSelects(editor);
                }

                function refreshEditors() {
                    document.querySelectorAll('[data-location-support-editor]').forEach(function (editor) {
                        reindexRows(editor);
                        syncAssignments(editor);
                    });
                }

                function initializeEditor(editor) {
                    if (editor.dataset.initialized === '1') return;
                    editor.dataset.initialized = '1';

                    editor.addEventListener('click', function (event) {
                        const add = event.target.closest('[data-location-support-add]');
                        const remove = event.target.closest('[data-location-support-remove]');
                        if (add) addRequirement(editor);
                        if (remove) removeRequirement(editor, remove.closest('[data-location-support-row]'));
                    });

                    editor.addEventListener('change', function (event) {
                        const row = event.target.closest('[data-location-support-row]');
                        if (!row) return;

                        if (event.target.matches('[data-location-support-authority]')) {
                            syncRequirementOptions(row);
                        }

                        applyRowState(row);
                    });

                    const table = document.getElementById(editor.dataset.locationTable || '');
                    if (table) {
                        table.addEventListener('input', function (event) {
                            if (event.target.matches('[data-location-name], [data-location-start-date], [data-location-end-date]')) {
                                syncAssignments(editor);
                            }
                        });
                        new MutationObserver(function () { syncAssignments(editor); })
                            .observe(table.querySelector('tbody') || table, { childList: true });
                    }

                    reindexRows(editor);
                    syncAssignments(editor);
                    editor.querySelectorAll('[data-location-support-row]').forEach(applyRowState);
                }

                document.addEventListener('DOMContentLoaded', function () {
                    document.querySelectorAll('[data-location-support-editor]').forEach(initializeEditor);
                });

                if (window.jQuery) {
                    window.jQuery(document).on('select2:select select2:unselect select2:clear', '[data-location-support-authority]', function () {
                        const row = this.closest('[data-location-support-row]');
                        if (row) syncRequirementOptions(row);
                    });
                    window.jQuery(document).on('select2:select select2:unselect select2:clear', '[data-location-support-requirement]', function () {
                        const row = this.closest('[data-location-support-row]');
                        if (row) updateNotes(row);
                    });
                }

                window.refreshSharedLocationSupportRequirements = refreshEditors;
            })();
        </script>
    @endpush
@endonce
