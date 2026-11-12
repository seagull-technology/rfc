<?php

namespace App\Services\Gsb;

class IndividualPersonalInfoLookupService
{
    public function __construct(
        private readonly CspdPersonalInfoService $cspd,
        private readonly PsdBasicInfoService $psd,
        private readonly StandardPersonalInfoService $standard,
    ) {}

    /** @return array<string, mixed> */
    public function lookup(string $personalNumber, string $nationalityCategory, ?string $birthDate = null, ?string $civilNumber = null): array
    {
        if (in_array($nationalityCategory, ['jordanian', 'arab', 'foreign', 'travel_document'], true)
            && $this->standard->enabled($nationalityCategory)) {
            return $this->standard->lookup($personalNumber, $nationalityCategory, $birthDate, $civilNumber);
        }

        if ($nationalityCategory === 'jordanian') {
            return $this->cspd->lookup($personalNumber);
        }

        if (in_array($nationalityCategory, ['arab', 'foreign', 'travel_document'], true)) {
            return $this->psd->lookup($personalNumber);
        }

        return ['ok' => false, 'error' => 'INVALID_NATIONALITY_CATEGORY'];
    }
}
