<?php

namespace App\Services\Gsb;

class IndividualPersonalInfoLookupService
{
    public function __construct(
        private readonly CspdPersonalInfoService $cspd,
        private readonly PsdBasicInfoService $psd,
    ) {}

    /** @return array<string, mixed> */
    public function lookup(string $personalNumber, string $nationalityCategory): array
    {
        if ($nationalityCategory === 'jordanian') {
            return $this->cspd->lookup($personalNumber);
        }

        if (in_array($nationalityCategory, ['arab', 'foreign'], true)) {
            return $this->psd->lookup($personalNumber);
        }

        return ['ok' => false, 'error' => 'INVALID_NATIONALITY_CATEGORY'];
    }
}
