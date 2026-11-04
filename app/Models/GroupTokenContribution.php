<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroupTokenContribution extends Model
{
    /** Bonus 20 NK dari promo buat grup (dicabut 4 Nov 2026; baris lama tetap ada). Bukan pendapatan. */
    public const SOURCE_BONUS = 'group_creation_bonus';

    /** Saldo awal grup gratis (App\Services\FreeGroups). Tanpa pembayaran; bukan pendapatan. */
    public const SOURCE_FREE_GROUP = 'free_group';

    /** Sumber Normkredit pemberian Normi Chat (harga 0), dipisahkan dari pendapatan di Keuangan. */
    public const GIFT_SOURCES = [self::SOURCE_FREE_GROUP, self::SOURCE_BONUS];

    /** Nama jenis transaksi untuk halaman superadmin. */
    public const SOURCE_LABELS = [
        'admin_adjustment' => 'Penyesuaian admin',
        'topup' => 'Isi Normkredit',
        'topup_midtrans' => 'Isi Normkredit',
        'interdotz_topup' => 'Isi Normkredit',
        'interdotz_charge_topup' => 'Isi Normkredit',
        'patungan' => 'Patungan',
        'patungan_midtrans' => 'Patungan',
        'group_creation' => 'Buat grup',
        'group_creation_midtrans' => 'Buat grup',
        self::SOURCE_BONUS => 'Bonus buat grup',
        self::SOURCE_FREE_GROUP => 'Saldo awal gratis',
    ];

    protected $fillable = [
        'group_id',
        'user_id',
        'source',
        'credit_amount',
        'price_paid',
        'payment_reference',
        'note',
    ];

    protected $casts = [
        'credit_amount' => 'float',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
