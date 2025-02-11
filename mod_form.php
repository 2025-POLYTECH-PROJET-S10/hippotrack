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
 * The main hippotrack configuration form.
 *
 * @package     mod_hippotrack
 * @copyright   2025 Lionel Di Marco
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

/**
 * Module instance settings form.
 *
 * @package     mod_hippotrack
 * @copyright   2025 Lionel Di Marco
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_hippotrack_mod_form extends moodleform_mod
{
    /**
     * Defines forms elements
     */
    public function definition()
    {
        global $CFG, $DB, $PAGE, $USER, $COURSE;

        $mform = &$this->_form;

        // 📌 Titre principal
        $mform->addElement('header', 'general', "Paramètres de l’activité");

        // 📌 Nom de l'instance du plugin
        $mform->addElement('text', 'name', 'Nom de l’activité', array('size' => '40'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', 'Veuillez entrer un nom.', 'required', null, 'client');

        // 📌 Description de l'activité
        $this->standard_intro_elements('Description');

        // 📌 (Optionnel) Ajouter un champ pour un paramètre futur (ex: mode d’affichage)
        $mform->addElement('advcheckbox', 'show_statistics', 'Afficher les statistiques aux étudiants', null, array('group' => 1));
        $mform->setDefault('show_statistics', 1);

        // 📌 Ajout des éléments standards communs à toutes les activités
        $this->standard_coursemodule_elements();

        // 📌 Boutons d'action (valider / annuler)
        $this->add_action_buttons();
    }
}
