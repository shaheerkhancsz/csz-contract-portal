<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One online-signing session per emailed link. Holds the signature capture + audit trail.
        Schema::create('signing_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->string('token', 64)->unique();          // bearer token in the signing URL
            $table->string('email')->nullable();            // client email the link was sent to (snapshot)
            $table->string('status', 20)->default('pending')->index(); // pending | verified | signed | voided
            $table->foreignId('sent_document_id')->nullable()->constrained('contract_documents')->nullOnDelete();
            $table->string('sent_sha256', 64)->nullable();  // hash of the PDF presented for signing (tamper pin)
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('verified_at')->nullable();   // when email OTP was confirmed
            $table->timestamp('signed_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            // Signature capture
            $table->string('signer_name')->nullable();      // typed full name — the legal record
            $table->string('signer_email')->nullable();
            $table->string('signature_image_path')->nullable(); // optional drawn signature (private disk)
            $table->boolean('intent_confirmed')->default(false);
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // OTP issuance log — supports re-issue, rate limiting and audit. Codes are hashed.
        Schema::create('signing_otps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('signing_request_id')->constrained()->cascadeOnDelete();
            $table->string('code_hash');
            $table->string('sent_to')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signing_otps');
        Schema::dropIfExists('signing_requests');
    }
};
