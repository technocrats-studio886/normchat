<?php

namespace App\Support;

use App\Models\AuditLog;

/** Kalimat untuk tindakan superadmin di log audit (action "admin.*"). */
final class AdminActions
{
    public static function label(AuditLog $log): string
    {
        $data = (array) $log->metadata_json;
        $nk = fn ($value) => number_format(abs((float) $value), 2, ',', '.');

        return match ($log->action) {
            'admin.adjust_credits' => ((float) ($data['amount'] ?? 0) >= 0 ? 'Menambah ' : 'Mengurangi ') . $nk($data['amount'] ?? 0) . ' NK grup ' . ($data['group'] ?? '') . ': ' . ($data['reason'] ?? ''),
            'admin.freeze_group' => 'Membekukan grup ' . ($data['name'] ?? ''),
            'admin.unfreeze_group' => 'Mengaktifkan kembali grup ' . ($data['name'] ?? ''),
            'admin.delete_group' => 'Menghapus grup ' . ($data['name'] ?? ''),
            'admin.block_user' => 'Memblokir ' . ($data['email'] ?? 'user'),
            'admin.unblock_user' => 'Membuka blokir ' . ($data['email'] ?? 'user'),
            'admin.delete_user' => 'Menghapus akun ' . ($data['email'] ?? ''),
            'admin.restore_user' => 'Memulihkan akun ' . ($data['email'] ?? ''),
            'admin.export_csv' => 'Mengekspor CSV ' . ($data['type'] ?? ''),
            'admin.update_pricing', 'superadmin.update_pricing' => 'Mengubah harga Normkredit',
            'admin.create_ai_model', 'superadmin.create_ai_model' => 'Menambah model AI ' . ($data['model_id'] ?? ''),
            'admin.update_ai_model', 'superadmin.update_ai_model' => 'Mengubah model AI ' . ($data['model_id'] ?? ''),
            'admin.set_exchange_rate' => 'Mengisi kurs USD→IDR ' . number_format((float) ($data['market_rate'] ?? 0), 2, ',', '.'),
            'admin.set_openai_budget' => 'Mengatur anggaran OpenAI bulanan',
            'admin.set_openai_balance' => 'Mengisi saldo OpenAI',
            'admin.announcement_saved' => 'Menyimpan pengumuman "' . ($data['title'] ?? '') . '"',
            'admin.announcement_published' => 'Menayangkan pengumuman "' . ($data['title'] ?? '') . '"',
            'admin.announcement_stopped' => 'Menghentikan pengumuman "' . ($data['title'] ?? '') . '"',
            'admin.announcement_deleted' => 'Menghapus pengumuman "' . ($data['title'] ?? '') . '"',
            'admin.update_promo' => 'Mengubah promo bonus buat grup',
            'admin.update_free_groups' => 'Mengubah pengaturan grup gratis',
            default => str_replace(['superadmin.', 'admin.', '_'], ['', '', ' '], $log->action),
        };
    }
}
