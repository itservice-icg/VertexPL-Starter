<section id="action-verification" class="ncr-stage-card ncr-panel ncr-flow-verification w-full overflow-hidden">
    <div class="ncr-stage-card__header ncr-verification-header">
        <div class="ncr-verification-heading">
            <div>
                <p class="ncr-stage-kicker">Stage 04 · Verification</p>
                <h2 class="ncr-section-title mt-1">ตรวจยืนยัน Corrective Action (Action Verification)</h2>
                <p class="ncr-section-description mt-1">ตรวจหลักฐานการปฏิบัติตาม Action เพื่อส่งต่อให้ QA ประเมินประสิทธิผล</p>
            </div>
        </div>
    </div>

    @if($ncr->current_status === 'PENDING_ACTION_VERIFICATION' || $ncr->actionVerifications->count())
    <div class="ncr-stage-card__body">
        @can('ncr.action.verify')
            @if($ncr->current_status === 'PENDING_ACTION_VERIFICATION')
                <form
                    method="POST"
                    action="{{ route('ncr.corrective-actions.verify', $ncr) }}"
                    class="ncr-workflow-form ncr-verification-form"
                    x-data='@json(['result' => old('result')])'
                >
                    @csrf
                    <input type="hidden" name="version_no" value="{{ $ncr->version_no }}">

                    <fieldset class="ncr-verification-form__result">
                        <legend class="text-sm font-semibold">ผลการตรวจยืนยัน <span class="text-red-600">*</span></legend>
                        <div class="mt-2 grid gap-2 sm:grid-cols-2">
                            <label class="ncr-verification-choice ncr-verification-choice--pass" :class="result === 'PASSED' ? 'is-selected' : ''">
                                <input type="radio" name="result" value="PASSED" x-model="result" required aria-describedby="verification-result-help">
                                <span class="ncr-verification-choice__radio" aria-hidden="true"></span>
                                <span><strong class="text-emerald-700">ผ่าน</strong><span class="block text-xs text-slate-500">บันทึกผลตรวจ แล้วส่งต่อให้ QA ประเมินประสิทธิผล</span></span>
                                <span x-show="result === 'PASSED'" class="ml-auto text-xs font-semibold text-emerald-700">เลือกแล้ว ✓</span>
                            </label>
                            <label class="ncr-verification-choice ncr-verification-choice--fail" :class="result === 'FAILED' ? 'is-selected' : ''">
                                <input type="radio" name="result" value="FAILED" x-model="result" required aria-describedby="verification-result-help">
                                <span class="ncr-verification-choice__radio" aria-hidden="true"></span>
                                <span><strong class="text-red-700">ไม่ผ่าน</strong><span class="block text-xs text-slate-500">ส่งกลับหน่วยงานรับผิดชอบ พร้อมเหตุผลที่ต้องแก้ไข</span></span>
                                <span x-show="result === 'FAILED'" class="ml-auto text-xs font-semibold text-red-700">เลือกแล้ว ✓</span>
                            </label>
                        </div>
                        <p id="verification-result-help" x-show="! result" class="mt-2 text-sm text-amber-700">กรุณาเลือกผลการตรวจยืนยันก่อนบันทึก</p>
                        @error('result')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </fieldset>

                    <div class="ncr-verification-form__detail">
                        <label for="verification_detail" class="text-sm font-semibold"><span x-text="result === 'FAILED' ? 'เหตุผลที่ส่งกลับ Rework' : 'รายละเอียดการตรวจยืนยัน'"></span> <span class="text-red-600">*</span></label>
                        <textarea id="verification_detail" name="verification_detail" rows="2" required class="mt-1 w-full rounded-lg border-slate-300" :placeholder="result === 'FAILED' ? 'ระบุสิ่งที่ต้องแก้ไขเพิ่มเติม' : 'ระบุหลักฐาน วิธีตรวจ และผลที่ตรวจพบ'">{{ old('verification_detail', old('return_reason')) }}</textarea>
                        @error('verification_detail')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div class="ncr-verification-form__submit flex justify-end">
                        <button class="w-full rounded-lg bg-indigo-600 px-5 py-2 font-semibold text-white sm:w-auto">บันทึกผลการตรวจยืนยัน</button>
                    </div>
                </form>
            @endif
        @endcan

        @if($ncr->actionVerifications->count())
            <div class="ncr-verification-history-heading">
                <span class="ncr-verification-history-heading__icon" aria-hidden="true">◷</span>
                <div><h3>ประวัติการตรวจยืนยัน</h3><p>แสดงผลการตรวจยืนยันที่ผ่านมา</p></div>
            </div>
            <div class="ncr-verification-history-table overflow-x-auto">
                <table class="ncr-table ncr-mobile-cards min-w-[760px] w-full text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="w-[10%] px-3 py-2 text-left">รอบ</th>
                            <th class="w-[18%] px-3 py-2 text-left">ผล</th>
                            <th class="w-[47%] px-3 py-2 text-left">รายละเอียด</th>
                            <th class="w-[25%] px-3 py-2 text-left">ผู้ตรวจ/เวลา</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach($ncr->actionVerifications as $verification)
                            <tr>
                                <td class="whitespace-nowrap px-3 py-3" data-label="รอบ">#{{ $verification->verification_round }}</td>
                                <td class="px-3 py-3" data-label="ผล">
                                    <span class="rounded-full px-2 py-1 text-xs font-semibold {{ $verification->result === 'PASSED' ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700' }}" title="{{ $verification->result }}">{{ config('ncr.verification_result_labels.'.$verification->result, $verification->result) }}</span>
                                    @if($verification->correctiveAction)<div class="mt-1 text-xs text-slate-500">Action #{{ $verification->correctiveAction->action_no }}</div>@endif
                                </td>
                                <td class="px-3 py-3" data-label="รายละเอียด">
                                    <div class="font-semibold">{{ $verification->result === 'FAILED' ? 'เหตุผลที่ส่งกลับ Rework' : 'รายละเอียดการตรวจยืนยัน' }}</div>
                                    <div class="whitespace-pre-line">{{ $verification->verification_detail }}</div>
                                    @if($verification->return_reason && $verification->return_reason !== $verification->verification_detail)<div class="mt-2 whitespace-pre-line text-red-600">เหตุผลส่งกลับ: {{ $verification->return_reason }}</div>@endif
                                </td>
                                <td class="whitespace-nowrap px-3 py-3" data-label="ผู้ตรวจ/เวลา">
                                    <div>{{ $verification->verifier->name }}</div>
                                    <div class="text-xs text-slate-500">{{ $verification->verified_at->timezone(config('app.display_timezone'))->format('d/m/Y H:i') }}</div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-sm text-slate-500">ยังไม่มีประวัติการตรวจยืนยัน</p>
        @endif
    </div>
    @endif
</section>
