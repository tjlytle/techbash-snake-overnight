Feature: The snake grows while food is cheap
  In order to be the longer snake when heads meet
  As the snake
  I take safe food within two squares at any health, unless a snake as long as me is nearer to it

  # Food up and food left are both one step away. Left lands on the edge; up keeps to the board.
  Scenario: game 310cb986 turn 74, full health, food next to the head
    Given the board is:
      """
      -----------
      ---T-------
      -0-ttt-----
      0St--t--0--
      --tttt-----
      --------0--
      -0-0---0---
      ---0-0-----
      -----------
      --0--------
      0----------
      """
    And our health is 99
    And the snake receives a "/start" request
    When the snake receives a "/move" request
    Then the snake moves "up"

  Scenario: Full health, food two squares up with nothing in the way
    Given the board is:
      """
      -----------
      -----------
      -----------
      -----0-----
      -----------
      -----S-----
      -----t-----
      -----T-----
      -----------
      -----------
      -----------
      """
    And our health is 100
    And the snake receives a "/start" request
    When the snake receives a "/move" request
    Then the snake moves "up"

  # Food is two squares away (one up, one right): up and right both close in, left walks away.
  Scenario: Full health, food one square diagonally away
    Given the board is:
      """
      -----------
      -----------
      -----------
      -----------
      ------0----
      -----S-----
      -----t-----
      -----T-----
      -----------
      -----------
      -----------
      """
    And our health is 100
    And the snake receives a "/start" request
    When the snake receives a "/move" request
    Then the snake does not move "left"
