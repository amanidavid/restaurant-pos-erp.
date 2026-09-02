<?php

namespace App\Models\Landlord;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class PlatformUser extends LandlordModel
{
    use HasRoles;
    use Notifiable;

    protected $guard_name = 'admin';

    protected $hidden = [
        'token_version',
    ];

    protected function casts(): array
    {
        return [
            'last_accessed_at' => 'immutable_datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
