# VertexPL

**VertexPL** ย่อมาจาก **Vertex PHP Laravel** เป็น Starter Project สำหรับพัฒนา Web Application ภายในองค์กร โดยแยกส่วนที่นำกลับมาใช้ซ้ำได้ออกจาก Business Logic ของโปรเจกต์ RGO เดิม

## สถานะปัจจุบัน

โปรเจกต์ `VertexPL` ที่ติดตั้งอยู่ใน Workspace นี้มีส่วนต่อไปนี้พร้อมใช้งาน:

- Authentication จาก Laravel Breeze
  - Login และ Logout
  - Forgot/Reset Password
  - Email Verification และ Confirm Password
- ปิด Public Registration โดยค่าเริ่มต้น
- ป้องกันการ Login ของบัญชีที่ `is_active=false`
- บันทึกเวลาและ IP ที่ Login ล่าสุด
- Dashboard ตัวอย่าง
- Layout หลักแบบ Responsive พร้อม Sidebar และ Topbar
- เมนูและ Route ที่ควบคุมด้วย Permission
- User Management
  - ค้นหาด้วยชื่อหรืออีเมล
  - กรองตามสถานะและ Role
  - Pagination
  - เพิ่ม แก้ไข ปิดใช้งาน และ Soft Delete
  - กำหนด Role และ Password Policy
- Role & Permission Management
  - ค้นหาและ Pagination
  - แสดงจำนวนผู้ใช้และ Permission
  - เพิ่มและแก้ไข Role
  - Permission Matrix แยกตาม Module
  - ป้องกัน Super Admin, Role ที่มีผู้ใช้ และ Self-lockout
- Profile & Security
  - แสดงข้อมูลบัญชี Role สถานะ และ Login ล่าสุด
  - แก้ไขชื่อและอีเมลได้ทันทีโดยไม่ส่ง Email Verification ซ้ำ
  - เปลี่ยนรหัสผ่านตาม Password Policy
  - Password Strength, Rate Limit และ Logout Other Devices
- Audit Log
  - บันทึก Login สำเร็จ/ไม่สำเร็จ, Logout และเหตุการณ์ Rate Limit
  - บันทึกการเพิ่ม แก้ไข และลบ User กับ Role/Permission
  - บันทึกการแก้ไข Profile และการเปลี่ยนรหัสผ่านโดยไม่เก็บรหัสผ่าน
  - ค้นหา กรองตาม Module/Action/ช่วงวันที่ และ Pagination
  - แสดงผู้ดำเนินการ IP, User Agent และข้อมูลก่อน/หลังการเปลี่ยนแปลง
  - จำกัดการเข้าถึงด้วย Permission `system.audit.view`
  - เปิด/ปิดการบันทึกได้ด้วย `AUDIT_LOG_ENABLED=true|false`
- Laravel Sanctum
- Role และ Permission จาก Spatie
- Seeder สำหรับ Role, Permission และบัญชี Super Admin
- ฟิลด์พื้นฐานของ User เช่น `is_active`, Audit Fields และ Soft Delete
- Laravel Excel และ Doctrine DBAL สำหรับนำไปพัฒนาระบบ Import/Export
- SweetAlert2
- Frontend assets ที่ Build ด้วย Laravel Mix

ส่วนต่อไปนี้ยังเป็นเพียง Foundation หรือ Permission ที่เตรียมไว้ ยังไม่มีหน้าจอ CRUD สำเร็จรูป:

- Excel Import/Export UI
- System Settings

โปรเจกต์ไม่รวม Model, Controller, Route, Migration และข้อมูลธุรกิจเฉพาะของ RGO

## Technology Stack

เวอร์ชันที่ Script กำหนดและติดตั้งจริง:

- PHP Runtime: รองรับ `7.4` ถึง `8.2`
  - แนะนำ PHP `8.1`
  - เมื่อรันด้วย PHP 8.2 Composer จะจำลอง Platform เป็น PHP `8.1.12`
