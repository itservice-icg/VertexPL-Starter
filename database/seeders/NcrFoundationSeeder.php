<?php

namespace Database\Seeders;

use App\Models\DispositionType;
use App\Models\NotificationRule;
use App\Models\NcrFormScope;
use App\Models\ProblemType;
use App\Models\RootCauseType;
use App\Models\SeverityLevel;
use App\Models\Unit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class NcrFoundationSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissionMap = [
            'NCR Reporter' => ['dashboard.view','ncr.view','ncr.create','ncr.update','ncr.submit'],
            'NCR Initial Reviewer' => ['dashboard.view','ncr.view','ncr.create','ncr.review','ncr.disposition.manage'],
            'Plant Manager' => ['dashboard.view','ncr.view','ncr.create','ncr.disposition.approve','ncr.report'],
            'Deputy Managing Director' => ['dashboard.view','ncr.view','ncr.report'],
            'Managing Director' => ['dashboard.view','ncr.view','ncr.destruction.approve','ncr.report'],
            'Responsible Department' => ['dashboard.view','ncr.view','ncr.create','ncr.action.manage'],
            'Action Verifier' => ['dashboard.view','ncr.view','ncr.create','ncr.action.verify'],
            'QA' => ['dashboard.view','ncr.view','ncr.create','ncr.review','ncr.disposition.manage','ncr.action.verify','ncr.qa.followup','ncr.close','ncr.report'],
        ];
        foreach (array_unique(array_merge(['ncr.master.manage', 'ncr.view.all'], ...array_values($permissionMap))) as $name) Permission::findOrCreate($name, 'web');
        foreach ($permissionMap as $roleName => $permissions) {
            /** @var Role $role */
            $role = Role::findOrCreate($roleName, 'web');
            $role->syncPermissions($permissions);
        }

        $this->seed(ProblemType::class, [
            ['RAW_MATERIAL','วัตถุดิบ/ส่วนผสม'], ['PACKAGING_SUPPLY','บรรจุภัณฑ์/Supply'], ['WIP','สินค้าระหว่างผลิต'],
            ['REPACK','สินค้า Repack'], ['FINISHED_GOODS','สินค้าสำเร็จรูป'], ['CUSTOMER_PRODUCT','ผลิตภัณฑ์ลูกค้า'],
        ]);
        $this->seed(SeverityLevel::class, [['LOW','ต่ำ'],['MEDIUM','ปานกลาง'],['HIGH','สูง'],['CRITICAL','วิกฤต']], true);
        $this->seed(DispositionType::class, [
            ['ACCEPT_AS_IS','ยอมรับใช้ตามสภาพ'],['HOLD_STATUS','ติดสถานะ'],['SEND_TO_RD','ส่งให้ RD'],['REWORK','แก้ไขงาน'],
            ['SUPPLIER_COORDINATION','ประสานผู้ผลิต'],['CUSTOMER_COORDINATION','ฝ่ายขาย / ประสานงานลูกค้า'],['CONDITIONAL_ACCEPTANCE','ยอมรับภายใต้เงื่อนไข'],
            ['RETURN_TO_SUPPLIER','RETURN / ส่งคืน / แลกเปลี่ยน / ลดหนี้ / ชดเชย'],['SCRAP','เสนอทำลาย'],['RECALL','Recall'],['OTHER','อื่น ๆ'],
        ]);
        $this->seed(RootCauseType::class, [['MAN','บุคลากร'],['MACHINE','เครื่องจักร'],['METHOD','วิธีการ'],['MATERIAL','วัตถุดิบ'],['MEASUREMENT','การวัด'],['ENVIRONMENT','สิ่งแวดล้อม']]);
        $this->seed(Unit::class, [['PCS','ชิ้น'],['KG','กิโลกรัม'],['G','กรัม'],['L','ลิตร'],['BOX','กล่อง'],['LOT','ล็อต']]);
        $this->seed(NcrFormScope::class, [['AI','AI'],['UP','UP']]);
        NotificationRule::firstOrCreate(['code' => 'NCR_SUBMITTED'], ['name_th' => 'แจ้งเตือนเมื่อส่ง NCR', 'event' => 'ncr.submitted', 'channels' => ['database'], 'is_active' => true]);

        $statuses = ['DRAFT','RETURNED_FOR_CORRECTION','INITIAL_REVIEW','PENDING_MANAGER_APPROVAL','RETURNED_FOR_DISPOSITION_REVISION','PENDING_DESTRUCTION_APPROVAL','DISPOSITION_APPROVED','ACTION_IN_PROGRESS','PENDING_ACTION_VERIFICATION','RETURNED_FOR_REWORK','QA_VERIFIED','CLOSED'];
        foreach ($statuses as $index => $code) DB::table('ncr_statuses')->updateOrInsert(['code' => $code], ['name_th' => $code, 'sort_order' => $index + 1, 'is_active' => true, 'updated_at' => now(), 'created_at' => now()]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function seed(string $model, array $items, bool $ordered = false): void
    {
        foreach ($items as $index => [$code,$name]) $model::firstOrCreate(['code' => $code], ['name_th' => $name, 'is_active' => true] + ($ordered ? ['sort_order' => $index + 1] : []));
    }
}
