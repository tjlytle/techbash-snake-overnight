# Opponents

Snakes `bin/play` can put on the board, one per line: name, URL, and what they are like. The
analyst picks from this list; anyone can add a public snake (a laptop through a tunnel, a
snake from the room). Keep the URL reachable from this machine.

| name | url | style |
|---|---|---|
| starter-py | http://localhost:8001 | the official Python starter: avoids walls and bodies, otherwise random. Weak; good for testing a new rule. |
| starter-js | http://localhost:8002 | the official JavaScript starter: same idea in Node. Weak. |
| old-php | http://localhost:8090 | the platform as it was before tonight's changes (the demo checkout from the talk prep). A mirror match. |

Pairings that teach something: one weak opponent shows whether we can hunt food and stay
alive; two or three on the board show crowding and head-to-head judgement; the mirror match
shows what our own rules do against each other. A solo game (no opponents) only tests whether
we trap ourselves.
