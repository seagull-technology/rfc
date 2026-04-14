<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\OrganizationRegistrationLookupService;
use App\Services\SmsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IntegrationDiagnosticsController extends Controller
{
    public function __construct(
        private readonly SmsService $smsService,
        private readonly OrganizationRegistrationLookupService $lookupService,
    ) {
    }

    public function index(Request $request): View
    {
        return view('admin.integrations.index', [
            'smsConfig' => [
                'base' => (string) config('services.gov_sms.base'),
                'username_configured' => filled(config('services.gov_sms.username')),
                'password_configured' => filled(config('services.gov_sms.password')),
                'header' => (string) config('services.gov_sms.header'),
                'message_type_id' => (string) config('services.gov_sms.message_type_id'),
            ],
            'companyRegistryConfig' => [
                'enabled' => $this->lookupService->isEnabled(),
                'host' => (string) config('services.gov_company_registry.host'),
                'path' => (string) config('services.gov_company_registry.path'),
                'client_id_configured' => filled(config('services.gov_company_registry.client_id')),
                'client_secret_configured' => filled(config('services.gov_company_registry.client_secret')),
                'basic_auth_configured' => filled(config('services.gov_company_registry.basic_user'))
                    && filled(config('services.gov_company_registry.basic_pass')),
            ],
            'results' => [
                'sms' => $request->session()->get('diagnostics.sms'),
                'company_registry' => $request->session()->get('diagnostics.company_registry'),
            ],
        ]);
    }

    public function sendSmsTest(Request $request): RedirectResponse
    {
        $payload = $request->validate([
            'phone' => ['required', 'string', 'max:30'],
            'message' => ['nullable', 'string', 'max:500'],
        ]);

        $message = trim((string) ($payload['message'] ?? ''));
        if ($message === '') {
            $message = 'RFC integration test message.';
        }

        $response = $this->smsService->send($message, $payload['phone']);

        return redirect()
            ->route('admin.integrations.index')
            ->with('diagnostics.sms', [
                'ok' => (bool) ($response['ok'] ?? false),
                'stage' => $response['stage'] ?? null,
                'http' => $response['http'] ?? null,
                'msisdn' => $response['msisdn'] ?? null,
            ]);
    }

    public function lookupCompanyRegistry(Request $request): RedirectResponse
    {
        $payload = $request->validate([
            'organization_national_id' => ['required', 'string', 'max:50'],
            'organization_registration_no' => ['nullable', 'string', 'max:50'],
        ]);

        $result = $this->lookupService->lookup(
            $payload['organization_national_id'],
            $payload['organization_registration_no'] ?? null,
        );

        return redirect()
            ->route('admin.integrations.index')
            ->with('diagnostics.company_registry', $result);
    }
}
