<?php

namespace local_customgradingform_renderer\output;

defined('MOODLE_INTERNAL') || die();

use plugin_renderer_base;
use moodle_url;

class renderer extends plugin_renderer_base {

    /**
     * Renders the "Import rubric from CSV" button for the grading form interface.
     *
     * @param int $areaid The ID of the grading area.
     * @param int $contextid The context ID (usually from the course module).
     * @param string $returnurl Optional return URL to redirect back after import.
     * @return string HTML button markup.
     */
    public function gradingform_new_form_from_file_button(int $areaid, int $contextid, string $returnurl = ''): string {
        global $PAGE;

        // Build return URL back to manage.php with expected parameters
        $returnurl = new moodle_url('/grade/grading/manage.php', [
            'contextid' => $contextid,
            'component' => 'mod_' . $PAGE->cm->modname,
            'area' => 'submissions'
        ]);

        // URL to the import form
        $url = new moodle_url('/local/customgradingform_renderer/import.php', [
            'areaid' => $areaid,
            'contextid' => $contextid,
            'returnurl' => $returnurl->out(false)
        ]);

        // Render the button
        return $this->output->single_button($url, get_string('importfromcsv', 'local_customgradingform_renderer'), 'get');
    }
}
