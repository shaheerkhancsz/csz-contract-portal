<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('signing_requests', function (Blueprint $table) {
            $table->unsignedTinyInteger('reminders_sent')->default(0)->after('intent_confirmed');
            $table->timestamp('last_reminded_at')->nullable()->after('reminders_sent');
        });
    }

    public function down(): void
    {
        Schema::table('signing_requests', function (Blueprint $table) {
            $table->dropColumn(['reminders_sent', 'last_reminded_at']);
        });
    }
};
