<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailLog extends Model
{
    protected $fillable = [
        'emailable_type', 'emailable_id', 'recipient', 'subject', 'message_id', 'headers', 'error', 'status', 'sent_at'
    ];

    protected $dates = ['sent_at', 'created_at', 'updated_at'];

    public function emailable()
    {
        return $this->morphTo();
    }
}
