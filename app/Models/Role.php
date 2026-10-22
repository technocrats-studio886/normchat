<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    public const OWNER = 'owner';
    public const ADMIN = 'admin';
    public const MEMBER = 'member';

    private const DEFAULTS = [
        self::OWNER => ['Owner', 'Group owner'],
        self::ADMIN => ['Admin', 'Group administrator'],
        self::MEMBER => ['Member', 'Group member'],
    ];

    protected $fillable = ['key', 'name', 'description'];

    /** Ambil role bawaan (owner/admin/member), buat bila belum ada. */
    public static function forKey(string $key): self
    {
        [$name, $description] = self::DEFAULTS[$key];

        return static::firstOrCreate(['key' => $key], ['name' => $name, 'description' => $description]);
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions');
    }
}
