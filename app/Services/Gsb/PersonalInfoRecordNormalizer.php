<?php

namespace App\Services\Gsb;

use Carbon\Carbon;
use Throwable;

class PersonalInfoRecordNormalizer
{
    /**
     * @param  array<string, mixed>  $json
     * @return array<string, mixed>|null
     */
    public function firstRecord(array $json): ?array
    {
        foreach (['data', 'Data', 'result', 'Result', 'person', 'Person', 'personInfo', 'PersonInfo', 'response', 'Response'] as $key) {
            $candidate = $json[$key] ?? null;

            if (! is_array($candidate)) {
                continue;
            }

            if (array_is_list($candidate)) {
                return is_array($candidate[0] ?? null) ? $candidate[0] : null;
            }

            return $this->firstRecord($candidate) ?? $candidate;
        }

        if (array_is_list($json)) {
            return is_array($json[0] ?? null) ? $json[0] : null;
        }

        return $json;
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    public function normalize(array $record): array
    {
        $values = $this->index($record);
        $firstName = $this->value($values, ['aname1', 'name1', 'firstname', 'arabicfirstname', 'firstnamearabic', 'arabicname1']);
        $fatherName = $this->value($values, ['aname2', 'name2', 'fathername', 'secondname', 'arabicsecondname', 'arabicname2']);
        $grandfatherName = $this->value($values, ['aname3', 'name3', 'grandfathername', 'thirdname', 'arabicthirdname', 'arabicname3']);
        $familyName = $this->value($values, ['aname4', 'name4', 'familyname', 'lastname', 'surname', 'arabicfamilyname', 'arabicname4']);
        $fullName = $this->value($values, [
            'fullname',
            'personname',
            'arabicname',
            'namearabic',
            'aname',
            'name',
        ]);

        if (! filled($fullName)) {
            $fullName = collect([$firstName, $fatherName, $grandfatherName, $familyName])
                ->filter(fn (?string $part): bool => filled($part))
                ->implode(' ');
        }

        return [
            'full_name' => $fullName ?: null,
            'first_name' => $firstName,
            'father_name' => $fatherName,
            'grandfather_name' => $grandfatherName,
            'family_name' => $familyName,
            'birth_date' => $this->date($this->value($values, ['birthdate', 'dateofbirth', 'dob', 'bdate', 'brthdate', 'birthday'])),
            'birth_place' => $this->value($values, ['birthplace', 'placeofbirth', 'placebirth', 'birthplacedesc', 'birthcity', 'birthcountry']),
            'gender' => $this->gender($this->value($values, ['genderdesc', 'genderdescription', 'gendercode', 'gender', 'sexdesc', 'sexcode', 'sex'])),
            'nationality' => $this->value($values, ['nationalityname', 'nationalitydesc', 'nationalitydescription', 'nationality', 'countryname']),
            'phone' => $this->value($values, ['mobilenumber', 'mobileno', 'mobile', 'cellphone', 'phonenumber', 'phoneno', 'phone', 'telno', 'telephone']),
            'email' => $this->value($values, ['emailaddress', 'email']),
            'address' => $this->value($values, ['fulladdress', 'residenceaddress', 'residenceaddressdesc', 'currentaddress', 'addressdesc', 'address']),
            'mother_full_name' => $this->value($values, ['motherfullname', 'mothernamearabic', 'mothername']),
            'mother_nationality' => $this->value($values, ['mothernationalityname', 'mothernationality']),
            'marital_status' => $this->maritalStatus($this->value($values, ['maritalstatusdesc', 'maritalstatusname', 'maritalstatus'])),
            'passport_number' => $this->value($values, ['passportnumber', 'passportno', 'passno', 'traveldocumentnumber', 'documentnumber']),
            'country_of_residence' => $this->value($values, ['residencecountryname', 'countryofresidence', 'residencecountry']),
        ];
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    private function index(array $record): array
    {
        $values = [];

        foreach ($record as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $values[$this->normalizeKey((string) $key)] = $value;
            }
        }

        return $values;
    }

    private function normalizeKey(string $key): string
    {
        return mb_strtolower((string) preg_replace('/[^A-Za-z0-9]+/', '', $key));
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  array<int, string>  $keys
     */
    private function value(array $values, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = trim((string) ($values[$this->normalizeKey($key)] ?? ''));

            if ($value !== '' && $value !== '_') {
                return $value;
            }
        }

        return null;
    }

    private function gender(?string $value): ?string
    {
        $normalized = mb_strtolower(trim((string) $value));

        if ($normalized === '') {
            return null;
        }

        if (in_array($normalized, ['2', 'f', 'female'], true) || str_contains($normalized, 'أنث') || str_contains($normalized, 'انث')) {
            return 'female';
        }

        if (in_array($normalized, ['1', 'm', 'male'], true) || str_contains($normalized, 'ذكر')) {
            return 'male';
        }

        return null;
    }

    private function maritalStatus(?string $value): ?string
    {
        $normalized = mb_strtolower(trim((string) $value));

        return match (true) {
            str_contains($normalized, 'single'), str_contains($normalized, 'أعزب'), str_contains($normalized, 'عزباء') => 'single',
            str_contains($normalized, 'married'), str_contains($normalized, 'متزوج') => 'married',
            str_contains($normalized, 'divorc'), str_contains($normalized, 'مطلق') => 'divorced',
            str_contains($normalized, 'widow'), str_contains($normalized, 'أرمل') => 'widowed',
            default => null,
        };
    }

    private function date(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $formats = preg_match('/^\d{1,2}-[A-Za-z]{3}-\d{2}$/', $value)
            ? ['d-M-y']
            : ['Y-m-d', 'd/m/Y', 'd-m-Y', 'd-M-Y'];

        foreach ($formats as $format) {
            try {
                $date = Carbon::createFromFormat($format, $value);

                if ($date !== false) {
                    return $date->toDateString();
                }
            } catch (Throwable) {
                // Try the next documented/provider date format.
            }
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }
}
