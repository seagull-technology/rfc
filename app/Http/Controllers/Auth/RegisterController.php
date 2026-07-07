<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Entity;
use App\Models\Group;
use App\Models\User;
use App\Services\CompanyRegistrationLookupService;
use App\Services\RoleAssignmentService;
use App\Services\StudentRegistrationLookupService;
use App\Support\PhoneNumber;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function index(): View
    {
        return view('auth.register', [
            'activeRegistrationType' => old('registration_type', 'student'),
            'registrationTypes' => $this->registrationTypes(),
        ]);
    }

    public function store(
        Request $request,
        RoleAssignmentService $roleAssignmentService,
        StudentRegistrationLookupService $studentLookupService,
        CompanyRegistrationLookupService $companyLookupService,
    ): RedirectResponse {
        $request->validate([
            'registration_type' => ['required', Rule::in(array_keys($this->registrationTypes()))],
        ]);

        $registrationType = (string) $request->input('registration_type');

        if ($registrationType === 'student') {
            return $this->storeStudent($request, $roleAssignmentService, $studentLookupService);
        }

        if ($registrationType === 'company') {
            return $this->storeCompany($request, $roleAssignmentService, $companyLookupService);
        }

        return $this->storeOrganizationLike($request, $roleAssignmentService, $registrationType);
    }

    private function storeStudent(
        Request $request,
        RoleAssignmentService $roleAssignmentService,
        StudentRegistrationLookupService $studentLookupService,
    ): RedirectResponse {
        $data = $request->validate([
            'registration_type' => ['required', 'in:student'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'national_id' => ['required', 'regex:/^\d{10}$/', Rule::unique('users', 'national_id'), Rule::unique('entities', 'national_id')],
            'phone' => ['required', 'regex:/^\d{10}$/', Rule::unique('users', 'phone')],
            'address' => ['required', 'string', 'max:255'],
            'logo' => $this->logoValidationRules(),
            'student_lookup_verified' => ['accepted'],
            'password' => array_merge(['required', 'confirmed'], $this->passwordRules()),
        ], array_merge([
            'national_id.regex' => __('app.auth.national_id_digits'),
            'phone.regex' => __('app.auth.phone_digits'),
        ], $this->logoValidationMessages()));

        $lookupState = $request->session()->get(StudentRegistrationLookupService::SESSION_KEY);

        if (! $studentLookupService->lookupStateMatches(is_array($lookupState) ? $lookupState : null, $data['national_id'])) {
            throw ValidationException::withMessages([
                'national_id' => __('app.auth.student_lookup_required'),
            ]);
        }

        $data = array_merge($data, (array) data_get($lookupState, 'data', []));
        $data['phone'] = PhoneNumber::normalize($data['phone']);
        $username = $this->makeUsername('student', $data['national_id']);
        $logo = $data['logo'] ?? null;

        DB::transaction(function () use ($data, $roleAssignmentService, $username, $logo): void {
            $group = Group::query()->where('code', 'individuals')->firstOrFail();

            $user = User::query()->create([
                'name' => $data['full_name'],
                'username' => $username,
                'email' => $data['email'],
                'national_id' => $data['national_id'],
                'phone' => $data['phone'],
                'status' => 'pending_review',
                'registration_type' => 'student',
                'password' => Hash::make($data['password']),
            ]);

            $entity = Entity::query()->create([
                'group_id' => $group->getKey(),
                'name_en' => $data['full_name'],
                'name_ar' => $data['full_name'],
                'national_id' => $data['national_id'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'registration_type' => 'student',
                'status' => 'pending_review',
                'metadata' => array_merge([
                    'birth_date' => $data['birth_date'],
                    'gender' => $data['gender'],
                    'nationality' => $data['nationality'],
                    'university_name' => $data['university_name'],
                    'major' => $data['major'],
                    'address' => $data['address'],
                ], $this->logoMetadata($logo, 'student')),
            ]);

            $entity->users()->attach($user->getKey(), [
                'is_primary' => true,
                'status' => 'active',
                'joined_at' => now(),
            ]);

            $roleAssignmentService->assignToEntity($user, $entity, 'applicant_owner');
        });

        $request->session()->forget(StudentRegistrationLookupService::SESSION_KEY);

        return redirect()
            ->route('login')
            ->with('status', __('app.auth.account_under_review'));
    }

    private function storeCompany(
        Request $request,
        RoleAssignmentService $roleAssignmentService,
        CompanyRegistrationLookupService $companyLookupService,
    ): RedirectResponse {
        $data = $request->validate([
            'registration_type' => ['required', 'in:company'],
            'registration_number' => ['required', 'string', 'max:50', Rule::unique('entities', 'registration_no')],
            'entity_name' => ['nullable', 'string', 'max:255'],
            'company_registration_date' => ['nullable', 'date'],
            'company_capital' => ['nullable', 'numeric', 'min:0'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'phone' => ['required', 'regex:/^\d{10}$/', Rule::unique('users', 'phone')],
            'address' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'registration_document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'logo' => $this->logoValidationRules(),
            'company_lookup_verified' => ['accepted'],
            'password' => array_merge(['required', 'confirmed'], $this->passwordRules()),
        ], array_merge([
            'phone.regex' => __('app.auth.phone_digits'),
        ], $this->logoValidationMessages()));

        $lookupState = $request->session()->get(CompanyRegistrationLookupService::SESSION_KEY);

        if (! $companyLookupService->lookupStateMatches(is_array($lookupState) ? $lookupState : null, $data['registration_number'])) {
            throw ValidationException::withMessages([
                'registration_number' => __('app.auth.company_lookup_required'),
            ]);
        }

        $data = array_merge($data, (array) data_get($lookupState, 'data', []));
        $data['phone'] = PhoneNumber::normalize($data['phone']);
        $username = $this->makeUsername('company', $data['registration_number']);
        $document = $data['registration_document'];
        $documentPath = $this->storeRegistrationDocument($document, 'company');
        $logo = $data['logo'] ?? null;

        DB::transaction(function () use ($data, $roleAssignmentService, $username, $documentPath, $document, $logo): void {
            $group = Group::query()->where('code', 'organizations')->firstOrFail();

            $user = User::query()->create([
                'name' => $data['entity_name'],
                'username' => $username,
                'email' => $data['email'],
                'national_id' => null,
                'phone' => $data['phone'],
                'status' => 'pending_review',
                'registration_type' => 'company',
                'password' => Hash::make($data['password']),
            ]);

            $entity = Entity::query()->create([
                'group_id' => $group->getKey(),
                'name_en' => $data['entity_name'],
                'name_ar' => $data['entity_name'],
                'registration_no' => $data['registration_number'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'registration_type' => 'company',
                'status' => 'pending_review',
                'metadata' => array_filter(array_merge([
                    'address' => $data['address'],
                    'description' => $data['description'] ?: null,
                    'company_registration_date' => $data['company_registration_date'],
                    'company_capital' => $data['company_capital'],
                    'registration_document_path' => $documentPath,
                    'registration_document_name' => $document->getClientOriginalName(),
                    'registration_document_mime' => $document->getClientMimeType(),
                ], $this->logoMetadata($logo, 'company')), static fn ($value) => $value !== null && $value !== ''),
            ]);

            $entity->users()->attach($user->getKey(), [
                'is_primary' => true,
                'status' => 'active',
                'joined_at' => now(),
            ]);

            $roleAssignmentService->assignToEntity($user, $entity, 'applicant_owner');
        });

        $request->session()->forget(CompanyRegistrationLookupService::SESSION_KEY);

        return redirect()
            ->route('login')
            ->with('status', __('app.auth.account_under_review'));
    }

    private function storeOrganizationLike(
        Request $request,
        RoleAssignmentService $roleAssignmentService,
        string $registrationType,
    ): RedirectResponse {
        $data = $request->validate([
            'registration_type' => ['required', Rule::in(['company', 'ngo', 'school'])],
            'entity_name' => ['required', 'string', 'max:255'],
            'registration_number' => ['required', 'string', 'max:50', Rule::unique('entities', 'registration_no')],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'phone' => ['required', 'regex:/^\d{10}$/', Rule::unique('users', 'phone')],
            'address' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'registration_document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'logo' => $this->logoValidationRules(),
            'password' => array_merge(['required', 'confirmed'], $this->passwordRules()),
        ], array_merge([
            'phone.regex' => __('app.auth.phone_digits'),
        ], $this->logoValidationMessages()));

        $data['phone'] = PhoneNumber::normalize($data['phone']);
        $username = $this->makeUsername($registrationType, $data['registration_number']);
        $document = $data['registration_document'];
        $documentPath = $this->storeRegistrationDocument($document, $registrationType);
        $logo = $data['logo'] ?? null;

        DB::transaction(function () use ($data, $roleAssignmentService, $registrationType, $username, $documentPath, $document, $logo): void {
            $group = Group::query()->where('code', 'organizations')->firstOrFail();

            $user = User::query()->create([
                'name' => $data['entity_name'],
                'username' => $username,
                'email' => $data['email'],
                'national_id' => null,
                'phone' => $data['phone'],
                'status' => 'pending_review',
                'registration_type' => $registrationType,
                'password' => Hash::make($data['password']),
            ]);

            $entity = Entity::query()->create([
                'group_id' => $group->getKey(),
                'name_en' => $data['entity_name'],
                'name_ar' => $data['entity_name'],
                'registration_no' => $data['registration_number'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'registration_type' => $registrationType,
                'status' => 'pending_review',
                'metadata' => array_filter(array_merge([
                    'address' => $data['address'],
                    'description' => $data['description'] ?: null,
                    'registration_document_path' => $documentPath,
                    'registration_document_name' => $document->getClientOriginalName(),
                    'registration_document_mime' => $document->getClientMimeType(),
                ], $this->logoMetadata($logo, $registrationType)), static fn ($value) => $value !== null && $value !== ''),
            ]);

            $entity->users()->attach($user->getKey(), [
                'is_primary' => true,
                'status' => 'active',
                'joined_at' => now(),
            ]);

            $roleAssignmentService->assignToEntity($user, $entity, 'applicant_owner');
        });

        return redirect()
            ->route('login')
            ->with('status', __('app.auth.account_under_review'));
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function registrationTypes(): array
    {
        return [
            'student' => [
                'tab_id' => 'home-justify',
                'tab_link_id' => 'home-tab-justify',
                'label' => __('app.auth.register_tabs.student'),
                'submit_label' => __('app.auth.register_submit.student'),
            ],
            'company' => [
                'tab_id' => 'profile-justify',
                'tab_link_id' => 'profile-tab-justify',
                'label' => __('app.auth.register_tabs.company'),
                'submit_label' => __('app.auth.register_submit.company'),
            ],
            'ngo' => [
                'tab_id' => 'ngo-justify',
                'tab_link_id' => 'ngo-tab-justify',
                'label' => __('app.auth.register_tabs.ngo'),
                'submit_label' => __('app.auth.register_submit.ngo'),
            ],
            'school' => [
                'tab_id' => 'school-justify',
                'tab_link_id' => 'school-tab-justify',
                'label' => __('app.auth.register_tabs.school'),
                'submit_label' => __('app.auth.register_submit.school'),
            ],
        ];
    }

    private function makeUsername(string $registrationType, string $identifier): string
    {
        $normalizedIdentifier = Str::of(Str::lower($identifier))
            ->replaceMatches('/[^a-z0-9]+/', '-')
            ->trim('-')
            ->value();

        return Str::limit($registrationType.'-'.$normalizedIdentifier, 50, '');
    }

    private function storeRegistrationDocument(UploadedFile $document, string $registrationType): string
    {
        return $document->store('registration-documents/'.$registrationType, 'local');
    }

    /**
     * @return array<int, string>
     */
    private function logoValidationRules(): array
    {
        return ['nullable', 'file', 'image', 'mimes:png', 'mimetypes:image/png', 'max:2048'];
    }

    /**
     * @return array<string, string>
     */
    private function logoValidationMessages(): array
    {
        return [
            'logo.image' => __('app.auth.logo_png_only'),
            'logo.mimes' => __('app.auth.logo_png_only'),
            'logo.mimetypes' => __('app.auth.logo_png_only'),
            'logo.max' => __('app.auth.logo_max_size'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function logoMetadata(mixed $logo, string $registrationType): array
    {
        if (! $logo instanceof UploadedFile) {
            return [];
        }

        return [
            'logo_path' => $logo->store('registration-logos/'.$registrationType, 'local'),
            'logo_name' => $logo->getClientOriginalName(),
            'logo_mime' => $logo->getClientMimeType(),
            'logo_size' => $logo->getSize(),
        ];
    }

    /**
     * @return array<int, \Illuminate\Contracts\Validation\Rule|string>
     */
    private function passwordRules(): array
    {
        return [
            Password::min(8)->mixedCase()->numbers()->symbols(),
        ];
    }
}
