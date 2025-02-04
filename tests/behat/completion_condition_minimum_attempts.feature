@mod @mod_hippotrack @core_completion
Feature: Set a hippotrack to be marked complete when the student completes a minimum amount of attempts
  In order to ensure a student has completed the hippotrack before being marked complete
  As a teacher
  I need to set a hippotrack to complete when the student completes a certain amount of attempts

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
      | activity | name           | course | idnumber | completion | completionminattemptsenabled | completionminattempts |
      | hippotrack     | Test hippotrack name | C1     | hippotrack1    | 2          | 1                            | 2                     |
    And hippotrack "Test hippotrack name" contains the following questions:
      | question       | page |
      | First question | 1    |
    And user "student1" has attempted "Test hippotrack name" with responses:
      | slot | response |
      |   1  | False    |

  Scenario: student1 uses up both attempts without passing
    When I log in as "teacher1"
    And I am on "Course 1" course homepage
    And "Completed: Test hippotrack name" "icon" should not exist in the "Test hippotrack name" "list_item"
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And the "Make attempts: 2" completion condition of "Test hippotrack name" is displayed as "todo"
    And I follow "Test hippotrack name"
    And I press "Re-attempt hippotrack"
    And I set the field "False" to "1"
    And I press "Finish attempt ..."
    And I press "Submit all and finish"
    And I am on "Course 1" course homepage
    Then the "Make attempts: 2" completion condition of "Test hippotrack name" is displayed as "done"
    And I follow "Test hippotrack name"
    And the "Make attempts: 2" completion condition of "Test hippotrack name" is displayed as "done"
    And I log out
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Test hippotrack name"
    And "Test hippotrack name" should have the "Make attempts: 2" completion condition
    And I am on "Course 1" course homepage
    And I navigate to "Reports" in current page administration
    And I click on "Activity completion" "link"
    And "Completed" "icon" should exist in the "Student 1" "table_row"
