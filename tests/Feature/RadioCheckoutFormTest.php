<?php

namespace Tests\Feature;

use Illuminate\Container\Container;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Mockery;
use Modules\RadioManagement\Models\Radio;
use Modules\RadioManagement\Models\RadioDepartment;
use Modules\RadioManagement\Models\RadioType;
use ReflectionProperty;
use Tests\Concerns\MakesUsers;
use Tests\Concerns\RunsPageChecks;
use Tests\TestCase;

/**
 * The "Cetak" button of the register: the IT Equipment Checkout Form (MTI-HRM-ICT-FRM-001) of one radio,
 * filled in from the register, shown in a pop-up, signed on the screen if wanted, and printed from there.
 */
class RadioCheckoutFormTest extends TestCase
{
    use MakesUsers;
    use RefreshDatabase;
    use RunsPageChecks;

    protected bool $seed = true;

    /** What the register fills in; every other field of the form is left empty. */
    private const FILLED = ['ict_date', 'radio_id', 'radio_serial', 'radio_type', 'requestor_department', 'requestor_name', 'user_department', 'user_name'];

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

    private function form(Radio $radio)
    {
        return $this->get(route('radio-management.radio.checkout-form', $radio));
    }

    /**
     * Every field of the form: its name and what stands between its tags (as it is in the page: escaped).
     *
     * @return array<string, string>
     */
    private static function fields(string $page): array
    {
        preg_match_all('#<span class="f(?: [^"]*)?" data-field="([a-z_]+)">(.*?)</span>#s', $page, $found, PREG_SET_ORDER);

        return array_column($found, 2, 1);
    }

    private static function field(string $page, string $name): string
    {
        return self::fields($page)[$name] ?? '(no such field)';
    }

    /** The page with its white space made one space, for what is looked for in the style sheet and the markup. */
    private static function flat(string $page): string
    {
        return (string) preg_replace('/\s+/', ' ', $page);
    }

    /** The style sheet of the form (it has one): without its comments, and with its white space made one space. */
    private static function css(string $page): string
    {
        preg_match('#<style>(.*?)</style>#s', $page, $style);

        return trim(self::flat((string) preg_replace('#/\*.*?\*/#s', '', $style[1] ?? '')));
    }

    public function test_the_form_is_the_it_equipment_checkout_form_filled_in_from_the_register(): void
    {
        $this->actingAs($this->superAdmin());
        $this->travelTo(Carbon::parse('2026-10-08 10:00:00', 'Asia/Makassar'));
        $page = $this->form($this->radio())->assertOk()->getContent();

        // The form as it is on paper: its heading, number and revision, every block, and the line at the foot.
        foreach (['MERDEKA TSINGSHAN INDONESIA', 'HR/ICT', 'IT EQUIPMENT CHECKOUT FORM', 'MTI-HRM-ICT-FRM-001', 'Rev.000', 'Reg Number:', '(Filled by ICT)'] as $text) {
            $this->assertStringContainsString($text, $page);
        }
        $this->assertSame(1, preg_match('#<img src="[^"]*assets/img/branding/mti-logo\.png"#', $page));
        $this->assertFileExists(public_path('assets/img/branding/mti-logo.png'));
        $this->assertSeeInOrder($page, [
            'REQUESTOR (filled by ICT)', 'Employee Name', 'Position', 'Employee ID', 'Department', 'Reg. No. FO-001',
            'DESKTOP', 'LAPTOP', 'HT', 'RIG', 'Device Name', 'No. ID', 'Merk &amp; Type', 'Processor', 'Serial Number', 'Service Tag',
            'MOUSE', 'KEYBOARD', 'HANDPHONE', 'MONITOR', 'Other:', '*) Streak one', 'Note :',
            'RESPONSIBLE USER', 'PROCESSED BY', '(ICT Person)', 'Date:', 'Date:',
            'The equipment belongs to the company.', 'All forms of equipment’s loss and damage are user’s responsibility.',
        ]);
        // The print pop-up lets the form be printed only when it finds the sheet in the frame: the form must have it.
        $this->assertSame(1, preg_match('#<main[^>]*\bclass="sheet"#', $page), 'the pop-up looks for main.sheet');

        // What the register knows: the radio goes into the block of HT / RIG, its user into both name blocks.
        $this->assertSame('11', self::field($page, 'radio_id'));
        $this->assertSame('MotoTrbo XiR P6620i', self::field($page, 'radio_type'));
        $this->assertSame('867TZB5309', self::field($page, 'radio_serial'));
        foreach (['requestor_name', 'user_name'] as $name) {
            $this->assertSame('Budi Santoso', self::field($page, $name), $name);
        }
        foreach (['requestor_department', 'user_department'] as $name) {
            $this->assertSame('MAINTENANCE', self::field($page, $name), $name);
        }
        // The date of the person at ICT is the day it is printed; everything else is for ICT, or the hand, to fill in.
        $this->assertSame('08-10-2026', self::field($page, 'ict_date'));
        $fields = self::fields($page);
        $this->assertCount(29, $fields, 'the fields of the form');
        $filled = array_keys(array_filter($fields, fn (string $text) => $text !== ''));
        sort($filled);
        $this->assertSame(self::FILLED, $filled, 'only what the register knows is filled in');
    }

