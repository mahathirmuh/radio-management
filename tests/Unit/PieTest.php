<?php

namespace Tests\Unit;

use Modules\Dashboard\Services\Pie;
use PHPUnit\Framework\TestCase;

/**
 * The slices of the dashboard's pie: clockwise from the top in the order given, a whole turn between them, a part too
 * small to see drawn at the minimum width.
 */
class PieTest extends TestCase
{
    public function test_the_slices_go_round_once_from_the_top_in_the_order_given(): void
    {
        $slices = Pie::slices(['a' => 3, 'b' => 1], 100);

        $this->assertSame(['a', 'b'], array_column($slices, 'key'));
        $this->assertEqualsWithDelta(360, array_sum(array_column($slices, 'degrees')), 0.001);
        $this->assertSame([270.0, 90.0], array_column($slices, 'degrees'));
        $this->assertSame([0.75, 0.25], array_column($slices, 'share'));
        // The first one starts at the top (12 o'clock), and the larger than half takes the long way round.
        $this->assertStringStartsWith('M50 50 L50 1 A49 49 0 1 1 ', $slices[0]['path']);
        $this->assertStringStartsWith('M50 50 L', $slices[1]['path']);
        $this->assertStringContainsString(' A49 49 0 0 1 50 1 Z', $slices[1]['path']);
        // The number of a slice sits on its middle line, 60% out from the centre: the first one's middle is at half past
        // four, the second one's at half past ten.
        $this->assertEqualsWithDelta([50 + 0.6 * 49 * cos(deg2rad(45)), 50 + 0.6 * 49 * sin(deg2rad(45))], [$slices[0]['label_x'], $slices[0]['label_y']], 0.01);
        $this->assertEqualsWithDelta([50 + 0.6 * 49 * cos(deg2rad(225)), 50 + 0.6 * 49 * sin(deg2rad(225))], [$slices[1]['label_x'], $slices[1]['label_y']], 0.01);
    }

    public function test_a_part_too_small_to_see_is_drawn_at_the_minimum_and_the_others_share_the_rest(): void
    {
        $slices = Pie::slices(['layak' => 636, 'perlu_service' => 2, 'rusak' => 10, 'belum_dicek' => 363], 148);

        $degrees = array_column($slices, 'degrees', 'key');
        $this->assertSame(3.0, $degrees['perlu_service']);
        $this->assertEqualsWithDelta(360, array_sum($degrees), 0.001);
        // What is left (357°) is shared in proportion by the others.
        $this->assertEqualsWithDelta(636 / 1009 * 357, $degrees['layak'], 0.001);
        $this->assertEqualsWithDelta(363 / 1009 * 357, $degrees['belum_dicek'], 0.001);
        // Its true share is still its own.
        $this->assertEqualsWithDelta(2 / 1011, array_column($slices, 'share', 'key')['perlu_service'], 1e-9);
    }

    public function test_one_part_is_the_whole_circle_and_nothing_is_no_slice(): void
    {
        $whole = Pie::slices(['layak' => 0, 'rusak' => 5], 148);
        $this->assertCount(1, $whole);
        $this->assertSame(['rusak', true, null, 360.0], [$whole[0]['key'], $whole[0]['full'], $whole[0]['path'], $whole[0]['degrees']]);
        $this->assertSame([74.0, 74.0], [$whole[0]['label_x'], $whole[0]['label_y']]);

        $this->assertSame([], Pie::slices(['layak' => 0, 'rusak' => 0], 148));
        $this->assertSame([], Pie::slices([], 148));
    }
}
