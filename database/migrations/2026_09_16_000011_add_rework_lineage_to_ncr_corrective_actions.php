<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddReworkLineageToNcrCorrectiveActions extends Migration
{
    public function up(): void
    {
        Schema::table('ncr_corrective_actions', function (Blueprint $table) {
            $table->foreignId('supersedes_corrective_action_id')->nullable()->after('corrective_action_cycle_id')->constrained('ncr_corrective_actions')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ncr_corrective_actions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supersedes_corrective_action_id');
        });
    }
}
