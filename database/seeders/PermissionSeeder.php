<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\PermissionKey;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * بذر مفاتيح الصلاحيات (docs/SPEC.md §2). آمن للتكرار.
 */
class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (PermissionKey::cases() as $key) {
            Permission::findOrCreate($key->value, 'web');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
