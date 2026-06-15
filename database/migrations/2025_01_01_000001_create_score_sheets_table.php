<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('score_sheets', function (Blueprint $table) {
            $table->id();
            $table->string('school_name')->nullable();
            $table->string('zone')->nullable();
            $table->string('ref_no')->nullable();
            $table->string('subject')->nullable();
            $table->string('exam_year')->nullable();
            $table->string('source_file')->nullable();
            $table->enum('scan_type', ['pdf', 'image'])->default('pdf');
            $table->timestamps();
        });

        Schema::create('score_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('score_sheet_id')
                  ->constrained('score_sheets')
                  ->onDelete('cascade');
            $table->integer('serial_no')->nullable();
            $table->string('candidate_name');
            $table->decimal('p1', 6, 2)->nullable();
            $table->decimal('p2', 6, 2)->nullable();
            $table->decimal('p3', 6, 2)->nullable();
            $table->decimal('p4', 6, 2)->nullable();
            $table->decimal('average', 6, 2)->nullable();
            $table->string('grade', 10)->nullable();  // supports "78", "A1", "B2" etc.
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('score_entries');
        Schema::dropIfExists('score_sheets');
    }
};