<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckMenuPermission;
use App\Models\UserManagement\User;
use Database\Seeders\ESignMenuSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Modules\RadioManagement\Models\ItSignature;
use Modules\RadioManagement\Models\ItSignatureUse;
use Modules\RadioManagement\Models\Radio;
use Modules\RadioManagement\Models\RadioDepartment;
use Modules\RadioManagement\Models\RadioType;
use Tests\Concerns\MakesUsers;
use Tests\Concerns\RunsPageChecks;
use Tests\TestCase;

/**
 * "E-Sign Master": the signatures of the people at ICT, registered by whoever manages the page, and the box
 * "PROCESSED BY" of the IT Equipment Checkout Form, which shows the signature of the account that opens the form
 * and of nobody else.
 */
class ESignTest extends TestCase
{
    use MakesUsers;
    use RefreshDatabase;
    use RunsPageChecks;

    protected bool $seed = true;

    /** A signature as the pad sends it: dark strokes on nothing, as a data: address. $after: bytes put behind the end of the picture. */
    private static function drawn(int $width = 600, int $height = 240, bool $ink = true, string $after = ''): string
    {
        $picture = imagecreatetruecolor($width, $height);
        imagealphablending($picture, false);
        imagesavealpha($picture, true);
        imagefill($picture, 0, 0, imagecolorallocatealpha($picture, 0, 0, 0, 127));
        if ($ink) {
            imagesetthickness($picture, 4);
            $black = imagecolorallocatealpha($picture, 16, 16, 16, 0);
            imageline($picture, 20, $height - 40, intdiv($width, 2), 30, $black);
            imageline($picture, intdiv($width, 2), 30, $width - 20, $height - 30, $black);
        }
        ob_start();
        imagepng($picture);
        $png = (string) ob_get_clean();
        imagedestroy($picture);

        return 'data:image/png;base64,' . base64_encode($png . $after);
    }

    private function technician(string $email = 'teknisi.satu@test.local', string $name = 'Teknisi Satu'): User
    {
        return User::where('email', $email)->first() ?? $this->makeUser($email, 'Admin Radio', $name);
    }

    private function register(User $owner, array $other = []): TestResponse
    {
        return $this->postJson(route('radio-management.e-sign.store'), $other + ['user_id' => $owner->id, 'display_name' => '', 'image' => self::drawn()]);
    }

    private function radio(array $overrides = []): Radio
    {
        return Radio::create($overrides + [
            'dmr_id' => 11,
            'serial_number' => '867TZB5309',
            'user_name' => 'Budi Santoso',
            'radio_type_id' => RadioType::where('name', 'MotoTrbo XiR P6620i')->value('id'),
            'department_id' => RadioDepartment::firstOrCreate(['name' => 'MAINTENANCE'])->id,
        ]);
    }

    private function form(Radio $radio): string
    {
        return (string) $this->get(route('radio-management.radio.checkout-form', $radio))->assertOk()->getContent();
    }

    private static function flat(string $page): string
    {
        return (string) preg_replace('/\s+/', ' ', $page);
    }

    // ================================================================ the master

