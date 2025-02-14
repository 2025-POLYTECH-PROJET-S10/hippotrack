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
                $(".container").each(function () {
                    let $container = $(this);
                    let schemaType = $container.data("schema-type"); // Get schema type
                    let angle = 0;
                    let moveOffset = 0;

                    // Find the correct interior image dynamically
                    let $interiorImage = $container.find("img").filter(function () {
                        return this.className.includes("partogramme_interieur");
                    });

                    // Function to update rotation and positioning
                    function updatePositions() {
                        let radians = (angle * Math.PI) / 180;
                        let xOffset = Math.sin(radians) * moveOffset;
                        let yOffset = -Math.cos(radians) * moveOffset;

                        // Apply transformations only to elements within this container
                        $container.find(".partogramme_contour, .partogramme_contour2").css({
                            transform: `rotate(${angle}deg)`,
                            transformOrigin: 'center center'
                        });

                        // Move only the correct interior image
                        $interiorImage.css({
                            transform: `translate(${xOffset}px, ${yOffset}px) rotate(${angle}deg)`,
                            transformOrigin: 'center center'
                        });

                        console.log(`Updated ${schemaType} -> Angle: ${angle}, Move Offset: ${moveOffset}`);
                    }

                    // Handle rotation slider
                    $container.closest("form").find(".rotate-slider").on("input", function () {
                        angle = parseFloat($(this).val());
                        updatePositions();
                    });

                    // Handle movement slider
                    $container.closest("form").find(".move-axis-slider").on("input", function () {
                        moveOffset = parseFloat($(this).val());
                        updatePositions();
                    });

                    updatePositions(); // Ensure initial positioning
                });
            });
        }
    };
});
