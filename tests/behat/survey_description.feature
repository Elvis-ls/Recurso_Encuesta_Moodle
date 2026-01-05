@mod @mod_coursesat
Feature: The default introduction is displayed when the activity description is empty

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher | 1 | teacher1@example.com |
    And the following "courses" exist:
      | fullname | shortname | format |
      | Course 1 | C1 | topics |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
    And I enable "coursesat" "mod" plugin
    And the following "activities" exist:
      | activity | name             | course | idnumber  | template |
      | coursesat   | Test coursesat name | C1     | coursesat1   | 1        |

  Scenario: Display the default coursesat introduction when activity description is empty
    Given I am on the "Test coursesat name" "coursesat activity" page logged in as "teacher1"
    And I should see "Test coursesat 1"
    When I am on the "Test coursesat name" "coursesat activity editing" page
    And I set the following fields to these values:
      | Description |  |
    And I press "Save and display"
    Then I should see "The purpose of this coursesat is to help us understand"
    And I am on the "Test coursesat name" "coursesat activity editing" page
    And I set the following fields to these values:
      | coursesat type | ATTLS (20 item version) |
    And I press "Save and display"
    And I should see "The purpose of this questionnaire is to help us evaluate"
