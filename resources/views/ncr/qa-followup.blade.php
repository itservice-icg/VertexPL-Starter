<section id="qa-followup" class="ncr-stage-card ncr-panel ncr-qa ncr-flow-qa w-full overflow-hidden">
    <div class="ncr-stage-card__header">
        <div>
            <p class="ncr-stage-kicker">Stage 05 · QA Follow-up</p>
            <h2 class="ncr-section-title mt-1">การติดตามผล / การปิด NCR (QA Follow-up)</h2>
            <p class="ncr-section-description mt-1">QA Follow-up คือการติดตามว่า Corrective Action ที่ตรวจผ่านแล้วแก้ปัญหาได้ผลจริงหรือไม่ ซึ่งต่างจาก Action Verification ที่ตรวจว่าทำตามแผนหรือไม่</p>
        </div>
    </div>

    @if(in_array($ncr->current_status, ['QA_VERIFIED', 'CLOSED'], true) || $ncr->qaFollowups->count() || $ncr->closure)
    <div class="ncr-stage-card__body ncr-qa__body flex flex-col gap-3">
        @can('ncr.qa.followup')
        @if($ncr->current_status === 'QA_VERIFIED')
        <div class="ncr-qa-workspace" x-data="{ result: '{{ old('effectiveness_result', 'EFFECTIVE') }}', followupDetail: '{{ old('followup_detail', old('return_reason')) }}' }">
            <div class="ncr-qa-primary">
                <form id="qa-followup-form" method="POST" action="{{ route('ncr.qa-followup.store', $ncr) }}" class="ncr-workflow-form ncr-qa-followup-form space-y-3">
                    @csrf
                    <input type="hidden" name="version_no" value="{{ $ncr->version_no }}">
                    <div class="ncr-qa-field-grid grid gap-4 sm:grid-cols-2">
                        <div><label for="followup_date" class="text-sm font-semibold">วันที่ติดตาม *</label><input id="followup_date" type="date" name="followup_date" value="{{ old('followup_date', now(config('app.display_timezone'))->toDateString()) }}" required class="mt-1 min-h-[42px] w-full rounded-lg border-slate-300"></div>
                        <div><label for="effectiveness_result" class="text-sm font-semibold">ผลการติดตาม *</label><select id="effectiveness_result" name="effectiveness_result" x-model="result" required class="mt-1 min-h-[42px] w-full rounded-lg border-slate-300"><option value="EFFECTIVE">มีประสิทธิผล — บันทึกผลและปิด NCR</option><option value="NOT_EFFECTIVE">ไม่ได้ผล — ส่งกลับหน่วยงานเพื่อทำ Rework</option><option value="NEED_MORE_TIME">ยังสรุปไม่ได้ — นัดติดตามอีกครั้ง</option></select></div>
                    </div>
                    <div><label for="qa-followup-detail" class="text-sm font-semibold"><span x-text="result === 'NOT_EFFECTIVE' ? 'ผลที่ติดตามและเหตุผลที่ส่งกลับ Rework' : 'ผลที่ติดตามและหลักฐานประกอบ'"></span><span x-text="result === 'EFFECTIVE' ? '' : ' *'"></span></label><textarea id="qa-followup-detail" name="followup_detail" rows="2" x-bind:required="result !== 'EFFECTIVE'" x-model="followupDetail" class="mt-1 w-full rounded-lg border-slate-300" placeholder="ระบุสิ่งที่ตรวจพบ หลักฐาน และเหตุผลที่สรุปผลการติดตาม"></textarea><input type="hidden" name="return_reason" x-model="followupDetail" x-bind:disabled="result === 'EFFECTIVE'"></div>
                    <div x-show="result === 'NEED_MORE_TIME'" x-cloak class="ncr-qa-followup-next-date max-w-md"><label for="next_followup_date" class="text-sm font-semibold">วันที่ติดตามครั้งถัดไป *</label><input id="next_followup_date" type="date" name="next_followup_date" value="{{ old('next_followup_date') }}" x-bind:required="result === 'NEED_MORE_TIME'" class="mt-1 min-h-[42px] w-full rounded-lg border-slate-300"></div>
                    <div x-show="result === 'EFFECTIVE'" x-cloak class="ncr-qa-followup-confirmation">
                        <label class="flex items-start gap-3 text-sm text-slate-700"><input type="checkbox" name="checklist_confirmed" value="1" x-bind:required="result === 'EFFECTIVE'" @checked(old('checklist_confirmed')) class="mt-0.5 h-4 w-4 shrink-0 accent-emerald-600 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600"><span><strong class="block font-semibold text-slate-900">ยืนยันความพร้อมก่อนปิด NCR</strong><span class="mt-0.5 block">Corrective Action ผ่านการตรวจ ผลติดตามมีประสิทธิผล และข้อมูลสำหรับปิด NCR ครบถ้วน</span></span></label>
                    </div>
                    <div class="flex flex-col justify-end gap-2 sm:flex-row">
                        <button x-show="result === 'EFFECTIVE'" x-cloak class="rounded-lg bg-emerald-700 px-5 py-2 font-semibold text-white" data-confirm data-confirm-always data-confirm-title="ยืนยันการปิด NCR" data-confirm-text="ระบบจะบันทึก QA Follow-up และปิด NCR ในขั้นตอนเดียว" data-confirm-button="บันทึกผลและปิด NCR">บันทึกผลและปิด NCR</button>
                        <button x-show="result === 'NOT_EFFECTIVE'" x-cloak class="rounded-lg bg-red-600 px-5 py-2 font-semibold text-white">บันทึกผลและส่งกลับ Rework</button>
                        <button x-show="result === 'NEED_MORE_TIME'" x-cloak class="rounded-lg bg-indigo-600 px-5 py-2 font-semibold text-white">บันทึกผลและนัดติดตามครั้งถัดไป</button>
                        <noscript><button class="rounded-lg bg-indigo-600 px-5 py-2 font-semibold text-white">บันทึกผลการติดตาม</button></noscript>
                    </div>
                </form>
            </div>
        </div>
        @endif
        @endcan

        @if($ncr->qaFollowups->count())
        <div class="overflow-x-auto rounded-lg border border-slate-200"><table class="ncr-mobile-cards min-w-[760px] w-full divide-y text-sm"><thead class="bg-slate-50"><tr><th class="px-3 py-2 text-left">รอบ</th><th class="px-3 py-2 text-left">วันที่/ผล</th><th class="px-3 py-2 text-left">รายละเอียด</th><th class="px-3 py-2 text-left">ผู้ติดตาม</th></tr></thead><tbody class="divide-y">
            @foreach($ncr->qaFollowups as $followup)
            <tr><td class="px-3 py-2 align-top" data-label="รอบ">#{{ $followup->followup_round }}</td><td class="px-3 py-2 align-top" data-label="วันที่/ผล">{{ \App\Support\ThaiDateFormatter::formatDate($followup->followup_date) }}<br><span class="font-semibold">{{ config('ncr.followup_result_labels.'.$followup->effectiveness_result, $followup->effectiveness_result) }}</span>@if($followup->next_followup_date)<br><span class="text-amber-700">ติดตามครั้งถัดไป: {{ \App\Support\ThaiDateFormatter::formatDate($followup->next_followup_date) }}</span>@elseif($followup->effectiveness_result === 'NEED_MORE_TIME')<br><span class="text-amber-700">ติดตามครั้งถัดไป: ยังไม่กำหนด</span>@endif</td><td class="whitespace-pre-line px-3 py-2 align-top" data-label="รายละเอียด">{{ $followup->followup_detail }}</td><td class="px-3 py-2 align-top" data-label="ผู้ติดตาม">{{ $followup->follower->name }}<br><span class="text-xs text-slate-500">{{ \App\Support\ThaiDateFormatter::formatDateTime($followup->followed_up_at, config('app.display_timezone')) }}</span></td></tr>
            @endforeach
        </tbody></table></div>
        @endif

        @if($ncr->closure)
        <div class="rounded-lg border border-slate-200 border-l-4 border-l-emerald-500 bg-white px-3 py-2 text-sm">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <p class="font-semibold text-emerald-700">QA Sign-off / ผู้ปิด · ปิด NCR แล้ว</p>
                <p class="text-xs text-slate-500">{{ $ncr->closure->closer->name }} · {{ \App\Support\ThaiDateFormatter::formatDateTime($ncr->closure->closed_at, config('app.display_timezone')) }}</p>
            </div>
            @if($ncr->closure->closure_note)
                <p class="mt-1 whitespace-pre-line text-xs text-slate-600"><span class="font-semibold">เหตุผลการปิด:</span> {{ $ncr->closure->closure_note }}</p>
            @endif
        </div>
        @endif
    </div>
    @endif
</section>
