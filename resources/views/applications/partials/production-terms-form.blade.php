@php
    $productionTerms = (array) ($productionTerms ?? data_get($annex ?? [], 'production_terms', []));
    $productionTermsReadOnly = (bool) ($productionTermsReadOnly ?? false);
    $applicationMetadata = (array) data_get($application ?? null, 'metadata', []);
    $authenticatedUser = auth()->user();
    $authenticatedUserIsForeignProducer = $authenticatedUser?->registration_type === 'international_producer';
    $foreignDeclaration = (array) data_get($applicationMetadata, 'international.account.declaration', []);
    $foreignDeclarationSigned = (bool) data_get($foreignDeclaration, 'accepted');
    $localApplicantName = old(
        'production_terms_local_applicant_name',
        data_get($productionTerms, 'local_applicant_name')
            ?: ($authenticatedUserIsForeignProducer
                ? data_get($applicationMetadata, 'producer.producer_name')
                : $authenticatedUser?->displayName()),
    );
    $foreignApplicantName = old(
        'production_terms_foreign_applicant_name',
        data_get($productionTerms, 'foreign_applicant_name')
            ?: ($authenticatedUserIsForeignProducer
                ? $authenticatedUser?->displayName()
                : (old('international_producer_name') ?: data_get($applicationMetadata, 'international.international_producer_name'))),
    );
    $localSignature = data_get($productionTerms, 'local_signature') ?: $localApplicantName;
    $foreignSignature = data_get($productionTerms, 'foreign_signature')
        ?: ($authenticatedUserIsForeignProducer
            ? $authenticatedUser?->displayName()
            : ($foreignDeclarationSigned ? data_get($foreignDeclaration, 'signed_by_name') : null));
    $termsAccepted = (bool) old('production_terms_accepted', data_get($productionTerms, 'accepted', false));
    $termsAcceptedAt = data_get($productionTerms, 'accepted_at');
    $termsClauses = (array) __('app.applications.production_terms.clauses');
@endphp

@once
    <style>
        .production-terms-document {
            color: #111827;
            line-height: 1.9;
        }

        .production-terms-document__clauses {
            background: #f8f9fb;
            border: 1px solid #e0e4ea;
            border-radius: 6px;
            max-height: 52vh;
            overflow-y: auto;
            padding: 1rem 1.25rem;
        }

        .production-terms-document__clauses li + li {
            margin-top: .75rem;
        }

        .production-terms-document__identity {
            background: #fff;
            border: 1px solid #e0e4ea;
            border-radius: 6px;
            padding: 1rem;
        }

        .production-terms-document__identity .form-control:disabled {
            background: #f1f3f6;
            color: #343a40;
            opacity: 1;
        }
    </style>
@endonce

<div class="production-terms-document">
    @unless ($productionTermsReadOnly)
        <input type="hidden" name="production_terms_version" value="production_form_2025">
    @endunless

    <p class="fw-600 text-danger mb-3">
        <i class="ph ph-info me-2"></i>{{ __('app.applications.production_terms.intro') }}
    </p>

    <div class="production-terms-document__clauses mb-4">
        <ol class="mb-0 ps-4">
            @foreach ($termsClauses as $clause)
                <li>{{ $clause }}</li>
            @endforeach
        </ol>
    </div>

    <div class="alert alert-light border mb-4">
        <div class="fw-600">{{ __('app.applications.production_terms.legal_document') }}</div>
        <div class="mt-2">{{ __('app.applications.production_terms.acknowledgment') }}</div>
    </div>

    <div class="production-terms-document__identity">
        <div class="row g-3">
            <div class="col-12 col-lg-6">
                <label class="form-label">{{ __('app.applications.production_terms.local_applicant') }}</label>
                <input
                    type="text"
                    class="form-control"
                    value="{{ $localApplicantName }}"
                    disabled
                    data-production-terms-local-applicant
                >
            </div>
            <div class="col-12 col-lg-6">
                <label class="form-label">{{ __('app.applications.production_terms.signature') }}</label>
                <input type="text" class="form-control" value="{{ $localSignature }}" disabled>
            </div>
            <div class="col-12 col-lg-6">
                <label class="form-label">{{ __('app.applications.production_terms.foreign_applicant') }}</label>
                <input
                    type="text"
                    class="form-control"
                    value="{{ $foreignApplicantName ?: __('app.applications.production_terms.not_applicable') }}"
                    disabled
                    data-production-terms-foreign-applicant
                    data-empty-value="{{ __('app.applications.production_terms.not_applicable') }}"
                >
            </div>
            <div class="col-12 col-lg-6">
                <label class="form-label">{{ __('app.applications.production_terms.signature') }}</label>
                <input
                    type="text"
                    class="form-control"
                    value="{{ $foreignSignature ?: (filled($foreignApplicantName) ? __('app.applications.production_terms.pending_foreign_signature') : __('app.applications.production_terms.not_applicable')) }}"
                    disabled
                >
            </div>
        </div>

        @if ($productionTermsReadOnly)
            <div class="d-flex align-items-center gap-2 flex-wrap mt-4">
                <span class="badge bg-{{ $termsAccepted ? 'success' : 'secondary' }}">
                    {{ $termsAccepted ? __('app.applications.annex_confirmed') : __('app.applications.annex_not_confirmed') }}
                </span>
                @if (filled($termsAcceptedAt))
                    <span class="text-muted small">{{ __('app.applications.production_terms.accepted_at', ['date' => $termsAcceptedAt]) }}</span>
                @endif
            </div>
        @else
            <div class="form-check mt-4">
                <input type="hidden" name="production_terms_accepted" value="0">
                <input
                    type="checkbox"
                    class="form-check-input"
                    id="production_terms_accepted_drawer"
                    name="production_terms_accepted"
                    value="1"
                    required
                    @checked($termsAccepted)
                >
                <label class="form-check-label fw-600" for="production_terms_accepted_drawer">
                    {{ __('app.applications.production_terms.accept_label') }} <span class="text-danger">*</span>
                </label>
            </div>
        @endif
    </div>
</div>
