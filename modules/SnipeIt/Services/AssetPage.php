<?php

namespace Modules\SnipeIt\Services;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;
use Throwable;

/**
 * The detail of an asset as the page shows it (SnipeIt::asset), from what AssetDetail asked Snipe-IT: texts as plain
 * text, moments in the page's way, the history in words (Names). A custom field whose name says it holds a secret is not
 * shown, and neither is a licence key.
 */
final class AssetPage
{
    /** An action of the history as Snipe-IT names it => the colour of its dot (none: grey) and its icon. */
    private const ACTIONS = [
        'checkout' => ['blue', 'user-check'],
        'checkin from' => ['purple', 'arrow-back-up'],
        'create new' => ['green', 'plus'],
        'update' => ['orange', 'edit'],
        'delete' => ['red', 'trash'],
        'audit' => ['yellow', 'checklist'],
        'requested' => ['yellow', 'hand-click'],
        'accepted' => ['green', 'circle-check'],
        'declined' => ['red', 'circle-x'],
        'uploaded' => ['', 'paperclip'],
        'restore' => ['green', 'restore'],
        'restored' => ['green', 'restore'],
    ];


    /** The colour of a status, by Snipe-IT's status_meta. */
    private const STATUS_TONES = ['deployed' => 'blue', 'deployable' => 'green', 'pending' => 'yellow', 'undeployable' => 'red', 'archived' => 'gray'];

    public function __construct(private readonly Names $names)
    {
    }

