@php
    $fieldName = $fieldName ?? 'conditions[annex_flags][]';
    $fieldId = $fieldId ?? 'conditions-annex-flags';
    $selectedFlags = collect($selectedAnnexFlags ?? [])
        ->filter(fn ($flag) => filled($flag))
        ->map(fn ($flag) => (string) $flag)
        ->unique()
        ->values();
@endphp

@once
    @push('styles')
        <style>
            .approval-annex-trigger-list {
                display: grid;
                gap: 1rem;
            }

            .approval-annex-trigger-item {
                border: 1px solid rgba(0, 0, 0, 0.08);
                background: #fff;
                padding: 1rem;
            }

            .approval-annex-trigger-panel {
                border-top: 1px solid rgba(0, 0, 0, 0.08);
                margin-top: 0.85rem;
                padding-top: 0.85rem;
            }

            .approval-annex-trigger-options {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
                gap: 0.55rem 1rem;
            }
        </style>
    @endpush

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                document.querySelectorAll('[data-annex-trigger-root]').forEach(function (root) {
                    root.querySelectorAll('[data-annex-trigger-group]').forEach(function (group) {
                        const toggle = group.querySelector('[data-annex-trigger-toggle]');
                        const panel = group.querySelector('[data-annex-trigger-panel]');
                        const base = group.querySelector('[data-annex-trigger-base]');
                        const children = Array.from(group.querySelectorAll('[data-annex-trigger-child]'));

                        function setOpen(open) {
                            if (panel) {
                                panel.classList.toggle('d-none', !open);
                            }

                            if (!open) {
                                group.querySelectorAll('input[type="checkbox"][name]').forEach(function (checkbox) {
                                    checkbox.checked = false;
                                });
                            }
                        }

                        if (toggle) {
                            toggle.addEventListener('change', function () {
                                setOpen(toggle.checked);
                            });
                        }

                        if (base) {
                            base.addEventListener('change', function () {
                                if (base.checked) {
                                    children.forEach(function (child) {
                                        child.checked = false;
                                    });

                                    if (toggle) {
                                        toggle.checked = true;
                                    }

                                    setOpen(true);
                                }
                            });
                        }

                        children.forEach(function (child) {
                            child.addEventListener('change', function () {
                                if (child.checked) {
                                    if (base) {
                                        base.checked = false;
                                    }

                                    if (toggle) {
                                        toggle.checked = true;
                                    }

                                    setOpen(true);
                                }
                            });
                        });
                    });
                });
            });
        </script>
    @endpush
@endonce

<div id="{{ $fieldId }}" class="approval-annex-trigger-list" data-annex-trigger-root>
    @foreach ($annexTriggerGroups as $group)
        @php
            $childOptions = collect($group['child_groups'] ?? [])
                ->flatMap(fn (array $childGroup) => collect($childGroup['options'] ?? [])->pluck('flag'))
                ->filter()
                ->values();
            $selectedChildren = $selectedFlags->intersect($childOptions)->values();
            $groupIsOpen = $selectedFlags->contains($group['flag']) || $selectedChildren->isNotEmpty();
            $baseChecked = $selectedFlags->contains($group['flag']) && $selectedChildren->isEmpty();
            $groupId = $fieldId.'-'.$group['key'];
        @endphp

        <div class="approval-annex-trigger-item" data-annex-trigger-group>
            <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                <div>
                    <h6 class="mb-1">{{ $group['label'] }}</h6>
                    <div class="small text-muted">{{ __('app.admin.approval_routing.annex_trigger_group_help') }}</div>
                </div>
                <div class="form-check form-switch">
                    <input id="{{ $groupId }}-toggle" class="form-check-input" type="checkbox" data-annex-trigger-toggle @checked($groupIsOpen)>
                    <label class="form-check-label" for="{{ $groupId }}-toggle">{{ __('app.admin.approval_routing.annex_trigger_form_toggle') }}</label>
                </div>
            </div>

            <div class="approval-annex-trigger-panel {{ $groupIsOpen ? '' : 'd-none' }}" data-annex-trigger-panel>
                <div class="form-check mb-3">
                    <input id="{{ $groupId }}-base" class="form-check-input" type="checkbox" name="{{ $fieldName }}" value="{{ $group['flag'] }}" data-annex-trigger-base @checked($baseChecked)>
                    <label class="form-check-label" for="{{ $groupId }}-base">
                        {{ __('app.admin.approval_routing.annex_trigger_any_form', ['form' => $group['label']]) }}
                    </label>
                </div>

                @foreach ($group['child_groups'] ?? [] as $childGroup)
                    @if (collect($childGroup['options'] ?? [])->isNotEmpty())
                        <div class="mb-3">
                            <div class="fw-semibold small mb-2">{{ $childGroup['label'] }}</div>
                            <div class="approval-annex-trigger-options">
                                @foreach ($childGroup['options'] as $option)
                                    @php $optionId = $groupId.'-'.$childGroup['key'].'-'.$loop->index; @endphp
                                    <div class="form-check">
                                        <input id="{{ $optionId }}" class="form-check-input" type="checkbox" name="{{ $fieldName }}" value="{{ $option['flag'] }}" data-annex-trigger-child @checked($selectedFlags->contains($option['flag']))>
                                        <label class="form-check-label" for="{{ $optionId }}">{{ $option['label'] }}</label>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    @endforeach
</div>
