<?php

namespace App\Services\Gsb;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Throwable;

class MoheSanadService
{
    public function __construct(
        private readonly GsbClient $client,
    ) {
    }

    public function isRunnable(): bool
    {
        return $this->client->isEnabled('mohe_sanad')
            && $this->client->hasPath('mohe_sanad')
            && $this->client->credentialsConfigured();
    }

    /**
     * @return array<string, mixed>
     */
    public function lookupCurrentStudent(string $nationalId): array
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
            sprintf('gsb:mohe_sanad:current:%s', $nationalId),
            now()->addMinutes((int) config('services.gsb.cache_minutes', 10)),
            fn (): array => $this->performCurrentStudentLookup($nationalId),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function performCurrentStudentLookup(string $nationalId): array
    {
        $response = $this->client->request('mohe_sanad', [
            'NationalNo' => $nationalId,
            'Check' => 1,
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

        if (! $record || ! $this->hasStudentSignal($record)) {
            return [
                'ok' => false,
                'error' => 'NOT_FOUND',
                'status' => $response['status'] ?? null,
            ];
        }

        return [
            'ok' => true,
            'national_id' => $nationalId,
            'data' => $this->normalizeRecord($record),
            'meta' => [
                'source' => 'gsb_mohe_sanad',
                'status' => $response['status'] ?? null,
                'student_status' => data_get($record, 'student_status'),
                'status_code' => data_get($record, 'STATUS_CODE'),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $json
     * @return array<string, mixed>|null
     */
    private function firstRecord(array $json): ?array
    {
        $records = data_get($json, 'data');

        if (is_array($records) && is_array($records[0] ?? null)) {
            return $records[0];
        }

        if (is_array($json[0] ?? null)) {
            return $json[0];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function hasStudentSignal(array $record): bool
    {
        foreach (['STUDENT_NAME', 'INSTITUTE_NAME', 'major', 'S_MAJOR_NAME', 'degree', 'STUDENT_ID'] as $key) {
            if (filled(data_get($record, $key))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    private function normalizeRecord(array $record): array
    {
        return [
            'full_name' => $this->clean(data_get($record, 'STUDENT_NAME')),
            'birth_date' => $this->date(data_get($record, 'BIRTH_DATE')),
            'gender' => $this->gender(data_get($record, 'gender_desc') ?: data_get($record, 'GENDER')),
            'nationality' => $this->clean(data_get($record, 'NATIONALITY')) ?: 'Jordanian',
            'university_name' => $this->clean(data_get($record, 'INSTITUTE_NAME')),
            'major' => $this->clean(data_get($record, 'S_MAJOR_NAME')) ?: $this->clean(data_get($record, 'major')),
            'degree' => $this->clean(data_get($record, 'degree')),
            'student_status' => $this->clean(data_get($record, 'student_status')),
            'student_phone' => $this->clean(data_get($record, 'STUDENT_PHONE')),
        ];
    }

    private function clean(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
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
