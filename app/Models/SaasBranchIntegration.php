<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaasBranchIntegration extends Model
{
    protected $fillable = ['cliente_id', 'provider', 'external_branch_id', 'branch_name', 'active', 'last_synced_at'];

    protected $casts = ['active' => 'boolean', 'last_synced_at' => 'datetime'];

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }
}
