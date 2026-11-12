<?php

namespace App\Services\Gsb;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;

class StandardPersonalInfoService
{
    public function __construct(
        private readonly GsbClient $client,
        private readonly PersonalInfoRecordNormalizer $normalizer,
    ) {}

    public function enabled(string $category): bool
    {
        return (bool) config('services.gsb.services.'.$this->service($category).'.enabled', false);
    }

    /** @return array<string, mixed> */
    public function lookup(string $identifier, string $category, ?string $birthDate, ?string $civilNumber = null): array
    {
        $jordanian = $category === 'jordanian';
        $service = $this->service($category);
        $identifier = trim($identifier);
        $civilNumber = trim((string) $civilNumber);
        if (preg_match('/^[0-9]{6}$/', $civilNumber)) {
            $civilNumber = substr($civilNumber, 0, 3).'/'.substr($civilNumber, 3);
        }

        if (! preg_match($jordanian ? '/^\d{10}$/' : '/^\d{1,20}$/', $identifier)) {
            return $this->failure($jordanian ? 'INVALID_NATIONAL_ID' : 'INVALID_INDIVIDUAL_NUMBER');
        }
        if (Validator::make(['birth_date' => $birthDate], ['birth_date' => ['required', 'date_format:Y-m-d', 'before:today']])->fails()) {
            return $this->failure('INVALID_BIRTH_DATE');
        }
        if ($jordanian && ! preg_match('/^[0-9]{3}\/[0-9]{3}$/', $civilNumber)) {
            return $this->failure('INVALID_CIVIL_NUMBER');
        }
        if (! $this->client->isEnabled($service) || ! $this->client->hasPath($service) || ! $this->client->credentialsConfigured($service)) {
            return $this->failure('STANDARD_LOOKUP_FAILED');
        }

        // Each request must validate the complete set of provider-required fields.
        // Do not reuse identifier-only caches or fall back to token/masked operations.
        $payload = $jordanian
            ? ['NAT_NO' => $identifier, 'BIRTHDT' => CarbonImmutable::createFromFormat('!Y-m-d', $birthDate)->format('n/j/Y'), 'CIV_NO' => $civilNumber]
            : ['nationalNo' => $identifier, 'BIRTH_DATE' => $birthDate];
        $response = $this->client->request($service, $payload, 'POST');
        $httpStatus = (int) ($response['status'] ?? 0);
        $json = $response['json'] ?? null;
        $providerStatus = is_array($json) ? ($json['status'] ?? $json['code'] ?? null) : null;
        $logicalStatus = is_numeric($providerStatus) ? (int) $providerStatus : null;

        if (in_array($httpStatus, [401, 403], true) || in_array($logicalStatus, [401, 403], true)) {
            return $this->failure('AUTHORIZATION_REQUIRED', $httpStatus);
        }
        if (in_array($httpStatus, [400, 404], true) || in_array($logicalStatus, [400, 404], true)) {
            return $this->failure('NOT_FOUND', $httpStatus);
        }
        if (! ($response['ok'] ?? false) || ! is_array($json)
            || $providerStatus === false || $providerStatus === 'error'
            || ($logicalStatus !== null && $logicalStatus >= 400)) {
            return $this->failure('STANDARD_LOOKUP_FAILED', $httpStatus);
        }
        $record = $this->normalizer->firstRecord($json);
        if (! $record) {
            return $this->failure('NOT_FOUND', $httpStatus);
        }
        $data = $this->normalizer->normalize($record);
        if (! $jordanian) {
            // The foreign crew form requires the name in English as in the passport.
            $fields = array_change_key_case($record, CASE_UPPER);
            $englishParts = [];
            foreach (['first_name' => 'E_FIRST_NAME', 'father_name' => 'E_FATHER_NAME', 'grandfather_name' => 'E_GRAND_FNAME', 'family_name' => 'E_FAMILY_NAME'] as $target => $key) {
                $part = trim((string) ($fields[$key] ?? ''));
                if ($part !== '' && $part !== '_') {
                    $data[$target] = $part;
                    $englishParts[] = $part;
                }
            }
            $englishName = trim((string) ($fields['E_FULL_NAME'] ?? ''));
            if ($englishName !== '' && $englishName !== '_') {
                $data['full_name'] = $englishName;
            } elseif ($englishParts !== []) {
                $data['full_name'] = implode(' ', $englishParts);
            }
        }
        if (blank($data['full_name'] ?? null)) {
            return $this->failure('NOT_FOUND', $httpStatus);
        }
        if ($jordanian && (! isset($record['NAT_NO']) || ! hash_equals($identifier, trim((string) $record['NAT_NO'])))) {
            return $this->failure('IDENTITY_NOT_CONFIRMED', $httpStatus);
        }
        if (filled($data['birth_date'] ?? null) && $data['birth_date'] !== $birthDate) {
            return $this->failure('IDENTITY_NOT_CONFIRMED', $httpStatus);
        }

        return [
            'ok' => true,
            'data' => $data,
            'meta' => ['source' => 'gsb_'.$service, 'status' => $httpStatus],
        ];
    }

    private function service(string $category): string
    {
        return $category === 'jordanian' ? 'cspd_personal_info_standard' : 'psd_personal_standard';
    }

    /** @return array<string, mixed> */
    private function failure(string $error, ?int $status = null): array
    {
        return ['ok' => false, 'error' => $error, 'status' => $status];
    }
}
