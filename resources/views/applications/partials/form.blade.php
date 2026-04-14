@php
    $metadata = $application->metadata ?? [];
    $producer = data_get($metadata, 'producer', []);
    $director = data_get($metadata, 'director', []);
    $international = data_get($metadata, 'international', []);
    $requirements = data_get($metadata, 'requirements', []);
@endphp

<form id="form-wizard1" method="POST" action="{{ $formAction }}" class="mt-3 text-center form-content">
    @csrf

    <ul id="top-tab-list" class="p-0 row list-inline">
        <li class="mb-2 col-lg-6 col-md-6 text-start active" id="step1">
            <a href="javascript:void(0);">
                <div class="iq-icon me-3"><img src="{{ asset('images/video-camera.png') }}" alt=""></div>
                <span class="dark-wizard">{{ __('app.applications.general_information') }}</span>
            </a>
        </li>
        <li id="step2" class="mb-2 col-lg-6 col-md-6 text-start">
            <a href="javascript:void(0);">
                <div class="iq-icon me-3"><img src="{{ asset('images/todo-list.png') }}" alt=""></div>
                <span class="dark-wizard">{{ __('app.applications.requirements_list') }}</span>
            </a>
        </li>
    </ul>

    <fieldset>
        <div class="form-card text-start">
            <div class="section-form">
                <div class="p-4 px-2">
                    <div class="row g-3 mb-4">
                        <div class="col-lg-6">
                            <div class="form-group">
                                <label class="form-label">{{ __('app.applications.project_name') }}</label>
                                <span class="text-danger">*</span>
                                <input class="form-control" type="text" name="project_name" value="{{ old('project_name', $application->project_name) }}" required>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="form-group">
                                <label class="form-label">{{ __('app.applications.project_nationality') }}</label>
                                <span class="text-danger">*</span>
                                <select name="project_nationality" class="form-control select2-basic-single" required>
                                    @foreach (['jordanian', 'international'] as $option)
                                        <option value="{{ $option }}" @selected(old('project_nationality', $application->project_nationality) === $option)>{{ __('app.applications.project_nationalities.'.$option) }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="card streamit-tabs-card">
                        <div class="card-body">
                            <div class="row gy-4">
                                <div class="col-lg-3">
                                    <div class="streamit-verticle-tab">
                                        <div class="nav flex-column nav-pills me-0 me-lg-3 mb-3 mb-md-0 list-inline streamit-tabs" role="tablist" aria-orientation="vertical">
                                            @foreach ([
                                                'local_producer' => __('app.applications.producer_information'),
                                                'director_info' => __('app.applications.director_information'),
                                                'international_projects' => __('app.applications.international_project_information'),
                                                'work_category' => __('app.applications.work_category'),
                                                'release_method' => __('app.applications.release_method'),
                                                'schedule' => __('app.applications.schedule_title'),
                                                'crew_count' => __('app.applications.estimated_crew_count'),
                                                'summary' => __('app.applications.project_summary'),
                                                'budget' => __('app.applications.estimated_budget'),
                                            ] as $tabKey => $tabLabel)
                                                <button class="nav-link {{ $loop->first ? 'active' : '' }}" data-bs-toggle="pill" type="button" data-bs-target="#{{ $tabKey }}_tab" role="tab" aria-selected="{{ $loop->first ? 'true' : 'false' }}">
                                                    <span>{{ $tabLabel }}</span>
                                                </button>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-9 edit-tab-content">
                                    <div class="tab-content" id="streamit-tabs-content">
                                        <div class="tab-pane fade show active" id="local_producer_tab" role="tabpanel">
                                            <div class="row g-3">
                                                @foreach ([
                                                    'producer_name' => __('app.applications.producer_name'),
                                                    'production_company_name' => __('app.applications.production_company_name'),
                                                    'contact_address' => __('app.applications.contact_address'),
                                                    'contact_phone' => __('app.applications.contact_phone'),
                                                    'contact_mobile' => __('app.applications.contact_mobile'),
                                                    'contact_fax' => __('app.applications.contact_fax'),
                                                    'contact_email' => __('app.applications.contact_email'),
                                                    'liaison_name' => __('app.applications.liaison_name'),
                                                    'liaison_position' => __('app.applications.liaison_position'),
                                                    'liaison_email' => __('app.applications.liaison_email'),
                                                    'liaison_mobile' => __('app.applications.liaison_mobile'),
                                                ] as $field => $label)
                                                    <div class="col-lg-{{ in_array($field, ['producer_name', 'production_company_name', 'liaison_name'], true) ? '12' : '6' }}">
                                                        <div class="form-group">
                                                            <label class="form-label">{{ $label }}</label>
                                                            @if (! in_array($field, ['contact_mobile', 'contact_fax'], true))
                                                                <span class="text-danger">*</span>
                                                            @endif
                                                            <input class="form-control" type="{{ str_contains($field, 'email') ? 'email' : 'text' }}" name="{{ $field }}" value="{{ old($field, data_get($producer, $field)) }}" @required(! in_array($field, ['contact_mobile', 'contact_fax'], true))>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>

                                        <div class="tab-pane fade" id="director_info_tab" role="tabpanel">
                                            <div class="row g-3">
                                                <div class="col-lg-12">
                                                    <div class="form-group">
                                                        <label class="form-label">{{ __('app.applications.director_name') }}</label>
                                                        <span class="text-danger">*</span>
                                                        <input class="form-control" type="text" name="director_name" value="{{ old('director_name', data_get($director, 'director_name')) }}" required>
                                                    </div>
                                                </div>
                                                <div class="col-lg-6">
                                                    <div class="form-group">
                                                        <label class="form-label">{{ __('app.applications.director_nationality') }}</label>
                                                        <span class="text-danger">*</span>
                                                        <input class="form-control" type="text" name="director_nationality" value="{{ old('director_nationality', data_get($director, 'director_nationality')) }}" required>
                                                    </div>
                                                </div>
                                                <div class="col-lg-6">
                                                    <div class="form-group">
                                                        <label class="form-label">{{ __('app.applications.director_profile_url') }}</label>
                                                        <input class="form-control" type="url" name="director_profile_url" value="{{ old('director_profile_url', data_get($director, 'director_profile_url')) }}">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="tab-pane fade" id="international_projects_tab" role="tabpanel">
                                            <div class="row g-3">
                                                <div class="col-lg-6">
                                                    <div class="form-group">
                                                        <label class="form-label">{{ __('app.applications.international_producer_name') }}</label>
                                                        <input class="form-control" type="text" name="international_producer_name" value="{{ old('international_producer_name', data_get($international, 'international_producer_name')) }}">
                                                    </div>
                                                </div>
                                                <div class="col-lg-6">
                                                    <div class="form-group">
                                                        <label class="form-label">{{ __('app.applications.international_producer_company') }}</label>
                                                        <input class="form-control" type="text" name="international_producer_company" value="{{ old('international_producer_company', data_get($international, 'international_producer_company')) }}">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="tab-pane fade" id="work_category_tab" role="tabpanel">
                                            <div class="form-group">
                                                <label class="form-label">{{ __('app.applications.work_category') }}</label>
                                                <span class="text-danger">*</span>
                                                <select name="work_category" class="form-control select2-basic-single" required>
                                                    @foreach (['feature_film', 'documentary', 'series', 'commercial', 'tv_program', 'student_project'] as $option)
                                                        <option value="{{ $option }}" @selected(old('work_category', $application->work_category) === $option)>{{ __('app.applications.work_categories.'.$option) }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>

                                        <div class="tab-pane fade" id="release_method_tab" role="tabpanel">
                                            <div class="form-group">
                                                <label class="form-label">{{ __('app.applications.release_method') }}</label>
                                                <span class="text-danger">*</span>
                                                <select name="release_method" class="form-control select2-basic-single" required>
                                                    @foreach (['cinema', 'television', 'streaming', 'festival', 'digital'] as $option)
                                                        <option value="{{ $option }}" @selected(old('release_method', $application->release_method) === $option)>{{ __('app.applications.release_methods.'.$option) }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>

                                        <div class="tab-pane fade" id="schedule_tab" role="tabpanel">
                                            <div class="row g-3">
                                                <div class="col-lg-6">
                                                    <div class="form-group">
                                                        <label class="form-label">{{ __('app.applications.planned_start_date') }}</label>
                                                        <span class="text-danger">*</span>
                                                        <input class="form-control" type="date" name="planned_start_date" value="{{ old('planned_start_date', optional($application->planned_start_date)->format('Y-m-d')) }}" required>
                                                    </div>
                                                </div>
                                                <div class="col-lg-6">
                                                    <div class="form-group">
                                                        <label class="form-label">{{ __('app.applications.planned_end_date') }}</label>
                                                        <span class="text-danger">*</span>
                                                        <input class="form-control" type="date" name="planned_end_date" value="{{ old('planned_end_date', optional($application->planned_end_date)->format('Y-m-d')) }}" required>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="tab-pane fade" id="crew_count_tab" role="tabpanel">
                                            <div class="form-group">
                                                <label class="form-label">{{ __('app.applications.estimated_crew_count') }}</label>
                                                <input class="form-control" type="number" min="1" name="estimated_crew_count" value="{{ old('estimated_crew_count', $application->estimated_crew_count) }}">
                                            </div>
                                        </div>

                                        <div class="tab-pane fade" id="summary_tab" role="tabpanel">
                                            <div class="form-group">
                                                <label class="form-label">{{ __('app.applications.project_summary') }}</label>
                                                <span class="text-danger">*</span>
                                                <textarea class="form-control" name="project_summary" rows="7" required>{{ old('project_summary', $application->project_summary) }}</textarea>
                                            </div>
                                        </div>

                                        <div class="tab-pane fade" id="budget_tab" role="tabpanel">
                                            <div class="form-group">
                                                <label class="form-label">{{ __('app.applications.estimated_budget') }}</label>
                                                <input class="form-control" type="number" step="0.01" min="0" name="estimated_budget" value="{{ old('estimated_budget', $application->estimated_budget) }}">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <button type="button" name="next" class="btn btn-danger next action-button float-end btn-lg">
            {{ app()->getLocale() === 'ar' ? 'التالي' : 'Next' }}
        </button>
    </fieldset>

    <fieldset>
        <div class="form-card text-start">
            <div class="card mt-0">
                <div class="card-header">
                    <div class="header-title">
                        <h2 class="episode-playlist-title wp-heading-inline">
                            <span class="position-relative">{{ __('app.applications.requirements_list') }}</span>
                        </h2>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label d-block">{{ __('app.applications.required_approvals') }}</label>
                            <div class="row">
                                @foreach (['public_security', 'digital_economy', 'environment', 'municipalities', 'airports', 'drones', 'heritage'] as $approval)
                                    <div class="col-lg-6 mb-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" value="{{ $approval }}" id="approval-{{ $approval }}" name="required_approvals[]" @checked(in_array($approval, old('required_approvals', data_get($requirements, 'required_approvals', [])), true))>
                                            <label class="form-check-label" for="approval-{{ $approval }}">{{ __('app.applications.required_approval_options.'.$approval) }}</label>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">{{ __('app.applications.supporting_notes') }}</label>
                            <textarea class="form-control" name="supporting_notes" rows="5">{{ old('supporting_notes', data_get($requirements, 'supporting_notes')) }}</textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="form-actions d-flex gap-2 flex-wrap justify-content-end">
            <button class="btn btn-danger d-flex align-items-center gap-2" type="submit">
                <i class="ph-fill ph-floppy-disk-back"></i>
                <span>{{ $submitLabel }}</span>
            </button>
            <button type="button" name="previous" class="btn btn-dark previous action-button-previous">
                {{ app()->getLocale() === 'ar' ? 'السابق' : 'Previous' }}
            </button>
        </div>
    </fieldset>
</form>

@push('scripts')
    <script src="{{ asset('js/form-wizard.js') }}"></script>
@endpush
