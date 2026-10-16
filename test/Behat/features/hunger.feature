Feature: A hungry snake hunts food
  In order not to starve
  As the snake
  I steer toward the nearest food when my health runs low

  Scenario: game cd37ef28 turn 99, starving, food below
    Given the board is:
      """
      ttT----vv--
      S-----Uvvv-
      0Wx-----0vv
      --x-------v
      --xx------v
      ---xxx----v
      0----X----V
      -----------
      ---0-------
      -----------
      -----------
      """
    And our health is 3
    And the snake receives a "/start" request
    When the snake receives a "/move" request
    Then the snake moves "down"

  Scenario: Hungry, food two squares up with nothing in the way
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
    And our health is 30
    And the snake receives a "/start" request
    When the snake receives a "/move" request
    Then the snake moves "up"
