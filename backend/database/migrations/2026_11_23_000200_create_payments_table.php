<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per checkout. Amounts are stored in paise (1/100 rupee) so no rounding ever creeps in. The student and the
        // item are also copied in as text, so the receipt and the books stay correct if a student or batch is deleted later.
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_no', 40)->nullable()->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_name')->nullable();
            $table->string('customer_email')->nullable();
            $table->foreignId('batch_id')->nullable()->constrained('batches')->nullOnDelete();
            $table->string('item_title');
            $table->foreignId('coupon_id')->nullable()->constrained('coupons')->nullOnDelete();
            $table->string('coupon_code', 40)->nullable();
            $table->unsignedBigInteger('amount_original_paise');
            $table->unsignedBigInteger('discount_paise')->default(0);
            $table->unsignedBigInteger('amount_paise');
            $table->string('currency', 3)->default('INR');
            $table->string('status', 20)->default('created')->index(); // created | paid | failed | refunded
            $table->string('razorpay_order_id', 80)->nullable()->unique();
            $table->string('razorpay_payment_id', 80)->nullable()->index();
            $table->string('method', 40)->nullable();
            $table->timestamp('paid_at')->nullable()->index();
            $table->text('failed_reason')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->text('refund_note')->nullable();
            $table->foreignId('refunded_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
