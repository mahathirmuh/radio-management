<?php

namespace Tests\Concerns;

use Closure;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * A pretend Snipe-IT (http://snipeit.test/api/v1) with made-up rows, written as Snipe-IT 6.2 writes them (texts
 * HTML-escaped, things as {id, name}, moments as {datetime, formatted}), and fields the pages must never show (a licence
 * key, a phone number). $snipeAsked keeps what the application asked it.
 */
trait FakesSnipeIt
{
    /** @var list<array{method: string, path: string, query: array<string, string>, token: ?string}> */
    protected array $snipeAsked = [];

    /** @var array<string, array<string, mixed>|Closure> what the pretend Snipe-IT answers now */
    private array $snipeAnswers = [];

    private bool $snipeFaked = false;

    public const SNIPE_TOKEN = 'snipe-secret-token-123';

    /**
     * Calling it again within a test changes the answers (Http::fake keeps its first stub).
     *
     * @param  array<string, array<string, mixed>|Closure>  $answers  endpoint => answer (or a function of the query that gives
     *     a response), over the made-up ones
     */
    protected function fakeSnipeIt(array $answers = []): void
    {
        config(['services.snipeit.url' => 'http://snipeit.test/api/v1', 'services.snipeit.token' => self::SNIPE_TOKEN]);
        $this->snipeAsked = [];
        $this->snipeAnswers = $answers + self::snipeData();
        if ($this->snipeFaked) {
            return;
        }
        $this->snipeFaked = true;

        Http::fake(function (Request $request) {
            $url = parse_url($request->url());
            parse_str($url['query'] ?? '', $query);
            $path = Str::after($url['path'] ?? '', '/api/v1/');
            $this->snipeAsked[] = ['method' => $request->method(), 'path' => $path, 'query' => $query, 'token' => $request->header('Authorization')[0] ?? null];
            $answer = $this->snipeAnswers[$path] ?? ['total' => 0, 'rows' => []];

            return $answer instanceof Closure ? $answer($query) : Http::response($answer);
        });
    }

    /** The questions asked about one endpoint, in order. */
    protected function snipeAskedAbout(string $path): array
    {
        return array_values(array_filter($this->snipeAsked, fn (array $asked) => $asked['path'] === $path));
    }

