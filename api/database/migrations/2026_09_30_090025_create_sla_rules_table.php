<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sla_rules', function (Blueprint $table) {
            $table->id();

            $table->string('priority');
            $table->unsignedInteger('response_time_hours');
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique('priority');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sla_rules');
    }
};
