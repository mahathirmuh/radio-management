<?php

namespace App\Http\Controllers;

use App\Models\UserManagement\User;
use App\Services\Language;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

/**
 * The language switch (the account menu, the sidebar on a phone, the sign-in page). The choice is kept with the account
 * when someone is signed in and the column is there, and in the browser in any case; then back to the page it was
 * made on (another address goes to the home page).
 */
class LanguageController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $code = Language::valid($request->input('locale'));
        abort_if($code === null, 422);

        $user = $request->user();
        if ($user !== null && User::localeReady()) {
            // Written past the model: a choice of language is no change of the account (updated_at stays, no entry in
            // the audit log).
            User::query()->whereKey($user->getKey())->toBase()->update(['locale' => $code]);
        }
        Cookie::queue(Language::COOKIE, $code, Language::COOKIE_MINUTES);

        $back = url()->previous();

        return redirect()->to(parse_url($back, PHP_URL_HOST) === $request->getHost() ? $back : url('/'));
    }
}
