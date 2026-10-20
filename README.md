# VertexPL — NCR Management

ระบบบริหาร Non-Conformance Report (NCR) พัฒนาด้วย Laravel 8 สำหรับรายงาน ตรวจสอบ อนุมัติ ติดตาม Corrective Action และปิด NCR ตามลำดับงานขององค์กร

## Stack project

- **Backend:** PHP (`^7.3|^8.0`), Laravel 8, Eloquent ORM, Blade
- **Frontend:** Tailwind CSS 3, Alpine.js 3, Laravel Mix 6, Webpack 5, PostCSS
- **Database:** MySQL ตามค่าใน `.env.example` และ SQLite แบบ in-memory สำหรับ Automated tests
- **Authentication and authorization:** Laravel Sanctum และ Spatie Laravel Permission
- **Integrations:** Maatwebsite Excel สำหรับไฟล์ Excel, mPDF สำหรับ PDF, SweetAlert2 สำหรับ dialogs
- **Testing and quality:** PHPUnit 9, Node.js built-in test runner (`npm run test:theme`), Larastan/PHPStan (`composer analyse`)
- **Asset commands:** `npm run dev` สำหรับ build ระหว่างพัฒนา และ `npm run prod` สำหรับ production build

## Development setup

```bash
composer install
php artisan config:clear
php artisan cache:clear
php artisan migrate
php artisan serve
```

การสร้างข้อมูลพื้นฐานทั้งหมด (roles, permissions, NCR master data และ settings):

```bash
php artisan db:seed --class=DatabaseSeeder
```

เมื่อต้องการรีเซ็ตฐานข้อมูลสำหรับ Development เท่านั้น (ลบข้อมูลในทุก table แล้วสร้าง schema และข้อมูลพื้นฐานใหม่):

```bash
php artisan migrate:fresh --seed
```

คำสั่งนี้เรียก `DatabaseSeeder` แต่ไม่เรียก `NcrMockupSeeder` อัตโนมัติ และห้ามใช้กับฐานข้อมูลที่ต้องเก็บข้อมูลไว้

การสร้าง Mock users ต้องกำหนด `VERTEXPL_MOCK_PASSWORD` ใน environment ก่อน แล้วจึงรัน:

```bash
php artisan db:seed --class=NcrMockupSeeder
```

## NCR mock users

หน้า `/dev` ใช้ได้เฉพาะ `APP_ENV=local` หรือ `testing` และแสดงบัญชีที่ active ซึ่งมี email ลงท้าย `@mock.vertexpl.local` หรือ role `Super Admin` / `Administrator`.
แต่ละบัญชีแสดง email ที่ใช้ระบุตัวผู้ใช้, Role ระบบ, หน่วยงาน และตำแหน่งในหน่วยงาน
กด **เข้าใช้บัญชีนี้** เพื่อเข้าสู่ระบบเป็นบัญชีนั้นทันทีโดยไม่ต้องกรอกรหัสผ่าน; ระบบใช้สิทธิ์เดิมของบัญชีและบันทึก audit action `auth.login.dev`.
หากไม่มีบัญชีจะแสดงคำแนะนำให้สร้าง mock users ด้วย `NcrMockupSeeder` หรือ `NcrReporterUsersSeeder` ตามขั้นตอนด้านล่าง
ทั้ง GET และ POST ของ `/dev` จะตอบ 404 ใน environment อื่น ส่วน `/login` ใช้รหัสผ่านตามปกติ

| ตำแหน่ง/หน้าที่ในชุดทดสอบ | บทบาทในหน่วยงาน | หน่วยงาน | User account | Role ในระบบ |
|---|---|---|---|---|
| กรรมการผู้จัดการ | - | ฝ่ายบริหาร (MOCK-NCR-MGMT) | `plant.manager01@mock.vertexpl.local` | Managing Director |
| รองกรรมการผู้จัดการ | - | ฝ่ายบริหาร (MOCK-NCR-MGMT) | `deputy.managing.director01@mock.vertexpl.local` | Plant Manager + Deputy Managing Director |
| ผจก.แผนกที่เปิด NCR | - | ฝ่ายผลิต (MOCK-NCR-PROD) | `production.manager01@mock.vertexpl.local` | NCR Initial Reviewer + Responsible Department |
| QA | - | ฝ่ายควบคุมคุณภาพ / ประกันคุณภาพ (MOCK-NCR-QA) | `qa.supervisor01@mock.vertexpl.local` | QA |
| ผู้รับผิดชอบ Corrective Action - วิศวกรรม | - | ฝ่ายวิศวกรรม (MOCK-NCR-ENG) | `engineering.staff01@mock.vertexpl.local` | Responsible Department |
| ผู้รับผิดชอบ Corrective Action - คลังสินค้า | - | ฝ่ายคลังสินค้า (MOCK-NCR-WH) | `warehouse.staff01@mock.vertexpl.local` | Responsible Department |
| ผู้รับผิดชอบ Corrective Action - จัดซื้อ | - | ฝ่ายจัดซื้อ (MOCK-NCR-PRC) | `purchasing.coordinator01@mock.vertexpl.local` | Responsible Department |
| ผู้รายงาน | - | ฝ่ายผลิต (MOCK-NCR-PROD) | `production.reporter01@mock.vertexpl.local` | NCR Reporter |