    public function test_a_radio_without_what_the_form_asks_for_leaves_it_empty(): void
    {
        $this->actingAs($this->superAdmin());
        $radio = Radio::create(['user_name' => null, 'serial_number' => null]);

        $page = $this->form($radio)->assertOk()->getContent();

        foreach (['radio_id', 'radio_type', 'radio_serial', 'requestor_name', 'requestor_department', 'user_name', 'user_department'] as $name) {
            $this->assertSame('', self::field($page, $name), $name);
        }
        // Without a type nothing is said about HT or RIG: neither is struck out.
        $this->assertDoesNotMatchRegularExpression('#class="alt\s+struck#', $page);
    }

    public function test_the_alternative_the_radio_is_not_is_struck_out(): void
    {
        $this->actingAs($this->superAdmin());
        $struck = fn (string $page) => collect(preg_match_all('#<span class="alt\s+struck\s*">([^<]+)</span>#', $page, $m) ? $m[1] : [])->all();

        $this->assertSame(['RIG'], $struck($this->form($this->radio())->getContent()), 'a handheld is not a rig');
        $rig = $this->radio(['dmr_id' => 12, 'serial_number' => '866TEST0003', 'radio_type_id' => RadioType::where('name', 'MotoTrbo XiR M3688')->value('id')]);
        $this->assertSame(['HT'], $struck($this->form($rig)->getContent()), 'a rig is not a handheld');
    }

    public function test_what_the_register_holds_is_written_as_text(): void
    {
        $this->actingAs($this->superAdmin());
        // Master data is typed in by whoever may edit it: the type and the department are as little to be trusted as the user.
        $type = RadioType::create(['name' => '<i>Type</i> & "X"', 'category' => 'HT', 'is_active' => true]);
        $department = RadioDepartment::create(['name' => '<u>IT</u> "Ops" & Co', 'is_active' => true]);
        $radio = $this->radio([
            'user_name' => '<script>alert(1)</script> "Budi" & Sons', 'serial_number' => '<img src=x onerror=alert(2)>',
            'radio_type_id' => $type->id, 'department_id' => $department->id,
        ]);

        $page = $this->form($radio)->assertOk()->getContent();

        $this->assertStringNotContainsString('<script>alert(1)', $page);
        $this->assertStringNotContainsString('<img src=x', $page);
        $this->assertStringNotContainsString('<i>Type</i>', $page);
        $this->assertStringNotContainsString('<u>IT</u>', $page);
        $this->assertSame('&lt;script&gt;alert(1)&lt;/script&gt; &quot;Budi&quot; &amp; Sons', self::field($page, 'user_name'));
        $this->assertSame('&lt;script&gt;alert(1)&lt;/script&gt; &quot;Budi&quot; &amp; Sons', self::field($page, 'requestor_name'));
        $this->assertSame('&lt;img src=x onerror=alert(2)&gt;', self::field($page, 'radio_serial'));
        $this->assertSame('&lt;i&gt;Type&lt;/i&gt; &amp; &quot;X&quot;', self::field($page, 'radio_type'));
        foreach (['requestor_department', 'user_department'] as $name) {
            $this->assertSame('&lt;u&gt;IT&lt;/u&gt; &quot;Ops&quot; &amp; Co', self::field($page, $name), $name);
        }
    }

