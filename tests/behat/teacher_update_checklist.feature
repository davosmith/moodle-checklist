@mod @mod_checklist @checklist
Feature: Teacher update checklist works as expected

  Background:
    Given the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "activities" exist:
      | activity  | name           | intro               | course | section | idnumber | teacheredit |
      | checklist | Test checklist | This is a checklist | C1     | 1       | CHK001   | 1           |
    And the following "users" exist:
      | username | firstname | lastname | email            |
      | teacher1 | Teacher   | 1        | teacher1@asd.com |
      | student1 | Student 1 | -        | student1@asd.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following items exist in checklist "Test checklist":
      | text                      | required |
      | Checklist required item 1 | required |
      | Checklist required item 2 | required |
      | Checklist required item 3 | required |
      | Checklist optional item 4 | optional |
      | Checklist optional item 5 | optional |

  @javascript
  Scenario: A teacher updates a checklist from the report overview and the student can see it
    Given I am on the "Test checklist" "checklist activity" page logged in as "teacher1"
    And I follow "View progress"
    When I press "Edit checks"
    # Row 3 = first row with a student in it, Item 3 = checklist item 1.
    And I set the field with xpath "//table[contains(@class,'checklistreport')]//tr[3]/td[3]/select" to "Yes"
    # Row 3 = first row with a student in it, Item 4 = checklist item 2.
    And I set the field with xpath "//table[contains(@class,'checklistreport')]//tr[3]/td[4]/select" to "No"
    # Row 3 = first row with a student in it, Item 7 = checklist item 5.
    And I set the field with xpath "//table[contains(@class,'checklistreport')]//tr[3]/td[7]/select" to "Yes"
    And I press "Save"
    And ".level0-checked.c1" "css_element" should exist in the "Student 1" "table_row"
    And ".level0-unchecked.c2" "css_element" should exist in the "Student 1" "table_row"
    And ".level0-checked.c3" "css_element" should not exist in the "Student 1" "table_row"
    And ".level0-checked.c4" "css_element" should not exist in the "Student 1" "table_row"
    And ".level0-checked.c5" "css_element" should exist in the "Student 1" "table_row"
    And I log out
    And I am on the "Test checklist" "checklist activity" page logged in as "student1"
    Then ".teachermarkyes" "css_element" should exist in the "Checklist required item 1" "list_item"
    And ".teachermarkno" "css_element" should exist in the "Checklist required item 2" "list_item"
    And ".teachermarkundecided" "css_element" should exist in the "Checklist required item 3" "list_item"
    And ".teachermarkundecided" "css_element" should exist in the "Checklist optional item 4" "list_item"
    And ".teachermarkyes" "css_element" should exist in the "Checklist optional item 5" "list_item"
    And I should see "33%" in the "#checklistprogressrequired" "css_element"
    And I should see "40%" in the "#checklistprogressall" "css_element"

  @javascript
  Scenario: A teacher clicks 'Toggle Row' and all items are updated
    Given I am on the "Test checklist" "checklist activity" page logged in as "teacher1"
    And I follow "View progress"
    When I press "Edit checks"
    And I click on "Toggle Row" "button" in the "Student 1" "table_row"
    And I press "Save"
    And ".level0-checked.c1" "css_element" should exist in the "Student 1" "table_row"
    And ".level0-checked.c2" "css_element" should exist in the "Student 1" "table_row"
    And ".level0-checked.c3" "css_element" should exist in the "Student 1" "table_row"
    And ".level0-checked.c4" "css_element" should exist in the "Student 1" "table_row"
    And ".level0-checked.c5" "css_element" should exist in the "Student 1" "table_row"

  @javascript
  Scenario: A teacher can update a student's checkmarks individually.
    Given I am on the "Test checklist" "checklist activity" page logged in as "teacher1"
    And I follow "View progress"
    When I click on "View progress for this user" "link" in the "Student 1" "table_row"
    And I set the following fields to these values:
      | Checklist required item 2 | Yes |
      | Checklist required item 3 | No  |
      | Checklist optional item 4 | Yes |
      | Checklist optional item 5 | Yes |
    # Lowercase 'save' to avoid clash with hidden 'Save' element.
    And I press "save"
    Then I should see "33%" in the "#checklistprogressrequired" "css_element"
    And I should see "60%" in the "#checklistprogressall" "css_element"
    And I press "View all students"
    And ".level0-checked.c1" "css_element" should not exist in the "Student 1" "table_row"
    And ".level0-checked.c2" "css_element" should exist in the "Student 1" "table_row"
    And ".level0-unchecked.c3" "css_element" should exist in the "Student 1" "table_row"
    And ".level0-checked.c4" "css_element" should exist in the "Student 1" "table_row"
    And ".level0-checked.c5" "css_element" should exist in the "Student 1" "table_row"

  Scenario: A teacher can view the results of multiple students via the 'next' button
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | student2 | Student 2 | -        | student2@example.com |
      | student3 | Student 3 | -        | student3@example.com |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student2 | C1     | student |
      | student3 | C1     | student |
    And the following items are checked off in checklist "Test checklist" for user "student1":
      | itemtext                  | teachermark |
      | Checklist required item 1 | yes         |
      | Checklist required item 2 | yes         |
      | Checklist optional item 5 | yes         |
    And the following items are checked off in checklist "Test checklist" for user "student2":
      | itemtext                  | teachermark |
      | Checklist required item 1 | no          |
      | Checklist required item 2 | yes         |
      | Checklist required item 3 | yes         |
      | Checklist optional item 4 | yes         |
    And I am on the "Test checklist" "checklist activity" page logged in as "teacher1"
    And I follow "View progress"
    And I click on "View progress for this user" "link" in the "Student 1" "table_row"
    And I should see "Checklist for Student 1"
    And I should not see "Checklist for Student 2"
    And the following fields match these values:
      | Checklist required item 1 | Yes |
      | Checklist required item 2 | Yes |
      | Checklist required item 3 |     |
      | Checklist optional item 4 |     |
      | Checklist optional item 5 | Yes |
    When I press "Next"
    Then I should see "Checklist for Student 2"
    And I should not see "Checklist for Student 1"
    And the following fields match these values:
      | Checklist required item 1 | No  |
      | Checklist required item 2 | Yes |
      | Checklist required item 3 | Yes |
      | Checklist optional item 4 | Yes |
      | Checklist optional item 5 |     |
    When I press "Next"
    Then I should see "Checklist for Student 3"
    And I should not see "Checklist for Student 2"
    And the following fields match these values:
      | Checklist required item 1 |  |
      | Checklist required item 2 |  |
      | Checklist required item 3 |  |
      | Checklist optional item 4 |  |
      | Checklist optional item 5 |  |
    And I should not see "Next"
