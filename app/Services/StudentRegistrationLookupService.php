<?php

namespace App\Services;

class StudentRegistrationLookupService
{
    public const SESSION_KEY = 'student_registration_lookup';

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

        return [
            'ok' => true,
            'national_id' => $nationalId,
            'data' => $this->localProfile($nationalId),
            'meta' => [
                'source' => 'local_mock',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $lookupState
     */
    public function lookupStateMatches(?array $lookupState, string $nationalId): bool
    {
        return (bool) ($lookupState['ok'] ?? false)
            && ($lookupState['national_id'] ?? null) === trim($nationalId)
            && is_array($lookupState['data'] ?? null);
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
            'data' => (array) ($lookup['data'] ?? []),
            'meta' => (array) ($lookup['meta'] ?? []),
            'verified_at' => now()->toDateTimeString(),
        ];
    }

    /**
     * @return array{full_name:string,birth_date:string,gender:string,nationality:string,university_name:string,major:string}
     */
    private function localProfile(string $nationalId): array
    {
        $suffix = substr($nationalId, -4);
        $year = 1998 + ((int) substr($nationalId, -2) % 8);
        $month = ((int) substr($nationalId, 2, 2) % 12) + 1;
        $day = ((int) substr($nationalId, 4, 2) % 28) + 1;
        $isFemale = ((int) substr($nationalId, -1)) % 2 === 0;

        return [
            'full_name' => 'RFC Student '.$suffix,
            'birth_date' => sprintf('%04d-%02d-%02d', $year, $month, $day),
            'gender' => $isFemale ? 'female' : 'male',
            'nationality' => 'Jordanian',
            'university_name' => 'University of Jordan',
            'major' => 'Film Studies',
        ];
    }
}
