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

    public function index(Request $request)
    {
        $user = $request->user();
        $ready = UserNotification::ready();
        $unreadOnly = $request->query('show') === 'unread';

        return view('Notifications::index', [
            'pageTitle' => 'Notifikasi',
            'menuItems' => [
                ['url' => '/', 'label' => 'Home', 'active' => false],
                ['url' => '/notifications', 'label' => 'Notifikasi', 'active' => true],
            ],
            'ready' => $ready,
            'unreadOnly' => $unreadOnly,
            'unread' => $ready ? UserNotification::query()->of($user)->unread()->count() : 0,
            'notifications' => $ready
                ? UserNotification::query()->of($user)->when($unreadOnly, fn ($query) => $query->unread())
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
            : redirect()->route('notifications.index', array_filter(['show' => $request->input('show')]));
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
            'when' => $notification->created_at?->locale('id')->diffForHumans(),
            'at' => $notification->created_at?->format('d-m-Y H:i'),
            'open' => route('notifications.open', $notification->id),
        ];
    }
}
