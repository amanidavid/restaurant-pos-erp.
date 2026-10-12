<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReservationTable extends TenantInternalModel
{
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function diningTable(): BelongsTo
    {
        return $this->belongsTo(DiningTable::class);
    }
}
