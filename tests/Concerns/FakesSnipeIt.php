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

    /**
     * The history of the asset of hardware/11 as Snipe-IT gives it (newest first): a checkout, a change of four fields (one
     * of them a password), its creation. $total: how many entries Snipe-IT says there are.
     *
     * @return array<string, mixed>
     */
    protected static function snipeAssetHistory(int $total = 3): array
    {
        $moment = fn (string $at) => ['datetime' => $at, 'formatted' => $at];

        return ['total' => $total, 'rows' => [
            ['id' => 30, 'action_date' => $moment('2026-10-21 10:30:29'), 'created_at' => $moment('2026-10-21 10:31:00'), 'action_type' => 'checkout',
                'item' => ['id' => 11, 'name' => 'MTI-HT-001', 'type' => 'asset'], 'target' => ['id' => 7, 'name' => 'Ann O&#039;Neil', 'type' => 'user'],
                'admin' => ['id' => 1, 'name' => 'Admin One'], 'log_meta' => null, 'note' => '<b>Handed over</b> at the gate'],
            ['id' => 29, 'action_date' => $moment('2026-10-20 08:00:00'), 'created_at' => $moment('2026-10-20 08:00:00'), 'action_type' => 'update',
                'item' => ['id' => 11, 'name' => 'MTI-HT-001', 'type' => 'asset'], 'target' => null, 'admin' => ['id' => 1, 'name' => 'Admin One'],
                'log_meta' => [
                    'status_id' => ['old' => '2', 'new' => '4'],
                    '_snipeit_radio_id_8' => ['old' => '', 'new' => '4021'],
                    'Current Location' => ['old' => '[id: 2] Warehouse &amp; Logistics', 'new' => '[id: 26] IT Store'],
                    'password' => ['old' => 'old-secret', 'new' => 'new-secret'],
                ], 'note' => null],
            ['id' => 1, 'action_date' => $moment('2024-10-07 16:12:45'), 'created_at' => $moment('2024-10-07 16:12:45'), 'action_type' => 'create new',
                'item' => ['id' => 11, 'name' => 'MTI-HT-001', 'type' => 'asset'], 'target' => null, 'admin' => null, 'log_meta' => null, 'note' => null],
        ]];
    }

    /** The answers for the page of the asset of hardware/11: its history, and one asset checked out to it. */
    protected static function snipeAssetAnswers(int $total = 3): array
    {
        return [
            'reports/activity' => fn (array $query) => Http::response(($query['item_id'] ?? null) === '11' ? self::snipeAssetHistory($total) : self::snipeData()['reports/activity']),
            'hardware' => fn (array $query) => Http::response(($query['assigned_to'] ?? null) === '11'
                ? ['total' => 1, 'rows' => [['id' => 99, 'asset_tag' => 'MTI-HS-099', 'name' => 'Headset', 'model' => ['id' => 30, 'name' => 'Headset model']]]]
                : self::snipeData()['hardware']),
        ];
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
            // The asset whose detail is checked (hardware/11), what was checked out to it, its licenses.
            'hardware/11' => [
                'id' => 11, 'asset_tag' => 'MTI-HT-001', 'name' => 'Radio &lt;b&gt;one&lt;/b&gt;', 'serial' => '867TBT0001',
                'model' => ['id' => 25, 'name' => 'Motorola XiR P6620i'], 'model_number' => 'XIR P6620i',
                'category' => ['id' => 15, 'name' => 'HT'], 'manufacturer' => ['id' => 7, 'name' => 'Motorola'],
                'status_label' => ['id' => 4, 'name' => 'Deployed', 'status_type' => 'deployable', 'status_meta' => 'deployed'],
                'company' => ['id' => 1, 'name' => 'MTI'], 'supplier' => ['id' => 3, 'name' => 'Radio Supplier'],
                'location' => ['id' => 2, 'name' => 'Warehouse &amp; Logistics'], 'rtd_location' => ['id' => 26, 'name' => 'IT Store'],
                'assigned_to' => ['id' => 7, 'name' => 'Ann O&#039;Neil', 'type' => 'user', 'username' => 'ann', 'email' => 'ann@example.test', 'employee_number' => null],
                'notes' => "NEW RADIO\r\nBattery SN 50002F1488A5 <script>alert(1)</script>",
                'order_number' => 'PO-47841', 'purchase_date' => ['date' => '2024-10-07', 'formatted' => '2024-10-07'], 'purchase_cost' => '1,234.00',
                'book_value' => '987.00', 'warranty_months' => 12, 'warranty_expires' => ['date' => '2027-01-02', 'formatted' => '2027-01-02'],
                'asset_eol_date' => null, 'last_checkout' => $moment('2026-10-21 10:30:29'), 'expected_checkin' => null,
                'checkout_counter' => 3, 'checkin_counter' => 2, 'created_at' => $moment('2024-10-07 16:12:45'), 'updated_at' => $moment('2026-10-21 09:15:42'),
                'last_audit_date' => null, 'next_audit_date' => ['date' => '2027-10-07', 'formatted' => '2027-10-07'],
                'image' => 'https://snipeit.test/uploads/models/p6620i.png', 'deleted_at' => null, 'byod' => false,
                'custom_fields' => [
                    'Radio ID' => ['field' => '_snipeit_radio_id_8', 'value' => '4021', 'field_format' => 'NUMERIC', 'element' => 'text'],
                    'MDC Code' => ['field' => '_snipeit_mdc_code_9', 'value' => '', 'field_format' => 'ANY', 'element' => 'text'],
                    'BIOS Password' => ['field' => '_snipeit_bios_password_13', 'value' => 'bios-secret-123', 'field_format' => 'ANY', 'element' => 'text'],
                ],
            ],
            'hardware/11/licenses' => ['total' => 1, 'rows' => [
                ['id' => 89, 'name' => 'Radio Programming Software', 'product_key' => 'SECRET-PROGRAMMING-KEY', 'category' => ['id' => 61, 'name' => 'Software'],
                    'expiration_date' => ['date' => '2027-03-31', 'formatted' => '2027-03-31']],
            ]],
            'hardware/99' => ['status' => 'error', 'messages' => 'Asset not found', 'payload' => null],
            'maintenances' => ['total' => 1, 'rows' => [
                ['id' => 5, 'title' => 'Battery swap', 'asset' => ['id' => 11, 'name' => 'Radio one', 'asset_tag' => 'MTI-HT-001'],
                    'asset_maintenance_type' => 'Repair', 'start_date' => ['date' => '2026-09-10', 'formatted' => '2026-09-10'],
                    'completion_date' => ['date' => '2026-09-12', 'formatted' => '2026-09-12'], 'supplier' => ['id' => 3, 'name' => 'Radio Supplier'],
                    'cost' => '350,000.00', 'is_warranty' => 0, 'notes' => 'Old battery &amp; charger checked'],
            ]],
            'statuslabels' => ['total' => 4, 'rows' => [
                ['id' => 4, 'name' => 'Deployed', 'type' => 'deployable', 'assets_count' => 2051, 'notes' => null],
                ['id' => 5, 'name' => 'Broken', 'type' => 'undeployable', 'assets_count' => 17, 'notes' => 'Waiting for &quot;repair&quot;'],
                ['id' => 6, 'name' => 'Archived', 'type' => 'archived', 'assets_count' => 9, 'notes' => null],
                ['id' => 2, 'name' => 'Ready to Deploy', 'type' => 'deployable', 'assets_count' => 148, 'notes' => null],
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
                ['id' => 1, 'name' => 'MAC Address', 'db_column_name' => '_snipeit_mac_address_1', 'type' => 'text', 'format' => 'MAC', 'field_values_array' => null, 'is_unique' => false],
                ['id' => 8, 'name' => 'Radio ID', 'db_column_name' => '_snipeit_radio_id_8', 'type' => 'text', 'format' => 'NUMERIC', 'field_values_array' => null, 'is_unique' => true],
                ['id' => 12, 'name' => 'Charger HT', 'db_column_name' => '_snipeit_charger_ht_12', 'type' => 'listbox', 'format' => 'ANY', 'field_values_array' => ['Yes', 'No'], 'is_unique' => false],
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
