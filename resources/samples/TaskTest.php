<?php

namespace Tests\Application;

use Tests\TestCase;

final class TaskTest extends TestCase
{
    // Requests run through the whole HTTP kernel, without a web server.
    public function testAMissingTaskIsNotFound(): void
    {
        $response = $this->get('/tasks/99', ['Accept' => 'application/json']);

        self::assertSame(404, $response->getStatusCode());
        self::assertSame(['message' => 'Not Found'], json_decode((string) $response->getBody(), true));
    }
}
