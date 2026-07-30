# VertexPL Design Guide

เอกสารนี้กำหนดแนวทางการออกแบบ UI, Authentication, Authorization และโครงสร้างหน้าจอของโปรเจกต์ `VertexPL`

## 1. Design Goals

VertexPL เป็นระบบ Web Application ภายในองค์กร การออกแบบจึงเน้น:

- ใช้งานง่ายและเรียนรู้ได้เร็ว
- หน้าตาสม่ำเสมอทุก Module
- รองรับ Desktop, Tablet และ Mobile
- แสดงเมนูตามสิทธิ์ของผู้ใช้
- ป้องกันการเข้าถึงทั้งในระดับ UI และ Route
- พร้อมต่อยอด CRUD โดยไม่ต้องสร้าง Layout ใหม่
- รักษาความเรียบง่ายของ Laravel Breeze และ Blade

## 2. Technology

| ส่วน | เทคโนโลยี |
|---|---|
| Backend | Laravel 8.83.x |
| Template Engine | Blade |
| Authentication | Laravel Breeze 1.9.2 |
| Authorization | Spatie Laravel Permission 5.11.1 |
| CSS | Tailwind CSS 3 |
| UI Interaction | Alpine.js 3 |
| Asset Builder | Laravel Mix 6 + Webpack 5 |
| Alert / Confirmation | SweetAlert2 11 |

## 3. Application Structure

```text
Guest
  └─ Login
       └─ ตรวจ Email + Password + is_active
            └─ Authenticated
                 ├─ Topbar
                 ├─ Sidebar ตาม Permission
                 └─ Page Content
                      ├─ Dashboard
                      ├─ User Management
                      ├─ Role & Permission
                      ├─ Import / Export
                      ├─ Audit Log
                      └─ System Settings
```

## 4. Authentication Design

### Login

หน้า Login ใช้ Laravel Breeze และ Route:

```text
GET  /login
POST /login
```

เงื่อนไขการ Login:

1. Email และ Password ต้องถูกต้อง
2. `users.is_active` ต้องเป็น `true`
3. User ต้องไม่ถูก Soft Delete
4. ระบบใช้ Rate Limiter ป้องกันการสุ่มรหัสผ่าน
5. เมื่อ Login สำเร็จ ระบบบันทึก:
   - `last_login_at`
   - `last_login_ip`

### Public Registration

ระบบปิด Public Registration โดยไม่มี Route ต่อไปนี้:

```text
GET  /register
POST /register
```

บัญชีผู้ใช้ต้องสร้างผ่าน Seeder หรือหน้า User Management โดยผู้มีสิทธิ์เท่านั้น

### Logout

การ Logout ใช้ `POST /logout` พร้อม CSRF Token จาก Topbar

## 5. Authorization Design

ระบบตรวจ Permission สองชั้น:

1. Blade ใช้ `@can` เพื่อซ่อนเมนูที่ไม่มีสิทธิ์
2. Route ใช้ `permission:<permission-name>` เพื่อป้องกันการเข้าด้วย URL โดยตรง

การซ่อนเมนูอย่างเดียวไม่ถือเป็นการรักษาความปลอดภัย ทุก Route ของ Module ต้องมี Permission Middleware เสมอ

### Permission Matrix

| Module | View | Create | Update | Delete / Manage |
|---|---|---|---|---|
| Dashboard | `dashboard.view` | - | - | - |
| Users | `users.view` | `users.create` | `users.update` | `users.delete` |
| Roles | `roles.view` | `roles.create` | `roles.update` | `roles.delete` |
| Permissions | `permissions.view` | - | - | `permissions.manage` |
| Import | `imports.view` | `imports.create` | - | - |
| Export | - | `exports.create` | - | - |
| Audit Log | `system.audit.view` | - | - | - |
| Settings | `system.settings` | - | `system.settings` | - |

### Default Roles

| Role | แนวทางใช้งาน |
|---|---|
| Super Admin | เข้าถึงทุก Permission |
| Administrator | จัดการงานระบบทั่วไป แต่ไม่ใช่ทุก System Permission |
| Viewer | ดู Dashboard ได้อย่างเดียว |

## 6. Layout

ไฟล์ Layout หลัก:

```text
resources/views/layouts/app.blade.php
resources/views/layouts/navigation.blade.php
```

โครงสร้าง:

```text
+------------------------------------------------------+
| Sidebar             | Topbar                         |
|                     +--------------------------------+
| Logo                | Page Header                    |
| Main Navigation     +--------------------------------+
| System Navigation   |                                |
|                     | Page Content                   |
| Current User        |                                |
+------------------------------------------------------+
```

