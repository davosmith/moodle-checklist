@mod @mod_checklist @javascript
Feature: A student can comment on checklist items, when comments are enabled

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

  Scenario: A student enters comments that can be seen by the teacher
    When I am on the "Test checklist" "checklist activity" page logged in as "student1"
    And I set the following fields to these values:
      | Comment on The third item | My first comment  |
      | Comment on The first item | My second comment |
    And I log out
    And I am on the "Test checklist" "checklist activity" page logged in as "teacher1"
    And I follow "View progress"
    And I click on "View progress for this user" "link" in the "Student 1" "table_row"
    Then I should see "My first comment"
    And I should see "My second comment"
