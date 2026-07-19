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

    private ?string $customMessage = null;

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
        $attribute = (string) $attribute;

        if (preg_match('/^location_support_requirements\.([^\.]+)\.notes$/', $attribute, $matches)) {
            $requirement = (string) data_get(
                $this->data,
                "location_support_requirements.{$matches[1]}.requirement",
                ''
            );
        } elseif (preg_match('/^filming_locations\.([^\.]+)\.support_requirements\.([^\.]+)\.notes$/', $attribute, $matches)) {
            $requirement = (string) data_get(
                $this->data,
                "filming_locations.{$matches[1]}.support_requirements.{$matches[2]}.requirement",
                ''
            );
        } else {
            return true;
        }

        if (! filled($requirement)) {
            return true;
        }

        $option = FormLookupOption::query()
            ->ofType(FormLookupOption::TYPE_SPECIAL_LOCATION_REQUIREMENT)
            ->where('code', $requirement)
            ->first();
        $this->requirementLabel = $option?->displayName()
            ?? FormLookupOption::labelFor(FormLookupOption::TYPE_SPECIAL_LOCATION_REQUIREMENT, $requirement);
        $this->customMessage = $option?->notesPrompt();

        return filled($value);
    }

    public function message(): string
    {
        if (filled($this->customMessage)) {
            return (string) $this->customMessage;
        }

        return __('app.applications.location_support_notes_prompt', [
            'requirement' => $this->requirementLabel,
        ]);
    }
}
