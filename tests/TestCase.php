<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // @vite n'a pas besoin de public/build (npm run build) pendant les tests
        $this->withoutVite();
    }
}
