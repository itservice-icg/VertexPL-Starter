<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSettingRequest;
use App\Http\Requests\UpdateSettingRequest;
use App\Models\Setting;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SettingController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'group' => ['nullable', 'string', 'max:50'],
        ]);

        $settings = Setting::query()
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('key', 'like', "%{$search}%")
                        ->orWhere('label', 'like', "%{$search}%");
                });
            })
            ->when($filters['group'] ?? null, fn ($query, $group) => $query->where('group', $group))
            ->orderBy('group')
            ->orderBy('key')
            ->paginate(15)
            ->withQueryString();

        return view('settings.index', [
            'settings' => $settings,
            'groups' => Setting::query()->distinct()->orderBy('group')->pluck('group'),
            'filters' => $filters,
        ]);
    }

    public function create()
    {
        return view('settings.create');
    }

    public function store(StoreSettingRequest $request)
    {
        $setting = DB::transaction(function () use ($request) {
            $setting = Setting::create($request->validated());
            AuditLogger::log('settings', 'settings.created', "เพิ่มการตั้งค่า {$setting->key}", $setting, [], $this->auditSnapshot($setting));

            return $setting;
        });

        return redirect()->route('settings.edit', $setting)->with('success', 'เพิ่มการตั้งค่าเรียบร้อยแล้ว');
    }

    public function edit(Setting $setting)
    {
        return view('settings.edit', compact('setting'));
    }

    public function update(UpdateSettingRequest $request, Setting $setting)
    {
        DB::transaction(function () use ($request, $setting) {
            $oldValues = $this->auditSnapshot($setting);
            $oldRawValue = $setting->value;
            $setting->update($request->validated());
            $newValues = $this->auditSnapshot($setting->fresh());
            $newValues['value_changed'] = $oldRawValue !== $setting->value;
            AuditLogger::log('settings', 'settings.updated', "แก้ไขการตั้งค่า {$setting->key}", $setting, $oldValues, $newValues);
        });

        return redirect()->route('settings.index')->with('success', 'บันทึกการตั้งค่าเรียบร้อยแล้ว');
    }

    public function destroy(Setting $setting)
    {
        DB::transaction(function () use ($setting) {
            AuditLogger::log('settings', 'settings.deleted', "ลบการตั้งค่า {$setting->key}", $setting, $this->auditSnapshot($setting));
            $setting->delete();
        });

        return redirect()->route('settings.index')->with('success', 'ลบการตั้งค่าเรียบร้อยแล้ว');
    }

    private function auditSnapshot(Setting $setting): array
    {
        return $setting->only(['key', 'label', 'group', 'type', 'description']);
    }
}
