<?php

namespace App\Models\Tenant;

use App\Modules\Shared\Database\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory;
    use HasPublicUuid;
    use HasRoles;
    use Notifiable;

    // Authentication identities are stored centrally in the landlord database.
    // Tenant-specific profile, branch, role, and payroll data remains tenant-local.
    protected $connection = 'landlord';

    protected $guard_name = 'tenant_api';

    protected $guarded = ['id'];

    protected $hidden = [
        'password',
        'remember_token',
        'token_version',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'immutable_datetime',
            'phone_verified_at' => 'immutable_datetime',
            'password_changed_at' => 'immutable_datetime',
            'last_login_at' => 'immutable_datetime',
            'password' => 'hashed',
        ];
    }

    public function tenantUser(): HasOne
    {
        return $this->hasOne(TenantUser::class, 'landlord_user_uuid', 'uuid');
    }

    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    /**
     * @return array<string, mixed>
     */
    public function getJWTCustomClaims(): array
    {
        return [
            'user_uuid' => $this->uuid,
            'token_version' => $this->token_version,
        ];
    }
}
