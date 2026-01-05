@mod @mod_coursesat @core_completion @javascript
Feature: A teacher can use activity completion to track a student progress
  In order to use activity completion
  As a teacher
  I need to set coursesat activities and enable activity completion

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher | 1 | teacher1@example.com |
      | student1 | Student | 1 | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category | enablecompletion |
      | Course 1 | C1 | 0 | 1 |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
      | student1 | C1 | student |
    And I enable "coursesat" "mod" plugin
    And I log in as "teacher1"

  Scenario: Require coursesat view
    Given the following "activities" exist:
      | activity   | name                   | course | idnumber    | template | completion | completionview | completionsubmit |
      | coursesat     | Test coursesat name       | C1     | coursesat1     |  5       | 2          | 1              | 0                |
    And I am on the "Test coursesat name" "coursesat activity" page
    # Teacher view.
    And "Test coursesat name" should have the "View" completion condition
    # Student view.
    When I am on the "Course 1" course page logged in as student1
    And the "View" completion condition of "Test coursesat name" is displayed as "todo"
    And I follow "Test coursesat name"
    And I am on "Course 1" course homepage
    Then the "View" completion condition of "Test coursesat name" is displayed as "done"

  Scenario: Require coursesat submission
    Given the following "activities" exist:
      | activity   | name                   | course | idnumber    | template | completion | completionview | completionsubmit |
      | coursesat     | Test coursesat name       | C1     | coursesat1     | 5        | 2          | 1              | 1                |
    And I am on the "Test coursesat name" "coursesat activity" page
    # Teacher view.
    And "Test coursesat name" should have the "Submit answers" completion condition
    # Student view.
    When I am on the "Course 1" course page logged in as student1
    And the "Submit answers" completion condition of "Test coursesat name" is displayed as "todo"
    And I follow "Test coursesat name"
    And the "Submit answers" completion condition of "Test coursesat name" is displayed as "todo"
    And I press "Submit"
    And I am on "Course 1" course homepage
    And the "Submit answers" completion condition of "Test coursesat name" is displayed as "done"
    And I follow "Test coursesat name"
    And the "Submit answers" completion condition of "Test coursesat name" is displayed as "done"

  Scenario: A student can manually mark the coursesat activity as done but a teacher cannot
    Given the following "activities" exist:
      | activity   | name                   | course | idnumber    | completion |
      | coursesat     | Test coursesat name       | C1     | coursesat1     | 1          |
    And I am on "Course 1" course homepage
    # Teacher view.
    And "Test coursesat name" should have the "Mark as done" completion condition
    # Student view.
    When I am on the "coursesat1" Activity page logged in as student1
    Then the manual completion button of "Test coursesat name" is displayed as "Mark as done"
    And I toggle the manual completion state of "Test coursesat name"
    And the manual completion button of "Test coursesat name" is displayed as "Done"
