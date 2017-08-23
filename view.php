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
 * Prints a particular instance of collaborativefolders. What is shown depends
 * on the current user.
 *
 * @package    mod_collaborativefolders
 * @copyright  2017 Project seminar (Learnweb, University of Münster)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

// Page and parameter setup.
$cmid = required_param('id', PARAM_INT);
list ($course, $cm) = get_course_and_cm_from_cmid($cmid, 'collaborativefolders');
require_login($course, false, $cm);
$context = context_module::instance($cmid);
$collaborativefolder = $DB->get_record('collaborativefolders', ['id' => $cm->instance]);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/mod/collaborativefolders/view.php', array('id' => $cmid)));
$PAGE->set_title(format_string($cm->name));
$PAGE->set_heading(format_string($course->fullname));

require_capability('mod/collaborativefolders:view', $context);

\mod_collaborativefolders\view_controller::handle_request(
    $collaborativefolder, $cm, $context, $PAGE->get_renderer('mod_collaborativefolders'));
exit;

// Check whether viewer be treated as teacher or student. Actively ignore admins!
// $capteacher = has_capability('mod/collaborativefolders:viewteacher', $context, false);
// $capstudent = has_capability('mod/collaborativefolders:viewstudent', $context, false);

// View action, supposed to be one of "reset", "logout", "generate", or empty.
$action = optional_param('action', null, PARAM_ALPHA);

// The system_folder_access object will be used to access the system user's storage.
$systemclient = new \mod_collaborativefolders\local\clients\system_folder_access();
$userclient = new \mod_collaborativefolders\local\clients\user_folder_access();


// If the reset link was used, the chosen foldername is reset.
if ($action === 'reset') {
    $userclient->set_entry('name', $cmid, $USER->id, null);
    redirect(qualified_me(), get_string('resetpressed', 'mod_collaborativefolders'));
    exit;
}

// Get form data and check whether the submit button has been pressed.
$mform = new mod_collaborativefolders\name_form(qualified_me(), array('namefield' => $cm->name));

if ($fromform = $mform->get_data() && isset($fromform->enter)) {
    // If a name has been submitted, it gets stored in the user preferences.
    $userclient->set_entry('name', $cmid, $USER->id, $fromform->namefield);
}


// Indicates, whether the teacher is allowed to have access to the folder or not.
$teacherallowed = $collaborativefolder->teacher;

// Indicator for groupmode.
$gm = false;

// Two separate paths are needed, one for the share and another to rename the shared folder.
$sharepath = '/' . $cmid;
$finalpath = $sharepath;

// Checks if the groupmode is active. Does not differentiate between VISIBLE and SEPERATE.
if (groups_get_activity_groupmode($cm) != 0) {
    // If the groupmode is set by the creator, $gm is set to true.
    $gm = true;
    // If the current user is a student and participates in one ore more of the chosen
    // groups, $ingroup is set to the group, which was created first.
    $ingroup = groups_get_activity_group($cm);

    // If a groupmode is used and the current user is not a teacher, the sharepath is
    // extended by the group ID of the student.
    // The path for renaming the folder, becomes the group ID, because only the groupfolder
    // is shared with the concerning user.
    if ($ingroup != 0) {

        $sharepath .= '/' . $ingroup;
        $finalpath = '/' . $ingroup;
    }
}

// Fetch a stored link belonging to this particular activity instance.
$privatelink = $userclient->get_entry('link', $cmid, $USER->id);

// Does the teacher have access to this activity?
$teacheraccess = $capteacher && $teacherallowed == true;

// Does the current user have access to this activity, be it teacher or student?
$hasaccess = ($teacheraccess || $capstudent) && \mod_collaborativefolders\toolbox::is_create_task_running($cmid);

// Does the user have a link to this Collaborative Folder and access to this activity?
$haslink = $privatelink != null && $hasaccess;

// If the current user already received a link to the Collaborative Folder, display it.
if ($haslink) {

    echo $renderer->print_link($privatelink, 'access');
}


// The name of the folder, chosen by the user.
$name = $userclient->get_entry('name', $cmid, $USER->id);

// Does the user have access but no link has been stored yet?
$nolink = $hasaccess && $privatelink == null;

// Does the user wish to generate a link and has not already stored one?
$generate = false;
if ($action === 'generate') {
    $generate = true; // Default until we know otherwise!

    if ($nolink) {
        if ($name == null) {
            // If no personal name has been stored for the folder, no link can be generated yet.
            $generate = false;
        } else {
            // Otherwise, try to share and rename the folder.
            // First: Get user identifier from user client.
            $user = 'testuser'; // TODO Get user identifier from user client.
            // Second: Share from system to user.
            $shared = $systemclient->generate_share($sharepath, $user);

            if (!$shared) {
                // Share was unsuccessful.
                echo $renderer->print_error('share', get_string('ocserror', 'mod_collaborativefolders'));
            } else {
                $renamed = $userclient->rename($finalpath, $name, $cmid, $USER->id);

                if ($renamed['status'] === false) {
                    echo $renderer->print_error('rename', $renamed['content']);
                } else {
                    // Sharing and renaming operations were successful.

                    // Display the Link.
                    echo $renderer->print_link($renamed['content'], 'access');

                    // Event data is gathered.
                    $params = array(
                        'context' => $context,
                        'objectid' => $cm->instance
                    );

                    // And the link_generated event is triggered.
                    $generatedevent = \mod_collaborativefolders\event\link_generated::create($params);
                    $generatedevent->trigger();
                }
            }
        }
    }
}


// No link has been stored yet and no order to generate one has been received.
$nogenerate = $nolink && !$generate;

if ($nogenerate) {

    // If the user already has set a name for the folder, proceed.
    if ($name != null) {

        // A reset parameter has to be passed on redirection.
        $reseturl = qualified_me() . '&action=reset';
        echo $renderer->print_name_and_reset($name, $reseturl);

        // Generate share
        $genurl = qualified_me() . '&action=generate';
        echo $renderer->print_link($genurl, 'generate');

    } else {

        // Otherwise, show a form for the user to enter a name into.
        $mform->display();
    }
}