    public function test_the_form_opens_for_whoever_may_read_the_register(): void
    {
        $radio = $this->radio();
        $url = route('radio-management.radio.checkout-form', $radio);

        $this->get($url)->assertRedirect(route('login'));
        // Read on "Register Radio" is enough, as for the detail of the radio ...
        $this->actingAs($this->makeUser('viewer.radio@test.local', 'Viewer Radio'))->get($url)->assertOk();
        // ... and nobody without the menu gets it.
        $this->actingAs($this->makeUser('no.role@test.local'))->get($url)->assertForbidden();
        $this->actingAs($this->makeUser('admin.radio@test.local', 'Admin Radio'))->get($url)->assertOk();

        $this->actingAs($this->superAdmin());
        // A radio that was taken out of service is still a radio that was handed out: its form can be printed. Its Radio ID
        // was released, so the box for it stays empty rather than carry an ID that another radio may be given.
        $archived = $this->radio(['dmr_id' => null, 'previous_dmr_id' => 11, 'serial_number' => '867TEST0001', 'archived_at' => now()]);
        $page = $this->form($archived)->assertOk()->getContent();
        $this->assertSame(['', '867TEST0001', 'Budi Santoso'], [self::field($page, 'radio_id'), self::field($page, 'radio_serial'), self::field($page, 'user_name')]);
        // One that does not exist, or was deleted, has none.
        $this->get(route('radio-management.radio.checkout-form', 999999))->assertNotFound();
        $gone = $this->radio(['dmr_id' => 13, 'serial_number' => '867TEST0002']);
        $gone->delete();
        $this->form($gone)->assertNotFound();
        // An id that is no id of a radio (more digits than the column holds: SQL Server answers such a number with an error) is not looked up at all.
        foreach ([16, 20, 40] as $digits) {
            $this->get('/radio-management/radio/' . str_repeat('9', $digits) . '/checkout-form')->assertNotFound();
        }
        $this->get('/radio-management/radio/abc/checkout-form')->assertNotFound();
        $this->post($url)->assertStatus(405);
    }

    public function test_the_register_offers_the_button_and_the_pop_up(): void
    {
        $this->actingAs($this->superAdmin());
        $radio = $this->radio();

        // Every row of the list carries the address of its form ...
        $this->getJson(route('radio-management.radio.list'))->assertOk()
            ->assertJsonPath('data.0.print_url', route('radio-management.radio.checkout-form', $radio));

        // ... and the page has the button in the column of actions, the pop-up, and what ties them together.
        $page = $this->get(route('radio-management.radio.index'))->assertOk()->getContent();
        $this->assertSeeInOrder($page, [
            'data-bs-title="Detail & Riwayat"', 'data-action="print"', 'ti ti-printer', 'data-bs-title="Edit"',
        ]);
        $flat = self::flat($page);
        // The button names itself for those who do not see its tooltip, and hands the address and the label to the pop-up.
        $this->assertStringContainsString('aria-label="Cetak form" data-action="print" data-url="${RadioUi.esc(r.print_url)}" data-label="${label}"', $flat);
        $this->assertMatchesRegularExpression('/case \'print\': radioPrint\.open\(button\.data\(\'url\'\), button\.data\(\'label\'\)\); break;/', $flat);
        foreach (['id="radioPrintModal"', 'id="radioPrintBody"', 'id="radioPrintButton"', 'radioPrint.print()'] as $text) {
            $this->assertStringContainsString($text, $page);
        }
        // What is typed and signed in the form is lost when the pop-up closes: it does not close by a click beside it or by Esc.
        $this->assertStringContainsString('<div class="modal fade rm-print" id="radioPrintModal" tabindex="-1" aria-hidden="true" aria-labelledby="radioPrintTitle" data-bs-backdrop="static" data-bs-keyboard="false">', $flat);
        // The frame is made for every form (a frame whose address changes fills the Back button's history).
        $this->assertStringNotContainsString('<iframe', $page);
    }

    public function test_the_form_is_laid_out_for_one_a4_page_and_leaves_the_screen_off_the_paper(): void
    {
        $this->actingAs($this->superAdmin());
        $page = $this->form($this->radio())->assertOk()->getContent();
        $flat = self::flat($page);
        $css = self::css($page);

        // A4 without a margin of the page's own: a margin is where browsers print the title and the address of the page, and the
        // company's form is to carry neither.
        $this->assertStringContainsString('@page { size: A4; margin: 0; }', $css);
        // On paper: no grey around the sheet; the 12 mm above and below are the body's padding (and the sides are the dialog's to
        // leave); the sheet is 160 mm wide, in the middle, without its shadow; what is for the screen (the hint, the warning, the pad
        // and the buttons of the box) is not there; and rows stay whole.
        $this->assertStringContainsString(
            '@media print { html, body { background: #fff; } body { padding: 12mm 0; } .hint, .no-print { display: none !important; } '
            . '.sheet { width: 160mm; margin: 0 auto; box-shadow: none; } tr { break-inside: avoid; } }',
            $css
        );
        $markup = (string) preg_replace('#<style.*?</style>|<script.*?</script>#s', '', $page);
        $this->assertSame(5, preg_match_all('#class="[^"]*\bno-print\b[^"]*"#', $markup), 'the hint, the warning, the pad and the two buttons of the box');
        // HT or RIG that the radio is not is struck out on paper too.
        $this->assertStringContainsString('.alt.struck { text-decoration: line-through; }', $css);
        // A form that has grown past a page says so with the size it is printed at: in view whatever the page is scrolled to, and in
        // no one's way, so it is out of the flow of the page.
        $this->assertStringContainsString('id="fitWarning" role="alert" hidden', $flat);
        $this->assertSame(1, substr_count($page, 'id="fitScale"'));
        $this->assertStringContainsString('.hint.warn { position: fixed;', $css);
        // Ctrl+P prints the form itself.
        $this->assertStringContainsString("String(event.key).toLowerCase() === 'p'", $page);
    }

