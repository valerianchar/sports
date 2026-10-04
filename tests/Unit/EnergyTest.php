<?php

namespace Tests\Unit;

use App\Support\Energy;
use Tests\TestCase;

class EnergyTest extends TestCase
{
    public function test_a_weight_session_burns_at_a_moderate_rate(): void
    {
        // Une heure de musculation à 80 kg : 4 MET × 80 kg × 1 h.
        $this->assertSame(320, Energy::kcal(3600, [['exercise' => 'developpe-couche', 'seconds' => 40]], 80));
    }

    public function test_cardio_efforts_count_at_their_own_intensity(): void
    {
        // 20 minutes de rameur (7 MET) dans une heure : 3 MET de plus pendant un tiers d'heure.
        $this->assertSame(400, Energy::kcal(3600, [['exercise' => 'rameur', 'seconds' => 1200]], 80));
        // Courir dépense plus que pédaler en position semi-allongée.
        $this->assertGreaterThan(
            Energy::kcal(1800, [['exercise' => 'velo-semi-allonge', 'seconds' => 1800]], 70),
            Energy::kcal(1800, [['exercise' => 'course', 'seconds' => 1800]], 70),
        );
    }

    public function test_efforts_never_outlast_the_session(): void
    {
        $this->assertSame(
            Energy::kcal(1800, [['exercise' => 'rameur', 'seconds' => 1800]], 70),
            Energy::kcal(1800, [['exercise' => 'rameur', 'seconds' => 5400]], 70),
        );
    }

    public function test_only_cardio_counts_as_cardio(): void
    {
        $this->assertTrue(Energy::isCardio('rameur'));
        $this->assertFalse(Energy::isCardio('wall-balls'));
        $this->assertFalse(Energy::isCardio('squat'));
    }
}
