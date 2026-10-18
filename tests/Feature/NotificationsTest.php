<?php

namespace Tests\Feature;

use App\Models\Notification\UserNotification;
use App\Models\UserManagement\User;
use App\Services\Notifications\NotificationSweep;
use App\Services\Notifications\Notifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Modules\RadioManagement\Models\Radio;
use Tests\Concerns\MakesUsers;
use Tests\Concerns\UsesApi;
use Tests\TestCase;

/**
 * The bell in the header (modules/Notifications, App\Services\Notifications): that a person sees their own notifications
 * only, is told a thing once, and gets the notices that time brings (a token of the API that ends, the week of the
 * register). The notices of the requests to change a workbook are tested in SharePointEditTest.
 */
class NotificationsTest extends TestCase
{
    use MakesUsers;
    use RefreshDatabase;
    use UsesApi;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();

        // A Monday morning: ISO week 2026-W43.
        $this->travelTo(Carbon::parse('2026-10-19 09:00:00'));
    }

    private function person(string $name, string $role = 'Viewer Radio'): User
    {
        return $this->makeUser(strtolower(str_replace(' ', '.', $name)) . '@merdekabattery.com', $role, $name);
    }

    private function notify(User $user, string $title = 'Halo', ?string $url = '/dashboard', ?string $key = null): int
    {
        return app(Notifier::class)->send($user, UserNotification::EDIT_DECIDED, $title, 'Isinya', $url, $key);
    }

    private function sweep(User $user): void
    {
        app(NotificationSweep::class)->forUser($user, force: true);
    }

    /** @return string[] */
    private function titles(User $user, ?string $kind = null): array
    {
        return UserNotification::query()->of($user)->when($kind, fn ($query) => $query->where('kind', $kind))
            ->orderBy('id')->pluck('title')->all();
    }

    // ================================================================ the bell and its page

    public function test_the_bell_shows_the_own_notifications_newest_first_and_counts_the_unread(): void
    {
        $me = $this->person('Saya Sendiri');
        $other = $this->person('Orang Lain');
        foreach (range(1, 10) as $i) {
            $this->notify($me, "Ke-{$i}");
            $this->travel(1)->minutes();
        }
        $this->notify($other, 'Bukan untuk saya');
        UserNotification::query()->of($me)->where('title', 'Ke-1')->update(['read_at' => now()]);

        $this->actingAs($me);
        $bell = $this->getJson(route('notifications.summary'))->assertOk()
            ->assertJsonPath('ready', true)->assertJsonPath('unread', 9)->assertJsonCount(8, 'items')
            ->assertJsonPath('items.0.title', 'Ke-10')->assertJsonPath('items.0.body', 'Isinya')
            ->assertJsonPath('items.0.read', false)->assertJsonPath('items.0.icon', 'ti-file-check')
            ->assertJsonPath('items.0.when', '1 menit yang lalu')->assertJsonPath('items.7.title', 'Ke-3');
        $this->assertNotContains('Bukan untuk saya', $bell->json('items.*.title'));
        $this->assertSame(route('notifications.open', UserNotification::query()->of($me)->where('title', 'Ke-10')->value('id')), $bell->json('items.0.open'));
    }

    public function test_opening_one_marks_it_read_and_goes_where_it_points_on_this_site_only(): void
    {
        $me = $this->person('Saya Sendiri');
        $other = $this->person('Orang Lain');
        $this->notify($me, 'Ke halaman', '/radio-management/radio?scope=archived');
        $this->notify($me, 'Tanpa tautan', null);
        $this->notify($me, 'Situs lain', 'https://evil.example/x');
        $this->notify($other, 'Milik orang lain');
        $mine = fn (string $title) => UserNotification::query()->of($me)->where('title', $title)->sole();
        $this->actingAs($me);

        $this->get(route('notifications.open', $mine('Ke halaman')->id))->assertRedirect(url('/radio-management/radio?scope=archived'));
        $this->assertNotNull($mine('Ke halaman')->read_at);
        $this->get(route('notifications.open', $mine('Tanpa tautan')->id))->assertRedirect(route('notifications.index'));
        // Another site is never kept as a link, and one put there by other means never leads away from here.
        $this->assertNull($mine('Situs lain')->url);
        $mine('Situs lain')->forceFill(['url' => '//evil.example/x'])->save();
        $this->get(route('notifications.open', $mine('Situs lain')->id))->assertRedirect(route('notifications.index'));
        // Somebody else's: not found, and left as it was.
        $theirs = UserNotification::query()->of($other)->sole();
        $this->get(route('notifications.open', $theirs->id))->assertNotFound();
        $this->assertNull($theirs->fresh()->read_at);
    }

    public function test_all_are_marked_read_from_the_bell_and_from_the_page(): void
    {
        $me = $this->person('Saya Sendiri');
        $other = $this->person('Orang Lain');
        $this->notify($me, 'Satu');
        $this->notify($me, 'Dua');
        $this->notify($other, 'Tiga');
        $this->actingAs($me);

        $this->postJson(route('notifications.read-all'))->assertOk()->assertExactJson(['unread' => 0]);
        $this->assertSame(0, UserNotification::query()->of($me)->unread()->count());
        $this->assertSame(1, UserNotification::query()->of($other)->unread()->count());

        $this->notify($me, 'Empat');
        $this->post(route('notifications.read-all'), ['show' => 'unread'])->assertRedirect(route('notifications.index', ['show' => 'unread']));
        $this->assertSame(0, UserNotification::query()->of($me)->unread()->count());
    }

    public function test_the_page_lists_them_and_the_unread_ones_as_text(): void
    {
        $me = $this->person('Saya Sendiri');
        $this->notify($me, 'Sudah dibaca');
        UserNotification::query()->of($me)->update(['read_at' => now()]);
        $this->notify($me, 'Belum dibaca');
        $this->notify($me, '<script>alert(1)</script>');
        $this->actingAs($me);

        // (The bell of the header says "Tandai semua dibaca" on every page: the page's own button is looked for by its id.)
        $this->get(route('notifications.index'))->assertOk()
            ->assertSee('Sudah dibaca')->assertSee('Belum dibaca')->assertSee('id="notifPageReadAll"', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $this->get(route('notifications.index', ['show' => 'unread']))->assertOk()
            ->assertDontSee('Sudah dibaca')->assertSee('Belum dibaca');

        UserNotification::query()->of($me)->update(['read_at' => now()]);
        $this->get(route('notifications.index', ['show' => 'unread']))->assertOk()
            ->assertSee('Semua notifikasi sudah dibaca')->assertDontSee('id="notifPageReadAll"', false);
    }

    public function test_every_page_has_the_bell(): void
    {
        $this->actingAs($this->superAdmin());
        $page = (string) $this->get(route('dashboard.index'))->assertOk()->getContent();

        $this->assertStringContainsString('id="notifBell"', $page);
        $this->assertStringContainsString(route('notifications.summary'), $page);
        $this->assertStringContainsString(route('notifications.index'), $page);
        // Not the empty bell of the template any more.
        $this->assertStringNotContainsString('No notifications available', $page);
    }

    public function test_a_notification_is_given_once_per_key_and_only_to_active_people(): void
    {
        $me = $this->person('Saya Sendiri');
        $this->assertSame(1, $this->notify($me, 'Sekali', key: 'test:1'));
        $this->assertSame(0, $this->notify($me, 'Sekali lagi', key: 'test:1'));
        $this->assertSame(1, $this->notify($me, 'Tanpa kunci'));
        $this->assertSame(1, $this->notify($me, 'Tanpa kunci'));
        $this->assertSame(['Sekali', 'Tanpa kunci', 'Tanpa kunci'], $this->titles($me));

        $inactive = $this->person('Sudah Keluar');
        $inactive->forceFill(['active' => '0'])->save();
        $client = $this->apiClient('Aplikasi Gudang', 'Viewer Radio');
        $this->assertSame(0, app(Notifier::class)->send([$inactive, $client], UserNotification::EDIT_DECIDED, 'Tidak sampai'));
        $this->assertSame(0, UserNotification::query()->whereIn('user_id', [$inactive->id, $client->id])->count());
    }

    public function test_before_the_migration_the_bell_is_empty_and_nothing_fails(): void
    {
        $me = $this->person('Saya Sendiri');
        Schema::drop('user_notifications');
        Cache::forget(UserNotification::READY_CACHE_KEY);

        $this->assertSame(0, $this->notify($me));
        $this->actingAs($me);
        $this->getJson(route('notifications.summary'))->assertOk()->assertExactJson(['ready' => false, 'unread' => 0, 'items' => []]);
        $this->get(route('notifications.index'))->assertOk()->assertSee('php artisan migrate');
        $this->postJson(route('notifications.read-all'))->assertOk();
        $this->get(route('notifications.open', 1))->assertNotFound();
        $this->get(route('dashboard.index'))->assertOk()->assertSee('id="notifBell"', false);
    }

    // ================================================================ what time brings

    public function test_a_token_that_ends_within_a_week_is_told_to_its_owner_once(): void
    {
        $me = $this->person('Pemilik Token');
        $soon = $me->createToken('Laporan Mingguan', ['read'], now()->addDays(3))->accessToken;
        $me->createToken('Masih lama', ['read'], now()->addDays(10));
        $me->createToken('Sudah berakhir', ['read'], now()->subDay());

        $this->sweep($me);
        $this->sweep($me);
        $notice = UserNotification::query()->of($me)->sole();
        $this->assertSame([UserNotification::TOKEN_EXPIRING, 'Token API Anda akan berakhir'], [$notice->kind, $notice->title]);
        $this->assertSame('Token "Laporan Mingguan" berakhir dalam 3 hari (22-10-2026). Bila masih dipakai, buat token baru di API Tokens.', $notice->body);
        $this->assertSame([route('api-tokens.index', [], false), 'token-expiring:' . $soon->id], [$notice->url, $notice->dedupe_key]);
    }

    public function test_the_tokens_of_the_api_clients_are_told_to_super_admin(): void
    {
        $super = $this->superAdmin();
        $admin = $this->person('Admin Radio Satu', 'Admin Radio');
        $client = $this->apiClient('Sistem Gudang', 'Viewer Radio');
        $client->createToken('Gudang', ['read'], now()->addDay());

        $this->sweep($super);
        $this->sweep($admin);
        $notice = UserNotification::query()->of($super)->where('kind', UserNotification::TOKEN_EXPIRING)->sole();
        $this->assertSame('Token API client akan berakhir', $notice->title);
        $this->assertStringStartsWith('Token "Gudang" milik API client Sistem Gudang berakhir besok (20-10-2026).', $notice->body);
        $this->assertSame([], $this->titles($admin, UserNotification::TOKEN_EXPIRING));
        $this->assertSame([], $this->titles($client));
    }

    public function test_who_may_change_the_register_gets_its_summary_once_a_week(): void
    {
        Radio::create(['dmr_id' => 20, 'serial_number' => null, 'user_name' => 'Tanpa SN']);
        $admin = $this->person('Admin Radio Satu', 'Admin Radio');
        $viewer = $this->person('Viewer Satu');

        $this->sweep($admin);
        $this->sweep($viewer);
        $notice = UserNotification::query()->of($admin)->where('kind', UserNotification::REGISTER_WEEKLY)->sole();
        $this->assertSame('Ringkasan register radio minggu ini', $notice->title);
        $this->assertStringStartsWith('Perlu tindakan: ', $notice->body);
        $this->assertStringContainsString('SN kosong 1', $notice->body);
        $this->assertSame([route('dashboard.index', [], false), 'register-weekly:2026-W43'], [$notice->url, $notice->dedupe_key]);
        // Who may only look at the register is not told.
        $this->assertSame([], $this->titles($viewer, UserNotification::REGISTER_WEEKLY));

        // The same week: nothing new. The next week: the summary again.
        $this->sweep($admin);
        $this->assertCount(1, $this->titles($admin, UserNotification::REGISTER_WEEKLY));
        $this->travel(7)->days();
        $this->sweep($admin);
        $this->assertCount(2, $this->titles($admin, UserNotification::REGISTER_WEEKLY));

        // Nothing to be done: nothing is said.
        Radio::query()->forceDelete();
        $this->travel(7)->days();
        $this->sweep($admin);
        $this->assertCount(2, $this->titles($admin, UserNotification::REGISTER_WEEKLY));
    }

    public function test_the_bell_makes_the_due_notices_at_most_once_an_hour(): void
    {
        $me = $this->person('Saya Sendiri');
        $this->actingAs($me);
        $this->getJson(route('notifications.summary'))->assertJsonPath('unread', 0);

        $me->createToken('Baru', ['read'], now()->addDays(2));
        $this->getJson(route('notifications.summary'))->assertJsonPath('unread', 0);
        $this->travel(61)->minutes();
        $this->getJson(route('notifications.summary'))->assertJsonPath('unread', 1)
            ->assertJsonPath('items.0.title', 'Token API Anda akan berakhir')->assertJsonPath('items.0.icon', 'ti-key');
    }

    public function test_read_notifications_go_after_90_days_and_unread_ones_stay(): void
    {
        $me = $this->person('Saya Sendiri');
        $this->notify($me, 'Lama, dibaca');
        $this->notify($me, 'Lama, belum dibaca');
        UserNotification::query()->of($me)->where('title', 'Lama, dibaca')->update(['read_at' => now()]);
        $this->travel(91)->days();
        $this->notify($me, 'Baru, dibaca');
        UserNotification::query()->of($me)->where('title', 'Baru, dibaca')->update(['read_at' => now()]);

        $this->sweep($me);
        $this->assertSame(['Lama, belum dibaca', 'Baru, dibaca'], $this->titles($me));
    }
}
