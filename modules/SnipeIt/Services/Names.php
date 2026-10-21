<?php

namespace Modules\SnipeIt\Services;

use Illuminate\Support\Str;
use Throwable;

/**
 * What a change in Snipe-IT's activity log is about, in words: Snipe-IT writes the fields as it stores them ("status_id",
 * "_snipeit_radio_id_8", "Current Location") and the values as ids ("4") or "[id: 26] MTI". Here a field gets its name in
 * the language of the page (SnipeIt::snipe.fields), a custom field the name Snipe-IT gives it, and an id the name of what it
 * stands for (a status, a location, a model...), from Snipe-IT's own lists, kept like every answer.
 *
 * A password is never shown, whatever Snipe-IT writes for it.
 */
final class Names
{
    /** The lists ids are looked up in: field => endpoint. */
    private const LOOKUPS = [
        'status_id' => 'statuslabels',
        'location_id' => 'locations',
        'rtd_location_id' => 'locations',
        'model_id' => 'models',
        'supplier_id' => 'suppliers',
        'company_id' => 'companies',
        'department_id' => 'departments',
    ];

    /** Fields whose value is a yes or a no (1 or 0). */
    private const YES_NO = ['activated', 'requestable', 'byod', 'archived', 'ldap_import', 'vip', 'remote'];

    /** A field whose name says it holds a secret: its value is never shown. */
    private const SECRET = '/pass(word)?|sandi|secret|rahasia|token|\bpin\b|bitlocker|recovery|product.?key|license.?key|kunci/i';

    /** @var array<string, array<int, string>> endpoint => [id => name], as far as asked */
    private array $maps = [];

    /** @var array<string, string>|null custom field column => its name */
    private ?array $customFields = null;

    public function __construct(private readonly Answers $answers)
    {
    }

    /**
     * The changes of an entry of the activity log, one per field, in words.
     *
     * @return list<array{field: string, old: ?string, new: ?string}>
     */
    public function changes(mixed $meta): array
    {
        if (! is_array($meta)) {
            return [];
        }
        $changes = [];
        foreach ($meta as $field => $change) {
            $field = (string) $field;
            $old = is_array($change) ? ($change['old'] ?? null) : null;
            $new = is_array($change) ? ($change['new'] ?? null) : $change;
            $changes[] = [
                'field' => $this->field($field),
                'old' => $this->value($field, $old),
                'new' => $this->value($field, $new),
            ];
        }

        return $changes;
    }

    /** The changes on one line: "Status: Ready to Deploy → Deployed · Lokasi: Gudang". */
    public function line(mixed $meta): ?string
    {
        $parts = array_map(fn (array $change) => $change['field'] . ': ' . ($change['old'] !== null ? $change['old'] . ' → ' : '') . ($change['new'] ?? '—'),
            $this->changes($meta));

        return $parts === [] ? null : Str::limit(implode(' · ', $parts), 400);
    }

    /** The name of a field: the page's word for it, a custom field's name in Snipe-IT, else as Snipe-IT writes it. */
    public function field(string $field): string
    {
        $words = __('SnipeIt::snipe.fields');
        $key = Str::snake(Str::lower(trim($field)));
        if (is_array($words) && isset($words[$key]) && is_string($words[$key])) {
            return $words[$key];
        }
        if (str_starts_with($field, '_snipeit_')) {
            return $this->customFields()[$field] ?? Str::ucfirst(str_replace('_', ' ', (string) preg_replace('/^_snipeit_(.+)_\d+$/', '$1', $field)));
        }

        return (string) Rows::text($field);
    }

    /** Whether a field (by its name or its column) holds a secret, whose value is not shown. */
    public static function secret(string $field): bool
    {
        return (bool) preg_match(self::SECRET, $field);
    }

    /** A value of a change in words: a password hidden, a yes or a no, an id as the name of its thing, "[id: 26] MTI" as "MTI". */
    private function value(string $field, mixed $value): ?string
    {
        if (self::secret($field) || self::secret($this->field($field))) {
            return $value === null || $value === '' ? null : '••••••';
        }
        $text = Rows::text($value);
        if ($text === null) {
            return null;
        }
        $key = Str::snake(Str::lower(trim($field)));
        if (in_array($key, self::YES_NO, true) && in_array($text, ['0', '1', 'true', 'false'], true)) {
            return __('SnipeIt::snipe.yes_no.' . (in_array($text, ['1', 'true'], true) ? 'yes' : 'no'));
        }
        if (isset(self::LOOKUPS[$key]) && ctype_digit($text)) {
            return $this->map(self::LOOKUPS[$key])[(int) $text] ?? '#' . $text;
        }

        return trim((string) preg_replace('/^\[id: \d+\]\s*/', '', $text)) ?: null;
    }

    /** @return array<int, string> id => name of an endpoint's rows; empty when Snipe-IT gave none */
    private function map(string $endpoint): array
    {
        if (! isset($this->maps[$endpoint])) {
            $this->maps[$endpoint] = [];
            try {
                foreach ($this->answers->get($endpoint, ['limit' => 500])['rows'] ?? [] as $row) {
                    if (is_array($row) && is_numeric($row['id'] ?? null)) {
                        $this->maps[$endpoint][(int) $row['id']] = Rows::text($row['name'] ?? null) ?? '#' . $row['id'];
                    }
                }
            } catch (Throwable) {
                // Without the list, the id itself.
            }
        }

        return $this->maps[$endpoint];
    }

    /** @return array<string, string> */
    private function customFields(): array
    {
        if ($this->customFields === null) {
            $this->customFields = [];
            try {
                foreach ($this->answers->get('fields', [])['rows'] ?? [] as $row) {
                    if (is_array($row) && is_string($row['db_column_name'] ?? null)) {
                        $this->customFields[$row['db_column_name']] = Rows::text($row['name'] ?? null) ?? $row['db_column_name'];
                    }
                }
            } catch (Throwable) {
                // Without the list, the column made readable.
            }
        }

        return $this->customFields;
    }
}