    public function test_the_box_of_the_responsible_user_can_be_signed_on_the_screen_and_nothing_of_it_is_kept(): void
    {
        $this->actingAs($this->superAdmin());
        $this->travelTo(Carbon::parse('2026-10-08 10:00:00', 'Asia/Makassar'));
        $radio = $this->radio();
        $page = $this->form($radio)->assertOk()->getContent();
        $flat = self::flat($page);

        // One box is for signing on the screen, with the day it is signed in it; the box of the ICT person is signed on paper, as before.
        $this->assertSame(1, substr_count($page, 'id="signCell"'));
        $this->assertStringContainsString('<td class="sign" id="signCell" colspan="2" rowspan="3" data-today="08-10-2026">', $flat);
        $this->assertStringContainsString('<td class="sign" rowspan="3"></td>', $flat);
        $this->assertStringContainsString('<button type="button" class="sig-open no-print" id="signOpen"', $flat);
        // The pad: a dialog for a pen, a finger or a mouse, closed until the box is clicked, and a pen on it draws instead of scrolling.
        $this->assertStringContainsString('<div class="sig-overlay no-print" id="signOverlay" role="dialog" aria-modal="true" aria-labelledby="signTitle" hidden>', $flat);
        $this->assertStringContainsString('<canvas id="signCanvas"', $flat);
        foreach (['signUndo', 'signClear', 'signCancel', 'signApply', 'signNote'] as $id) {
            $this->assertSame(1, substr_count($page, "id=\"{$id}\""), $id);
        }
        // The panel is what gets the focus, and the note under it says why Ctrl+P did nothing while the pad was open.
        $this->assertSame(1, substr_count($page, 'class="sig-panel"'));
        $this->assertStringContainsString('<p class="sig-note" id="signNote" role="alert" hidden>', $flat);

        $css = self::css($page);
        // A pen on the pad draws instead of scrolling, and the line to sign on does not catch it.
        $this->assertMatchesRegularExpression('/\.sig-surface canvas \{[^}]*touch-action: none/', $css);
        $this->assertMatchesRegularExpression('/\.sig-line \{[^}]*pointer-events: none; \}/', $css);
        // What is shown with a display of its own is hidden by the attribute only because a rule says so.
        foreach (['.sig-overlay[hidden]', '.sig-note[hidden]', '.sig-remove[hidden]'] as $selector) {
            $this->assertStringContainsString("{$selector} { display: none; }", $css);
        }
        // The buttons and the picture lie over the box, which they are placed in; the picture is 1 mm inside it (see INSET).
        $this->assertStringContainsString('.grid td.sign { position: relative; }', $css);
        $this->assertMatchesRegularExpression('/\.sig-img \{ position: absolute; inset: 1mm; width: calc\(100% - 2mm\); height: calc\(100% - 2mm\);/', $css);

        // Nothing is signed in the page that is sent, and the page takes nothing from anywhere but its own logo: every address in its
        // markup is that of the logo, and it has no form to send.
        $this->assertStringNotContainsString('class="sig-img"', $page);
        $markup = (string) preg_replace('#<style.*?</style>|<script.*?</script>#s', '', $page);
        $this->assertSame(0, preg_match('#<(form|iframe|object|embed|link|a)\b#i', $markup));
        $this->assertSame(1, preg_match_all('#\b(?:src|href|action|data|poster|srcset)="([^"]*)"#', $markup, $addresses));
        $this->assertStringEndsWith('/assets/img/branding/mti-logo.png', $addresses[1][0]);

        // Nothing in any script of the page (whatever its attributes) sends a signature anywhere, keeps it, or reaches for the page around
        // the frame.
        $this->assertSame(2, preg_match_all('#<script\b([^>]*)>(.*?)</script\s*>#is', $page, $scripts, PREG_SET_ORDER), 'the pad and the fields');
        foreach ($scripts as [, $attributes, $body]) {
            $this->assertStringNotContainsString('src', $attributes, 'a script from somewhere else');
            foreach ([
                'fetch(', 'XMLHttpRequest', 'sendBeacon', 'WebSocket', 'EventSource', 'Worker', 'new Image(', 'postMessage', 'import(', 'importScripts',
                '$.ajax', '$.post', '$.get', 'navigator.', 'window.open', 'window.parent', 'window.top', 'window.opener', 'document.write',
                'localStorage', 'sessionStorage', 'indexedDB', 'document.cookie', 'caches.', '.submit(', 'eval(', 'Function(',
            ] as $api) {
                $this->assertStringNotContainsString($api, $body, $api);
            }
        }
        // There is no route that takes a signature.
        $this->post(route('radio-management.radio.checkout-form', $radio), ['signature' => 'data:image/png;base64,AAAA'])->assertStatus(405);
    }

