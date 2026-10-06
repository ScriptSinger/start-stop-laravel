<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Тесты не зависят от собранного фронтенда (public/build): @vite
     * отдаёт пустые теги.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }
}
