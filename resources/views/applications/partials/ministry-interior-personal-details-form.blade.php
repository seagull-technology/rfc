@php
    $ministryInteriorPersonalDetailsReadOnly = (bool) ($ministryInteriorPersonalDetailsReadOnly ?? false);
    $ministryInteriorPersonalDetailsIdPrefix = $ministryInteriorPersonalDetailsIdPrefix ?? 'ministry_interior_personal_details';
    $ministryNationalityOptions = collect($ministryNationalityOptions ?? data_get($nationalityOptions ?? [], 'director', []));
    $submittedDetails = old('ministry_interior_personal_details', $ministryInteriorPersonalDetails ?? []);
    $ministryInteriorPersonalDetailsRows = \App\Support\MinistryInteriorPersonalDetails::rows($submittedDetails);

    if (! $ministryInteriorPersonalDetailsReadOnly && $ministryInteriorPersonalDetailsRows === []) {
        $ministryInteriorPersonalDetailsRows = [[]];
    }
@endphp

@once
    <style>
        .ministry-personal-details-form {
            color: #111827;
        }

        .ministry-personal-details-form__record {
            background: #fff;
            border: 1px solid #d8dde5;
            border-radius: 6px;
            padding: 1rem;
        }

        .ministry-personal-details-form__record + .ministry-personal-details-form__record {
            margin-top: 1rem;
        }

        .ministry-personal-details-form__section {
            background: #f8f9fb;
            border: 1px solid #e0e4ea;
            border-radius: 6px;
            padding: 1rem;
        }

        .ministry-personal-details-form .form-control:disabled,
        .ministry-personal-details-form .form-select:disabled {
            background: #f1f3f6;
            color: #343a40;
            opacity: 1;
        }

        .ministry-personal-details-form__empty {
            border: 1px dashed #c7ccd4;
            border-radius: 6px;
            color: #6b7280;
            padding: 2rem 1rem;
            text-align: center;
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
                    const initializeMinistryPersonalDetails = () => {
                        document.querySelectorAll('[data-ministry-personal-details-editor]').forEach((editor) => {
                            if (editor.dataset.initialized === 'true') {
                                return;
                            }

                            editor.dataset.initialized = 'true';

                            const rowsContainer = editor.querySelector('[data-ministry-personal-details-rows]');
                            const template = editor.querySelector('[data-ministry-personal-details-template]');

                            if (!rowsContainer || !template) {
                                return;
                            }

                            const refresh = () => {
                                const rows = [...rowsContainer.querySelectorAll(':scope > [data-ministry-personal-details-row]')];

                                rows.forEach((row, index) => {
                                    const number = row.querySelector('[data-ministry-personal-details-number]');
                                    if (number) {
                                        number.textContent = String(index + 1);
                                    }
                                });

                                editor.querySelectorAll('[data-ministry-personal-details-count]').forEach((counter) => {
                                    counter.textContent = (counter.dataset.countTemplate || '__COUNT__').replace('__COUNT__', String(rows.length));
                                });
                            };

                            const addRow = () => {
                                const index = Number.parseInt(editor.dataset.nextIndex || '0', 10);
                                const html = template.innerHTML.replaceAll('__INDEX__', String(index));

                                rowsContainer.insertAdjacentHTML('beforeend', html);
                                editor.dataset.nextIndex = String(index + 1);
                                refresh();

                                rowsContainer.lastElementChild?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                            };

                            editor.querySelectorAll('[data-ministry-personal-details-add]').forEach((button) => {
                                button.addEventListener('click', addRow);
                            });

                            rowsContainer.addEventListener('click', (event) => {
                                const removeButton = event.target.closest('[data-ministry-personal-details-remove]');
                                if (!removeButton) {
                                    return;
                                }

                                removeButton.closest('[data-ministry-personal-details-row]')?.remove();
                                refresh();
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