    public function test_the_page_is_in_the_group_of_masters_and_is_super_admins_alone(): void
    {
        $menu = DB::table('menu')->where('route', ESignMenuSeeder::ROUTE)->sole();
        $group = DB::table('menu')->where('id', $menu->parent_id)->sole();
        $this->assertSame(['E-Sign Master', 'Master Data Radio', 5], [$menu->title, $group->title, (int) $menu->order]);
        $this->assertSame([1], DB::table('menu_role')->where('menu_id', $menu->id)->pluck('role_id')->map(fn ($id) => (int) $id)->all());
        $this->assertSame('/radio-management/e-sign', route(ESignMenuSeeder::ROUTE, [], false));

        // Admin Radio manages the other masters, not this one: it holds other people's signatures.
        $this->actingAs($this->technician());
        $this->get(route('radio-management.e-sign.index'))->assertForbidden();
        $this->getJson(route('radio-management.e-sign.list'))->assertForbidden();
        $this->get(route('radio-management.e-sign.pad'))->assertForbidden();
        $this->register($this->technician())->assertForbidden();
        $this->assertSame(0, ItSignature::count());

        $this->actingAs($this->superAdmin());
        $this->get(route('radio-management.e-sign.index'))->assertOk()->assertSee('E-Sign Master')->assertSee('PROCESSED BY');
        $this->getJson(route('radio-management.e-sign.list'))->assertOk()->assertJsonPath('meta.total', 0);

        // Without its menu row the page is closed to everyone but Super Admin (other pages are open then: this one is not).
        $migration = require database_path('migrations/2026_10_12_130000_add_e_sign_menu.php');
        $migration->down();
        $this->assertFalse(DB::table('menu')->where('route', ESignMenuSeeder::ROUTE)->exists());
        $this->getJson(route('radio-management.e-sign.list'))->assertOk();
        $this->actingAs($this->technician());
        $this->getJson(route('radio-management.e-sign.list'))->assertForbidden();
        $this->register($this->technician())->assertForbidden();

        // Migrated again, it is back as it was; a second time changes nothing.
        $migration->up();
        $migration->up();
        $this->seed(ESignMenuSeeder::class);
        $this->assertSame(1, DB::table('menu')->where('route', ESignMenuSeeder::ROUTE)->count());
        $again = DB::table('menu')->where('route', ESignMenuSeeder::ROUTE)->sole();
        $this->assertSame([1], DB::table('menu_role')->where('menu_id', $again->id)->pluck('role_id')->map(fn ($id) => (int) $id)->all());
        $this->assertSame(
            array_values(CheckMenuPermission::PERMISSION_IDS),
            DB::table('menu_role_permission')->where('menu_id', $again->id)->orderBy('permission_id')->pluck('permission_id')->map(fn ($id) => (int) $id)->all()
        );
    }

    public function test_a_signature_is_registered_for_an_account_and_kept_encrypted(): void
    {
        $owner = $this->technician();
        $this->actingAs($this->superAdmin());
        $sent = self::drawn();

        $this->register($owner, ['image' => $sent])->assertOk()->assertJsonPath('message', 'Tanda tangan Teknisi Satu berhasil didaftarkan.');

        $signature = ItSignature::sole();
        // The name under the signature is the account's unless another is given; it is in use at once.
        $this->assertSame([$owner->id, 'Teknisi Satu', true, $this->superAdmin()->id], [$signature->user_id, $signature->display_name, $signature->active, (int) $signature->created_by]);
        // What is kept is a PNG, and in the table it is not readable: neither the picture nor the word "png".
        $this->assertStringStartsWith('data:image/png;base64,', $signature->image);
        $this->assertSame("\x89PNG", substr((string) base64_decode(substr($signature->image, 22)), 0, 4));
        $kept = (string) DB::table('it_signatures')->value('image');
        $this->assertStringNotContainsString('data:image', $kept);
        $this->assertStringNotContainsString(substr($signature->image, 22, 60), $kept);

        // The list shows it, with the accounts that have none yet (the owner is no longer one of them).
        $list = $this->getJson(route('radio-management.e-sign.list'))->assertOk();
        $list->assertJsonPath('data.0.account', 'Teknisi Satu')->assertJsonPath('data.0.email', 'teknisi.satu@test.local')
            ->assertJsonPath('data.0.display_name', 'Teknisi Satu')->assertJsonPath('data.0.active', true)
            ->assertJsonPath('data.0.uses', 0)->assertJsonPath('data.0.last_used', null)->assertJsonPath('data.0.image', $signature->image);
        $candidates = array_column($list->json('candidates'), 'email');
        $this->assertNotContains('teknisi.satu@test.local', $candidates);
        $this->assertContains('superadmin@test.local', $candidates);

        // One signature per account.
        $this->register($owner)->assertStatus(422)->assertJsonPath('errors.0', 'Akun itu sudah punya tanda tangan. Ganti lewat tombol Edit.');
        $this->assertSame(1, ItSignature::count());

        // Found by the name of the account, its e-mail or the name under the signature; what is typed is no pattern.
        $this->register($this->technician('teknisi.dua@test.local', 'Teknisi Dua'), ['display_name' => 'Pak [Dua]'])->assertOk();
        $this->getJson(route('radio-management.e-sign.list', ['search' => 'satu@test']))->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.account', 'Teknisi Satu');
        $this->getJson(route('radio-management.e-sign.list', ['search' => '[Dua]']))->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.display_name', 'Pak [Dua]');
        $this->getJson(route('radio-management.e-sign.list', ['search' => '%']))->assertJsonPath('meta.total', 0);
    }

