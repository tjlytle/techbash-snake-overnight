# Opponents

Snakes `bin/play` can put on the board, one per line: name, URL, and what they are like. The
analyst picks from this list; anyone can add a public snake (a laptop through a tunnel, a
snake from the room). Keep the URL reachable from this machine. The strong ones run as
containers from `~/Clients/timlytle.net/opponents/start.sh`.

| name | url | style |
|---|---|---|
| shapeshifter | http://localhost:8011 | JonathanArns' Shapeshifter (Rust): paranoid minimax with a Monte-Carlo fallback. Two first places at the 2022 elite tournaments. The one to beat. |
| snork | http://localhost:8012 | wrenger's snork (Rust): flood-fill space control with tree search. Strong, fast. |
| hobbs | http://localhost:8013/hovering-hobbs | coreyja's Hovering Hobbs (Rust): minimax, played the top league. Strong. |
| devin | http://localhost:8013/devious-devin | coreyja's Devious Devin: aggressive, head-to-head hunter. |
| irene | http://localhost:8013/improbable-irene | coreyja's Improbable Irene: Monte-Carlo search. |
| old-php | http://localhost:8090 | the platform as it was before tonight's changes. A mirror match. |
| starter-py | http://localhost:8001 | the official Python starter: avoids walls and bodies, otherwise random. Weak; only for proving the plumbing. |
| starter-js | http://localhost:8002 | the official JavaScript starter. Weak. |

Pairings that teach something: one strong opponent shows whether we survive against real
play; two or three show crowding and head-to-head judgement; the mirror match shows what our
own rules do against each other. A solo game only tests whether we trap ourselves. Prefer the
strong snakes; the starters die in a few turns and teach nothing.
