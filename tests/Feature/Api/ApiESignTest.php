<?php

namespace Tests\Feature\Api;

use App\Models\Log\AuditLog;
use App\Services\Audit\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\RadioManagement\Models\ItSignature;
use Modules\RadioManagement\Models\ItSignatureUse;
use Modules\RadioManagement\Models\Radio;
use Tests\Concerns\MakesUsers;
use Tests\Concerns\UsesApi;
use Tests\TestCase;

/**
 * "E-Sign Master" through the API: read, and switch off. Never the picture of a signature.
 */
class ApiESignTest extends TestCase
{
    use MakesUsers;
    use RefreshDatabase;
    use UsesApi;

    protected bool $seed = true;

    /** A small picture as the page stores it (it is encrypted in the database). */
    private const PICTURE = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    private function signatureOf(string $email, string $name, bool $active = true): ItSignature
    {
        $owner = $this->makeUser($email, 'Admin Radio', $name);

        return ItSignature::create(['user_id' => $owner->id, 'display_name' => $name, 'image' => self::PICTURE, 'active' => $active]);
    }

    public function test_the_signatures_are_listed_without_their_picture(): void
    {
        $andi = $this->signatureOf('andi@test.local', 'Andi Pratama');
        $this->signatureOf('rina@test.local', 'Rina Wulandari', active: false);
        $radio = Radio::create(['dmr_id' => 11, 'serial_number' => '867TZB5309', 'user_name' => 'Budi']);
        foreach ([1, 2] as $i) {
            ItSignatureUse::create(['signature_id' => $andi->id, 'user_id' => $andi->user_id, 'user_name' => 'Andi Pratama', 'radio_id' => $radio->id,
                'radio_label' => 'ID 11 · SN 867TZB5309 · Budi', 'created_at' => now()->subMinutes(10 - $i)]);
        }
        $token = $this->tokenFor($this->superAdmin(), write: false);

        $list = $this->api('GET', '/api/v1/e-signs', token: $token)->assertOk()->json();
        $this->assertSame(['Andi Pratama', 'Rina Wulandari'], array_column($list['data'], 'display_name'));
        $this->assertSame([true, 2, 'andi@test.local', false], [$list['data'][0]['active'], $list['data'][0]['uses'], $list['data'][0]['account']['email'], $list['data'][0]['account']['gone']]);
        $this->assertSame([false, 0, null], [$list['data'][1]['active'], $list['data'][1]['uses'], $list['data'][1]['last_used_at']]);
        $this->assertStringNotContainsString('image', json_encode($list));
        $this->assertStringNotContainsString('base64', json_encode($list));
        $this->assertSame(['Rina Wulandari'], array_column($this->api('GET', '/api/v1/e-signs?search=rina@', token: $token)->json('data'), 'display_name'));

        // Where it was put, the latest first.
        $uses = $this->api('GET', "/api/v1/e-signs/{$andi->id}/uses", token: $token)->assertOk()->json();
        $this->assertSame([2, 'ID 11 · SN 867TZB5309 · Budi', $radio->id], [$uses['total'], $uses['data'][0]['radio'], $uses['data'][0]['radio_id']]);
        $this->api('GET', '/api/v1/e-signs/999999/uses', token: $token)->assertNotFound();

        // The menu is Super Admin's: Admin Radio has none.
        $this->api('GET', '/api/v1/e-signs', token: $this->tokenFor($this->makeUser('admin.radio@test.local', 'Admin Radio')))->assertForbidden();
    }

    public function test_a_signature_is_switched_off_and_its_form_is_signed_on_paper_again(): void
    {
        $andi = $this->signatureOf('andi@test.local', 'Andi Pratama');
        $radio = Radio::create(['dmr_id' => 11, 'serial_number' => '867TZB5309', 'user_name' => 'Budi']);
        $form = fn () => (string) $this->actingAs($andi->user, 'web')->get(route('radio-management.radio.checkout-form', $radio))->assertOk()->getContent();
        // Before: the form its account opens carries it.
        $this->assertStringContainsString('<img class="ict-sig-img"', $form());
        $token = $this->tokenFor($this->superAdmin(), name: 'Offboarding');

        // A token that reads does not switch it off.
        $this->api('POST', "/api/v1/e-signs/{$andi->id}/deactivate", token: $this->tokenFor($this->superAdmin(), write: false))->assertForbidden();

        $this->api('POST', "/api/v1/e-signs/{$andi->id}/deactivate", token: $token)->assertOk()
            ->assertJsonPath('message', 'Signature of Andi Pratama switched off.')->assertJsonPath('data.active', false);
        $this->assertFalse($andi->fresh()->active);
        $entry = AuditLog::where('action', AuditLogger::ESIGN_UPDATED)->sole();
        $this->assertSame([['Active' => 'Yes'], ['Active' => 'No']], [$entry->old_values, $entry->new_values]);
        $this->assertMatchesRegularExpression('/^API · Offboarding \(#\d+\)$/', (string) $entry->via);

        // Once is enough: nothing more is written. (The message follows the request's language: English unless it asks
        // for Indonesian.)
        $this->api('POST', "/api/v1/e-signs/{$andi->id}/deactivate", token: $token)->assertOk()->assertJsonPath('message', 'The signature of Andi Pratama was already switched off.');
        $this->api('POST', "/api/v1/e-signs/{$andi->id}/deactivate", token: $token, headers: ['Accept-Language' => 'id'])->assertOk()
            ->assertJsonPath('message', 'Tanda tangan Andi Pratama memang sudah nonaktif.');
        $this->assertSame(1, AuditLog::where('action', AuditLogger::ESIGN_UPDATED)->count());

        // After: an empty box, to be signed on paper.
        $this->assertStringNotContainsString('<img class="ict-sig-img"', $form());
    }
}
