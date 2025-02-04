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
 * Restore instructions for the seb (Safe Exam Browser) hippotrack access subplugin.
 *
 * @package    hippotrackaccess_seb
 * @category   backup
 * @author     Andrew Madden <andrewmadden@catalyst-au.net>
 * @copyright  2020 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use hippotrackaccess_seb\hippotrack_settings;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/hippotrack/backup/moodle2/restore_mod_hippotrack_access_subplugin.class.php');

/**
 * Restore instructions for the seb (Safe Exam Browser) hippotrack access subplugin.
 *
 * @copyright  2020 Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_hippotrackaccess_seb_subplugin extends restore_mod_hippotrack_access_subplugin {

    /**
     * Provides path structure required to restore data for seb hippotrack access plugin.
     *
     * @return array
     */
    protected function define_hippotrack_subplugin_structure() {
        $paths = [];

        // HippoTrack settings.
        $path = $this->get_pathfor('/hippotrackaccess_seb_hippotracksettings'); // Subplugin root path.
        $paths[] = new restore_path_element('hippotrackaccess_seb_hippotracksettings', $path);

        // Template settings.
        $path = $this->get_pathfor('/hippotrackaccess_seb_hippotracksettings/hippotrackaccess_seb_template');
        $paths[] = new restore_path_element('hippotrackaccess_seb_template', $path);

        return $paths;
    }

    /**
     * Process the restored data for the hippotrackaccess_seb_hippotracksettings table.
     *
     * @param stdClass $data Data for hippotrackaccess_seb_hippotracksettings retrieved from backup xml.
     */
    public function process_hippotrackaccess_seb_hippotracksettings($data) {
        global $DB, $USER;

        // Process hippotracksettings.
        $data = (object) $data;
        $data->hippotrackid = $this->get_new_parentid('hippotrack'); // Update hippotrackid with new reference.
        $data->cmid = $this->task->get_moduleid();

        unset($data->id);
        $data->timecreated = $data->timemodified = time();
        $data->usermodified = $USER->id;
        $DB->insert_record(hippotrackaccess_seb\hippotrack_settings::TABLE, $data);

        // Process attached files.
        $this->add_related_files('hippotrackaccess_seb', 'filemanager_sebconfigfile', null);
    }

    /**
     * Process the restored data for the hippotrackaccess_seb_template table.
     *
     * @param stdClass $data Data for hippotrackaccess_seb_template retrieved from backup xml.
     */
    public function process_hippotrackaccess_seb_template($data) {
        global $DB;

        $data = (object) $data;

        $hippotrackid = $this->get_new_parentid('hippotrack');

        $template = null;
        if ($this->task->is_samesite()) {
            $template = \hippotrackaccess_seb\template::get_record(['id' => $data->id]);
        } else {
            // In a different site, try to find existing template with the same name and content.
            $candidates = \hippotrackaccess_seb\template::get_records(['name' => $data->name]);
            foreach ($candidates as $candidate) {
                if ($candidate->get('content') == $data->content) {
                    $template = $candidate;
                    break;
                }
            }
        }

        if (empty($template)) {
            unset($data->id);
            $template = new \hippotrackaccess_seb\template(0, $data);
            $template->save();
        }

        // Update the restored hippotrack settings to use restored template.
        $DB->set_field(\hippotrackaccess_seb\hippotrack_settings::TABLE, 'templateid', $template->get('id'), ['hippotrackid' => $hippotrackid]);
    }

}

