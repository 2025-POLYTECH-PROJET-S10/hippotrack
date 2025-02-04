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

namespace mod_hippotrack\local\structure;

/**
 * Class slot_random, represents a random question slot type.
 *
 * @package    mod_hippotrack
 * @copyright  2018 Shamim Rezaie <shamim@moodle.com>
 * @author     2021 Safat Shahin <safatshahin@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class slot_random {

    /** @var \stdClass Slot's properties. A record retrieved from the hippotrack_slots table. */
    protected $record;

    /**
     * @var \stdClass set reference record
     */
    protected $referencerecord;

    /**
     * @var \stdClass The hippotrack this question slot belongs to.
     */
    protected $hippotrack = null;

    /**
     * @var \core_tag_tag[] List of tags for this slot.
     */
    protected $tags = [];

    /**
     * @var string filter condition
     */
    protected $filtercondition = null;

    /**
     * slot_random constructor.
     *
     * @param \stdClass $slotrecord Represents a record in the hippotrack_slots table.
     */
    public function __construct($slotrecord = null) {
        $this->record = new \stdClass();
        $this->referencerecord = new \stdClass();

        $slotproperties = ['id', 'slot', 'hippotrackid', 'page', 'requireprevious', 'maxmark'];
        $setreferenceproperties = ['usingcontextid', 'questionscontextid'];

        foreach ($slotproperties as $property) {
            if (isset($slotrecord->$property)) {
                $this->record->$property = $slotrecord->$property;
            }
        }

        foreach ($setreferenceproperties as $referenceproperty) {
            if (isset($slotrecord->$referenceproperty)) {
                $this->referencerecord->$referenceproperty = $slotrecord->$referenceproperty;
            }
        }
    }

    /**
     * Returns the hippotrack for this question slot.
     * The hippotrack is fetched the first time it is requested and then stored in a member variable to be returned each subsequent time.
     *
     * @return mixed
     * @throws \coding_exception
     */
    public function get_hippotrack() {
        global $DB;

        if (empty($this->hippotrack)) {
            if (empty($this->record->hippotrackid)) {
                throw new \coding_exception('hippotrackid is not set.');
            }
            $this->hippotrack = $DB->get_record('hippotrack', array('id' => $this->record->hippotrackid));
        }

        return $this->hippotrack;
    }

    /**
     * Sets the hippotrack object for the hippotrack slot.
     * It is not mandatory to set the hippotrack as the hippotrack slot can fetch it the first time it is accessed,
     * however it helps with the performance to set the hippotrack if you already have it.
     *
     * @param \stdClass $hippotrack The qui object.
     */
    public function set_hippotrack($hippotrack) {
        $this->hippotrack = $hippotrack;
        $this->record->hippotrackid = $hippotrack->id;
    }

    /**
     * Set some tags for this hippotrack slot.
     *
     * @param \core_tag_tag[] $tags
     */
    public function set_tags($tags) {
        $this->tags = [];
        foreach ($tags as $tag) {
            // We use $tag->id as the key for the array so not only it handles duplicates of the same tag being given,
            // but also it is consistent with the behaviour of set_tags_by_id() below.
            $this->tags[$tag->id] = $tag;
        }
    }

    /**
     * Set some tags for this hippotrack slot. This function uses tag ids to find tags.
     *
     * @param int[] $tagids
     */
    public function set_tags_by_id($tagids) {
        $this->tags = \core_tag_tag::get_bulk($tagids, 'id, name');
    }

    /**
     * Set filter condition.
     *
     * @param \stdClass $filters
     */
    public function set_filter_condition($filters) {
        if (!empty($this->tags)) {
            $filters->tags = $this->tags;
        }

        $this->filtercondition = json_encode($filters);
    }

    /**
     * Inserts the hippotrack slot at the $page page.
     * It is required to call this function if you are building a hippotrack slot object from scratch.
     *
     * @param int $page The page that this slot will be inserted at.
     */
    public function insert($page) {
        global $DB;

        $slots = $DB->get_records('hippotrack_slots', array('hippotrackid' => $this->record->hippotrackid),
                'slot', 'id, slot, page');
        $hippotrack = $this->get_hippotrack();

        $trans = $DB->start_delegated_transaction();

        $maxpage = 1;
        $numonlastpage = 0;
        foreach ($slots as $slot) {
            if ($slot->page > $maxpage) {
                $maxpage = $slot->page;
                $numonlastpage = 1;
            } else {
                $numonlastpage += 1;
            }
        }

        if (is_int($page) && $page >= 1) {
            // Adding on a given page.
            $lastslotbefore = 0;
            foreach (array_reverse($slots) as $otherslot) {
                if ($otherslot->page > $page) {
                    $DB->set_field('hippotrack_slots', 'slot', $otherslot->slot + 1, array('id' => $otherslot->id));
                } else {
                    $lastslotbefore = $otherslot->slot;
                    break;
                }
            }
            $this->record->slot = $lastslotbefore + 1;
            $this->record->page = min($page, $maxpage + 1);

            hippotrack_update_section_firstslots($this->record->hippotrackid, 1, max($lastslotbefore, 1));
        } else {
            $lastslot = end($slots);
            if ($lastslot) {
                $this->record->slot = $lastslot->slot + 1;
            } else {
                $this->record->slot = 1;
            }
            if ($hippotrack->questionsperpage && $numonlastpage >= $hippotrack->questionsperpage) {
                $this->record->page = $maxpage + 1;
            } else {
                $this->record->page = $maxpage;
            }
        }

        $this->record->id = $DB->insert_record('hippotrack_slots', $this->record);

        $this->referencerecord->component = 'mod_hippotrack';
        $this->referencerecord->questionarea = 'slot';
        $this->referencerecord->itemid = $this->record->id;
        $this->referencerecord->filtercondition = $this->filtercondition;
        $DB->insert_record('question_set_references', $this->referencerecord);

        $trans->allow_commit();

        // Log slot created event.
        $cm = get_coursemodule_from_instance('hippotrack', $hippotrack->id);
        $event = \mod_hippotrack\event\slot_created::create([
            'context' => \context_module::instance($cm->id),
            'objectid' => $this->record->id,
            'other' => [
                'hippotrackid' => $hippotrack->id,
                'slotnumber' => $this->record->slot,
                'page' => $this->record->page
            ]
        ]);
        $event->trigger();
    }
}