    public function test_only_a_picture_of_a_signature_is_kept_and_only_its_pixels(): void
    {
        $owner = $this->technician();
        $this->actingAs($this->superAdmin());

        foreach ([
            'nothing' => '',
            'not a picture' => 'data:image/png;base64,' . base64_encode('<?php echo 1;'),
            'a picture of another kind' => 'data:image/jpeg;base64,' . base64_encode("\xFF\xD8\xFF\xE0"),
            'an address somewhere else' => 'https://example.com/signature.png',
            'not base64' => 'data:image/png;base64,***',
            'an empty pad' => self::drawn(600, 240, false),
            'too big' => self::drawn(2600, 400),
            'too small' => self::drawn(4, 4),
            'a script that calls itself a picture' => 'data:image/png;base64,' . base64_encode("\x89PNG\r\n\x1a\n<script>alert(1)</script>"),
        ] as $what => $image) {
            $this->register($owner, ['image' => $image])->assertStatus(422);
            $this->assertSame(0, ItSignature::count(), $what);
        }
        // An account that is not there, or was removed.
        $this->register($owner, ['user_id' => 999999])->assertStatus(422)->assertJsonPath('errors.0', 'Akun itu tidak ditemukan.');
        $gone = $this->makeUser('pergi@test.local', 'Admin Radio', 'Sudah Pergi');
        $gone->delete();
        $this->register($gone)->assertStatus(422);
        $this->assertSame(0, ItSignature::count());

        // What was put behind the end of the picture is not kept: the picture is drawn again from its pixels.
        $this->register($owner, ['image' => self::drawn(600, 240, true, '<script>alert("ikut")</script>')])->assertOk();
        $kept = (string) base64_decode(substr(ItSignature::sole()->image, 22));
        $this->assertStringNotContainsString('ikut', $kept);
        $this->assertSame([600, 240], array_slice((array) getimagesizefromstring($kept), 0, 2));
    }

    public function test_a_signature_is_renamed_switched_off_replaced_and_removed(): void
    {
        $owner = $this->technician();
        $this->actingAs($this->superAdmin());
        $this->register($owner)->assertOk();
        $signature = ItSignature::sole();
        $first = $signature->image;
        $update = fn (array $data) => $this->putJson(route('radio-management.e-sign.update', $signature->id), $data);

        // The name and the switch, without touching the picture.
        $update(['display_name' => '  Budi (ICT)  ', 'active' => 0])->assertOk();
        $signature->refresh();
        $this->assertSame(['Budi (ICT)', false, $first], [$signature->display_name, $signature->active, $signature->image]);
        // A new picture replaces the old one; one that is no signature leaves it.
        $update(['display_name' => 'Budi (ICT)', 'active' => 1, 'image' => self::drawn(800, 300)])->assertOk();
        $this->assertNotSame($first, $signature->refresh()->image);
        $replaced = $signature->image;
        $update(['display_name' => 'Budi (ICT)', 'active' => 1, 'image' => 'data:image/png;base64,AAAA'])->assertStatus(422);
        $update(['display_name' => '', 'active' => 1])->assertStatus(422);
        $this->assertSame([$replaced, 'Budi (ICT)'], [$signature->refresh()->image, $signature->display_name]);
        // The owner is not changed by an update.
        $update(['display_name' => 'Budi (ICT)', 'active' => 1, 'user_id' => $this->superAdmin()->id])->assertOk();
        $this->assertSame($owner->id, $signature->refresh()->user_id);

        // Removed: gone from the list, and the account can be given a new one. Where it was put stays on record.
        ItSignatureUse::create(['signature_id' => $signature->id, 'user_id' => $owner->id, 'user_name' => 'Teknisi Satu', 'radio_id' => null, 'radio_label' => 'ID 11', 'created_at' => now()]);
        $this->deleteJson(route('radio-management.e-sign.destroy', $signature->id))->assertOk();
        $this->assertSame([0, 1], [ItSignature::count(), ItSignatureUse::count()]);
        $this->deleteJson(route('radio-management.e-sign.destroy', $signature->id))->assertNotFound();
        $this->register($owner)->assertOk();
    }

