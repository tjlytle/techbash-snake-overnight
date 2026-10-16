Feature: The snake dodges longer heads
  In order not to lose a head-to-head
  As the snake
  I keep away from the squares a longer snake's head can reach, and off the edges near it

  # snork (U, length 10) is two squares away. Down runs along the right edge into the corner;
  # left turns back toward the open board.
  Scenario: game 20487baf turn 86, a longer head close by, the edge leads into the corner
    Given the board is:
      """
      -----------
      --------0--
      -----------
      --0----vv--
      -------vv--
      ------Vvv--
      ---0-0vvU-T
      ----------t
      --0-------S
      -----------
      --------0--
      """
    And the snake receives a "/start" request
    When the snake receives a "/move" request
    Then the snake moves "left"

  # Like game cf088a69: running along the top edge with a longer head (U, length 5) below.
  # Left keeps to the edge toward the corner; down leaves it.
  Scenario: Running along the top edge with a longer head two squares away
    Given the board is:
      """
      -----StT---
      -----------
      vvvU-------
      V----------
      -----------
      -----------
      -----------
      -----------
      -----------
      -----------
      -----------
      """
    And the snake receives a "/start" request
    When the snake receives a "/move" request
    Then the snake moves "down"
