@mod @mod_checklist @checklist
Feature: Teacher marks can be set to locked once updated

  Background:
    Given the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "activities" exist:
      | activity  | name           | intro               | course | section | idnumber | teacheredit | lockteachermarks |
      | checklist | Test checklist | This is a checklist | C1     | 1       | CHK001   | 1           | 1                |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
      | teacher2 | Teacher   | 2        | teacher2@example.com |
      | student1 | Student 1 | -        | student1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | teacher2 | C1     | teacher        |
      | student1 | C1     | student        |
    And the following items exist in checklist "Test checklist":
      | text                      | required |
      | Checklist required item 1 | required |
      | Checklist required item 2 | required |
      | Checklist required item 3 | required |
      | Checklist optional item 4 | optional |
      | Checklist optional item 5 | optional |

  Scenario: A non-editing teacher can set 'Yes' marks, but cannot change them afterwards
    Given I am on the "Test checklist" "checklist activity" page logged in as "teacher2"
    And I follow "View progress"
    And I click on "View progress for this user" "link" in the "Student 1" "table_row"
    And I should see "Once you have saved these marks, you will be unable to change any 'Yes' marks"
    When I set the following fields to these values:
      | Checklist required item 2 | Yes |
      | Checklist required item 3 | No  |
      | Checklist optional item 4 | Yes |
    And I press "Save"
    Then the "Checklist required item 2" "select" should be disabled
    And the "Checklist optional item 4" "select" should be disabled
    # The teacher can still change the settings for these items.
    And I set the following fields to these values:
      | Checklist required item 1 | Yes |
      | Checklist required item 3 |     |
      | Checklist optional item 5 | No  |

  Scenario: An editing teacher can change 'Yes' marks.
    Given I am on the "Test checklist" "checklist activity" page logged in as "teacher1"
    And I follow "View progress"
    And I click on "View progress for this user" "link" in the "Student 1" "table_row"
    And I should not see "Once you have saved these marks, you will be unable to change any 'Yes' marks"
    When I set the following fields to these values:
      | Checklist required item 2 | Yes |
      | Checklist required item 3 | No  |
      | Checklist optional item 4 | Yes |
    And I press "Save"
    Then I set the following fields to these values:
      | Checklist required item 1 | Yes |
      | Checklist required item 2 | No  |
      | Checklist required item 3 |     |
      | Checklist optional item 4 | No  |
      | Checklist optional item 5 | No  |

  Scenario: A student does not see the warning message about being unable to change the marks
    When I am on the "Test checklist" "checklist activity" page logged in as "student1"
    Then I should not see "Once you have saved these marks, you will be unable to change any 'Yes' marks"