    // ================================================================ the form

    public function test_the_form_carries_the_signature_of_whoever_opens_it_and_of_nobody_else(): void
    {
        $this->travelTo(Carbon::parse('2026-10-12 09:30:00', 'Asia/Makassar'));
        $owner = $this->technician();
        $other = $this->technician('teknisi.dua@test.local', 'Teknisi Dua');
        $this->actingAs($this->superAdmin());
        $this->register($owner, ['display_name' => 'Teknisi <Satu>'])->assertOk();
        $signature = ItSignature::sole();
        $radio = $this->radio();

        // Super Admin registered it, and does not get it on a form: it is not theirs.
        $page = self::flat($this->form($radio));
        $this->assertStringContainsString('<td class="sign" rowspan="3"></td>', $page);
        $this->assertStringNotContainsString('ict-sig-img"', $page);
        // Nor does a colleague.
        $this->actingAs($other);
        $this->assertStringNotContainsString('ictSignCell"', $this->form($radio));
        $this->assertSame(0, ItSignatureUse::count());

        // The owner does: the picture and the name are in the box "PROCESSED BY", and nothing on the form takes them off.
        $this->actingAs($owner);
        $page = $this->form($radio);
        $flat = self::flat($page);
        $this->assertStringContainsString(
            '<td class="sign" id="ictSignCell" rowspan="3"><img class="ict-sig-img" src="' . $signature->image . '" alt="Tanda tangan Teknisi &lt;Satu&gt;">'
            . '<span class="ict-sig-name">Teknisi &lt;Satu&gt;</span></td>',
            $flat
        );
        $this->assertStringNotContainsString('<td class="sign" rowspan="3"></td>', $flat);
        // The box comes after the one of the responsible user, in the row under "PROCESSED BY".
        $this->assertTrue(strpos($page, 'PROCESSED BY') < strpos($page, 'id="signCell"') && strpos($page, 'id="signCell"') < strpos($page, 'id="ictSignCell"'));

        // It is on record: whose, by whom, for which radio, when.
        $use = ItSignatureUse::sole();
        $this->assertSame(
            [$signature->id, $owner->id, 'Teknisi Satu', $radio->id, 'ID 11 · SN 867TZB5309 · Budi Santoso', '12-10-2026 09:30'],
            [(int) $use->signature_id, (int) $use->user_id, $use->user_name, (int) $use->radio_id, $use->radio_label, $use->created_at->format('d-m-Y H:i')]
        );
        $this->form($radio);
        $this->assertSame(2, ItSignatureUse::count());

        // The record is shown on the master, to who manages it.
        $this->getJson(route('radio-management.e-sign.uses', $signature->id))->assertForbidden();
        $this->actingAs($this->superAdmin());
        $this->getJson(route('radio-management.e-sign.uses', $signature->id))->assertOk()->assertJsonPath('total', 2)
            ->assertJsonPath('data.0', ['at' => '12-10-2026 09:30', 'user' => 'Teknisi Satu', 'radio' => 'ID 11 · SN 867TZB5309 · Budi Santoso']);
        $this->getJson(route('radio-management.e-sign.list'))->assertJsonPath('data.0.uses', 2)->assertJsonPath('data.0.last_used', '12-10-2026 09:30');

        // Switched off, it is not put on a form any more (and nothing is recorded).
        $this->putJson(route('radio-management.e-sign.update', $signature->id), ['display_name' => 'Teknisi Satu', 'active' => 0])->assertOk();
        $this->actingAs($owner);
        $this->assertStringContainsString('<td class="sign" rowspan="3"></td>', self::flat($this->form($radio)));
        $this->assertSame(2, ItSignatureUse::count());
    }

