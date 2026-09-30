<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outbox_events', function (Blueprint $table) {
            $table->unsignedSmallInteger('attempts')->default(0)->after('payload');
            $table->text('last_error')->nullable()->after('attempts');
            $table->timestamp('failed_at')->nullable()->after('processed_at');

            $table->index(['processed_at', 'failed_at', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('outbox_events', function (Blueprint $table) {
            $table->dropIndex(['processed_at', 'failed_at', 'created_at']);
            $table->dropColumn(['attempts', 'last_error', 'failed_at']);
        });
    }
};
