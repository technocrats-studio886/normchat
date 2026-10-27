<?php

namespace App\Services\Credits;

use App\Models\AiModel;
use App\Models\GroupToken;
use App\Services\NormkreditPricing;

/**
 * Kategori AI yang dikunci saldo grup (mis. Advanced), supaya grup baru tidak langsung habis.
 *
 * Ambang dalam kelipatan modal pembuatan grup: kategori terbuka saat saldo mencapai
 * unlock_multiple × modal, lalu tetap terbuka sampai saldo turun di bawah lock_multiple × modal.
 * Selama terbuka, permintaan kategori itu tidak boleh memakai saldo di bawah batas tutup,
 * jadi modal grup tetap tersisa untuk kategori lain.
 */
class ModelAccess
{
    public function __construct(private readonly NormkreditPricing $pricing)
    {
    }

    public function openAt(AiModel $model): float
    {
        return round((float) $model->unlock_multiple * $this->pricing->groupCreationCredits(), 2);
    }

    /** Saldo yang tidak boleh dipakai kategori ini; 0 untuk kategori yang tidak dikunci. */
    public function floor(AiModel $model): float
    {
        return $model->isGated() ? round((float) $model->lock_multiple * $this->pricing->groupCreationCredits(), 2) : 0.0;
    }

    public function isUnlocked(GroupToken $token, AiModel $model): bool
    {
        if (! $model->isGated()) {
            return true;
        }

        $balance = (float) $token->remaining_credits;
        if ($balance >= $this->openAt($model)) {
            return true;
        }

        return $balance >= $this->floor($model)
            && in_array($model->model_id, (array) $token->unlocked_models, true);
    }

    /** Catat kategori yang terbuka/terkunci setelah saldo berubah. Dipanggil CreditWallet. */
    public function sync(GroupToken $token): void
    {
        $unlocked = AiModel::query()
            ->where('unlock_multiple', '>', 0)
            ->get()
            ->filter(fn (AiModel $model) => $this->isUnlocked($token, $model))
            ->pluck('model_id')
            ->values()
            ->all();

        if ($unlocked !== array_values((array) $token->unlocked_models)) {
            $token->update(['unlocked_models' => $unlocked]);
        }
    }

    public function lockedMessage(AiModel $model, GroupToken $token): string
    {
        return $model->label . ' terkunci. ' . $model->label . ' terbuka saat saldo grup mencapai '
            . $this->format($this->openAt($model)) . ' Normkredit (sekarang ' . $this->format((float) $token->remaining_credits)
            . '). Pakai model lain atau isi Normkredit.';
    }

    public function floorMessage(AiModel $model, float $balance): string
    {
        return 'Saldo grup tinggal ' . $this->format($balance) . ' Normkredit. ' . $model->label
            . ' hanya boleh memakai saldo di atas ' . $this->format($this->floor($model))
            . ' Normkredit (modal grup), dan itu belum cukup untuk permintaan ini. Pakai model lain atau isi Normkredit.';
    }

    /** 500.0 → "500", 412.5 → "412,5". */
    public function format(float $credits): string
    {
        return rtrim(rtrim(number_format($credits, 2, ',', '.'), '0'), ',');
    }
}
