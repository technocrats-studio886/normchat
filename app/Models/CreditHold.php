<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * NK yang ditahan untuk satu request AI. Setelah diselesaikan, amount berisi
 * pemakaian aktualnya.
 */
class CreditHold extends Model
{
    public const HELD = 'held';
    public const SETTLED = 'settled';
    public const RELEASED = 'released';

    protected $fillable = ['group_id', 'user_id', 'amount', 'status'];

    protected $casts = [
        'amount' => 'float',
    ];
}
