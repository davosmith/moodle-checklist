<?php

// This lists the fields to be included from the 'user' table
// (checklist items will appear in the columns to the right of these fields)
// You can include either standard user field names or custom fields
// There is also a special '_groups' field, that lists all the groups the user is a member of

// The second part of each array entry is the text to appear at the top of the column

$checklist_report_user_columns = Array(
                                       //'region' => 'Region',    // Requested by a specific client
                                       //'district' => 'District', // Requested by a specific client
                                       'lastname' => get_string('lastname'),
                                       'firstname' => get_string('firstname'),
                                       'username' => get_string('username'),
                                       '_groups' => 'Groups(s)',
                                       //'role' => 'Position', // Requested by a specific client
                                       //'dealername' => 'Dealer Name', // Requested by a specific client
                                       //'dealernumber' => 'Dealer #', // Requested by a specific client
                                       '_enroldate' => get_string('enroldate', 'gradeexport_checklist'),
                                       '_startdate' => get_string('startdate', 'gradeexport_checklist'),
                                       '_percent' => get_string('percent', 'gradeexport_checklist') // Percentage of items student has completed
                                       );

// The output from the default setting above would be:
// | Surname | First name | Username | Groups(s)        | Checklistitem1 | Checklistitem2 | etc.
// | Smith   | Bob        | bobsmith | Group A, Group B |                |              1 |
// Where '1' indicates the item is checked-off
