@mod @mod_checklist @checklist
Feature: Student checklist can track completion of other activities

  Background:
    Given the following "courses" exist:
      | fullname | shortname | enablecompletion |
      | Course 1 | C1        | 1                |
    And the following "activities" exist:
      | activity  | name           | intro               | course | section | idnumber | teacheredit | autopopulate | autoupdate |
      | checklist | Test checklist | This is a checklist | C1     | 1       | CHK001   | 0           | 2            | 2          |
    And the following "activities" exist:
      | activity | name        | intro       | course | section | idnumber | content                                       | completion | completionview |
      | page     | Test page 1 | Test page 1 | C1     | 1       | PGE001   | This page 1 should be complete when I view it | 2          | 1              |
      | page     | Test page 2 | Test page 2 | C1     | 1       | PGE002   | This page 2 should be complete when I view it | 2          | 1              |
    And the following "users" exist:
      | username | firstname | lastname | email            |
      | teacher1 | Teacher   | 1        | teacher1@asd.com |
      | student1 | Student   | 1        | student1@asd.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |

  Scenario: The checklist should always display the current items from the section, keeping up to date when they change.
    When I am on the "Test checklist" "checklist activity" page logged in as "teacher1"
    Then "Topic 1" "text" should appear before "Test page 1" "text"
    And "Test page 1" "text" should appear before "Test page 2" "text"
    # Check that changes to the course are tracked.
    When I follow "Course 1"
    And I follow "Test page 2"
    # Workaround for differences between M3.9 "Edit settings" and M4.0 "Settings".
    And I navigate to "ettings" in current page administration
    And I set the field "Name" to "Updated name to page 5"
    And I press "Save and return to course"
    And I follow "Test checklist"
    Then "Topic 1" "text" should appear before "Test page 1" "text"
    And "Test page 1" "text" should appear before "Updated name to page 5" "text"

  Scenario: Checklist names should update even when viewed by a student (without editing permission).
    When I am on the "Test checklist" "checklist activity" page logged in as "teacher1"
    # Check that changes to the course are tracked.
    When I follow "Course 1"
    And I follow "Test page 2"
    # Workaround for differences between M3.9 "Edit settings" and M4.0 "Settings".
    And I navigate to "ettings" in current page administration
    And I set the field "Name" to "Updated name to page 5"
    And I press "Save and return to course"
    And I log out
    And I am on the "Test checklist" "checklist activity" page logged in as "student1"
    Then "Topic 1" "text" should appear before "Test page 1" "text"
    And "Test page 1" "text" should appear before "Updated name to page 5" "text"

  @javascript
  Scenario: The checklist state should update to reflect the completion of imported activities.
    Given I am on the "Test checklist" "checklist activity" page logged in as "student1"
    And the following fields match these values:
      | Test page 1 | 0 |
      | Test page 2 | 0 |
    When I click on "Activity associated with this item" "link"
    And I should see "This page 1 should be complete when I view it"
    And I am on the "Test checklist" "checklist activity" page
    Then the following fields match these values:
      | Test page 1 | 1 |
      | Test page 2 | 0 |

  @javascript
  Scenario: The checklist state should update based on logs, if completion is disabled.
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    # Workaround for differences between M3.9 "Edit settings" and M4.0 "Settings".
    And I navigate to "ettings" in current page administration
    And I expand all fieldsets
    And I set the field "Enable completion tracking" to "No"
    And I press "Save and display"
    And I log out
    And I am on the "Test checklist" "checklist activity" page logged in as "student1"
    And the following fields match these values:
      | Test page 1 | 0 |
      | Test page 2 | 0 |
    When I click on "Activity associated with this item" "link"
    And I should see "This page 1 should be complete when I view it"
    And I am on the "Test checklist" "checklist activity" page
    Then the following fields match these values:
      | Test page 1 | 1 |
      | Test page 2 | 0 |