- Laravel Framework `8.83.x`
- MySQL 8
- Blade
- Laravel Breeze `1.9.2`
- Laravel Sanctum `~2.11`
- Spatie Laravel Permission `5.11.1`
- Maatwebsite Laravel Excel `~3.1`
- Doctrine DBAL `~3.9`
- Guzzle `~7.0`
- Laravel Mix `^6.0.6`
- Webpack `^5.97.1`
- Tailwind CSS `^3.0.18`
- Alpine.js `^3.4.2`
- SweetAlert2 `11.x`
- PHPUnit `^9.5.10`

## เครื่องมือที่ต้องมี

ก่อนรัน Script ต้องติดตั้งและเรียกคำสั่งต่อไปนี้ผ่าน `PATH` ได้:

```powershell
php --version
composer --version
node --version
npm --version
```

## สร้างโปรเจกต์

เปิด PowerShell ที่โฟลเดอร์ `VertexPL-Starter` แล้วรัน:

```powershell
Set-ExecutionPolicy -Scope Process Bypass
.\setup-vertexpl.ps1
```

Script จะ:

1. สร้าง Laravel 8 ในโฟลเดอร์ `VertexPL`
2. ติดตั้ง Backend และ Frontend dependencies
3. สร้าง Authentication ด้วย Laravel Breeze
4. Publish Migration และ Config ของ Spatie Permission
5. คัดลอก Seeder, User Model และ Migration ของ VertexPL
6. กำหนด `APP_NAME=VertexPL` และ `DB_DATABASE=vertex_pl`
7. สร้าง Application Key
8. Build Frontend assets

> Script จะหยุดหากมีโฟลเดอร์ `VertexPL` อยู่แล้ว เพื่อป้องกันการเขียนทับโปรเจกต์เดิม

## ตั้งค่าหลังติดตั้ง

1. สร้างฐานข้อมูล MySQL ชื่อ `vertex_pl`
2. แก้ไขข้อมูลเชื่อมต่อฐานข้อมูลใน `VertexPL\.env`
3. กำหนดบัญชีผู้ดูแลระบบใน PowerShell:

```powershell
cd VertexPL
$env:VERTEXPL_ADMIN_EMAIL="admin@example.com"
$env:VERTEXPL_ADMIN_PASSWORD="ChangeThisPassword123!"
php artisan migrate --seed
```

รหัสผ่านต้องมีอย่างน้อย 14 ตัวอักษร และประกอบด้วยตัวพิมพ์ใหญ่ ตัวพิมพ์เล็ก ตัวเลข และสัญลักษณ์

หากไม่กำหนด Environment Variables ระบบจะสร้าง Role และ Permission แต่จะไม่สร้างบัญชีผู้ดูแลระบบ

## เปิดใช้งาน

```powershell
cd VertexPL
php artisan serve
```

เปิดหน้า Login ที่:

```text
http://127.0.0.1:8000/login
```

หากพอร์ต `8000` ถูกใช้งานอยู่ ให้เปลี่ยนพอร์ต:

```powershell
php artisan serve --port=8001
```

แล้วเปิด:

```text
http://127.0.0.1:8001/login
```

หากแก้ไขไฟล์ Frontend ให้ Build ใหม่ด้วย:

```powershell
npm run dev
```

## Naming Convention

- Project / Display Name: `VertexPL`
- Folder: `VertexPL`
- Database: `vertex_pl`
- Application name: `VertexPL`
- Namespace: ใช้ Namespace มาตรฐาน `App\` ของ Laravel 8

> `composer.json` ที่สร้างจริงยังใช้ Package Name เริ่มต้น `laravel/laravel` ไม่ใช่ `vertex/vertex-pl`

## ข้อควรระวัง

- Laravel 8 และ Frontend dependencies ชุดนี้เป็น Legacy Stack
- Laravel 8 รองรับ PHP อย่างเป็นทางการถึง PHP 8.1 ส่วนการใช้ PHP 8.2 มีไว้สำหรับ Local Runtime โดยล็อก Composer Platform เป็น PHP 8.1.12
- Composer และ npm อาจรายงาน Security Advisories จาก Legacy dependencies ควรตรวจด้วย `composer audit` และ `npm audit`
- ควรแยกแผนอัปเกรด Laravel/PHP ออกจากการย้ายระบบ เพื่อหลีกเลี่ยงการเปลี่ยนแปลงหลายชั้นพร้อมกัน
