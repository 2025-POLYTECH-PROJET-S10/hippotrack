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
 * Backup instructions for the seb (Safe Exam Browser) hippotrack access subplugin.
 *
 * @package    hippotrackaccess_seb
 * @category   backup
 * @author     Andrew Madden <andrewmadden@catalyst-au.net>
 * @copyright  2020 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/hippotrack/backup/moodle2/backup_mod_hippotrack_access_subplugin.class.php');

/**
 * Backup instructions for the seb (Safe Exam Browser) hippotrack access subplugin.
 *
 * @copyright  2020 Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_hippotrackaccess_seb_subplugin extends backup_mod_hippotrack_access_subplugin {

    /**
     * Stores the data related to the Safe Exam Browser hippotrack settings and management for a particular hippotrack.
     *
     * @return backup_subplugin_element
     */
    protected function define_hippotrack_subplugin_structure() {
        parent::define_hippotrack_subplugin_structure();
        $hippotrackid = backup::VAR_ACTIVITYID;

        $subplugin = $this->get_subplugin_element();
        $subpluginwrapper = new backup_nested_element($this->get_recommended_name());

        $template = new \hippotrackaccess_seb\template();
        $blanktemplatearray = (array) $template->to_record();
        unset($blanktemplatearray['usermodified']);
        unset($blanktemplatearray['timemodified']);

        $templatekeys = array_keys($blanktemplatearray);

        $subplugintemplatesettings = new backup_nested_element('hippotrackaccess_seb_template', null, $templatekeys);

        // Get hippotrack settings keys to save.
        $settings = new \hippotrackaccess_seb\hippotrack_settings();
        $blanksettingsarray = (array) $settings->to_record();
        unset($blanksettingsarray['id']); // We don't need to save reference to settings record in current instance.
        // We don't need to save the data about who last modified the settings as they will be overwritten on restore. Also
        // means we don't have to think about user data for the backup.
        unset($blanksettingsarray['usermodified']);
        unset($blanksettingsarray['timemodified']);

        $settingskeys = array_keys($blanksettingsarray);

        // Save the settings.
        $subpluginhippotracksettings = new backup_nested_element('hippotrackaccess_seb_hippotracksettings', null, $settingskeys);

        // Connect XML elements into the tree.
        $subplugin->add_child($subpluginwrapper);
        $subpluginwrapper->add_child($subpluginhippotracksettings);
        $subpluginhippotracksettings->add_child($subplugintemplatesettings);

        // Set source to populate the settings data by referencing the ID of hippotrack being backed up.
        $subpluginhippotracksettings->set_source_table(hippotrackaccess_seb\hippotrack_settings::TABLE, ['hippotrackid' => $hippotrackid]);

        $subpluginhippotracksettings->annotate_files('hippotrackaccess_seb', 'filemanager_sebconfigfile', null);

        $params = ['id' => '../templateid'];
        $subplugintemplatesettings->set_source_table(\hippotrackaccess_seb\template::TABLE, $params);

        return $subplugin;
    }
}