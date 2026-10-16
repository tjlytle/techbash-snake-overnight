Feature: The snake hunts food in the opening
  In order to be the longer snake when heads first meet
  As the snake
  I head for the nearest food at any distance early in the game, unless a longer snake is nearer to it

  # Food four squares straight up, nothing in the way; W is as long as us and as far away.
  Scenario: game 62a9ed5a turn 6
    Given the board is:
      """
      --------U--
      --------v--
      --------V--
      -----------
      -----------
      -----0-----
      -----------
      -------W---
      -------x---
      ----tS-x---
      ---Tt--X---
      """
    And our health is 96
    And the snake receives a "/start" request
    When the snake receives a "/move" request
    Then the snake moves "up"
