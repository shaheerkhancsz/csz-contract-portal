<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('company_name');
            $table->string('contact_name')->nullable();
            $table->string('contact_designation')->nullable();
            $table->string('email')->nullable()->index();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('country')->default('Pakistan');
            $table->string('website')->nullable();
            $table->string('ntn')->nullable();
            $table->string('strn')->nullable();
            $table->boolean('withholds_tax')->default(true);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('whmcs_client_id')->nullable()->index();
            $table->string('zoho_customer_id')->nullable()->index();
            $table->string('odoo_partner_id')->nullable()->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 10)->unique();        // used in agreement numbers, e.g. WMA
            $table->string('category')->nullable();
            $table->text('description')->nullable();
            $table->string('default_billing_cycle', 20)->default('monthly');
            $table->decimal('default_fee', 14, 2)->nullable();
            $table->decimal('min_fee', 14, 2)->nullable();
            $table->decimal('max_fee', 14, 2)->nullable();
            $table->unsignedSmallInteger('default_term_months')->default(12);
            $table->decimal('default_renewal_increase', 5, 2)->default(10);
            $table->decimal('default_late_fee', 5, 2)->default(5);
            $table->boolean('is_active')->default(true);
            // working copy edited in the template editor; saving creates a new version
            $table->longText('body')->nullable();
            $table->json('fields')->nullable();
            $table->json('clauses')->nullable();
            $table->unsignedBigInteger('current_version_id')->nullable();
            $table->timestamps();
        });

        Schema::create('template_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->longText('body');
            $table->json('fields')->nullable();
            $table->json('clauses')->nullable();
            $table->string('change_note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['template_id', 'version']);
        });

        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->string('agreement_no')->unique();
            $table->string('title');
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->foreignId('template_id')->constrained()->restrictOnDelete();
            $table->foreignId('template_version_id')->constrained()->restrictOnDelete();
            $table->string('status', 30)->default('draft')->index();
            $table->date('agreement_date')->nullable();
            $table->date('start_date');
            $table->unsignedSmallInteger('term_months')->default(12);
            $table->date('end_date');
            $table->string('currency', 3)->default('PKR');
            $table->decimal('fee', 14, 2);
            $table->string('billing_cycle', 20)->default('monthly'); // monthly|quarterly|annual|one_off|milestones|per_term|none
            $table->unsignedTinyInteger('invoice_day')->nullable();
            $table->unsignedTinyInteger('due_day')->nullable();
            $table->decimal('late_fee_percent', 5, 2)->default(0);
            $table->decimal('renewal_increase_percent', 5, 2)->default(0);
            $table->boolean('apply_sales_tax')->default(true);
            $table->decimal('sales_tax_rate', 5, 2)->default(15);
            $table->boolean('apply_wht')->default(true);
            $table->decimal('wht_rate', 5, 2)->default(4);
            $table->json('field_values')->nullable();
            $table->json('clause_flags')->nullable();
            $table->longText('frozen_html')->nullable();   // exact text approved; never changes afterwards
            $table->text('internal_notes')->nullable();
            $table->text('review_note')->nullable();
            $table->foreignId('parent_contract_id')->nullable()->constrained('contracts')->nullOnDelete(); // renewals
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('contract_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20);          // sent | signed_upload | signed
            $table->string('path');              // relative to storage/app/private
            $table->string('original_name')->nullable();
            $table->string('sha256', 64);
            $table->unsignedBigInteger('size')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('activity_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('action', 50);
            $table->text('description')->nullable();
            $table->json('properties')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        foreach (['activity_log', 'contract_documents', 'contracts', 'template_versions', 'templates', 'clients', 'settings'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
