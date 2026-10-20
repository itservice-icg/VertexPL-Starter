# สรุปงาน VertexPL — 19 ตุลาคม 2026

โครงการ: `D:\01_Project\MCP\VertexPL`  
ขอบเขต: หน้ารายการ NCR, การกำหนดผู้รับผิดชอบ Corrective Action และการทดสอบ Playwright E2E ผ่าน UI

## สรุปผล

- ปรับตาราง `/ncr` ให้กำหนดสัดส่วนคอลัมน์ครบ 100%, ตัดคอลัมน์ Email, ลด padding/ความกว้างในคอลัมน์ที่ระบุ และคงรูปแบบการ์ดสำหรับหน้าจอเล็กไว้ใน CSS
- ตรวจฟิลด์ผู้รับผิดชอบ Corrective Action: บันทึกไม่ได้เมื่อไม่เลือกผู้รับผิดชอบ, Dropdown แสดงผู้ใช้ใน Engineering และตาราง Stage 03 แสดงชื่อผู้รับผิดชอบหลัง Reload
- ทดสอบ Workflow 6 สถานการณ์บน NCR 20–25: **5 PASS, 1 FAIL**; NCR 20–24 ปิด `CLOSED`, NCR 25 คง `QA_VERIFIED`
- แก้ Playwright Runner ให้อ่านผลจากแถวในตาราง QA Follow-up จริง เรียงตามรอบ, ตรวจข้อมูลก่อนส่ง และยืนยันว่ามีแถวใหม่เพียงหนึ่งแถวหลัง Save/Reload
- ทดสอบเส้นทาง `NEED_MORE_TIME → EFFECTIVE → CLOSED` บน NCR ทดสอบแยกหมายเลข 26: **PASS**; ตารางมี 2 แถวตามลำดับที่กำหนด และ marker ระบุว่าสร้าง NCR เพียงครั้งเดียว
- ตรวจ NCR 25 ก่อนและหลังทดสอบ NCR 26: `QA_VERIFIED` และข้อความประวัติทั้ง 4 แถวตรงกัน ไม่มีการแก้หรือลบรายการเดิม

## งานที่ทำ

### หน้ารายการ NCR

- ปรับคอลัมน์ใน `/ncr` ให้มีสัดส่วนรวม 100% และลดพื้นที่คอลัมน์เลข NCR, ข้อเสนอแนะ, สินค้า/ปัญหา และหน่วยงานตามที่ระบุ
- ไม่แสดงคอลัมน์ Email
- ปรับการแสดงผลจอเล็กเป็นการ์ดตาม label ของแต่ละข้อมูล
- เปลี่ยนไฟล์ที่เกี่ยวข้อง: `resources/views/ncr/index.blade.php`, `resources/css/app.css`
- ข้อจำกัด: สรุปนี้ยืนยันการแก้ source; ไม่มีหลักฐาน screenshot สำหรับตรวจภาพทุกขนาดหน้าจอในงานชุดนี้

### Corrective Action — ผู้รับผิดชอบ / ผู้ลงชื่อ

ทดสอบด้วย NCR 19 ซึ่งปิด `CLOSED`:

- PASS — ไม่เลือกผู้รับผิดชอบแล้ว browser validation ป้องกันการ submit; ไม่มี Action เพิ่ม
- PASS — Dropdown พบผู้ใช้ในหน่วยงาน Engineering เท่านั้น
- PASS — หลังเลือกผู้รับผิดชอบและ Reload ตาราง Stage 03 แสดงชื่อจริง ไม่ใช่ `-`

รายงาน: [NCR-FRESH-OWNER-20261019071243.json](NCR-FRESH-OWNER-20261019071243.json) · [Markdown](NCR-FRESH-OWNER-20261019071243.md)

### Playwright E2E Workflow

ผลจากการรัน 6 scenarios บน NCR ใหม่:

| Scenario | NCR | ผล | สถานะสุดท้าย |
|---|---:|---|---|
| TC01 — Initial Review correction / Disposition revision | 20 | PASS | CLOSED |
| TC03 — Corrective Action rejection / revision | 21 | PASS | CLOSED |
| TC04 — SCRAP destruction rejection / approval | 22 | PASS | CLOSED |
| TC05 — Verification FAILED / Rework 1 รอบ | 23 | PASS | CLOSED |
| TC06 — NOT_EFFECTIVE 1 ครั้ง / Rework | 24 | PASS | CLOSED |
| TC07 — NEED_MORE_TIME แล้ว EFFECTIVE | 25 | FAIL / หยุดเพื่อความปลอดภัย | QA_VERIFIED |

NCR 25 มี `NEED_MORE_TIME` อยู่ 4 แถวจากการรันก่อนแก้ runner จึงไม่ได้ส่ง QA Follow-up เพิ่มเติมให้ NCR นี้

## แก้ Runner และยืนยัน QA Follow-up

