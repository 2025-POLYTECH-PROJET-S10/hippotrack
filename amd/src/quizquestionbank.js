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
 * Initialise the question bank modal on the hippotrack page.
 *
 * @module    mod_hippotrack/hippotrackquestionbank
 * @copyright 2018 Ryan Wyllie <ryan@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(
    [
        'mod_hippotrack/add_question_modal_launcher',
        'mod_hippotrack/modal_hippotrack_question_bank'
    ],
    function (
        AddQuestionModalLauncher,
        ModalHippoTrackQuestionBank
    ) {

        return {
            /**
             * Create the question bank modal.
             *
             * @param  {int} contextId Current context id.
             */
            init: function (contextId) {
                AddQuestionModalLauncher.init(
                    ModalHippoTrackQuestionBank.TYPE,
                    '.menu [data-action="questionbank"]',
                    contextId
                );
            }
        };
    });
