@extends('layouts.auth', ['title' => __('app.dashboard.complete_registration_title')])

@section('content')
    <div class="wrapper">
        <section class="sign-in-page" style="background-image: url('{{ asset('images/loginBg.jpeg') }}')">
            <div class="container">
                <div class="justify-content-center align-items-center height-self-center row">
                    <div class="align-self-center col-lg-10 col-md-12">
                        <div class="sign-user_card">
                            <a href="{{ route('login') }}">
                                <img class="img-fluid logo" src="{{ asset('images/logo.svg') }}" alt="#">
                            </a>
                            <div class="sign-in-page-data pt-5">
                                <div class="sign-in-from w-100 m-auto">
                                    @include('auth.partials.alerts')

                                    <div class="text-center mb-4">
                                        <h3 class="mb-2">{{ __('app.dashboard.complete_registration_title') }}</h3>
                                        <p class="mb-0">{{ __('app.dashboard.complete_registration_intro') }}</p>
                                    </div>

                                    <div class="alert alert-warning mb-4">
                                        <strong>{{ __('app.dashboard.review_notes') }}</strong>
                                        <div class="mt-2">{{ data_get($entity->metadata, 'review.note', __('app.dashboard.no_review_notes')) }}</div>
                                    </div>

                                    <form method="POST" action="{{ $isSignedLinkFlow ? request()->fullUrl() : route('registration.completion.update') }}" enctype="multipart/form-data">
                                        @csrf

                                        <div class="row">
                                            <div class="col-md-12">
                                                <div class="mb-3">
                                                    <label class="form-label">{{ __('app.auth.entity_name_labels.'.$registrationType) }}</label>
                                                    <input type="text" name="entity_name" class="form-control @error('entity_name') is-invalid @enderror" value="{{ old('entity_name', $entity->displayName()) }}" required>
                                                    @error('entity_name')
                                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                                    @enderror
                                                </div>
                                            </div>

                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label">{{ __('app.auth.registration_number_labels.'.$registrationType) }}</label>
                                                    <input type="text" name="registration_number" class="form-control @error('registration_number') is-invalid @enderror" value="{{ old('registration_number', $entity->registration_no) }}" required>
                                                    @error('registration_number')
                                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                                    @enderror
                                                </div>
                                            </div>

                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label">{{ __('app.auth.email') }}</label>
                                                    <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $entity->email ?: $user->email) }}" required>
                                                    @error('email')
                                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                                    @enderror
                                                </div>
                                            </div>

                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label">{{ __('app.auth.mobile_number') }}</label>
                                                    <input type="text" name="phone" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone', $entity->phone ?: $user->phone) }}" required>
                                                    @error('phone')
                                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                                    @enderror
                                                </div>
                                            </div>

                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label">{{ __('app.auth.address_labels.'.$registrationType) }}</label>
                                                    <input type="text" name="address" class="form-control @error('address') is-invalid @enderror" value="{{ old('address', data_get($entity->metadata, 'address')) }}" required>
                                                    @error('address')
                                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                                    @enderror
                                                </div>
                                            </div>

                                            <div class="col-md-12">
                                                <div class="mb-3">
                                                    <label class="form-label">{{ __('app.auth.description_labels.'.$registrationType) }}</label>
                                                    <textarea name="description" class="form-control @error('description') is-invalid @enderror" rows="6">{{ old('description', data_get($entity->metadata, 'description')) }}</textarea>
                                                    @error('description')
                                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                                    @enderror
                                                </div>
                                            </div>

                                            <div class="col-md-12">
                                                <div class="mb-3">
                                                    <label for="registration-document" class="form-label custom-file-input">{{ __('app.dashboard.replace_registration_document') }}</label>
                                                    <input class="form-control @error('registration_document') is-invalid @enderror" type="file" id="registration-document" name="registration_document">
                                                    @error('registration_document')
                                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                                    @enderror
                                                    <div class="form-text">{{ data_get($entity->metadata, 'registration_document_name', __('app.dashboard.not_available')) }}</div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="d-flex gap-3 mt-3">
                                            <button type="submit" class="btn btn-danger flex-grow-1">{{ __('app.dashboard.submit_completion') }}</button>
                                            <a href="{{ $isSignedLinkFlow ? route('login') : route('dashboard') }}" class="btn btn-outline-secondary flex-grow-1">{{ $isSignedLinkFlow ? __('app.dashboard.back_to_login') : __('app.dashboard.back_to_status') }}</a>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection
