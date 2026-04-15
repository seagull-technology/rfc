<div class="card mb-4">
    <div class="card-header">
        <div class="iq-header-title">
            <h3 class="card-title">{{ __('app.admin.approval_routing.guide_title') }}</h3>
        </div>
    </div>
    <div class="card-body">
        <p class="text-muted mb-3">{{ __('app.admin.approval_routing.guide_intro') }}</p>

        <div class="small">
            <div class="mb-2">
                <span class="fw-semibold">{{ __('app.admin.approval_routing.guide_steps.match_approval_title') }}:</span>
                {{ __('app.admin.approval_routing.guide_steps.match_approval_text') }}
            </div>
            <div class="mb-2">
                <span class="fw-semibold">{{ __('app.admin.approval_routing.guide_steps.check_conditions_title') }}:</span>
                {{ __('app.admin.approval_routing.guide_steps.check_conditions_text') }}
            </div>
            <div class="mb-2">
                <span class="fw-semibold">{{ __('app.admin.approval_routing.guide_steps.route_target_title') }}:</span>
                {{ __('app.admin.approval_routing.guide_steps.route_target_text') }}
            </div>
            <div>
                <span class="fw-semibold">{{ __('app.admin.approval_routing.guide_steps.priority_title') }}:</span>
                {{ __('app.admin.approval_routing.guide_steps.priority_text') }}
            </div>
        </div>

        <div class="alert alert-light border mt-4 mb-0">
            <div class="fw-semibold mb-1">{{ __('app.admin.approval_routing.terminology_title') }}</div>
            <div class="small text-muted">{{ __('app.admin.approval_routing.terminology_body') }}</div>
        </div>
    </div>
</div>
