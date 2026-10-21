<?php

namespace Tests\Feature;

use App\Models\Notification\UserNotification;
use App\Models\UserManagement\Menu;
use App\Models\UserManagement\User;
use App\Services\Language;
use App\Services\Notifications\Notifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\MakesUsers;
use Tests\TestCase;

/**
 * The language of the screens (App\Services\Language, SetLocale, LanguageController): Indonesian until another is
 * chosen, the choice kept with the account and in the browser, the switch in the account menu and on the sign-in page.
 */
class LanguageTest extends TestCase
{
    use MakesUsers;
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_a_page_is_in_indonesian_until_another_language_is_chosen(): void
    {
        $page = (string) $this->actingAs($this->superAdmin())->get(route('dashboard.index'))->assertOk()->getContent();

        $this->assertStringContainsString('<html lang="id">', $page);
        $this->assertStringContainsString('<li class="rm-nav-section" role="presentation"><span>Pengaturan</span></li>', $page);
        $this->assertStringContainsString('<i class="ti ti-logout" aria-hidden="true"></i> Keluar', $page);
        // The switch in the account menu: each language by its own name, the one in use ticked.
        $this->assertStringContainsString('<form method="POST" action="' . route('language.switch') . '" class="rm-menu-group" id="languageMenu">', $page);
        $this->assertMatchesRegularExpression('#value="id" lang="id"\s+class="dropdown-item rm-menu-item is-on"\s+aria-describedby="languageMenuLabel" aria-pressed="true">\s*<i class="ti ti-check rm-menu-tick" aria-hidden="true"></i> Bahasa Indonesia#', $page);
        $this->assertMatchesRegularExpression('#value="en" lang="en"\s+class="dropdown-item rm-menu-item"\s+aria-describedby="languageMenuLabel" aria-pressed="false">\s*<i class="ti ti-check rm-menu-tick" aria-hidden="true"></i> English#', $page);
        // On a phone the sidebar offers the other one.
        $this->assertMatchesRegularExpression('#<button type="submit" name="locale" value="en" class="rm-nav-link" lang="en"\s+aria-label="Bahasa: English">#', $page);
    }

    public function test_the_switch_keeps_the_choice_with_the_account_and_in_the_browser(): void
    {
        $user = $this->superAdmin();
        $before = DB::table('users')->where('id', $user->id)->value('updated_at');

        $this->actingAs($user)->from(route('dashboard.index'))
            ->post(route('language.switch'), ['locale' => 'en'])
            ->assertRedirect(route('dashboard.index'))
            ->assertPlainCookie(Language::COOKIE, 'en');
        // Written past the model: the account is not counted as changed.
        $this->assertSame(['en', $before], [DB::table('users')->where('id', $user->id)->value('locale'), DB::table('users')->where('id', $user->id)->value('updated_at')]);

        $page = (string) $this->actingAs($user->fresh())->get(route('dashboard.index'))->assertOk()->getContent();
        $this->assertStringContainsString('<html lang="en">', $page);
        $this->assertStringContainsString('<span>Settings</span>', $page);
        $this->assertStringContainsString('<i class="ti ti-logout" aria-hidden="true"></i> Sign out', $page);
        $this->assertMatchesRegularExpression('#value="en" lang="en"\s+class="dropdown-item rm-menu-item is-on"#', $page);
    }

    public function test_the_choice_of_the_account_wins_over_the_browser_and_the_browser_learns_it(): void
    {
        $user = $this->superAdmin();
        DB::table('users')->where('id', $user->id)->update(['locale' => 'en']);

        $response = $this->actingAs($user->fresh())->withUnencryptedCookie(Language::COOKIE, 'id')->get(route('dashboard.index'))->assertOk();

        $this->assertStringContainsString('<html lang="en">', (string) $response->getContent());
        $response->assertPlainCookie(Language::COOKIE, 'en');
    }

    public function test_the_sign_in_page_goes_by_the_browser_and_has_the_switch(): void
    {
        $page = (string) $this->get(route('login'))->assertOk()->getContent();
        $this->assertStringContainsString('<html lang="id">', $page);
        $this->assertStringContainsString('Masuk dengan SSO', $page);
        $this->assertStringContainsString('<form method="POST" action="' . route('language.switch') . '" class="rm-login-lang" aria-label="Bahasa">', $page);
        $this->assertMatchesRegularExpression('#value="id" lang="id" title="Bahasa Indonesia"\s+aria-label="Bahasa Indonesia" aria-pressed="true">ID</button>#', $page);

        // A guest chooses: kept by the browser.
        $this->from(route('login'))->post(route('language.switch'), ['locale' => 'en'])
            ->assertRedirect(route('login'))->assertPlainCookie(Language::COOKIE, 'en');

        $english = (string) $this->withUnencryptedCookie(Language::COOKIE, 'en')->get(route('login'))->assertOk()->getContent();
        $this->assertStringContainsString('<html lang="en">', $english);
        $this->assertStringContainsString('Sign in with SSO', $english);
        $this->assertMatchesRegularExpression('#value="en" lang="en" title="English"\s+aria-label="English" aria-pressed="true">EN</button>#', $english);
    }

