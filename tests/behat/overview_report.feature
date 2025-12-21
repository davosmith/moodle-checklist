@mod @mod_checklist
Feature: Testing overview integration in checklist activity
  In order to summarize the checklist activity
  As a user
  I need to be able to see the checklist activity overview

  Background:
    Given the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "users" exist:
      | username | firstname | lastname |
      | student1 | Student   | 1        |
      | teacher1 | Teacher   | 1        |
    And the following "course enrolments" exist:
      | course | user     | role           |
      | C1     | student1 | student        |
      | C1     | teacher1 | editingteacher |
    And the following "activity" exists:
      | activity        | checklist      |
      | name            | Test checklist |
      | course          | C1             |
      | idnumber        | CHK001         |
      | studentcomments | 1              |
    And the following items exist in checklist "Test checklist":
      | text            | required |
      | The first item  | required |
      | The second item | required |
      | The third item  | required |

  @javascript
  Scenario: The checklist activity overview report should generate log events
    Given the site is running Moodle version 5.0 or higher
    And I am on the "Course 1" "course > activities > checklist" page logged in as "teacher1"
    When I am on the "Course 1" "course" page logged in as "teacher1"
    And I navigate to "Reports" in current page administration
    And I click on "Logs" "link"
    And I click on "Get these logs" "button"
    Then I should see "Course activities overview page viewed"
    And I should see "viewed the instance list for the module 'checklist'"

  @javascript
  Scenario: The Checklist activity index redirect to the activities overview
    Given the site is running Moodle version 5.0 or higher
    When I am on the "C1" "course > activities > checklist" page logged in as "admin"
    Then I should see "Name" in the "checklist_overview_collapsible" "region"
    And I should see "Actions" in the "checklist_overview_collapsible" "region"
    And I should see "Test checklist"
