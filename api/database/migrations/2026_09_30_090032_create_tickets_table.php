<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();

            $table->string('reference', 30)->unique();

            $table->string('subject');
            $table->text('description');

            $table->foreignId('category_id')
                ->constrained()
                ->restrictOnDelete();

            $table->string('priority');
            $table->string('status')->default('open');

            $table->foreignId('requester_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('assignee_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('team_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->timestamp('due_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();

            $table->timestamps();

            $table->index(['status', 'priority']);
            $table->index(['team_id', 'status']);
            $table->index(['requester_id', 'status']);
            $table->index('assignee_id');
            $table->index('due_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
