# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

ผู้ใช้หลักคือผู้แจ้ง NCR ซึ่งต้องบันทึก Non-Conformance Report ได้รวดเร็วและมีโอกาสกรอกข้อมูลผิดน้อยที่สุด

ผู้ใช้อื่นที่ระบบรองรับจาก workflow และสิทธิ์ในโค้ด ได้แก่ QA/ผู้ตรวจสอบ ผู้รับผิดชอบหน่วยงาน และผู้มีอำนาจอนุมัติ โดยแต่ละบทบาทมีการมองเห็นข้อมูลและ action ตาม permission ของระบบ

- ข้อมูลผู้ใช้หลัก: ยืนยันโดยผู้ใช้
- บทบาทอื่น: อ้างอิงจาก route, workflow terminology และโครงสร้างระบบปัจจุบัน

## Product Purpose

VertexPL เป็นระบบจัดการ Non-Conformance Report แบบครบวงจร ตั้งแต่การสร้างรายงาน บันทึกข้อมูลปัญหา แนบหลักฐาน ตรวจสอบ มอบหมาย/ติดตามการแก้ไข อนุมัติ ตรวจยืนยัน และปิดรายงานตาม workflow ขององค์กร

ความสำเร็จของผลิตภัณฑ์คือผู้ใช้สามารถจัดการ NCR ได้ครบถ้วน ถูกต้อง ตรวจสอบย้อนหลังได้ และดำเนินการตามบทบาท/สิทธิ์ที่ได้รับ

- จุดประสงค์หลัก: ยืนยันโดยผู้ใช้
- รายละเอียดความสามารถและผลลัพธ์: อ้างอิงจากโค้ดและเอกสารของโปรเจกต์

## Positioning

ระบบรวมข้อมูล NCR, หลักฐาน, สถานะ, ประวัติการตัดสินใจ และ corrective actions ไว้ใน workflow เดียวที่ควบคุมตามบทบาทและ permission ของผู้ใช้

ข้อความนี้เป็นข้อสรุปจากโครงสร้าง route, workflow service, authorization rules และหน้าจอ NCR ปัจจุบัน ไม่ใช่ claim ทางการตลาด

## Operating Context

- ผู้ใช้เริ่มจากการสร้าง NCR เมื่อพบความไม่สอดคล้องของผลิตภัณฑ์ กระบวนการ หรือการทำงาน
- การสร้าง NCR มีข้อมูลบริบท วันที่/เวลา การจำแนกประเภทและระดับความรุนแรง ข้อมูลอ้างอิง รายการผลิตภัณฑ์ รายละเอียดปัญหา และหลักฐานแนบ
- NCR ดำเนินต่อผ่านสถานะและ action ตาม workflow โดยผู้ใช้แต่ละบทบาทจะเห็นเฉพาะการดำเนินการที่ได้รับอนุญาต
- ข้อมูลประวัติ หลักฐาน การอนุมัติ และผลการตรวจยืนยันต้องคงอยู่เพื่อการตรวจสอบย้อนหลัง
- ระบบใช้งานเป็น web application ภายในองค์กรและต้องรองรับหน้าจอ Desktop, Tablet และ Mobile

รายการข้างต้นเป็นข้อสรุปจาก `CONTEXT.md`, `AGENTS.md`, route definitions และ NCR views/services ปัจจุบัน

## Capabilities and Constraints

- ใช้ Laravel 8, Eloquent, Blade, Tailwind CSS 3, Alpine.js, Laravel Mix และ SweetAlert2 ที่มีอยู่แล้ว
- มีระบบ authentication, permission, NCR visibility และ workflow authorization
- ต้องรักษา workflow, validation rules, permission, audit/history, CSRF และ concurrency behavior เดิม
- ต้องใช้ field, route, endpoint, upload rule และ terminology เดิม เว้นแต่มี requirement ใหม่ที่ยืนยันชัดเจน
- ห้ามเปลี่ยน Database, API, Backend Logic หรือเพิ่ม frontend framework/CSS framework/build dependency โดยไม่มี requirement
- UI ใหม่ต้อง reuse shared layouts, Blade components, NCR classes และ theme tokens เดิม
- ต้องรองรับ Light/Dark Mode และรักษา `vertexpl-theme` behavior เดิม
- ต้องไม่สร้างข้อมูล ทดสอบ หรือเขียนข้อมูลจริงโดยไม่ผ่าน authorization และ environment ที่เหมาะสม

