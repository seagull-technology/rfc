<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ApplicationCorrespondence extends Model
{
    use HasFactory;

    public const RECIPIENT_ALL = 'all';

    public const RECIPIENT_RFC = 'rfc';

    public const RECIPIENT_APPLICANT = 'applicant';

    protected $fillable = [
        'application_id',
        'created_by_user_id',
        'sender_type',
        'sender_name',
        'recipient_type',
        'subject',
        'message',
        'attachment_path',
        'attachment_name',
        'attachment_mime_type',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function localizedSenderType(): string
    {
        return __('app.correspondence.senders.'.Str::lower($this->sender_type ?: 'applicant'));
    }

    public function localizedRecipientType(): string
    {
        return __('app.correspondence.recipients.'.Str::lower($this->recipient_type ?: self::RECIPIENT_ALL));
    }

    public function scopeVisibleToRfc(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query
                ->where('sender_type', 'admin')
                ->orWhereNull('recipient_type')
                ->orWhereIn('recipient_type', [self::RECIPIENT_ALL, self::RECIPIENT_RFC]);
        });
    }

    public function scopeVisibleToApplicant(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query
                ->where('sender_type', 'applicant')
                ->orWhereNull('recipient_type')
                ->orWhereIn('recipient_type', [self::RECIPIENT_ALL, self::RECIPIENT_APPLICANT]);
        });
    }

    public function isVisibleToRfc(): bool
    {
        return $this->sender_type === 'admin'
            || blank($this->recipient_type)
            || in_array($this->recipient_type, [self::RECIPIENT_ALL, self::RECIPIENT_RFC], true);
    }

    public function isVisibleToApplicant(): bool
    {
        return $this->sender_type === 'applicant'
            || blank($this->recipient_type)
            || in_array($this->recipient_type, [self::RECIPIENT_ALL, self::RECIPIENT_APPLICANT], true);
    }
}
