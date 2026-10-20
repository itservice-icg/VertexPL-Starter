<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateNotificationsTable extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('notifications')) return;
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('ncr_report_id')->nullable()->constrained('ncr_reports')->nullOnDelete();
            $table->string('notification_type', 100);
            $table->string('title');
            $table->text('message');
            $table->string('channel', 50)->default('IN_APP');
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['user_id', 'is_read'], 'notification_user_read_idx');
            $table->index(['ncr_report_id', 'notification_type'], 'notification_report_type_idx');
        });
    }
    public function down(): void { Schema::dropIfExists('notifications'); }
}
