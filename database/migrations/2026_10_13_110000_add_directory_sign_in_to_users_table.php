<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The two facts the sign-in method of an account is told from (User::signInMethod()):
 *
 * - ldap_username: the Active Directory account it signs in with on the login form. Null for an account that signs in
 *   with Microsoft only. Lower case; one directory account belongs to one account here (the form checks that).
 * - microsoft_linked_at: when it first signed in with Microsoft. Filled for the accounts whose last sign-in is known
 *   to have been one.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'ldap_username')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('ldap_username', 128)->nullable()->index();
                $table->dateTime('microsoft_linked_at')->nullable();
            });
        }

        if (Schema::hasColumn('users', 'last_login_method')) {
            DB::table('users')
                ->where('last_login_method', 'Microsoft')
                ->whereNull('microsoft_linked_at')
                ->update(['microsoft_linked_at' => DB::raw('last_login_at')]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'ldap_username')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropIndex(['ldap_username']);
                $table->dropColumn(['ldap_username', 'microsoft_linked_at']);
            });
        }
    }
};