Mock users ทั้งหมดใช้รหัสผ่านจาก `VERTEXPL_MOCK_PASSWORD` ส่วนบัญชีผู้ดูแลระบบใช้ค่าจาก `VERTEXPL_ADMIN_PASSWORD`

### NCR Reporter สำหรับวิศวกรรม จัดซื้อ และคลังสินค้า

`NcrReporterUsersSeeder` เพิ่มบัญชี NCR Reporter 15 บัญชี (หน่วยงานละ 5 บัญชี) โดยกำหนดบทบาทในหน่วยงานเป็น `MEMBER` และอ่านรหัสผ่านจาก `VERTEXPL_ADMIN_PASSWORD` โดยตรง บัญชีเหล่านี้เป็น mock users ในโดเมน `@mock.vertexpl.local` และ seeder จะหยุดโดยไม่แก้ไขข้อมูลหากพบ username ซ้ำ

หากยังไม่มีหน่วยงาน mock ให้รัน `NcrMockupSeeder` ก่อน จากนั้นรัน:

```bash
php artisan db:seed --class=NcrReporterUsersSeeder
```

| หน่วยงาน | User accounts |
|---|---|
| วิศวกรรม | `eng01`–`eng05`@mock.vertexpl.local |
| จัดซื้อ | `prc01`–`prc05`@mock.vertexpl.local |
| คลังสินค้า | `wh01`–`wh05`@mock.vertexpl.local |

## NCR permissions

ระบบมี NCR permissions 15 รายการ เรียงตามลำดับการใช้งานดังนี้:

| ลำดับ | Permission | การใช้งาน | ผู้ใช้งานหลัก |
|---:|---|---|---|
| 1 | `ncr.view` | ดูรายการและรายละเอียด NCR ที่เกี่ยวข้องกับตนเอง | ทุกบทบาทใน NCR |
| 2 | `ncr.create` | สร้างรายงาน NCR ฉบับร่าง | NCR Reporter |
| 3 | `ncr.update` | แก้ไขฉบับร่างหรือ NCR ที่ถูกส่งกลับ | NCR Reporter |
| 4 | `ncr.submit` | ส่ง NCR เข้ากระบวนการตรวจสอบ | NCR Reporter |
| 5 | `ncr.review` | ตรวจสอบความครบถ้วนของรายงาน | NCR Initial Reviewer / QA |
| 6 | `ncr.disposition.manage` | เสนอหรือแก้ไข Disposition และกำหนดหน่วยงานรับผิดชอบ | NCR Initial Reviewer / QA |
| 7 | `ncr.disposition.approve` | อนุมัติหรือปฏิเสธ Disposition | Plant Manager / Deputy Managing Director (บุคคลเดียวกัน) |
| 8 | `ncr.action.manage` | จัดทำ แก้ไข และดำเนินการ Corrective Action ตาม workflow | ผู้ใช้ที่ได้รับสิทธิ์นี้ |
| 9 | `ncr.action.approve` | อนุมัติหรือปฏิเสธ Corrective Action | Deputy Managing Director |
| 10 | `ncr.destruction.approve` | อนุมัติหรือปฏิเสธการทำลายเฉพาะกรณี `Disposition = S/SCRAP` | Managing Director |
| 11 | `ncr.action.verify` | ตรวจยืนยันผลการดำเนิน Corrective Action | QA / Action Verifier |
| 12 | `ncr.qa.followup` | ติดตามผลหลังการดำเนินการ | QA |
| 13 | `ncr.close` | อนุญาตให้ QA Follow-up ผล `EFFECTIVE` ปิด NCR เป็น `CLOSED` | QA |
| 14 | `ncr.report` | ดูรายงาน สรุป และข้อมูลวิเคราะห์ NCR | ผู้บริหาร / QA |
| 15 | `ncr.master.manage` | จัดการข้อมูลหลักของ NCR | ผู้ดูแลระบบ |

## NCR permission flow

ลำดับงานหลัก:

```text
ncr.create
→ ncr.update
→ ncr.submit
→ ncr.review
→ ncr.disposition.manage
→ ncr.disposition.approve
→ ncr.action.manage
→ ncr.action.approve
```

หลังรองกรรมการผู้จัดการอนุมัติ Corrective Action:

- `Disposition != S/SCRAP` → ดำเนิน Corrective Action ต่อได้
- `Disposition = S/SCRAP` → ต้องผ่าน `ncr.destruction.approve` โดยกรรมการผู้จัดการก่อน

ขั้นตอนหลังดำเนินการ:

```text
ncr.action.verify
→ ncr.qa.followup
→ CLOSED เมื่อผล EFFECTIVE และผู้ใช้มี ncr.close
```

ไม่มี route ปิดงานแยก: `ncr.close` เป็น permission เพิ่มเติมที่ตรวจในคำสั่งบันทึก QA Follow-up เมื่อเลือก `EFFECTIVE`

`ncr.view`, `ncr.report` และ `ncr.master.manage` เป็นสิทธิ์ประกอบ ไม่ใช่คำสั่งเปลี่ยนสถานะโดยตรง

## NCR documentation

Current source of truth: [NCR current workflow flowchart](docs/NCR-CURRENT-FLOWCHART.md).

Historical source documents and earlier proposals are retained in [NCR documentation archive](docs/NCR-DOCUMENTATION.md) for audit only.

## License

This project uses the Laravel framework, which is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
