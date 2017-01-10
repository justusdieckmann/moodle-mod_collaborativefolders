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
 * Handles the view form
 *
 * @package    mod_collaborativefolders
 * @copyright  2016 University of Muenster
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_collaborativefolders;

//use mod_collaborativefolders\enrol_yourself_form;
//use mod_collaborativefolders\folder_generator;

class enrol_handler {

    public function handle_my_form($id, $modid) {
        global $DB;
        $mform = new enrol_yourself_form($id, $modid);

        // Handle form cancel operation, if cancel button is present on form.
        if ($mform->is_cancelled()) {
            notice(get_string('cancelform', 'mod_collaborativefolders'), new moodle_url('/mod/collaborativefolders/view.php', array('id' => $id)));
        }
         if ($fromform = $mform->get_data()) {
            // In this case you process validated data. $mform->get_data() returns data posted in form.
            $thisdata = $mform->get_data();

            $scieboidentifier = $thisdata->name;
            $collaborativefolders = $DB->get_record('collaborativefolders', array('id' => $modid));

            $foldergenerator = new folder_generator();
            $foldergenerator->add_to_personal_account($collaborativefolders->id . '/' . $collaborativefolders->foldername, $scieboidentifier, $id);

        }

        return $mform;
    }

}