<?php

// Edit course reminder settings

require_once(dirname(__FILE__).'/../config.php');
require_once($CFG->libdir.'/reminderlib.php');
require_once($CFG->dirroot.'/course/reminders_form.php');


// Reminder we are currently editing
$id = optional_param('id', 0, PARAM_INT);

// Course id
$courseid = required_param('courseid', PARAM_INT);


// Basic access control checks
if ($courseid) { // editing course

    if($courseid == SITEID){
        // don't allow editing of  'site course' using this from
        error('You cannot edit the site course using this form');
    }

    if (!$course = get_record('course', 'id', $courseid)) {
        error('Course ID was incorrect');
    }

    require_login($course->id);
    require_capability('moodle/site:doanything', get_context_instance(CONTEXT_COURSE, $course->id));

} else {
    require_login();
    error('Either course id or category must be specified');
}


// Get all course reminders
$reminders = get_course_reminders($course->id);


// Get current reminder
// Specified in get params
if (in_array($id, array_keys($reminders))) {
    $reminder = $reminders[$id];
}
// Grab the first one
elseif (count($reminders) && $id === 0) {
    $reminder = reset($reminders);
}
// Otherwise we must be creating a new one
else {
    $reminder = new reminder();
    $reminder->courseid = $course->id;
}


// Load all form data
$formdata = $reminder->get_form_data();

// First create the form
$reminderform = new reminder_edit_form('reminders.php', compact('course', 'reminder'));
$reminderform->set_data($formdata);


    // Process current action
if ($reminderform->is_cancelled()){
    redirect($CFG->wwwroot.'/course/view.php?id='.$course->id);

} else if ($data = $reminderform->get_data()) {

    begin_sql();

    $config = array(
        'tracking' => $data->tracking
    );

    // Create the reminder object
    $reminder->timemodified = time();
    $reminder->modifierid = $USER->id;
    $reminder->deleted = '0';
    $reminder->title = stripslashes($data->title);
    $reminder->type = 'completion';
    $reminder->config = serialize($config);
    $reminder->timecreated = $reminder->timecreated ? $reminder->timecreated : $reminder->timemodified;

    if (empty($reminder->id)) {
        if (!$reminder->insert()) {
            rollback_sql();
            error('Could not insert reminder record');
        }
    }
    else {
        if (!$reminder->update()) {
            rollback_sql();
            error('Could not update reminder record');
        }
    }

    // Create the messages
    foreach (array('invitation', 'reminder', 'escalation') as $mtype) {
        $nosend = "{$mtype}dontsend";
        $p = "{$mtype}period";
        $sm = "{$mtype}skipmanager";
        $s = "{$mtype}subject";
        $m = "{$mtype}message";

        $message = new reminder_message(
            array(
                'reminderid'    => $reminder->id,
                'type'          => $mtype,
                'deleted'       => 0
            )
        );

        // Do some unique stuff for escalation messages
        if ($mtype === 'escalation') {
            if (!empty($data->$nosend)) {
                // Delete any existing message
                if ($message->id) {
                    $message->deleted = 1;

                    if (!$message->update()) {
                        rollback_sql();
                        error('Could not delete reminder message record');
                    }
                }

                // Do not create a new one
                continue;
            }
        }

        $message->period = $data->$p;
        $message->copyto = isset($data->$sm) ? $data->$sm : '';
        $message->subject = $data->$s;
        $message->message = $data->$m;
        $message->deleted = 0;

        if (empty($message->id)) {
            if (!$message->insert()) {
                rollback_sql();
                error('Could not insert reminder message record');
            }
        }
        else {
            if (!$message->update()) {
                rollback_sql();
                error('Could not update reminder message record');
            }
        }
    }

    commit_sql();

    redirect($CFG->wwwroot."/course/reminders.php?courseid={$course->id}&id=".$reminder->id);
}


// Print the page

$site = get_site();

$streditcoursereminders = get_string('editcoursereminders', 'reminders');
$navlinks = array();
$navlinks[] = array('name' => $streditcoursereminders,
                    'link' => null,
                    'type' => 'misc');
$title = $streditcoursereminders;
$fullname = $course->fullname;

$navigation = build_navigation($navlinks);
print_header($title, $fullname, $navigation, $reminderform->focus());
print_heading($streditcoursereminders);


// Check if there are any activites we can use
$completion = new completion_info($course);


// Show tabs
$tabs = array();
foreach ($reminders as $r) {
    $tabs[] = new tabobject($r->id, $CFG->wwwroot.'/course/reminders.php?courseid='.$course->id.'&id='.$r->id, $r->title);
}

$tabs[] = new tabobject('new', $CFG->wwwroot.'/course/reminders.php?courseid='.$course->id.'&id=-1', get_string('new', 'reminders'));

if ($reminder->id < 1) {
    $reminder->id = 'new';
}

// If no current reminders or creating a new reminder, and no activites - do not show form
if (!$completion->is_enabled()) {

    print_box(get_string('noactivitieswithcompletionenabled', 'reminders'), 'generalbox adminerror boxwidthwide boxaligncenter');
    print_continue($CFG->wwwroot.'/course/view.php?id='.$course->id);

} else {

    if (count($tabs)) {
        print_tabs(array($tabs), $reminder->id);
    }

    // Show form
    $reminderform->display();
}

print_footer($course);