    public function test_the_signed_form_still_takes_nothing_from_outside_and_sends_nothing(): void
    {
        $owner = $this->technician();
        $this->actingAs($this->superAdmin());
        $this->register($owner)->assertOk();
        $this->actingAs($owner);
        $page = $this->form($this->radio());

        // The print pop-up asks before it closes on a signature that was drawn in the form and not printed; it finds that one by its
        // class. The signature from the master is not one of those: it is not lost when the pop-up closes.
        $this->assertStringNotContainsString('class="sig-img"', $page);
        // Every address in the markup is the logo's, or the picture of the signature inside the page itself.
        $markup = (string) preg_replace('#<style.*?</style>|<script.*?</script>#s', '', $page);
        $this->assertSame(0, preg_match('#<(form|iframe|object|embed|link|a)\b#i', $markup));
        $this->assertSame(2, preg_match_all('#\b(?:src|href|action|data|poster|srcset)="([^"]*)"#', $markup, $addresses));
        $this->assertStringEndsWith('/assets/img/branding/mti-logo.png', $addresses[1][0]);
        $this->assertStringStartsWith('data:image/png;base64,', $addresses[1][1]);
        // What is for the screen only: the five of the form. (No button takes the signature of ICT off.)
        $this->assertSame(5,preg_match_all('#class="[^"]*\bno-print\b[^"]*"#', $markup));
        // The scripts are the two the form has, and neither sends or keeps anything.
        $this->assertSame(2, preg_match_all('#<script\b([^>]*)>(.*?)</script\s*>#is', $page, $scripts, PREG_SET_ORDER));
        foreach ($scripts as [, $attributes, $body]) {
            $this->assertStringNotContainsString('src', $attributes);
            foreach (['fetch(', 'XMLHttpRequest', 'sendBeacon', '$.ajax', '$.post', 'localStorage', 'sessionStorage', 'document.cookie', 'window.parent', 'window.top', '.submit('] as $api) {
                $this->assertStringNotContainsString($api, $body, $api);
            }
        }
        // The picture and its name have a place in the box, and the picture leaves room for the name.
        $this->assertMatchesRegularExpression('/\.ict-sig-img \{ position: absolute; inset: 1mm 1mm 3\.4mm;[^}]*object-fit: contain; \}/', self::flat($page));
        $this->assertMatchesRegularExpression('/\.ict-sig-name \{ position: absolute;[^}]*white-space: nowrap;/', self::flat($page));
        // It says who processed the radio, and that is on record: the form has no way to take it off.
        $this->assertStringNotContainsString('ictSignRemove', $page);
        $this->assertStringNotContainsString('.ict-sig-img, .ict-sig-name', $page);
    }

