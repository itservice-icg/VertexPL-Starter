<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class NcrFileUploadService
{
    public const ALLOWED_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png', 'xlsx', 'docx'];
    public const MAX_KILOBYTES = 10240;

    public function store(UploadedFile $file, string $directory = 'ncr'): array
    {
        if (! $file->isValid() || $file->getSize() > self::MAX_KILOBYTES * 1024 || ! in_array(strtolower($file->getClientOriginalExtension()), self::ALLOWED_EXTENSIONS, true)) {
            throw ValidationException::withMessages(['attachment' => 'ไฟล์ไม่ถูกต้อง รองรับ PDF, รูปภาพ และเอกสาร Office ขนาดไม่เกิน 10 MB']);
        }

        $path = $file->store($directory, ['disk' => 'local']);

        return ['original_name' => $file->getClientOriginalName(), 'stored_path' => $path, 'mime_type' => $file->getMimeType(), 'file_size' => $file->getSize()];
    }

    public function delete(string $path): bool
    {
        return Storage::disk('local')->delete($path);
    }
}
