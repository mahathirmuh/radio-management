<?php

namespace Tests\Feature;

use App\Models\Log\AuditLog;
use App\Models\Notification\UserNotification;
use App\Services\Audit\AuditLogger;
use App\Services\Notifications\Notifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\RadioManagement\Models\RadioType;
use Tests\Concerns\MakesUsers;
use Tests\Concerns\UsesApi;
use Tests\Concerns\UsesEnglish;
use Tests\TestCase;

/**
 * The pages of Settings in their other language: Audit Log and API Tokens were written in English, so here they are
 * checked in Indonesian (the default); Notifikasi was written in Indonesian, so here it is checked in English too. Their
 * own tests (AuditLogTest, ApiTokensPageTest, NotificationsTest) check them in the language they were written in.
 */
class SystemPagesLanguageTest extends TestCase
{
    use MakesUsers;
    use RefreshDatabase;
    use UsesApi;
    use UsesEnglish;

    protected bool $seed = true;

    public function test_the_audit_log_is_in_indonesian_by_default_and_tells_its_entries_in_it(): void
    {
        $admin = $this->superAdmin();
        $budi = $this->makeUser('budi@test.local', 'Admin Radio', 'Budi Santoso');
        $logger = app(AuditLogger::class);
        $this->actingAs($budi);
        $status = $logger->log(AuditLogger::USER_STATUS_CHANGED, $admin, ['Status' => 'Active', 'Checked' => false], ['Status' => 'Inactive', 'Checked' => true], label: $admin->email);
        $this->actingAs($admin);
        $type = $logger->log(AuditLogger::MASTER_UPDATED, (new RadioType())->forceFill(['id' => 3]), ['Name' => 'A'], ['Name' => 'B']);
        // A failed sign-in, from a browser: nobody signed in.
        auth()->logout();
        $failed = $logger->log(AuditLogger::LOGIN_FAILED, new: ['Account' => 'siti.rahma']);
        DB::table('audit_logs')->where('id', $failed->id)->update(['user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36']);
        $this->actingAs($admin);

        $page = (string) $this->get(route('audit-log.index'))->assertOk()->getContent();
        foreach (['Log aktivitas', 'Email user', 'Cari menurut email', 'Semua aksi', 'Semua entitas', 'Rentang tanggal', 'Pilih tanggal…',
            'Detail kejadian', 'Terbaru di atas', 'aria-label="Tutup detail"'] as $part) {
            $this->assertStringContainsString($part, $page, $part);
        }
        preg_match_all('#<th scope="col"[^>]*>([^<]*)</th>#', $page, $titles);
        $this->assertSame(['Waktu', 'User', 'Aksi', 'Entitas', 'Ringkasan', 'IP'], $titles[1]);
        // The actions by their names in Indonesian, under the parts of the application in theirs.
        $this->assertMatchesRegularExpression('#<option value="">Semua aksi</option>\s*<optgroup label="Masuk &amp; keluar">\s*<option value="auth.login_failed"\s*>Gagal masuk</option>\s*</optgroup>\s*'
            . '<optgroup label="Akun">\s*<option value="user.status_changed"\s*>Status akun diubah</option>\s*</optgroup>\s*'
            . '<optgroup label="Data master">\s*<option value="master_data.updated"\s*>Data master diubah</option>\s*</optgroup>\s*</select>#', $page);
        // The kinds of record by their names in Indonesian, in the order of those names; the ranges by theirs.
        $this->assertMatchesRegularExpression('#<option value="">Semua entitas</option>\s*<option value="User"\s*>Akun</option>\s*<option value="RadioType"\s*>Tipe radio</option>#', $page);
        $this->assertMatchesRegularExpression('#<option value="today"\s*>Hari ini</option>\s*<option value="yesterday"\s*>Kemarin</option>\s*<option value="7d"\s*>7 hari terakhir</option>\s*<option value="30d"\s*>30 hari terakhir</option>\s*<option value="all" selected>Semua waktu</option>#', $page);
        // The script's words and the calendar's months.
        foreach (['"auth.login":"Masuk"', '"Oktober"', '"Sn"', '"pick_start":"Pilih tanggal awal"'] as $part) {
            $this->assertStringContainsString($part, $page, $part);
        }
        // (The comments of the script still name things in English: what the page shows is looked for.)
        foreach (['>All actions<', 'placeholder="Search by email"', '>Log activity<', '>Event details<', '>Last 7 days<', '"Signed in"', '"October"'] as $english) {
            $this->assertStringNotContainsString($english, $page, $english);
        }

        $rows = collect($this->getJson(route('audit-log.list'))->assertOk()->json('data'))->keyBy('id');
        $this->assertSame(['Tamu', 'gagal masuk', 'Chrome 141 di Windows'], [$rows[$failed->id]['user'], $rows[$failed->id]['activity'], $rows[$failed->id]['browser']]);
        $this->assertSame(['mengubah', 'RadioType #3'], [$rows[$type->id]['activity'], $rows[$type->id]['entity']]);
        $this->assertSame('mengubah status', $rows[$status->id]['activity']);
        // What was written stays as it was written; only a yes or a no is said in the words of the page.
        $this->assertSame([['field' => 'Status', 'old' => 'Active', 'new' => 'Inactive'], ['field' => 'Checked', 'old' => 'Tidak', 'new' => 'Ya']], $rows[$status->id]['changes']);

        // The API keeps the words of the trail, whatever language it is asked in.
        $api = collect($this->api('GET', '/api/v1/audit-log', token: $this->tokenFor($admin), headers: ['Accept-Language' => 'id'])->assertOk()->json('data'))->keyBy('id');
        $this->assertSame(['changed the status of', 'Guest', 'failed to sign in'], [$api[$status->id]['activity'], $api[$failed->id]['user']['name'], $api[$failed->id]['activity']]);

        // Before its migration: the page and the list say so in Indonesian.
        Schema::drop('audit_logs');
        Cache::forget(AuditLog::READY_CACHE_KEY);
        $this->actingAs($admin);
        $this->assertStringContainsString('Audit Log belum aktif: migrasinya belum dijalankan di database ini (<code>php artisan migrate</code>).',
            (string) $this->get(route('audit-log.index'))->assertOk()->getContent());
        $this->getJson(route('audit-log.list'))->assertStatus(503)->assertJsonPath('message', 'Audit Log belum aktif: migrasinya belum dijalankan di database ini.');
    }

