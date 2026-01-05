@mod @mod_coursesat
Feature: A teacher can set three types of coursesat activity
  In order to use verified coursesat instruments
  As a teacher
  I need to set coursesat activities and select which coursesat type suits my needs

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
    And I log in as "teacher1"

  Scenario: Switching between the three coursesat types
    Given the following "activities" exist:
      | activity | name             | course | idnumber  |
      | coursesat   | Test coursesat name | C1     | coursesat1   |
    And I am on the "Test coursesat name" "coursesat activity editing" page
    And I set the following fields to these values:
      | coursesat type | ATTLS (20 item version) |
    And I press "Save and display"
    Then I should see "Attitudes Towards Thinking and Learning"
    And I navigate to "Settings" in current page administration
    And I set the following fields to these values:
      | coursesat type | Critical incidents |
    And I press "Save and display"
    And I should see "At what moment in class were you most engaged as a learner?"
    And I navigate to "Settings" in current page administration
    And I set the following fields to these values:
      | coursesat type | COLLES (Preferred and Actual) |
    And I press "Save and display"
    And I should see "In this online unit..."
    And I should see "my learning focuses on issues that interest me."

  Scenario: coursesat activity is created via UI
    Given I add a coursesat activity to course "Course 1" section "1"
    And I set the following fields to these values:
      | Name        | Test coursesat name        |
      | Description | Test coursesat description |
      | coursesat type | ATTLS (20 item version) |
    When I press "Save and return to course"
    Then I should see "Test coursesat name"