    public function test_the_page_with_the_pad_is_the_pad_of_the_form_and_keeps_nothing(): void
    {
        $this->actingAs($this->superAdmin());
        $page = (string) $this->get(route('radio-management.e-sign.pad'))->assertOk()->getContent();
        $flat = self::flat($page);

        // The box that is signed in, the pad, and the sheet the pad switches off while it is open.
        foreach (['id="signCell"', 'id="signOpen"', 'id="signRemove"', 'id="signOverlay"', 'id="signCanvas"', 'id="signApply"', 'id="signCancel"'] as $part) {
            $this->assertSame(1, substr_count($page, $part), $part);
        }
        $this->assertSame(1, preg_match('#<main[^>]*\bclass="sheet"#', $page));
        // In its own words: it is the person at ICT who signs here.
        $this->assertStringContainsString('<h2 id="signTitle">Tanda tangan ICT Person</h2>', $flat);
        $this->assertStringContainsString('const WHO = "ICT Person";', $page);
        $this->assertStringNotContainsString('Responsible User', $page);
        // The same style as on the form: a pen draws instead of scrolling, and the picture lies in the box.
        $this->assertMatchesRegularExpression('/\.sig-surface canvas \{[^}]*touch-action: none/', $flat);
        $this->assertMatchesRegularExpression('/\.sig-img \{ position: absolute; inset: 1mm;/', $flat);
        // The second way to a signature: a file, chosen or dropped on the box, PNG or JPG only.
        $this->assertStringContainsString('<input type="file" id="signFile" accept="image/png,image/jpeg">', $flat);
        $this->assertStringContainsString('<label for="signFile">Unggah file tanda tangan</label>', $flat);
        $this->assertStringContainsString('<p class="upload-note" id="uploadNote" role="alert" hidden></p>', $flat);
        $this->assertStringContainsString('seret &amp; lepas file gambar tanda tangan ke sini', $page);
        // It sends nothing and keeps nothing (a file that is given is read in the page, and goes nowhere): the page around the frame
        // reads the picture out of the box.
        $this->assertSame(2, preg_match_all('#<script\b([^>]*)>(.*?)</script\s*>#is', $page, $scripts, PREG_SET_ORDER), 'the pad and the file');
        foreach ($scripts as [, $attributes, $body]) {
            $this->assertStringNotContainsString('src', $attributes);
            foreach (['fetch(', 'XMLHttpRequest', 'sendBeacon', '$.ajax', 'localStorage', 'sessionStorage', 'document.cookie', 'window.parent', 'window.top', 'postMessage', 'createObjectURL'] as $api) {
                $this->assertStringNotContainsString($api, $body, $api);
            }
        }
        $this->assertSame(0, preg_match('#<(form|iframe|object|embed|link|a|img)\b#i', (string) preg_replace('#<style.*?</style>|<script.*?</script>#s', '', $page)));
        // The form itself still says "Responsible User" on its pad.
        $this->assertStringContainsString('<h2 id="signTitle">Tanda tangan Responsible User</h2>', self::flat($this->form($this->radio())));
    }

    public function test_before_the_migration_has_run_the_form_is_printed_without_a_signature(): void
    {
        // The code of this folder is live as soon as it is saved; the migration is run by hand, later.
        Schema::drop('it_signature_uses');
        Schema::drop('it_signatures');
        Cache::forget('it-signatures-table');

        $this->actingAs($this->superAdmin());
        $this->assertStringContainsString('<td class="sign" rowspan="3"></td>', self::flat($this->form($this->radio())));
        $this->getJson(route('radio-management.e-sign.list'))->assertStatus(503)->assertJsonPath('message', fn ($message) => str_contains($message, 'php artisan migrate'));
        $this->register($this->technician())->assertStatus(503);
        $this->get(route('radio-management.e-sign.index'))->assertOk();
    }

    public function test_an_account_that_was_removed_no_longer_signs(): void
    {
        $owner = $this->technician();
        $this->actingAs($this->superAdmin());
        $this->register($owner)->assertOk();
        $owner->delete();

        // Its signature stays on the master, marked: it cannot sign in, so it cannot put it on a form.
        $this->getJson(route('radio-management.e-sign.list'))->assertOk()
            ->assertJsonPath('data.0.account', 'Teknisi Satu')->assertJsonPath('data.0.account_gone', true);
    }

    public function test_the_script_of_the_master_page_behaves_as_described(): void
    {
        $this->checkPageScript(route('radio-management.e-sign.index'), 'tests/tools/esign-index-behaviour.mjs', 8);
    }
}
