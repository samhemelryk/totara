<?php

require_once('../../config.php');
require_once('learningreportslib.php');
require_once($CFG->dirroot.'/local/learningreports/download_form.php');

$format    = optional_param('format', '', PARAM_TEXT);
$id = required_param('id',PARAM_INT);

$shortname = get_field('learning_report','shortname','id',$id);

// new report object
$report = new learningreport($shortname);

if(!$report->is_capable()) {
    error('You cannot view this page');
}

$countfiltered = $report->get_filtered_count();
$countall = $report->get_full_count();
$fullname = $report->_fullname;
$pagetitle = format_string(get_string('learningreports','local').': '.$fullname);
$navlinks[] = array('name' => get_string('learningreports','local'), 'link'=> '', 'type'=>'title');
$navlinks[] = array('name' => $fullname, 'link'=> '', 'type'=>'title');

$navigation = build_navigation($navlinks);
print_header_simple($pagetitle, '', $navigation, '', '', true);

// display heading including filtering stats
print_heading("$fullname: Showing $countfiltered / $countall");

// print filters
$report->display_add();
$report->display_active();

// show results
if($countfiltered>0) {
    $report->display_table();
    // export button
    $report->export_button();
} else {
    print "No results found. Try removing one or more filters.";
}


print_footer();

