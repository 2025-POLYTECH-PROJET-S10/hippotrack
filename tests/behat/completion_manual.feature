@mod @mod_hippotrack @core_completion
Feature: Manually complete a hippotrack
  In order to meet manual hippotrack completion requirements
  As a student
  I need to be able to view and modify my hippotrack manual completion status

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Student   | 1        | student1@example.com |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category | enablecompletion |
      | Course 1 | C1        | 0        | 1                |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following config values are set as admin:
      | grade_item_advanced | hiddenuntil |
    And the following "question categories" exist:
      | contextlevel | reference | name           |
      | Course       | C1        | Test questions |
    And the following "questions" exist:
      | questioncategory | qtype     | name           | questiontext              |
      | Test questions   | truefalse | First question | Answer the first question |
    And the following "activities" exist:
      | activity | name           | course | idnumber | completion |
      | hippotrack     | Test hippotrack name | C1     | hippotrack1    | 1          |
    And hippotrack "Test hippotrack name" contains the following questions:
      | question       | page |
      | First question | 1    |

  @javascript
  Scenario: A student can manually mark the hippotrack activity as done but a teacher cannot
    Given I am on the "Test hippotrack name" "hippotrack activity" page logged in as teacher1
    And the manual completion button for "Test hippotrack name" should be disabled
    And I log out
    # Student view.
    When I am on the "Test hippotrack name" "hippotrack activity" page logged in as student1
    Then the manual completion button of "Test hippotrack name" is displayed as "Mark as done"
    And I toggle the manual completion state of "Test hippotrack name"
    And the manual completion button of "Test hippotrack name" is displayed as "Done"
