<?php

declare(strict_types=1);

/**
 * To swap in your own snake:
 *   1. Create a class under src/Snake/ that implements BattleSnake\Strategy\Strategy.
 *   2. Change the binding below to point at it.
 */

use BattleSnake\Analyzer\MoveClassifier;
use BattleSnake\Domain\GameState;
use BattleSnake\EventSourcing\Game;
use BattleSnake\Snake\HuntFoodWhenHungry;
use BattleSnake\Snake\RandomSnake;
use BattleSnake\Strategy\Aggregate\Conditional;
use BattleSnake\Strategy\Decorator\Fallback;
use BattleSnake\Strategy\Decorator\Nudgeable;
use BattleSnake\Strategy\Strategy;

return [
    Strategy::class => DI\get(Nudgeable::class),

    // The Nudgeable decorator wraps a fallback. Swap the fallback to change behavior.
    Nudgeable::class => DI\autowire()
        ->constructorParameter('fallback', DI\get('TechBashSnake')),

    // The playing snake, picked by health:
    //   40 and up  RandomSnake
    //   15 to 39   HuntFoodWhenHungry, SAFE moves first
    //   under 15   HuntFoodWhenHungry, desperate: nearest food unless certain death
    // RandomSnake catches any END move the picked strategy returns.
    'TechBashSnake' => DI\factory(static fn(MoveClassifier $classifier, RandomSnake $random): Strategy => new Fallback(
        new Conditional(
            static fn(Game $game, GameState $state): int => match (true) {
                $state->you->health < 15 => 2,
                $state->you->health < 40 => 1,
                default => 0,
            },
            $random,
            new HuntFoodWhenHungry($classifier),
            new HuntFoodWhenHungry($classifier, desperate: true),
        ),
        $random,
        $classifier,
    )),

    RandomSnake::class => DI\autowire(),
];
