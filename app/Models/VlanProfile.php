<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VlanProfile extends Model
{
    protected $fillable = [
        'olt_id',
        'profile_name',
        'profile_id',
        'vlan_data',
        'vlan_count',
        'profile_type',
        'last_updated',
    ];

    protected $casts = [
        'vlan_data' => 'array',
        'last_updated' => 'datetime',
    ];

    public function olt()
    {
        return $this->belongsTo(Olt::class);
    }
}
