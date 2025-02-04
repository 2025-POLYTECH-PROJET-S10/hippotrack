@mod @mod_hippotrack @core_completion
Feature: Ensure saving a hippotrack does not modify the completion settings.
  In order to reliably use completion
  As a teacher
  I need to be able to update the hippotrack
  without changing the completion settings.

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
    And the following "activity" exists:
      | activity                     | hippotrack      |
      | course                       | C1        |
      | idnumber                     | hippotrack1     |
      | name                         | Test hippotrack |
      | section                      | 1         |
      | attempts                     | 2         |
      | gradepass                    | 5.00      |
      | completion                   | 2         |
      | completionview               | 0         |
      | completionusegrade           | 1         |
      | completionpassgrade          | 1         |
      | completionattemptsexhausted  | 1         |
    And hippotrack "Test hippotrack" contains the following questions:
      | question       | page |
      | First question | 1    |
    And user "student1" has attempted "Test hippotrack" with responses:
      | slot | response |
      |   1  | True     |

  Scenario: Ensure saving hippotrack activty does not change completion settings
    Given I am on the "Test hippotrack" "mod_hippotrack > View" page logged in as "teacher1"
    When I navigate to "Settings" in current page administration
    Then the "completionattemptsexhausted" "field" should be disabled
    And the field "completionattemptsexhausted" matches value "1"
    And I press "Save and display"
    And I navigate to "Settings" in current page administration
    And the "completionattemptsexhausted" "field" should be disabled
    And the field "completionattemptsexhausted" matches value "1"
