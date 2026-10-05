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
        Schema::create('employee_contractor', function (Blueprint $table) {
            $table->id();
            $table->string('employee_id')->nullable(); // NIP/NIK
            $table->string('name');
            $table->string('gender')->nullable(); // L / P
            $table->string('birth_place')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('marital_status')->nullable();
            $table->string('blood_type')->nullable();
            $table->string('religion')->nullable();
            $table->string('phone_number')->nullable();
            $table->string('home_phone')->nullable();
            $table->string('email')->nullable();
            $table->string('identity_number')->nullable()->comment('KTP/Paspor'); 
            $table->string('category_person')->nullable();
            $table->string('category_nationality')->nullable();
            $table->string('nationality')->nullable();
            $table->enum('hire_type', ['internal', 'contract','intern'])->nullable();

            // Alamat
            $table->text('address')->nullable();
            $table->string('district')->nullable();
            $table->string('sub_district')->nullable();
            $table->string('city')->nullable();
            $table->string('province')->nullable();
            $table->string('postal_code')->nullable();

            // Pekerjaan
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->string('company_name')->nullable();
            $table->string('contractor_id')->nullable(); 
            $table->string('contractor_name')->nullable(); 
            $table->string('project')->nullable();
            $table->string('under_contractor')->nullable();
            $table->string('status_employee_in_contractor')->nullable();
            $table->string('hire_accepted_location')->nullable();
            $table->string('level')->nullable();

            $table->string('department')->nullable();
            $table->string('department_code')->nullable();
            $table->string('division')->nullable();
            $table->string('division_code')->nullable();
            $table->string('position_title')->nullable();
            $table->string('position_code')->nullable();
            $table->string('position_level')->nullable();
            $table->string('grade_interval')->nullable();
            $table->string('section')->nullable();

            // Pendidikan
            $table->string('education_level')->nullable();
            $table->string('education_institution')->nullable();
            $table->string('education_field')->nullable();

            // Tanggal kerja
            $table->date('first_join_date')->nullable();
            $table->date('join_date')->nullable();
            $table->date('valid_to')->nullable();
            $table->integer('year_in_group')->nullable();
            $table->integer('year_in_service')->nullable();
            
            // Lokasi kerja dan transportasi 
            $table->string('point_of_hire')->nullable();
            $table->string('point_of_origin')->nullable();
            $table->string('work_location')->nullable();
            $table->string('work_location_category')->nullable();
            $table->string('company_office')->nullable(); 
            $table->string('departure_city')->nullable();
            $table->string('destination_city')->nullable();
            $table->string('transit')->nullable();
            $table->string('final_destination')->nullable();
            
            // Data Kepegawaian
            $table->string('NPWP')->nullable(); 
            $table->string('BPJSTK')->nullable(); 
            $table->string('BPJSK')->nullable(); 
            $table->string('bank_account')->nullable(); 
            
            // Atasan dan akomodasi
            $table->foreignId('supervisor_id')->nullable();
            $table->string('supervisor_name')->nullable();
            $table->string('photo_path')->nullable();
            $table->string('mess')->nullable();
            $table->string('room_number')->nullable();
            $table->string('flight_type')->nullable(); // Flight / Non-Flight

            // Transport info
            $table->string('data_mark')->comment('Sehat/MyOrange/Contractor Excel/M-Safe'); // Sumber data yang digunakan 
            $table->string('is_active')->default('1'); // Aktif / Tidak Aktif 

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_contractor');
    }
};
