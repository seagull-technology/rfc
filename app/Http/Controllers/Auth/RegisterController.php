<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Entity;
use App\Models\Group;
use App\Models\User;
use App\Services\RoleAssignmentService;
use App\Support\PhoneNumber;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
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

    public function store(Request $request, RoleAssignmentService $roleAssignmentService): RedirectResponse
    {
        $request->validate([
            'registration_type' => ['required', Rule::in(array_keys($this->registrationTypes()))],
        ]);

        $registrationType = (string) $request->input('registration_type');

        if ($registrationType === 'student') {
            return $this->storeStudent($request, $roleAssignmentService);
        }

        return $this->storeOrganizationLike($request, $roleAssignmentService, $registrationType);
    }

    private function storeStudent(Request $request, RoleAssignmentService $roleAssignmentService): RedirectResponse
    {
        $data = $request->validate([
            'registration_type' => ['required', 'in:student'],
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'national_id' => ['required', 'string', 'max:50', Rule::unique('users', 'national_id'), Rule::unique('entities', 'national_id')],
            'phone' => ['required', 'string', 'max:30', Rule::unique('users', 'phone')],
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        $data['phone'] = PhoneNumber::normalize($data['phone']);
        $username = $this->makeUsername('student', $data['national_id']);

        DB::transaction(function () use ($data, $roleAssignmentService, $username): void {
            $group = Group::query()->where('code', 'individuals')->firstOrFail();

            $user = User::query()->create([
                'name' => $data['full_name'],
                'username' => $username,
                'email' => $data['email'],
                'national_id' => $data['national_id'],
                'phone' => $data['phone'],
                'status' => 'active',
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
                'status' => 'active',
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
            ->with('status', __('app.auth.account_created'));
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
            'phone' => ['required', 'string', 'max:30', Rule::unique('users', 'phone')],
            'address' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'registration_document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        $data['phone'] = PhoneNumber::normalize($data['phone']);
        $username = $this->makeUsername($registrationType, $data['registration_number']);
        $document = $data['registration_document'];
        $documentPath = $this->storeRegistrationDocument($document, $registrationType);

        DB::transaction(function () use ($data, $roleAssignmentService, $registrationType, $username, $documentPath, $document): void {
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
                'metadata' => array_filter([
                    'address' => $data['address'],
                    'description' => $data['description'] ?: null,
                    'registration_document_path' => $documentPath,
                    'registration_document_name' => $document->getClientOriginalName(),
                    'registration_document_mime' => $document->getClientMimeType(),
                ], static fn ($value) => $value !== null && $value !== ''),
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
            ->with('status', __('app.auth.organization_account_created'));
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
}
