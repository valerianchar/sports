<?php

namespace Tests\Unit;

use App\Support\Strength;
use PHPUnit\Framework\TestCase;

class StrengthTest extends TestCase
{
    public function test_the_one_rep_max_follows_epley(): void
    {
        $this->assertSame(100.0, Strength::oneRepMax(100, 1));
        $this->assertSame(133.33, Strength::oneRepMax(100, 10));
        $this->assertNull(Strength::oneRepMax(100, 20), 'Au-delà de 12 reps, on n’estime plus.');
        $this->assertNull(Strength::oneRepMax(null, 8), 'Au poids du corps, pas de 1RM.');
        $this->assertNull(Strength::oneRepMax(60, 0));
    }

    public function test_all_reps_made_means_a_step_up(): void
    {
        $sets = [['weight' => 60.0, 'reps' => 8, 'target_reps' => 8], ['weight' => 60.0, 'reps' => 8, 'target_reps' => 8]];

        $this->assertSame(['weight' => 62.5, 'trend' => 'up'], Strength::nextWeight($sets));
        $this->assertSame(['weight' => 9.0, 'trend' => 'up'], Strength::nextWeight([['weight' => 8.0, 'reps' => 12, 'target_reps' => 12]]));
        $this->assertSame(['weight' => 145.0, 'trend' => 'up'], Strength::nextWeight([['weight' => 140.0, 'reps' => 5, 'target_reps' => 5]]));
    }

    public function test_a_rep_short_means_keep_two_short_means_back_off(): void
    {
        $this->assertSame(['weight' => 60.0, 'trend' => 'keep'], Strength::nextWeight([
            ['weight' => 60.0, 'reps' => 8, 'target_reps' => 8], ['weight' => 60.0, 'reps' => 7, 'target_reps' => 8],
        ]));

        $this->assertSame(['weight' => 57.5, 'trend' => 'down'], Strength::nextWeight([
            ['weight' => 60.0, 'reps' => 5, 'target_reps' => 8],
        ]));
    }

    public function test_bodyweight_work_gets_no_advice(): void
    {
        $this->assertNull(Strength::nextWeight([['weight' => null, 'reps' => 10, 'target_reps' => 10]]));
        $this->assertNull(Strength::nextWeight([]));
    }
}
