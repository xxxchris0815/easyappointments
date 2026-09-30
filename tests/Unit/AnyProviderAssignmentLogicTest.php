<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Pure logic checks for any-provider assignment algorithms (no CodeIgniter boot).
 */
final class AnyProviderAssignmentLogicTest extends TestCase
{
    public function testMostAvailablePicksHighestHourCount(): void
    {
        $candidates = [
            ['id' => 1, 'available_hours_count' => 2, 'weight' => 1],
            ['id' => 2, 'available_hours_count' => 5, 'weight' => 1],
            ['id' => 3, 'available_hours_count' => 4, 'weight' => 1],
        ];

        $best_id = null;
        $max_hours = -1;

        foreach ($candidates as $candidate) {
            if ($candidate['available_hours_count'] > $max_hours) {
                $max_hours = $candidate['available_hours_count'];
                $best_id = $candidate['id'];
            }
        }

        $this->assertSame(2, $best_id);
    }

    public function testRoundRobinRotatesByCounter(): void
    {
        $candidates = [
            ['id' => 10, 'available_hours_count' => 1, 'weight' => 1],
            ['id' => 20, 'available_hours_count' => 1, 'weight' => 1],
            ['id' => 30, 'available_hours_count' => 1, 'weight' => 1],
        ];

        $this->assertSame(10, $candidates[0 % 3]['id']);
        $this->assertSame(20, $candidates[1 % 3]['id']);
        $this->assertSame(30, $candidates[2 % 3]['id']);
        $this->assertSame(10, $candidates[3 % 3]['id']);
    }

    public function testWeightedRoundRobinExpandsByWeight(): void
    {
        $candidates = [
            ['id' => 1, 'available_hours_count' => 1, 'weight' => 1],
            ['id' => 2, 'available_hours_count' => 1, 'weight' => 3],
        ];

        $sequence = [];

        foreach ($candidates as $candidate) {
            for ($i = 0; $i < max(1, (int) $candidate['weight']); $i++) {
                $sequence[] = $candidate['id'];
            }
        }

        $this->assertSame([1, 2, 2, 2], $sequence);
        $this->assertSame(1, $sequence[0 % 4]);
        $this->assertSame(2, $sequence[1 % 4]);
        $this->assertSame(2, $sequence[2 % 4]);
        $this->assertSame(2, $sequence[3 % 4]);
        $this->assertSame(1, $sequence[4 % 4]);
    }
}
