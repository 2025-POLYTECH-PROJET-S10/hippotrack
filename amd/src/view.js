


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

define(['jquery', 'core/log'], function ($, Log) {
    'use strict';
    var cmid;

    function init(value) {
        cmid = value;
        console.log(cmid);
    }

    //Add question button, this redirect the user to edit_question.php when used
    document.getElementById('addQuestionButton').addEventListener('click', function () {
        var url_dbaction = "/mod/hippotrack/dbaction.php";
        window.location.href = url_dbaction + "?cmid=" + cmid;
        // window.location.href = url_dbaction;
    });


    return {
        init: init
    };
});

