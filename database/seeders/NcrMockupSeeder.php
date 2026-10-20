<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class NcrMockupSeeder extends Seeder
{
    private const MOCK_EMAIL_DOMAIN = 'mock.vertexpl.local';

    private const RETIRED_EMAIL_LOCALS = [
        'plant.manager.approver01',
    ];

    private const DEPARTMENTS = [
        'PRODUCTION' => [
            'code' => 'MOCK-NCR-PROD',
            'name_th' => 'ฝ่ายผลิต',
            'name_en' => 'Production',
        ],
        'QUALITY' => [
            'code' => 'MOCK-NCR-QA',
            'name_th' => 'ฝ่ายควบคุมคุณภาพ / ประกันคุณภาพ',
            'name_en' => 'Quality Control / QA',
        ],
        'ENGINEERING' => [
            'code' => 'MOCK-NCR-ENG',
            'name_th' => 'ฝ่ายวิศวกรรม',
            'name_en' => 'Engineering',
        ],
        'WAREHOUSE' => [
            'code' => 'MOCK-NCR-WH',
            'name_th' => 'ฝ่ายคลังสินค้า',
            'name_en' => 'Warehouse',
        ],
        'PURCHASING' => [
            'code' => 'MOCK-NCR-PRC',
            'name_th' => 'ฝ่ายจัดซื้อ',
            'name_en' => 'Purchasing',
        ],
        'MANAGEMENT' => [
            'code' => 'MOCK-NCR-MGMT',
            'name_th' => 'ฝ่ายบริหาร',
            'name_en' => 'Management',
        ],
    ];

    private const USERS = [
        [
            'name' => 'ผจก.แผนกที่เปิด NCR',
            'email_local' => 'production.manager01',
            'department' => 'PRODUCTION',
            'roles' => ['NCR Initial Reviewer', 'Responsible Department'],
        ],
        [
            'name' => 'ผู้รายงาน',
            'email_local' => 'production.reporter01',
            'department' => 'PRODUCTION',
            'role' => 'NCR Reporter',
        ],
        [
            'name' => 'QA',
            'email_local' => 'qa.supervisor01',
            'department' => 'QUALITY',
            'role' => 'QA',
        ],
        [
            'name' => 'หน่วยงานรับผิดชอบ - วิศวกรรม',
            'email_local' => 'engineering.staff01',
            'department' => 'ENGINEERING',
            'role' => 'Responsible Department',
        ],
        [
            'name' => 'หน่วยงานรับผิดชอบ - คลังสินค้า',
            'email_local' => 'warehouse.staff01',
            'department' => 'WAREHOUSE',
            'role' => 'Responsible Department',
        ],
        [
            'name' => 'หน่วยงานรับผิดชอบ - จัดซื้อ',
            'email_local' => 'purchasing.coordinator01',
            'department' => 'PURCHASING',
            'role' => 'Responsible Department',
        ],
        [
            'name' => 'ผู้จัดการโรงงาน',
            'email_local' => 'plant.manager.approver01',
            'department' => 'MANAGEMENT',
            'role' => 'Plant Manager',
        ],
        [
            'name' => 'รองกรรมการผู้จัดการ',
            'email_local' => 'deputy.managing.director01',
            'department' => 'MANAGEMENT',
            'roles' => ['Plant Manager', 'Deputy Managing Director'],
        ],
        [
            'name' => 'กรรมการผู้จัดการ',
            'email_local' => 'plant.manager01',
            'department' => 'MANAGEMENT',
            'role' => 'Managing Director',
        ],
    ];

    private const ROLE_PERMISSIONS = [
        'NCR Reporter' => ['dashboard.view', 'ncr.view', 'ncr.create', 'ncr.update', 'ncr.submit'],
        'NCR Initial Reviewer' => ['dashboard.view', 'ncr.view', 'ncr.create', 'ncr.review', 'ncr.disposition.manage'],
        'QA' => ['dashboard.view', 'ncr.view', 'ncr.create', 'ncr.review', 'ncr.disposition.manage', 'ncr.action.verify', 'ncr.qa.followup', 'ncr.close', 'ncr.report'],
        'Responsible Department' => ['dashboard.view', 'ncr.view', 'ncr.create', 'ncr.action.manage'],
        'Plant Manager' => ['dashboard.view', 'ncr.view', 'ncr.create', 'ncr.disposition.approve', 'ncr.report'],
        'Deputy Managing Director' => ['dashboard.view', 'ncr.view', 'ncr.report'],
        'Managing Director' => ['dashboard.view', 'ncr.view', 'ncr.destruction.approve', 'ncr.report'],
    ];

    public function run(): void
    {
        $password = env('VERTEXPL_MOCK_PASSWORD');

        if (! is_string($password) || $password === '') {
            throw new RuntimeException('Set VERTEXPL_MOCK_PASSWORD before running NcrMockupSeeder.');
        }

        Validator::make(
            ['password' => $password],
            ['password' => ['required', Password::min(14)->letters()->mixedCase()->numbers()->symbols()]]
        )->validate();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        DB::transaction(function () use ($password): void {
            $roles = $this->resolveRolesAndVerifyPermissions();
            $departments = $this->seedDepartments();

            $this->retireObsoleteUsers();

            foreach (self::USERS as $definition) {
                if (in_array($definition['email_local'], self::RETIRED_EMAIL_LOCALS, true)) {
                    continue;
                }

                $this->seedUser($definition, $departments, $roles, $password);
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $activeUserCount = count(self::USERS) - count(self::RETIRED_EMAIL_LOCALS);
        $this->command->info('NCR mock organization seeded: '.count(self::DEPARTMENTS).' departments and '.$activeUserCount.' users.');
    }

    /**
     * Resolve roles only. This seeder intentionally never creates, syncs, or edits roles.
     *
     * @return array<string, Role>
     */
    private function resolveRolesAndVerifyPermissions(): array
    {
        $roles = [];

        foreach (self::ROLE_PERMISSIONS as $roleName => $requiredPermissions) {
            $role = Role::query()
                ->where('name', $roleName)
                ->where('guard_name', 'web')
                ->first();

            if (! $role) {
                throw new RuntimeException("Required NCR role is missing: {$roleName}. Run NcrFoundationSeeder first.");
            }

            $actualPermissions = $role->permissions()->pluck('name')->all();
            $missingPermissions = array_diff($requiredPermissions, $actualPermissions);

            if ($missingPermissions) {
                throw new RuntimeException(
                    "Role {$roleName} is missing required NCR permissions: ".implode(', ', $missingPermissions)
                );
            }

            $roles[$roleName] = $role;
        }

        return $roles;
    }

    /**
     * @return array<string, Department>
     */
    private function seedDepartments(): array
    {
        $departments = [];

        foreach (self::DEPARTMENTS as $key => $attributes) {
            $department = Department::withTrashed()->where('code', $attributes['code'])->first();

            if (! $department) {
                $department = Department::create($attributes + ['is_active' => true]);
            } else {
                $department->restore();
                $department->update($attributes + ['is_active' => true]);
            }

            $departments[$key] = $department->fresh();
        }

        return $departments;
    }

    /**
     * @param array<string, mixed> $definition
     * @param array<string, Department> $departments
     * @param array<string, Role> $roles
     */
    private function seedUser(array $definition, array $departments, array $roles, string $password): void
    {
        $email = $definition['email_local'].'@'.self::MOCK_EMAIL_DOMAIN;
        $user = User::withTrashed()->where('email', $email)->first();

        if ($user && ! str_ends_with((string) $user->email, '@'.self::MOCK_EMAIL_DOMAIN)) {
            throw new RuntimeException("Refusing to modify an existing non-mock user: {$email}");
        }

        if (! $user) {
            $user = new User;
        } else {
            $user->restore();
        }

        $user->forceFill([
            'name' => $definition['name'],
            'email' => $email,
            'department_id' => $departments[$definition['department']]->id,
            'password' => Hash::make($password),
            'email_verified_at' => now(),
            'is_active' => true,
        ])->save();

        // The role assignment is scoped to these mock accounts only.
        $roleNames = $definition['roles'] ?? [$definition['role']];
        $user->syncRoles(array_map(fn (string $roleName) => $roles[$roleName], $roleNames));
    }

    private function retireObsoleteUsers(): void
    {
        foreach (self::RETIRED_EMAIL_LOCALS as $emailLocal) {
            $user = User::withTrashed()
                ->where('email', $emailLocal.'@'.self::MOCK_EMAIL_DOMAIN)
                ->first();

            if (! $user) {
                continue;
            }

            $user->syncRoles([]);
            $user->forceFill(['is_active' => false])->save();
            $user->delete();
        }
    }
}