- อ่านแถวจาก `#qa-followup tbody` ซึ่งแสดง Follow-up ที่บันทึกไว้ ไม่ใช้ Status History นับจำนวนผล
- เรียงและตรวจเลขรอบให้ต่อเนื่อง พร้อมตรวจผลย้อนหลังตรงตาม outcome plan
- ตรวจตารางอีกครั้งก่อน submit เพื่อหยุดหากข้อมูลเปลี่ยนระหว่างวางแผนกับส่ง
- หลัง submit ตรวจจำนวนแถวและผลที่บันทึกหลัง Reload เพื่อยืนยันว่าเพิ่มเพียงหนึ่งแถว
- รอการนำทาง POST ให้เสร็จก่อนอ่านตาราง ป้องกันการเริ่ม `page.goto()` แข่งกับ redirect
- เพิ่ม scenario `TC09` ที่มี state file และ marker ถาวร เพื่อให้ retry resume NCR เดิมแทนสร้างซ้ำ

NCR 26 มีผล QA Follow-up ที่ตรวจจากตารางจริง:

| รอบ | ผล | ผลตรวจ |
|---:|---|---|
| 1 | NEED_MORE_TIME | บันทึกและ Reload แล้วยังแสดงวันนัดติดตาม |
| 2 | EFFECTIVE | บันทึกและ Reload แล้ว NCR เป็น CLOSED |

TC09 final verification: **1 passed**; `createAttempts: 1`; การรัน final audit เป็น read-only

หลักฐาน: [ผล JSON](NCR-FRESH-TC09-20261019095738.json) · [รายงาน Markdown](NCR-FRESH-TC09-20261019095738.md) · [Playwright HTML](playwright-html-qa-followup-validation-v4/index.html) · [run log](qa-followup-validation-tc09-v4.log)

## ขอบเขตข้อมูลและความปลอดภัย

- NCR 13–14 ถูก Resume/ตรวจจาก UI และอยู่ `CLOSED` ตามหลักฐานการรัน
- NCR 16–18 ใช้ทดสอบ Fresh Workflow; NCR 18 หยุดที่ `ACTION_IN_PROGRESS` เนื่องจาก Action Cycle 3 เกิน safe maximum 2
- NCR 19 ใช้ตรวจผู้รับผิดชอบ Action และปิด `CLOSED`
- NCR 20–24 ปิด `CLOSED`; NCR 25 คงเปิดที่ `QA_VERIFIED` และเก็บประวัติ Follow-up เดิม 4 รายการ
- NCR 26 ใช้ยืนยันเส้นทาง QA Follow-up และปิด `CLOSED`
- ไม่มีการ Reset ฐานข้อมูล, Migration, Seeder, การเปลี่ยน Permission หรือการแก้ Business Logic
- อ่าน NCR 25 แบบ read-only ก่อน/หลังการรัน TC09 และยืนยันว่า Status กับข้อความ 4 แถวไม่เปลี่ยน

## ข้อจำกัด/งานค้าง

- TC07 บน NCR 25 ยังไม่ผ่านและยังไม่ปิด เพราะมี Follow-up เดิมเกิน outcome plan; ไม่ควรแก้ประวัตินั้นเพื่อทำให้ test ผ่าน
- TC08 ตรวจการแสดงปุ่มแบบ read-only ได้ 2 PASS และ 1 BLOCKED (Warehouse เข้า NCR 25 ไม่ได้); การทดสอบ request ปฏิเสธ, ข้ามสถานะ และ version conflict ไม่ได้ทำ
- ยังมีความไม่ตรงกันระหว่าง README กับ runtime permission ของ Corrective Action approver; ไม่ได้เปลี่ยน permission หรือ authority
- การตรวจ PHPUnit ที่บันทึกไว้ในรายงานก่อนหน้า: 64 passed, 7 failed บน SQLite in-memory; ต้องแยกวิเคราะห์ต่อ

## ไฟล์อ้างอิง

- [สรุปผล 6 scenarios](NCR-E2E-6-SCENARIOS-SUMMARY.md)
- [ผล JSON 6 scenarios](NCR-E2E-6-SCENARIOS-RESULTS.json)
- [HTML report 6 scenarios](NCR-E2E-6-SCENARIOS-REPORT.html)
- [Runner: workflow.spec.js](../workflow.spec.js)
- [Trace final audit NCR 26](../test-results/qa-followup-validation-20261019-v4/workflow-Fresh-Scenario-TC-032fd-ithout-duplicate-follow-ups/session-trace-4-TC09-final-qa-followup-audit-NCR-26-attempt-1.zip)
- [Screenshot final audit NCR 26](../test-results/qa-followup-validation-20261019-v4/workflow-Fresh-Scenario-TC-032fd-ithout-duplicate-follow-ups/TC09-final-qa-followup-audit-NCR-26.png)
