<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * NK yang ditahan untuk satu request AI. Setelah diselesaikan, shared_amount dan
 * protected_amount berisi pemakaian aktual dari masing-masing kantong.
 */
class CreditHold extends Model
{
    public const HELD = 'held';
    public const SETTLED = 'settled';
    public const RELEASED = 'released';

    protected $fillable = ['group_id', 'user_id', 'shared_amount', 'protected_amount', 'shared_first', 'status'];

    protected $casts = [
        'shared_amount' => 'float',
        'protected_amount' => 'float',
        'shared_first' => 'boolean',
    ];

    public function total(): float
    {
        return round($this->shared_amount + $this->protected_amount, 4);
    }
}
