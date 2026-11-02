<?php

namespace Tests\Application;

use Tests\TestCase;

final class TaskTest extends TestCase
{
    // Requests run through the whole HTTP kernel, without a web server.
    public function testAMissingTaskIsNotFound(): void
    {
        $this->get('/tasks/99', ['Accept' => 'application/json'])
            ->assertNotFound()
            ->assertExactJson(['message' => 'Not Found']);
    }
}
