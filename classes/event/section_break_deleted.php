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
 * The mod_hippotrack section break deleted event.
 *
 * @package    mod_hippotrack
 * @copyright  2021 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_hippotrack\event;

/**
 * The mod_hippotrack section break deleted event class.
 *
 * @property-read array $other {
 *      Extra information about event.
 *
 *      - int hippotrackid: the id of the hippotrack.
 *      - int firstslotid: id of the slot which we will remove the section break before.
 *      - int firstslotnumber: slot number of the slot which we will remove the section break before.
 * }
 *
 * @package    mod_hippotrack
 * @copyright  2021 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class section_break_deleted extends \core\event\base {
    protected function init() {
        $this->data['objecttable'] = 'hippotrack_sections';
        $this->data['crud'] = 'd';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
    }

    public static function get_name() {
        return get_string('eventsectionbreakdeleted', 'mod_hippotrack');
    }

    public function get_description() {
        return "The user with id '$this->userid' deleted the section break with id '{$this->objectid}' " .
            "before the slot with id '{$this->other['firstslotid']}' and slot number '{$this->other['firstslotnumber']}' " .
            "belonging to the hippotrack with course module id '$this->contextinstanceid'.";
    }

    public function get_url() {
        return new \moodle_url('/mod/hippotrack/edit.php', [
            'cmid' => $this->contextinstanceid
        ]);
    }

    protected function validate_data() {
        parent::validate_data();

        if (!isset($this->objectid)) {
            throw new \coding_exception('The \'objectid\' value must be set.');
        }

        if (!isset($this->contextinstanceid)) {
            throw new \coding_exception('The \'contextinstanceid\' value must be set.');
        }

        if (!isset($this->other['hippotrackid'])) {
            throw new \coding_exception('The \'hippotrackid\' value must be set in other.');
        }

        if (!isset($this->other['firstslotid'])) {
            throw new \coding_exception('The \'firstslotid\' value must be set in other.');
        }

        if (!isset($this->other['firstslotnumber'])) {
            throw new \coding_exception('The \'firstslotnumber\' value must be set in other.');
        }
    }

    public static function get_objectid_mapping() {
        return ['db' => 'hippotrack_sections', 'restore' => 'hippotrack_section'];
    }

    public static function get_other_mapping() {
        $othermapped = [];
        $othermapped['hippotrackid'] = ['db' => 'hippotrack', 'restore' => 'hippotrack'];
        $othermapped['firstslotid'] = ['db' => 'hippotrack_slots', 'restore' => 'hippotrack_question_instance'];

        return $othermapped;
    }
}
