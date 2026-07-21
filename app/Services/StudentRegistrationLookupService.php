<?php

namespace App\Services;

use App\Services\Gsb\CspdPersonalInfoService;
use App\Services\Gsb\MoheUndergraduateStudentsService;
use Carbon\Carbon;
use Throwable;

class StudentRegistrationLookupService
{
    public const SESSION_KEY = 'student_registration_lookup';

    public function __construct(
        private readonly MoheUndergraduateStudentsService $moheUndergraduateStudentsService,
        private readonly CspdPersonalInfoService $cspdPersonalInfoService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function lookup(string $nationalId, string $birthDate): array
    {
        $nationalId = preg_replace('/\D+/', '', $nationalId) ?: '';
        $birthDate = $this->normalizeBirthDate($birthDate);

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

        if ($this->shouldUseLocalLookup()) {
            return [
                'ok' => true,
                'national_id' => $nationalId,
                'birth_date' => $birthDate,
                'data' => $this->localProfile($nationalId, $birthDate),
                'meta' => [
                    'source' => 'local_mock',
                ],
            ];
        }

        $data = $this->localProfile($nationalId, $birthDate);
        $sources = ['local_fallback'];

        if ($this->cspdPersonalInfoService->isRunnable()) {
            $personLookup = $this->cspdPersonalInfoService->lookup($nationalId);

            if (! ($personLookup['ok'] ?? false)) {
                return [
                    'ok' => false,
                    'error' => $personLookup['error'] ?? 'PERSON_LOOKUP_FAILED',
                    'technical_message' => $personLookup['technical_message'] ?? null,
                ];
            }

            $data = $this->mergeFilled($data, (array) ($personLookup['data'] ?? []));
            $sources[] = (string) data_get($personLookup, 'meta.source', 'gsb_cspd_personal_info_masked');
        }

        if ($this->moheUndergraduateStudentsService->isRunnable()) {
            $educationLookup = $this->moheUndergraduateStudentsService->lookupCurrentStudent($nationalId, $birthDate);

            if (! ($educationLookup['ok'] ?? false)) {
                return [
                    'ok' => false,
                    'error' => ($educationLookup['error'] ?? null) === 'NOT_FOUND'
                        ? 'STUDENT_NOT_FOUND'
                        : ($educationLookup['error'] ?? 'EDUCATION_LOOKUP_FAILED'),
                    'technical_message' => $educationLookup['technical_message'] ?? null,
                ];
            }

            $studentStatus = data_get($educationLookup, 'data.student_status')
                ?? data_get($educationLookup, 'meta.student_status');

            if (! $this->isCurrentlyStudying($studentStatus)) {
                return [
                    'ok' => false,
                    'error' => 'NOT_CURRENT_STUDENT',
                ];
            }

            $data = $this->mergeFilled($data, (array) ($educationLookup['data'] ?? []));
            $sources[] = (string) data_get($educationLookup, 'meta.source', 'gsb_mohe_undergraduate_last_semester');
        }

        return [
            'ok' => true,
            'national_id' => $nationalId,
            'birth_date' => $birthDate,
            'data' => $data,
            'meta' => [
                'source' => implode('+', array_values(array_unique($sources))),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $lookupState
     */
    public function lookupStateMatches(?array $lookupState, string $nationalId, string $birthDate): bool
    {
        $matches = (bool) ($lookupState['ok'] ?? false)
            && ($lookupState['national_id'] ?? null) === trim($nationalId)
            && ($lookupState['birth_date'] ?? null) === $this->normalizeBirthDate($birthDate)
            && is_array($lookupState['data'] ?? null);

        if (! $matches) {
            return false;
        }

        $source = (string) data_get($lookupState, 'meta.source', '');

        return ! str_contains($source, 'gsb_mohe_undergraduate')
            || $this->isCurrentlyStudying(data_get($lookupState, 'data.student_status'));
    }

    /**
     * @param  array<string, mixed>  $lookup
     * @return array<string, mixed>
     */
    public function sessionStateFromLookup(array $lookup): array
    {
        return [
            'ok' => (bool) ($lookup['ok'] ?? false),
            'national_id' => $lookup['national_id'] ?? null,
            'birth_date' => $lookup['birth_date'] ?? data_get($lookup, 'data.birth_date'),
            'data' => (array) ($lookup['data'] ?? []),
            'meta' => (array) ($lookup['meta'] ?? []),
            'verified_at' => now()->toDateTimeString(),
        ];
    }

    /**
     * @return array{full_name:string,birth_date:string,gender:string,nationality:string,university_name:string,major:string}
     */
    private function localProfile(string $nationalId, string $birthDate): array
    {
        $suffix = substr($nationalId, -4);
        $isFemale = ((int) substr($nationalId, -1)) % 2 === 0;

        return [
            'full_name' => 'RFC Student '.$suffix,
            'birth_date' => $birthDate,
            'gender' => $isFemale ? 'female' : 'male',
            'nationality' => 'Jordanian',
            'university_name' => 'University of Jordan',
            'major' => 'Film Studies',
        ];
    }

    public function normalizeBirthDate(string $value): ?string
    {
        $value = trim($value);

        foreach (['Y-m-d', 'd/m/Y'] as $format) {
            try {
                $date = Carbon::createFromFormat($format, $value);

                if ($date !== false && $date->format($format) === $value) {
                    return $date->toDateString();
                }
            } catch (Throwable) {
                // Try the next supported display format.
            }
        }

        return null;
    }

    private function isCurrentlyStudying(mixed $status): bool
    {
        $status = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', (string) $status) ?? '';
        $status = preg_replace('/\s+/u', ' ', trim($status)) ?? '';

        return in_array($status, ['على مقاعد الدراسة', 'منتظم'], true);
    }

    private function shouldUseLocalLookup(): bool
    {
        return ! $this->cspdPersonalInfoService->isRunnable()
            && ! $this->moheUndergraduateStudentsService->isRunnable();
    }

    /**
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $override
     * @return array<string, mixed>
     */
    private function mergeFilled(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if (filled($value)) {
                $base[$key] = $value;
            }
        }

        return $base;
    }
}
