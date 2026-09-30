<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();

            $table->string('key', 255);
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('request_hash', 64);

            $table->unsignedSmallInteger('response_status');
            $table->json('response_body');

            $table->timestamp('expires_at');

            $table->timestamps();

            $table->unique(['key', 'user_id']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
