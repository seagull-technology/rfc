<?php

namespace App\Services\Gsb;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Throwable;

class MoheUndergraduateStudentsService
{
    private const SERVICE = 'mohe_undergraduate_students';

    public function __construct(
        private readonly GsbClient $client,
    ) {}

    public function isRunnable(): bool
    {
        return $this->client->isEnabled(self::SERVICE)
            && $this->client->hasPath(self::SERVICE)
            && $this->client->credentialsConfigured();
    }

    /**
     * @return array<string, mixed>
     */
    public function lookupCurrentStudent(string $nationalId, string $birthDate): array
    {
        $nationalId = preg_replace('/\D+/', '', $nationalId) ?: '';
        $birthDate = $this->inputDate($birthDate);

        if (! preg_match('/^\d{10}$/', $nationalId)) {
            return [
                'ok' => false,
                'error' => 'INVALID_NATIONAL_ID',
            ];
        }

        if ($birthDate === null) {
            return [
                'ok' => false,
                'error' => 'INVALID_BIRTH_DATE',
            ];
        }

        if (! $this->isRunnable()) {
            return [
                'ok' => false,
                'error' => 'SERVICE_DISABLED',
            ];
        }

        return Cache::remember(
            sprintf('gsb:mohe_undergraduate:last_semester:%s:%s', $nationalId, $birthDate),
            now()->addMinutes((int) config('services.gsb.cache_minutes', 10)),
            fn (): array => $this->performLookup($nationalId, $birthDate),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function performLookup(string $nationalId, string $birthDate): array
    {
        $response = $this->client->request(self::SERVICE, [
            'nationalNo' => $nationalId,
            'birthDate' => $birthDate,
        ]);

        if (! ($response['ok'] ?? false) || ! is_array($response['json'] ?? null)) {
            $status = $response['status'] ?? null;

            return [
                'ok' => false,
                'error' => $status === 404 ? 'NOT_FOUND' : ($response['error'] ?? 'LOOKUP_FAILED'),
                'status' => $status,
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

        $normalizedRecord = $this->normalizeRecord($record);

        if (($normalizedRecord['birth_date'] ?? null) !== $birthDate) {
            return [
                'ok' => false,
                'error' => 'IDENTITY_MISMATCH',
                'status' => $response['status'] ?? null,
            ];
        }

        return [
            'ok' => true,
            'national_id' => $nationalId,
            'birth_date' => $birthDate,
            'data' => $normalizedRecord,
            'meta' => [
                'source' => 'gsb_mohe_undergraduate_last_semester',
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
        foreach (['data', 'result', 'records'] as $key) {
            $records = data_get($json, $key);

            if (! is_array($records)) {
                continue;
            }

            if ($this->hasStudentSignal($records)) {
                return $records;
            }

            foreach ($records as $record) {
                if (is_array($record) && $this->isCurrentlyStudying(
                    data_get($record, 'student_status'),
                    data_get($record, 'STATUS_CODE'),
                )) {
                    return $record;
                }
            }

            if (is_array($records[0] ?? null)) {
                return $records[0];
            }
        }

        if ($this->hasStudentSignal($json)) {
            return $json;
        }

        if (is_array($json[0] ?? null)) {
            return $json[0];
        }

        return null;
    }

    private function isCurrentlyStudying(mixed $status, mixed $statusCode = null): bool
    {
        $status = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', (string) $status) ?? '';
        $status = preg_replace('/\s+/u', ' ', trim($status)) ?? '';

        return in_array($status, ['على مقاعد الدراسة', 'منتظم'], true)
            || ((string) $statusCode === '1' && $status !== 'خريج');
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
            'major' => $this->clean(data_get($record, 'major')) ?: $this->clean(data_get($record, 'S_MAJOR_NAME')),
            'degree' => $this->clean(data_get($record, 'degree')),
            'student_status' => $this->clean(data_get($record, 'student_status')),
            'student_phone' => $this->localPhone(data_get($record, 'STUDENT_PHONE')),
            'student_id' => $this->clean(data_get($record, 'STUDENT_ID')),
            'university_type' => $this->clean(data_get($record, 'UNIVERSITY_TYPE_NAME')),
            'university_governorate' => $this->clean(data_get($record, 'INSTITUTE_GOVERNORATE')),
            'city' => $this->clean(data_get($record, 'CITY_NAME')),
        ];
    }

    private function inputDate(string $value): ?string
    {
        $value = trim($value);

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            $date = Carbon::createFromFormat('Y-m-d', $value);

            return $date !== false && $date->format('Y-m-d') === $value
                ? $date->toDateString()
                : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function localPhone(mixed $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value) ?: '';

        if (preg_match('/^07\d{8}$/', $digits)) {
            return $digits;
        }

        if (preg_match('/^7\d{8}$/', $digits)) {
            return '0'.$digits;
        }

        if (preg_match('/^9627\d{8}$/', $digits)) {
            return '0'.substr($digits, 3);
        }

        return null;
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
