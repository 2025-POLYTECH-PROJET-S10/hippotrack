<?php
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
 * Code to be executed after the plugin's database scheme has been installed is defined here.
 *
 * @package     mod_hippotrack
 * @category    upgrade
 * @copyright   2025 Lionel Di Marco <LDiMarco@chu-grenoble.fr>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

/**
 * Custom installation procedure for the hippotrack module.
 */
function xmldb_hippotrack_install()
{
    global $DB, $CFG;
    
    // 📌 Chemin du fichier CSV contenant les ensembles de données
    $csv_file = $CFG->dirroot . '/mod/hippotrack/assets/datasets.csv';

    // Log a message for debugging purposes.
    debugging('Installing the hippotrack module', DEBUG_DEVELOPER);


    // 📌 Vérifier si le fichier existe
    if (!file_exists($csv_file)) {
        debugging('⚠️ Fichier CSV introuvable : ' . $csv_file, DEBUG_DEVELOPER);
        return true;
    }

    // 📌 Ouvrir le fichier CSV
    $handle = fopen($csv_file, 'r');
    if (!$handle) {
        debugging('⚠️ Impossible d’ouvrir le fichier CSV.', DEBUG_DEVELOPER);
        return true;
    }

    $line_number = 0;
    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {  
        $line_number++;
        if ($line_number == 1) {
            continue; // Ignorer l'en-tête
        }

        // 📌 Vérifier que toutes les colonnes sont bien présentes
        if (count($data) < 5) {  // ⚠️ Suppression de `schema_simplifie`, donc 5 colonnes au lieu de 6
            debugging("⚠️ Ligne $line_number mal formatée dans le CSV.", DEBUG_DEVELOPER);
            continue;
        }

        // 📌 Assignation correcte des valeurs
        $name = trim($data[0]);
        $sigle = trim($data[1]);
        $partogramme = trim($data[2]);
        $vue_anterieure = trim($data[3]);
        $vue_laterale = trim($data[4]);

        // 📌 Séparer la rotation et l’inclinaison
        if (strpos($partogramme, ';') !== false) {
            list($rotation, $inclinaison) = explode(';', $partogramme);
        } else {
            $rotation = 0;
            $inclinaison = 0;
        }

        // 📌 Préparer l'objet à insérer en base
        $record = new stdClass();
        $record->name = $name;
        $record->sigle = $sigle;
        $record->rotation = (int) $rotation;
        $record->inclinaison = (int) $inclinaison;
        $record->vue_anterieure = $vue_anterieure;
        $record->vue_laterale = $vue_laterale;

        // 📌 Insérer en base
        $DB->insert_record('hippotrack_datasets', $record);
    }

    fclose($handle);

    debugging('✅ Importation des données initiales terminée avec succès.', DEBUG_DEVELOPER);
    return true;

    return true;
}