<?php

namespace Modules\UserManagement\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

/**
 * The employee directory on the HR SQL Server, read only (config/employee_directory.php).
 *
 * It is there so the address of an account is chosen and not typed: the address has to be the one Microsoft knows the
 * person by. Three columns are read (staff number, name, work address) and nothing is written. Only an address in a
 * domain that can sign in here is offered: the column also holds private addresses and notes such as "no need".
 *
 * It lives on a server this application does not own. When it cannot be reached the search answers with nothing and
 * says so in the log: a user can still be added by typing.
 */
class EmployeeDirectory
{
    /** Whether there is a directory to search: switched on, and its connection has a host and a database. */
    public function isAvailable(): bool
    {
        $connection = 'database.connections.' . $this->connection();

        return (bool) config('employee_directory.enabled')
            && filled(config($connection . '.host'))
            && filled(config($connection . '.database'));
    }

    /** Fewer letters than this are not searched for. */
    public function minimum(): int
    {
        return max(1, (int) config('employee_directory.minimum_query_length', 2));
    }

    /**
     * Colleagues whose name or work address holds $term. Never throws.
     *
     * @return array<int, array{employee_id: string, name: string, email: string}>
     */
    public function search(string $term): array
    {
        $term = trim($term);

        if (! $this->isAvailable() || mb_strlen($term) < $this->minimum()) {
            return [];
        }

        try {
            return $this->distinct($this->query($term));
        } catch (Throwable $e) {
            // A warning, not an error: nothing of this application is broken when that server does not answer.
            Log::warning('The employee directory could not be searched: ' . $e->getMessage());

            return [];
        }
    }

    private function connection(): string
    {
        return (string) config('employee_directory.connection', 'hr');
    }

    /** @return array<int, object> */
    private function query(string $term): array
    {
        $id = $this->identifier('columns.id');
        $name = $this->identifier('columns.name');
        $email = $this->identifier('columns.email');
        $like = '%' . $this->escapeLike($term) . '%';

        $query = DB::connection($this->connection())
            ->table($this->identifier('table'))
            ->selectRaw("{$id} AS employee_id, {$name} AS full_name, {$email} AS email")
            // By the name or by the address: the name is what is known, the address is what is looked for.
            ->whereRaw("({$name} LIKE ? ESCAPE '\\' OR {$email} LIKE ? ESCAPE '\\')", [$like, $like])
            // What is in the column but is no address ("no need" and the like).
            ->whereRaw("{$email} LIKE '%@%.%'");

        $domains = array_filter((array) config('employee_directory.sign_in_domains', []));
        if ($domains !== []) {
            $query->where(function ($inner) use ($domains, $email) {
                foreach ($domains as $domain) {
                    $inner->orWhereRaw("LOWER({$email}) LIKE ? ESCAPE '\\'", ['%@' . $this->escapeLike(mb_strtolower((string) $domain))]);
                }
            });
        }

        return $query
            ->orderBy($name)
            ->limit(max(1, (int) config('employee_directory.limit', 8)))
            ->get()
            ->all();
    }

    /**
     * Rows that repeat are dropped; rows that only share an address are kept. An address that sits on the records of
     * two different people is a mistake in the HR data: both are shown, with their staff numbers, for somebody to
     * choose, and one name is not quietly put on the address of another.
     *
     * @param array<int, object> $rows
     * @return array<int, array{employee_id: string, name: string, email: string}>
     */
    private function distinct(array $rows): array
    {
        $matches = [];

        foreach ($rows as $row) {
            $name = trim((string) ($row->full_name ?? ''));
            $email = trim((string) ($row->email ?? ''));
            $key = mb_strtolower($name . '|' . $email);

            if ($email === '' || isset($matches[$key])) {
                continue;
            }

            $matches[$key] = [
                'employee_id' => trim((string) ($row->employee_id ?? '')),
                'name' => $name,
                'email' => $email,
            ];
        }

        return array_values($matches);
    }

    /**
     * A table or column name from the configuration. They are not typed by a user, but they do come from the
     * environment and end up in the text of a query: anything that is not a plain name is refused.
     */
    private function identifier(string $key): string
    {
        $value = (string) config('employee_directory.' . $key);

        if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value)) {
            throw new InvalidArgumentException("employee_directory.{$key} must be a plain table or column name.");
        }

        return $value;
    }

    /** What SQL Server reads as a wildcard in LIKE is searched for as the letter it is ("%" does not find everyone). */
    private function escapeLike(string $term): string
    {
        return str_replace(['\\', '%', '_', '['], ['\\\\', '\\%', '\\_', '\\['], $term);
    }
}
