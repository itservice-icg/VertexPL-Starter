<?php

namespace Tests\Unit;

use App\Http\Requests\SaveCorrectiveActionRequest;
use App\Http\Requests\SaveNcrReportRequest;
use App\Services\NcrFileUploadService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NcrAttachmentAllowlistTest extends TestCase
{
    public function test_xls_is_allowed_by_upload_service_and_both_request_validators(): void
    {
        Storage::fake('local');
        $metadata = app(NcrFileUploadService::class)->store(
            UploadedFile::fake()->create('evidence.xls', 100, 'application/vnd.ms-excel')
        );

        Storage::disk('local')->assertExists($metadata['stored_path']);
        $this->assertSame('evidence.xls', $metadata['original_name']);

        foreach ([new SaveNcrReportRequest(), new SaveCorrectiveActionRequest()] as $request) {
            $rules = $request->rules()['attachments.*'];
            $mimeRule = collect($rules)->first(static fn ($rule) => is_string($rule) && str_starts_with($rule, 'mimes:'));

            $this->assertNotFalse($mimeRule);
            $this->assertSame(
                NcrFileUploadService::ALLOWED_EXTENSIONS,
                explode(',', substr($mimeRule, strlen('mimes:')))
            );
        }
    }
}
