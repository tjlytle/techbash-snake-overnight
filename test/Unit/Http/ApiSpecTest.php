<?php

declare(strict_types=1);

namespace BattleSnake\Tests\Unit\Http;

use BattleSnake\Core\Application;
use BattleSnake\Tests\Support\TestSchema;
use Laminas\Diactoros\ServerRequest;
use Laminas\Diactoros\StreamFactory;
use League\OpenAPIValidation\PSR7\OperationAddress;
use League\OpenAPIValidation\PSR7\ResponseValidator;
use League\OpenAPIValidation\PSR7\ServerRequestValidator;
use League\OpenAPIValidation\PSR7\ValidatorBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The snake stays compliant with the Battlesnake API: every request the engine
 * recorded in test/requests/ is valid against open-api.yaml, and every response
 * the platform gives it is too. This is the QA gate for "did we break the snake".
 */
final class ApiSpecTest extends TestCase
{
    private const SPEC = __DIR__ . '/../../../open-api.yaml';
    private const REQUESTS = __DIR__ . '/../../requests';

    private static ?Application $app = null;
    private static ?ServerRequestValidator $requests = null;
    private static ?ResponseValidator $responses = null;

    public static function setUpBeforeClass(): void
    {
        self::$app = TestSchema::application();
        $builder = (new ValidatorBuilder())->fromYamlFile(self::SPEC);
        self::$requests = $builder->getServerRequestValidator();
        self::$responses = $builder->getResponseValidator();
    }

    #[Test]
    public function info_matches_the_spec(): void
    {
        $request = new ServerRequest([], [], 'http://localhost/', 'GET');

        $this->assertCompliant($request);
    }

    /**
     * One recorded four-player game, start to end, played through the app in order.
     *
     * @return iterable<string, array{string, string}>
     */
    public static function recordedGame(): iterable
    {
        yield 'start' => ['/start', 'four-player-large-start.json'];
        for ($turn = 1; $turn <= 10; $turn++) {
            yield "move $turn" => ['/move', "four-player-large-move$turn.json"];
        }
        yield 'end' => ['/end', 'four-player-large-end.json'];
    }

    #[Test]
    #[DataProvider('recordedGame')]
    public function recorded_engine_traffic_matches_the_spec(string $path, string $fixture): void
    {
        $body = (string) \file_get_contents(self::REQUESTS . '/' . $fixture);
        $request = (new ServerRequest([], [], 'http://localhost' . $path, 'POST'))
            ->withHeader('Content-Type', 'application/json')
            ->withBody((new StreamFactory())->createStream($body));

        $this->assertCompliant($request);
    }

    #[Test]
    public function the_official_example_game_matches_the_spec(): void
    {
        $body = (string) \file_get_contents(self::REQUESTS . '/official-example.json');
        foreach (['/start', '/move'] as $path) {
            $request = (new ServerRequest([], [], 'http://localhost' . $path, 'POST'))
                ->withHeader('Content-Type', 'application/json')
                ->withBody((new StreamFactory())->createStream($body));

            $this->assertCompliant($request);
        }
    }

    private function assertCompliant(ServerRequestInterface $request): void
    {
        $address = self::$requests->validate($request);
        $response = self::$app->handle($request);

        self::assertSame(200, $response->getStatusCode(), (string) $response->getBody());
        self::$responses->validate(
            new OperationAddress($address->path(), $address->method()),
            $response,
        );
        $this->addToAssertionCount(2);
    }
}
