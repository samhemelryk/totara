<?php

// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.


/**
 * Block for displayed logged in user's course completion status
 *
 * @package   moodlecore
 * @copyright 2009 Catalyst IT Ltd
 * @author    Aaron Barnes <aaronb@catalyst.net.nz>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once('../../config.php');
require_once($CFG->libdir.'/completionlib.php');


///
/// Load data
///
$id = required_param('course', PARAM_INT);

// Load course
$course = get_record('course', 'id', $id);

// Check permissions
require_login($course);

// Don't display if completion isn't enabled!
if (!$course->enablecompletion) {
    error('completion not enabled');
}

// Load criteria to display
$info = new completion_info($course);
$completions = $info->get_completions($USER->id);

// Check if this course has any criteria
if (empty($completions)) {
    error('no criteria');
}

// Check this user is enroled
$users = $info->internal_get_tracked_users(true);
if (!in_array($USER->id, array_keys($users))) {
    error(get_string('notenroled', 'completion'));
}


///
/// Display page
///

// Print header
$page = get_string('completionprogressdetails', 'block_completionstatus');
$title = format_string($course->fullname) . ': ' . $page;

$navlinks[] = array('name' => $page, 'link' => null, 'type' => 'misc');
$navigation = build_navigation($navlinks);

print_header($title, $title, $navigation);
print_heading($title);


// Display completion status
echo '<table class="generalbox boxaligncenter"><tbody>';
echo '<tr><td colspan="2"><b>'.get_string('status').':</b> ';

// Is course complete?
$coursecomplete = $info->is_course_complete($USER->id);

// Has this user completed any criteria?
$criteriacomplete = $info->count_course_user_data($USER->id);

#if ($pending_update) {
#    echo '<i>'.get_string('pending', 'completion').'</i>';
if ($coursecomplete) {
    echo get_string('complete');
} else if (!$criteriacomplete) {
    echo '<i>'.get_string('notyetstarted', 'completion').'</i>';
} else {
    echo '<i>'.get_string('inprogress','completion').'</i>';
}

echo '</td></tr>';
echo '<tr><td colspan="2"><b>'.get_string('required').':</b> ';

// Get overall aggregation method
$overall = $info->get_aggregation_method();

if ($overall == COMPLETION_AGGREGATION_ALL) {
    echo get_string('criteriarequiredall', 'completion');
} else {
    echo get_string('criteriarequiredany', 'completion');
}

echo '</td></tr></tbody></table>';

// Generate markup for criteria statuses
echo '<table class="generalbox boxaligncenter" cellpadding="3"><tbody>';
echo '<tr class="ccheader">';
echo '<th class="c0 header" scope="col">'.get_string('criteriagroup', 'block_completionstatus').'</th>';
echo '<th class="c1 header" scope="col">'.get_string('criteria', 'completion').'</th>';
echo '<th class="c2 header" scope="col">'.get_string('requirement', 'block_completionstatus').'</th>';
echo '<th class="c3 header" scope="col">'.get_string('status').'</th>';
echo '<th class="c4 header" scope="col">'.get_string('complete').'</th>';
echo '<th class="c5 header" scope="col">'.get_string('completiondate', 'coursereport_completion').'</th>';
echo '</tr>';

// Save row data
$rows = array();

global $COMPLETION_CRITERIA_TYPES;

// Loop through course criteria
foreach ($completions as $completion) {
    $criteria = $completion->get_criteria();
    $complete = $completion->is_complete();

    $row = array();
    $row['type'] = $criteria->criteriatype;
    $row['title'] = $criteria->get_title();
    $row['status'] = $completion->get_status();
    $row['timecompleted'] = $completion->timecompleted;
    $row['details'] = $criteria->get_details($completion);
    $rows[] = $row;
}

// Print table
$last_type = '';
$agg_type = false;

foreach ($rows as $row) {

    // Criteria group
    echo '<td class="c0">';
    if ($last_type !== $row['details']['type']) {
        $last_type = $row['details']['type'];
        echo $last_type;

        // Reset agg type
        $agg_type = true;
    } else {
        // Display aggregation type
        if ($agg_type) {
            $agg = $info->get_aggregation_method($row['type']);

            echo '(<i>';

            if ($agg == COMPLETION_AGGREGATION_ALL) {
                echo strtolower(get_string('all', 'completion'));
            } else {
                echo strtolower(get_string('any', 'completion'));
            }

            echo '</i> '.strtolower(get_string('required')).')';
        } else {
            $agg_type = false;
        }
    }
    echo '</td>';

    // Criteria title
    echo '<td class="c1">';
    echo $row['details']['criteria'];
    echo '</td>';

    // Requirement
    echo '<td class="c2">';
    echo $row['details']['requirement'];
    echo '</td>';

    // Status
    echo '<td class="c3">';
    echo $row['details']['status'];
    echo '</td>';

    // Is complete
    echo '<td class="c4">';
    echo ($row['status'] === 'Yes') ? 'Yes' : 'No';
    echo '</td>';

    // Completion data
    echo '<td class="c5">';
    if ($row['timecompleted']) {
        echo userdate($row['timecompleted'], '%e %B %G');
    } else {
        echo '-';
    }
    echo '</td>';
    echo '</tr>';
}

echo '</tbody></table>';

/*
    *
$shtml = '<tr><td><b>'.get_string('requiredcriteria', 'completion').'</b></td><td style="text-align: right"><b>'.get_string('status').'</b></td></tr>';

// For aggregating activity completion
$activities = array();
$activities_complete = 0;

// Flag to set if current completion data is inconsistent with
// what is stored in the database
$pending_update = false;

// Loop through course criteria
foreach ($completions as $completion) {

    $criteria = $completion->get_criteria();
    $complete = $completion->is_complete();

    if (!$pending_update && $criteria->is_pending($completion)) {
        $pending_update = true;
    }

    // Activities are a special case, so cache them and leave them till last
    if ($criteria->criteriatype == COMPLETION_CRITERIA_TYPE_ACTIVITY) {
        $activities[$criteria->moduleinstance] = $complete;

        if ($complete) {
            $activities_complete++;
        }

        continue;
    }

    $shtml .= '<tr><td>';
    $shtml .= $criteria->get_title();
    $shtml .= '</td><td style="text-align: right">';
    $shtml .= $completion->get_status();
    $shtml .= '</td></tr>';
}

// Aggregate activities
if (!empty($activities)) {

    $shtml .= '<tr><td>';
    $shtml .= 'Activities complete';
    $shtml .= '</td><td style="text-align: right">';
    $shtml .= $activities_complete.' of '.count($activities);
    $shtml .= '</td></tr>';
}
 */

print_footer($course);