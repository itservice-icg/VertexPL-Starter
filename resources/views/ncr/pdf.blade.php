@php
    $item = $ncr->items->first();
    $disposition = $ncr->dispositions->first();
    $followup = $ncr->qaFollowups->first();

    $part1Signoff = $ncr->formSignoffs->firstWhere('form_part', 1);
    $part2Signoff = $ncr->formSignoffs->firstWhere('form_part', 2);
    $part3Signoff = $ncr->formSignoffs->firstWhere('form_part', 3);

    $problemTypeCode = optional($ncr->problemType)->code;
    $dispositionCode = optional(optional($disposition)->type)->code;
    $dispositionStatus = optional($disposition)->status;

    $scopeCodes = preg_split(
        '/\s*,\s*/',
        (string) $ncr->legacy_form_scope_code,
        -1,
        PREG_SPLIT_NO_EMPTY
    ) ?: [];

    $box = static function (bool $checked): string {
        return '<span class="checkbox">'.($checked ? '&#9746;' : '&#9744;').'</span>';
    };

    $number = static function ($value): string {
        if ($value === null || $value === '') {
            return '';
        }

        $formatted = number_format((float) $value, 4, '.', '');

        return rtrim(rtrim($formatted, '0'), '.');
    };

    $productCode = optional($item)->product_code;
    $productName = optional($item)->product_name;
    $genericName = $ncr->generic_name;
    $poNo = optional($item)->po_no;
    $lotNo = optional($item)->lot_no;
    $customerName = optional($item)->customer_name;

    $defectQty = optional($item)->defect_quantity;
    $totalQty = optional($item)->total_quantity;
    $defectUnit = optional($item)->defect_unit_name ?: optional($item)->unit_name;
    $totalUnit = optional($item)->total_unit_name ?: optional($item)->unit_name;
    $defectPercent = optional($item)->defect_percentage;

    $part1Name = optional(optional($part1Signoff)->signer)->name
        ?: optional($part1Signoff)->signer_name_snapshot;
    $part2Name = optional(optional($part2Signoff)->signer)->name
        ?: optional($part2Signoff)->signer_name_snapshot;
    $part3Name = optional(optional($part3Signoff)->signer)->name
        ?: optional($part3Signoff)->signer_name_snapshot;

    $qaName = optional(optional($followup)->follower)->name
        ?: optional(optional($ncr->closure)->closer)->name;

    $closedAt = optional($ncr->closure)->closed_at;
    $closedDate = $closedAt
        ? \App\Support\ThaiDateFormatter::formatDate($closedAt, config('app.display_timezone'))
        : '';

    $actions = $ncr->correctiveActions->take(6)->values();
@endphp

