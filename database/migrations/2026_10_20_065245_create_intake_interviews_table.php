<?php

use App\Support\Form8;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HDC Form 8: Intake Interview (highly sensitive). A student can have many.
 * One nullable boolean per Yes/No question so reports can COUNT each concern (null = not answered).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('intake_interviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('counselor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('interview_at')->nullable();
            $table->date('date_of_entry')->nullable();
            $table->string('academic_status', 100)->nullable();

            // I. Personal data snapshot (pre-filled from Form 5)
            $table->string('name')->nullable();
            $table->string('nickname', 100)->nullable();
            $table->string('course')->nullable();
            $table->string('home_address', 500)->nullable();
            $table->unsignedTinyInteger('age')->nullable();
            $table->string('sex', 20)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('place_of_birth')->nullable();
            $table->string('civil_status', 20)->nullable();
            $table->string('spouse_name')->nullable();
            $table->unsignedTinyInteger('number_of_children')->nullable();
            $table->string('citizenship', 20)->nullable();
            $table->string('citizenship_other', 100)->nullable();
            $table->string('religion', 100)->nullable();
            $table->string('contact_number', 50)->nullable();
            $table->string('boarding_address', 500)->nullable();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_relationship', 100)->nullable();
            $table->string('emergency_contact_address', 500)->nullable();
            $table->string('emergency_contact_number', 50)->nullable();

            // II. Psychological concerns, relationship concerns; III. Coping strategies
            foreach (Form8::yesNoFields() as $field) {
                $table->boolean($field)->nullable();
            }

            // Free text describing concerns and IV–VIII counselor notes: encrypted (text for the ciphertext)
            foreach ([...Form8::SPECIFY_FIELDS, ...array_keys(Form8::NOTES)] as $field) {
                $table->text($field)->nullable();
            }
            $table->json('relationship_during_intake')->nullable();

            $table->string('status', 20)->default('draft')->index();
            $table->timestamp('finalized_at')->nullable();
            $table->boolean('interviewee_acknowledged')->default(false);
            $table->timestamps();

            $table->index(['student_id', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('intake_interviews');
    }
};