### Desktop

- Sidebar กว้าง `16rem` (`w-64`)
- Sidebar อยู่ด้านซ้ายแบบ Fixed
- Topbar สูง `4rem` (`h-16`)
- Content เว้นพื้นที่ด้านซ้ายด้วย `lg:pl-64`

### Mobile

- Sidebar ซ่อนอยู่นอกหน้าจอ
- เปิดด้วย Hamburger Button
- มี Overlay ด้านหลัง
- ปิดได้ด้วยปุ่ม Close หรือกด Overlay
- การเปิด/ปิดควบคุมด้วย Alpine.js

## 7. Sidebar

Sidebar แบ่งเมนูเป็นสองกลุ่ม:

### เมนูหลัก

- Dashboard
- ผู้ใช้งาน
- บทบาทและสิทธิ์
- Import / Export

### ระบบ

- Audit Log
- ตั้งค่าระบบ

ข้อกำหนด:

- แสดงเมนูด้วย `@can`
- Active Menu ใช้พื้นหลัง Indigo
- เมนูปกติใช้สี Slate
- ทุกเมนูต้องมี Icon, Label และ Named Route
- ห้ามใช้ URL แบบ Hard-code ใน Blade

ตัวอย่าง:

```blade
@can('users.view')
    <a href="{{ route('users.index') }}">
        ผู้ใช้งาน
    </a>
@endcan
```

## 8. Topbar

Topbar แสดง:

- ปุ่มเปิด Sidebar บน Mobile
- ชื่อผู้ใช้
- Email
- ปุ่ม Logout

ปุ่ม Logout ต้องอยู่ใน `<form method="POST">` และมี `@csrf`

## 9. Visual Language

### Colors

| การใช้งาน | Tailwind |
|---|---|
| Primary | `indigo-500`, `indigo-600` |
| Primary Gradient | `from-indigo-600 to-violet-600` |
| Sidebar | `slate-900` |
| Sidebar Hover | `slate-800` |
| Page Background | `slate-100` |
| Card Background | `white` |
| Border | `slate-200` |
| Main Text | `slate-900` |
| Secondary Text | `slate-500` |
| Success | `emerald-600` |
| Danger | `red-600` |

### Typography

- Font: Nunito
- Page Title: `text-2xl font-bold`
- Section Title: `text-lg font-bold`
- Body: `text-sm` หรือ `text-base`
- Helper Text: `text-xs text-slate-500`
- Menu: `text-sm font-medium`

### Spacing

- Page Padding: `p-4 sm:p-6 lg:p-8`
- Card Padding: `p-5` หรือ `p-6`
- Grid Gap: `gap-4` หรือ `gap-6`
- Section Gap: `mt-6`

### Shape and Shadow

- Card: `rounded-xl`
- Large Panel: `rounded-2xl`
- Input/Button: `rounded-lg`
- Card Border: `border border-slate-200`
- Card Shadow: `shadow-sm`

## 10. Page Header

ทุกหน้าภายในระบบควรส่ง Header ผ่าน `<x-slot name="header">`

```blade
<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm font-medium text-indigo-600">Module Name</p>
            <h1 class="mt-1 text-2xl font-bold text-slate-900">
                Page Title
            </h1>
        </div>
    </x-slot>

    <div class="p-4 sm:p-6 lg:p-8">
        <!-- Page content -->
    </div>
</x-app-layout>
```

## 11. CRUD Page Pattern

หน้า List ของแต่ละ Module ควรมี:

1. Page Header
2. Action Button ที่แสดงตาม Permission
3. Search และ Filter
4. Data Table
5. Pagination
6. Empty State
7. Loading State
8. Confirmation ก่อน Delete

ตัวอย่างปุ่มตาม Permission:

```blade
@can('users.create')
    <a href="{{ route('users.create') }}">
        เพิ่มผู้ใช้งาน
    </a>
@endcan
```

### Form

- Label ต้องชัดเจน
- Required Field ต้องระบุ
- แสดง Validation Error ใต้ Field
- ค่าเดิมต้องไม่หายเมื่อ Validation ไม่ผ่าน
- ปุ่มหลักอยู่ด้านขวา
- มีปุ่มย้อนกลับหรือยกเลิก

### Delete

- ใช้ HTTP Method `DELETE`
- ต้องมี Permission Middleware
- ต้องยืนยันด้วย SweetAlert2
- ห้ามลบ User ที่กำลัง Login
- ใช้ Soft Delete สำหรับข้อมูลที่ต้องตรวจสอบย้อนหลัง

