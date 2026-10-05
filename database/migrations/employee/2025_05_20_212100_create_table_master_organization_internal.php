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
        Schema::create('master_organization_internal', function (Blueprint $table) {
            $table->id();
            $table->string('company_id')->nullable(); 
            $table->string('org_id')->nullable(); 
            $table->string('name')->nullable(); 
            $table->string('parent_org_id')->nullable(); 
            $table->string('level')->nullable(); 
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('master_organization_internal');
    }
};