    public function test_the_developer_bar_is_left_off_the_form(): void
    {
        // A page that is signed on and printed carries nothing of the developer's tools: their bar would lie over the box that is signed in.
        $this->actingAs($this->superAdmin());
        $bar = Mockery::spy();
        $this->app->instance('debugbar', $bar);

        $this->form($this->radio())->assertOk();

        $bar->shouldHaveReceived('disable')->once();
    }

    public function test_the_form_opens_where_the_developer_bar_is_not_installed(): void
    {
        // A server that was installed without the developer packages (composer install --no-dev) has no bar to turn off: the form must not ask
        // for one. (The bar is an alias of the container; taking it away is how this test makes the server without it.)
        $this->actingAs($this->superAdmin());
        $radio = $this->radio();
        $aliases = new ReflectionProperty(Container::class, 'aliases');
        $was = $aliases->getValue($this->app);
        try {
            $aliases->setValue($this->app, array_diff_key($was, ['debugbar' => true]));
            $this->assertFalse($this->app->bound('debugbar'));

            $this->form($radio)->assertOk();
        } finally {
            $aliases->setValue($this->app, $was);
        }
    }

    public function test_the_date_is_the_day_it_is_in_the_time_zone_of_the_application(): void
    {
        $this->actingAs($this->superAdmin());
        $was = date_default_timezone_get();
        date_default_timezone_set('Asia/Makassar');
        try {
            // 00:30 on the 8th in Makassar is still the 7th in UTC and in Jakarta.
            $this->travelTo(Carbon::parse('2026-10-08 00:30:00', 'Asia/Makassar'));

            $page = $this->form($this->radio())->assertOk()->getContent();

            $this->assertSame('08-10-2026', self::field($page, 'ict_date'));
            $this->assertStringContainsString('data-today="08-10-2026"', $page);
        } finally {
            date_default_timezone_set($was);
        }
    }

    public function test_the_buttons_of_a_row_are_tied_to_what_they_open(): void
    {
        // The row writes the address and the label of its radio into the print button, and a click hands them to the pop-up.
        $this->checkPageScript(route('radio-management.radio.index'), 'tests/tools/radio-index-behaviour.mjs', 4);
    }

    public function test_the_script_of_the_pop_up_behaves_as_described(): void
    {
        // It is a script in the page: it makes a frame for every form, offers printing only for a form (not for a sign-in page) and
        // says what to do for any other page, prints on Ctrl+P, and cleans up and gives the focus back when it closes.
        $this->checkPageScript(route('radio-management.radio.index'), 'tests/tools/radio-print-behaviour.mjs', 8);
    }

    public function test_the_script_of_the_form_behaves_as_described(): void
    {
        // The fields can be filled in by hand before the form is printed: as plain text, a click strikes out HT or RIG,
        // a form that runs past a page says so and is made smaller for the printing, and Ctrl+P prints it.
        $this->checkPageScript(route('radio-management.radio.checkout-form', $this->radio()), 'tests/tools/radio-form-behaviour.mjs', 9);
    }

    public function test_the_script_of_the_pad_for_signing_behaves_as_described(): void
    {
        // What a pen, a finger and a mouse draw and do not draw, how big the pad is, what comes out as the picture and how
        // thick its ink is, and how the picture and the date go into the form and out again.
        $this->checkPageScript(route('radio-management.radio.checkout-form', $this->radio()), 'tests/tools/radio-signature-behaviour.mjs', 14);
    }

    /** @param array<int, string> $texts */
    private function assertSeeInOrder(string $page, array $texts): void
    {
        $at = 0;
        foreach ($texts as $text) {
            $found = strpos($page, $text, $at);
            $this->assertNotFalse($found, "\"{$text}\" is not there, or not after the one before it.");
            $at = $found + strlen($text);
        }
    }
}
