<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SystemSettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            [
                'key' => 'general.app_name',
                'label' => 'ชื่อระบบ',
                'group' => 'general',
                'type' => 'string',
                'value' => 'VertexPL',
                'description' => 'ชื่อที่ใช้แสดงภายในระบบ',
            ],
            [
                'key' => 'pagination.default_size',
                'label' => 'จำนวนรายการต่อหน้า',
                'group' => 'pagination',
                'type' => 'integer',
                'value' => '20',
                'description' => 'จำนวนรายการเริ่มต้นที่แสดงในหน้ารายการ',
            ],
            [
                'key' => 'uploads.max_size_mb',
                'label' => 'ขนาดไฟล์สูงสุด',
                'group' => 'uploads',
                'type' => 'integer',
                'value' => '10',
                'description' => 'ขนาดไฟล์สูงสุดที่อนุญาต หน่วยเป็น MB',
            ],
            [
                'key' => 'imports.max_rows',
                'label' => 'จำนวนแถว Import สูงสุด',
                'group' => 'imports',
                'type' => 'integer',
                'value' => '5000',
                'description' => 'จำนวนแถวสูงสุดที่อนุญาตต่อการ Import หนึ่งครั้ง',
            ],
            [
                'key' => 'audit.retention_days',
                'label' => 'ระยะเวลาเก็บ Audit Log',
                'group' => 'audit',
                'type' => 'integer',
                'value' => '365',
                'description' => 'จำนวนวันที่เก็บ Audit Log ก่อนเข้าสู่กระบวนการ Archive หรือ Cleanup',
            ],
            [
                'key' => 'features.import_enabled',
                'label' => 'เปิดใช้งาน Import',
                'group' => 'features',
                'type' => 'boolean',
                'value' => '1',
                'description' => 'เปิดหรือปิดการใช้งานฟังก์ชัน Import',
            ],
            [
                'key' => 'ncr.form.code',
                'label' => 'รหัสแบบฟอร์ม NCR',
                'group' => 'ncr',
                'type' => 'string',
                'value' => 'FM-DQA-06',
                'description' => 'รหัสแบบฟอร์มต้นฉบับสำหรับการแสดงผลและ Print/PDF',
            ],
            [
                'key' => 'ncr.form.revision',
                'label' => 'Revision แบบฟอร์ม NCR',
                'group' => 'ncr',
                'type' => 'string',
                'value' => '03',
                'description' => 'Revision ปัจจุบันของแบบฟอร์ม FM-DQA-06',
            ],
            [
                'key' => 'ncr.form.revision_date',
                'label' => 'วันที่ Revision แบบฟอร์ม NCR',
                'group' => 'ncr',
                'type' => 'string',
                'value' => '2023-01-04',
                'description' => 'วันที่ Revision ในรูปแบบ ISO สำหรับ Print/PDF',
            ],
        ];

        foreach ($settings as $setting) {
            Setting::firstOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }
    }
}
