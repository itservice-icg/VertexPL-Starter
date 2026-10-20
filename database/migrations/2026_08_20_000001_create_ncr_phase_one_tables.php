<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateNcrPhaseOneTables extends Migration
{
    public function up(): void
    {
        Schema::create('problem_types', function (Blueprint $table) {
            $table->id(); $table->string('code', 50)->unique(); $table->string('name_th');
            $table->text('description')->nullable(); $table->boolean('require_attachment')->default(false);
            $table->boolean('is_active')->default(true); $table->timestamps(); $table->softDeletes();
        });
        Schema::create('severity_levels', function (Blueprint $table) {
            $table->id(); $table->string('code', 50)->unique(); $table->string('name_th', 100);
            $table->unsignedInteger('sla_days')->nullable(); $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true); $table->timestamps(); $table->softDeletes();
        });
        Schema::create('disposition_types', function (Blueprint $table) {
            $table->id(); $table->string('code', 50)->unique(); $table->string('name_th');
            $table->text('description')->nullable(); $table->boolean('require_approval')->default(true);
            $table->boolean('is_active')->default(true); $table->timestamps(); $table->softDeletes();
        });
        Schema::create('root_cause_types', function (Blueprint $table) {
            $table->id(); $table->string('code', 50)->unique(); $table->string('name_th');
            $table->boolean('is_active')->default(true); $table->timestamps(); $table->softDeletes();
        });
        Schema::create('units', function (Blueprint $table) {
            $table->id(); $table->string('code', 50)->unique(); $table->string('name_th');
            $table->string('symbol', 30)->nullable(); $table->boolean('is_active')->default(true);
            $table->timestamps(); $table->softDeletes();
        });
        Schema::create('notification_rules', function (Blueprint $table) {
            $table->id(); $table->string('code', 100)->unique(); $table->string('name_th');
            $table->string('event', 100); $table->json('channels')->nullable();
            $table->boolean('is_active')->default(true); $table->timestamps(); $table->softDeletes();
        });
        Schema::create('ncr_statuses', function (Blueprint $table) {
            $table->id(); $table->string('code', 50)->unique(); $table->string('name_th');
            $table->unsignedInteger('sort_order')->default(0); $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('document_sequences', function (Blueprint $table) {
            $table->id(); $table->string('document_type', 50); $table->unsignedSmallInteger('year');
            $table->unsignedBigInteger('last_number')->default(0); $table->timestamps();
            $table->unique(['document_type', 'year']);
        });
    }

    public function down(): void
    {
        foreach (['document_sequences','ncr_statuses','notification_rules','units','root_cause_types','disposition_types','severity_levels','problem_types'] as $table) {
            Schema::dropIfExists($table);
        }
    }
}
