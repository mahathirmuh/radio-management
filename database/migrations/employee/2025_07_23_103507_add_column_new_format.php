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
        Schema::table('employee_contractor', function (Blueprint $table) {
            $table->string('mine_permit_id')->nullable()->after('id');
            $table->string('rfid_number')->nullable()->after('mine_permit_id');
            $table->text('first_name')->nullable()->after('rfid_number');
            $table->text('last_name')->nullable()->after('first_name');
            $table->text('nik_valid_until')->nullable()->after('identity_number');
            $table->text('passport_number')->nullable()->after('nik_valid_until');
            $table->text('passport_valid_until')->nullable()->after('passport_number');
            $table->text('visa_number')->nullable()->after('passport_valid_until');
            $table->text('visa_valid_until')->nullable()->after('visa_number');
            $table->text('itas_number')->nullable()->after('visa_valid_until');
            $table->text('itas_valid_until')->nullable()->after('itas_number');
            $table->text('address_different')->nullable()->after('province');
            $table->text('sub_district_different')->nullable()->after('address_different');
            $table->text('district_different')->nullable()->after('sub_district_different');
            $table->text('city_different')->nullable()->after('district_different');
            $table->text('province_different')->nullable()->after('city_different');
            $table->string('ethnicity')->nullable()->after('nationality');
            $table->text('emergency_contact_name')->nullable()->after('home_phone');
            $table->text('emergency_contact_relation')->nullable()->after('emergency_contact_name');
            $table->text('emergency_contact_phone')->nullable()->after('emergency_contact_relation');
            $table->text('business_field')->nullable()->after('contractor_name');
            $table->text('contract_duration')->nullable()->after('status_employee_in_contractor');
            $table->date('contract_start')->nullable()->after('contract_duration');
            $table->date('contract_end')->nullable()->after('contract_start');
            $table->text('responsible_company')->nullable()->after('company_name');
            $table->text('responsible_department')->nullable()->after('responsible_company');
            $table->string('leave_roster')->nullable()->after('photo_path');
            $table->text('work_system')->nullable()->after('leave_roster');
            $table->text('work_hours_per_day')->nullable()->after('work_system');
            $table->text('camp_location')->nullable()->after('work_hours_per_day');
            $table->text('bed_number')->nullable()->after('room_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employee_contractor', function (Blueprint $table) {
            $table->dropColumn('mine_permit_id');
            $table->dropColumn('rfid_number');
            $table->dropColumn('first_name');
            $table->dropColumn('last_name');
            $table->dropColumn('nik_valid_until');
            $table->dropColumn('passport_number');
            $table->dropColumn('passport_valid_until');
            $table->dropColumn('visa_number');
            $table->dropColumn('visa_valid_until');
            $table->dropColumn('itas_number');
            $table->dropColumn('itas_valid_until');
            $table->dropColumn('address_different');
            $table->dropColumn('sub_district_different');
            $table->dropColumn('district_different');
            $table->dropColumn('city_different');
            $table->dropColumn('province_different');
            $table->dropColumn('ethnicity');
            $table->dropColumn('emergency_contact_name');
            $table->dropColumn('emergency_contact_relation');
            $table->dropColumn('emergency_contact_phone');
            $table->dropColumn('business_field');
            $table->dropColumn('contract_duration');
            $table->dropColumn('contract_start');
            $table->dropColumn('contract_end');
            $table->dropColumn('responsible_company');
            $table->dropColumn('responsible_department');
            $table->dropColumn('leave_roster');
            $table->dropColumn('work_system');
            $table->dropColumn('work_hours_per_day');
            $table->dropColumn('camp_location');
            $table->dropColumn('bed_number');
        });
    }
};