    /**
     * @param  bool  $users  whoever looks may open the detail of a user here (else a user who holds the asset leads to Snipe-IT)
     * @return array<string, mixed>
     */
    public function present(array $data, ?string $web, bool $users = false): array
    {
        $asset = $data['asset'];
        $id = (int) $asset['id'];
        $category = Rows::text(data_get($asset, 'category.name'));

        return [
            'id' => $id,
            'tag' => Rows::text($asset['asset_tag'] ?? null) ?? '#' . $id,
            'name' => Rows::text($asset['name'] ?? null),
            'serial' => Rows::text($asset['serial'] ?? null),
            'model' => Rows::text(data_get($asset, 'model.name')),
            'category' => $category,
            'manufacturer' => Rows::text(data_get($asset, 'manufacturer.name')),
            'status' => $this->status($asset),
            'deleted' => Rows::date($asset['deleted_at'] ?? null),
            'image' => Rows::image($asset['image'] ?? null),
            'icon' => Rows::icon($category),
            'holder' => $this->holder($asset['assigned_to'] ?? null, $web, $users),
            'facts' => $this->facts($asset, $web, $users),
            'custom' => $this->custom($asset['custom_fields'] ?? null),
            'notes' => self::paragraph($asset['notes'] ?? null),
            'history' => [
                'total' => $data['history']['total'],
                'items' => array_map(fn (array $entry) => $this->event($entry, $web), $data['history']['rows']),
            ],
            'maintenances' => array_map(fn (array $row) => [
                'title' => Rows::text($row['title'] ?? null) ?? '—',
                'kind' => Rows::text($row['asset_maintenance_type'] ?? null),
                'start' => Rows::date($row['start_date'] ?? null),
                'end' => Rows::date($row['completion_date'] ?? null),
                'supplier' => Rows::text(data_get($row, 'supplier.name')),
                'cost' => Rows::text($row['cost'] ?? null),
                'warranty' => filter_var($row['is_warranty'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'notes' => self::paragraph($row['notes'] ?? null),
            ], $data['maintenances']),
            // A licence by its name, never by its key.
            'licenses' => array_map(fn (array $row) => [
                'name' => Rows::text($row['name'] ?? null) ?? '—',
                'category' => Rows::text(data_get($row, 'category.name')),
                'expires' => Rows::date($row['expiration_date'] ?? null),
                'link' => $web !== null && is_numeric($row['id'] ?? null) ? $web . '/licenses/' . (int) $row['id'] : null,
            ], $data['licenses']),
            'attached' => array_map(fn (array $row) => [
                'tag' => Rows::text($row['asset_tag'] ?? null) ?? '#' . ($row['id'] ?? ''),
                'name' => Rows::text($row['name'] ?? null) ?? Rows::text(data_get($row, 'model.name')),
                'link' => is_numeric($row['id'] ?? null) ? route('snipe-it.assets.show', (int) $row['id']) : null,
            ], $data['attached']),
            'web' => $web !== null ? $web . '/hardware/' . $id : null,
            'at' => $data['at'],
        ];
    }

    /** @return array{name: ?string, tone: string} */
    private function status(array $asset): array
    {
        $meta = Str::lower((string) Rows::text(data_get($asset, 'status_label.status_meta')));

        return ['name' => Rows::text(data_get($asset, 'status_label.name')), 'tone' => self::STATUS_TONES[$meta] ?? 'gray'];
    }

    /** Who or what holds the asset: a user (whose detail is here for whoever may open it), a location, another asset (here). */
    private function holder(mixed $to, ?string $web, bool $users): ?array
    {
        if (! is_array($to) || Rows::text($to['name'] ?? null) === null) {
            return null;
        }
        $type = Str::lower((string) ($to['type'] ?? ''));
        $id = is_numeric($to['id'] ?? null) ? (int) $to['id'] : null;
        $sub = $type === 'user'
            ? implode(' · ', array_filter([Rows::text($to['username'] ?? null), Rows::text($to['email'] ?? null), Rows::text($to['employee_number'] ?? null)]))
            : null;

        return [
            'name' => Rows::text($to['name']),
            'kind' => $this->word('kinds', $type) ?? $type,
            'sub' => $sub ?: null,
            'link' => match (true) {
                $id === null => null,
                $type === 'asset' => route('snipe-it.assets.show', $id),
                $type === 'user' && $users => route('snipe-it.users.show', $id),
                $web !== null && in_array($type, ['user', 'location'], true) => $web . '/' . ($type === 'user' ? 'users' : 'locations') . '/' . $id,
                default => null,
            },
            'inside' => $type === 'asset' || ($type === 'user' && $users),
        ];
    }

    /**
     * The facts of the boxes of the page (SnipeIt::partials.facts).
     *
     * @return array<string, list<array{label: string, value: ?string, mono?: bool, tone?: string, link?: ?string, inside?: bool, sub?: ?string, empty?: string}>>
     */
    private function facts(array $asset, ?string $web, bool $users): array
    {
        $fact = fn (string $key, ?string $value, array $more = []) => ['label' => __('SnipeIt::snipe.asset.facts.' . $key), 'value' => $value] + $more;
        $holder = $this->holder($asset['assigned_to'] ?? null, $web, $users);
        $months = is_numeric($asset['warranty_months'] ?? null) ? (int) $asset['warranty_months'] : null;
        $warranty = $months === null ? null : trim(trans_choice('SnipeIt::snipe.asset.months', $months, ['count' => $months])
            . (($until = Rows::date($asset['warranty_expires'] ?? null)) ? ' · ' . __('SnipeIt::snipe.asset.until', ['date' => $until]) : ''));

        return [
            'details' => [
                $fact('name', Rows::text($asset['name'] ?? null)),
                $fact('asset_tag', Rows::text($asset['asset_tag'] ?? null), ['mono' => true]),
                $fact('serial', Rows::text($asset['serial'] ?? null), ['mono' => true]),
                $fact('model', Rows::text(data_get($asset, 'model.name')), ['sub' => Rows::text($asset['model_number'] ?? null)]),
                $fact('category', Rows::text(data_get($asset, 'category.name'))),
                $fact('manufacturer', Rows::text(data_get($asset, 'manufacturer.name'))),
                $fact('company', Rows::text(data_get($asset, 'company.name'))),
                $fact('status', $this->status($asset)['name'], ['tone' => $this->status($asset)['tone']]),
                ...(($asset['byod'] ?? false) === true ? [$fact('byod', __('SnipeIt::snipe.yes_no.yes'))] : []),
            ],
            'holder' => [
                $fact('holder', $holder['name'] ?? null, $holder === null ? ['empty' => __('SnipeIt::snipe.asset.nobody')] : [
                    'link' => $holder['link'], 'inside' => $holder['inside'], 'sub' => implode(' · ', array_filter([$holder['kind'], $holder['sub']])),
                ]),
                $fact('location', Rows::text(data_get($asset, 'location.name'))),
                $fact('rtd_location', Rows::text(data_get($asset, 'rtd_location.name'))),
                $fact('last_checkout', Rows::date($asset['last_checkout'] ?? null)),
                $fact('expected_checkin', Rows::date($asset['expected_checkin'] ?? null)),
                $fact('counters', __('SnipeIt::snipe.asset.counters', [
                    'checkout' => (int) ($asset['checkout_counter'] ?? 0), 'checkin' => (int) ($asset['checkin_counter'] ?? 0),
                ])),
            ],
            'purchase' => [
                $fact('supplier', Rows::text(data_get($asset, 'supplier.name'))),
                $fact('order_number', Rows::text($asset['order_number'] ?? null), ['mono' => true]),
                $fact('purchase_date', Rows::date($asset['purchase_date'] ?? null)),
                $fact('age', $this->age($asset['purchase_date'] ?? null)),
                $fact('purchase_cost', Rows::text($asset['purchase_cost'] ?? null)),
                $fact('book_value', Rows::text($asset['book_value'] ?? null)),
                $fact('warranty', $warranty),
                $fact('eol', Rows::date($asset['asset_eol_date'] ?? null)),
            ],
            'dates' => [
                $fact('created', Rows::date($asset['created_at'] ?? null)),
                $fact('updated', Rows::date($asset['updated_at'] ?? null)),
                $fact('last_audit', Rows::date($asset['last_audit_date'] ?? null)),
                $fact('next_audit', Rows::date($asset['next_audit_date'] ?? null)),
            ],
        ];
    }

    /**
     * The custom fields of the asset's model, in Snipe-IT's order: a field whose name says it holds a secret is shown as
     * hidden.
     *
     * @return list<array{name: string, value: ?string, hidden: bool}>
     */
    private function custom(mixed $fields): array
    {
        if (! is_array($fields)) {
            return [];
        }
        $shown = [];
        foreach ($fields as $name => $field) {
            $name = Rows::text((string) $name) ?? (string) $name;
            $hidden = Names::secret($name) || Names::secret((string) data_get($field, 'field'));
            $shown[] = ['name' => $name, 'value' => $hidden ? null : Rows::text(is_array($field) ? ($field['value'] ?? null) : $field), 'hidden' => $hidden];
        }

        return $shown;
    }

    /** One entry of the history: what was done, to or from whom, when, by whom, what changed, the note. */
    private function event(array $entry, ?string $web): array
    {
        $action = Str::lower((string) Rows::text($entry['action_type'] ?? null));
        [$tone, $icon] = self::ACTIONS[$action] ?? ['', 'activity'];
        $target = Rows::text(data_get($entry, 'target.name'));
        $targetKind = Str::lower((string) data_get($entry, 'target.type'));
        $toward = match (true) {
            $target === null => null,
            $action === 'checkout' => __('SnipeIt::snipe.asset.to', ['name' => $target]),
            str_starts_with($action, 'checkin') => __('SnipeIt::snipe.asset.from', ['name' => $target]),
            default => $target,
        };

        return [
            'action' => $this->word('actions', $action) ?? (Rows::text($entry['action_type'] ?? null) ?? '—'),
            'tone' => $tone,
            'icon' => $icon,
            'toward' => $toward,
            'toward_kind' => $target !== null ? ($this->word('kinds', $targetKind) ?? ($targetKind ?: null)) : null,
            'at' => Rows::date($entry['action_date'] ?? null) ?? Rows::date($entry['created_at'] ?? null),
            'by' => Rows::text(data_get($entry, 'admin.name')),
            'changes' => $this->names->changes($entry['log_meta'] ?? null),
            'note' => self::paragraph($entry['note'] ?? null),
            'file' => Rows::text(data_get($entry, 'file.filename')),
        ];
    }

    /** How old the asset is, from the day it was bought, in the page's words ("2 tahun 3 bulan"). */
    private function age(mixed $purchased): ?string
    {
        $day = is_array($purchased) ? ($purchased['date'] ?? null) : null;
        if (! is_string($day) || $day === '') {
            return null;
        }
        try {
            return Carbon::parse($day)->locale(app()->getLocale())->diffForHumans(null, CarbonInterface::DIFF_ABSOLUTE, false, 2);
        } catch (Throwable) {
            return null;
        }
    }


    /** A longer text of Snipe-IT (a note) as plain text that keeps its lines. */
    public static function paragraph(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $text = html_entity_decode(strip_tags(html_entity_decode(str_ireplace(['<br>', '<br/>', '<br />'], "\n", $value), ENT_QUOTES | ENT_HTML5, 'UTF-8')), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = (string) preg_replace("/\n{3,}/", "\n\n", implode("\n", array_map('trim', preg_split('/\r\n|\r|\n/', $text) ?: [])));
        $text = trim($text);

        return $text === '' ? null : Str::limit($text, 2000);
    }

    private function word(string $group, string $key): ?string
    {
        $words = __('SnipeIt::snipe.' . $group);

        return is_array($words) && isset($words[$key]) && is_string($words[$key]) ? $words[$key] : null;
    }
}
