<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // An older, different `coupons` table (from an earlier line of this project) may already exist. It is kept under
        // another name instead of being dropped, so nothing is lost.
        if (Schema::hasTable('coupons') && ! Schema::hasColumn('coupons', 'min_order')) {
            Schema::rename('coupons', 'coupons_legacy');
        }

        // Discount codes a student types at checkout. `value` is a percentage (type "percent") or an amount in rupees
        // (type "fixed"). A coupon can be limited to one batch, to a minimum order, to a number of uses and to a date range.
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('description')->nullable();
            $table->string('type', 10)->default('percent');
            $table->decimal('value', 10, 2);
            $table->decimal('max_discount', 10, 2)->nullable();
            $table->decimal('min_order', 10, 2)->nullable();
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('per_user_limit')->default(1);
            $table->unsignedInteger('used_count')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('batch_id')->nullable()->constrained('batches')->nullOnDelete();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
