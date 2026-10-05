<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Extends the approval module to support:
 *  - Sequential STAGES that each contain PARALLEL steps (rule: ALL / ANY / QUORUM)
 *  - Runtime instances (snapshot) so an approval in-flight can be edited / have
 *    ad-hoc approvers inserted in the middle without touching the template
 *  - Custom required fields / file attachments per step
 */
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

    public function up(): void
    {
        // ---- TEMPLATE: stages (sequential groups that own parallel steps) ----
        Schema::create('approval_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_flow_id')->constrained('approval_flows')->onDelete($this->fk('cascade'));
            $table->string('name');
            $table->integer('stage_order');
            $table->enum('rule', ['ALL', 'ANY', 'QUORUM'])->default('ALL');
            $table->unsignedInteger('quorum_count')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['approval_flow_id', 'stage_order']);
        });

        // ---- TEMPLATE: attach steps to a stage, allow parallel steps per order ----
        // The composite unique also backs the approval_flow_id foreign key, so add a
        // plain index first, otherwise MySQL refuses to drop the unique (error 1553).
        Schema::table('approval_steps', function (Blueprint $table) {
            $table->index('approval_flow_id', 'approval_steps_approval_flow_id_index');
        });
        Schema::table('approval_steps', function (Blueprint $table) {
            // one order per flow no longer holds: multiple steps share an order (parallel)
            $table->dropUnique(['approval_flow_id', 'step_order']);
            $table->foreignId('stage_id')->nullable()->after('approval_flow_id')
                ->constrained('approval_stages')->onDelete($this->fk('cascade'));
        });

        // ---- TEMPLATE: custom field definitions per step ----
        Schema::create('approval_step_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_step_id')->constrained('approval_steps')->onDelete($this->fk('cascade'));
            $table->string('key');
            $table->string('label');
            $table->enum('type', ['text', 'textarea', 'number', 'date', 'select', 'file', 'checkbox'])->default('text');
            $table->boolean('is_required')->default(false);
            $table->json('options')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        // ---- RUNTIME: snapshot of stages for a running approval ----
        Schema::create('approval_stage_instances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_id')->constrained('approvals')->onDelete($this->fk('cascade'));
            $table->foreignId('stage_id')->nullable()->constrained('approval_stages')->onDelete($this->fk('set null'));
            $table->string('name');
            $table->decimal('stage_order', 8, 2); // decimal so we can insert 2.5 between 2 and 3
            $table->enum('rule', ['ALL', 'ANY', 'QUORUM'])->default('ALL');
            $table->unsignedInteger('quorum_count')->nullable();
            $table->enum('status', ['PENDING', 'ACTIVE', 'APPROVED', 'REJECTED', 'SKIPPED'])->default('PENDING');
            $table->timestamps();
        });

        // ---- RUNTIME: snapshot of steps (approvers) for a running approval ----
        Schema::create('approval_step_instances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_id')->constrained('approvals')->onDelete($this->fk('cascade'));
            $table->foreignId('stage_instance_id')->constrained('approval_stage_instances')->onDelete($this->fk('cascade'));
            $table->foreignId('step_id')->nullable()->constrained('approval_steps')->onDelete($this->fk('set null'));
            $table->string('name')->nullable();
            $table->decimal('order', 8, 2)->default(0);
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete($this->fk('set null'));
            $table->foreignId('role_id')->nullable()->constrained('roles')->onDelete($this->fk('set null'));
            $table->enum('status', ['PENDING', 'APPROVED', 'REJECTED', 'SKIPPED', 'DELEGATED'])->default('PENDING');
            $table->json('fields_schema')->nullable(); // snapshot of required custom fields
            $table->foreignId('acted_by')->nullable()->constrained('users')->onDelete($this->fk('set null'));
            $table->timestamp('acted_at')->nullable();
            $table->text('note')->nullable();
            $table->boolean('is_adhoc')->default(false); // inserted at runtime
            $table->foreignId('inserted_by')->nullable()->constrained('users')->onDelete($this->fk('set null'));
            $table->timestamps();
        });

        // ---- RUNTIME: captured custom field values ----
        Schema::create('approval_field_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_id')->constrained('approvals')->onDelete($this->fk('cascade'));
            $table->foreignId('step_instance_id')->constrained('approval_step_instances')->onDelete($this->fk('cascade'));
            $table->string('field_key');
            $table->string('label')->nullable();
            $table->string('type')->default('text');
            $table->text('value')->nullable();
            $table->timestamps();
        });

        // ---- RUNTIME: uploaded attachments ----
        Schema::create('approval_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_id')->constrained('approvals')->onDelete($this->fk('cascade'));
            $table->foreignId('step_instance_id')->nullable()->constrained('approval_step_instances')->onDelete($this->fk('cascade'));
            $table->string('field_key')->nullable();
            $table->string('original_name');
            $table->string('path');
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->onDelete($this->fk('set null'));
            $table->timestamps();
        });

        // ---- track the active stage instance on the approval ----
        Schema::table('approvals', function (Blueprint $table) {
            $table->foreignId('current_stage_instance_id')->nullable()->after('current_step_id')
                ->constrained('approval_stage_instances')->onDelete($this->fk('set null'));
        });

        $this->backfillStages();
    }

    /**
     * Existing flows only have flat steps. Convert each distinct step_order into a
     * stage (rule ALL) so old data keeps working under the new engine.
     */
    protected function backfillStages(): void
    {
        $flowIds = DB::table('approval_flows')->whereNull('deleted_at')->pluck('id');

        foreach ($flowIds as $flowId) {
            $steps = DB::table('approval_steps')
                ->where('approval_flow_id', $flowId)
                ->whereNull('deleted_at')
                ->orderBy('step_order')
                ->get();

            foreach ($steps as $step) {
                $stageId = DB::table('approval_stages')
                    ->where('approval_flow_id', $flowId)
                    ->where('stage_order', $step->step_order)
                    ->value('id');

                if (! $stageId) {
                    $stageId = DB::table('approval_stages')->insertGetId([
                        'approval_flow_id' => $flowId,
                        'name' => $step->name,
                        'stage_order' => $step->step_order,
                        'rule' => 'ALL',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                DB::table('approval_steps')->where('id', $step->id)->update(['stage_id' => $stageId]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('approvals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_stage_instance_id');
        });

        Schema::dropIfExists('approval_attachments');
        Schema::dropIfExists('approval_field_values');
        Schema::dropIfExists('approval_step_instances');
        Schema::dropIfExists('approval_stage_instances');
        Schema::dropIfExists('approval_step_fields');

        Schema::table('approval_steps', function (Blueprint $table) {
            $table->dropConstrainedForeignId('stage_id');
            $table->unique(['approval_flow_id', 'step_order']);
        });

        Schema::dropIfExists('approval_stages');
    }
};
