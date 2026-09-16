<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            // Add API scanning columns
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->string('api_url')->nullable();
            $table->enum('status', ['pending', 'scanning', 'completed', 'failed'])->default('pending');
            $table->enum('severity', ['low', 'medium', 'high', 'critical'])->nullable();
            $table->json('findings')->nullable();
            $table->json('scan_result')->nullable();
            $table->timestamp('scanned_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn([
                'title',
                'description',
                'api_url',
                'status',
                'severity',
                'findings',
                'scan_result',
                'scanned_at',
            ]);
        });
    }
};
