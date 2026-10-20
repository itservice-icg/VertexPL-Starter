<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Database\Seeder;

class NcrReferenceDemoSeeder extends Seeder
{
    public function run(): void
    {
        $records = [
            [Product::class, 'DEMO-P001', 'สินค้าทดลอง NCR'],
            [Customer::class, 'DEMO-C001', 'ลูกค้าทดลอง NCR'],
            [Supplier::class, 'DEMO-S001', 'ผู้ขายทดลอง NCR'],
        ];

        foreach ($records as [$model, $code, $name]) {
            $existing = $model::withTrashed()->where('code', $code)->first();
            if ($existing) {
                $this->command->line("ข้าม {$code}: มีรหัสนี้ในทะเบียนแล้ว");
                continue;
            }

            $model::create(['code' => $code, 'name_th' => $name, 'is_active' => true]);
            $this->command->info("เพิ่ม {$code} - {$name}");
        }
    }
}
