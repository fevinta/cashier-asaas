<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('asaas_id')->nullable()->index()->after('id');
            $table->timestamp('trial_ends_at')->nullable()->after('asaas_id');
        });

        Schema::create('asaas_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('asaas_id')->unique();
            $table->string('asaas_status');
            $table->string('plan');
            $table->decimal('value', 10, 2);
            $table->string('cycle');
            $table->string('billing_type');
            $table->timestamp('next_due_date')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'type']);
            $table->index('asaas_status');
        });

        Schema::create('asaas_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->nullable()->constrained('asaas_subscriptions')->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable();
            $table->string('asaas_id')->unique();
            $table->string('billing_type');
            $table->decimal('value', 10, 2);
            $table->decimal('net_value', 10, 2)->nullable();
            $table->string('status');
            $table->date('due_date');
            $table->date('payment_date')->nullable();
            $table->timestamp('confirmed_date')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->string('invoice_url')->nullable();
            $table->string('bank_slip_url')->nullable();
            $table->text('pix_qrcode')->nullable();
            $table->text('pix_copy_paste')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'status']);
            $table->index('status');
            $table->index('due_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asaas_payments');
        Schema::dropIfExists('asaas_subscriptions');
        
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['asaas_id', 'trial_ends_at']);
        });
    }
};
