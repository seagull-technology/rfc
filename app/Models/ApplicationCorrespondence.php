<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ApplicationCorrespondence extends Model
{
    use HasFactory;

    protected $fillable = [
        'application_id',
        'created_by_user_id',
        'sender_type',
        'sender_name',
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
}
