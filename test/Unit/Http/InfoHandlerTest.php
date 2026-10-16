<?php

declare(strict_types=1);

namespace BattleSnake\Tests\Unit\Http;

use BattleSnake\Http\Handler\InfoHandler;
use Laminas\Diactoros\ResponseFactory;
use Laminas\Diactoros\ServerRequest;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The snake's TechBash look: what the board shows when it joins a game.
 * ApiSpecTest checks the shape of /info; this checks the values.
 */
final class InfoHandlerTest extends TestCase
{
    #[Test]
    public function info_gives_the_techbash_look(): void
    {
        $handler = new InfoHandler(new ResponseFactory());

        $response = $handler->handle(new ServerRequest([], [], 'http://localhost/', 'GET'));
        $info = \json_decode((string) $response->getBody(), true);

        self::assertSame('tjlytle', $info['author']);
        self::assertSame('#0e8a16', $info['color']);
        self::assertSame('evil', $info['head']);
        self::assertSame('bolt', $info['tail']);
    }
}
