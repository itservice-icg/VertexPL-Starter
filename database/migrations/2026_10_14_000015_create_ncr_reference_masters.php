<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateNcrReferenceMasters extends Migration
{
    public function up(): void
    {
        foreach (['products', 'customers', 'suppliers'] as $tableName) {
            Schema::create($tableName, function (Blueprint $table) {
                $table->id();
                $table->string('code', 100)->unique();
                $table->string('name_th', 255);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        Schema::table('ncr_items', function (Blueprint $table) {
            $table->foreignId('product_id')->nullable()->after('ncr_report_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->after('product_id')->constrained('customers')->restrictOnDelete();
        });

        Schema::table('ncr_reports', function (Blueprint $table) {
            $table->foreignId('supplier_id')->nullable()->after('supplier_name')->constrained('suppliers')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ncr_reports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supplier_id');
        });

        Schema::table('ncr_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_id');
            $table->dropConstrainedForeignId('product_id');
        });

        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('products');
    }
}
