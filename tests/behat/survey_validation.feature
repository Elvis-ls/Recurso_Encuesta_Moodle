@mod @mod_coursesat @javascript
Feature: When some answers are not selected, the coursesat should not be submitted
    In order to submit valid responses
    As a student
    I need to fill values

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Student   | 1        | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |
    And I enable "coursesat" "mod" plugin

  Scenario: Require coursesat view
    Given the following "activities" exist:
      | activity | name             | course |
      | coursesat   | Test coursesat name | C1     |
    And I am on the "Test coursesat name" "coursesat activity" page logged in as "student1"
    When I press "Submit"
    Then I should see "Some of the multiple choice questions have not been answered." in the "Error" "dialogue"