<style>
    * { box-sizing: border-box; }

    body {
        margin: 0;
        padding: 0;
        color: #000;
        font-family: garuda;
        font-size: 7.2pt;
        line-height: 1.15;
    }

    table {
        border-collapse: collapse;
        border-spacing: 0;
    }

    .page {
        width: 100%;
    }

    .header,
    .section-shell,
    .grid {
        width: 100%;
        table-layout: fixed;
    }

    .header td,
    .grid td,
    .grid th,
    .vertical-cell {
        border: 0.18mm solid #4c4c4c;
    }

    .header td,
    .grid td,
    .grid th {
        padding: 0.45mm 0.65mm;
        vertical-align: middle;
    }

    .header {
        margin: 0;
    }

    .header-left {
        width: 15.5%;
        text-align: center;
        white-space: normal;
        font-weight: bold;
        font-size: 6pt;
        line-height: 1.1;
    }

    .header-title {
        width: 56.5%;
        text-align: center;
        font-weight: bold;
        font-size: 9pt;
        line-height: 1.15;
    }

    .header-label {
        width: 13%;
        font-weight: bold;
    }

    .header-value {
        width: 15%;
        font-weight: normal;
    }

    .section-shell {
        margin: 0;
    }

    .section-shell > tbody > tr > td {
        padding: 0;
        vertical-align: top;
    }

    .vertical-cell {
        width: 3.55%;
        padding: 0;
        text-align: center;
        font-weight: bold;
        background: #f3f3f3;
        vertical-align: middle !important;
    }

    .vertical-text {
        writing-mode: tb-rl;
        white-space: nowrap;
        text-align: center;
        margin: 0 auto;
        font-size: 7pt;
    }

    .section-body {
        width: 96.45%;
        border: 0;
        padding: 0 !important;
    }

    .section-title,
    .subhead,
    .action-head {
        background: #d9dde3;
        font-weight: bold;
    }

    .section-title {
        font-size: 7.3pt;
    }

    .subhead {
        font-size: 7pt;
    }

    .label {
        font-weight: bold;
    }

    .center {
        text-align: center;
    }

    .right {
        text-align: right;
    }

    .top {
        vertical-align: top !important;
    }

    .nowrap {
        white-space: nowrap;
    }

    .checkbox {
        font-family: dejavusans;
        font-size: 8pt;
        font-weight: normal;
    }

    .field {
        min-height: 3mm;
        border-bottom: 0.13mm dotted #8b8b8b;
        padding-bottom: 0.2mm;
    }

    .lined-area {
        padding: 0 !important;
        vertical-align: top !important;
    }

    .line-table {
        width: 100%;
        table-layout: fixed;
        border-collapse: collapse;
    }

    .line-table td {
        border: 0;
        border-bottom: 0.13mm dotted #8b8b8b;
        padding: 0.35mm 0.7mm;
        vertical-align: top;
    }

    .line-table tr:last-child td {
        border-bottom: 0;
    }

    .signature {
        text-align: right;
        white-space: nowrap;
        font-size: 6.8pt;
    }

    .signature-center {
        text-align: center;
        white-space: nowrap;
        font-size: 6.8pt;
    }

    .muted {
        color: #333;
    }

    .h44 { height: 4.4mm; }
    .h48 { height: 4.8mm; }
    .h52 { height: 5.2mm; }
    .h57 { height: 5.7mm; }
    .h61 { height: 6.1mm; }
    .h65 { height: 6.5mm; }

    .problem-area { height: 26.2mm; }
    .detail-area { height: 15.7mm; }
    .followup-area { height: 20.9mm; }

    .action-head {
        text-align: center;
        font-weight: bold;
    }

    .action-row td {
        height: 6.05mm;
        vertical-align: top;
        padding-top: 0.55mm;
    }

    .footer {
        width: 100%;
        text-align: right;
        font-size: 6pt;
        padding-top: 1mm;
    }
</style>

