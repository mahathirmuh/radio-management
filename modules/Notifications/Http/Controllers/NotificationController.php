<?php

namespace Modules\Notifications\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Notification\UserNotification;
use App\Services\Notifications\NotificationSweep;
use App\Services\Notifications\Notifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The notifications of whoever is signed in: the bell asks for the last few (and so makes the notices that are due, see
 * NotificationSweep), opening one marks it read, and "See all" lists them. Only ever the person's own.
 */
class NotificationController extends Controller
{
    /** How many the bell shows. */
    private const BELL = 8;

    private const PER_PAGE = 25;

    /** The tabs of the page besides "Semua" (all): the unread ones, and each category (UserNotification::CATEGORIES). */
    private const SHOWS = ['unread', 'radio', 'documents', 'system'];

    public function index(Request $request)
    {
        $user = $request->user();
        $ready = UserNotification::ready();
        $show = in_array($request->query('show'), self::SHOWS, true) ? $request->query('show') : 'all';

        // How many each tab holds, from one count per kind.
        $byKind = $ready
            ? UserNotification::query()->of($user)
                ->selectRaw('kind, count(*) as total, sum(case when read_at is null then 1 else 0 end) as unread')
                ->groupBy('kind')->get()
            : collect();
        $tabs = ['all' => (int) $byKind->sum('total'), 'unread' => (int) $byKind->sum('unread')];
        foreach (array_slice(self::SHOWS, 1) as $category) {
            $tabs[$category] = (int) $byKind->whereIn('kind', UserNotification::kindsOf($category))->sum('total');
        }

        return view('Notifications::index', [
            'pageTitle' => 'Notifikasi',
            'menuItems' => [
                ['url' => '/', 'label' => 'Home', 'active' => false],
                ['url' => '/notifications', 'label' => 'Notifikasi', 'active' => true],
            ],
            'ready' => $ready,
            'show' => $show,
            'tabs' => $tabs,
            'unread' => $tabs['unread'],
            'notifications' => $ready
                ? UserNotification::query()->of($user)
                    ->when($show === 'unread', fn ($query) => $query->unread())
                    ->when(! in_array($show, ['all', 'unread'], true), fn ($query) => $query->whereIn('kind', UserNotification::kindsOf($show)))
                    ->latest('id')->paginate(self::PER_PAGE)->withQueryString()
                : null,
        ]);
    }

    /** What the bell shows: how many are unread, and the last few. */
    public function summary(Request $request, NotificationSweep $sweep): JsonResponse
    {
        $user = $request->user();
        if (! UserNotification::ready()) {
            return response()->json(['ready' => false, 'unread' => 0, 'items' => []]);
        }
        $sweep->forUser($user);

        return response()->json([
            'ready' => true,
            'unread' => UserNotification::query()->of($user)->unread()->count(),
            'items' => UserNotification::query()->of($user)->latest('id')->limit(self::BELL)->get()
                ->map(fn (UserNotification $notification) => self::item($notification))->values(),
        ]);
    }

    /** Marks it read and goes where it points (a page of this application; this list when it points nowhere). */
    public function open(Request $request, int $notification): RedirectResponse
    {
        abort_unless(UserNotification::ready(), 404);
        $found = UserNotification::query()->of($request->user())->findOrFail($notification);
        if ($found->read_at === null) {
            $found->forceFill(['read_at' => now()])->save();
        }

        return redirect()->to(Notifier::path($found->url) ?? route('notifications.index'));
    }

    public function readAll(Request $request): JsonResponse|RedirectResponse
    {
        if (UserNotification::ready()) {
            UserNotification::query()->of($request->user())->unread()->update(['read_at' => now(), 'updated_at' => now()]);
        }

        return $request->expectsJson()
            ? response()->json(['unread' => 0])
            : redirect()->route('notifications.index', self::tab($request));
    }

    /** "Tandai dibaca" of a row of the page: marks it read without opening it. Only ever one of the person's own. */
    public function read(Request $request, int $notification): JsonResponse|RedirectResponse
    {
        abort_unless(UserNotification::ready(), 404);
        $found = UserNotification::query()->of($request->user())->findOrFail($notification);
        if ($found->read_at === null) {
            $found->forceFill(['read_at' => now()])->save();
        }

        return $request->expectsJson()
            ? response()->json(['unread' => UserNotification::query()->of($request->user())->unread()->count()])
            : redirect()->route('notifications.index', self::tab($request));
    }

    /** @return array{show?: string} the tab of the page a form was sent from, to go back to it */
    private static function tab(Request $request): array
    {
        return in_array($request->input('show'), self::SHOWS, true) ? ['show' => $request->input('show')] : [];
    }

    /** One notification as the bell shows it (texts as they are: the page puts them in as text, never as markup). */
    private static function item(UserNotification $notification): array
    {
        return [
            'id' => $notification->id,
            'kind' => $notification->kind,
            'icon' => $notification->icon(),
            'title' => $notification->title,
            'body' => $notification->body,
            'read' => $notification->read_at !== null,
            // In the language of the page ("5 menit yang lalu", "5 minutes ago").
            'when' => $notification->created_at?->diffForHumans(),
            'at' => $notification->created_at?->format('d-m-Y H:i'),
            'open' => route('notifications.open', $notification->id),
        ];
    }
}
