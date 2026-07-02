@php
    $letter = $letter ?? null;
    $attachmentOptions = __('app.official_letters.attachment_options');
    $selectedAttachments = collect(old('attachments', $letter?->attachments ?? []))->filter()->values()->all();
    $routedApprovals = collect($officialLetterApprovals ?? []);
    $status = $letter?->status ?? 'draft';
    $statusClass = $status === 'issued' ? 'success' : 'secondary';
    $displayApprovals = $letter
        ? ($letter->targetEntity ? collect([['target_entity_name' => $letter->targetEntity->displayName()]]) : ($letter->authorityApproval ? collect([$letter->authorityApproval]) : collect()))
        : $routedApprovals;
    $targetLabel = static function ($target): string {
        if (is_array($target)) {
            return $target['target_entity_name'] ?? $target['approval_label'] ?? __('app.dashboard.not_available');
        }

        return $target->entity?->displayName() ?? $target->localizedAuthority();
    };
@endphp

@csrf
@isset($method)
    @method($method)
@endisset

<div class="letter-container px-3">
    <div class="header-logo text-center mb-4">
        <img src="{{ asset('images/logo.svg') }}" alt="{{ config('app.name') }}" style="max-height: 110px;">
    </div>

    <div class="row g-3">
        <div class="col-lg-4">
            <label class="form-label" for="{{ $formId }}_letter_date">{{ __('app.official_letters.letter_date') }}</label>
            <input id="{{ $formId }}_letter_date" name="letter_date" type="date" class="form-control" value="{{ old('letter_date', $letter?->letter_date?->format('Y-m-d') ?? now()->toDateString()) }}">
        </div>
        <div class="col-lg-4">
            <label class="form-label">{{ __('app.official_letters.serial_number') }}</label>
            <div class="form-control bg-light">{{ $letter?->serial_number ?: __('app.official_letters.auto_generated_serial') }}</div>
        </div>
        <div class="col-lg-4">
            <label class="form-label">{{ __('app.official_letters.status') }}</label>
            <div class="form-control bg-light d-flex align-items-center">
                <span class="badge bg-{{ $statusClass }}">{{ __('app.official_letters.statuses.'.$status) }}</span>
            </div>
        </div>
        <div class="col-lg-4">
            <label class="form-label" for="{{ $formId }}_recipient_prefix">{{ __('app.official_letters.recipient_prefix') }}</label>
            <input id="{{ $formId }}_recipient_prefix" name="recipient_prefix" type="text" class="form-control" value="{{ old('recipient_prefix', $letter?->recipient_prefix ?? (app()->getLocale() === 'ar' ? 'عطوفة' : 'H.E.')) }}">
        </div>
        <div class="col-lg-4">
            <label class="form-label" for="{{ $formId }}_recipient_name">{{ __('app.official_letters.recipient_name') }}</label>
            <input id="{{ $formId }}_recipient_name" name="recipient_name" type="text" class="form-control" value="{{ old('recipient_name', $letter?->recipient_name) }}" required>
        </div>
        <div class="col-12">
            <label class="form-label">{{ __('app.official_letters.automatic_recipients') }}</label>
            <div class="border rounded p-3 bg-light">
                @if ($letter?->targetEntity)
                    <span class="badge bg-primary-subtle text-primary">{{ $letter->targetEntity->displayName() }}</span>
                @elseif ($displayApprovals->isNotEmpty())
                    <div class="d-flex gap-2 flex-wrap">
                        @foreach ($displayApprovals as $target)
                            <span class="badge bg-primary-subtle text-primary">{{ $targetLabel($target) }}</span>
                        @endforeach
                    </div>
                @else
                    <span class="text-muted">{{ __('app.official_letters.no_routed_authorities') }}</span>
                @endif
            </div>
        </div>
        <div class="col-12">
            <label class="form-label" for="{{ $formId }}_subject">{{ __('app.official_letters.subject') }}</label>
            <input id="{{ $formId }}_subject" name="subject" type="text" class="form-control" value="{{ old('subject', $letter?->subject) }}" required>
        </div>
        <div class="col-12">
            <label class="form-label" for="{{ $formId }}_body">{{ __('app.official_letters.body') }}</label>
            <textarea id="{{ $formId }}_body" name="body" rows="10" class="form-control" required>{{ old('body', $letter?->body) }}</textarea>
        </div>
        <div class="col-12">
            <h6 class="fw-bold mb-3">{{ __('app.official_letters.attachments') }}</h6>
            <div class="row g-2">
                @foreach ($attachmentOptions as $key => $label)
                    <div class="col-lg-6">
                        <label class="d-flex align-items-center gap-2 border rounded p-2 mb-0 bg-light">
                            <input type="checkbox" name="attachments[]" value="{{ $label }}" @checked(in_array($label, $selectedAttachments, true))>
                            <span>{{ $label }}</span>
                        </label>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
