<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Data referensi roles + permissions. Idempoten, aman dijalankan di produksi:
 * php artisan db:seed --class=RolePermissionSeeder --force
 */
class RolePermissionSeeder extends Seeder
{
    public const PERMISSIONS = [
        'add_member',
        'remove_member',
        'add_ai',
        'change_password',
        'set_approval',
        'export_chat',
        'recover_history',
        'manage_billing',
        'pin_message',
        'delete_message',
        'view_audit_log',
    ];

    public const ROLE_PERMISSIONS = [
        'owner' => self::PERMISSIONS,
        'admin' => ['add_member', 'remove_member', 'export_chat', 'pin_message', 'delete_message', 'view_audit_log'],
        'member' => ['export_chat'],
    ];

    public function run(): void
    {
        $permissionIds = [];

        foreach (self::PERMISSIONS as $key) {
            $permissionIds[$key] = Permission::firstOrCreate(['key' => $key], [
                'name' => str($key)->replace('_', ' ')->title(),
                'description' => 'Permission '.$key,
            ])->id;
        }

        foreach (self::ROLE_PERMISSIONS as $roleKey => $keys) {
            Role::forKey($roleKey)->permissions()->syncWithoutDetaching(
                array_map(fn ($key) => $permissionIds[$key], $keys)
            );
        }
    }
}
