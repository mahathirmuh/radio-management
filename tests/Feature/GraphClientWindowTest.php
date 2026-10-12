<?php

namespace Tests\Feature;

use Modules\SharePointDocs\Services\GraphClient;
use RuntimeException;
use Tests\TestCase;

/**
 * The time that a reading from Microsoft may take (within()), and whether it is a time that was cut out of the time of a request that has another source
 * to read too (shared()): running out of that says nothing about Microsoft, and the services do not hold a source back for it.
 */
class GraphClientWindowTest extends TestCase
{
    public function test_a_reading_is_shared_only_while_a_shared_window_is_open(): void
    {
        $client = new GraphClient();

        $this->assertFalse($client->shared(), 'no window at all');
        $this->assertSame(INF, $client->timeLeft());

        $client->within(10, function () use ($client) {
            $this->assertFalse($client->shared(), 'a window of its own');
            $this->assertEqualsWithDelta(10.0, $client->timeLeft(), 0.5);
        });
        $client->within(10, function () use ($client) {
            $this->assertTrue($client->shared(), 'a window that was cut out of another time');
            $this->assertEqualsWithDelta(10.0, $client->timeLeft(), 0.5);
        }, true);
        $this->assertFalse($client->shared(), 'not after it');
        $this->assertSame(INF, $client->timeLeft());

        // Inside a window the outer one counts: a reading that goes on inside a shared window is shared, whatever it asks for itself, and the other way round.
        $client->within(10, fn () => $client->within(5, function () use ($client) {
            $this->assertTrue($client->shared());
            $this->assertEqualsWithDelta(10.0, $client->timeLeft(), 0.5, 'the time of the outer window counts');
        }), true);
        $client->within(10, fn () => $client->within(5, function () use ($client) {
            $this->assertFalse($client->shared());
        }, true));

        // A reading that fails leaves nothing behind, and the next window is what it says.
        try {
            $client->within(10, fn () => throw new RuntimeException('x'), true);
        } catch (RuntimeException) {
            // as it should
        }
        $this->assertFalse($client->shared());
        $this->assertSame(INF, $client->timeLeft());
        $client->within(10, fn () => $this->assertFalse($client->shared()));
    }

    public function test_what_the_reading_returns_is_returned(): void
    {
        $client = new GraphClient();

        $this->assertSame('x', $client->within(5, fn () => 'x'));
        $this->assertSame('y', $client->within(5, fn () => 'y', true));
    }
}
