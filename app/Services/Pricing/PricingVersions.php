<?php

namespace App\Services\Pricing;

use App\Models\AiModel;
use App\Models\PricingVersion;
use App\Services\NormkreditPricing;
use Illuminate\Support\Facades\Cache;

/**
 * Riwayat harga: setiap kali superadmin mengubah pengaturan harga atau model AI, pengaturan saat itu
 * disalin menjadi versi baru. Hold, ledger, dan log pemakaian menyimpan id versinya, jadi transaksi
 * lama tetap bisa diaudit dengan tarif yang berlaku saat itu.
 */
class PricingVersions
{
    private const CACHE_KEY = 'normchat:pricing-version-id';

    public function __construct(private readonly NormkreditPricing $pricing)
    {
    }

    /** Versi yang berlaku sekarang; versi pertama dibuat dari pengaturan saat ini bila belum ada. */
    public function currentId(): int
    {
        return (int) Cache::rememberForever(self::CACHE_KEY, function () {
            return PricingVersion::query()->latest('id')->value('id')
                ?? $this->record(null, 'Versi awal dari pengaturan yang berlaku')->id;
        });
    }

    public function record(?int $userId, string $note): PricingVersion
    {
        $version = PricingVersion::create([
            'effective_from' => now(),
            'settings' => $this->settingsSnapshot(),
            'models' => $this->modelsSnapshot(),
            'created_by' => $userId,
            'note' => $note,
        ]);
        Cache::forget(self::CACHE_KEY);

        return $version;
    }

    /** Perbedaan versi ini dengan versi sebelumnya, untuk riwayat di halaman superadmin. */
    public function changes(PricingVersion $version): array
    {
        $previous = PricingVersion::query()->where('id', '<', $version->id)->latest('id')->first();
        if (! $previous) {
            return [];
        }

        $changes = [];
        foreach ($version->settings as $key => $value) {
            if (is_scalar($value) && ($previous->settings[$key] ?? null) != $value) {
                $changes[] = [$key, $previous->settings[$key] ?? null, $value];
            }
        }
        foreach ($version->models as $modelId => $fields) {
            foreach ($fields as $key => $value) {
                $before = $previous->models[$modelId][$key] ?? null;
                if ($before != $value) {
                    $changes[] = [$modelId . ' · ' . $key, $before, $value];
                }
            }
        }

        return $changes;
    }

    private function settingsSnapshot(): array
    {
        return [
            ...$this->pricing->settings(),
            'image_models' => (array) config('normchat.image_models'),
            'transcription_model' => (array) config('normchat.transcription_model'),
            'web_search_usd_per_call' => (float) config('normchat.web_search.usd_per_call'),
            'embedding' => (array) config('normchat.embedding'),
        ];
    }

    private function modelsSnapshot(): array
    {
        return AiModel::query()->orderBy('sort_order')->orderBy('id')->get()
            ->mapWithKeys(fn (AiModel $model) => [$model->model_id => [
                'label' => $model->label,
                'input_usd_per_mtok' => $model->input_usd_per_mtok,
                'cached_input_usd_per_mtok' => $model->cached_input_usd_per_mtok,
                'output_usd_per_mtok' => $model->output_usd_per_mtok,
                'multiplier' => $model->multiplier,
                'unlock_multiple' => $model->unlock_multiple,
                'lock_multiple' => $model->lock_multiple,
                'is_active' => $model->is_active,
                'image_model' => $model->image_model,
                'image_model_precise' => $model->image_model_precise,
            ]])
            ->all();
    }
}
