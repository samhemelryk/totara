<?php
/**
 * This file is part of Totara LMS
 *
 * Copyright (C) 2010, 2011 Totara Learning Solutions LTD
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author Aaron Barnes <aaron.barnes@totaralms.com>
 * @author Alastair Munro <alastair.munro@totaralms.com>
 * @package totara
 * @subpackage facetoface
 */

require_once(dirname(dirname(dirname(dirname(dirname(__FILE__))))).'/config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/mod/facetoface/lib.php');
require_once($CFG->dirroot . '/mod/facetoface/notification/template/edit_form.php');

// Parameters
$id = optional_param('id', 0, PARAM_INT);

// Setup page and check permissions
$contextsystem = context_system::instance();
$PAGE->set_url($CFG->wwwroot . '/mod/facetoface/notification/template/edit.php');
$PAGE->set_context($contextsystem);

require_login(0, false);
require_capability('moodle/site:config', $contextsystem);

$redirectto = "{$CFG->wwwroot}/mod/facetoface/notification/template/index.php";

if ($id) {
    $notification = $DB->get_record('facetoface_notification_tpl', array('id' => $id));
}

// Setup editors
$editoroptions = array(
    'noclean'  => false,
    'maxfiles' => EDITOR_UNLIMITED_FILES,
    'maxbytes' => $CFG->maxbytes,
    'context'  => $contextsystem,
);

$body = new stdClass();
$body->id = isset($notification) ? $notification->id : 0;
$body->body = isset($notification->body) ? $notification->body : '';
$body->bodyformat = FORMAT_HTML;
$body = file_prepare_standard_editor($body, 'body', $editoroptions, $contextsystem, 'mod_facetoface', 'notification_template', $id);

$managerprefix = new stdClass();
$managerprefix->id = isset($notification) ? $notification->id : 0;
$managerprefix->managerprefix = isset($notification->managerprefix) ? $notification->managerprefix : '';
$managerprefix->managerprefixformat = FORMAT_HTML;
$managerprefix = file_prepare_standard_editor($managerprefix, 'managerprefix', $editoroptions, $contextsystem, 'mod_facetoface', 'notification_template', $id);

// Load data
$form = new mod_facetoface_notification_template_form(null, compact('id', 'editoroptions'));

if ($id) {
    $template = $DB->get_record('facetoface_notification_tpl', array('id' => $id));
    if (!$template) {
        print_error('error:notificationtemplatecouldnotbefound', 'facetoface');
    }

    // Format for the text editors
    $template->managerprefixformat = FORMAT_HTML;
    $template->bodyformat = FORMAT_HTML;

    $template = file_prepare_standard_editor($template, 'body', $editoroptions, $contextsystem, 'mod_facetoface', 'notification', $id);
    $template = file_prepare_standard_editor($template, 'managerprefix', $editoroptions, $contextsystem, 'mod_facetoface', 'notification', $id);

    $form->set_data($template);
}

// Process data
if ($form->is_cancelled()) {
    redirect($redirectto);

} else if ($data = $form->get_data()) {

    $data->body = '';
    $data->managerprefix = '';

    if ($data->id) {
        $itemid = $DB->update_record('facetoface_notification_tpl', $data);
    } else {
        $itemid = $DB->insert_record('facetoface_notification_tpl', $data);
    }

    $data = file_postupdate_standard_editor($data, 'body', $editoroptions, $contextsystem, 'mod_facetoface', 'session', $itemid);
    $DB->set_field('facetoface_notification_tpl', 'body', $data->body, array('id' => $itemid));

    $data = file_postupdate_standard_editor($data, 'managerprefix', $editoroptions, $contextsystem, 'mod_facetoface', 'session', $itemid);
    $DB->set_field('facetoface_notification_tpl', 'managerprefix', $data->managerprefix, array('id' => $itemid));

    totara_set_notification(get_string('notificationtemplatesaved', 'facetoface'), $redirectto, array('class' => 'notifysuccess'));
}

$navlinks = array();
$navlinks[] = array('name' => get_string('administration'));
$navlinks[] = array('name' => get_string('managemodules'));
$navlinks[] = array('name' => get_string('activities'));
$navlinks[] = array('name' => get_string('modulename', 'facetoface'));
$navlinks[] = array('name' => get_string('notificationtemplates', 'facetoface'));
$navigation = build_navigation($navlinks);

$PAGE->set_title(get_string('notificationtemplates', 'facetoface'));
$PAGE->set_heading('');
$PAGE->set_focuscontrol('');
$PAGE->set_cacheable(true);
echo $OUTPUT->header();

if ($id) {
    echo $OUTPUT->heading(get_string('editnotificationtemplate', 'facetoface'));
} else {
    echo $OUTPUT->heading(get_string('addnotificationtemplate', 'facetoface'));
}

$form->display();

echo $OUTPUT->footer();