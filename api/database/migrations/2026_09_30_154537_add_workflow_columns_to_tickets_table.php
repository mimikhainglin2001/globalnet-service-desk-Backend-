<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            // The sequential reference (GN-000123) is derived from the id, so it
            // is only known after the insert.
            $table->string('reference', 30)->nullable()->change();

            // Drives the "average first-response time" dashboard metric.
            $table->timestamp('first_responded_at')->nullable()->after('due_at');

            $table->index('created_at');
            $table->index('resolved_at');

            if (DB::getDriverName() === 'mysql') {
                $table->fullText(['subject', 'description']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            if (DB::getDriverName() === 'mysql') {
                $table->dropFullText(['subject', 'description']);
            }

            $table->dropIndex(['created_at']);
            $table->dropIndex(['resolved_at']);
            $table->dropColumn('first_responded_at');
        });
    }
};
