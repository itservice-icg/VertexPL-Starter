<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\DepartmentRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class NcrReporterUsersSeeder extends Seeder
{
    private const ACCOUNTS = [
        'eng01' => ['department' => 'MOCK-NCR-ENG', 'name' => 'ผู้รายงานวิศวกรรม 01'],
        'eng02' => ['department' => 'MOCK-NCR-ENG', 'name' => 'ผู้รายงานวิศวกรรม 02'],
        'eng03' => ['department' => 'MOCK-NCR-ENG', 'name' => 'ผู้รายงานวิศวกรรม 03'],
        'eng04' => ['department' => 'MOCK-NCR-ENG', 'name' => 'ผู้รายงานวิศวกรรม 04'],
        'eng05' => ['department' => 'MOCK-NCR-ENG', 'name' => 'ผู้รายงานวิศวกรรม 05'],
        'prc01' => ['department' => 'MOCK-NCR-PRC', 'name' => 'ผู้รายงานจัดซื้อ 01'],
        'prc02' => ['department' => 'MOCK-NCR-PRC', 'name' => 'ผู้รายงานจัดซื้อ 02'],
        'prc03' => ['department' => 'MOCK-NCR-PRC', 'name' => 'ผู้รายงานจัดซื้อ 03'],
        'prc04' => ['department' => 'MOCK-NCR-PRC', 'name' => 'ผู้รายงานจัดซื้อ 04'],
        'prc05' => ['department' => 'MOCK-NCR-PRC', 'name' => 'ผู้รายงานจัดซื้อ 05'],
        'wh01' => ['department' => 'MOCK-NCR-WH', 'name' => 'ผู้รายงานคลังสินค้า 01'],
        'wh02' => ['department' => 'MOCK-NCR-WH', 'name' => 'ผู้รายงานคลังสินค้า 02'],
        'wh03' => ['department' => 'MOCK-NCR-WH', 'name' => 'ผู้รายงานคลังสินค้า 03'],
        'wh04' => ['department' => 'MOCK-NCR-WH', 'name' => 'ผู้รายงานคลังสินค้า 04'],
        'wh05' => ['department' => 'MOCK-NCR-WH', 'name' => 'ผู้รายงานคลังสินค้า 05'],
    ];

    private const REQUIRED_PERMISSIONS = [
        'dashboard.view',
        'ncr.view',
        'ncr.create',
        'ncr.update',
        'ncr.submit',
    ];

    public function run(): void
    {
        $password = env('VERTEXPL_ADMIN_PASSWORD');
        if (! is_string($password) || $password === '') {
            throw new RuntimeException('Set VERTEXPL_ADMIN_PASSWORD before running NcrReporterUsersSeeder.');
        }

        Validator::make(
            ['password' => $password],
            ['password' => ['required', Password::min(14)->letters()->mixedCase()->numbers()->symbols()]]
        )->validate();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $role = Role::query()->where('name', 'NCR Reporter')->where('guard_name', 'web')->first();
        if (! $role) {
            throw new RuntimeException('Required role is missing: NCR Reporter. Run NcrFoundationSeeder first.');
        }

        $missingPermissions = array_diff(self::REQUIRED_PERMISSIONS, $role->permissions()->pluck('name')->all());
        if ($missingPermissions) {
            throw new RuntimeException('NCR Reporter role is missing required permissions: '.implode(', ', $missingPermissions));
        }

        $memberRoleExists = DepartmentRole::query()->where('code', 'MEMBER')->exists();
        if (! $memberRoleExists) {
            throw new RuntimeException('Required department role is missing: MEMBER. Run the department role migrations first.');
        }

        $departments = [];
        foreach (array_unique(array_column(self::ACCOUNTS, 'department')) as $code) {
            $department = Department::query()->where('code', $code)->where('is_active', true)->first();
            if (! $department) {
                throw new RuntimeException("Required active department is missing: {$code}. Run NcrMockupSeeder first.");
            }
            $departments[$code] = $department;
        }

        $emails = array_map(fn (string $username) => $username.'@mock.vertexpl.local', array_keys(self::ACCOUNTS));
        $existingEmails = User::withTrashed()->whereIn('email', $emails)->pluck('email')->all();
        if ($existingEmails) {
            throw new RuntimeException('Refusing to overwrite existing accounts: '.implode(', ', $existingEmails));
        }

        DB::transaction(function () use ($departments, $role, $password): void {
            foreach (self::ACCOUNTS as $username => $definition) {
                $user = User::create([
                    'name' => $definition['name'],
                    'email' => $username.'@mock.vertexpl.local',
                    'department_id' => $departments[$definition['department']]->id,
                    'department_role' => 'MEMBER',
                    'password' => Hash::make($password),
                    'email_verified_at' => now(),
                    'is_active' => true,
                ]);

                $user->syncRoles([$role]);
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->command->info('Created 15 NCR Reporter mock users: 5 each for engineering, purchasing, and warehouse.');
    }
}
