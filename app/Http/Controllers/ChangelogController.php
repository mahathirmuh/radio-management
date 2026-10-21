<?php

namespace App\Http\Controllers;

use App\Services\Changelog;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

/**
 * What changed in each version, for the dialog that the version in the footer opens (main/footer): the versions newest
 * first, the one this server runs marked, the notes in the language of the page.
 */
class ChangelogController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $running = (string) config('app.version');
        $releases = array_map(fn (array $release) => $release + [
            'date_label' => $release['date'] === null ? null : Carbon::parse($release['date'])->translatedFormat('j M Y'),
            'current' => $release['version'] === $running,
        ], Changelog::ofApp()->releasesIn(app()->getLocale()));

        return response()->json(['version' => $running, 'releases' => $releases]);
    }
}
