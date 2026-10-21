<?php

namespace Modules\Dashboard\Services;

/**
 * A pie drawn as SVG paths, without a chart library: one slice per part that has a count, clockwise from the top, in the
 * order given. A part too small to be seen is drawn $minimum degrees wide and the others share what is left in proportion;
 * its own number stays in the list beside the pie and in its tooltip.
 */
final class Pie
{
    /**
     * @param  array<string, int>  $counts  a count per key, in the order of the slices
     * @param  float  $size  the width and height of the SVG (its viewBox)
     * @return list<array{key: string, count: int, share: float, degrees: float, full: bool, path: ?string, label_x: float, label_y: float}>
     *     path: null for the one slice that is the whole circle (full); label_x/y: the middle of the slice, for its number
     */
    public static function slices(array $counts, float $size, float $minimum = 3.0): array
    {
        $counts = array_filter(array_map('intval', $counts), fn (int $count) => $count > 0);
        $total = array_sum($counts);
        if ($total === 0) {
            return [];
        }

        $raw = array_map(fn (int $count) => $count / $total * 360, $counts);
        $small = array_filter($raw, fn (float $degrees) => $degrees < $minimum);
        $bigSum = array_sum($raw) - array_sum($small);
        $left = 360 - count($small) * $minimum;
        $degrees = array_map(fn (float $d) => $d < $minimum ? $minimum : ($bigSum > 0 ? $d / $bigSum * $left : $d), $raw);

        $centre = $size / 2;
        $radius = $centre - 1;
        $point = fn (float $angle, float $distance) => [
            round($centre + $distance * cos(deg2rad($angle)), 2),
            round($centre + $distance * sin(deg2rad($angle)), 2),
        ];

        $slices = [];
        $start = -90.0;
        foreach ($counts as $key => $count) {
            $width = $degrees[$key];
            $end = $start + $width;
            $full = count($counts) === 1;
            [$x1, $y1] = $point($start, $radius);
            [$x2, $y2] = $point($end, $radius);
            [$labelX, $labelY] = $full ? [round($centre, 2), round($centre, 2)] : $point($start + $width / 2, $radius * 0.6);
            $slices[] = [
                'key' => (string) $key,
                'count' => $count,
                'share' => $count / $total,
                'degrees' => round($width, 4),
                'full' => $full,
                'path' => $full ? null : sprintf('M%s %s L%s %s A%s %s 0 %d 1 %s %s Z',
                    round($centre, 2), round($centre, 2), $x1, $y1, round($radius, 2), round($radius, 2), $width > 180 ? 1 : 0, $x2, $y2),
                'label_x' => $labelX,
                'label_y' => $labelY,
            ];
            $start = $end;
        }

        return $slices;
    }
}
