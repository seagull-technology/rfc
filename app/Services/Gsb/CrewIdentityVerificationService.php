<?php

namespace App\Services\Gsb;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Throwable;

class CrewIdentityVerificationService
{
    public const STATUS_VERIFIED = 'verified';

    public const STATUS_PENDING = 'pending';

    public const STATUS_MANUAL = 'manual';

    public const STATUS_UNVERIFIED = 'unverified';

    public function __construct(
        private readonly IndividualPersonalInfoLookupService $lookupService,
    ) {}

    /** @return array<string, mixed> */
    public function lookup(string $identifier, string $category, int $userId): array
    {
        $identifier = preg_replace('/\D+/', '', $identifier) ?: '';
        $result = $this->lookupService->lookup($identifier, $category);

        if ($result['ok'] ?? false) {
            $verifiedAt = now()->toIso8601String();
            $identity = Arr::only((array) ($result['data'] ?? []), [
                'full_name',
                'first_name',
                'father_name',
                'grandfather_name',
                'family_name',
                'birth_date',
                'gender',
                'nationality',
            ]);
            $source = (string) data_get($result, 'meta.source', 'government_registry');

            return [
                'ok' => true,
                'status' => self::STATUS_VERIFIED,
                'data' => $identity,
                'source' => $source,
                'verified_at' => $verifiedAt,
                'proof' => $this->encryptProof([
                    'user_id' => $userId,
                    'category' => $category,
                    'identifier' => $identifier,
                    'status' => self::STATUS_VERIFIED,
                    'source' => $source,
                    'verified_at' => $verifiedAt,
                    'identity' => $identity,
                ]),
            ];
        }

        $error = (string) ($result['error'] ?? 'LOOKUP_FAILED');

        if ($this->isDefinitiveFailure($error)) {
            return [
                'ok' => false,
                'status' => self::STATUS_UNVERIFIED,
                'error' => $error,
            ];
        }

        $checkedAt = now()->toIso8601String();

        return [
            'ok' => true,
            'status' => self::STATUS_PENDING,
            'source' => $category === 'jordanian' ? 'gsb_cspd' : 'gsb_psd',
            'verified_at' => $checkedAt,
            'proof' => $this->encryptProof([
                'user_id' => $userId,
                'category' => $category,
                'identifier' => $identifier,
                'status' => self::STATUS_PENDING,
                'source' => $category === 'jordanian' ? 'gsb_cspd' : 'gsb_psd',
                'verified_at' => $checkedAt,
                'identity' => [],
            ]),
        ];
    }

    /** @return array<string, mixed>|null */
    public function consumeProof(?string $proof, string $category, string $identifier, int $userId): ?array
    {
        if (blank($proof)) {
            return null;
        }

        try {
            $payload = json_decode(Crypt::decryptString($proof), true, 512, JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException) {
            return null;
        }

        $identifier = preg_replace('/\D+/', '', $identifier) ?: '';
        try {
            $issuedAt = Carbon::parse((string) ($payload['issued_at'] ?? ''));
        } catch (Throwable) {
            return null;
        }
        $expiresAt = $issuedAt->copy()->addMinutes((int) config('services.gsb.crew_verification_minutes', 120));

        if ((int) ($payload['user_id'] ?? 0) !== $userId
            || (string) ($payload['category'] ?? '') !== $category
            || (string) ($payload['identifier'] ?? '') !== $identifier
            || now()->greaterThan($expiresAt)) {
            return null;
        }

        return Arr::only($payload, [
            'category',
            'identifier',
            'status',
            'source',
            'verified_at',
            'identity',
        ]);
    }

    /** @param array<string, mixed> $payload */
    private function encryptProof(array $payload): string
    {
        return Crypt::encryptString(json_encode([
            ...$payload,
            'issued_at' => now()->toIso8601String(),
        ], JSON_THROW_ON_ERROR));
    }

    private function isDefinitiveFailure(string $error): bool
    {
        return in_array($error, [
            'INVALID_NATIONAL_ID',
            'INVALID_INDIVIDUAL_NUMBER',
            'INVALID_NATIONALITY_CATEGORY',
            'NOT_FOUND',
        ], true);
    }
}
