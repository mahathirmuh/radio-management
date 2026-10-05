<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The original titles carried a redundant "User Management - " prefix that
     * made sense as a standalone label before these rows were nested under the
     * "User Management" group, but overflows the sidebar dropdown now that the
     * sidebar renders `title` directly instead of a hardcoded short label.
     */
    private array $renames = [
        98 => 'User Registered',
        99 => 'Role & Permission',
    ];

    public function up()
    {
        foreach ($this->renames as $id => $title) {
            DB::table('menu')->where('id', $id)->update(['title' => $title]);
        }
    }

    public function down()
    {
        DB::table('menu')->where('id', 98)->update(['title' => 'User Management - User Registered']);
        DB::table('menu')->where('id', 99)->update(['title' => 'User Management - Role and Permission']);
    }
};