    public function test_api_tokens_is_in_indonesian_by_default_with_what_its_server_answers(): void
    {
        $this->actingAs($this->superAdmin());

        $page = (string) $this->get(route('api-tokens.index'))->assertOk()->getContent();
        foreach (['<h1 class="rm-title">API Tokens</h1>',
            'Token baru', 'Client baru', 'Dokumentasi API', '<h2 class="rm-section-title">API client</h2>', '<th scope="col">Nama</th>',
            '<th scope="col">Terakhir dipakai</th>', '<option value="me">Saya (Super Admin)</option>', 'Hanya baca', 'Baca &amp; tulis',
            '<option value="365" selected>365 hari</option>', 'Buat token', 'Salin token sekarang', 'Sudah saya salin', 'Pilih role…',
            '"no_tokens":"Belum ada token."', '"revoke_title":"Cabut token ini?"'] as $part) {
            $this->assertStringContainsString($part, $page, $part);
        }
        foreach (['New token', 'New client', 'API documentation', 'Create token', 'Copy the token now', 'Read only', 'Choose a role'] as $english) {
            $this->assertStringNotContainsString($english, $page, $english);
        }

        $this->postJson(route('api-tokens.store-client'), ['name' => 'Power BI', 'role_id' => 1])->assertStatus(422)
            ->assertJsonPath('message', 'Input tidak valid')->assertJsonPath('errors', ['API client tidak boleh memegang Super Admin.']);
        $this->postJson(route('api-tokens.store-token'), ['owner' => 'me', 'access' => 'read', 'days' => 30])->assertStatus(422)
            ->assertJsonPath('errors', ['Kolom Nama wajib diisi.']);
        $this->postJson(route('api-tokens.store-token'), ['name' => 'Laporan', 'owner' => 'me', 'access' => 'read', 'days' => 30])
            ->assertCreated()->assertJsonPath('message', 'Token dibuat.');
    }

    public function test_the_notifications_page_is_in_indonesian_by_default_and_in_english_once_chosen(): void
    {
        $this->travelTo(Carbon::parse('2026-10-19 09:00:00'));
        $me = $this->makeUser('saya@test.local', 'Viewer Radio', 'Saya Sendiri');
        app(Notifier::class)->send($me, UserNotification::EDIT_REQUESTED, 'Halo', 'Isinya', '/dashboard');
        $this->travel(2)->minutes();
        $this->actingAs($me);

        $page = (string) $this->get(route('notifications.index'))->assertOk()->getContent();
        foreach (['<h1 class="rm-title">Notifikasi</h1>', 'Semua', 'Belum dibaca', '>Dokumen', 'Tandai semua dibaca', 'Tandai dibaca',
            'Hari ini', '<span class="rm-badge gray">Dokumen</span>', '2 menit yang lalu'] as $part) {
            $this->assertStringContainsString($part, $page, $part);
        }
        $this->getJson(route('notifications.summary'))->assertOk()->assertJsonPath('items.0.when', '2 menit yang lalu');

        // The browser chose English: the page, its script and what the bell is told.
        $this->useEnglish();
        $page = (string) $this->get(route('notifications.index'))->assertOk()->getContent();
        foreach (['<h1 class="rm-title">Notifications</h1>', 'Unread', '>Documents', 'Mark all as read', '>Mark as read</button>',
            'aria-label="Notifications shown"', 'Today', '<span class="rm-badge gray">Documents</span>', '2 minutes ago',
            '"marked":"All notifications marked as read."'] as $part) {
            $this->assertStringContainsString($part, $page, $part);
        }
        foreach (['belum dibaca', 'Hari ini', 'Tandai semua dibaca', 'Tandai dibaca', 'Dokumen', 'menit yang lalu', 'Pengajuan perubahan'] as $indonesian) {
            $this->assertStringNotContainsString($indonesian, $page, $indonesian);
        }
        $this->getJson(route('notifications.summary'))->assertOk()->assertJsonPath('items.0.when', '2 minutes ago');
    }
}
