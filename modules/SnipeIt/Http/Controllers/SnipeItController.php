<?php

namespace Modules\SnipeIt\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\Language;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\SnipeIt\Services\Answers;
use Modules\SnipeIt\Services\Lists;
use Modules\SnipeIt\Services\Rows;
use Modules\SnipeIt\Services\SnipeItClient;
use Modules\SnipeIt\Services\SnipeItUnavailable;
use Modules\SnipeIt\Services\Summary;

/**
 * The menu "Snipe-IT": what is in Snipe-IT, read only and grouped (an overview, then a page per group of lists with a tab
 * per list). Every request this sends to Snipe-IT is a GET (SnipeItClient); nothing here changes anything there.
 */
class SnipeItController extends Controller
{
    /** How many rows a page of a list may have. */
    private const LIMITS = [10, 25, 50, 100];

    /** How many categories the overview names; the others are counted together. */
    private const CATEGORIES = 8;

    public function overview(Request $request, Summary $summary)
    {
        // "Refresh": Snipe-IT is asked again, then the page opens at its own address (a reload does not ask again).
        if ($request->boolean('fresh')) {
            $this->summary($summary, true);

            return redirect()->route('snipe-it.overview.index');
        }
        [$data, $error] = $this->summary($summary, false);

        $groups = [];
        foreach (Lists::GROUPS as $group => $lists) {
            $groups[$group] = [];
            foreach ($lists as $list) {
                $groups[$group][$list] = $data['totals'][$list] ?? null;
            }
        }
        $categories = $data['categories'] ?? [];

        return view('SnipeIt::overview', [
            'error' => $error,
            'groups' => $groups,
            'statuses' => $data['statuses'] ?? [],
            'categories' => array_slice($categories, 0, self::CATEGORIES),
            'otherCategories' => array_slice($categories, self::CATEGORIES),
            'version' => $data['version'] ?? null,
            'at' => $data['at'] ?? null,
            'web' => SnipeItClient::webUrl(),
        ]);
    }

    public function index(Request $request, Summary $summary, string $group)
    {
        $tab = $this->tab($request, $group);
        $list = Lists::get($tab);
        [$data, $error] = $this->summary($summary, false);

        $filters = [];
        foreach ($list['filters'] ?? [] as $filter) {
            $filters[$filter] = [
                'options' => $this->options($filter, $data),
                'chosen' => $this->chosen($request, $filter),
            ];
        }

        return view('SnipeIt::list', [
            'group' => $group,
            'tab' => $tab,
            'tabs' => collect(Lists::GROUPS[$group])->mapWithKeys(fn (string $key) => [$key => $data['totals'][$key] ?? null])->all(),
            'heads' => Lists::heads($tab),
            'order' => $list['order'] ?? null,
            'filters' => $filters,
            'search' => mb_substr(trim((string) (is_string($request->query('q')) ? $request->query('q') : '')), 0, 100),
            'error' => $error,
            'web' => SnipeItClient::webUrl(),
        ]);
    }

