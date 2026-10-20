<x-app-layout>
    <?php
    /** @var \App\Models\NcrReport $ncr */
    $editing = $ncr->exists;
    ?>

    <div class="ncr-page ncr-form ncr-create-page w-full space-y-4 px-3 py-3 sm:px-5 sm:py-4">

        <header class="ncr-create-heading">
            <h1>{{ $editing ? 'แก้ไข '.$ncr->ncr_no : 'สร้าง Non-Conformance Report ใหม่' }}</h1>
            <p>เตรียมวันที่พบ หน่วยงาน สินค้า เลข Lot จำนวนที่พบ รายละเอียดปัญหา และหลักฐาน · <span>* ข้อมูลบังคับ</span></p>
            @if($editing && ! empty($returnReason))
                <p class="mt-3 rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-950"><strong>เหตุผลที่ส่งกลับให้แก้ไข:</strong> {{ $returnReason }}</p>
            @endif
        </header>
        <form method="POST" enctype="multipart/form-data"
            action="{{ $editing ? route('ncr.update', $ncr) : route('ncr.store') }}"
            class="ncr-create-form space-y-4"
            data-ncr-submit>
            @csrf

            @if($editing)
            @method('PUT')

            <input
                type="hidden"
                name="version_no"
                value="{{ $ncr->version_no }}">
            @endif

            <x-auth-validation-errors :errors="$errors" />

            <?php
            $report = $ncr;

            $v = function ($key, $default = '') use ($editing, $report) {
                $value = old(
                    $key,
                    $editing ? $report->getAttribute($key) : $default
                );

                if ($value instanceof \DateTimeInterface) {
                    return $key === 'found_time'
                        ? $value->format('H:i')
                        : $value->format('Y-m-d');
                }

                if ($key === 'found_time' && is_string($value) && preg_match('/^\d{2}:\d{2}(?::\d{2}(?:\.\d+)?)?$/', $value)) {
                    return substr($value, 0, 5);
                }

                return $value;
            };

            $initialItems = old('items');

            if ($initialItems === null) {
                $initialItems = $editing
                    ? $ncr->items
                    ->map(fn($item) => $item->only([
                        'id',
                        'product_id',
                        'customer_id',
                        'product_code',
                        'product_name',
                        'lot_no',
                        'po_no',
                        'batch_no',
                        'customer_name',
                        'defect_quantity',
                        'defect_unit_name',
                        'total_quantity',
                        'total_unit_name',
                        'unit_name',
                        'problem_description',
                    ]))
                    ->values()
                    ->all()
                    : [];
            }
            ?>

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                {{-- ข้อมูลวันที่ --}}
                <div id="ncr-context" class="ncr-form-section ncr-form-section--compact scroll-mt-28 p-3 sm:p-4">
                    <div class="ncr-form-section__header ncr-compact-section-header -mx-3 -mt-3 mb-3 sm:-mx-4 sm:-mt-4">
                        <p class="ncr-stage-kicker">Section 01 · Context</p>
                        <h2 class="ncr-section-title mt-1">ข้อมูลเหตุการณ์</h2>
                        <p class="ncr-section-description mt-1">วันที่และขอบเขตของแบบฟอร์ม NCR</p>
                    </div>
                    <div class="ncr-compact-grid grid grid-cols-1 gap-3 sm:grid-cols-2">

                        <div>
                            <x-label
                                value="AI / UP"
                                class="text-sm font-semibold text-slate-700" />

                            @php
                            $scopeCodes = array_slice(old(
                            'legacy_form_scope_codes',
                            filled($v('legacy_form_scope_code'))
                            ? explode(',', $v('legacy_form_scope_code'))
                            : []
                            ), 0, 1);
                            @endphp

                            <div class="mt-2 flex min-h-[42px] items-center gap-5 rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-900 shadow-sm">
                                @foreach (['AI', 'UP'] as $scopeCode)
                                <label class="inline-flex cursor-pointer items-center gap-2">
                                    <input
                                        type="checkbox"
                                        name="legacy_form_scope_codes[]"
                                        value="{{ $scopeCode }}"
                                        @checked(in_array($scopeCode, $scopeCodes, true))
                                        onchange="document.querySelectorAll('input[name=&quot;legacy_form_scope_codes[]&quot;]').forEach((input) => { if (input !== this) input.checked = false; })"
                                        class="rounded border-slate-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                    <span>{{ $scopeCode }}</span>
                                </label>
                                @endforeach
                            </div>
                        </div>

                        <div>
                            <x-label
                                value="วันที่ออก"
                                class="text-sm font-semibold text-slate-700" />

                            <x-input
                                type="date"
                                name="report_date"
                                class="mt-2 block min-h-[42px] w-full"
                                value="{{ $v('report_date', date('Y-m-d')) }}"
                                required />
                        </div>

                        <div>
                            <x-label
                                value="วันที่พบ"
                                class="text-sm font-semibold text-slate-700" />

                            <x-input
                                type="date"
                                name="found_date"
                                class="mt-2 block min-h-[42px] w-full"
                                value="{{ $v('found_date', date('Y-m-d')) }}"
                                required />
                        </div>

                        <div>
                            <x-label
                                value="เวลาที่พบ"
                                class="text-sm font-semibold text-slate-700" />

                            <x-input
                                type="time"
                                name="found_time"
                                class="mt-2 block min-h-[42px] w-full"
                                value="{{ $v('found_time', $editing ? '' : now()->format('H:i')) }}" />
                        </div>

                    </div>
                @if(! $editing && ! auth()->user()->department_id)

                <div class="mt-3 border-t border-slate-200 pt-3">

                    <x-label
                        value="หน่วยงานผู้รายงาน"
                        class="text-sm font-semibold text-slate-700" />

                    <p class="mb-2 mt-1 text-xs text-slate-500">
                        บัญชีนี้ยังไม่ได้ผูกหน่วยงาน กรุณาเลือกหน่วยงานสำหรับ NCR นี้
                    </p>

                    <select
                        name="reporter_department_id"
                        class="block min-h-[42px] w-full rounded-lg border-slate-300 bg-white text-sm text-slate-900 shadow-sm transition focus:border-indigo-500 focus:ring-indigo-500"
                        required>
                        <option value="">เลือกหน่วยงาน</option>

                        @foreach($departments as $x)
                        <option
                            value="{{ $x->id }}"
                            {{ (string) old('reporter_department_id') === (string) $x->id ? 'selected' : '' }}>
                            {{ $x->code }} - {{ $x->name_th }}
                        </option>
                        @endforeach
                    </select>

                </div>

                @endif
                </div>





                {{-- ประเภท / ความรุนแรง / หน่วยงาน --}}
                <div id="ncr-classification" class="ncr-form-section ncr-form-section--compact scroll-mt-28 p-3 sm:p-4">
                    <div class="ncr-form-section__header ncr-compact-section-header -mx-3 -mt-3 mb-3 sm:-mx-4 sm:-mt-4">
                        <p class="ncr-stage-kicker">Section 02 · Classification</p>
                        <h2 class="ncr-section-title mt-1">ประเภทและหน่วยงาน</h2>
                        <p class="ncr-section-description mt-1">จัดหมวดหมู่เหตุการณ์และระบุหน่วยงานที่พบปัญหา</p>
                    </div>

                    <div class="ncr-compact-grid grid grid-cols-1 gap-3 sm:grid-cols-2">

                        <div class="sm:col-span-2">
                            <x-label
                                value="ประเภทที่พบ"
                                class="text-sm font-semibold text-slate-700" />

                            <select
                                name="problem_type_id"
                                class="mt-2 block min-h-[42px] w-full rounded-lg border border-slate-300 bg-white text-sm text-slate-900 shadow-sm transition focus:border-indigo-500 focus:ring-indigo-500"
                                required>
                                <option value="">เลือกประเภทที่พบ</option>

                                @foreach($problemTypes as $x)
                                <option
                                    value="{{ $x->id }}"
                                    {{ (string) $v('problem_type_id') === (string) $x->id ? 'selected' : '' }}>
                                    {{ $x->name_th }}
                                </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <x-label
                                value="ความรุนแรง"
                                class="text-sm font-semibold text-slate-700" />

                            <select
                                name="severity_level_id"
                                class="mt-2 block min-h-[42px] w-full rounded-lg border border-slate-300 bg-white text-sm text-slate-900 shadow-sm transition focus:border-indigo-500 focus:ring-indigo-500"
                                required>
                                <option value="">เลือก</option>

                                @foreach($severityLevels as $x)
                                <option
                                    value="{{ $x->id }}"
                                    {{ (string) $v('severity_level_id') === (string) $x->id ? 'selected' : '' }}>
                                    {{ $x->name_th }}
                                </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <x-label
                                value="หน่วยงานที่พบปัญหา"
                                class="text-sm font-semibold text-slate-700" />

                            <select
                                name="related_department_id"
                                class="mt-2 block min-h-[42px] w-full rounded-lg border border-slate-300 bg-white text-sm text-slate-900 shadow-sm transition focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">เลือกหน่วยงานที่พบปัญหา</option>

                                @foreach($departments as $x)
                                <option
                                    value="{{ $x->id }}"
                                    {{ (string) $v('related_department_id', $editing ? null : (auth()->user()->department_id ?: old('reporter_department_id'))) === (string) $x->id ? 'selected' : '' }}>
                                    {{ $x->code }} - {{ $x->name_th }}
                                </option>
                                @endforeach
                            </select>
                        </div>

                    </div>
                </div>


                {{-- ข้อมูลทั่วไป --}}
                <div id="ncr-reference" class="ncr-form-section ncr-form-section--compact scroll-mt-28 p-3 sm:p-4 lg:col-span-2">
                    <div class="ncr-form-section__header ncr-compact-section-header -mx-3 -mt-3 mb-3 sm:-mx-4 sm:-mt-4">
                        <p class="ncr-stage-kicker">Section 03 · Reference</p>
                        <h2 class="ncr-section-title mt-1">ข้อมูลอ้างอิง</h2>
                        <p class="ncr-section-description mt-1">ข้อมูลสินค้า ผู้ผลิต พื้นที่ และกระบวนการที่เกี่ยวข้อง</p>
                    </div>

                    <div class="ncr-compact-grid ncr-reference-grid grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">

                        @foreach([
                        'company_name' => 'บริษัท',
                        'generic_name' => 'ชื่อสามัญ',
                        'reference_no' => 'เลขที่อ้างอิง',
                        'found_location' => 'พื้นที่ที่พบ',
                        'machine_or_process' => 'เครื่องจักร/กระบวนการ'
                        ] as $key => $label)

                        <div>
                            <x-label
                                :value="$label"
                                class="text-sm font-semibold text-slate-700" />

                            <x-input
                                :name="$key"
                                class="mt-2 block min-h-[42px] w-full"
                                :value="$v($key)" />
                        </div>

                        @endforeach

                        <div>
                            <x-label for="supplier_id" value="ผู้ผลิต/ผู้ขาย" class="text-sm font-semibold text-slate-700" />
                            <select id="supplier_id" name="supplier_id" class="mt-2 block min-h-[42px] w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">ไม่ระบุผู้ผลิต/ผู้ขาย</option>
                                @foreach($suppliers as $supplier)
                                    <option value="{{ $supplier->id }}" {{ (string) $v('supplier_id') === (string) $supplier->id ? 'selected' : '' }}>{{ $supplier->code }} — {{ $supplier->name_th }}{{ $supplier->is_active && ! $supplier->trashed() ? '' : ' (ปิดใช้งาน)' }}</option>
                                @endforeach
                            </select>
                            @if(! $v('supplier_id') && $v('supplier_name'))
                                <p class="mt-1 text-xs text-slate-500">ข้อมูลเดิมที่ยังไม่ผูกทะเบียน: {{ $v('supplier_name') }}</p>
                            @endif
                        </div>

                        <div>
                            <x-label
                                value="วันที่ผลิต"
                                class="text-sm font-semibold text-slate-700" />

                            <x-input
                                type="date"
                                name="production_date"
                                class="mt-2 block min-h-[42px] w-full"
                                value="{{ $v('production_date') }}" />
                        </div>

                    </div>

                </div>
            </div>

            {{-- รายการผลิตภัณฑ์ --}}
            <section
                x-data='{ 
                    items: {!! json_encode($initialItems, JSON_HEX_APOS | JSON_HEX_QUOT) !!},
                    products: {!! json_encode($products->map(fn($product) => ['id' => $product->id, 'code' => $product->code, 'name' => $product->name_th])->values(), JSON_HEX_APOS | JSON_HEX_QUOT) !!},
                    customers: {!! json_encode($customers->map(fn($customer) => ['id' => $customer->id, 'name' => $customer->name_th])->values(), JSON_HEX_APOS | JSON_HEX_QUOT) !!},
                    editingIndex: null,
                    itemDialogOpen: false,
                    itemDraft: {},
                    emptyItem() {
                        return {
                            id: null,
                            product_id: "",
                            customer_id: "",
                            product_code: "",
                            product_name: "",
                            lot_no: "",
                            po_no: "",
                            batch_no: "",
                            customer_name: "",
                            defect_quantity: 0,
                            defect_unit_name: "",
                            total_quantity: 1,
                            total_unit_name: "",
                            unit_name: "",
                            problem_description: "",
                        };
                    },
                    selectProduct(value) {
                        const previousProductId = this.itemDraft.product_id;
                        const product = this.products.find((option) => String(option.id) === String(value));
                        this.itemDraft.product_id = value || null;
                        if (product) {
                            this.itemDraft.product_code = product.code;
                            this.itemDraft.product_name = product.name;
                        } else if (previousProductId || !this.itemDraft.id) {
                            this.itemDraft.product_code = "";
                            this.itemDraft.product_name = "";
                        }
                    },
                    selectCustomer(value) {
                        const previousCustomerId = this.itemDraft.customer_id;
                        const customer = this.customers.find((option) => String(option.id) === String(value));
                        this.itemDraft.customer_id = value || null;
                        if (customer) this.itemDraft.customer_name = customer.name;
                        else if (previousCustomerId || !this.itemDraft.id) this.itemDraft.customer_name = "";
                    },
                    addItem() {
                        if (this.items.length >= 50) return;
                        this.editingIndex = null;
                        this.itemDraft = this.emptyItem();
                        this.$nextTick(() => {
                            this.$refs.itemDialog.showModal();
                            this.itemDialogOpen = true;
                        });
                    },
                    editItem(index) {
                        this.editingIndex = index;
                        this.itemDraft = { ...this.items[index] };
                        this.$nextTick(() => {
                            this.$refs.itemDialog.showModal();
                            this.itemDialogOpen = true;
                        });
                    },
                    saveItem() {
                        if (!this.$refs.itemEditor.checkValidity()) {
                            this.$refs.itemEditor.reportValidity();
                            return;
                        }

                        const savedItem = { ...this.itemDraft };

                        if (this.editingIndex === null) {
                            this.items.push(savedItem);
                        } else {
                            this.items[this.editingIndex] = savedItem;
                        }

                        this.$refs.itemDialog.close();
                        this.itemDialogOpen = false;
                    },
                    removeItem(index) {
                        if (this.items.length > 1) this.items.splice(index, 1);
                    }
                }'
                id="ncr-products"
                class="ncr-form-items ncr-form-section scroll-mt-28 overflow-hidden rounded-xl border border-slate-200 bg-white">

                <div class="ncr-form-items__header border-b border-slate-200 px-3 py-3 sm:px-4">

                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h2 class="text-base font-bold text-slate-900 sm:text-lg">
                                ข้อมูลผลิตภัณฑ์ / รายงาน
                            </h2>

                            <p class="mt-1 text-xs leading-5 text-slate-500 sm:text-sm">
                                รายการผลิตภัณฑ์ที่ไม่เป็นไปตามข้อกำหนด
                            </p>
                        </div>

                        <button
                            type="button"
                            x-on:click="addItem()"
                            x-bind:disabled="items.length >= 50"
                            x-bind:aria-disabled="items.length >= 50"
                            class="shrink-0 rounded-md border border-indigo-200 bg-indigo-50 px-3 py-2 text-sm font-semibold text-indigo-700 transition hover:bg-indigo-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-1 disabled:cursor-not-allowed disabled:opacity-50">
                            + เพิ่มรายการ
                        </button>
                    </div>

                </div>


                <div class="ncr-form-items__body bg-slate-50/70 p-2 sm:p-3">

                    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
                        <table class="ncr-items-table w-full border-collapse text-left text-sm">
                            <caption class="sr-only">รายการผลิตภัณฑ์ที่ไม่เป็นไปตามข้อกำหนดใน NCR</caption>
                            <thead class="bg-slate-50">
                                <tr>
                                    <th scope="col" class="w-16 px-2 py-3 text-center font-semibold text-slate-700">รายการ</th>
                                    <th scope="col" class="px-3 py-3 font-semibold text-slate-700">สินค้า</th>
                                    <th scope="col" class="hidden px-3 py-3 font-semibold text-slate-700 md:table-cell">เลขอ้างอิง</th>
                                    <th scope="col" class="px-3 py-3 font-semibold text-slate-700">จำนวนที่พบ</th>
                                    <th scope="col" class="hidden px-3 py-3 text-center font-semibold text-slate-700 sm:table-cell">ร้อยละ</th>
                                    <th scope="col" class="w-36 px-3 py-3 text-center font-semibold text-slate-700">จัดการ</th>
                                </tr>
                            </thead>

                            <tbody class="divide-y divide-slate-200">
                                <tr x-show="items.length === 0">
                                    <td colspan="6" class="px-4 py-10 text-center">
                                        <p class="font-semibold text-slate-700">ยังไม่มีรายการผลิตภัณฑ์</p>
                                        <p class="mt-1 text-sm text-slate-500">เลือก “เพิ่มรายการ” เพื่อบันทึกสินค้าและรายละเอียดที่พบ</p>
                                    </td>
                                </tr>
                                <template
                                    x-for="(item, index) in items"
                                    :key="index">

                                    <tr class="align-middle hover:bg-slate-50/70">
                                        <th scope="row" class="px-2 py-3 text-center font-semibold text-slate-700" x-text="index + 1"></th>
                                        <td class="px-3 py-3">
                                            <p class="font-semibold text-slate-900" x-text="item.product_name || 'ยังไม่ได้ระบุชื่อสินค้า'"></p>
                                            <p class="mt-1 text-xs text-slate-500" x-text="item.product_code || 'ไม่มีรหัสสินค้า'"></p>
                                        </td>
                                        <td class="hidden px-3 py-3 text-slate-600 md:table-cell">
                                            <p x-text="item.lot_no ? 'Lot: ' + item.lot_no : 'Lot: -'"></p>
                                            <p class="mt-1 text-xs" x-text="[item.po_no && 'PO: ' + item.po_no, item.batch_no && 'Batch: ' + item.batch_no].filter(Boolean).join(' · ') || 'PO / Batch: -'"></p>
                                        </td>
                                        <td class="px-3 py-3 text-slate-700">
                                            <span class="font-semibold" x-text="item.defect_quantity"></span>
                                            <span x-text="item.defect_unit_name || ''"></span>
                                            <p class="mt-1 text-xs text-slate-500" x-text="'จาก ' + item.total_quantity + (item.total_unit_name ? ' ' + item.total_unit_name : '')"></p>
                                        </td>
                                        <td class="hidden px-3 py-3 text-center font-semibold text-slate-700 sm:table-cell"
                                            x-text="Number(item.total_quantity) > 0 ? ((Number(item.defect_quantity) / Number(item.total_quantity)) * 100).toFixed(2) + '%' : '-'">-</td>
                                        <td class="px-3 py-3 text-center">
                                            <div class="flex flex-wrap items-center justify-center gap-1">
                                                <button type="button" x-on:click="editItem(index)"
                                                    :aria-label="'แก้ไขรายการ ' + (index + 1)"
                                                    class="rounded-md px-2 py-2 text-sm font-semibold text-indigo-700 transition hover:bg-indigo-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-1">
                                                    แก้ไข
                                                </button>
                                            <button
                                                type="button"
                                                x-show="items.length > 1"
                                                x-cloak
                                                x-on:click="removeItem(index)"
                                                :aria-label="'ลบรายการ ' + (index + 1)"
                                                class="rounded-md px-2 py-2 text-sm font-medium text-red-600 transition hover:bg-red-50 hover:text-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-1">
                                                ลบรายการ
                                            </button>
                                            </div>
                                            <input type="hidden" :name="`items[${index}][item_id]`" :value="item.id">
                                            <input type="hidden" :name="`items[${index}][product_id]`" :value="item.product_id">
                                            <input type="hidden" :name="`items[${index}][customer_id]`" :value="item.customer_id">
                                            <input type="hidden" :name="`items[${index}][product_code]`" :value="item.product_code">
                                            <input type="hidden" :name="`items[${index}][product_name]`" :value="item.product_name">
                                            <input type="hidden" :name="`items[${index}][lot_no]`" :value="item.lot_no">
                                            <input type="hidden" :name="`items[${index}][po_no]`" :value="item.po_no">
                                            <input type="hidden" :name="`items[${index}][batch_no]`" :value="item.batch_no">
                                            <input type="hidden" :name="`items[${index}][customer_name]`" :value="item.customer_name">
                                            <input type="hidden" :name="`items[${index}][defect_quantity]`" :value="item.defect_quantity">
                                            <input type="hidden" :name="`items[${index}][defect_unit_name]`" :value="item.defect_unit_name">
                                            <input type="hidden" :name="`items[${index}][total_quantity]`" :value="item.total_quantity">
                                            <input type="hidden" :name="`items[${index}][total_unit_name]`" :value="item.total_unit_name">
                                            <input type="hidden" :name="`items[${index}][unit_name]`" :value="item.unit_name">
                                            <input type="hidden" :name="`items[${index}][problem_description]`" :value="item.problem_description">
                                        </td>
                                    </tr>

                                </template>
                            </tbody>
                        </table>
                    </div>

                    <p class="mt-2 px-1 text-xs leading-5 text-slate-500">
                        เลือก “เพิ่มรายการ” เพื่อบันทึกสินค้า หรือ “แก้ไข” เพื่อปรับข้อมูลรายการเดิม (เพิ่มได้สูงสุด 50 รายการ)
                    </p>

                </div>

                <dialog x-ref="itemDialog" x-on:close="itemDialogOpen = false" closedby="any"
                    class="m-auto w-[calc(100%_-_2rem)] max-w-4xl overflow-hidden rounded-2xl border-0 bg-white p-0 shadow-2xl backdrop:bg-slate-950/55"
                    aria-labelledby="ncr-item-dialog-title">
                    <fieldset x-ref="itemEditor" disabled x-bind:disabled="!itemDialogOpen" class="m-0 max-h-[calc(100vh_-_2rem)] min-w-0 overflow-y-auto border-0 p-0">
                        <div class="sticky top-0 z-10 flex items-start justify-between gap-4 border-b border-slate-200 bg-white px-4 py-4 sm:px-6">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-wider text-indigo-600">Product / Report</p>
                                <h3 id="ncr-item-dialog-title" class="mt-1 text-lg font-bold text-slate-900"
                                    x-text="editingIndex === null ? 'เพิ่มรายการผลิตภัณฑ์' : 'แก้ไขรายการที่ ' + (editingIndex + 1)"></h3>
                            </div>
                            <button type="button" x-on:click="$refs.itemDialog.close()" aria-label="ปิดหน้าต่าง"
                                class="rounded-lg p-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                <span aria-hidden="true" class="text-xl leading-none">&times;</span>
                            </button>
                        </div>

                        <div class="grid grid-cols-1 gap-4 px-4 py-5 sm:grid-cols-2 sm:px-6 lg:grid-cols-3">
                            <div class="sm:col-span-2 lg:col-span-3">
                                <label for="item-product-reference" class="block text-sm font-semibold text-slate-700">สินค้า <span class="text-red-600">*</span></label>
                                <select id="item-product-reference" x-model="itemDraft.product_id" x-on:change="selectProduct($event.target.value)" x-bind:required="!itemDraft.product_id && (!itemDraft.id || !itemDraft.product_name)" class="mt-2 block min-h-[42px] w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="" x-text="itemDraft.id && itemDraft.product_name ? 'ค่าเดิมที่ยังไม่ผูกทะเบียน' : 'เลือกสินค้าจากทะเบียนกลาง'"></option>
                                    @foreach($products as $product)
                                        <option value="{{ $product->id }}">{{ $product->code }} — {{ $product->name_th }}{{ $product->is_active && ! $product->trashed() ? '' : ' (ปิดใช้งาน)' }}</option>
                                    @endforeach
                                </select>
                                <p x-show="itemDraft.product_name" x-text="itemDraft.product_id ? 'ข้อมูลอ้างอิง: ' + itemDraft.product_code + ' — ' + itemDraft.product_name : 'Snapshot เดิม: ' + itemDraft.product_code + ' — ' + itemDraft.product_name" class="mt-1 text-xs text-slate-500"></p>
                            </div>
                            <label class="block text-sm font-semibold text-slate-700">Lot No.
                                <input type="text" x-model="itemDraft.lot_no" class="mt-2 block min-h-[42px] w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </label>
                            <label class="block text-sm font-semibold text-slate-700">PO No.
                                <input type="text" x-model="itemDraft.po_no" class="mt-2 block min-h-[42px] w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </label>
                            <label class="block text-sm font-semibold text-slate-700">Batch No.
                                <input type="text" x-model="itemDraft.batch_no" class="mt-2 block min-h-[42px] w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </label>
                            <div class="sm:col-span-2 lg:col-span-3">
                                <label for="item-customer-reference" class="block text-sm font-semibold text-slate-700">ลูกค้า (ถ้ามี)</label>
                                <select id="item-customer-reference" x-model="itemDraft.customer_id" x-on:change="selectCustomer($event.target.value)" class="mt-2 block min-h-[42px] w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">ไม่ระบุลูกค้า</option>
                                    @foreach($customers as $customer)
                                        <option value="{{ $customer->id }}">{{ $customer->code }} — {{ $customer->name_th }}{{ $customer->is_active && ! $customer->trashed() ? '' : ' (ปิดใช้งาน)' }}</option>
                                    @endforeach
                                </select>
                                <p x-show="itemDraft.customer_name" x-text="itemDraft.customer_id ? 'ข้อมูลอ้างอิง: ' + itemDraft.customer_name : 'ข้อมูลเดิมที่ยังไม่ผูกทะเบียน: ' + itemDraft.customer_name" class="mt-1 text-xs text-slate-500"></p>
                            </div>
                            <label class="block text-sm font-semibold text-slate-700">จำนวนที่พบ <span class="text-red-600">*</span>
                                <input type="number" step="0.0001" min="0" x-model="itemDraft.defect_quantity" required class="mt-2 block min-h-[42px] w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </label>
                            <label class="block text-sm font-semibold text-slate-700">หน่วยนับที่พบ
                                <select x-model="itemDraft.defect_unit_name" class="mt-2 block min-h-[42px] w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">เลือกหน่วยนับ</option>
                                    @foreach($units as $x)
                                    <option value="{{ $x->code }}">{{ $x->name_th }} ({{ $x->code }})</option>
                                    @endforeach
                                </select>
                            </label>
                            <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                                <p class="text-sm font-semibold text-slate-700">คิดเป็นร้อยละ</p>
                                <output class="mt-2 block text-lg font-bold text-indigo-700"
                                    x-text="Number(itemDraft.total_quantity) > 0 ? ((Number(itemDraft.defect_quantity) / Number(itemDraft.total_quantity)) * 100).toFixed(2) + '%' : '-'">-</output>
                            </div>
                            <label class="block text-sm font-semibold text-slate-700">จำนวนทั้งหมด <span class="text-red-600">*</span>
                                <input type="number" step="0.0001" min="0.0001" x-model="itemDraft.total_quantity" required class="mt-2 block min-h-[42px] w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </label>
                            <label class="block text-sm font-semibold text-slate-700">หน่วยนับทั้งหมด
                                <select x-model="itemDraft.total_unit_name" class="mt-2 block min-h-[42px] w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">เลือกหน่วยนับ</option>
                                    @foreach($units as $x)
                                    <option value="{{ $x->code }}">{{ $x->name_th }} ({{ $x->code }})</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="block text-sm font-semibold text-slate-700 sm:col-span-2 lg:col-span-3">ลักษณะผิดปกติ / รายละเอียด
                                <textarea x-model="itemDraft.problem_description" rows="4" class="mt-2 block w-full resize-y rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                            </label>
                        </div>

                        <div class="sticky bottom-0 flex flex-col-reverse gap-2 border-t border-slate-200 bg-white px-4 py-4 sm:flex-row sm:justify-end sm:px-6">
                            <button type="button" x-on:click="$refs.itemDialog.close()"
                                class="min-h-[42px] rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-400 focus:ring-offset-2">
                                ยกเลิก
                            </button>
                            <button type="button" x-on:click="saveItem()"
                                class="min-h-[42px] rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                                บันทึกรายการ
                            </button>
                        </div>
                    </fieldset>
                </dialog>
            </section>


            {{-- รายละเอียดและไฟล์ --}}
            <div id="ncr-evidence" class="ncr-form-section ncr-form-section--compact scroll-mt-28 rounded-xl border border-slate-200 bg-white p-3 sm:p-4">

                <div class="ncr-description-grid grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,2fr)_minmax(280px,1fr)]">

                    <div>
                        <x-label
                            value="สรุปเหตุการณ์ NCR (ภาพรวม)"
                            class="text-sm font-semibold text-slate-700" />
                        <p class="mt-1 text-xs leading-5 text-slate-500">สรุปเหตุการณ์ระดับ NCR ใช้เป็นบริบทกลางของทุกขั้นตอน ไม่แทนรายละเอียดเฉพาะรายการสินค้า</p>

                        <textarea
                            name="problem_description"
                            rows="5"
                            class="mt-2 block w-full resize-y rounded-lg border border-slate-300 bg-white text-sm shadow-sm transition focus:border-indigo-500 focus:ring-indigo-500"
                            required>{{ $v('problem_description') }}</textarea>
                    </div>


                    <div>
                        <x-label
                            value="ไฟล์แนบ (สูงสุด 10 MB/ไฟล์)"
                            class="text-sm font-semibold text-slate-700" />

                        <input
                            type="file"
                            name="attachments[]"
                            multiple
                            accept=".pdf,.jpg,.jpeg,.png,.xlsx,.xls,.docx"
                            class="mt-2 block w-full rounded-lg border border-slate-300 bg-white text-sm text-slate-600 shadow-sm
                                file:mr-4 file:border-0 file:border-r file:border-slate-200
                                file:bg-slate-50 file:px-4 file:py-3
                                file:text-sm file:font-semibold file:text-slate-700
                                hover:file:bg-slate-100
                                focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                    </div>

                </div>

            </div>


            {{-- Actions --}}
            <div class="ncr-form-actions flex flex-col-reverse gap-2 rounded-xl border border-slate-200 bg-white p-3 sm:flex-row sm:items-center sm:justify-between sm:p-3">

                <p class="hidden text-sm text-slate-500 sm:block">ตรวจสอบข้อมูลบังคับก่อนบันทึก ระบบจะเก็บรายการเป็น Draft</p>

                <div class="flex flex-col-reverse gap-2 sm:flex-row">

                    <a
                        href="{{ $editing ? route('ncr.show', $ncr) : route('ncr.index') }}"
                        class="inline-flex min-h-[42px] items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-400 focus:ring-offset-2">
                        ยกเลิก
                    </a>

                    <button
                        type="submit"
                        class="inline-flex min-h-[42px] items-center justify-center rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                        บันทึก Draft
                    </button>

                </div>

            </div>

        </form>

    </div>
</x-app-layout>