    public function test_only_a_language_of_the_screens_is_taken(): void
    {
        $user = $this->superAdmin();

        $this->actingAs($user)->post(route('language.switch'), ['locale' => 'fr'])->assertStatus(422);
        $this->actingAs($user)->post(route('language.switch'))->assertStatus(422);

        $this->assertNull(DB::table('users')->where('id', $user->id)->value('locale'));
    }

    public function test_before_its_column_is_there_the_browser_alone_keeps_the_choice(): void
    {
        // As before the migration: the column is not looked for again within the minute.
        Cache::put(User::LOCALE_CACHE_KEY, false);
        $user = $this->superAdmin();

        $this->actingAs($user)->post(route('language.switch'), ['locale' => 'en'])->assertPlainCookie(Language::COOKIE, 'en');

        $this->assertNull(DB::table('users')->where('id', $user->id)->value('locale'));
    }

    public function test_it_goes_back_only_to_a_page_of_this_application(): void
    {
        $this->from('https://elsewhere.example/page')->post(route('language.switch'), ['locale' => 'en'])->assertRedirect(url('/'));
    }

    public function test_an_address_that_is_no_page_goes_by_the_browser(): void
    {
        $this->get('/no-such-page-at-all')->assertNotFound()->assertSee('Halaman tidak ditemukan');
        $this->withUnencryptedCookie(Language::COOKIE, 'en')->get('/no-such-page-at-all')->assertNotFound()->assertSee('Page not found');
    }

    public function test_the_api_speaks_english_unless_a_client_asks_for_indonesian(): void
    {
        $request = fn (?string $accept) => Request::create('/api/v1/me', 'GET', server: $accept === null ? [] : ['HTTP_ACCEPT_LANGUAGE' => $accept]);

        $this->assertSame('en', Language::forApi($request(null)));
        $this->assertSame('id', Language::forApi($request('id-ID,id;q=0.9,en;q=0.8')));
        $this->assertSame('en', Language::forApi($request('fr-FR,en;q=0.5,id;q=0.4')));
        $this->assertSame('en', Language::forApi($request('fr-FR')));
        // First of all on every route of the API, whatever the cookie of a browser says.
        foreach (Route::getRoutes() as $route) {
            if (str_starts_with((string) $route->getName(), 'api.v1.')) {
                $this->assertSame('App\Http\Middleware\SetLocale:api', $route->gatherMiddleware()[0], $route->getName());
            }
        }
    }

    public function test_a_notification_is_written_in_the_language_of_whoever_gets_it(): void
    {
        $english = $this->makeUser('english@test.local', 'Admin Radio');
        DB::table('users')->where('id', $english->id)->update(['locale' => 'en']);
        $indonesian = $this->makeUser('indonesian@test.local', 'Admin Radio');

        app(Notifier::class)->send([$english->fresh(), $indonesian], UserNotification::REGISTER_WEEKLY,
            fn (string $locale) => __('notifications.weekly_title', [], $locale),
            fn (string $locale) => trans_choice('notifications.weekly_more', 3, ['count' => 3], $locale));

        $this->assertSame(["This week's radio register summary", 'and 3 more checks'],
            array_values((array) UserNotification::query()->where('user_id', $english->id)->first(['title', 'body'])->only(['title', 'body'])));
        $this->assertSame(['Ringkasan register radio minggu ini', 'dan 3 cek lain'],
            array_values((array) UserNotification::query()->where('user_id', $indonesian->id)->first(['title', 'body'])->only(['title', 'body'])));
    }

    public function test_every_entry_of_the_sidebar_has_a_name_in_both_languages(): void
    {
        foreach (array_keys(Language::NAMES) as $code) {
            app()->setLocale($code);
            $routes = __('menu.routes');
            $groups = __('menu.groups');
            $sections = __('menu.sections');
            foreach (Menu::query()->get() as $menu) {
                if ($menu->route !== null && Route::has($menu->route)) {
                    $this->assertArrayHasKey($menu->route, $routes, "{$code}: {$menu->title}");
                } elseif ($menu->route === null && Menu::query()->where('parent_id', $menu->id)->exists()) {
                    $this->assertArrayHasKey($menu->title, $groups, "{$code}: {$menu->title}");
                }
                if ($menu->section !== null) {
                    $this->assertArrayHasKey($menu->section, $sections, "{$code}: {$menu->section}");
                }
            }
        }
    }
}
