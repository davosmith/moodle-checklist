@mod @mod_checklist @checklist
Feature: A teacher can attach a link to an external URL to a checklist item

  Background:
    Given the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "activities" exist:
      | activity  | name           | intro               | course | section | idnumber | teacheredit |
      | checklist | Test checklist | This is a checklist | C1     | 1       | CHK001   | 0           |
    And the following "users" exist:
      | username | firstname | lastname | email            |
      | teacher1 | Teacher   | 1        | teacher1@asd.com |
      | student1 | Student   | 1        | student1@asd.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "activities" exist:
      | activity | name   | content           | course | idnumber |
      | page     | Page 1 | Welcome to page 1 | C1     | PG01     |

  Scenario: A teacher links to an external website and then follows that link
    Given I am on the "Test checklist" "checklist activity" page logged in as "teacher1"
    And "linkcourseid" "select" should not exist
    When I set the following fields to these values:
      | displaytext | Item with link   |
      | linkurl     | www.google.co.uk |
    And I press "Add"
    And I follow "Edit this item"
    Then the following fields match these values:
      | displaytext | Item with link          |
      | linkurl     | http://www.google.co.uk |

    When I set the following fields to these values:
      | displaytext | Item with link (edited) |
    And I set the field "linkurl" to the view URL for activity "PG01"
    And I press "Update"
    And I am on the "Test checklist" "checklist activity" page
    Then I should see "Item with link (edited)"
    And I click on "Link associated with this item" "link" in the "Item with link (edited)" "list_item"
    And I should see "Welcome to page 1"