    public function list(Request $request, Answers $answers, string $group): JsonResponse
    {
        $tab = $this->tab($request, $group);
        $list = Lists::get($tab);
        $local = $list['local'] ?? false;
        $limit = in_array((int) $request->input('paginated'), self::LIMITS, true) ? (int) $request->input('paginated') : 25;
        $page = max(1, min((int) $request->input('page', 1), 100000));
        $search = mb_substr(trim(is_string($request->input('search')) ? $request->input('search') : ''), 0, 100);
        [$sort, $order] = $this->order($request, $list);

        // What Snipe-IT is asked. A list it gives whole is asked as it is (it would not take more), and cut here.
        $query = [];
        if (! $local) {
            $query = ['limit' => $limit, 'offset' => ($page - 1) * $limit];
            if ($search !== '') {
                $query['search'] = $search;
            }
            if ($sort !== null) {
                $query['sort'] = $list['columns'][$sort]['sort'];
                $query['order'] = $order;
            }
        }
        foreach ($list['filters'] ?? [] as $filter) {
            $chosen = $this->chosen($request, $filter);
            if ($chosen !== null) {
                $query[['category' => 'category_id', 'status' => 'status_id', 'action' => 'action_type'][$filter]] = $chosen;
            }
        }

        if ($request->boolean('fresh')) {
            SnipeItClient::retry();
        }
        try {
            $answer = $answers->get($list['endpoint'], $query, $request->boolean('fresh'));
        } catch (SnipeItUnavailable $unavailable) {
            return response()->json(['message' => $unavailable->describe(), 'reason' => $unavailable->reason], 503);
        }

        $rows = array_values(array_filter(is_array($answer['rows'] ?? null) ? $answer['rows'] : [], 'is_array'));
        $total = is_numeric($answer['total'] ?? null) ? (int) $answer['total'] : count($rows);
        if ($local) {
            $rows = Rows::matching($list, $rows, $search);
            $total = count($rows);
            $rows = array_slice($rows, ($page - 1) * $limit, $limit);
        }

        $web = SnipeItClient::webUrl();
        $from = ($page - 1) * $limit;

        return response()->json([
            'data' => array_map(fn (array $row) => Rows::show($list, $row, $web), $rows),
            'meta' => [
                'current_page' => $page,
                'per_page' => $limit,
                'total' => $total,
                'last_page' => max(1, (int) ceil($total / $limit)),
                'from' => $rows === [] ? null : $from + 1,
                'to' => $rows === [] ? null : $from + count($rows),
                'total_text' => Language::number($total),
            ],
            'sort' => ['key' => $sort, 'order' => $order],
        ]);
    }

    /** The list asked for (?tab=), one of the page's; else its first. */
    private function tab(Request $request, string $group): string
    {
        $lists = Lists::GROUPS[$group];
        $tab = $request->input('tab');

        return is_string($tab) && in_array($tab, $lists, true) ? $tab : $lists[0];
    }

    /**
     * The column the list is ordered by and which way: the one asked for when a click on it may order the list, else the
     * list's own order. Null when the list has none (Snipe-IT's: the newest first).
     *
     * @return array{0: ?string, 1: string}
     */
    private function order(Request $request, array $list): array
    {
        $asked = $request->input('sort');
        $way = in_array($request->input('order'), ['asc', 'desc'], true) ? $request->input('order') : null;
        if (is_string($asked) && isset($list['columns'][$asked]['sort'])) {
            return [$asked, $way ?? 'asc'];
        }
        if (isset($list['order'])) {
            return [$list['order'][0], $way ?? $list['order'][1]];
        }

        return [null, 'desc'];
    }

    /** What a filter is set to (?category=15, ?status=4, ?action=checkout), or null. */
    private function chosen(Request $request, string $filter): int|string|null
    {
        $value = $request->input($filter);
        if ($filter === 'action') {
            return is_string($value) && in_array($value, Lists::ACTIONS, true) ? $value : null;
        }

        return is_scalar($value) && ctype_digit((string) $value) && (int) $value > 0 ? (int) $value : null;
    }

    /**
     * The choices of a filter: [value => name]; for the categories and the statuses of the assets with how many there are.
     *
     * @return array<int|string, string>
     */
    private function options(string $filter, array $data): array
    {
        $count = fn (array $item) => $item['name'] . ' (' . Language::number($item['assets']) . ')';

        return match ($filter) {
            'category' => collect($data['categories'] ?? [])->mapWithKeys(fn (array $item) => [$item['id'] => $count($item)])->all(),
            'status' => collect($data['statuses'] ?? [])->mapWithKeys(fn (array $item) => [$item['id'] => $count($item)])->all(),
            'action' => collect(Lists::ACTIONS)->mapWithKeys(fn (string $action) => [$action => __('SnipeIt::snipe.filters.actions.' . str_replace(' ', '_', $action))])->all(),
            default => [],
        };
    }

    /**
     * The overview of Snipe-IT, or why there is none.
     *
     * @return array{0: array<string, mixed>, 1: ?string}
     */
    private function summary(Summary $summary, bool $fresh): array
    {
        if ($fresh) {
            SnipeItClient::retry();
        }
        try {
            return [$summary->get($fresh), null];
        } catch (SnipeItUnavailable $unavailable) {
            return [[], $unavailable->describe()];
        }
    }
}
