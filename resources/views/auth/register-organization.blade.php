@extends('layouts.auth', ['title' => __('app.auth.organization_register_title')])

@section('content')
    <h1>{{ __('app.auth.organization_register_title') }}</h1>
    <p>{{ __('app.auth.organization_register_intro') }}</p>

    <form method="POST" action="{{ route('register.organization.store') }}" class="grid">
        @csrf

        <div class="grid-2">
            <div>
                <label for="organization_name">{{ __('app.auth.organization_tile_title') }}</label>
                <input id="organization_name" name="organization_name" type="text" value="{{ old('organization_name') }}" required>
            </div>

            <div>
                <label for="organization_national_id">{{ __('app.auth.organization_national_id') }}</label>
                <input id="organization_national_id" name="organization_national_id" type="text" value="{{ old('organization_national_id') }}" required>
                <div class="field-help">{{ __('app.auth.organization_lookup_help') }}</div>
            </div>
        </div>

        <div class="grid-2">
            <div>
                <label for="organization_registration_no">{{ __('app.auth.registration_number') }}</label>
                <input id="organization_registration_no" name="organization_registration_no" type="text" value="{{ old('organization_registration_no') }}">
            </div>

            <div>
                <label for="organization_phone">{{ __('app.auth.organization_phone') }}</label>
                <input id="organization_phone" name="organization_phone" type="text" value="{{ old('organization_phone') }}" required>
            </div>
        </div>

        <div class="inline-actions">
            <button
                id="organization_lookup_button"
                class="btn btn-secondary"
                type="button"
                @disabled(! $organizationLookupEnabled)
            >
                {{ __('app.auth.organization_lookup_action') }}
            </button>
            <div id="organization_lookup_status" class="field-help">
                {{ $organizationLookupEnabled ? __('app.auth.organization_lookup_intro') : __('app.auth.organization_lookup_disabled') }}
            </div>
        </div>

        <div>
            <label for="organization_email">{{ __('app.auth.organization_email') }}</label>
            <input id="organization_email" name="organization_email" type="email" value="{{ old('organization_email') }}" required>
        </div>

        <hr style="border: 0; border-top: 1px solid #d9e4de; width: 100%;">

        <div class="grid-2">
            <div>
                <label for="owner_name">{{ __('app.auth.owner_full_name') }}</label>
                <input id="owner_name" name="owner_name" type="text" value="{{ old('owner_name') }}" required>
            </div>

            <div>
                <label for="owner_username">{{ __('app.auth.owner_username') }}</label>
                <input id="owner_username" name="owner_username" type="text" value="{{ old('owner_username') }}" required>
            </div>
        </div>

        <div class="grid-2">
            <div>
                <label for="owner_email">{{ __('app.auth.owner_email') }}</label>
                <input id="owner_email" name="owner_email" type="email" value="{{ old('owner_email') }}" required>
            </div>

            <div>
                <label for="owner_phone">{{ __('app.auth.owner_mobile_number') }}</label>
                <input id="owner_phone" name="owner_phone" type="text" value="{{ old('owner_phone') }}" required>
            </div>
        </div>

        <div>
            <label for="owner_national_id">{{ __('app.auth.owner_national_id') }}</label>
            <input id="owner_national_id" name="owner_national_id" type="text" value="{{ old('owner_national_id') }}" required>
        </div>

        <div class="grid-2">
            <div>
                <label for="password">{{ __('app.auth.password') }}</label>
                <input id="password" name="password" type="password" required>
            </div>

            <div>
                <label for="password_confirmation">{{ __('app.auth.confirm_password') }}</label>
                <input id="password_confirmation" name="password_confirmation" type="password" required>
            </div>
        </div>

        <div class="actions">
            <button class="btn" type="submit">{{ __('app.auth.create_organization_account') }}</button>
            <a class="btn btn-secondary" href="{{ route('register') }}">{{ __('app.auth.back') }}</a>
        </div>
    </form>

    <script>
        (() => {
            const enabled = @json($organizationLookupEnabled);

            if (!enabled) {
                return;
            }

            const button = document.getElementById('organization_lookup_button');
            const status = document.getElementById('organization_lookup_status');
            const nationalId = document.getElementById('organization_national_id');
            const registrationNo = document.getElementById('organization_registration_no');
            const organizationName = document.getElementById('organization_name');
            const organizationEmail = document.getElementById('organization_email');
            const organizationPhone = document.getElementById('organization_phone');
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

            const setStatus = (message, isError = false) => {
                status.textContent = message;
                status.style.color = isError ? '#8d1f1f' : '#60776c';
            };

            button.addEventListener('click', async () => {
                if (!nationalId.value.trim()) {
                    setStatus(@json(__('app.auth.organization_lookup_validation')), true);
                    nationalId.focus();
                    return;
                }

                button.disabled = true;
                setStatus(@json(__('app.auth.organization_lookup_loading')));

                try {
                    const response = await fetch(@json(route('register.organization.lookup')), {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify({
                            organization_national_id: nationalId.value,
                            organization_registration_no: registrationNo.value,
                        }),
                    });

                    const payload = await response.json();

                    if (!response.ok || !payload.ok) {
                        setStatus(payload.message || @json(__('app.auth.organization_lookup_failed')), true);
                        return;
                    }

                    if (payload.data.organization_name) {
                        organizationName.value = payload.data.organization_name;
                    }

                    if (payload.data.organization_registration_no) {
                        registrationNo.value = payload.data.organization_registration_no;
                    }

                    if (payload.data.organization_email) {
                        organizationEmail.value = payload.data.organization_email;
                    }

                    if (payload.data.organization_phone) {
                        organizationPhone.value = payload.data.organization_phone;
                    }

                    setStatus(payload.message || @json(__('app.auth.organization_lookup_success')));
                } catch (error) {
                    setStatus(@json(__('app.auth.organization_lookup_failed')), true);
                } finally {
                    button.disabled = false;
                }
            });
        })();
    </script>
@endsection