    /** @return array<string, array<string, mixed>> */
    protected static function snipeData(): array
    {
        $moment = fn (string $at) => ['datetime' => $at, 'formatted' => $at];

        return [
            'version' => ['version' => 'v6.2.4-pre'],
            'hardware' => ['total' => 539, 'rows' => [
                [
                    'id' => 11, 'asset_tag' => 'MTI-HT-001', 'name' => 'Radio &lt;b&gt;one&lt;/b&gt;', 'serial' => '867TBT0001',
                    'model' => ['id' => 25, 'name' => 'Motorola XiR P6620i'], 'category' => ['id' => 15, 'name' => 'HT'],
                    'status_label' => ['id' => 4, 'name' => 'Deployed', 'status_type' => 'deployable', 'status_meta' => 'deployed'],
                    'assigned_to' => ['id' => 7, 'name' => 'Ann O&#039;Neil', 'type' => 'user', 'username' => 'ann', 'email' => 'ann@example.test'],
                    'location' => ['id' => 2, 'name' => 'Warehouse &amp; Logistics'],
                    'updated_at' => $moment('2026-10-21 09:15:42'), 'purchase_cost' => '1,234.00',
                    'custom_fields' => ['Radio ID' => ['field' => '_snipeit_radio_id_8', 'value' => '4021']],
                    'notes' => 'Battery SN 50002F1488A5',
                ],
                [
                    'id' => 12, 'asset_tag' => 'MTI-HT-002', 'name' => '', 'serial' => null,
                    'model' => ['id' => 25, 'name' => 'Motorola XiR P6620i'], 'category' => ['id' => 15, 'name' => 'HT'],
                    'status_label' => ['id' => 2, 'name' => 'Ready to Deploy', 'status_type' => 'deployable', 'status_meta' => 'deployable'],
                    'assigned_to' => null, 'location' => null, 'updated_at' => $moment('2026-10-20 16:00:00'),
                ],
            ]],
            'statuslabels' => ['total' => 3, 'rows' => [
                ['id' => 4, 'name' => 'Deployed', 'type' => 'deployable', 'assets_count' => 2051, 'notes' => null],
                ['id' => 5, 'name' => 'Broken', 'type' => 'undeployable', 'assets_count' => 17, 'notes' => 'Waiting for &quot;repair&quot;'],
                ['id' => 6, 'name' => 'Archived', 'type' => 'archived', 'assets_count' => 9, 'notes' => null],
            ]],
            'categories' => ['total' => 11, 'rows' => [
                ...array_map(fn (int $i, string $name, int $assets) => ['id' => 100 + $i, 'name' => $name, 'category_type' => 'asset', 'item_count' => $assets, 'assets_count' => $assets],
                    range(1, 10), ['HT', 'Notebook', 'CCTV', 'Monitor', 'PC Desktop', 'SIM card', 'Switch', 'Tablet', 'Printer', 'Router'],
                    [539, 462, 418, 252, 127, 117, 103, 39, 48, 10]),
                ['id' => 200, 'name' => 'Empty', 'category_type' => 'asset', 'item_count' => 0, 'assets_count' => 0],
            ]],
            'models' => ['total' => 190, 'rows' => [
                ['id' => 25, 'name' => 'Motorola XiR P6620i', 'model_number' => 'XIR P6620i', 'manufacturer' => ['id' => 7, 'name' => 'Motorola'],
                    'category' => ['id' => 15, 'name' => 'HT'], 'fieldset' => ['id' => 2, 'name' => 'HT/Radio'], 'assets_count' => 498],
            ]],
            'users' => ['total' => 2085, 'rows' => [
                ['id' => 7, 'name' => 'Ann O&#039;Neil', 'email' => 'ann@example.test', 'username' => 'ann', 'employee_num' => 'EMP001',
                    'jobtitle' => 'Technician', 'department' => ['id' => 1, 'name' => 'IT'], 'location' => ['id' => 2, 'name' => 'Warehouse'],
                    'assets_count' => 2, 'activated' => true, 'phone' => '0812-PRIVATE-PHONE', 'address' => 'Private Street 1'],
                ['id' => 8, 'name' => 'Bob Inactive', 'email' => null, 'username' => 'bob', 'employee_num' => null, 'jobtitle' => null,
                    'department' => null, 'location' => null, 'assets_count' => 0, 'activated' => false],
            ]],
            'licenses' => ['total' => 86, 'rows' => [
                ['id' => 89, 'name' => 'Office Suite', 'product_key' => 'SECRET-LICENSE-KEY-9999', 'seats' => 400, 'free_seats_count' => 107,
                    'manufacturer' => ['id' => 46, 'name' => 'Microsoft'], 'category' => ['id' => 61, 'name' => 'Software'],
                    'purchase_date' => ['date' => '2026-01-02', 'formatted' => '2026-01-02'], 'expiration_date' => null,
                    'license_name' => 'Private Licensee', 'license_email' => 'licensee@example.test'],
            ]],
            'fields' => ['total' => 3, 'rows' => [
                ['id' => 1, 'name' => 'MAC Address', 'type' => 'text', 'format' => 'MAC', 'field_values_array' => null, 'is_unique' => false],
                ['id' => 8, 'name' => 'Radio ID', 'type' => 'text', 'format' => 'NUMERIC', 'field_values_array' => null, 'is_unique' => true],
                ['id' => 12, 'name' => 'Charger HT', 'type' => 'listbox', 'format' => 'ANY', 'field_values_array' => ['Yes', 'No'], 'is_unique' => false],
            ]],
            'reports/activity' => ['total' => 23630, 'rows' => [
                ['id' => 1, 'created_at' => $moment('2026-10-21 10:30:29'), 'action_type' => 'checkout',
                    'item' => ['id' => 11, 'name' => 'MTI-HT-001', 'type' => 'asset'], 'target' => ['id' => 7, 'name' => 'Ann O&#039;Neil', 'type' => 'user'],
                    'admin' => ['id' => 1, 'name' => 'Admin One'], 'log_meta' => ['status_id' => ['old' => '2', 'new' => '4']], 'note' => '<b>Battery</b> swapped'],
                ['id' => 2, 'created_at' => $moment('2026-10-20 08:00:00'), 'action_type' => 'restored',
                    'item' => ['id' => 3, 'name' => 'Something', 'type' => 'gadget'], 'target' => null, 'admin' => null, 'log_meta' => null, 'note' => null],
            ]],
        ];
    }
}
