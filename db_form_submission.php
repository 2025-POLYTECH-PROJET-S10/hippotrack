<?php
require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/form/db_form.php');

$cmid = required_param('cmid', PARAM_INT);
$userid = $USER->id;

// 📌 Retrieve Course Module and Context
$cm = get_coursemodule_from_id('hippotrack', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$context = context_module::instance($cm->id);

// 📌 Access Control
require_login($course, true, $cm);
require_capability('mod/hippotrack:manage', $context);

// 📌 Page Configuration
$PAGE->set_cm($cm);
$PAGE->set_context($context);
$PAGE->set_url('/mod/hippotrack/db_form_submission.php', array('id' => $cmid));
$PAGE->set_title("Ajout/Modification des données");
$PAGE->set_heading("Ajout/Modification des données");

$editing = optional_param('edit',0, PARAM_INT); // Edit an entry
$showform = optional_param('addnew', 0, PARAM_BOOL); // Add a new entry

global $DB;

/**
 * Save the form data to the database.
* This should be called after the form is submitted and validated.
*/
function _save_submission($data) {
    global $DB, $context;

    if ($data) {
        $record = new stdClass();
        $record->name = $data->name;
        $record->sigle = $data->sigle;
        $record->rotation = $data->rotation;
        $record->inclinaison = $data->inclinaison;

        // Save filepicker data
        $fs = get_file_storage();
        $draftitemid_vue_anterieure = file_get_submitted_draft_itemid('vue_anterieure');
        $draftitemid_vue_laterale = file_get_submitted_draft_itemid('vue_laterale');

        if ($data->id) {
            // Update existing record
            $record->id = $data->id;
            $DB->update_record('hippotrack_datasets', $record);

            // Save files to the appropriate file areas
            file_save_draft_area_files($draftitemid_vue_anterieure, $context->id, 'mod_hippotrack', 'vue_anterieure', $record->id);
            file_save_draft_area_files($draftitemid_vue_laterale, $context->id, 'mod_hippotrack', 'vue_laterale', $record->id);
        } else {
            // Insert a new record
            $record->id = $DB->insert_record('hippotrack_datasets', $record);

            // Save files to the appropriate file areas
            file_save_draft_area_files($draftitemid_vue_anterieure, $context->id, 'mod_hippotrack', 'vue_anterieure', $record->id);
            file_save_draft_area_files($draftitemid_vue_laterale, $context->id, 'mod_hippotrack', 'vue_laterale', $record->id);
        }
    }
}


// 📌 Create Form
if ($editing){
    $nexturl = new moodle_url('/mod/hippotrack/db_form_submission.php', array('cmid' => $cmid, 'edit' => $editing));
} else {
    $nexturl = new moodle_url('/mod/hippotrack/db_form_submission.php', array('cmid' => $cmid, 'addnew' => $showform));
}

$mform = new manage_datasets_form($action= $nexturl);


if ($mform->is_cancelled()) {
    // Cancel operation
    redirect(new moodle_url('/mod/hippotrack/manage_datasets.php', array('cmid' => $cmid)), "Opération annulée.");
    // throw new moodle_exception("Operation cancelled");
} else if ($data = $mform->get_data()) {
    try {
        $draftitemid_vue_anterieure = file_get_submitted_draft_itemid('vue_anterieure');
        $draftitemid_vue_laterale = file_get_submitted_draft_itemid('vue_laterale');
        
        if ($editing) {
            // Update existing entry
            $dataset = $DB->get_record('hippotrack_datasets', array('id' => $editing), '*', MUST_EXIST);
            // Set existing data to form
            $mform->set_data($dataset);
            $dataset->name = $data->name;
            $dataset->sigle = $data->sigle;
            $dataset->rotation = $data->rotation;
            $dataset->inclinaison = $data->inclinaison;
            $record->vue_anterieure = $data->vue_anterieure;
            $record->vue_laterale = $data->vue_laterale;
            $DB->update_record('hippotrack_datasets', $dataset);
        } else {
            // Insert new entry
            $record = new stdClass();
            $record->name = $data->name;
            $record->sigle = $data->sigle;
            $record->rotation = $data->rotation;
            $record->inclinaison = $data->inclinaison;
            $record->vue_anterieure = $data->vue_anterieure;
            $record->vue_laterale = $data->vue_laterale;
            $newid = $DB->insert_record('hippotrack_datasets', $record);
        }
        
        // Redirect with success message
        redirect(
            new moodle_url('/mod/hippotrack/manage_datasets.php', array('cmid' => $cmid)),
            "Entrée enregistrée avec succès.",
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    } catch (Exception $e) {
        redirect(
            new moodle_url('/mod/hippotrack/manage_datasets.php', array('cmid' => $cmid)),
            "Une erreur est survenue lors de l'enregistrement de l'entrée.",
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }
} else {

    echo $OUTPUT->header();

    // Display form for adding/editing
    echo html_writer::tag('h2', $editing ? "Modifier l'entrée" : "Ajouter une nouvelle entrée");

    if ($editing){
        // Load existing entry
        $dataset = $DB->get_record('hippotrack_datasets', array('id' => $editing), '*', MUST_EXIST);
        $mform->set_data($dataset);
        $mform->display();
    } else {
        // Display form for adding new entry
        $mform->display();
    }

    echo $OUTPUT->footer();
}

