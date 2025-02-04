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
 * Renderer outputting the hippotrack editing UI.
 *
 * @package mod_hippotrack
 * @copyright 2013 The Open University.
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_hippotrack\output;
defined('MOODLE_INTERNAL') || die();

use core_question\local\bank\question_version_status;
use mod_hippotrack\question\bank\qbank_helper;
use \mod_hippotrack\structure;
use \html_writer;
use qbank_previewquestion\question_preview_options;
use renderable;

/**
 * Renderer outputting the hippotrack editing UI.
 *
 * @copyright 2013 The Open University.
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since Moodle 2.7
 */
class edit_renderer extends \plugin_renderer_base {

    /** @var string The toggle group name of the checkboxes for the toggle-all functionality. */
    protected $togglegroup = 'hippotrack-questions';

    /**
     * Render the edit page
     *
     * @param \hippotrack $hippotrackobj object containing all the hippotrack settings information.
     * @param structure $structure object containing the structure of the hippotrack.
     * @param \core_question\local\bank\question_edit_contexts $contexts the relevant question bank contexts.
     * @param \moodle_url $pageurl the canonical URL of this page.
     * @param array $pagevars the variables from {@link question_edit_setup()}.
     * @return string HTML to output.
     */
    public function edit_page(\hippotrack $hippotrackobj, structure $structure,
        \core_question\local\bank\question_edit_contexts $contexts, \moodle_url $pageurl, array $pagevars) {
        $output = '';

        // Page title.
        $output .= $this->heading(get_string('questions', 'hippotrack'));

        // Information at the top.
        $output .= $this->hippotrack_state_warnings($structure);

        $output .= html_writer::start_div('mod_hippotrack-edit-top-controls');

        $output .= html_writer::start_div('d-flex justify-content-between flex-wrap mb-1');
        $output .= html_writer::start_div('d-flex flex-column justify-content-around');
        $output .= $this->hippotrack_information($structure);
        $output .= html_writer::end_tag('div');
        $output .= $this->maximum_grade_input($structure, $pageurl);
        $output .= html_writer::end_tag('div');

        $output .= html_writer::start_div('d-flex justify-content-between flex-wrap mb-1');
        $output .= html_writer::start_div('mod_hippotrack-edit-action-buttons btn-group edit-toolbar', ['role' => 'group']);
        $output .= $this->repaginate_button($structure, $pageurl);
        $output .= $this->selectmultiple_button($structure);
        $output .= html_writer::end_tag('div');

        $output .= html_writer::start_div('d-flex flex-column justify-content-around');
        $output .= $this->total_marks($hippotrackobj->get_hippotrack());
        $output .= html_writer::end_tag('div');
        $output .= html_writer::end_tag('div');

        $output .= $this->selectmultiple_controls($structure);
        $output .= html_writer::end_tag('div');

        // Show the questions organised into sections and pages.
        $output .= $this->start_section_list($structure);

        foreach ($structure->get_sections() as $section) {
            $output .= $this->start_section($structure, $section);
            $output .= $this->questions_in_section($structure, $section, $contexts, $pagevars, $pageurl);

            if ($structure->is_last_section($section)) {
                $output .= \html_writer::start_div('last-add-menu');
                $output .= html_writer::tag('span', $this->add_menu_actions($structure, 0,
                        $pageurl, $contexts, $pagevars), array('class' => 'add-menu-outer'));
                $output .= \html_writer::end_div();
            }

            $output .= $this->end_section();
        }

        $output .= $this->end_section_list();

        // Initialise the JavaScript.
        $this->initialise_editing_javascript($structure, $contexts, $pagevars, $pageurl);

        // Include the contents of any other popups required.
        if ($structure->can_be_edited()) {
            $thiscontext = $contexts->lowest();
            $this->page->requires->js_call_amd('mod_hippotrack/hippotrackquestionbank', 'init', [
                $thiscontext->id
            ]);

            $this->page->requires->js_call_amd('mod_hippotrack/add_random_question', 'init', [
                $thiscontext->id,
                $pagevars['cat'],
                $pageurl->out_as_local_url(true),
                $pageurl->param('cmid'),
                \core\plugininfo\qbank::is_plugin_enabled(\qbank_managecategories\helper::PLUGINNAME),
            ]);

            // Include the question chooser.
            $output .= $this->question_chooser();
        }

        return $output;
    }

    /**
     * Render any warnings that might be required about the state of the hippotrack,
     * e.g. if it has been attempted, or if the shuffle questions option is
     * turned on.
     *
     * @param structure $structure the hippotrack structure.
     * @return string HTML to output.
     */
    public function hippotrack_state_warnings(structure $structure) {
        $warnings = $structure->get_edit_page_warnings();

        if (empty($warnings)) {
            return '';
        }

        $output = array();
        foreach ($warnings as $warning) {
            $output[] = \html_writer::tag('p', $warning);
        }
        return $this->box(implode("\n", $output), 'statusdisplay');
    }

    /**
     * Render the status bar.
     *
     * @param structure $structure the hippotrack structure.
     * @return string HTML to output.
     */
    public function hippotrack_information(structure $structure) {
        list($currentstatus, $explanation) = $structure->get_dates_summary();

        $output = html_writer::span(
                    get_string('numquestionsx', 'hippotrack', $structure->get_question_count()),
                    'numberofquestions') . ' | ' .
                html_writer::span($currentstatus, 'hippotrackopeningstatus',
                    array('title' => $explanation));

        return html_writer::div($output, 'statusbar');
    }

