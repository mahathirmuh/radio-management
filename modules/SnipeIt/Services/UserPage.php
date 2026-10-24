<?php

namespace Modules\SnipeIt\Services;

use Illuminate\Support\Str;

/**
 * The detail of a user as the page shows it (SnipeIt::user), from what UserDetail asked Snipe-IT: who they are at work, what
 * is checked out to them, and the history. Only work data leaves the server: never the phone, the address, the notes, the
 * picture or the permissions of a person, also not as a change in the history; a licence by its name, never by its key.
 */
final class UserPage
{
    /** The fields of a user that are private: not shown, also not as a change in the history. */
    private const PRIVATE = '/^(phone|mobile|address|city|state|country|zip|website|notes|avatar|permissions|two_factor\w*|remember_token|password)$/i';

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
     * @param  bool  $users  whoever looks may open the detail of a user here (the manager's, a user's in the history)
     * @return array<string, mixed>
     */
    public function present(array $data, ?string $web, bool $users): array
    {
        $user = $data['user'];
        $id = (int) $user['id'];
        $name = Rows::text($user['name'] ?? null) ?? trim(Rows::text($user['first_name'] ?? null) . ' ' . Rows::text($user['last_name'] ?? null));
        $active = filter_var($user['activated'] ?? null, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        $count = fn (string $key) => is_numeric($user[$key] ?? null) ? (int) $user[$key] : null;

        return [
            'id' => $id,
            'name' => $name !== '' ? $name : '#' . $id,
            'initials' => self::initials($name),
            'username' => Rows::text($user['username'] ?? null),
            'employee_num' => Rows::text($user['employee_num'] ?? null),
            'jobtitle' => Rows::text($user['jobtitle'] ?? null),
            'department' => Rows::text(data_get($user, 'department.name')),
            'active' => $active,
            'vip' => ($user['vip'] ?? false) === true,
            'deleted' => Rows::date($user['deleted_at'] ?? null),
            'facts' => $this->facts($user, $users),
            // As many as the page lists (Snipe-IT gives them whole); the consumables, which it does not list, by its count.
            'counts' => [
                'assets' => count($data['assets']),
                'licenses' => count($data['licenses']),
                'accessories' => count($data['accessories']),
                'consumables' => $count('consumables_count') ?? 0,
            ],
            'assets' => array_map(fn (array $row) => $this->asset($row), $data['assets']),
            'licenses' => array_map(fn (array $row) => [
                'name' => Rows::text($row['name'] ?? null) ?? '—',
                'category' => Rows::text(data_get($row, 'category.name')),
                'manufacturer' => Rows::text(data_get($row, 'manufacturer.name')),
                'expires' => Rows::date($row['expiration_date'] ?? null),
                'link' => $web !== null && is_numeric($row['id'] ?? null) ? $web . '/licenses/' . (int) $row['id'] : null,
            ], $data['licenses']),
            'accessories' => array_map(fn (array $row) => [
                'name' => Rows::text($row['name'] ?? null) ?? '—',
                'category' => Rows::text(data_get($row, 'category.name')),
                'link' => $web !== null && is_numeric($row['id'] ?? null) ? $web . '/accessories/' . (int) $row['id'] : null,
            ], $data['accessories']),
            'history' => $this->history($data, $id),
            'web' => $web !== null ? $web . '/users/' . $id : null,
            'at' => $data['at'],
        ];
    }

    /**
     * The facts of the boxes of the page (SnipeIt::partials.facts).
     *
     * @return array<string, list<array<string, mixed>>>
     */
    private function facts(array $user, bool $users): array
    {
        $fact = fn (string $key, ?string $value, array $more = []) => ['label' => __('SnipeIt::snipe.user.facts.' . $key), 'value' => $value] + $more;
        $managerId = data_get($user, 'manager.id');
        $active = filter_var($user['activated'] ?? null, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        $yes = fn (mixed $value) => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === null ? null
            : __('SnipeIt::snipe.yes_no.' . (filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'yes' : 'no'));

        return [
            'profile' => [
                $fact('name', Rows::text($user['name'] ?? null)),
                $fact('username', Rows::text($user['username'] ?? null), ['mono' => true]),
                $fact('email', Rows::text($user['email'] ?? null)),
                $fact('employee_num', Rows::text($user['employee_num'] ?? null), ['mono' => true]),
                $fact('jobtitle', Rows::text($user['jobtitle'] ?? null)),
                $fact('manager', Rows::text(data_get($user, 'manager.name')), [
                    'link' => $users && is_numeric($managerId) ? route('snipe-it.users.show', (int) $managerId) : null, 'inside' => true,
                ]),
                ...(($user['vip'] ?? false) === true ? [$fact('vip', __('SnipeIt::snipe.yes_no.yes'))] : []),
            ],
            'organization' => [
                $fact('company', Rows::text(data_get($user, 'company.name'))),
                $fact('department', Rows::text(data_get($user, 'department.name'))),
                $fact('location', Rows::text(data_get($user, 'location.name'))),
                $fact('groups', Rows::text(is_array($user['groups'] ?? null) ? ($user['groups']['rows'] ?? $user['groups']) : null)),
                $fact('active', $active === null ? null : __('SnipeIt::snipe.active.' . ($active ? 'yes' : 'no')), $active === null ? [] : ['tone' => $active ? 'green' : 'gray']),
                $fact('ldap', $yes($user['ldap_import'] ?? null)),
                ...(($user['remote'] ?? false) === true ? [$fact('remote', __('SnipeIt::snipe.yes_no.yes'))] : []),
            ],
            'dates' => [
                $fact('created', Rows::date($user['created_at'] ?? null)),
                $fact('updated', Rows::date($user['updated_at'] ?? null)),
                $fact('last_login', Rows::date($user['last_login'] ?? null), ['empty' => __('SnipeIt::snipe.user.never')]),
                ...(($start = Rows::date($user['start_date'] ?? null)) ? [$fact('start_date', $start)] : []),
                ...(($end = Rows::date($user['end_date'] ?? null)) ? [$fact('end_date', $end)] : []),
            ],
        ];
    }

    /** An asset checked out to the user: its picture, tag, name, model, status, since when; its detail is here. */
    private function asset(array $row): array
    {
        $category = Rows::text(data_get($row, 'category.name'));
        $meta = Str::lower((string) Rows::text(data_get($row, 'status_label.status_meta')));

        return [
            'tag' => Rows::text($row['asset_tag'] ?? null) ?? '#' . ($row['id'] ?? ''),
            'name' => Rows::text($row['name'] ?? null),
            'model' => Rows::text(data_get($row, 'model.name')),
            'category' => $category,
            'serial' => Rows::text($row['serial'] ?? null),
            'status' => ['name' => Rows::text(data_get($row, 'status_label.name')), 'tone' => self::STATUS_TONES[$meta] ?? 'gray'],
            'since' => Rows::date($row['last_checkout'] ?? null),
            'image' => Rows::image($row['image'] ?? null),
            'icon' => Rows::icon($category),
            'link' => is_numeric($row['id'] ?? null) ? route('snipe-it.assets.show', (int) $row['id']) : null,
        ];
    }

    /**
     * The history, newest first: what was checked out to or in from the user, and what was done to the user itself (an
     * entry Snipe-IT gives for both is shown once).
     *
     * @return array{total: int, items: list<array<string, mixed>>}
     */
    private function history(array $data, int $id): array
    {
        $entries = [];
        foreach ([...$data['history']['rows'], ...$data['changes']['rows']] as $entry) {
            $entries[(string) ($entry['id'] ?? count($entries) . '-')] = $entry;
        }
        $moment = fn (array $entry) => (string) (data_get($entry, 'action_date.datetime') ?? data_get($entry, 'created_at.datetime') ?? '');
        uasort($entries, fn (array $a, array $b) => strcmp($moment($b), $moment($a)));

        return [
            'total' => $data['history']['total'] + $data['changes']['total'],
            'items' => array_values(array_map(fn (array $entry) => $this->event($entry, $id), $entries)),
        ];
    }

    /** One entry of the history: what was done, with which item (an asset's detail is here), when, by whom, what changed. */
    private function event(array $entry, int $id): array
    {
        $action = Str::lower((string) Rows::text($entry['action_type'] ?? null));
        [$tone, $icon] = self::ACTIONS[$action] ?? ['', 'activity'];
        $kind = Str::lower((string) data_get($entry, 'item.type'));
        $itemId = data_get($entry, 'item.id');
        // An entry about the user itself names no item: it is this page's.
        $self = $kind === 'user' && is_numeric($itemId) && (int) $itemId === $id;
        $meta = is_array($entry['log_meta'] ?? null) ? array_filter($entry['log_meta'], fn ($field) => ! preg_match(self::PRIVATE, (string) $field), ARRAY_FILTER_USE_KEY) : null;

        return [
            'action' => $this->word('actions', $action) ?? (Rows::text($entry['action_type'] ?? null) ?? '—'),
            'tone' => $tone,
            'icon' => $icon,
            'item' => $self ? null : Rows::text(data_get($entry, 'item.name')),
            'item_kind' => $self ? null : ($this->word('kinds', $kind) ?? ($kind ?: null)),
            'link' => ! $self && $kind === 'asset' && is_numeric($itemId) ? route('snipe-it.assets.show', (int) $itemId) : null,
            'at' => Rows::date($entry['action_date'] ?? null) ?? Rows::date($entry['created_at'] ?? null),
            'by' => Rows::text(data_get($entry, 'admin.name')),
            'changes' => $this->names->changes($meta),
            'note' => AssetPage::paragraph($entry['note'] ?? null),
        ];
    }

    /** The initials of a name ("Ann O'Neil": AO), for the circle in place of a picture of the person. */
    private static function initials(?string $name): string
    {
        $words = array_values(array_filter(preg_split('/\s+/u', trim((string) $name)) ?: [], fn (string $word) => preg_match('/^\p{L}/u', $word) === 1));
        if ($words === []) {
            return '?';
        }
        $first = mb_strtoupper(mb_substr($words[0], 0, 1));

        return count($words) === 1 ? $first : $first . mb_strtoupper(mb_substr(end($words), 0, 1));
    }

    private function word(string $group, string $key): ?string
    {
        $words = __('SnipeIt::snipe.' . $group);

        return is_array($words) && isset($words[$key]) && is_string($words[$key]) ? $words[$key] : null;
    }
}
