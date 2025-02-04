@mod @mod_hippotrack
Feature: HippoTrack availability can be set
  In order to see hippotrack availability
  As a teacher
  I need to be able to set hippotrack opening and closing times

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | One      | teacher1@example.com |
      | student1 | Student   | One      | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "question categories" exist:
      | contextlevel | reference | name           |
      | Course       | C1        | Test questions |
    And the following "questions" exist:
      | questioncategory | qtype     | name | questiontext    |
      | Test questions   | truefalse | TF1  | First question  |

  Scenario Outline: Set hippotrack opening time while closing time is disabled
    Given the following "activities" exist:
      | activity | course | name   | timeopen   |
      | hippotrack     | C1     | HippoTrack 1 | <timeopen> |
    And hippotrack "HippoTrack 1" contains the following questions:
      | question | page | maxmark |
      | TF1      | 1    | 2       |
    When I am on the "HippoTrack 1" "hippotrack activity" page logged in as student1
    # Confirm display as student depending on case.
    Then I should see "<opentext>:"
    And I should see "<timeopen>%A, %d %B %Y, %I:%M##"
    And I should not see "Close:"
    And I <hippotrackavailability> see "This hippotrack is currently not available."
    And "Attempt hippotrack" "button" <attemptvisibility> exist

    Examples:
      | opentext | timeopen      | attemptvisibility | hippotrackavailability |
      # Case 1 - open is set to future date, close is disabled.
      | Opens    | ##tomorrow##  | should not        | should           |
      # Case 4 - open is set to past date, close is disabled.
      | Opened   | ##yesterday## | should           | should not        |

  Scenario Outline: Set hippotrack closing time while opening time is disabled
    Given the following "activities" exist:
      | activity | course | name   | timeclose   |
      | hippotrack     | C1     | HippoTrack 1 | <timeclose> |
    And hippotrack "HippoTrack 1" contains the following questions:
      | question | page | maxmark |
      | TF1      | 1    | 2       |
    When I am on the "HippoTrack 1" "hippotrack activity" page logged in as student1
    # Confirm display as student depending on case.
    Then I should see "<closetext>:"
    And I should see "<timeclose>%A, %d %B %Y, %I:%M##"
    And I <hippotrackavailability> see "This hippotrack is currently not available."
    And "Attempt hippotrack" "button" <attemptvisibility> exist

    Examples:
      | closetext | timeclose      | attemptvisibility | hippotrackavailability |
      # Case 2 - open is disabled, close is set to past date.
      | Closed    | ##yesterday##  | should not        | should not       |
      # Case 5 - open is disabled, close is set to future date.
      | Closes    | ##tomorrow##   | should            | should not       |

  Scenario Outline: Set hippotrack opening and closing times
    Given the following "activities" exist:
      | activity | course | name   | timeopen   | timeclose   |
      | hippotrack     | C1     | HippoTrack 1 | <timeopen> | <timeclose> |
    And hippotrack "HippoTrack 1" contains the following questions:
      | question | page | maxmark |
      | TF1      | 1    | 2       |
    When I am on the "HippoTrack 1" "hippotrack activity" page logged in as student1
    # Confirm display as student depending on case.
    Then I should see "<opentext>:"
    And I should see "<timeopen>%A, %d %B %Y, %I:%M##"
    And I should see "<closetext>:"
    And I should see "<timeclose>%A, %d %B %Y, %I:%M##"
    And I <hippotrackavailability> see "This hippotrack is currently not available."
    And "Attempt hippotrack" "button" <attemptvisibility> exist

    Examples:
      | opentext | timeopen        | closetext | timeclose      | attemptvisibility | hippotrackavailability |
      # Case 6 - open and close are set to past date.
      | Opened   | ##3 days ago## | Closed     | ##yesterday##  | should not        | should not       |
      # Case 7 - open is set to past date, close is set to future date.
      | Opened   | ##yesterday##  | Closes     | ##tomorrow##   | should            | should not       |
      # Case 8 - open and close are set to future date
      | Opens    | ##tomorrow##   | Closes     | ##+2 days##    | should not        | should           |

  Scenario: HippoTrack time open and time close are disabled
    # Case 3 - both open and close are disabled.
    Given the following "activities" exist:
      | activity | course | name   |
      | hippotrack     | C1     | HippoTrack 1 |
    And hippotrack "HippoTrack 1" contains the following questions:
      | question | page | maxmark |
      | TF1      | 1    | 2       |
    When I am on the "HippoTrack 1" "hippotrack activity" page logged in as student1
    Then I should not see "Opens"
    And I should not see "Opened"
    And I should not see "Closes"
    And I should not see "Closed"
    And I should not see "This hippotrack is currently not available."
    And "Attempt hippotrack" "button" should exist

  @javascript
  Scenario Outline: Timer is displayed when hippotrack closes in less than an hour
    Given the following "activities" exist:
      | activity | course | name   | timeclose   |
      | hippotrack     | C1     | HippoTrack 1 | <closedate> |
    And hippotrack "HippoTrack 1" contains the following questions:
      | question | page |
      | TF1      | 1    |
    And I am on the "HippoTrack 1" "hippotrack activity" page logged in as "teacher1"
    When I press "Preview hippotrack"
    # Confirm timer visibility for teacher
    Then I <timervisibility> see "Time left"
    And I am on the "HippoTrack 1" "hippotrack activity" page logged in as "student1"
    And I press "Attempt hippotrack"
    # Confirm timer visibility for student
    And I <timervisibility> see "Time left"

    Examples:
      | closedate           | timervisibility |
      # Case 1 - closedate is < 1hr, the timer is visible
      | ##now +10 minutes## | should          |
      # Case 2 - closedate is > 1hr, the timer is not visible
      | ##now +2 hours##    | should not      |