<div class="page">
    <table class="header">
        <tr style="height: 8.2mm;">
            <td class="header-left" rowspan="3">
                @foreach ($formScopes as $formScope)
                    {!! $box(in_array($formScope->code, $scopeCodes, true)) !!} {{ $formScope->name_th }}@if (! $loop->last)&nbsp;&nbsp;@endif
                @endforeach
            </td>
            <td class="header-title" rowspan="3">
                รายงานผลิตภัณฑ์ที่ไม่เป็นไปตามข้อกำหนด /
                Non Conformity Report (NCR)
            </td>
            <td class="header-label">เลขที่ NCR:</td>
            <td class="header-value">{{ $ncr->ncr_no }}</td>
        </tr>
        <tr style="height: 8.2mm;">
            <td class="header-label">วันที่ออก :</td>
            <td class="header-value">{{ \App\Support\ThaiDateFormatter::formatDate($ncr->report_date) }}</td>
        </tr>
        <tr style="height: 8.2mm;">
            <td class="header-label">กำหนดเสร็จ NCR :</td>
            <td class="header-value">{{ \App\Support\ThaiDateFormatter::formatDate($ncr->due_date) ?: 'ยังไม่กำหนด' }}</td>
        </tr>
    </table>

    {{-- ส่วนที่ 1 --}}
    <table class="section-shell">
        <tr>
            <td class="vertical-cell">
                <div class="vertical-text">ส่วนรายงานและการแก้ไขปรับปรุงสินค้า</div>
            </td>
            <td class="section-body">
                <table class="grid">
                    <colgroup>
                        <col style="width:3.211%"><col style="width:1.835%"><col style="width:3.211%">
                        <col style="width:5.963%"><col style="width:5.963%"><col style="width:1.835%">
                        <col style="width:3.211%"><col style="width:5.963%"><col style="width:5.963%">
                        <col style="width:1.835%"><col style="width:3.211%"><col style="width:5.963%">
                        <col style="width:5.963%"><col style="width:1.835%"><col style="width:3.211%">
                        <col style="width:5.963%"><col style="width:5.963%"><col style="width:1.835%">
                        <col style="width:3.211%"><col style="width:5.963%"><col style="width:5.963%">
                        <col style="width:5.963%"><col style="width:5.963%">
                    </colgroup>

                    <tr class="h48">
                        <td colspan="23" class="section-title">
                            ส่วนที่ 1 : การแจ้ง / รายละเอียดผลิตภัณฑ์ที่ไม่เป็นไปตามข้อกำหนด
                        </td>
                    </tr>

                    <tr>
                        <td colspan="23" style="padding:0;">
                            <table class="grid">
                                <tr>
                                    <td class="label" style="width:16%;" rowspan="2">ประเภทที่พบ</td>
                                    <td style="width:28%;">{!! $box($problemTypeCode === 'RAW_MATERIAL') !!} วัตถุดิบ/ส่วนผสม</td>
                                    <td style="width:28%;">{!! $box($problemTypeCode === 'PACKAGING_SUPPLY') !!} บรรจุภัณฑ์ / Supply</td>
                                    <td style="width:28%;">{!! $box($problemTypeCode === 'WIP') !!} สินค้าระหว่างผลิต</td>
                                </tr>
                                <tr>
                                    <td>{!! $box($problemTypeCode === 'REPACK') !!} สินค้า Repack</td>
                                    <td>{!! $box($problemTypeCode === 'FINISHED_GOODS') !!} สินค้าสำเร็จรูป</td>
                                    <td>{!! $box($problemTypeCode === 'CUSTOMER_PRODUCT') !!} ผลิตภัณฑ์ลูกค้า</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr class="h52">
                        <td colspan="2" class="label">รหัสสินค้า</td>
                        <td colspan="5"><div class="field">{{ $productCode }}</div></td>
                        <td colspan="2" class="label">ชื่อสามัญ</td>
                        <td colspan="6"><div class="field">{{ $genericName }}</div></td>
                        <td colspan="2" class="label">ชื่อสินค้า</td>
                        <td colspan="6"><div class="field">{{ $productName }}</div></td>
                    </tr>

                    <tr class="h52">
                        <td colspan="2" class="label">PO NO.</td>
                        <td colspan="4"><div class="field">{{ $poNo }}</div></td>
                        <td colspan="2" class="label">Lot No.</td>
                        <td colspan="5"><div class="field">{{ $lotNo }}</div></td>
                        <td colspan="2" class="label">ชื่อลูกค้า</td>
                        <td colspan="8"><div class="field">{{ $customerName }}</div></td>
                    </tr>

                    <tr class="h52">
                        <td colspan="2" class="label">จำนวนที่พบ</td>
                        <td colspan="3"><div class="field">{{ $number($defectQty) }}</div></td>
                        <td colspan="2" class="label">หน่วยนับ</td>
                        <td colspan="2"><div class="field">{{ $defectUnit }}</div></td>
                        <td colspan="3" class="label">จากจำนวนทั้งหมด</td>
                        <td colspan="3"><div class="field">{{ $number($totalQty) }}</div></td>
                        <td colspan="2" class="label">หน่วยนับ</td>
                        <td colspan="2"><div class="field">{{ $totalUnit }}</div></td>
                        <td colspan="2" class="label">คิดเป็นร้อยละ (%)</td>
                        <td colspan="2"><div class="field">{{ $defectPercent !== null ? number_format($defectPercent, 2) : '' }}</div></td>
                    </tr>

                    <tr class="h52">
                        <td colspan="2" class="label">พื้นที่ที่พบ</td>
                        <td colspan="9"><div class="field">{{ $ncr->found_location }}</div></td>
                        <td colspan="2" class="label">เวลาที่พบ</td>
                        <td colspan="10"><div class="field">{{ $ncr->found_time ? substr((string) $ncr->found_time, 0, 5) : '' }}</div></td>
                    </tr>

                    <tr class="h48">
                        <td colspan="23" class="label">ลักษณะผิดปกติที่พบ(อธิบาย) :</td>
                    </tr>

                    <tr>
                        <td colspan="23" class="lined-area problem-area">
                            <table class="line-table">
                                <tr style="height:9.5mm"><td>{{ $ncr->problem_description }}</td></tr>
                                <tr style="height:4.2mm"><td>&nbsp;</td></tr>
                                <tr style="height:4.2mm"><td>&nbsp;</td></tr>
                                <tr style="height:4.2mm"><td>&nbsp;</td></tr>
                                <tr style="height:4.1mm"><td>&nbsp;</td></tr>
                            </table>
                        </td>
                    </tr>

                    <tr class="h52">
                        <td colspan="23" class="signature">
                            ลงชื่อ..........................................................
                            {{ optional($ncr->reporter)->name }}
                            ผู้พบ/ผู้แจ้ง
                        </td>
                    </tr>

                    <tr>
                        <td colspan="23" class="label">ข้อเสนอแนะการจัดการเบื้องต้น :</td>
                    </tr>
                    <tr>
                        <td colspan="23" style="padding:0;">
                            @php
                                $dispositionChoices = [
                                    'ACCEPT_AS_IS' => 'เสนอขอรับได้ตามสภาพ',
                                    'HOLD_STATUS' => 'เสนอคัดสถานะ',
                                    'SEND_TO_RD' => 'ส่งให้ RD',
                                    'REWORK' => 'REWORK / ให้แก้ไขงาน',
                                    'SUPPLIER_COORDINATION' => 'ส่งให้จัดซื้อ-ประสานงานผู้ผลิต',
                                    'CUSTOMER_COORDINATION' => 'ส่งฝ่ายขาย-ประสานงานลูกค้า',
                                    'CONDITIONAL_ACCEPTANCE' => 'เสนอขอรับภายใต้เงื่อนไข',
                                    'RETURN_TO_SUPPLIER' => 'RETURN / ให้ส่งคืน/แลกเปลี่ยน/ลดหนี้/ชดเชย',
                                    'SCRAP' => 'SCRAP / ให้เสนอทำลาย',
                                    'RECALL' => 'RECALL',
                                    'OTHER' => 'OTHERS / อื่น ๆ (ระบุ)',
                                ];
                            @endphp
                            <table class="grid">
                                @foreach (array_chunk($dispositionChoices, 4, true) as $choices)
                                    <tr>
                                        @foreach ($choices as $code => $label)
                                            <td style="width:25%;">{!! $box($dispositionCode === $code) !!} {{ $label }}</td>
                                        @endforeach
                                        @for ($i = count($choices); $i < 4; $i++)
                                            <td style="width:25%;">&nbsp;</td>
                                        @endfor
                                    </tr>
                                @endforeach
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="23" class="lined-area detail-area">
                            <table class="line-table">
                                <tr style="height:5.3mm"><td>{{ optional($disposition)->disposition_detail }}</td></tr>
                                <tr style="height:5.2mm"><td>กำหนดเสร็จตามข้อเสนอ Disposition: {{ \App\Support\ThaiDateFormatter::formatDate(optional($disposition)->proposed_due_date) ?: 'ยังไม่กำหนด' }}</td></tr>
                                <tr style="height:5.2mm"><td>&nbsp;</td></tr>
                            </table>
                        </td>
                    </tr>

                    <tr class="h61">
                        <td colspan="11" class="signature-center">
                            ลงชื่อ....................................
                            {{ $part1Name }}
                            ผู้จัดการแผนกที่พบปัญหา
                        </td>
                        <td colspan="12" class="signature-center">
                            ลงชื่อ....................................
                            ผู้จัดการฝ่าย/รองกรรมการผู้จัดการสายงานโรงงานที่พบปัญหา
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- ส่วนที่ 2 --}}
    <table class="section-shell">
        <tr>
            <td class="vertical-cell">
                <div class="vertical-text">ส่วนการจัดการกับ NCR</div>
            </td>
            <td class="section-body">
                <table class="grid">
                    <tr class="h48">
                        <td style="width:48%" class="section-title">
                            ส่วนที่ 2 : การจัดการกับผลิตภัณฑ์ที่ไม่เป็นไปตามข้อกำหนด
                        </td>
                        <td style="width:52%" class="section-title center">
                            I = รอตรวจสอบ / R = รอแก้ไขงาน / V = รอส่งคืนผู้ขาย / S = รอเสนอทำลาย
                        </td>
                    </tr>

                    <tr class="h48">
                        <td colspan="2" class="label">
                            2.1 การพิจารณาดำเนินการจัดการผลิตภัณฑ์ที่ไม่เป็นไปตามข้อกำหนด
                        </td>
                    </tr>

                    <tr class="h65">
                        <td colspan="2">
                            <table style="width:100%; table-layout:fixed;">
                                <tr>
                                    <td style="width:32%; border:0;">
                                        {!! $box($dispositionStatus === 'APPROVED') !!}
                                        เห็นควรอนุมัติตามที่นำเสนอ
                                    </td>
                                    <td style="width:43%; border:0;">
                                        {!! $box($dispositionStatus === 'REJECTED') !!}
                                        เห็นควรไม่อนุมัติตามที่นำเสนอ / ดำเนินการแก้ไขใหม่
                                    </td>
                                    <td style="width:25%; border:0;">
                                        {!! $box(! in_array($dispositionStatus, ['APPROVED', 'REJECTED'], true)) !!}
                                        OTHERS / อื่น ๆ (ระบุ)
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td colspan="2" class="lined-area" style="height:20.9mm;">
                            <table class="line-table">
                                <tr style="height:5.3mm"><td>{{ optional($disposition)->rejection_reason }}</td></tr>
                                <tr style="height:5.2mm"><td>&nbsp;</td></tr>
                                <tr style="height:5.2mm"><td>&nbsp;</td></tr>
                                <tr style="height:5.2mm"><td>&nbsp;</td></tr>
                            </table>
                        </td>
                    </tr>

                    <tr class="h57">
                        <td colspan="2" class="signature">
                            ลงชื่อ..........................................................
                            {{ $part2Name }}
                            รองกรรมการผจก.สายงานโรงงาน/กรรมการผู้จัดการ
                        </td>
                    </tr>

                    <tr class="h52">
                        <td colspan="2" class="label">
                            2.2 การดำเนินการจัดการผลิตภัณฑ์ที่ไม่เป็นไปตามข้อกำหนด เพื่อแก้ไขและปรับปรุง
                        </td>
                    </tr>

                    <tr>
                        <td colspan="2" style="padding:0;">
                            <table class="grid" style="border:0;">
                                <tr class="h52 action-head">
                                    <th style="width:52%">รายละเอียดการปฏิบัติการแก้ไข</th>
                                    <th style="width:17%">แผนก/ฝ่าย</th>
                                    <th style="width:13%">กำหนดเสร็จ Action</th>
                                    <th style="width:18%">ลงชื่อ ผจก.แผนก/ฝ่าย</th>
                                </tr>

                                @for ($i = 0; $i < 6; $i++)
                                    @php
                                        $action = $actions->get($i);
                                    @endphp
                                    <tr class="action-row">
                                        <td>
                                            @if ($action)
                                                {{ $action->action_no }}. {{ $action->corrective_action_detail }}
                                            @endif
                                        </td>
                                        <td class="center">
                                            {{ $action ? optional($action->responsibleDepartment)->code : '' }}
                                        </td>
                                        <td class="center nowrap">
                                            {{ \App\Support\ThaiDateFormatter::formatDate($action ? $action->due_date : null) ?: 'ยังไม่กำหนด' }}
                                        </td>
                                        <td class="center">
                                            {{ $action ? optional($action->owner)->name : '' }}
                                        </td>
                                    </tr>
                                @endfor
                            </table>
                        </td>
                    </tr>

                    <tr class="h57">
                        <td colspan="2">&nbsp;</td>
                    </tr>

                    <tr class="h52">
                        <td colspan="2" class="signature">
                            ลงชื่อ..........................................................
                            {{ $part2Name }}
                            รองกรรมการผจก.สายงานโรงงาน/กรรมการผู้จัดการ (รับทราบ)
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- ส่วนที่ 3 --}}
    <table class="section-shell">
        <tr>
            <td class="vertical-cell">
                <div class="vertical-text">ส่วนติดตามผล</div>
            </td>
            <td class="section-body">
                <table class="grid">
                    <tr class="h48">
                        <td class="section-title">
                            ส่วนที่ 3 : การติดตามผลการดำเนินการเพื่อให้มั่นใจว่าองค์กรสามารถควบคุมผลิตภัณฑ์ที่ไม่เป็นไปตามข้อกำหนดได้ / การปิดรายงาน
                        </td>
                    </tr>

                    <tr>
                        <td class="lined-area followup-area">
                            <table class="line-table">
                                <tr style="height:5.3mm"><td>{{ optional($followup)->followup_detail ?: optional($ncr->closure)->closure_note }}</td></tr>
                                <tr style="height:5.2mm"><td>&nbsp;</td></tr>
                                <tr style="height:5.2mm"><td>&nbsp;</td></tr>
                                <tr style="height:5.2mm"><td>&nbsp;</td></tr>
                            </table>
                        </td>
                    </tr>

                    <tr class="h52">
                        <td class="signature">
                            ลงชื่อ........................................
                            {{ $qaName }}
                            ผจก.แผนก QA
                            &nbsp;&nbsp; วันที่ {{ $closedDate }}
                        </td>
                    </tr>

                    <tr class="h61">
                        <td>
                            <table style="width:100%; table-layout:fixed;">
                                <tr>
                                    <td style="width:10%; border:0;">
                                        {!! $box($ncr->current_status === 'CLOSED') !!} ปิด NCR
                                    </td>
                                    <td style="width:27%; border:0;">
                                        {!! $box($ncr->current_status !== 'CLOSED') !!}
                                        ไม่ปิด NCR ติดตามครั้งที่ 2
                                    </td>
                                    <td style="width:15%; border:0;">
                                        วันที่ {{ \App\Support\ThaiDateFormatter::formatDate(optional($followup)->next_followup_date) ?: 'ยังไม่กำหนด' }}
                                    </td>
                                    <td style="width:48%; border:0;" class="right">
                                        ลงชื่อ................................
                                        {{ $part3Name }}
                                        รองกรรมการผจก.สายงานโรงงาน
                                        &nbsp; วันที่ {{ $closedDate }}
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr style="height:2.2mm;">
                        <td>&nbsp;</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <div class="footer">FM-DQA-06 / Rev. 03, 04-01-66</div>
</div>
