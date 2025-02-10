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
                let angle = 0; // Rotation angle in degrees
                let moveOffset = 0; // Distance moved along the rotated axis

                // Function to get the center of the reference element (#partogramme_contour)
                function getCenter(element) {
                    let $el = $(element);
                    return {
                        x: $el.position().left + $el.width() / 2,
                        y: $el.position().top + $el.height() / 2
                    };
                }

                // Function to update rotation and positioning
                function updatePositions() {
                    let radians = (angle * Math.PI) / 180;
                    let xOffset = Math.sin(radians) * moveOffset; // Moves along rotated axis
                    let yOffset = -Math.cos(radians) * moveOffset; // Moves along rotated axis

                    // Apply rotation to all elements
                    $('#partogramme_contour, #partogramme_contour2, #partogramme_interieur').css({
                        transform: `rotate(${angle}deg)`,
                        transformOrigin: 'center center'
                    });

                    // Move partogramme_interieur along the rotated axis
                    $('#partogramme_interieur').css({
                        transform: `translate(${xOffset}px, ${yOffset}px) rotate(${angle}deg)`,
                        transformOrigin: 'center center'
                    });
                }

                // Handle rotation slider
                $('#rotate-slider').on('input', function () {
                    angle = parseFloat($(this).val()); // Get the slider value
                    updatePositions(); // Update rotation
                });

                // Handle translation slider
                $('#move-axis-slider').on('input', function () {
                    moveOffset = parseFloat($(this).val()); // Get the slider value
                    updatePositions(); // Update movement
                });

                // Ensure initial positioning
                updatePositions();
            });
        }
    };
});
