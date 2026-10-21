<?php

namespace Tests\Feature;

use App\Models\Log\AuditLog;
use App\Services\Audit\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\RadioManagement\Models\ItSignature;
use Modules\RadioManagement\Models\Radio;
use Modules\RadioManagement\Models\RadioCompany;
use Modules\RadioManagement\Services\MasterData;
use Tests\Concerns\MakesUsers;
use Tests\Concerns\UsesEnglish;
use Tests\TestCase;

/**
 * "Master Data Radio" and "E-Sign Master" in English: the pages, the words their scripts are given, and what the server
 * answers. What the tables hold, and what the audit trail keeps of them, stay as they are.
 */
class MasterESignLanguageTest extends TestCase
{
    use MakesUsers;
    use RefreshDatabase;
    use UsesEnglish;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useEnglish();
    }

    /** A signature as the pad sends it: two dark strokes on nothing. */
    private static function drawn(): string
    {
        $picture = imagecreatetruecolor(600, 240);
        imagealphablending($picture, false);
        imagesavealpha($picture, true);
        imagefill($picture, 0, 0, imagecolorallocatealpha($picture, 0, 0, 0, 127));
        imagesetthickness($picture, 4);
        $black = imagecolorallocatealpha($picture, 16, 16, 16, 0);
        imageline($picture, 20, 200, 300, 30, $black);
        imageline($picture, 300, 30, 580, 210, $black);
        ob_start();
        imagepng($picture);
        $png = (string) ob_get_clean();
        imagedestroy($picture);

        return 'data:image/png;base64,' . base64_encode($png);
    }

    public function test_a_master_page_is_in_english(): void
    {
        $this->actingAs($this->superAdmin());
        $page = (string) $this->get(route('radio-management.radio-type.index'))->assertOk()->getContent();

        $this->assertStringContainsString('<h1 class="rm-title">Radio Types</h1>', $page);
        $this->assertStringContainsString('<p class="rm-sub">The radio models that can be chosen in the register.</p>', $page);
        foreach (['Radio Types', 'Companies', 'Departments', 'Channel Templates'] as $tab) {
            $this->assertMatchesRegularExpression('#<a href="[^"]+"[^>]*>' . $tab . '</a>#', $page, $tab);
        }
        $this->assertStringContainsString('<i class="ti ti-plus" aria-hidden="true"></i> Add radio type', $page);
        $this->assertStringContainsString('placeholder="Search radio types…"', $page);
        $this->assertMatchesRegularExpression('#<th class="text-end">In use</th>\s*<th class="text-end"><span class="visually-hidden">Actions</span></th>#', $page);
        $this->assertStringContainsString('<div class="rm-cardbox-sub" id="masterFormSub">Fill in and save; new data can be chosen in the register at once.</div>', $page);
        // The fields (labels in the form and the head of the table) and the words of the script.
        $this->assertStringContainsString('"name":{"label":"Type name","type":"text"', $page);
        $this->assertStringContainsString('"power":{"label":"Power"', $page);
        $this->assertStringContainsString('"add":"Add radio type","edit":"Edit radio type","delete":"Delete this radio type?"', $page);
        $this->assertStringContainsString('"edit_sub":"Change and save; the radios that use it change with it."', $page);
        $this->assertStringContainsString('"empty":"No data yet"', $page);
        // An example is data: the same in both languages.
        $this->assertStringContainsString('"placeholder":"MotoTrbo XiR P6620i"', $page);
        foreach (['Model radio yang', 'Tambah Tipe Radio', 'Cari tipe radio', 'Nama Tipe', 'Isi lalu simpan', 'Ubah lalu simpan', 'Belum ada data', 'Gagal memuat data', 'masih dipakai radio', 'Dipakai', 'Nonaktif'] as $indonesian) {
            $this->assertStringNotContainsString($indonesian, $page, $indonesian);
        }

        // The help of a field.
        $templates = (string) $this->get(route('radio-management.channel-template.index'))->assertOk()->getContent();
        $this->assertStringContainsString('<h1 class="rm-title">Channel Templates</h1>', $templates);
        // (As the page's script gets it: in JSON, with the quotes written out.)
        $help = json_encode('Radios on this template that were programmed before this date are marked "Needs reprogramming".', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
        $this->assertStringContainsString('"help":' . $help, $templates);
    }

    public function test_the_server_answers_a_master_page_in_english_and_the_audit_trail_keeps_its_wording(): void
    {
        $this->actingAs($this->superAdmin());

        $this->postJson(route('radio-management.company.store'), ['code' => 'abc', 'name' => 'PT ABC', 'is_active' => 1])
            ->assertOk()->assertExactJson(['message' => 'Company added.']);
        $this->postJson(route('radio-management.company.store'), ['code' => 'ABC'])
            ->assertStatus(422)->assertExactJson(['message' => 'Invalid input', 'errors' => ['Code already exists.']]);
        $this->postJson(route('radio-management.radio-type.store'), ['category' => 'HT'])
            ->assertStatus(422)->assertJsonPath('errors.0', 'Type name is required.');

        $company = RadioCompany::where('code', 'ABC')->sole();
        $this->putJson(route('radio-management.company.update', $company->id), ['code' => 'ABC', 'name' => 'PT ABC Baru', 'is_active' => 1])
            ->assertOk()->assertExactJson(['message' => 'Company saved.']);
        Radio::create(['serial_number' => '867TZB1111', 'user_name' => 'x', 'company_id' => $company->id]);
        $this->deleteJson(route('radio-management.company.destroy', $company->id))->assertStatus(422)->assertJsonPath('errors.0',
            'Still used by 1 radio. Switch it off instead (it can then no longer be chosen for other radios), or move the radio to other data first.');
        $this->assertStringStartsWith('Still used by 2 radios.', MasterData::inUseMessage(2));

        // What the trail keeps is in one wording, whichever language the page was in.
        $made = AuditLog::where('action', AuditLogger::MASTER_CREATED)->sole();
        $this->assertSame('Perusahaan: ABC', $made->entity_label);
        $this->assertSame(['Kode' => 'ABC', 'Nama Perusahaan' => 'PT ABC', 'Aktif' => 'Ya'], $made->new_values);
        $this->assertSame(['Nama Perusahaan' => 'PT ABC Baru'], AuditLog::where('action', AuditLogger::MASTER_UPDATED)->sole()->new_values);
    }

    public function test_the_e_sign_page_and_its_pad_are_in_english(): void
    {
        $this->actingAs($this->superAdmin());
        $page = (string) $this->get(route('radio-management.e-sign.index'))->assertOk()->getContent();

        $this->assertStringContainsString('<h1 class="rm-title">E-Sign Master</h1>', $page);
        $this->assertStringContainsString('The signatures of the ICT team for the PROCESSED BY box of the IT Equipment Checkout Form.', $page);
        $this->assertStringContainsString('<span>A form shows the signature of <b>the account that opens that form</b>: nobody can put', $page);
        $this->assertStringContainsString('<i class="ti ti-plus" aria-hidden="true"></i> Register a signature', $page);
        $this->assertMatchesRegularExpression('#<th>Account</th>\s*<th>Printed name</th>\s*<th>Signature</th>#', $page);
        $this->assertStringContainsString('<label for="esign_user" class="form-label required">Owner account</label>', $page);
        $this->assertStringContainsString('<h3 class="rm-dialog-title" id="esignUsesTitle">Use history</h3>', $page);
        // The words of the script, the frame of the pad among them.
        $this->assertStringContainsString('"signature_of":"Signature of :name"', $page);
        $this->assertStringContainsString('"times":"{1} :count time|[0,*] :count times"', $page);
        $this->assertStringContainsString('"pad":"Signature box"', $page);
        $this->assertStringContainsString('"choose_owner":"Choose the account the signature belongs to."', $page);
        foreach (['Daftarkan tanda tangan', 'Nama tercetak', 'Riwayat pemakaian', 'Belum pernah dipakai', 'Akun pemilik', 'Tanda tangan sekarang', 'kali dipakai', 'Kotak tanda tangan'] as $indonesian) {
            $this->assertStringNotContainsString($indonesian, $page, $indonesian);
        }

        $pad = (string) preg_replace('/\s+/', ' ', (string) $this->get(route('radio-management.e-sign.pad'))->assertOk()->getContent());
        $this->assertStringContainsString('<html lang="en">', $pad);
        $this->assertStringContainsString('<title>Signature of ICT Person</title>', $pad);
        $this->assertStringContainsString('<p class="hint">Two ways: <b>click the box</b> to sign on the screen (pen, finger or mouse), or <b>drag &amp; drop / upload a file</b> with the signature.</p>', $pad);
        $this->assertStringContainsString('aria-label="Sign for ICT Person on the screen">Click to sign<small>or drag &amp; drop a picture of the signature here</small></button>', $pad);
        $this->assertStringContainsString('<label for="signFile">Upload a signature file</label>', $pad);
        $this->assertStringContainsString('<h2 id="signTitle">Signature of ICT Person</h2>', $pad);
        $this->assertStringContainsString('"picture":"Signature of ICT Person","change":"Change the signature of ICT Person"', $pad);
        foreach (['Unggah', 'seret', 'Tanda tangan', 'Klik untuk'] as $indonesian) {
            $this->assertStringNotContainsString($indonesian, $pad, $indonesian);
        }
    }

    public function test_the_server_answers_the_e_sign_page_in_english(): void
    {
        $this->actingAs($this->superAdmin());
        $owner = $this->makeUser('teknisi.satu@test.local', 'Admin Radio', 'Teknisi Satu');

        $this->postJson(route('radio-management.e-sign.store'), ['display_name' => '', 'image' => self::drawn()])
            ->assertStatus(422)->assertExactJson(['message' => 'Invalid input', 'errors' => ['Choose the account the signature belongs to.']]);
        $this->postJson(route('radio-management.e-sign.store'), ['user_id' => $owner->id, 'image' => 'data:image/jpeg;base64,AAAA'])
            ->assertStatus(422)->assertJsonPath('errors.0', 'The signature must be a PNG picture from the signature box.');
        $this->postJson(route('radio-management.e-sign.store'), ['user_id' => $owner->id, 'display_name' => '', 'image' => self::drawn()])
            ->assertOk()->assertExactJson(['message' => 'Signature of Teknisi Satu registered.']);
        $this->postJson(route('radio-management.e-sign.store'), ['user_id' => $owner->id, 'image' => self::drawn()])
            ->assertStatus(422)->assertJsonPath('errors.0', 'That account already has a signature. Replace it with the Edit button.');

        $signature = ItSignature::where('user_id', $owner->id)->sole();
        $this->putJson(route('radio-management.e-sign.update', $signature->id), ['display_name' => '', 'active' => true])
            ->assertStatus(422)->assertJsonPath('errors.0', 'The name printed under the signature is required.');
        $this->putJson(route('radio-management.e-sign.update', $signature->id), ['display_name' => 'T. Satu', 'active' => true])
            ->assertOk()->assertExactJson(['message' => 'Signature of T. Satu saved.']);
        $this->deleteJson(route('radio-management.e-sign.destroy', $signature->id))->assertOk()->assertExactJson(['message' => 'Signature of T. Satu deleted.']);

        // The trail keeps its own words (it always did, in English).
        $this->assertSame(['Account' => 'teknisi.satu@test.local', 'Name printed' => 'Teknisi Satu', 'Active' => 'Yes'],
            AuditLog::where('action', AuditLogger::ESIGN_CREATED)->sole()->new_values);
    }
}
