<?php

declare(strict_types=1);

namespace BattleSnake\Http\Handler;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class InfoHandler extends AbstractHandler
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->createJsonResponse([
            'apiversion' => '1',
            'author' => 'tjlytle',
            'color' => '#0e8a16',
            'head' => 'evil',
            'tail' => 'bolt',
            'version' => '0.1.0',
        ]);
    }
}
