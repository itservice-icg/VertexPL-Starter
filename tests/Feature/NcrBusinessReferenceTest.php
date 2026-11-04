<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Department;
use App\Models\NcrReport;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\NcrFoundationSeeder;
use Database\Seeders\NcrReferenceDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class NcrBusinessReferenceTest extends TestCase
{
    use RefreshDatabase;

    private Department $department;
    private Product $product;
    private Customer $customer;
    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(NcrFoundationSeeder::class);
        $this->department = Department::create(['code' => 'QA', 'name_th' => 'ประกันคุณภาพ', 'is_active' => true]);
        $this->product = Product::create(['code' => 'P-001', 'name_th' => 'สินค้าเดิม', 'is_active' => true]);
        $this->customer = Customer::create(['code' => 'C-001', 'name_th' => 'ลูกค้าเดิม', 'is_active' => true]);
        $this->supplier = Supplier::create(['code' => 'S-001', 'name_th' => 'ผู้ขายเดิม', 'is_active' => true]);
    }

    public function test_reference_selection_writes_snapshots_and_keeps_them_when_master_names_change(): void
    {
        $reporter = $this->user(['ncr.create', 'ncr.update']);
        $payload = $this->payload();

        $this->actingAs($reporter)->post(route('ncr.store'), $payload)
            ->assertRedirect()->assertSessionHasNoErrors();

        $report = NcrReport::firstOrFail();
        $item = $report->items()->firstOrFail();
        $this->assertSame('P-001', $item->product_code);
        $this->assertSame('สินค้าเดิม', $item->product_name);
        $this->assertSame('ลูกค้าเดิม', $item->customer_name);
        $this->assertSame('ผู้ขายเดิม', $report->supplier_name);
        $this->assertSame((int) $this->product->id, (int) $item->product_id);
        $this->assertSame((int) $this->customer->id, (int) $item->customer_id);
        $this->assertSame((int) $this->supplier->id, (int) $report->supplier_id);
        $this->actingAs($reporter)->get(route('ncr.edit', $report))
            ->assertOk()
            ->assertSee('P-001')
            ->assertSee('สินค้าเดิม')
            ->assertSee('name="reference_customer_name"', false)
            ->assertSee('ผู้ขายเดิม');

        $this->product->update(['name_th' => 'สินค้าเปลี่ยนชื่อ']);
        $this->customer->update(['name_th' => 'ลูกค้าเปลี่ยนชื่อ']);
        $this->supplier->update(['name_th' => 'ผู้ขายเปลี่ยนชื่อ']);

        $update = $this->payload();
        $update['version_no'] = $report->version_no;
        $update['supplier_id'] = $this->supplier->id;
        $update['items'][0]['item_id'] = $item->id;
        $update['items'][0]['product_id'] = $this->product->id;
        $update['items'][0]['customer_id'] = $this->customer->id;

        $this->actingAs($reporter)->put(route('ncr.update', $report), $update)
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame($item->id, $report->fresh()->items()->firstOrFail()->id);
        $this->assertSame('สินค้าเดิม', $report->fresh()->items()->firstOrFail()->product_name);
        $this->assertSame('ลูกค้าเดิม', $report->fresh()->items()->firstOrFail()->customer_name);
        $this->assertSame('ผู้ขายเดิม', $report->fresh()->supplier_name);

        $replacementProduct = Product::create(['code' => 'P-002', 'name_th' => 'สินค้าใหม่', 'is_active' => true]);
        $replacementCustomer = Customer::create(['code' => 'C-002', 'name_th' => 'ลูกค้าใหม่', 'is_active' => true]);
        $replacementSupplier = Supplier::create(['code' => 'S-002', 'name_th' => 'ผู้ขายใหม่', 'is_active' => true]);
        $update['version_no'] = $report->fresh()->version_no;
        $update['supplier_id'] = $replacementSupplier->id;
        $update['items'][0]['product_id'] = $replacementProduct->id;
        $update['items'][0]['customer_id'] = $replacementCustomer->id;

        $this->actingAs($reporter)->put(route('ncr.update', $report), $update)
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('P-002', $report->fresh()->items()->firstOrFail()->product_code);
        $this->assertSame('สินค้าใหม่', $report->fresh()->items()->firstOrFail()->product_name);
        $this->assertSame('ลูกค้าใหม่', $report->fresh()->items()->firstOrFail()->customer_name);
        $this->assertSame('ผู้ขายใหม่', $report->fresh()->supplier_name);
    }

    public function test_product_is_required_while_customer_and_supplier_are_optional(): void
    {
        $reporter = $this->user(['ncr.create']);
        $payload = $this->payload();
        unset($payload['supplier_id'], $payload['items'][0]['product_id']);

        $this->actingAs($reporter)->post(route('ncr.store'), $payload)
            ->assertSessionHasErrors('items.0.product_id');
        $this->assertDatabaseCount('ncr_reports', 0);

        $payload['items'][0]['product_id'] = $this->product->id;
        unset($payload['items'][0]['customer_id']);
        $this->actingAs($reporter)->post(route('ncr.store'), $payload)
            ->assertRedirect()->assertSessionHasNoErrors();
        $report = NcrReport::firstOrFail();
        $this->assertNull($report->supplier_id);
        $this->assertNull($report->items()->firstOrFail()->customer_id);
    }

    public function test_reference_masters_require_permission_and_cannot_be_deleted_while_used(): void
    {
        $reporter = $this->user(['ncr.create']);
        $this->actingAs($reporter)->post(route('ncr.store'), $this->payload())->assertRedirect();

        $unauthorized = $this->user([]);
        $this->actingAs($unauthorized)->get(route('ncr-masters.index', 'products'))->assertForbidden();

        $masterManager = $this->user(['ncr.master.manage']);
        $this->actingAs($masterManager)->get(route('ncr-masters.index', 'products'))->assertOk()->assertSee('สินค้า');
        $this->actingAs($masterManager)->post(route('ncr-masters.store', 'products'), [
            'code' => 'p-002_test', 'name_th' => 'สินค้าเพิ่มใหม่', 'is_active' => '1',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('products', ['code' => 'P-002_TEST', 'name_th' => 'สินค้าเพิ่มใหม่']);
        $this->actingAs($masterManager)->from(route('ncr-masters.create', 'products'))->post(route('ncr-masters.store', 'products'), [
            'code' => 'รหัส ไม่ถูกต้อง', 'name_th' => 'รายการไม่ผ่าน', 'is_active' => '1',
        ])->assertRedirect()->assertSessionHasErrors('code');
        $this->actingAs($masterManager)->get(route('ncr-masters.create', 'products'))
            ->assertOk()->assertSee('กรุณาตรวจสอบข้อมูลที่กรอก')->assertSee('รูปแบบรหัสไม่ถูกต้อง', false)->assertSee('P-001');
        $this->actingAs($masterManager)->delete(route('ncr-masters.destroy', ['products', $this->product->id]))
            ->assertRedirect()->assertSessionHas('error');
        $this->assertDatabaseHas('products', ['id' => $this->product->id, 'deleted_at' => null]);
    }

    public function test_demo_reference_seeder_adds_only_missing_records_and_is_repeatable(): void
    {
        $this->seed(NcrReferenceDemoSeeder::class);
        $this->seed(NcrReferenceDemoSeeder::class);

        $this->assertDatabaseHas('products', ['code' => 'DEMO-P001', 'name_th' => 'สินค้าทดลอง NCR']);
        $this->assertDatabaseHas('customers', ['code' => 'DEMO-C001', 'name_th' => 'ลูกค้าทดลอง NCR']);
        $this->assertDatabaseHas('suppliers', ['code' => 'DEMO-S001', 'name_th' => 'ผู้ขายทดลอง NCR']);
        $this->assertSame(1, Product::where('code', 'DEMO-P001')->count());
        $this->assertSame(1, Customer::where('code', 'DEMO-C001')->count());
        $this->assertSame(1, Supplier::where('code', 'DEMO-S001')->count());
    }

    private function payload(): array
    {
        return [
            'report_date' => '2026-10-14',
            'found_date' => '2026-10-14',
            'problem_type_id' => \App\Models\ProblemType::where('code', 'RAW_MATERIAL')->value('id'),
            'severity_level_id' => \App\Models\SeverityLevel::where('code', 'MEDIUM')->value('id'),
            'problem_description' => 'พบสินค้าที่ไม่เป็นไปตามข้อกำหนด',
            'supplier_id' => $this->supplier->id,
            'supplier_name' => 'ข้อมูลที่ส่งมาเองต้องไม่ทับ snapshot',
            'items' => [[
                'product_id' => $this->product->id,
                'product_code' => 'FORGED-CODE',
                'product_name' => 'ชื่อที่ส่งมาเอง',
                'customer_id' => $this->customer->id,
                'customer_name' => 'ลูกค้าที่ส่งมาเอง',
                'defect_quantity' => 1,
                'total_quantity' => 10,
                'defect_unit_name' => 'PCS',
                'total_unit_name' => 'PCS',
            ]],
        ];
    }

    private function user(array $permissions): User
    {
        $user = User::factory()->create(['department_id' => $this->department->id]);
        foreach ($permissions as $permission) $user->givePermissionTo(Permission::findOrCreate($permission));
        return $user;
    }
}
