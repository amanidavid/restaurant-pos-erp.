<?php

namespace App\Models\Landlord;

use App\Modules\Shared\Database\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;

abstract class LandlordModel extends Model
{
    use HasPublicUuid;

    protected $connection = 'landlord';

    protected $guarded = ['id'];
}
