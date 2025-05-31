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

require('../../config.php');
require_once($CFG->libdir . '/formslib.php');
require_once($CFG->dirroot . '/grade/grading/lib.php');

/**
 * @package   local_customgradingform_renderer
 * @copyright 2025 Richard Rangel
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$areaid     = required_param('areaid', PARAM_INT);
$contextid  = required_param('contextid', PARAM_INT);
$returnurl  = required_param('returnurl', PARAM_LOCALURL);

$context = context::instance_by_id($contextid, MUST_EXIST);
list($context, $course, $cm) = get_context_info_array($contextid);

require_login($course, true, $cm);
require_capability('moodle/grade:managegradingforms', $context);

$PAGE->set_cm($cm);
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/customgradingform_renderer/import.php', [
    'areaid' => $areaid,
    'contextid' => $contextid,
    'returnurl' => $returnurl
]));
$PAGE->set_title(get_string('importfromcsv', 'local_customgradingform_renderer'));
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('importfromcsv', 'local_customgradingform_renderer'));

$exists = $DB->record_exists('grading_definitions', [
    'areaid' => $areaid,
    'method' => 'rubric'
]);

if ($exists) {
    echo $OUTPUT->notification(get_string('errorrubricexists', 'local_customgradingform_renderer'), 'notifyproblem');
    echo $OUTPUT->footer();
    exit;
}

if (debugging('', DEBUG_DEVELOPER)) {
    echo html_writer::div("
        🧪 <strong>areaid</strong> = {$areaid}, 
        <strong>contextid</strong> = {$contextid}, 
        <strong>cmid</strong> = {$cm->id}, 
        <strong>modname</strong> = {$cm->modname}
    ", 'debug', ['style' => 'margin:10px 0;padding:10px;border-left:4px solid #0073aa;']);
}

class rubric_csv_form extends moodleform {
    public function definition() {
        $mform = $this->_form;
        $mform->addElement('filepicker', 'rubriccsv', get_string('choosecsvfile', 'local_customgradingform_renderer'), null,
            ['accepted_types' => ['.csv']]);
        $mform->addRule('rubriccsv', null, 'required');

        $mform->addElement('hidden', 'areaid', $this->_customdata['areaid']);
        $mform->setType('areaid', PARAM_INT);

        $mform->addElement('hidden', 'contextid', $this->_customdata['contextid']);
        $mform->setType('contextid', PARAM_INT);

        $mform->addElement('hidden', 'returnurl', $this->_customdata['returnurl']);
        $mform->setType('returnurl', PARAM_LOCALURL);

        $this->add_action_buttons(true, get_string('submitcsv', 'local_customgradingform_renderer'));
    }
}

$mform = new rubric_csv_form(null, [
    'areaid' => $areaid,
    'contextid' => $contextid,
    'returnurl' => $returnurl
]);
$dataerror = false;

if ($mform->is_cancelled()) {
    redirect($returnurl);
} else if ($mform->is_submitted() && $mform->is_validated() && ($data = $mform->get_data())) {
    $draftitemid = file_get_submitted_draft_itemid('rubriccsv');
    file_prepare_draft_area($draftitemid, $contextid, 'local_customgradingform_renderer', 'temp', 0);

    $usercontext = context_user::instance($USER->id);
    $fs = get_file_storage();
    $files = $fs->get_area_files($usercontext->id, 'user', 'draft', $draftitemid, 'id DESC', false);

    if (empty($files)) {
        echo $OUTPUT->notification(get_string('csvrequired', 'local_customgradingform_renderer'), 'notifyproblem');
        $dataerror = true;
    } else {
        $file = reset($files);
        $content = $file->get_content();
        $rows = array_map('str_getcsv', explode(PHP_EOL, $content));
        $headers = array_map('trim', array_shift($rows));

        if (!in_array('criterion', $headers) || !in_array('level_description', $headers) || !in_array('score', $headers)) {
            echo $OUTPUT->notification(get_string('csvmissingcolumns', 'local_customgradingform_renderer'), 'notifyproblem');
            $dataerror = true;
        } else {
            $rubric = [];
            foreach ($rows as $row) {
                if (count($row) < 3) { continue;
                }
                $row = array_combine($headers, $row);
                $criterion = clean_param(trim($row['criterion']), PARAM_TEXT);
                $description = clean_param(trim($row['level_description']), PARAM_TEXT);
                $score = (float) clean_param($row['score'], PARAM_FLOAT);

                if (!isset($rubric[$criterion])) { $rubric[$criterion] = [];
                }
                $rubric[$criterion][] = ['definition' => $description, 'score' => $score];
            }

            $enablemax = get_config('local_customgradingform_renderer', 'enablemaxlevelscore');
            $maxscore = (float)get_config('local_customgradingform_renderer', 'maxlevelscore');
            $enablemin = get_config('local_customgradingform_renderer', 'enableminlevelscore');
            $minscore = (float)get_config('local_customgradingform_renderer', 'minlevelscore');

            $gradeitem = $DB->get_record('grade_items', [
                'iteminstance' => $cm->instance,
                'itemmodule' => $cm->modname,
                'itemnumber' => 0
            ], '*', IGNORE_MULTIPLE);

            $grademax = (float)$gradeitem->grademax;
            $sum = 0;

            foreach ($rubric as $criterion => $levels) {
                $scores = array_column($levels, 'score');
                $max = max($scores);
                $sum += $max;

                if (count(array_unique($scores)) < count($scores)) {
                    echo $OUTPUT->notification(get_string('errorrepeatedscores', 'local_customgradingform_renderer', $criterion), 'notifyproblem');
                    $dataerror = true;
                    break;
                }

                if ($enablemin && !in_array($minscore, $scores)) {
                    echo $OUTPUT->notification(get_string('errorminmissing', 'local_customgradingform_renderer', ['criterion' => $criterion, 'min' => $minscore]), 'notifyproblem');
                    $dataerror = true;
                    break;
                }

                if ($enablemax) {
                    foreach ($scores as $score) {
                        if ($score > $maxscore) {
                            echo $OUTPUT->notification(get_string('errormaxexceeded', 'local_customgradingform_renderer', ['criterion' => $criterion, 'score' => $score, 'max' => $maxscore]), 'notifyproblem');
                            $dataerror = true;
                            break 2;
                        }
                    }
                }
            }

            if (!$dataerror && round($sum, 2) !== round($grademax, 2)) {
                echo $OUTPUT->notification(get_string('errormismatchtotal', 'local_customgradingform_renderer', ['sum' => $sum, 'grademax' => $grademax]), 'notifyproblem');
                $dataerror = true;
            }

            if (!$dataerror) {
                $definition = new stdClass();
                $definition->areaid = $areaid;
                $definition->method = 'rubric';
                $definition->name = get_string('importedrubricname', 'local_customgradingform_renderer', date('d/m/Y H:i:s'));
                $definition->status = 0;
                $definition->timecreated = time();
                $definition->timemodified = time();
                $definition->usercreated = $USER->id;
                $definition->usermodified = $USER->id;
                $definition->options = json_encode([
                    "sortlevelsasc" => "0",
                    "lockzeropoints" => "1",
                    "alwaysshowdefinition" => "1",
                    "showdescriptionteacher" => null,
                    "showdescriptionstudent" => "1",
                    "showscoreteacher" => "1",
                    "showscorestudent" => "1",
                    "enableremarks" => "1",
                    "showremarksstudent" => "1"
                ]);
                $definitionid = $DB->insert_record('grading_definitions', $definition);

                $criteriaorder = 0;
                foreach ($rubric as $criteriontext => $levels) {
                    $criterion = new stdClass();
                    $criterion->definitionid = $definitionid;
                    $criterion->description = $criteriontext;
                    $criterion->descriptionformat = FORMAT_HTML;
                    $criterion->sortorder = $criteriaorder++;
                    $criterionid = $DB->insert_record('gradingform_rubric_criteria', $criterion);

                    $levelsorder = 0;
                    foreach ($levels as $level) {
                        $levelobj = new stdClass();
                        $levelobj->criterionid = $criterionid;
                        $levelobj->definition = $level['definition'];
                        $levelobj->definitionformat = FORMAT_HTML;
                        $levelobj->score = $level['score'];
                        $levelobj->sortorder = $levelsorder++;
                        $DB->insert_record('gradingform_rubric_levels', $levelobj);
                    }
                }

                echo $OUTPUT->notification(get_string('importsuccess', 'local_customgradingform_renderer'), 'notifysuccess');
                $manageurl = new moodle_url('/grade/grading/manage.php', ['areaid' => $areaid]);
                echo html_writer::script("setTimeout(function(){ window.location.href = '{$manageurl->out(false)}'; }, 3000);");
            }
        }
    }
}

// Display form if needed.
if (!isset($data) || $dataerror) {
    $mform->display();
}

echo $OUTPUT->footer();
