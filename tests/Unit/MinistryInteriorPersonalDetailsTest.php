<?php

namespace Tests\Unit;

use App\Support\MinistryInteriorPersonalDetails;
use PHPUnit\Framework\TestCase;

class MinistryInteriorPersonalDetailsTest extends TestCase
{
    public function test_rendered_helper_values_do_not_start_an_optional_personal_details_record(): void
    {
        $this->assertFalse(MinistryInteriorPersonalDetails::hasSubmittedData([
            'current_full_name' => 'Applicant Name',
            'attachments' => [
                ['document_type' => 'passport_copy'],
                ['document_type' => 'foreign_residence'],
            ],
        ]));
    }

    public function test_real_personal_data_starts_the_personal_details_record(): void
    {
        $this->assertTrue(MinistryInteriorPersonalDetails::hasSubmittedData([
            'current_full_name' => 'Applicant Name',
            'attachments' => [
                ['document_type' => 'passport_copy'],
            ],
            'first_name' => 'Ahmad',
        ]));
    }

    public function test_a_real_stored_attachment_starts_the_personal_details_record(): void
    {
        $this->assertTrue(MinistryInteriorPersonalDetails::hasSubmittedData([
            'attachments' => [
                [
                    'id' => 'passport-copy-1',
                    'document_type' => 'passport_copy',
                    'path' => 'annexes/passport-copy.jpg',
                ],
            ],
        ]));
    }

    public function test_a_stored_attachment_path_starts_the_personal_details_record(): void
    {
        $this->assertTrue(MinistryInteriorPersonalDetails::hasSubmittedData([
            'attachments' => [
                [
                    'document_type' => 'passport_copy',
                    'path' => 'annexes/passport-copy.jpg',
                ],
            ],
        ]));
    }
}
