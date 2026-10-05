<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SQL Server refuses multiple cascade paths between these tables, so the
     * delete actions fall back to NO ACTION there (records are soft-deleted anyway).
     */
    private function fk(string $action): string
    {
        return DB::getDriverName() === 'sqlsrv' ? 'no action' : $action;
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Create APPROVAL_FLOWS table
        Schema::create('approval_flows', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('status', ['ACTIVE', 'INACTIVE'])->default('active');
            $table->timestamps();
            $table->softDeletes();
        });

        // 2. Create APPROVAL_STEPS table
        Schema::create('approval_steps', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('approval_flow_id')->constrained('approval_flows')->onDelete($this->fk('cascade'));
            $table->integer('step_order');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete($this->fk('set null'));
            $table->foreignId('role_id')->nullable()->constrained('roles')->onDelete($this->fk('set null'));
            $table->boolean('is_final_step')->default(false);
            $table->boolean('is_special_case')->default(false);
            $table->timestamps();
            $table->softDeletes();
            
            // Ensure steps are ordered correctly within a flow
            $table->unique(['approval_flow_id', 'step_order']);
        });

        // 3. Create APPROVALS table
        Schema::create('approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_flow_id')->constrained('approval_flows')->onDelete($this->fk('cascade'));
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->foreignId('current_step_id')->nullable()->constrained('approval_steps')->onDelete($this->fk('set null'));
            $table->enum('status', ['DRAFT', 'PENDING', 'APPROVED', 'REJECTED', 'REVISED'])->default('DRAFT');
            $table->foreignId('takeover_by')->nullable()->constrained('users')->onDelete($this->fk('set null'));
            $table->timestamps();
            $table->softDeletes();
            
            // Polymorphic relationship with the model that needs approval
            $table->index(['model_type', 'model_id']);
        });

        // 4. Create APPROVAL_HISTORIES table
        Schema::create('approval_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_id')->constrained('approvals')->onDelete($this->fk('cascade'));
            $table->foreignId('step_id')->nullable()->constrained('approval_steps')->onDelete($this->fk('set null'));
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete($this->fk('set null'));
            $table->enum('action', ['APPROVE', 'REJECT', 'REVISE', 'DELEGATE', 'TAKEOVER'])->default('APPROVE');
            $table->text('note')->nullable();
            $table->foreignId('delegated_to')->nullable()->constrained('users')->onDelete($this->fk('set null'));
            $table->timestamps();
        });

        // 5. Create DELEGATIONS table
        Schema::create('delegations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_id')->constrained('approvals')->onDelete($this->fk('cascade'));
            $table->foreignId('from_user_id')->constrained('users')->onDelete($this->fk('cascade'));
            $table->foreignId('to_user_id')->constrained('users')->onDelete($this->fk('cascade'));
            $table->foreignId('created_by')->constrained('users')->onDelete($this->fk('cascade'));
            $table->text('note')->nullable();
            $table->enum('status', ['ACTIVE', 'INACTIVE'])->default('active');
            $table->timestamp('start_date')->nullable();
            $table->timestamp('end_date')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop tables in reverse order to respect foreign key constraints
        Schema::dropIfExists('delegations');
        Schema::dropIfExists('approval_histories');
        Schema::dropIfExists('approvals');
        Schema::dropIfExists('approval_steps');
        Schema::dropIfExists('approval_flows');
    }
};