# techbash-snake-overnight (tjlytle/techbash-snake-overnight)

The Battlesnake PHP platform (a copy of `tjlytle/battle-snake-framework`) as used live in the
TechBash 2026 talk "BattleSnake in 50 minutes". The map of the code is
`.claude/skills/build-snake/platform.md`; read it before touching `src/`.

## Working here

- Stack: `make build && make install && make migrate && make up` (http://localhost:8089).
  Tests: `make test` (PHPUnit, including the API-spec compliance test) and `make behat`
  (ASCII-board scenarios); both run inside `docker compose run`, so they need the image but
  not a running stack. `make stan` and `make cs` fail on `main` already and are not gates.
- This snake is served from this checkout on :8089 (`.env` sets PORT; not public). After a
  merge the QA stage pulls here so it serves the new code.
- A snake is one `Strategy` class under `src/Snake/`, bound in `src/Core/di/snake.php`, with a
  Behat scenario in `test/Behat/features/`. Do not touch the event-sourcing wiring, Docker,
  Composer or the public API for a snake change.

## Strategies: compose, never replace

The snake that plays is a **composition** of small strategies, not one class that keeps
growing. This is the design the talk is about, so every change keeps to it:

- A behaviour is its own `Strategy` class under `src/Snake/`, named for what it does
  (`HuntFoodWhenHungry`, `AvoidDeadEnds`, `DodgeLongerHeads`), one concern each, built on the
  `MoveClassifier` labels, with its own unit test. A departure in behaviour is a **new** class;
  an existing strategy is only edited to fix a bug in what it already claims to do.
- The playing snake is assembled from the platform's combinators: aggregates (`Majority`,
  `Scored`, `FirstSafe`, `Conditional`) and decorators (`Fallback`, `Nudgeable`). `Conditional`
  switches strategies by the phase of the game (turn, health, length, snakes left), `Scored`
  weighs votes, `FirstSafe` orders preferences, `Fallback` catches the rest. Complex behaviour
  comes from the composition, never from one strategy knowing everything.
- The composition lives in one place, `src/Core/di/snake.php` (a `TechBashSnake` factory
  there if it outgrows a few lines), and that is the only wiring that changes when a strategy
  is added. Say in the PR where the new strategy slots in and why (which condition, which
  weight, which order).
- A superseded strategy is left in place and unwired, with a line in the composition saying
  what replaced it. Nothing is deleted during the talk.
- An issue that says "the snake should X" is answered by three questions before code: which
  existing strategy already covers part of X, what new strategy X needs, and where in the
  composition it goes. The Behat scenario tests the composed snake; the unit test tests the
  strategy alone.

## How work gets done here

There are two ways to build something, and the words Tim uses pick one.

**"Fast track it"** means build it end to end in this session, with the human in the loop:
use the harness (`/build-snake <idea>` for a snake behaviour, superpowers' brainstorming
and planning or `/build` for anything bigger), ask the questions those skills ask, write the
test first, run the suites, and open the PR as `builder`. Nothing goes through the pipeline.

**"SDLC"**, "use the pipeline", or no instruction at all means the pipeline does the work:
frame the ask as an issue as `advocate` (problem, context, acceptance criteria as board
situations where possible), label it `stage:in-progress`, and stop. Do not build it here.
The loop in `bin/pipeline` takes it from there.

**Vague asks.** When the ask has no testable acceptance criteria and no constraints, such as
"build the best Battlesnake", "make it smarter" or "make it win", do not build, do not
frame, and do not invent requirements. Reply, in this voice:

> Dude, aren't you the engineer? I can write the code, but I don't design your snake. I have
> no idea what "best" means here: eat first or stay long? Hug the walls or hold the centre?
> How much risk per move? What does it do when it is the smallest snake on the board? Give me
> the constraints and the board situations you care about, and I'll give you good code. Give
> me vibes and you'll get vibes.

Then ask for two or three concrete board situations and the move each one should produce.
That is what turns the ask into something a test can check. The same rule holds in the
pipeline: the builder parks the issue with that reply instead of guessing.

## The pipeline

`bin/pipeline` watches the open issues and runs one prompt per stage in a headless Claude Code
session (`bin/pipeline --once` for a single pass, `bin/pipeline <#>` for one issue in the
foreground). Stages are labels; the loop decides nothing:

| Label | Who | Prompt | Leaves as |
|---|---|---|---|
| `stage:todo` | nobody | backlog, untouched | moved by a human |
| `stage:in-progress` | builder | `pipeline/build.md`: worktree, failing test, smallest change, suites green, draft PR | `stage:review` |
| `stage:review` | reviewer | `pipeline/review.md`: read the PR; approve, or send back with the finding; improvements become `idea` issues | `stage:qa` or back to `stage:in-progress` |
| `stage:qa` | qa | `pipeline/qa.md`: run PHPUnit (with the API-spec test) and Behat; post the results | `stage:done` or back to `stage:in-progress` |
| `stage:done` | | ready to merge, or merged | |
| `stage:parked` | | one question for a human, `@tjlytle` in it; the loop resumes when the newest comment is from a human | `stage:in-progress` |

`working` is the loop's own label: on while a session has the issue, off when it ends. The
loop tracks its sessions; one that ends without moving the stage label counts as a failure,
the issue stays where it was and is retried, and after three such endings it parks with the
log tail and `@tjlytle`. An issue that comes back to the builder a third time parks instead
of looping. Logs are in `var/pipeline/`, `bin/pipeline --status` shows what is running.
Headless sessions run with the allow list in `bin/pipeline`; a denied tool call shows in the
log as the reason a stage stopped.

## The analyst

Every game is event-sourced, so the snake's history is on disk. `bin/games` (run as
`make games ARGS="…"`, inside the PHP container) replays it: `list`, `game <id>`,
`turn <id> <n>` (the board as the specs draw it, our move, what it led to), `last <id>`,
`scenario <id> <n> [move]` (a Behat scenario to paste into an issue). `bin/analyze` runs
`pipeline/analyze.md` as ops: it reads the games since the last run and files one `idea`
issue per lesson, each carrying the board situation as a scenario, which is exactly what the
build stage needs as acceptance criteria. A session plays its own games with `bin/play`
against the snakes in `pipeline/opponents.md`, stops at its deadline, and systemd starts the
next one. The two lines below are read by the prompts:

Merge: auto
Promote ideas: yes

`Merge: auto` lets the QA stage merge a green PR as builder and pull this checkout, so the
running snake serves the new code. `Promote ideas: yes` lets the analyst move its own best
lesson to `stage:in-progress` while fewer than two issues are in flight. Both are on here, off in the demo repo.

## Keeping it running

`bin/install-services` writes user systemd units named after this directory: the pipeline
loop and the analyst (both restart on exit) and the two local starter snakes the analyst plays
against. `loginctl enable-linger` keeps them up after logout; `bin/install-services remove`
takes them out, `status` shows them.

## GitHub Workflow

Bots: advocate=advocate, architect=architect, builder=builder, qa=qa, reviewer=reviewer, ops=ops