    /**
     * Render the form for setting a hippotrack' overall grade
     *
     * @param structure $structure the hippotrack structure.
     * @param \moodle_url $pageurl the canonical URL of this page.
     * @return string HTML to output.
     */
    public function maximum_grade_input($structure, \moodle_url $pageurl) {
        $output = '';
        $output .= html_writer::start_div('maxgrade');
        $output .= html_writer::start_tag('form', array('method' => 'post', 'action' => 'edit.php',
                'class' => 'hippotracksavegradesform form-inline'));
        $output .= html_writer::start_tag('fieldset', array('class' => 'invisiblefieldset'));
        $output .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()));
        $output .= html_writer::input_hidden_params($pageurl);
        $output .= html_writer::tag('label', get_string('maximumgrade') . ' ',
                array('for' => 'inputmaxgrade'));
        $output .= html_writer::empty_tag('input', array('type' => 'text', 'id' => 'inputmaxgrade',
                'name' => 'maxgrade', 'size' => ($structure->get_decimal_places_for_grades() + 2),
                'value' => $structure->formatted_hippotrack_grade(),
                'class' => 'form-control'));
        $output .= html_writer::empty_tag('input', array('type' => 'submit', 'class' => 'btn btn-secondary ml-1',
                'name' => 'savechanges', 'value' => get_string('save', 'hippotrack')));
        $output .= html_writer::end_tag('fieldset');
        $output .= html_writer::end_tag('form');
        $output .= html_writer::end_tag('div');
        return $output;
    }

    /**
     * Return the repaginate button
     * @param structure $structure the structure of the hippotrack being edited.
     * @param \moodle_url $pageurl the canonical URL of this page.
     * @return string HTML to output.
     */
    protected function repaginate_button(structure $structure, \moodle_url $pageurl) {
        $header = html_writer::tag('span', get_string('repaginatecommand', 'hippotrack'), array('class' => 'repaginatecommand'));
        $form = $this->repaginate_form($structure, $pageurl);

        $buttonoptions = array(
            'type'  => 'submit',
            'name'  => 'repaginate',
            'id'    => 'repaginatecommand',
            'value' => get_string('repaginatecommand', 'hippotrack'),
            'class' => 'btn btn-secondary mr-1',
            'data-header' => $header,
            'data-form'   => $form,
        );
        if (!$structure->can_be_repaginated()) {
            $buttonoptions['disabled'] = 'disabled';
        } else {
            $this->page->requires->js_call_amd('mod_hippotrack/repaginate', 'init');
        }

        return html_writer::empty_tag('input', $buttonoptions);
    }

    /**
     * Generate the bulk action button.
     *
     * @param structure $structure the structure of the hippotrack being edited.
     * @return string HTML to output.
     */
    protected function selectmultiple_button(structure $structure) {
        $buttonoptions = array(
            'type'  => 'button',
            'name'  => 'selectmultiple',
            'id'    => 'selectmultiplecommand',
            'value' => get_string('selectmultipleitems', 'hippotrack'),
            'class' => 'btn btn-secondary'
        );
        if (!$structure->can_be_edited()) {
            $buttonoptions['disabled'] = 'disabled';
        }

        return html_writer::tag('button', get_string('selectmultipleitems', 'hippotrack'), $buttonoptions);
    }

    /**
     * Generate the controls that appear when the bulk action button is pressed.
     *
     * @param structure $structure the structure of the hippotrack being edited.
     * @return string HTML to output.
     */
    protected function selectmultiple_controls(structure $structure) {
        $output = '';

        // Bulk action button delete and bulk action button cancel.
        $buttondeleteoptions = array(
            'type' => 'button',
            'id' => 'selectmultipledeletecommand',
            'value' => get_string('deleteselected', 'mod_hippotrack'),
            'class' => 'btn btn-secondary',
            'data-action' => 'toggle',
            'data-togglegroup' => $this->togglegroup,
            'data-toggle' => 'action',
            'disabled' => true
        );
        $buttoncanceloptions = array(
            'type' => 'button',
            'id' => 'selectmultiplecancelcommand',
            'value' => get_string('cancel', 'moodle'),
            'class' => 'btn btn-secondary'
        );

        $groupoptions = array(
            'class' => 'btn-group selectmultiplecommand actions m-1',
            'role' => 'group'
        );

        $output .= html_writer::tag('div',
                        html_writer::tag('button', get_string('deleteselected', 'mod_hippotrack'), $buttondeleteoptions) .
                        " " .
                        html_writer::tag('button', get_string('cancel', 'moodle'),
                $buttoncanceloptions), $groupoptions);

        $toolbaroptions = array(
            'class' => 'btn-toolbar m-1',
            'role' => 'toolbar',
            'aria-label' => get_string('selectmultipletoolbar', 'hippotrack'),
        );

        // Select all/deselect all questions.
        $selectallid = 'questionselectall';
        $selectalltext = get_string('selectall', 'moodle');
        $deselectalltext = get_string('deselectall', 'moodle');
        $mastercheckbox = new \core\output\checkbox_toggleall($this->togglegroup, true, [
            'id' => $selectallid,
            'name' => $selectallid,
            'value' => 1,
            'label' => $selectalltext,
            'selectall' => $selectalltext,
            'deselectall' => $deselectalltext,
        ], true);

        $selectdeselect = html_writer::div($this->render($mastercheckbox), 'selectmultiplecommandbuttons');
        $output .= html_writer::tag('div', $selectdeselect, $toolbaroptions);
        return $output;
    }

    /**
     * Return the repaginate form
     * @param structure $structure the structure of the hippotrack being edited.
     * @param \moodle_url $pageurl the canonical URL of this page.
     * @return string HTML to output.
     */
    protected function repaginate_form(structure $structure, \moodle_url $pageurl) {
        $perpage = array();
        $perpage[0] = get_string('allinone', 'hippotrack');
        for ($i = 1; $i <= 50; ++$i) {
            $perpage[$i] = $i;
        }

        $hiddenurl = clone($pageurl);
        $hiddenurl->param('sesskey', sesskey());

        $select = html_writer::select($perpage, 'questionsperpage',
                $structure->get_questions_per_page(), false, array('class' => 'custom-select'));

        $buttonattributes = array(
            'type' => 'submit',
            'name' => 'repaginate',
            'value' => get_string('go'),
            'class' => 'btn btn-secondary ml-1'
        );

        $formcontent = html_writer::tag('form', html_writer::div(
                    html_writer::input_hidden_params($hiddenurl) .
                    get_string('repaginate', 'hippotrack', $select) .
                    html_writer::empty_tag('input', $buttonattributes)
                ), array('action' => 'edit.php', 'method' => 'post'));

        return html_writer::div($formcontent, '', array('id' => 'repaginatedialog'));
    }

    /**
     * Render the total marks available for the hippotrack.
     *
     * @param \stdClass $hippotrack the hippotrack settings from the database.
     * @return string HTML to output.
     */
    public function total_marks($hippotrack) {
        $totalmark = html_writer::span(hippotrack_format_grade($hippotrack, $hippotrack->sumgrades), 'mod_hippotrack_summarks');
        return html_writer::tag('span',
                get_string('totalmarksx', 'hippotrack', $totalmark),
                array('class' => 'totalpoints'));
    }

    /**
     * Generate the starting container html for the start of a list of sections
     * @param structure $structure the structure of the hippotrack being edited.
     * @return string HTML to output.
     */
    protected function start_section_list(structure $structure) {
        $class = 'slots';
        if ($structure->get_section_count() == 1) {
            $class .= ' only-one-section';
        }
        return html_writer::start_tag('ul', array('class' => $class, 'role' => 'presentation'));
    }

    /**
     * Generate the closing container html for the end of a list of sections
     * @return string HTML to output.
     */
    protected function end_section_list() {
        return html_writer::end_tag('ul');
    }

    /**
     * Display the start of a section, before the questions.
     *
     * @param structure $structure the structure of the hippotrack being edited.
     * @param \stdClass $section The hippotrack_section entry from DB
     * @return string HTML to output.
     */
    protected function start_section($structure, $section) {

        $output = '';

        $sectionstyle = '';
        if ($structure->is_only_one_slot_in_section($section)) {
            $sectionstyle = ' only-has-one-slot';
        }

        if ($section->heading) {
            $sectionheadingtext = format_string($section->heading);
            $sectionheading = html_writer::span($sectionheadingtext, 'instancesection');
        } else {
            // Use a sr-only default section heading, so we don't end up with an empty section heading.
            $sectionheadingtext = get_string('sectionnoname', 'hippotrack');
            $sectionheading = html_writer::span($sectionheadingtext, 'instancesection sr-only');
        }

        $output .= html_writer::start_tag('li', array('id' => 'section-'.$section->id,
            'class' => 'section main clearfix'.$sectionstyle, 'role' => 'presentation',
            'data-sectionname' => $sectionheadingtext));

        $output .= html_writer::start_div('content');

        $output .= html_writer::start_div('section-heading');

        $headingtext = $this->heading(html_writer::span($sectionheading, 'sectioninstance'), 3);

        if (!$structure->can_be_edited()) {
            $editsectionheadingicon = '';
        } else {
            $editsectionheadingicon = html_writer::link(new \moodle_url('#'),
                $this->pix_icon('t/editstring', get_string('sectionheadingedit', 'hippotrack', $sectionheadingtext),
                        'moodle', array('class' => 'editicon visibleifjs')),
                        array('class' => 'editing_section', 'data-action' => 'edit_section_title', 'role' => 'button'));
        }
        $output .= html_writer::div($headingtext . $editsectionheadingicon, 'instancesectioncontainer');

        if (!$structure->is_first_section($section) && $structure->can_be_edited()) {
            $output .= $this->section_remove_icon($section);
        }
        $output .= $this->section_shuffle_questions($structure, $section);

        $output .= html_writer::end_div($output, 'section-heading');

        return $output;
    }

    /**
     * Display a checkbox for shuffling question within a section.
     *
     * @param structure $structure object containing the structure of the hippotrack.
     * @param \stdClass $section data from the hippotrack_section table.
     * @return string HTML to output.
     */
    public function section_shuffle_questions(structure $structure, $section) {
        $checkboxattributes = array(
            'type' => 'checkbox',
            'id' => 'shuffle-' . $section->id,
            'value' => 1,
            'data-action' => 'shuffle_questions',
            'class' => 'cm-edit-action',
        );

        if (!$structure->can_be_edited()) {
            $checkboxattributes['disabled'] = 'disabled';
        }
        if ($section->shufflequestions) {
            $checkboxattributes['checked'] = 'checked';
        }

        if ($structure->is_first_section($section)) {
            $help = $this->help_icon('shufflequestions', 'hippotrack');
        } else {
            $help = '';
        }

        $helpspan = html_writer::span($help, 'shuffle-help-tip');
        $progressspan = html_writer::span('', 'shuffle-progress');
        $checkbox = html_writer::empty_tag('input', $checkboxattributes);
        $label = html_writer::label(get_string('shufflequestions', 'hippotrack'),
                $checkboxattributes['id'], false);
        return html_writer::span($progressspan . $checkbox . $label. ' ' . $helpspan,
                'instanceshufflequestions', array('data-action' => 'shuffle_questions'));
    }

    /**
     * Display the end of a section, after the questions.
     *
     * @return string HTML to output.
     */
    protected function end_section() {
        $output = html_writer::end_tag('div');
        $output .= html_writer::end_tag('li');

        return $output;
    }

    /**
     * Render an icon to remove a section from the hippotrack.
     *
     * @param object $section the section to be removed.
     * @return string HTML to output.
     */
    public function section_remove_icon($section) {
        $title = get_string('sectionheadingremove', 'hippotrack', format_string($section->heading));
        $url = new \moodle_url('/mod/hippotrack/edit.php',
                array('sesskey' => sesskey(), 'removesection' => '1', 'sectionid' => $section->id));
        $image = $this->pix_icon('t/delete', $title);
        return $this->action_link($url, $image, null, array(
                'class' => 'cm-edit-action editing_delete', 'data-action' => 'deletesection'));
    }

    /**
     * Renders HTML to display the questions in a section of the hippotrack.
     *
     * This function calls {@link core_course_renderer::hippotrack_section_question()}
     *
     * @param structure $structure object containing the structure of the hippotrack.
     * @param \stdClass $section information about the section.
     * @param \core_question\local\bank\question_edit_contexts $contexts the relevant question bank contexts.
     * @param array $pagevars the variables from {@link \question_edit_setup()}.
     * @param \moodle_url $pageurl the canonical URL of this page.
     * @return string HTML to output.
     */
    public function questions_in_section(structure $structure, $section,
            $contexts, $pagevars, $pageurl) {

        $output = '';
        foreach ($structure->get_slots_in_section($section->id) as $slot) {
            $output .= $this->question_row($structure, $slot, $contexts, $pagevars, $pageurl);
        }
        return html_writer::tag('ul', $output, array('class' => 'section img-text'));
    }

    /**
     * Displays one question with the surrounding controls.
     *
     * @param structure $structure object containing the structure of the hippotrack.
     * @param int $slot which slot we are outputting.
     * @param \core_question\local\bank\question_edit_contexts $contexts the relevant question bank contexts.
     * @param array $pagevars the variables from {@link \question_edit_setup()}.
     * @param \moodle_url $pageurl the canonical URL of this page.
     * @return string HTML to output.
     */
    public function question_row(structure $structure, $slot, $contexts, $pagevars, $pageurl) {
        $output = '';

        $output .= $this->page_row($structure, $slot, $contexts, $pagevars, $pageurl);

        // Page split/join icon.
        $joinhtml = '';
        if ($structure->can_be_edited() && !$structure->is_last_slot_in_hippotrack($slot) &&
                                            !$structure->is_last_slot_in_section($slot)) {
            $joinhtml = $this->page_split_join_button($structure, $slot);
        }
        // Question HTML.
        $questionhtml = $this->question($structure, $slot, $pageurl);
        $qtype = $structure->get_question_type_for_slot($slot);
        $questionclasses = 'activity ' . $qtype . ' qtype_' . $qtype . ' slot';

        $output .= html_writer::tag('li', $questionhtml . $joinhtml,
                array('class' => $questionclasses, 'id' => 'slot-' . $structure->get_slot_id_for_slot($slot),
                        'data-canfinish' => $structure->can_finish_during_the_attempt($slot)));

        return $output;
    }

    /**
     * Displays one question with the surrounding controls.
     *
     * @param structure $structure object containing the structure of the hippotrack.
     * @param int $slot the first slot on the page we are outputting.
     * @param \core_question\local\bank\question_edit_contexts $contexts the relevant question bank contexts.
     * @param array $pagevars the variables from {@link \question_edit_setup()}.
     * @param \moodle_url $pageurl the canonical URL of this page.
     * @return string HTML to output.
     */
    public function page_row(structure $structure, $slot, $contexts, $pagevars, $pageurl) {
        $output = '';

        $pagenumber = $structure->get_page_number_for_slot($slot);

        // Put page in a heading for accessibility and styling.
        $page = $this->heading(get_string('page') . ' ' . $pagenumber, 4);

        if ($structure->is_first_slot_on_page($slot)) {
            // Add the add-menu at the page level.
            $addmenu = html_writer::tag('span', $this->add_menu_actions($structure,
                    $pagenumber, $pageurl, $contexts, $pagevars),
                    array('class' => 'add-menu-outer'));

            $addquestionform = $this->add_question_form($structure,
                    $pagenumber, $pageurl, $pagevars);

            $output .= html_writer::tag('li', $page . $addmenu . $addquestionform,
                    array('class' => 'pagenumber activity yui3-dd-drop page', 'id' => 'page-' . $pagenumber));
        }

        return $output;
    }

    /**
     * Returns the add menu that is output once per page.
     * @param structure $structure object containing the structure of the hippotrack.
     * @param int $page the page number that this menu will add to.
     * @param \moodle_url $pageurl the canonical URL of this page.
     * @param \core_question\local\bank\question_edit_contexts $contexts the relevant question bank contexts.
     * @param array $pagevars the variables from {@link \question_edit_setup()}.
     * @return string HTML to output.
     */
    public function add_menu_actions(structure $structure, $page, \moodle_url $pageurl,
            \core_question\local\bank\question_edit_contexts $contexts, array $pagevars) {

        $actions = $this->edit_menu_actions($structure, $page, $pageurl, $pagevars);
        if (empty($actions)) {
            return '';
        }
        $menu = new \action_menu();
        $menu->set_constraint('.mod-hippotrack-edit-content');
        $trigger = html_writer::tag('span', get_string('add', 'hippotrack'), array('class' => 'add-menu'));
        $menu->set_menu_trigger($trigger);
        // The menu appears within an absolutely positioned element causing width problems.
        // Make sure no-wrap is set so that we don't get a squashed menu.
        $menu->set_nowrap_on_items(true);

        // Disable the link if hippotrack has attempts.
        if (!$structure->can_be_edited()) {
            return $this->render($menu);
        }

        foreach ($actions as $action) {
            if ($action instanceof \action_menu_link) {
                $action->add_class('add-menu');
            }
            $menu->add($action);
        }
        $menu->attributes['class'] .= ' page-add-actions commands';

        // Prioritise the menu ahead of all other actions.
        $menu->prioritise = true;

        return $this->render($menu);
    }

    /**
     * Returns the list of actions to go in the add menu.
     * @param structure $structure object containing the structure of the hippotrack.
     * @param int $page the page number that this menu will add to.
     * @param \moodle_url $pageurl the canonical URL of this page.
     * @param array $pagevars the variables from {@link \question_edit_setup()}.
     * @return array the actions.
     */
    public function edit_menu_actions(structure $structure, $page,
            \moodle_url $pageurl, array $pagevars) {
        $questioncategoryid = question_get_category_id_from_pagevars($pagevars);
        static $str;
        if (!isset($str)) {
            $str = get_strings(array('addasection', 'addaquestion', 'addarandomquestion',
                    'addarandomselectedquestion', 'questionbank'), 'hippotrack');
        }

        // Get section, page, slotnumber and maxmark.
        $actions = array();

        // Add a new question to the hippotrack.
        $returnurl = new \moodle_url($pageurl, array('addonpage' => $page));
        $params = array('returnurl' => $returnurl->out_as_local_url(false),
                'cmid' => $structure->get_cmid(), 'category' => $questioncategoryid,
                'addonpage' => $page, 'appendqnumstring' => 'addquestion');

        $actions['addaquestion'] = new \action_menu_link_secondary(
            new \moodle_url('/question/bank/editquestion/addquestion.php', $params),
            new \pix_icon('t/add', $str->addaquestion, 'moodle', array('class' => 'iconsmall', 'title' => '')),
            $str->addaquestion, array('class' => 'cm-edit-action addquestion', 'data-action' => 'addquestion')
        );

        // Call question bank.
        $icon = new \pix_icon('t/add', $str->questionbank, 'moodle', array('class' => 'iconsmall', 'title' => ''));
        if ($page) {
            $title = get_string('addquestionfrombanktopage', 'hippotrack', $page);
        } else {
            $title = get_string('addquestionfrombankatend', 'hippotrack');
        }
        $attributes = array('class' => 'cm-edit-action questionbank',
                'data-header' => $title, 'data-action' => 'questionbank', 'data-addonpage' => $page);
        $actions['questionbank'] = new \action_menu_link_secondary($pageurl, $icon, $str->questionbank, $attributes);

        // Add a random question.
        if ($structure->can_add_random_questions()) {
            $returnurl = new \moodle_url('/mod/hippotrack/edit.php', array('cmid' => $structure->get_cmid(), 'data-addonpage' => $page));
            $params = ['returnurl' => $returnurl, 'cmid' => $structure->get_cmid(), 'appendqnumstring' => 'addarandomquestion'];
            $url = new \moodle_url('/mod/hippotrack/addrandom.php', $params);
            $icon = new \pix_icon('t/add', $str->addarandomquestion, 'moodle', array('class' => 'iconsmall', 'title' => ''));
            $attributes = array('class' => 'cm-edit-action addarandomquestion', 'data-action' => 'addarandomquestion');
            if ($page) {
                $title = get_string('addrandomquestiontopage', 'hippotrack', $page);
            } else {
                $title = get_string('addrandomquestionatend', 'hippotrack');
            }
            $attributes = array_merge(array('data-header' => $title, 'data-addonpage' => $page), $attributes);
            $actions['addarandomquestion'] = new \action_menu_link_secondary($url, $icon, $str->addarandomquestion, $attributes);
        }

        // Add a new section to the add_menu if possible. This is always added to the HTML
        // then hidden with CSS when no needed, so that as things are re-ordered, etc. with
        // Ajax it can be relevaled again when necessary.
        $params = array('cmid' => $structure->get_cmid(), 'addsectionatpage' => $page);

        $actions['addasection'] = new \action_menu_link_secondary(
            new \moodle_url($pageurl, $params),
            new \pix_icon('t/add', $str->addasection, 'moodle', array('class' => 'iconsmall', 'title' => '')),
            $str->addasection, array('class' => 'cm-edit-action addasection', 'data-action' => 'addasection')
        );

        return $actions;
    }

    /**
     * Render the form that contains the data for adding a new question to the hippotrack.
     *
     * @param structure $structure object containing the structure of the hippotrack.
     * @param int $page the page number that this menu will add to.
     * @param \moodle_url $pageurl the canonical URL of this page.
     * @param array $pagevars the variables from {@link \question_edit_setup()}.
     * @return string HTML to output.
     */
    protected function add_question_form(structure $structure, $page, \moodle_url $pageurl, array $pagevars) {

        $questioncategoryid = question_get_category_id_from_pagevars($pagevars);

        $output = html_writer::tag('input', null,
                array('type' => 'hidden', 'name' => 'returnurl',
                        'value' => $pageurl->out_as_local_url(false, array('addonpage' => $page))));
        $output .= html_writer::tag('input', null,
                array('type' => 'hidden', 'name' => 'cmid', 'value' => $structure->get_cmid()));
        $output .= html_writer::tag('input', null,
                array('type' => 'hidden', 'name' => 'appendqnumstring', 'value' => 'addquestion'));
        $output .= html_writer::tag('input', null,
                array('type' => 'hidden', 'name' => 'category', 'value' => $questioncategoryid));

        return html_writer::tag('form', html_writer::div($output),
                array('class' => 'addnewquestion', 'method' => 'post',
                        'action' => new \moodle_url('/question/bank/editquestion/addquestion.php')));
    }

    /**
     * Display a question.
     *
     * @param structure $structure object containing the structure of the hippotrack.
     * @param int $slot the first slot on the page we are outputting.
     * @param \moodle_url $pageurl the canonical URL of this page.
     * @return string HTML to output.
     */
    public function question(structure $structure, int $slot, \moodle_url $pageurl) {
        // Get the data required by the question_slot template.
        $slotid = $structure->get_slot_id_for_slot($slot);

        $output = '';
        $output .= html_writer::start_tag('div');

        if ($structure->can_be_edited()) {
            $output .= $this->question_move_icon($structure, $slot);
        }

        $data = [
            'slotid' => $slotid,
            'canbeedited' => $structure->can_be_edited(),
            'checkbox' => $this->get_checkbox_render($structure, $slot),
            'questionnumber' => $this->question_number($structure->get_displayed_number_for_slot($slot)),
            'questionname' => $this->get_question_name_for_slot($structure, $slot, $pageurl),
            'questionicons' => $this->get_action_icon($structure, $slot, $pageurl),
            'questiondependencyicon' => ($structure->can_be_edited() ? $this->question_dependency_icon($structure, $slot) : ''),
            'versionselection' => false,
            'draftversion' => $structure->get_question_in_slot($slot)->status == question_version_status::QUESTION_STATUS_DRAFT,
        ];

        $data['versionoptions'] = [];
        if ($structure->get_slot_by_number($slot)->qtype !== 'random') {
            $data['versionselection'] = true;
            $data['versionoption'] = $structure->get_version_choices_for_slot($slot);
            $this->page->requires->js_call_amd('mod_hippotrack/question_slot', 'init', [$slotid]);
        }

        // Render the question slot template.
        $output .= $this->render_from_template('mod_hippotrack/question_slot', $data);

        $output .= html_writer::end_tag('div');

        return $output;
    }

    /**
     * Get the checkbox render.
     *
     * @param structure $structure object containing the structure of the hippotrack.
     * @param int $slot the slot on the page we are outputting.
     * @return string HTML to output.
     */
    public function get_checkbox_render(structure $structure, int $slot) : string {
        $questionslot = $structure->get_displayed_number_for_slot($slot);
        $checkbox = new \core\output\checkbox_toggleall($this->togglegroup, false,
            [
                'id' => 'selectquestion-' . $questionslot,
                'name' => 'selectquestion[]',
                'value' => $questionslot,
                'classes' => 'select-multiple-checkbox',
                'label' => get_string('selectquestionslot', 'hippotrack', $questionslot),
                'labelclasses' => 'sr-only',
            ]);

        return $this->render($checkbox);
    }

    /**
     * Get the question name for the slot.
     *
     * @param structure $structure object containing the structure of the hippotrack.
     * @param int $slot the slot on the page we are outputting.
     * @param \moodle_url $pageurl the canonical URL of this page.
     * @return string HTML to output.
     */
    public function get_question_name_for_slot(structure $structure, int $slot, \moodle_url $pageurl) : string {
        // Display the link to the question (or do nothing if question has no url).
        if ($structure->get_question_type_for_slot($slot) === 'random') {
            $questionname = $this->random_question($structure, $slot, $pageurl);
        } else {
            $questionname = $this->question_name($structure, $slot, $pageurl);
        }

        return $questionname;
    }

    /**
     * Get the action icons render.
     *
     * @param structure $structure object containing the structure of the hippotrack.
     * @param int $slot the slot on the page we are outputting.
     * @param \moodle_url $pageurl the canonical URL of this page.
     * @return string HTML to output.
     */
    public function get_action_icon(structure $structure, int $slot, \moodle_url $pageurl) : string {
        // Action icons.
        $qtype = $structure->get_question_type_for_slot($slot);
        $slotinfo = $structure->get_slot_by_number($slot);
        $questionicons = '';
        if ($qtype !== 'random') {
            $questionicons .= $this->question_preview_icon($structure->get_hippotrack(),
                    $structure->get_question_in_slot($slot),
                    null, null, $slotinfo->requestedversion ?: question_preview_options::ALWAYS_LATEST);
        }
        if ($structure->can_be_edited() && $structure->has_use_capability($slot)) {
            $questionicons .= $this->question_remove_icon($structure, $slot, $pageurl);
        }
        $questionicons .= $this->marked_out_of_field($structure, $slot);

        return $questionicons;
    }

    /**
     * Render the move icon.
     *
     * @param structure $structure object containing the structure of the hippotrack.
     * @param int $slot the first slot on the page we are outputting.
     * @return string The markup for the move action.
     */
    public function question_move_icon(structure $structure, $slot) {
        return html_writer::link(new \moodle_url('#'),
            $this->pix_icon('i/dragdrop', get_string('move'), 'moodle', array('class' => 'iconsmall', 'title' => '')),
            array('class' => 'editing_move', 'data-action' => 'move')
        );
    }

    /**
     * Output the question number.
     * @param string $number The number, or 'i'.
     * @return string HTML to output.
     */
    public function question_number($number) {
        if (is_numeric($number)) {
            $number = html_writer::span(get_string('question'), 'accesshide') . ' ' . $number;
        }
        return html_writer::tag('span', $number, array('class' => 'slotnumber'));
    }

    /**
     * Render the preview icon.
     *
     * @param \stdClass $hippotrack the hippotrack settings from the database.
     * @param \stdClass $questiondata which question to preview.
     *      If ->questionid is set, that is used instead of ->id.
     * @param bool $label if true, show the preview question label after the icon
     * @param int $variant which question variant to preview (optional).
     * @param int $restartversion version to use when restarting the preview
     * @return string HTML to output.
     */
    public function question_preview_icon($hippotrack, $questiondata, $label = null, $variant = null, $restartversion = null) {
        $question = clone($questiondata);
        if (isset($question->questionid)) {

            $question->id = $question->questionid;
        }

        $url = hippotrack_question_preview_url($hippotrack, $question, $variant, $restartversion);

        // Do we want a label?
        $strpreviewlabel = '';
        if ($label) {
            $strpreviewlabel = ' ' . get_string('preview', 'hippotrack');
        }

        // Build the icon.
        $strpreviewquestion = get_string('previewquestion', 'hippotrack');
        $image = $this->pix_icon('t/preview', $strpreviewquestion);

        $action = new \popup_action('click', $url, 'questionpreview',
                \qbank_previewquestion\helper::question_preview_popup_params());

        return $this->action_link($url, $image . $strpreviewlabel, $action,
                array('title' => $strpreviewquestion, 'class' => 'preview'));
    }

    /**
     * Render an icon to remove a question from the hippotrack.
     *
     * @param structure $structure object containing the structure of the hippotrack.
     * @param int $slot the first slot on the page we are outputting.
     * @param \moodle_url $pageurl the canonical URL of the edit page.
     * @return string HTML to output.
     */
    public function question_remove_icon(structure $structure, $slot, $pageurl) {
        $url = new \moodle_url($pageurl, array('sesskey' => sesskey(), 'remove' => $slot));
        $strdelete = get_string('delete');

        $image = $this->pix_icon('t/delete', $strdelete);

        return $this->action_link($url, $image, null, array('title' => $strdelete,
                    'class' => 'cm-edit-action editing_delete', 'data-action' => 'delete'));
    }

    /**
     * Display an icon to split or join two pages of the hippotrack.
     *
     * @param structure $structure object containing the structure of the hippotrack.
     * @param int $slot the first slot on the page we are outputting.
     * @return string HTML to output.
     */
    public function page_split_join_button($structure, $slot) {
        $insertpagebreak = !$structure->is_last_slot_on_page($slot);
        $url = new \moodle_url('repaginate.php', array('hippotrackid' => $structure->get_hippotrackid(),
                'slot' => $slot, 'repag' => $insertpagebreak ? 2 : 1, 'sesskey' => sesskey()));

        if ($insertpagebreak) {
            $title = get_string('addpagebreak', 'hippotrack');
            $image = $this->image_icon('e/insert_page_break', $title);
            $action = 'addpagebreak';
        } else {
            $title = get_string('removepagebreak', 'hippotrack');
            $image = $this->image_icon('e/remove_page_break', $title);
            $action = 'removepagebreak';
        }

        // Disable the link if hippotrack has attempts.
        $disabled = null;
        if (!$structure->can_be_edited()) {
            $disabled = 'disabled';
        }
        return html_writer::span($this->action_link($url, $image, null, array('title' => $title,
                    'class' => 'page_split_join cm-edit-action', 'disabled' => $disabled, 'data-action' => $action)),
                'page_split_join_wrapper');
    }

    /**
     * Display the icon for whether this question can only be seen if the previous
     * one has been answered.
     *
     * @param structure $structure object containing the structure of the hippotrack.
     * @param int $slot the first slot on the page we are outputting.
     * @return string HTML to output.
     */
    public function question_dependency_icon($structure, $slot) {
        $a = array(
            'thisq' => $structure->get_displayed_number_for_slot($slot),
            'previousq' => $structure->get_displayed_number_for_slot(max($slot - 1, 1)),
        );
        if ($structure->is_question_dependent_on_previous_slot($slot)) {
            $title = get_string('questiondependencyremove', 'hippotrack', $a);
            $image = $this->pix_icon('t/locked', get_string('questiondependsonprevious', 'hippotrack'),
                    'moodle', array('title' => ''));
            $action = 'removedependency';
        } else {
            $title = get_string('questiondependencyadd', 'hippotrack', $a);
            $image = $this->pix_icon('t/unlocked', get_string('questiondependencyfree', 'hippotrack'),
                    'moodle', array('title' => ''));
            $action = 'adddependency';
        }

        // Disable the link if hippotrack has attempts.
        $disabled = null;
        if (!$structure->can_be_edited()) {
            $disabled = 'disabled';
        }
        $extraclass = '';
        if (!$structure->can_question_depend_on_previous_slot($slot)) {
            $extraclass = ' question_dependency_cannot_depend';
        }
        return html_writer::span($this->action_link('#', $image, null, array('title' => $title,
                'class' => 'cm-edit-action', 'disabled' => $disabled, 'data-action' => $action)),
                'question_dependency_wrapper' . $extraclass);
    }

    /**
     * Renders html to display a name with the link to the question on a hippotrack edit page
     *
     * If the user does not have permission to edi the question, it is rendered
     * without a link
     *
     * @param structure $structure object containing the structure of the hippotrack.
     * @param int $slot which slot we are outputting.
     * @param \moodle_url $pageurl the canonical URL of this page.
     * @return string HTML to output.
     */
    public function question_name(structure $structure, $slot, $pageurl) {
        $output = '';

        $question = $structure->get_question_in_slot($slot);
        $editurl = new \moodle_url('/question/bank/editquestion/question.php', array(
                'returnurl' => $pageurl->out_as_local_url(),
                'cmid' => $structure->get_cmid(), 'id' => $question->questionid));

        $instancename = hippotrack_question_tostring($question);

        $qtype = \question_bank::get_qtype($question->qtype, false);
        $namestr = $qtype->local_name();

        $icon = $this->pix_icon('icon', $namestr, $qtype->plugin_name(), array('title' => $namestr,
                'class' => 'activityicon', 'alt' => ' ', 'role' => 'presentation'));

        $editicon = $this->pix_icon('t/edit', '', 'moodle', array('title' => ''));

        // Need plain question name without html tags for link title.
        $title = shorten_text(format_string($question->name), 100);

        // Display the link itself.
        $activitylink = $icon . html_writer::tag('span', $editicon . $instancename, array('class' => 'instancename'));
        $output .= html_writer::link($editurl, $activitylink,
                array('title' => get_string('editquestion', 'hippotrack').' '.$title));

        return $output;
    }

    /**
     * Renders html to display a random question the link to edit the configuration
     * and also to see that category in the question bank.
     *
     * @param structure $structure object containing the structure of the hippotrack.
     * @param int $slotnumber which slot we are outputting.
     * @param \moodle_url $pageurl the canonical URL of this page.
     * @return string HTML to output.
     */
    public function random_question(structure $structure, $slotnumber, $pageurl) {
        $question = $structure->get_question_in_slot($slotnumber);
        $slot = $structure->get_slot_by_number($slotnumber);
        $editurl = new \moodle_url('/mod/hippotrack/editrandom.php',
                array('returnurl' => $pageurl->out_as_local_url(), 'slotid' => $slot->id));

        $temp = clone($question);
        $temp->questiontext = '';
        $temp->name = qbank_helper::describe_random_question($slot);
        $instancename = hippotrack_question_tostring($temp);

        $configuretitle = get_string('configurerandomquestion', 'hippotrack');
        $qtype = \question_bank::get_qtype($question->qtype, false);
        $namestr = $qtype->local_name();
        $icon = $this->pix_icon('icon', $namestr, $qtype->plugin_name(), array('title' => $namestr,
                'class' => 'icon activityicon', 'alt' => ' ', 'role' => 'presentation'));

        $editicon = $this->pix_icon('t/edit', $configuretitle, 'moodle', array('title' => ''));
        $qbankurlparams = [
            'cmid' => $structure->get_cmid(),
            'cat' => $slot->category . ',' . $slot->contextid,
            'recurse' => $slot->randomrecurse,
        ];

        $slottags = [];
        if (isset($slot->randomtags)) {
            $slottags = $slot->randomtags;
        }
        foreach ($slottags as $index => $slottag) {
            $slottag = explode(',', $slottag);
            $qbankurlparams["qtagids[{$index}]"] = $slottag[0];
        }

        // If this is a random question, display a link to show the questions
        // selected from in the question bank.
        $qbankurl = new \moodle_url('/question/edit.php', $qbankurlparams);
        $qbanklink = ' ' . \html_writer::link($qbankurl,
                        get_string('seequestions', 'hippotrack'), array('class' => 'mod_hippotrack_random_qbank_link'));

        return html_writer::link($editurl, $icon . $editicon, array('title' => $configuretitle)) .
                ' ' . $instancename . ' ' . $qbanklink;
    }

    /**
     * Display the 'marked out of' information for a question.
     * Along with the regrade action.
     * @param structure $structure object containing the structure of the hippotrack.
     * @param int $slot which slot we are outputting.
     * @return string HTML to output.
     */
    public function marked_out_of_field(structure $structure, $slot) {
        if (!$structure->is_real_question($slot)) {
            $output = html_writer::span('',
                    'instancemaxmark decimalplaces_' . $structure->get_decimal_places_for_question_marks());

            $output .= html_writer::span(
                    $this->pix_icon('spacer', '', 'moodle', array('class' => 'editicon visibleifjs', 'title' => '')),
                    'editing_maxmark');
            return html_writer::span($output, 'instancemaxmarkcontainer infoitem');
        }

        $output = html_writer::span($structure->formatted_question_grade($slot),
                'instancemaxmark decimalplaces_' . $structure->get_decimal_places_for_question_marks(),
                array('title' => get_string('maxmark', 'hippotrack')));

        $output .= html_writer::span(
            html_writer::link(
                new \moodle_url('#'),
                $this->pix_icon('t/editstring', '', 'moodle', array('class' => 'editicon visibleifjs', 'title' => '')),
                array(
                    'class' => 'editing_maxmark',
                    'data-action' => 'editmaxmark',
                    'title' => get_string('editmaxmark', 'hippotrack'),
                )
            )
        );
        return html_writer::span($output, 'instancemaxmarkcontainer');
    }

    /**
     * Renders the question chooser.
     *
     * @param renderable
     * @return string
     */
    public function render_question_chooser(renderable $chooser) {
        return $this->render_from_template('mod_hippotrack/question_chooser', $chooser->export_for_template($this));
    }

    /**
     * Render the question type chooser dialogue.
     * @return string HTML to output.
     */
    public function question_chooser() {
        $chooser = \mod_hippotrack\output\question_chooser::get($this->page->course, [], null);
        $container = html_writer::div($this->render($chooser), '', array('id' => 'qtypechoicecontainer'));
        return html_writer::div($container, 'createnewquestion');
    }

    /**
     * Render the contents of the question bank pop-up in its initial state,
     * when it just contains a loading progress indicator.
     * @return string HTML to output.
     */
    public function question_bank_loading() {
        return html_writer::div($this->pix_icon('i/loading', get_string('loading')), 'questionbankloading');
    }

    /**
     * Initialise the JavaScript for the general editing. (JavaScript for popups
     * is handled with the specific code for those.)
     *
     * @param structure $structure object containing the structure of the hippotrack.
     * @param \core_question\local\bank\question_edit_contexts $contexts the relevant question bank contexts.
     * @param array $pagevars the variables from {@link \question_edit_setup()}.
     * @param \moodle_url $pageurl the canonical URL of this page.
     * @return bool Always returns true
     */
    protected function initialise_editing_javascript(structure $structure,
            \core_question\local\bank\question_edit_contexts $contexts, array $pagevars, \moodle_url $pageurl) {

        $config = new \stdClass();
        $config->resourceurl = '/mod/hippotrack/edit_rest.php';
        $config->sectionurl = '/mod/hippotrack/edit_rest.php';
        $config->pageparams = array();
        $config->questiondecimalpoints = $structure->get_decimal_places_for_question_marks();
        $config->pagehtml = $this->new_page_template($structure, $contexts, $pagevars, $pageurl);
        $config->addpageiconhtml = $this->add_page_icon_template($structure);

        $this->page->requires->yui_module('moodle-mod_hippotrack-toolboxes',
                'M.mod_hippotrack.init_resource_toolbox',
                array(array(
                        'courseid' => $structure->get_courseid(),
                        'hippotrackid' => $structure->get_hippotrackid(),
                        'ajaxurl' => $config->resourceurl,
                        'config' => $config,
                ))
        );
        unset($config->pagehtml);
        unset($config->addpageiconhtml);

        $this->page->requires->strings_for_js(array('areyousureremoveselected'), 'hippotrack');
        $this->page->requires->yui_module('moodle-mod_hippotrack-toolboxes',
                'M.mod_hippotrack.init_section_toolbox',
                array(array(
                        'courseid' => $structure,
                        'hippotrackid' => $structure->get_hippotrackid(),
                        'ajaxurl' => $config->sectionurl,
                        'config' => $config,
                ))
        );

        $this->page->requires->yui_module('moodle-mod_hippotrack-dragdrop', 'M.mod_hippotrack.init_section_dragdrop',
                array(array(
                        'courseid' => $structure,
                        'hippotrackid' => $structure->get_hippotrackid(),
                        'ajaxurl' => $config->sectionurl,
                        'config' => $config,
                )), null, true);

        $this->page->requires->yui_module('moodle-mod_hippotrack-dragdrop', 'M.mod_hippotrack.init_resource_dragdrop',
                array(array(
                        'courseid' => $structure,
                        'hippotrackid' => $structure->get_hippotrackid(),
                        'ajaxurl' => $config->resourceurl,
                        'config' => $config,
                )), null, true);

        // Require various strings for the command toolbox.
        $this->page->requires->strings_for_js(array(
                'clicktohideshow',
                'deletechecktype',
                'deletechecktypename',
                'edittitle',
                'edittitleinstructions',
                'emptydragdropregion',
                'hide',
                'markedthistopic',
                'markthistopic',
                'move',
                'movecontent',
                'moveleft',
                'movesection',
                'page',
                'question',
                'selectall',
                'show',
                'tocontent',
        ), 'moodle');

        $this->page->requires->strings_for_js(array(
                'addpagebreak',
                'cannotremoveallsectionslots',
                'cannotremoveslots',
                'confirmremovesectionheading',
                'confirmremovequestion',
                'dragtoafter',
                'dragtostart',
                'numquestionsx',
                'sectionheadingedit',
                'sectionheadingremove',
                'sectionnoname',
                'removepagebreak',
                'questiondependencyadd',
                'questiondependencyfree',
                'questiondependencyremove',
                'questiondependsonprevious',
        ), 'hippotrack');

        foreach (\question_bank::get_all_qtypes() as $qtype => $notused) {
            $this->page->requires->string_for_js('pluginname', 'qtype_' . $qtype);
        }

        return true;
    }

    /**
     * HTML for a page, with ids stripped, so it can be used as a javascript template.
     *
     * @param structure $structure object containing the structure of the hippotrack.
     * @param \core_question\local\bank\question_edit_contexts $contexts the relevant question bank contexts.
     * @param array $pagevars the variables from {@link \question_edit_setup()}.
     * @param \moodle_url $pageurl the canonical URL of this page.
     * @return string HTML for a new page.
     */
    protected function new_page_template(structure $structure,
            \core_question\local\bank\question_edit_contexts $contexts, array $pagevars, \moodle_url $pageurl) {
        if (!$structure->has_questions()) {
            return '';
        }

        $pagehtml = $this->page_row($structure, 1, $contexts, $pagevars, $pageurl);

        // Normalise the page number.
        $pagenumber = $structure->get_page_number_for_slot(1);
        $strcontexts = array();
        $strcontexts[] = 'page-';
        $strcontexts[] = get_string('page') . ' ';
        $strcontexts[] = 'addonpage%3D';
        $strcontexts[] = 'addonpage=';
        $strcontexts[] = 'addonpage="';
        $strcontexts[] = get_string('addquestionfrombanktopage', 'hippotrack', '');
        $strcontexts[] = 'data-addonpage%3D';
        $strcontexts[] = 'action-menu-';

        foreach ($strcontexts as $strcontext) {
            $pagehtml = str_replace($strcontext . $pagenumber, $strcontext . '%%PAGENUMBER%%', $pagehtml);
        }

        return $pagehtml;
    }

    /**
     * HTML for a page, with ids stripped, so it can be used as a javascript template.
     *
     * @param structure $structure object containing the structure of the hippotrack.
     * @return string HTML for a new icon
     */
    protected function add_page_icon_template(structure $structure) {

        if (!$structure->has_questions()) {
            return '';
        }

        $html = $this->page_split_join_button($structure, 1);
        return str_replace('&amp;slot=1&amp;', '&amp;slot=%%SLOT%%&amp;', $html);
    }

    /**
     * Return the contents of the question bank, to be displayed in the question-bank pop-up.
     *
     * @param \mod_hippotrack\question\bank\custom_view $questionbank the question bank view object.
     * @param array $pagevars the variables from {@link \question_edit_setup()}.
     * @return string HTML to output / send back in response to an AJAX request.
     */
    public function question_bank_contents(\mod_hippotrack\question\bank\custom_view $questionbank, array $pagevars) {

        $qbank = $questionbank->render($pagevars, 'editq');
        return html_writer::div(html_writer::div($qbank, 'bd'), 'questionbankformforpopup');
    }
}
