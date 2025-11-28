<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryTransaction extends Model
{
    use HasFactory;

    // PASTIKAN TULISANNYA BENAR: guarded (bukan fillable)
    protected $guarded = ['id'];

    protected $casts = [
        'date' => 'date',
        'qty_input' => 'float',
        'price_input' => 'float',
        'qty' => 'float',
        'cost' => 'float',
        'total_cost' => 'float',
        'qty_balance' => 'float',
        'value_balance' => 'float',
        'hpp' => 'float',
    ];
}