## Brand Commitments

- ชื่อผลิตภัณฑ์: VertexPL
- คำศัพท์หลักของผู้ใช้: NCR และ Non-Conformance Report
- ใช้ภาษาไทยใน user-facing UI ให้สอดคล้องกับ terminology ปัจจุบัน
- รักษา Design System, shared components, spacing rules และ theme tokens เดิมของโปรเจกต์
- UI ควรมีลักษณะ professional product UI ที่กระชับ ชัดเจน และเหมาะกับงานปฏิบัติการ
- ห้ามเปลี่ยนข้อความทางธุรกิจหรือความหมายของข้อมูลโดยไม่จำเป็น

ข้อผูกพันด้าน workflow, permission และ Design System ยืนยันโดยผู้ใช้ ส่วนชื่อ ภาษา และแนวทาง UI อ้างอิงจากโค้ด/เอกสารปัจจุบัน

## Evidence on Hand

- `AGENTS.md` — development standards, NCR workflow contracts, authorization และ UI rules
- `CONTEXT.md` — NCR terminology และ lifecycle context
- `routes/web.php` — authenticated routes, NCR operations และ permission boundaries
- `app/Http/Controllers/NcrReportController.php` — NCR request entry points
- `app/Services/NcrWorkflowService.php` — workflow behavior
- `app/Services/NcrVisibilityService.php` — visibility rules
- `resources/views/ncr/form.blade.php` — NCR create/edit form และ current user flow
- `resources/css/app.css` — shared styles, theme tokens และ NCR UI styles
- `resources/js/app.js`, `resources/js/theme.js` — form submission/loading behavior และ theme initialization
- `package.json` — frontend stack and available build/test commands

ไม่มีข้อมูล testimonial, benchmark, pricing หรือ marketing claim ที่ยืนยันแล้ว และห้ามสร้างข้อมูลดังกล่าวขึ้นเอง

## Product Principles

1. รักษาความถูกต้องของ NCR และประวัติการตรวจสอบเป็นหลัก
2. ให้ผู้ใช้สร้างและดำเนินการ NCR ได้ชัดเจน รวดเร็ว และลดข้อผิดพลาด
3. ให้ workflow และ permission เป็นตัวควบคุม action ที่เชื่อถือได้
4. พัฒนาต่อโดย reuse architecture, Design System และ shared components เดิม
5. ทำให้ข้อมูลและ action อ่านง่าย ใช้งานได้จริง และเข้าถึงได้บนหลายขนาดหน้าจอ

หลักการเหล่านี้สรุปจากคำยืนยันของผู้ใช้และข้อกำหนดในโปรเจกต์

## Accessibility & Inclusion

- รองรับการใช้งานด้วย keyboard และต้องมี visible focus state
- ใช้ accessible name, label และ error state ที่ชัดเจนสำหรับ form controls
- ไม่ใช้สีเพียงอย่างเดียวในการสื่อความหมายของสถานะหรือ action
- รักษา contrast ตามมาตรฐานของโปรเจกต์ทั้ง Light และ Dark Mode
- รองรับ responsive layout สำหรับ Desktop, Tablet และ Mobile
- รักษา native dialog, scrollable content และ action ที่เข้าถึงได้

ข้อกำหนดนี้อ้างอิงจาก UI/accessibility standards ใน `AGENTS.md` และ acceptance criteria ของงาน UI ล่าสุด
