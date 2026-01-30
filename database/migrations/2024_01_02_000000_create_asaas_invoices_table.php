<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('asaas_invoices')) {
            Schema::create('asaas_invoices', function (Blueprint $table) {
                $table->id();
                $table->foreignId('payment_id')->nullable()->constrained('asaas_payments')->nullOnDelete();
                $table->foreignId('customer_id')->nullable();
                $table->string('asaas_id')->unique();
                $table->string('status')->default('SCHEDULED');
                $table->string('type')->nullable();
                $table->date('effective_date');
                $table->decimal('value', 10, 2);
                $table->decimal('deductions', 10, 2)->nullable();
                $table->decimal('net_value', 10, 2)->nullable();
                $table->text('service_description');
                $table->text('observations')->nullable();
                $table->string('municipal_service_id')->nullable();
                $table->string('municipal_service_code')->nullable();
                $table->string('municipal_service_name')->nullable();
                $table->string('rps_number')->nullable();
                $table->string('rps_series')->nullable();
                $table->string('invoice_number')->nullable();
                $table->string('verification_code')->nullable();
                $table->string('pdf_url')->nullable();
                $table->string('xml_url')->nullable();
                $table->json('taxes')->nullable();
                $table->string('external_reference')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['customer_id', 'status']);
                $table->index('status');
                $table->index('effective_date');
                $table->index('external_reference');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('asaas_invoices');
    }
};
