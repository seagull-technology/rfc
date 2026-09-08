<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

trait ProtectsRegistrationIdentity
{
    public static function bootProtectsRegistrationIdentity(): void
    {
        static::updating(function (Model $model): void {
            foreach (['national_id', 'registration_no'] as $field) {
                if ($model->isImmutableRegistrationIdentityField($field) && $model->isDirty($field)) {
                    throw ValidationException::withMessages([
                        $field => __('app.auth.registration_identity_locked'),
                    ]);
                }
            }
        });
    }
}
