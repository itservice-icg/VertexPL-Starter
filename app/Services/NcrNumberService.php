<?php

namespace App\Services;

use App\Models\DocumentSequence;
use Illuminate\Support\Facades\DB;

class NcrNumberService
{
    public function next(?int $year = null): string
    {
        $year = $year ?? (int) now()->format('Y');

        return DB::transaction(function () use ($year) {
            DocumentSequence::query()->firstOrCreate(
                ['document_type' => 'NCR', 'year' => $year],
                ['last_number' => 0]
            );
            $sequence = DocumentSequence::query()->where('document_type', 'NCR')->where('year', $year)->lockForUpdate()->firstOrFail();
            $sequence->increment('last_number');

            return sprintf('NCR-%d-%05d', $year, $sequence->fresh()->last_number);
        }, 3);
    }
}
