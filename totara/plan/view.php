<?php
/*
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
 * @author Simon Coggins <simonc@catalyst.net.nz>
 * @author Aaron Barnes <aaronb@catalyst.net.nz>
 * @author Eugene Venter <eugene@catalyst.net.nz>
 * @package totara
 * @subpackage plan
 */

/**
 * Plan view page
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once($CFG->dirroot . '/totara/plan/lib.php');

require_login();

$id = required_param('id', PARAM_INT); // plan id
$action = optional_param('action', 'view', PARAM_TEXT);

if ($action == 'edit') {
    require_once($CFG->dirroot . '/totara/core/js/lib/setup.php');

    //Javascript include
    local_js(array(
        TOTARA_JS_DATEPICKER,
        TOTARA_JS_PLACEHOLDER
    ));
}

$componentname = 'plan';

$currenturl = qualified_me();
$viewurl = strip_querystring(qualified_me())."?id={$id}&action=view";
$editurl = strip_querystring(qualified_me())."?id={$id}&action=edit";

require_login();
$plan = new development_plan($id);

// Permissions check
$systemcontext = get_system_context();
if(!has_capability('totara/plan:accessanyplan', $systemcontext) && ($plan->get_setting('view') < DP_PERMISSION_ALLOW)) {
        print_error('error:nopermissions', 'local_plan');
}

require_once('edit_form.php');
$form = new plan_edit_form($currenturl, array('plan'=>$plan, 'action'=>$action));

if ($form->is_cancelled()) {
    totara_set_notification(get_string('planupdatecancelled', 'local_plan'), $viewurl, array('class' => 'notifysuccess'));
}

if ($plan->get_setting('view') != DP_PERMISSION_ALLOW) {
    print_error('error:nopermissions', 'local_plan');
}

// Handle form submits
if ($data = $form->get_data()) {
    if (isset($data->edit)) {
        if ($plan->get_setting('update') < DP_PERMISSION_ALLOW) {
            print_error('error:nopermissions', 'local_plan');
        }
        redirect($editurl);
    } elseif (isset($data->delete)) {
        if ($plan->get_setting('delete') < DP_PERMISSION_ALLOW) {
            print_error('error:nopermissions', 'local_plan');
        }
        redirect(strip_querystring(qualified_me())."?id={$id}&action=delete");
    } elseif (isset($data->deleteyes)) {
        if ($plan->get_setting('delete') < DP_PERMISSION_ALLOW) {
            print_error('error:nopermissions', 'local_plan');
        }
        if ($plan->delete()) {
            totara_set_notification(get_string('plandeletesuccess', 'local_plan', $plan->name), "{$CFG->wwwroot}/totara/plan/index.php?userid={$plan->userid}", array('class' => 'notifysuccess'));
        } else {
            totara_set_notification(get_string('plandeletefail', 'local_plan', $plan->name), $viewurl);
        }
    } elseif (isset($data->deleteno)) {
        redirect($viewurl);
    } elseif (isset($data->complete)) {
        if ($plan->get_setting('completereactivate') < DP_PERMISSION_ALLOW) {
            print_error('error:nopermissions', 'local_plan');
        }
        redirect(strip_querystring(qualified_me())."?id={$id}&action=complete");
    } elseif (isset($data->completeyes)) {
        if ($plan->get_setting('completereactivate') < DP_PERMISSION_ALLOW) {
            print_error('error:nopermissions', 'local_plan');
        }
        if ($plan->set_status(DP_PLAN_STATUS_COMPLETE, DP_PLAN_REASON_MANUAL_COMPLETE)) {
            $plan->send_completion_alert();
            totara_set_notification(get_string('plancompletesuccess', 'local_plan', $plan->name), $viewurl, array('class' => 'notifysuccess'));
        } else {
            totara_set_notification(get_string('plancompletefail', 'local_plan', $plan->name), $viewurl);
        }
    } elseif (isset($data->completeno)) {
        redirect($viewurl);
    } elseif (isset($data->submitbutton)) {
        if ($plan->get_setting('update') < DP_PERMISSION_ALLOW) {
            print_error('error:nopermissions', 'local_plan');
        }
        // Save plan data
        unset($data->startdate);
        $data->enddate = totara_date_parse_from_format(get_string('datepickerparseformat', 'totara_core'), $data->enddate);  // convert to timestamp
        if (!update_record('dp_plan', $data)) {
            totara_set_notification(get_string('planupdatefail', 'local_plan'), $editurl);
        }

        totara_set_notification(get_string('planupdatesuccess', 'local_plan'), $viewurl, array('class' => 'notifysuccess'));
    }

    // Reload plan to reflect any changes
    $plan = new development_plan($id);
}


/**
 * Display header
 */
$plan->print_header('plan');

add_to_log(SITEID, 'plan', 'view', "view.php?id={$plan->id}", $plan->name);

// Plan details
if ($plan->timecompleted) {
    $plan->enddate = userdate($plan->timecompleted, get_string('strftimedate'), $CFG->timezone, false);
} else {
    $plan->enddate = userdate($plan->enddate, get_string('strftimedatefullshort', 'langconfig'), $CFG->timezone, false);
}
$form->set_data($plan);
$form->display();

if ($action == 'view') {
    // Comments
    require_once($CFG->dirroot.'/totara/comment/lib.php');
    comment::init();
    $options->area    = 'plan-overview';
    $options->context = $systemcontext;
    $options->itemid  = $plan->id;
    $options->showcount = true;
    $options->component = 'local_plan';
    $options->autostart = true;
    $options->notoggle = true;
    $comment = new comment($options);
    echo $comment->output(true);
}

print_container_end();

if ($action == 'edit') {
    echo build_datepicker_js('input[name="enddate"]');
}

print_footer();