<?php

namespace App\Rules;

use App\Models\FormLookupOption;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ImplicitRule;

class SupportRequirementNotesRequired implements DataAwareRule, ImplicitRule
{
    /**
     * @var array<string, mixed>
     */
    private array $data = [];

    private string $requirementLabel = '';

    /**
     * @param  array<string, mixed>  $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function passes($attribute, $value): bool
    {
        if (! preg_match('/^filming_locations\.([^\.]+)\.support_requirements\.([^\.]+)\.notes$/', (string) $attribute, $matches)) {
            return true;
        }

        $requirement = (string) data_get(
            $this->data,
            "filming_locations.{$matches[1]}.support_requirements.{$matches[2]}.requirement",
            ''
        );

        if (! filled($requirement)) {
            return true;
        }

        $this->requirementLabel = FormLookupOption::labelFor(FormLookupOption::TYPE_SPECIAL_LOCATION_REQUIREMENT, $requirement);

        return filled($value);
    }

    public function message(): string
    {
        return __('app.applications.location_support_notes_prompt', [
            'requirement' => $this->requirementLabel,
        ]);
    }
}
