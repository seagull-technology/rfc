<?php

namespace App\Services\Gsb;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Throwable;

class CspdPersonalInfoService
{
    public function __construct(
        private readonly GsbClient $client,
    ) {
    }

    public function isRunnable(): bool
    {
        return $this->client->isEnabled('cspd_personal_info_masked')
            && $this->client->hasPath('cspd_personal_info_masked')
            && $this->client->credentialsConfigured();
    }

    /**
     * @return array<string, mixed>
     */
    public function lookup(string $nationalId): array
    {
        $nationalId = preg_replace('/\D+/', '', $nationalId) ?: '';

        if (! preg_match('/^\d{10}$/', $nationalId)) {
            return [
                'ok' => false,
                'error' => 'INVALID_NATIONAL_ID',
            ];
        }

        if (! $this->isRunnable()) {
            return [
                'ok' => false,
                'error' => 'SERVICE_DISABLED',
            ];
        }

        return Cache::remember(
            sprintf('gsb:cspd_personal_info_masked:%s', $nationalId),
            now()->addMinutes((int) config('services.gsb.cache_minutes', 10)),
            fn (): array => $this->performLookup($nationalId),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function performLookup(string $nationalId): array
    {
        $response = $this->client->request('cspd_personal_info_masked', [
            'NationalNo' => $nationalId,
        ]);

        if (! ($response['ok'] ?? false) || ! is_array($response['json'] ?? null)) {
            return [
                'ok' => false,
                'error' => $response['error'] ?? 'LOOKUP_FAILED',
                'status' => $response['status'] ?? null,
                'technical_message' => $response['error'] ?? null,
            ];
        }

        $record = $this->firstRecord($response['json']);

        if (! $record) {
            return [
                'ok' => false,
                'error' => 'NOT_FOUND',
                'status' => $response['status'] ?? null,
            ];
        }

        $data = $this->normalizeRecord($record);

        if (! filled($data['full_name'] ?? null) && ! filled($data['birth_date'] ?? null)) {
            return [
                'ok' => false,
                'error' => 'NOT_FOUND',
                'status' => $response['status'] ?? null,
            ];
        }

        return [
            'ok' => true,
            'national_id' => $nationalId,
            'data' => $data,
            'meta' => [
                'source' => 'gsb_cspd_personal_info_masked',
                'status' => $response['status'] ?? null,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $json
     * @return array<string, mixed>|null
     */
    private function firstRecord(array $json): ?array
    {
        foreach (['data.0', 'Data.0', 'result.0', 'Result.0', 'person', 'Person'] as $path) {
            $candidate = data_get($json, $path);

            if (is_array($candidate)) {
                return $candidate;
            }
        }

        if (is_array($json[0] ?? null)) {
            return $json[0];
        }

        return $json;
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    private function normalizeRecord(array $record): array
    {
        return [
            'full_name' => $this->firstFilled($record, ['FullName', 'FULL_NAME', 'full_name', 'Name', 'NAME', 'ArabicName', 'ARABIC_NAME']),
            'birth_date' => $this->date($this->firstFilled($record, ['BirthDate', 'BIRTH_DATE', 'DateOfBirth', 'DOB', 'dob'])),
            'gender' => $this->gender($this->firstFilled($record, ['Gender', 'GENDER', 'gender_desc', 'GenderDesc'])),
            'nationality' => $this->firstFilled($record, ['Nationality', 'NATIONALITY']),
            'phone' => $this->firstFilled($record, ['Phone', 'PHONE', 'Mobile', 'MOBILE', 'phone']),
            'email' => $this->firstFilled($record, ['Email', 'EMAIL', 'email']),
            'address' => $this->firstFilled($record, ['Address', 'ADDRESS', 'address']),
        ];
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  array<int, string>  $keys
     */
    private function firstFilled(array $record, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = trim((string) data_get($record, $key));

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function gender(mixed $value): ?string
    {
        $value = mb_strtolower(trim((string) $value));

        if ($value === '') {
            return null;
        }

        if (str_contains($value, 'female') || str_contains($value, 'أنث') || str_contains($value, 'انث')) {
            return 'female';
        }

        if (str_contains($value, 'male') || str_contains($value, 'ذكر')) {
            return 'male';
        }

        return $value;
    }

    private function date(mixed $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (Throwable) {
            return $value;
        }
    }
}
