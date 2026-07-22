@php
    $attachment = (array) ($attachment ?? []);
    $attachmentIndex = $attachmentIndex ?? 0;
    $attachmentInputPrefix = $inputPrefix.'[attachments]['.$attachmentIndex.']';
    $attachmentIdToken = preg_replace('/[^A-Za-z0-9_-]/', '_', (string) $attachmentIndex);
    $attachmentIdPrefix = $idPrefix.'_attachment_'.$attachmentIdToken;
    $storedAttachment = filled($attachment['id'] ?? null) && filled($attachment['path'] ?? null);
@endphp

<div class="ministry-personal-details-form__attachment" data-ministry-attachment-row data-stored="{{ $storedAttachment ? 'true' : 'false' }}">
    <input type="hidden" name="{{ $attachmentInputPrefix }}[id]" value="{{ $attachment['id'] ?? '' }}">
    <input type="hidden" name="{{ $attachmentInputPrefix }}[_remove]" value="0" data-ministry-attachment-remove-flag>

    <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
        <strong>
            {{ __('app.applications.ministry_interior_personal_details.attachment_record') }}
            <span data-ministry-attachment-number>{{ is_numeric($attachmentIndex) ? ((int) $attachmentIndex + 1) : 1 }}</span>
        </strong>
        @unless ($readOnly)
            <button type="button" class="btn btn-sm btn-outline-danger" data-ministry-attachment-remove title="{{ __('app.applications.ministry_interior_personal_details.remove_attachment') }}">
                <i class="fa-solid fa-trash" aria-hidden="true"></i>
                <span class="visually-hidden">{{ __('app.applications.ministry_interior_personal_details.remove_attachment') }}</span>
            </button>
        @endunless
    </div>

    <div class="row g-3 align-items-end">
        <div class="col-12 col-lg-7">
            <label class="form-label" for="{{ $attachmentIdPrefix }}_document_type">
                {{ __('app.applications.ministry_interior_personal_details.fields.attachment_type') }}
                <span class="text-danger" aria-hidden="true">*</span>
            </label>
            <select class="form-select" id="{{ $attachmentIdPrefix }}_document_type" name="{{ $attachmentInputPrefix }}[document_type]" @disabled($readOnly)>
                <option value="">{{ __('app.admin.select_placeholder') }}</option>
                @foreach (['professional_license', 'kinship_proof', 'passport_copy', 'sponsor_residence', 'foreign_residence'] as $option)
                    <option value="{{ $option }}" @selected((string) ($attachment['document_type'] ?? '') === $option)>{{ __('app.applications.ministry_interior_personal_details.options.attachment_type.'.$option) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-12 col-lg-5">
            @if ($readOnly)
                @if ($storedAttachment && isset($application))
                    <a class="btn btn-outline-primary w-100" href="{{ route('applications.annex.personal-details.attachments.download', [$application, $rowIndex, $attachment['id']]) }}">
                        <i class="fa-solid fa-download me-2" aria-hidden="true"></i>{{ __('app.applications.ministry_interior_personal_details.download_attachment') }}
                    </a>
                    <div class="form-text text-break mt-2">{{ $attachment['name'] ?? '' }}</div>
                @else
                    <input type="text" class="form-control" value="{{ $attachment['name'] ?? __('app.dashboard.not_available') }}" disabled>
                @endif
            @else
                <label class="form-label" for="{{ $attachmentIdPrefix }}_file">
                    {{ __('app.applications.ministry_interior_personal_details.fields.attachment_file') }}
                    @unless ($storedAttachment)
                        <span class="text-danger" aria-hidden="true">*</span>
                    @endunless
                </label>
                <input type="file" class="form-control" id="{{ $attachmentIdPrefix }}_file" name="{{ $attachmentInputPrefix }}[file]" accept=".jpg,.jpeg,.tif,.tiff,image/jpeg,image/tiff">
                @if ($storedAttachment)
                    <div class="form-text">{{ $attachment['name'] ?? '' }}</div>
                @endif
            @endif
        </div>
    </div>
</div>
