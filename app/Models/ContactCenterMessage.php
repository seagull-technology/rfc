<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ContactCenterMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'created_by_user_id',
        'entity_id',
        'recipient_scope',
        'sender_name',
        'title',
        'message_type',
        'message',
        'attachment_path',
        'attachment_name',
        'attachment_mime_type',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    public function localizedMessageType(): string
    {
        return __('app.contact_center.message_types.'.Str::lower($this->message_type ?: 'general_notice'));
    }

    public function localizedRecipientScope(): string
    {
        return __('app.contact_center.recipient_scopes.'.Str::lower($this->recipient_scope ?: 'all'));
    }
}
