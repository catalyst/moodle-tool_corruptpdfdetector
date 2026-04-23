@tool @tool_corruptpdfdetector
Feature: View the corrupt PDF submissions report
  In order to monitor corrupt PDF assignment submissions
  As a site administrator
  I need to be able to view the corrupt PDF detector report page

  Background:
    Given I log in as "admin"

  Scenario: Admin can navigate to the corrupt PDF report via site administration
    When I navigate to "Server > Corrupt pdf assignment finder" in site administration
    Then I should see "Detected corrupt pdf submissions"
    And I should see "Faulty pdf file percentage:"

  Scenario: The report page shows the correct table columns when empty
    When I navigate to "Server > Corrupt pdf assignment finder" in site administration
    Then I should see "Detected corrupt pdf submissions"
    And I should see "Course"
    And I should see "Assignment"
    And I should see "Student name"
    And I should see "Email"
    And I should see "Reason"
    And I should see "Last modified"
    And I should see "Detected"
    And I should see "Fixed"

  Scenario: The error percentage shows 0 when there are no detections
    When I navigate to "Server > Corrupt pdf assignment finder" in site administration
    Then I should see "Faulty pdf file percentage: 0"

  Scenario: Non-admin users cannot access the corrupt PDF report
    Given I log out
    And the following "users" exist:
      | username | firstname | lastname | email          |
      | teacher1 | Teacher   | One      | t1@example.com |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
    When I log in as "teacher1"
    And I visit "/admin/tool/corruptpdfdetector/index.php"
    Then I should not see "Detected corrupt pdf submissions"

  Scenario: Report page shows an unfixed detection record
    Given the following "tool_corruptpdfdetector > detections" exist:
      | coursename | assignname   | userfullname | email         | filename   | message        | fixed |
      | Course 1   | Assignment 1 | Alice Smith  | alice@example.com | fakehash01 | Invalid header | 0     |
    When I navigate to "Server > Corrupt pdf assignment finder" in site administration
    Then I should see "Course 1"
    And I should see "Assignment 1"
    And I should see "Alice Smith"
    And I should see "alice@example.com"
    And I should see "Invalid header"
    And I should see "No" in the "Alice Smith" "table_row"

  Scenario: A fixed detection record is shown with Fixed = Yes
    Given the following "tool_corruptpdfdetector > detections" exist:
      | coursename | assignname   | userfullname | email         | filename   | message        | fixed |
      | Course 1   | Assignment 1 | Alice Smith  | alice@example.com | fakehash02 | Invalid header | 1     |
    When I navigate to "Server > Corrupt pdf assignment finder" in site administration
    Then I should see "Yes" in the "Alice Smith" "table_row"

  Scenario: Multiple detection records are all shown in the report
    Given the following "tool_corruptpdfdetector > detections" exist:
      | coursename | assignname   | userfullname | email          | filename   | message        | fixed |
      | Course 1   | Assignment 1 | Alice Smith  | alice@test.com | fakehash03 | Invalid header | 0     |
      | Course 2   | Assignment 2 | Bob Jones    | bob@test.com   | fakehash04 | Missing xref   | 0     |
    When I navigate to "Server > Corrupt pdf assignment finder" in site administration
    Then I should see "Alice Smith"
    And I should see "Bob Jones"
    And I should see "Course 1"
    And I should see "Course 2"

