<x-app-layout>
    <?php
    /** @var \App\Models\NcrReport $ncr */
    $editing = $ncr->exists;
    ?>

    <div class="ncr-page ncr-form ncr-create-page w-full space-y-4 px-3 py-3 sm:px-5 sm:py-4">

        <header class="ncr-create-heading">
            <h1>{{ $editing ? 'แก้ไข '.$ncr->ncr_no : 'สร้างรายงาน NCR ใหม่' }}</h1>
            <p>กรอกข้อมูลเหตุการณ์ เลือกสินค้า และระบุรายละเอียดที่พบ · <span>* คือข้อมูลบังคับ</span></p>
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
                    ->take(1)
                    ->map(fn($item) => $item->only([
                        'id',
                        'product_id',
                        'product_code',
                        'product_name',
                        'lot_no',
                        'po_no',
                        'batch_no',
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
            $initialItems = array_values((array) $initialItems);
            $initialItem = $initialItems[0] ?? [];
            ?>

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                {{-- ข้อมูลวันที่ --}}
                <div id="ncr-context" class="ncr-form-section ncr-form-section--compact scroll-mt-28 p-3 sm:p-4">
                    <div class="ncr-form-section__header ncr-compact-section-header -mx-3 -mt-3 mb-3 sm:-mx-4 sm:-mt-4">
                        <h2 class="ncr-section-title mt-1">ข้อมูลเหตุการณ์</h2>
                        <p class="ncr-section-description mt-1">กำหนดวันที่ของรายงานและเลือกแบบฟอร์มที่ใช้</p>
                    </div>
                    <div class="ncr-compact-grid grid grid-cols-1 gap-3 sm:grid-cols-2">

                        <div>
                            <x-label for="legacy_form_scope_code"
                                value="แบบฟอร์มที่ใช้"
                                class="text-sm font-semibold text-slate-700" />

                            @php
                            $oldScopeCodes = old('legacy_form_scope_codes');
                            $scopeCodes = array_slice(is_array($oldScopeCodes) ? $oldScopeCodes : (filled($v('legacy_form_scope_code')) ? explode(',', $v('legacy_form_scope_code')) : []), 0, 1);
                            $selectedScopeCode = old('legacy_form_scope_code', $scopeCodes[0] ?? '');
                            $previousScopeCode = old('legacy_form_scope_code_previous', $editing ? $ncr->legacy_form_scope_code : '');
                            $previousScopeIsInactive = filled($previousScopeCode) && ! $formScopes->contains('code', $previousScopeCode);
                            @endphp

                            <select
                                id="legacy_form_scope_code"
                                name="legacy_form_scope_code"
                                aria-invalid="{{ $errors->has('legacy_form_scope_code') ? 'true' : 'false' }}"
                                @if($errors->has('legacy_form_scope_code')) aria-describedby="legacy-form-scope-error" @endif
                                class="mt-2 block min-h-[42px] w-full rounded-lg border-slate-300 bg-white text-sm text-slate-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="" {{ $selectedScopeCode === '' ? 'selected' : '' }}>เลือกแบบฟอร์ม</option>
                                @if($previousScopeIsInactive)
                                    <option value="__clear__" {{ $selectedScopeCode === '__clear__' ? 'selected' : '' }}>ไม่ระบุแบบฟอร์ม</option>
                                @endif
                                @foreach ($formScopes as $formScope)
                                    <option value="{{ $formScope->code }}" {{ $selectedScopeCode === $formScope->code ? 'selected' : '' }}>
                                        {{ $formScope->code }} — {{ $formScope->name_th }}
                                    </option>
                                @endforeach
                            </select>
                            @if($previousScopeIsInactive)
                                <input type="hidden" name="legacy_form_scope_code_previous" value="{{ $previousScopeCode }}">
                            @endif
                            @if($previousScopeIsInactive)
                                <p class="mt-1 text-xs text-slate-500">แบบฟอร์มเดิมปิดใช้งาน ระบบจะเก็บค่าเดิมไว้ หากต้องการเปลี่ยน ให้เลือกแบบฟอร์มที่เปิดใช้งาน</p>
                            @endif
                            @error('legacy_form_scope_code') <p id="legacy-form-scope-error" class="mt-1 text-xs text-red-600" role="alert">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <x-label for="report_date" class="text-sm font-semibold text-slate-700">
                                วันที่ออก <span class="text-red-600" aria-hidden="true">*</span>
                            </x-label>

                            <x-input
                                id="report_date"
                                type="date"
                                name="report_date"
                                class="mt-2 block min-h-[42px] w-full"
                                value="{{ $v('report_date', now(config('app.display_timezone'))->toDateString()) }}"
                                aria-invalid="{{ $errors->has('report_date') ? 'true' : 'false' }}"
                                :aria-describedby="$errors->has('report_date') ? 'report-date-error' : false"
                                required />
                            @error('report_date') <p id="report-date-error" class="mt-1 text-xs text-red-600" role="alert">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <x-label for="found_date" class="text-sm font-semibold text-slate-700">
                                วันที่พบ <span class="text-red-600" aria-hidden="true">*</span>
                            </x-label>

                            <x-input
                                id="found_date"
                                type="date"
                                name="found_date"
                                class="mt-2 block min-h-[42px] w-full"
                                value="{{ $v('found_date', now(config('app.display_timezone'))->toDateString()) }}"
                                aria-invalid="{{ $errors->has('found_date') ? 'true' : 'false' }}"
                                :aria-describedby="$errors->has('found_date') ? 'found-date-error' : false"
                                required />
                            @error('found_date') <p id="found-date-error" class="mt-1 text-xs text-red-600" role="alert">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <x-label for="found_time"
                                value="เวลาที่พบ"
                                class="text-sm font-semibold text-slate-700" />

                            <x-input
                                id="found_time"
                                type="time"
                                name="found_time"
                                class="mt-2 block min-h-[42px] w-full"
                                aria-invalid="{{ $errors->has('found_time') ? 'true' : 'false' }}"
                                :aria-describedby="$errors->has('found_time') ? 'found-time-error' : false"
                                value="{{ $v('found_time', $editing ? '' : now(config('app.display_timezone'))->format('H:i')) }}" />
                            @error('found_time') <p id="found-time-error" class="mt-1 text-xs text-red-600" role="alert">{{ $message }}</p> @enderror
                        </div>

                    </div>
                @if(! $editing && ! auth()->user()->department_id)

                <div class="mt-3 border-t border-slate-200 pt-3">

                    <x-label for="reporter_department_id" class="text-sm font-semibold text-slate-700">
                        หน่วยงานผู้รายงาน <span class="text-red-600" aria-hidden="true">*</span>
                    </x-label>

                    <p class="mb-2 mt-1 text-xs text-slate-500">
                        บัญชีนี้ยังไม่ได้ผูกหน่วยงาน กรุณาเลือกหน่วยงานสำหรับ NCR นี้
                    </p>

                    <select
                        id="reporter_department_id"
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





                {{-- ประเภท / หน่วยงาน --}}
                <div id="ncr-classification" class="ncr-form-section ncr-form-section--compact scroll-mt-28 p-3 sm:p-4">
                    <div class="ncr-form-section__header ncr-compact-section-header -mx-3 -mt-3 mb-3 sm:-mx-4 sm:-mt-4">
                        <h2 class="ncr-section-title mt-1">ประเภทและหน่วยงาน</h2>
                        <p class="ncr-section-description mt-1">ระบุประเภทเหตุการณ์และหน่วยงานที่พบปัญหา</p>
                    </div>

                    <div class="ncr-compact-grid grid grid-cols-1 gap-3 sm:grid-cols-2">

                        <div>
                            <x-label for="problem_type_id" class="text-sm font-semibold text-slate-700">
                                ประเภทที่พบ <span class="text-red-600" aria-hidden="true">*</span>
                            </x-label>

                            <select
                                id="problem_type_id"
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
                            <x-label for="related_department_id"
                                value="หน่วยงานที่พบปัญหา"
                                class="text-sm font-semibold text-slate-700" />

                            <select
                                id="related_department_id"
                                name="related_department_id"
                                aria-invalid="{{ $errors->has('related_department_id') ? 'true' : 'false' }}"
                                @if($errors->has('related_department_id')) aria-describedby="related-department-error" @endif
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
                            @error('related_department_id') <p id="related-department-error" class="mt-1 text-xs text-red-600" role="alert">{{ $message }}</p> @enderror
                        </div>

                    </div>
                    @if($editing && $ncr->severity_level_id)
                        <input type="hidden" name="severity_level_id" value="{{ $ncr->severity_level_id }}">
                    @endif
                </div>


                {{-- ข้อมูลทั่วไป --}}
                <div id="ncr-reference" class="ncr-form-section ncr-form-section--compact scroll-mt-28 p-3 sm:p-4 lg:col-span-2">
                    <div class="ncr-form-section__header ncr-compact-section-header -mx-3 -mt-3 mb-3 sm:-mx-4 sm:-mt-4">
                        <h2 class="ncr-section-title mt-1">ข้อมูลอ้างอิง</h2>
                        <p class="ncr-section-description mt-1">ข้อมูลลูกค้า สินค้า ผู้ผลิต พื้นที่ และกระบวนการที่เกี่ยวข้อง</p>
                    </div>

                    <div class="ncr-compact-grid ncr-reference-grid grid min-w-0 grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4"
                        x-data="{
                            products: {{ \Illuminate\Support\Js::from($products->map(fn($product) => ['id' => $product->id, 'code' => $product->code, 'name' => $product->name_th])->values()) }},
                            productId: {{ \Illuminate\Support\Js::from(old('items.0.product_id', $initialItem['product_id'] ?? '')) }},
                            productCode: {{ \Illuminate\Support\Js::from(old('items.0.product_code', $initialItem['product_code'] ?? '')) }},
                            productName: {{ \Illuminate\Support\Js::from(old('items.0.product_name', $initialItem['product_name'] ?? '')) }},
                            selectProduct(id) {
                                this.productId = id || '';
                                const product = this.products.find((entry) => String(entry.id) === String(id));
                                if (product) {
                                    this.productCode = product.code;
                                    this.productName = product.name;
                                    this.$refs.productNameInput.setCustomValidity('');
                                }
                            }
                        }">
                        <div class="min-w-0">
                            <x-label for="customer_id" class="text-sm font-semibold text-slate-700">
                                ชื่อลูกค้า <span class="text-red-600" aria-hidden="true">*</span>
                            </x-label>
                            <select id="customer_id" name="customer_id" required oninvalid="this.setCustomValidity('กรุณาเลือกลูกค้า')" onchange="this.setCustomValidity('')" aria-invalid="{{ $errors->has('customer_id') ? 'true' : 'false' }}" @if($errors->has('customer_id')) aria-describedby="customer-id-error" @endif class="mt-2 block min-h-[42px] w-full rounded-lg border border-slate-300 bg-white text-sm text-slate-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">เลือกลูกค้าจากทะเบียน</option>
                                @foreach($customers as $customer)
                                    <option value="{{ $customer->id }}" {{ (string) old('customer_id', $editing ? $ncr->customer_id : '') === (string) $customer->id ? 'selected' : '' }}>{{ $customer->code }} — {{ $customer->name_th }}{{ $customer->is_active && ! $customer->trashed() ? '' : ' (ปิดใช้งาน)' }}</option>
                                @endforeach
                            </select>
                            @error('customer_id') <p id="customer-id-error" class="mt-1 text-xs text-red-600" role="alert">{{ $message }}</p> @enderror
                        </div>

                        <div class="min-w-0">
                                <x-label for="product_id" value="ทะเบียนสินค้า" class="text-sm font-semibold text-slate-700" />
                                <select id="product_id" name="items[0][product_id]" x-model="productId" x-on:change="selectProduct($event.target.value)" class="mt-2 block min-h-[42px] w-full min-w-0 rounded-lg border border-slate-300 bg-white text-sm text-slate-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">ไม่เลือกจากทะเบียน / กรอกเอง</option>
                                    @foreach($products as $product)
                                        <option value="{{ $product->id }}">{{ $product->code }} — {{ $product->name_th }}</option>
                                    @endforeach
                                </select>
                                @error('items.0.product_id') <p class="mt-1 text-xs text-red-600" role="alert">{{ $message }}</p> @enderror
                            </div>
                        <div class="min-w-0">
                                <x-label for="product_code" value="รหัสสินค้า" class="text-sm font-semibold text-slate-700" />
                                <input id="product_code" name="items[0][product_code]" type="text" maxlength="255" x-model="productCode" x-on:input="productId = ''" value="{{ old('items.0.product_code', $initialItem['product_code'] ?? '') }}" class="mt-2 block min-h-[42px] w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @error('items.0.product_code') <p class="mt-1 text-xs text-red-600" role="alert">{{ $message }}</p> @enderror
                            </div>
                        <div class="min-w-0">
                                <x-label for="product_name" class="text-sm font-semibold text-slate-700">ชื่อสินค้า <span class="text-red-600" aria-hidden="true">*</span></x-label>
                                <input id="product_name" x-ref="productNameInput" name="items[0][product_name]" type="text" maxlength="255" required oninvalid="this.setCustomValidity('กรุณากรอกชื่อสินค้า')" oninput="this.setCustomValidity('')" x-model="productName" x-on:input="productId = ''" value="{{ old('items.0.product_name', $initialItem['product_name'] ?? '') }}" aria-invalid="{{ $errors->has('items.0.product_name') ? 'true' : 'false' }}" @if($errors->has('items.0.product_name')) aria-describedby="product-name-error" @endif class="mt-2 block min-h-[42px] w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @error('items.0.product_name') <p id="product-name-error" class="mt-1 text-xs text-red-600" role="alert">{{ $message }}</p> @enderror
                            </div>

                        @foreach([
                        'generic_name' => 'ชื่อสามัญ',
                        'found_location' => 'พื้นที่ที่พบ'
                        ] as $key => $label)

                        <div class="min-w-0">
                            <x-label
                                :for="$key"
                                :value="$label"
                                class="text-sm font-semibold text-slate-700" />

                            <x-input
                                :id="$key"
                                :name="$key"
                                class="mt-2 block min-h-[42px] w-full"
                                :aria-invalid="$errors->has($key) ? 'true' : 'false'"
                                :aria-describedby="$errors->has($key) ? $key . '-error' : false"
                                :value="$v($key)" />
                            @error($key) <p id="{{ $key }}-error" class="mt-1 text-xs text-red-600" role="alert">{{ $message }}</p> @enderror
                        </div>

                        @endforeach

                        <div class="min-w-0">
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

                        <div class="min-w-0">
                            <x-label for="production_date"
                                value="วันที่ผลิต"
                                class="text-sm font-semibold text-slate-700" />

                            <x-input
                                id="production_date"
                                type="date"
                                name="production_date"
                                class="mt-2 block min-h-[42px] w-full"
                                aria-invalid="{{ $errors->has('production_date') ? 'true' : 'false' }}"
                                :aria-describedby="$errors->has('production_date') ? 'production-date-error' : false"
                                value="{{ $v('production_date') }}" />
                            @error('production_date') <p id="production-date-error" class="mt-1 text-xs text-red-600" role="alert">{{ $message }}</p> @enderror
                        </div>

                    </div>

                </div>
            </div>

            {{-- รายการผลิตภัณฑ์ --}}
            <section id="ncr-products" x-data="{
                defect: {{ \Illuminate\Support\Js::from(old('items.0.defect_quantity', $initialItem['defect_quantity'] ?? '')) }},
                total: {{ \Illuminate\Support\Js::from(old('items.0.total_quantity', $initialItem['total_quantity'] ?? '')) }},
                percentage() {
                    const defect = Number(this.defect);
                    const total = Number(this.total);
                    return Number.isFinite(defect) && Number.isFinite(total) && total > 0 ? ((defect / total) * 100).toFixed(2) + '%' : '0.00%';
                },
                quantityInvalid() {
                    const defect = Number(this.defect);
                    const total = Number(this.total);
                    return this.defect !== '' && this.total !== '' && Number.isFinite(defect) && Number.isFinite(total) && total > 0 && defect > total;
                }
            }" class="ncr-form-items ncr-form-section scroll-mt-28 overflow-hidden rounded-xl border border-slate-200 bg-white">
                <div class="ncr-form-items__header border-b border-slate-200 px-3 py-3 sm:px-4">
                    <h2 id="ncr-items-heading" class="text-base font-bold text-slate-900 sm:text-lg">รายการสินค้าและรายละเอียดที่พบ</h2>
                    <p class="mt-1 text-xs leading-5 text-slate-500 sm:text-sm">กรอกข้อมูลสินค้าและจำนวนที่พบ พร้อมรายละเอียดความผิดปกติ</p>
                </div>
                <div class="grid grid-cols-1 gap-4 p-3 sm:grid-cols-2 lg:grid-cols-4 sm:p-4">
                    <input type="hidden" name="items[0][item_id]" value="{{ old('items.0.item_id', $initialItem['id'] ?? '') }}">
                    <label class="block text-sm font-semibold text-slate-700">Lot No.
                        <input type="text" name="items[0][lot_no]" maxlength="255" value="{{ old('items.0.lot_no', $initialItem['lot_no'] ?? '') }}" class="mt-2 block min-h-[42px] w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('items.0.lot_no') <span class="mt-1 block text-xs text-red-600" role="alert">{{ $message }}</span> @enderror
                    </label>
                    <label class="block text-sm font-semibold text-slate-700">PO No.
                        <input type="text" name="items[0][po_no]" maxlength="255" value="{{ old('items.0.po_no', $initialItem['po_no'] ?? '') }}" class="mt-2 block min-h-[42px] w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('items.0.po_no') <span class="mt-1 block text-xs text-red-600" role="alert">{{ $message }}</span> @enderror
                    </label>
                    <label class="block text-sm font-semibold text-slate-700">Batch No.
                        <input type="text" name="items[0][batch_no]" maxlength="255" value="{{ old('items.0.batch_no', $initialItem['batch_no'] ?? '') }}" class="mt-2 block min-h-[42px] w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('items.0.batch_no') <span class="mt-1 block text-xs text-red-600" role="alert">{{ $message }}</span> @enderror
                    </label>
                    <label class="block text-sm font-semibold text-slate-700">จำนวนที่พบ <span class="text-red-600" aria-hidden="true">*</span>
                        <input type="number" name="items[0][defect_quantity]" step="0.0001" min="0" max="99999999999999.9999" x-bind:max="Number(total) > 0 ? total : 99999999999999.9999" required oninvalid="this.setCustomValidity('กรุณากรอกจำนวนที่พบเป็นตัวเลขไม่ติดลบและไม่เกินจำนวนทั้งหมด')" oninput="this.setCustomValidity('')" x-model="defect" value="{{ old('items.0.defect_quantity', $initialItem['defect_quantity'] ?? '') }}" x-bind:aria-invalid="quantityInvalid() ? 'true' : '{{ $errors->has('items.0.defect_quantity') ? 'true' : 'false' }}'" aria-describedby="defect-quantity-client-error @if($errors->has('items.0.defect_quantity')) defect-quantity-error @endif" class="mt-2 block min-h-[42px] w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('items.0.defect_quantity') <span id="defect-quantity-error" class="mt-1 block text-xs text-red-600" role="alert">{{ $message }}</span> @enderror
                        <span id="defect-quantity-client-error" x-cloak x-show="quantityInvalid()" class="mt-1 block text-xs text-red-600">จำนวนที่พบต้องไม่มากกว่าจำนวนทั้งหมด</span>
                    </label>
                    <label class="block text-sm font-semibold text-slate-700">หน่วยนับที่พบ
                        <select name="items[0][defect_unit_name]" class="mt-2 block min-h-[42px] w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">เลือกหน่วยนับ</option>
                            @foreach($units as $unit)<option value="{{ $unit->code }}" {{ (string) old('items.0.defect_unit_name', $initialItem['defect_unit_name'] ?? $initialItem['unit_name'] ?? '') === (string) $unit->code ? 'selected' : '' }}>{{ $unit->name_th }} ({{ $unit->code }})</option>@endforeach
                        </select>
                        @error('items.0.defect_unit_name') <span class="mt-1 block text-xs text-red-600" role="alert">{{ $message }}</span> @enderror
                    </label>
                    <label class="block text-sm font-semibold text-slate-700">จำนวนทั้งหมด <span class="text-red-600" aria-hidden="true">*</span>
                        <input type="number" name="items[0][total_quantity]" step="0.0001" min="0.0001" max="99999999999999.9999" required oninvalid="this.setCustomValidity('กรุณากรอกจำนวนทั้งหมดเป็นตัวเลขมากกว่า 0')" oninput="this.setCustomValidity('')" x-model="total" value="{{ old('items.0.total_quantity', $initialItem['total_quantity'] ?? '') }}" aria-invalid="{{ $errors->has('items.0.total_quantity') ? 'true' : 'false' }}" @if($errors->has('items.0.total_quantity')) aria-describedby="total-quantity-error" @endif class="mt-2 block min-h-[42px] w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('items.0.total_quantity') <span id="total-quantity-error" class="mt-1 block text-xs text-red-600" role="alert">{{ $message }}</span> @enderror
                    </label>
                    <label class="block text-sm font-semibold text-slate-700">หน่วยนับทั้งหมด
                        <select name="items[0][total_unit_name]" class="mt-2 block min-h-[42px] w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">เลือกหน่วยนับ</option>
                            @foreach($units as $unit)<option value="{{ $unit->code }}" {{ (string) old('items.0.total_unit_name', $initialItem['total_unit_name'] ?? $initialItem['unit_name'] ?? '') === (string) $unit->code ? 'selected' : '' }}>{{ $unit->name_th }} ({{ $unit->code }})</option>@endforeach
                        </select>
                        @error('items.0.total_unit_name') <span class="mt-1 block text-xs text-red-600" role="alert">{{ $message }}</span> @enderror
                    </label>
                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                        <p class="text-sm font-semibold text-slate-700">ร้อยละที่พบ</p>
                        <output class="mt-2 block text-lg font-bold text-indigo-700" aria-live="polite" x-text="percentage()">0.00%</output>
                    </div>
                    <div class="sm:col-span-2 lg:col-span-4">
                        <x-label for="item_problem_description" class="text-sm font-semibold text-slate-700">รายละเอียดความผิดปกติ</x-label>
                        <textarea id="item_problem_description" name="items[0][problem_description]" rows="4" maxlength="5000" aria-invalid="{{ $errors->has('items.0.problem_description') ? 'true' : 'false' }}" @if($errors->has('items.0.problem_description')) aria-describedby="item-problem-description-error" @endif class="mt-2 block w-full resize-y rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('items.0.problem_description', $initialItem['problem_description'] ?? '') }}</textarea>
                        @error('items.0.problem_description') <span id="item-problem-description-error" class="mt-1 block text-xs text-red-600" role="alert">{{ $message }}</span> @enderror
                    </div>
                    @error('items') <p class="text-sm text-red-600 sm:col-span-2 lg:col-span-4" role="alert">{{ $message }}</p> @enderror
                </div>
            </section>

            <div id="ncr-evidence" class="ncr-form-section ncr-form-section--compact scroll-mt-28 rounded-xl border border-slate-200 bg-white p-3 sm:p-4">

                <div class="ncr-description-grid space-y-4">

                    <div>
                        <x-label for="problem_description"
                            value="สรุปเหตุการณ์ NCR (ภาพรวม)"
                            class="text-sm font-semibold text-slate-700" />
                        <p class="mt-1 text-xs leading-5 text-slate-500">ระบุว่าเกิดอะไรขึ้น เมื่อใดและที่ไหน รวมถึงผลกระทบที่พบ ส่วนรายละเอียดเฉพาะสินค้าให้กรอกในแต่ละรายการ</p>

                        <textarea
                            id="problem_description"
                            name="problem_description"
                            rows="5"
                            aria-invalid="{{ $errors->has('problem_description') ? 'true' : 'false' }}"
                            @if($errors->has('problem_description')) aria-describedby="problem-description-error" @endif
                            class="mt-2 block w-full resize-y rounded-lg border border-slate-300 bg-white text-sm shadow-sm transition focus:border-indigo-500 focus:ring-indigo-500">{{ $v('problem_description') }}</textarea>
                        @error('problem_description') <p id="problem-description-error" class="mt-1 text-xs text-red-600" role="alert">{{ $message }}</p> @enderror
                    </div>


                    <div class="border-t border-slate-200 pt-3">
                        <x-label for="attachments"
                            value="ไฟล์แนบ"
                            class="text-sm font-semibold text-slate-700" />
                        <p class="mt-1 text-xs leading-5 text-slate-500">รองรับ PDF, JPG/JPEG/PNG, XLS/XLSX และ DOCX · ไฟล์ละไม่เกิน 10 MB</p>

                        <input
                            id="attachments"
                            type="file"
                            name="attachments[]"
                            multiple
                            accept=".pdf,.jpg,.jpeg,.png,.xlsx,.xls,.docx"
                            aria-invalid="{{ $errors->has('attachments.0') ? 'true' : 'false' }}"
                            @if($errors->has('attachments.0')) aria-describedby="attachments-error" @endif
                            class="mt-2 block w-full rounded-lg border border-slate-300 bg-white text-sm text-slate-600 shadow-sm
                                file:mr-4 file:border-0 file:border-r file:border-slate-200
                                file:bg-slate-50 file:px-4 file:py-3
                                file:text-sm file:font-semibold file:text-slate-700
                                hover:file:bg-slate-100
                                focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                        @error('attachments.0') <p id="attachments-error" class="mt-1 text-xs text-red-600" role="alert">{{ $message }}</p> @enderror
                    </div>

                </div>

            </div>


            {{-- Actions --}}
            <div class="ncr-form-actions flex flex-col-reverse gap-2 rounded-xl border border-slate-200 bg-white p-3 sm:flex-row sm:items-center sm:justify-between sm:p-3">

                <p class="text-sm text-slate-500">กรอกช่องที่มี * ให้ครบก่อนบันทึกเป็นฉบับร่าง</p>

                <div class="flex flex-col-reverse gap-2 sm:flex-row">

                    <a
                        href="{{ $editing ? route('ncr.show', $ncr) : route('ncr.index') }}"
                        class="inline-flex min-h-[42px] items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-400 focus:ring-offset-2">
                        ยกเลิก
                    </a>

                    <button
                        type="submit"
                        class="inline-flex min-h-[42px] items-center justify-center rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                        บันทึกเป็นฉบับร่าง
                    </button>

                </div>

            </div>

        </form>

    </div>
</x-app-layout>
