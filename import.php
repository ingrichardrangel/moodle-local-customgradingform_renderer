<?php
require('../../config.php');
require_once($CFG->dirroot . '/grade/grading/lib.php');

// === Required Parameters ===
$areaid     = required_param('areaid', PARAM_INT);
$contextid  = required_param('contextid', PARAM_INT);
$returnurl  = required_param('returnurl', PARAM_LOCALURL);

require_login();

// === Get Context, Course, and Module ===
$context = context::instance_by_id($contextid, MUST_EXIST);
list($context, $course, $cm) = get_context_info_array($contextid);

// === Setup Page ===
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
echo html_writer::tag('h3', get_string('importfromcsv', 'local_customgradingform_renderer'));

// === Debug: Show context info ===
echo html_writer::div("🧪 areaid = <strong>$areaid</strong>, contextid = <strong>$contextid</strong>, cmid = <strong>{$cm->id}</strong>, modname = <strong>{$cm->modname}</strong>", 'debug');

// === Check if rubric already exists for this area ===
$exists = $DB->record_exists('grading_definitions', [
    'areaid' => $areaid,
    'method' => 'rubric'
]);

if ($exists) {
    echo $OUTPUT->notification('⚠️ A rubric is already defined for this activity. You cannot import another one.', 'notifyproblem');
    echo $OUTPUT->footer();
    exit;
}

// === Upload Form ===
echo '<form method="post" enctype="multipart/form-data">';
echo '<input type="file" name="rubriccsv" accept=".csv" required>';
echo '<br><br><input type="submit" value="' . get_string('submitcsv', 'local_customgradingform_renderer') . '">';
echo '</form>';

// === Process CSV if submitted ===
if (!empty($_FILES['rubriccsv']['tmp_name'])) {
    $file = $_FILES['rubriccsv']['tmp_name'];
    $handle = fopen($file, "r");

    if ($handle === false) {
        echo $OUTPUT->notification('Failed to open the CSV file.', 'notifyproblem');
    } else {
        $rubric = [];
        $headers = fgetcsv($handle);

        while (($data = fgetcsv($handle)) !== false) {
            $row = array_combine($headers, $data);
            $criterion = trim($row['criterio']);
            $description = trim($row['descripcion_nivel']);
            $score = floatval($row['puntuacion']);

            if (!isset($rubric[$criterion])) {
                $rubric[$criterion] = [];
            }

            $rubric[$criterion][] = [
                'definition' => $description,
                'score' => $score
            ];
        }

        fclose($handle);

        // === Settings from site administration ===
        $enablemax = get_config('local_customgradingform_renderer', 'enablemaxlevelscore');
        $maxscore = (float)get_config('local_customgradingform_renderer', 'maxlevelscore');
        $enablemin = get_config('local_customgradingform_renderer', 'enableminlevelscore');
        $minscore = (float)get_config('local_customgradingform_renderer', 'minlevelscore');

        // === Retrieve grademax from grade_items ===
        $gradeitem = $DB->get_record('grade_items', [
            'iteminstance' => $cm->instance,
            'itemmodule' => $cm->modname,
            'itemnumber' => 0
        ], '*', IGNORE_MULTIPLE);

        $grademax = (float)$gradeitem->grademax;
        echo html_writer::div("🧪 grademax = <strong>$grademax</strong>", 'debug');

        // === Validations ===
        $sum = 0;
        foreach ($rubric as $criterion => $levels) {
            $scores = array_column($levels, 'score');
            $max = max($scores);
            $sum += $max;

            if (count(array_unique($scores)) < count($scores)) {
                echo $OUTPUT->notification("⚠️ Error: Criterion <strong>$criterion</strong> has repeated level scores. Each level must have a unique score.", 'notifyproblem');
                echo $OUTPUT->footer();
                exit;
            }

            if ($enablemin && !in_array($minscore, $scores)) {
                echo $OUTPUT->notification("⚠️ Error: Criterion <strong>$criterion</strong> does not contain the minimum allowed score of <strong>$minscore</strong>.", 'notifyproblem');
                echo $OUTPUT->footer();
                exit;
            }

            if ($enablemax) {
                foreach ($scores as $score) {
                    if ($score > $maxscore) {
                        echo $OUTPUT->notification("⚠️ Error: Score <strong>$score</strong> in criterion <strong>$criterion</strong> exceeds the allowed maximum of <strong>$maxscore</strong>.", 'notifyproblem');
                        echo $OUTPUT->footer();
                        exit;
                    }
                }
            }
        }

        if (round($sum, 2) !== round($grademax, 2)) {
            echo $OUTPUT->notification("⚠️ Error: The sum of the highest levels per criterion is <strong>$sum</strong>, but the maximum grade is <strong>$grademax</strong>.", 'notifyproblem');
            echo $OUTPUT->footer();
            exit;
        }

        // === Insert rubric definition ===
        $definition = new stdClass();
        $definition->areaid = $areaid;
        $definition->method = 'rubric';
        $definition->name = 'Imported Rubric (' . date('d/m/Y H:i:s') . ')';
        $definition->status = 0;
        $definition->copiedfromid = null;
        $definition->timecreated = time();
        $definition->timemodified = time();
        $definition->timecopied = 0;
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
        echo html_writer::div("🧪 definition ID created = <strong>$definitionid</strong>", 'debug');

        // === Insert criteria and levels ===
        $criteria_order = 0;
        foreach ($rubric as $criteriontext => $levels) {
            $criterion = new stdClass();
            $criterion->definitionid = $definitionid;
            $criterion->description = $criteriontext;
            $criterion->descriptionformat = FORMAT_HTML;
            $criterion->sortorder = $criteria_order++;
            $criterionid = $DB->insert_record('gradingform_rubric_criteria', $criterion);

            $levels_order = 0;
            foreach ($levels as $level) {
                $levelobj = new stdClass();
                $levelobj->criterionid = $criterionid;
                $levelobj->definition = $level['definition'];
                $levelobj->definitionformat = FORMAT_HTML;
                $levelobj->score = $level['score'];
                $levelobj->sortorder = $levels_order++;
                $DB->insert_record('gradingform_rubric_levels', $levelobj);
            }
        }

        echo $OUTPUT->notification(get_string('importsuccess', 'local_customgradingform_renderer'), 'notifysuccess');

        $manageurl = new moodle_url('/grade/grading/manage.php', ['areaid' => $areaid]);
        echo html_writer::script("setTimeout(function(){ window.location.href = '{$manageurl->out(false)}'; }, 3000);");
    }
}

echo $OUTPUT->footer();
