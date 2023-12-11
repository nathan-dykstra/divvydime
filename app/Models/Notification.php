<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    /**
     * Defines the Notification to NotificationAttribute relationship.
     */
    public function attributes()
    {
        return $this->hasOne(NotificationAttribute::class);
    }

    protected $fillable = [
        'notification_type_id',
        'creator', // User who triggered the notification
        'sender', // User who sent the notification
        'recipient', // User who received the notification

    ];

    protected $casts = [
        'notification_type_id' => 'int',
        'creator' => 'int',
        'sender' => 'int',
        'recipient' => 'int',
    ];
}
