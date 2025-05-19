<?php
require('../../config.php');
require_once($CFG->dirroot . '/grade/grading/lib.php');

// Required parameters
$cmid = required_param('cmid', PARAM_INT);
$debug = optional_param('debug', 0, PARAM_BOOL); // Set ?debug=1 in the URL to enable debug info

// Get course module
$cm = get_coursemodule_from_id(null, $cmid, 0, false, MUST_EXIST);
$context = context_module::instance($cm->id);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);

// User authentication and capability check
require_login($course, true, $cm);
require_capability('moodle/grade:managegradingforms', $context);

// Determine grading component and area
$component = 'mod_' . $cm->modname;
$area = 'submissions'; // Default for assign module

// Fetch or create grading area
$gradingarea = $DB->get_record('grading_areas', [
    'contextid' => $context->id,
    'component' => $component,
    'areaname'  => $area
]);

if (!$gradingarea) {
    $gradingarea = new stdClass();
    $gradingarea->contextid = $context->id;
    $gradingarea->component = $component;
    $gradingarea->areaname = $area;
    $gradingarea->activemethod = 'rubric';
    $gradingarea->id = $DB->insert_record('grading_areas', $gradingarea);
} else if ($gradingarea->activemethod !== 'rubric') {
    $gradingarea->activemethod = 'rubric';
    $DB->update_record('grading_areas', $gradingarea);
}

// Check if a rubric is already defined
$alreadyexists = $DB->record_exists('grading_definitions', [
    'areaid' => $gradingarea->id,
    'method' => 'rubric'
]);

$returnurl = new moodle_url('/mod/' . $cm->modname . '/view.php', ['id' => $cm->id]);

if ($alreadyexists) {
    if ($debug) {
        echo $OUTPUT->header();
        echo $OUTPUT->notification('⚠️ A rubric is already defined for this activity. Importing a new one is not allowed.', 'notifyproblem');
        echo html_writer::start_div('debug', ['style' => 'border-left:4px solid red;padding:10px;margin:10px 0']);
        echo "<strong>cmid:</strong> {$cmid}<br>";
        echo "<strong>contextid:</strong> {$context->id}<br>";
        echo "<strong>component:</strong> {$component}<br>";
        echo "<strong>area:</strong> {$area}<br>";
        echo "<strong>gradingarea id:</strong> {$gradingarea->id}<br>";
        echo html_writer::end_div();
        echo $OUTPUT->continue_button($returnurl);
        echo $OUTPUT->footer();
        exit;
    } else {
        redirect($returnurl, '⚠️ A rubric is already defined for this activity.', 5);
    }
}

// Redirect to import script
$importurl = new moodle_url('/local/customgradingform_renderer/import.php', [
    'areaid' => $gradingarea->id,
    'contextid' => $context->id,
    'returnurl' => $returnurl->out(false)
]);

redirect($importurl);
