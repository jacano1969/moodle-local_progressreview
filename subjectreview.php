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
 * Displays and processes the form for entering Subject reviews
 *
 * @package   local_progressreview
 * @copyright 2011 Taunton's College, UK
 * @author    Mark Johnson <mark.johnson@tauntons.ac.uk>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot.'/local/progressreview/lib.php');
require_once($CFG->dirroot.'/local/progressreview/renderer.php');

$sessionid = required_param('sessionid', PARAM_INT);
$courseid = required_param('courseid', PARAM_INT);
$submitted = optional_param('submit', false, PARAM_BOOL);
$coursecontext = get_context_instance(CONTEXT_COURSE, $courseid);

if (!$session = $DB->get_record('progressreview_session', array('id' => $sessionid))) {
    print_error('invalidsession', 'local_progressreview');
}

if (!$course = $DB->get_record('course', array('id' => $courseid))) {
    print_error('invalidcourse', 'local_progressreview');
}

if (has_capability('moodle/local_progressreview:write', $coursecontext)) {
    $mode = PROGRESSREVIEW_TEACHER;
} else if (has_capability('moodle/local_progressreview:viewown', $coursecontext)) {
    $mode = PROGRESSREVIEW_STUDENT;
} else {
    print_error('noaccess');
}

require_login($course);
$params = array('sessionid' => $sessionid, 'courseid' => $courseid);
$PAGE->set_url('/local/progressreview/subjectreview.php', $params);
$PAGE->navbar->add(get_string('pluginname', 'local_progressreview'));
$PAGE->navbar->add($session->name);

$output = $PAGE->get_renderer('local_progressreview');
$content = '';

if ($mode == PROGRESSREVIEW_TEACHER) {
    $reviews = array();
    $reviewdata = array();
    $previousdata = array();
    $students = get_users_by_capability($coursecontext,
                                        'moodle/local_progressreview:hasreview',
                                        '',
                                        'lastname, firstname');
    foreach ($students as $student) {
        $reviews[$student->id] = new progressreview($student->id,
                                                    $sessionid,
                                                    $courseid,
                                                    $USER->id,
                                                    PROGRESSREVIEW_SUBJECT);
        $subjectreview = $reviews[$student->id]->get_plugin('subject');
        if ($submitted) {
            $post = $_POST['review'][$subjectreview->id];
            $newdata = array(
                'homeworkdone' => $post['homeworkdone'] == '' ? null : clean_param($post['homeworkdone'], PARAM_INT),
                'homeworktotal' => $post['homeworktotal'] == '' ? null : clean_param($post['homeworktotal'], PARAM_INT),
                'behaviour' => $post['behaviour'] == '' ? null : clean_param($post['behaviour'], PARAM_INT),
                'effort' => $post['effort'] == '' ? null : clean_param($post['effort'], PARAM_INT),
                'targetgrade' => $post['targetgrade'] == '' ? null : clean_param($post['targetgrade'], PARAM_INT),
                'performancegrade' => $post['performancegrade'] == '' ? null : clean_param($post['performancegrade'], PARAM_INT)
            );
            if (!$reviews[$student->id]->get_session()->inductionreview) {
                $newdata['comments'] = $post['comments'] == '' ? null : clean_param($post['comments'], PARAM_TEXT);
            }
            try {
                $subjectreview->update($newdata);
                add_to_log($course->id,
                           'local_progressreview',
                           'update',
                           $PAGE->url->out(),
                           $student->id);
                $content = $OUTPUT->notification(get_string('changessaved'));
            } catch (dml_write_exception $e) {
                add_to_log($course->id,
                           'local_progressreview',
                           'update',
                           $PAGE->url->out(),
                           $student->id.': '.$e->error);
                $strnotsaved = get_string('changesnotsaved', 'local_progressreview');
                $content = $OUTPUT->error_text($strnotsaved);
            }
        }
        $reviewdata[$student->id] = $subjectreview->get_review();
        if ($session->previoussession) {
            if($previousreview = $reviews[$student->id]->get_previous()) {
                $previousdata[$student->id] = $previousreview->get_plugin('subject')->get_review();
            }
        }
    }

    $content .= $output->changescale_button($sessionid, $courseid);
    if (!empty($session->deadline_subject)) {
        $deadline = userdate($session->deadline_subject);
        $strdeadline = get_string('completesubjectreviewsby', 'local_progressreview', $deadline);
        $content .= $OUTPUT->container($strdeadline, 'reviewnotes');
    }
    if (!empty($session->previoussession)) {
        $previoussession = progressreview_controller::validate_session($session->previoussession);
        $strprevious = get_string('previousfigures', 'local_progressreview', $previoussession->name);
        $content .= $OUTPUT->container($strprevious, 'reviewnotes');
    }
    $content .= $output->container('', 'clearfix');
    $content .= $output->subject_review_table($reviewdata, true, $previousdata);
    add_to_log($course->id, 'local_progressreview', 'view subjectreview', $PAGE->url->out());
}

echo $OUTPUT->header();

echo $content;

echo $OUTPUT->footer();
