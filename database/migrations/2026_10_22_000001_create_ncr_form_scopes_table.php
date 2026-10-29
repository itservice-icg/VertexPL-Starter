<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateNcrFormScopesTable extends Migration
{
    public function up(): void
    {
        Schema::create('ncr_form_scopes', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('name_th', 255);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        $now = now();
        DB::table('ncr_form_scopes')->insert([
            ['code' => 'AI', 'name_th' => 'AI', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'UP', 'name_th' => 'UP', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('ncr_form_scopes');
    }
}
