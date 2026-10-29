<?php

namespace App\Http\Controller;

use App\Tasks\TaskRepository;
use Crystoline\Xpsr\Http\Exception\NotFoundHttpException;
use Crystoline\Xpsr\Http\Response\ResponseBuilder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class TaskController
{
    // The container supplies both by type: no registration needed.
    public function __construct(
        private readonly TaskRepository $tasks,
        private readonly ResponseBuilder $responses,
    ) {}

    public function show(ServerRequestInterface $request): ResponseInterface
    {
        $task = $this->tasks->find((int) $request->getAttribute('id'))
            ?? throw new NotFoundHttpException();

        return $this->responses->json($task);
    }
}
