// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Prints an instance of hippotrack.
 *
 * @package     mod_hippotrack
 * @copyright   2025 Lionel Di Marco <LDiMarco@chu-grenoble.fr>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery'], function ($) {
    return {
        init: function () {
            $(document).ready(function () {
                var questionCount = $('.question-group').length; // Start count at default (2)

                // Add new question dynamically
                $('.add-question-btn').click(function (e) {
                    e.preventDefault();

                    var newQuestion = `
                        <div class="question-group">
                            <select name="difficulty[${questionCount}]">
                                <option value="Fa">Easy</option>
                                <option value="Di">Hard</option>
                            </select>

                            <select name="question_type[${questionCount}]">
                                <option value="MCQ">Multiple Choice</option>
                                <option value="TF">True/False</option>
                                <option value="SA">Short Answer</option>
                            </select>

                            <textarea name="question_text[${questionCount}]" rows="2"></textarea>

                            <button class="remove-question-btn">Remove</button>
                        </div>
                    `;

                    $('#question-container').append(newQuestion);
                    questionCount++;
                });

                // Remove question dynamically
                $(document).on('click', '.remove-question-btn', function (e) {
                    e.preventDefault();

                    if ($('.question-group').length > 1) {
                        $(this).closest('.question-group').fadeOut(300, function () {
                            $(this).remove();
                        });
                    } else {
                        alert("You must have at least one question!");
                    }
                });
            });
        }
    };
});
