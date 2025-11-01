@mod @mod_checklist @javascript
Feature: Progress reports can be filtered by group

  Background:
    Given the following "courses" exist:
      | shortname | fullname |
      | C1        | Course 1 |
    And the following "users" exist:
      | username | firstname | lastname |
      | student1 | Student   | 1        |
      | student2 | Student   | 2        |
      | student3 | Student   | 3        |
      | student4 | Student   | 4        |
      | student5 | Student   | 5        |
      | student6 | Student   | 6        |
      | student7 | Student   | 7        |
      | student8 | Student   | 8        |
      | teacher1 | Teacher   | 1        |
    And the following "course enrolments" exist:
      | course | user     | role           |
      | C1     | student1 | student        |
      | C1     | student2 | student        |
      | C1     | student3 | student        |
      | C1     | student4 | student        |
      | C1     | student5 | student        |
      | C1     | student6 | student        |
      | C1     | student7 | student        |
      | C1     | student8 | student        |
      | C1     | teacher1 | editingteacher |
    And the following "groups" exist:
      | name    | course | idnumber |
      | Group 1 | C1     | GRP01    |
      | Group 2 | C1     | GRP02    |
      | Group 3 | C1     | GRP03    |
    And the following "group members" exist:
      | user     | group |
      | student1 | GRP01 |
      | student2 | GRP01 |
      | student3 | GRP01 |
      | student4 | GRP01 |
      | student5 | GRP01 |
      | student1 | GRP02 |
      | student6 | GRP02 |
      | student7 | GRP02 |
    And the following "activities" exist:
      | activity  | name        | course | idnumber | groupmode |
      | checklist | Checklist 1 | C1     | CHK01    | 1         |
      | checklist | Checklist 2 | C1     | CHK02    | 2         |
    And the following items exist in checklist "Checklist 1":
      | text   |
      | Item 1 |
      | Item 2 |
      | Item 3 |
    And the following items exist in checklist "Checklist 2":
      | text   |
      | Item 4 |
      | Item 5 |
      | Item 6 |
    And the following items are checked off in checklist "Checklist 1" for user "student1":
      | itemtext | studentmark |
      | Item 1   | yes         |
      | Item 2   | yes         |
    And the following items are checked off in checklist "Checklist 1" for user "student2":
      | itemtext | studentmark |
      | Item 2   | yes         |
      | Item 3   | yes         |
    And the following items are checked off in checklist "Checklist 1" for user "student3":
      | itemtext | studentmark |
      | Item 1   | yes         |
    And the following items are checked off in checklist "Checklist 1" for user "student6":
      | itemtext | studentmark |
      | Item 3   | yes         |
    And the following items are checked off in checklist "Checklist 2" for user "student1":
      | itemtext | studentmark |
      | Item 5   | yes         |
      | Item 6   | yes         |
    And the following items are checked off in checklist "Checklist 2" for user "student2":
      | itemtext | studentmark |
      | Item 4   | yes         |
      | Item 6   | yes         |
    And the following items are checked off in checklist "Checklist 2" for user "student3":
      | itemtext | studentmark |
      | Item 4   | yes         |
    And the following items are checked off in checklist "Checklist 2" for user "student6":
      | itemtext | studentmark |
      | Item 5   | yes         |

  Scenario: A teacher can filter checklist progress by group in separate groups mode
    Given I am on the "Checklist 1" "checklist activity" page logged in as "teacher1"
    And I follow "View progress"
    When I set the field "Separate groups" to "Group 1"
    Then I should see "Student 1"
    And I should see "Student 2"
    And I should see "Student 3"
    And I should see "Student 4"
    And I should see "Student 5"
    And I should not see "Student 6"
    And I should not see "Student 7"
    And I should not see "Student 8"

    When I set the field "Separate groups" to "Group 2"
    Then I should see "Student 1"
    And I should not see "Student 2"
    And I should not see "Student 3"
    And I should not see "Student 4"
    And I should not see "Student 5"
    And I should see "Student 6"
    And I should see "Student 7"
    And I should not see "Student 8"

    When I set the field "Separate groups" to "Group 3"
    Then I should not see "Student 1"
    And I should not see "Student 2"
    And I should not see "Student 3"
    And I should not see "Student 4"
    And I should not see "Student 5"
    And I should not see "Student 6"
    And I should not see "Student 7"
    And I should not see "Student 8"

    When I set the field "Separate groups" to "All participants"
    Then I should see "Student 1"
    And I should see "Student 2"
    And I should see "Student 3"
    And I should see "Student 4"
    And I should see "Student 5"
    And I should see "Student 6"
    And I should see "Student 7"
    And I should see "Student 8"

  Scenario: A teacher can filter checklist progress by group in visible groups mode
    Given I am on the "Checklist 2" "checklist activity" page logged in as "teacher1"
    And I follow "View progress"
    When I set the field "Visible groups" to "Group 1"
    Then I should see "Student 1"
    And I should see "Student 2"
    And I should see "Student 3"
    And I should see "Student 4"
    And I should see "Student 5"
    And I should not see "Student 6"
    And I should not see "Student 7"
    And I should not see "Student 8"

    When I set the field "Visible groups" to "Group 2"
    Then I should see "Student 1"
    And I should not see "Student 2"
    And I should not see "Student 3"
    And I should not see "Student 4"
    And I should not see "Student 5"
    And I should see "Student 6"
    And I should see "Student 7"
    And I should not see "Student 8"

    When I set the field "Visible groups" to "Group 3"
    Then I should not see "Student 1"
    And I should not see "Student 2"
    And I should not see "Student 3"
    And I should not see "Student 4"
    And I should not see "Student 5"
    And I should not see "Student 6"
    And I should not see "Student 7"
    And I should not see "Student 8"

    When I set the field "Visible groups" to "All participants"
    Then I should see "Student 1"
    And I should see "Student 2"
    And I should see "Student 3"
    And I should see "Student 4"
    And I should see "Student 5"
    And I should see "Student 6"
    And I should see "Student 7"
    And I should see "Student 8"
