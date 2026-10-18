<?php

namespace Database\Factories\Concerns;

use Database\Factories\Providers\TunisiaProvider;

/**
 * Enregistre le provider tunisien sur fake() à la création de la factory :
 * les factories peuvent ensuite appeler fake()->tunisianName(), fake()->tunisianAddress()…
 */
trait UsesTunisiaProvider
{
    protected function withFaker()
    {
        TunisiaProvider::register(fake());

        return parent::withFaker();
    }
}
