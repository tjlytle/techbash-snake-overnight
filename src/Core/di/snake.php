<?php

declare(strict_types=1);

/**
 * To swap in your own snake:
 *   1. Create a class under src/Snake/ that implements BattleSnake\Strategy\Strategy.
 *   2. Change the binding below to point at it.
 */

use BattleSnake\Analyzer\MoveClassifier;
use BattleSnake\Domain\Battlesnake;
use BattleSnake\Domain\GameState;
use BattleSnake\EventSourcing\Game;
use BattleSnake\Snake\AvoidDeadEnds;
use BattleSnake\Snake\DodgeLongerHeads;
use BattleSnake\Snake\GrowWhenSafe;
use BattleSnake\Snake\HuntFoodWhenHungry;
use BattleSnake\Snake\OpeningHunt;
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
    //   40 and up  GrowWhenSafe: SAFE food within two squares that no longer (or equal)
    //              head is as near to; RandomSnake when there is none
    //   15 to 39   HuntFoodWhenHungry, SAFE moves first
    //   under 15   HuntFoodWhenHungry, desperate: nearest food unless certain death
    // DodgeLongerHeads overrides the first two when a longer (or equal) head is within two
    // squares; a starving snake takes the food anyway.
    // In the opening (turn under 50, or shorter than the longest other snake) OpeningHunt
    // overrides the 40-and-up branch, dodge included: SAFE food at any distance that no
    // longer head is as near to, on a move with at least our length of room.
    // AvoidDeadEnds vetoes the pick when it leads into a pocket with less room than our length.
    // RandomSnake catches any END move that is left.
    'TechBashSnake' => DI\factory(static function (MoveClassifier $classifier, RandomSnake $random): Strategy {
        $healthy = new DodgeLongerHeads(new GrowWhenSafe($random, $classifier), $classifier);

        return new Fallback(
            new AvoidDeadEnds(
                new Conditional(
                    static fn(Game $game, GameState $state): int => match (true) {
                        $state->you->health < 15 => 3,
                        $state->you->health < 40 => 2,
                        $state->turn < 50 => 1,
                        $state->you->length < \max(\array_map(
                            static fn(Battlesnake $snake): int => $snake->id === $state->you->id ? 0 : $snake->length,
                            \iterator_to_array($state->board->snakes, false),
                        )) => 1,
                        default => 0,
                    },
                    $healthy,
                    new OpeningHunt($healthy, $classifier),
                    new DodgeLongerHeads(new HuntFoodWhenHungry($classifier), $classifier),
                    new HuntFoodWhenHungry($classifier, desperate: true),
                ),
                $classifier,
            ),
            $random,
            $classifier,
        );
    }),

    RandomSnake::class => DI\autowire(),
];