## 12. Routes

Named Routes ปัจจุบัน:

| URL | Route Name | Permission |
|---|---|---|
| `/dashboard` | `dashboard` | `dashboard.view` |
| `/users` | `users.index` | `users.view` |
| `/roles` | `roles.index` | `roles.view` |
| `/imports` | `imports.index` | `imports.view` |
| `/audit-logs` | `audit-logs.index` | `system.audit.view` |
| `/settings` | `settings.index` | `system.settings` |

Route ของ Module ใหม่ควรอยู่ภายใน `auth` middleware group และกำหนด Permission เพิ่มเติม:

```php
Route::middleware('auth')->group(function () {
    Route::get('/users', [UserController::class, 'index'])
        ->middleware('permission:users.view')
        ->name('users.index');
});
```

## 13. Responsive Requirements

ทุกหน้าต้องตรวจอย่างน้อยสามขนาด:

| ขนาด | แนวทาง |
|---|---|
| Mobile | ต่ำกว่า `640px` |
| Tablet | `640px` ถึง `1023px` |
| Desktop | `1024px` ขึ้นไป |

ข้อกำหนด:

- ห้ามมี Horizontal Scroll จาก Layout
- Table ขนาดใหญ่ต้องมี `overflow-x-auto`
- Action Buttons ต้อง Wrap หรือเปลี่ยนเป็น Dropdown บน Mobile
- Form Grid ต้องลดเหลือหนึ่ง Column บน Mobile

## 14. Accessibility

- Icon-only Button ต้องมี `<span class="sr-only">`
- Input ต้องมี Label
- สีข้อความต้องมี Contrast เพียงพอ
- Focus State ต้องมองเห็น
- Form Error ต้องไม่สื่อด้วยสีเพียงอย่างเดียว
- Modal และ Dropdown ต้องใช้ Keyboard ได้

## 15. Testing Requirements

Feature ใหม่ควรมี Test อย่างน้อย:

- Guest ถูก Redirect ไป Login
- User ไม่มี Permission ได้ `403`
- User มี Permission เปิดหน้าได้
- เมนูไม่แสดงเมื่อไม่มี Permission
- Validation ทำงาน
- Active User Login ได้
- Inactive User Login ไม่ได้
- Public Registration ได้ `404`

Test ใช้ SQLite แบบ In-memory จาก `phpunit.xml` เพื่อไม่แตะฐานข้อมูลจริง

รันด้วย:

```powershell
php artisan test
```

Build Frontend:

```powershell
npm run dev
```

## 16. Current Status

ส่วนที่พร้อมใช้งาน:

- Login / Logout
- Password Reset และ Email Verification
- Public Registration Disabled
- Active-user Login Guard
- Last Login Tracking
- Permission Middleware
- Responsive Sidebar และ Topbar
- Permission-aware Navigation
- Dashboard
- User Management CRUD
  - Search, Status Filter และ Role Filter
  - Pagination
  - Create และ Update
  - Role Assignment
  - Active/Inactive
  - Password Policy
  - Self-deactivation และ Self-deletion Protection
  - Soft Delete
  - SweetAlert Confirmation
- Role & Permission Management
  - Search และ Pagination
  - User/Permission Counts
  - Create และ Update Role
  - Grouped Permission Matrix
  - Group Select/Deselect และ Selected Count
  - Protected Super Admin Role
  - Prevent Deleting Roles With Users
  - Prevent Removing Critical Permissions From Current User's Role
- Profile & Security
  - Self-service Profile
  - Update Email Without Re-verification
  - Current Password Validation
  - Strong and Non-reused Password
  - Password Strength Indicator
  - Password Change Rate Limit
  - Password Confirmation Middleware
  - Logout Other Devices
  - Password Change Timestamp
- Audit Log
  - Login Success/Failure, Logout และ Login Throttling
  - User, Role/Permission และ Profile Change History
  - Actor, IP Address, User Agent และ Timestamp
  - Before/After Values โดยไม่จัดเก็บ Password
  - Search, Module/Action/Date Filters และ Pagination
  - Responsive Detail View
  - Permission `system.audit.view`
  - Feature Toggle `AUDIT_LOG_ENABLED`

ส่วนที่เป็น Placeholder และพร้อมพัฒนา CRUD:

- Import / Export
- System Settings

## 17. Next Recommended Implementation

ลำดับการพัฒนาต่อ:

1. System Settings
2. Import / Export Workflow
3. Audit Log Retention / Export Policy
