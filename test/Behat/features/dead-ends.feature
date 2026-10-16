Feature: The snake avoids dead ends
  In order not to trap myself
  As the snake
  I never enter a pocket with less room than my length

  Scenario: game b6f0f963 turn 130, right is a pocket walled by our body
    Given the board is:
      """
      -----0-----
      --------0--
      -----------
      -----------
      -0---------
      --------0--
      -----Ttt--0
      ---0---tt-0
      ----ttS-t--
      --0-ttttt--
      -------tt--
      """
    And the snake receives a "/start" request
    When the snake receives a "/move" request
    Then the snake moves "up"

  # The issue's board drew no tail; T at the end of the body gives the length 9 it describes.
  # Right and down both keep the open board, so either is fine; left is the pocket.
  Scenario: game 767f5075 turn 50, left is a one-square pocket
    Given the board is:
      """
      -ttt-----0-
      -t-S-------
      -tt-0------
      --t--------
      --T--------
      -----------
      -----------
      -----------
      ---------0-
      -----------
      --------0--
      """
    And the snake receives a "/start" request
    When the snake receives a "/move" request
    Then the snake does not move "left"
