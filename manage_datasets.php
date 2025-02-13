<?php
require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/form/db_form.php');

// 📌 Parameter Validation
$cmid = required_param('cmid', PARAM_INT);
$savedata = optional_param('save_data',0, PARAM_INT);
debugging('save_data' . $savedata, DEBUG_DEVELOPER);
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
$PAGE->set_url('/mod/hippotrack/manage_datasets.php', array('id' => $cmid));
$PAGE->set_title("Gestion des ensembles de données");
$PAGE->set_heading("Gestion des ensembles de données");

// 📌 Action Parameters
$editing = optional_param('edit', 0, PARAM_INT); // Edit an entry
$deleting = optional_param('delete', 0, PARAM_INT); // Delete an entry
$showform = optional_param('addnew', 0, PARAM_BOOL); // Add a new entry

// 📌 Handle Deletion
if ($deleting && confirm_sesskey()) {
    try {
        $DB->delete_records('hippotrack_datasets', array('id' => $deleting));
        redirect(
            new moodle_url('/mod/hippotrack/manage_datasets.php', array('cmid' => $cmid)),
            "Entrée supprimée avec succès.",
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    } catch (Exception $e) {
        redirect(
            new moodle_url('/mod/hippotrack/manage_datasets.php', array('cmid' => $cmid)),
            "Une erreur est survenue lors de la suppression de l'entrée.",
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }
}

/**
 * Save the form data to the database.
* This should be called after the form is submitted and validated.
*/
function _save_submission($data) {
    global $DB, $context;

    if ($data) {
        $record = new stdClass();
        $record->name = $data["name"];
        $record->sigle = $data["sigle"];
        $record->rotation = $data["rotation"];
        $record->inclinaison = $data["inclinaison"];

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

if ($savedata) {
    // get the sent data from parameters:
    $data = array(
        'id'=> required_param('id', PARAM_INT),
        'name' => required_param('name', PARAM_TEXT),
        'sigle' => required_param('sigle', PARAM_TEXT),
        'sigle' => required_param('rotation', PARAM_INT),
        'inclinaison' => required_param('inclinaison', PARAM_INT),
        'vue_anterieure' => required_param('vue_anterieure', PARAM_TEXT),
        'vue_laterale' => required_param('vue_laterale', PARAM_TEXT),
    );

    _save_submission($data);
}

// 📌 Handle Form Submission
$mform = new manage_datasets_form($action= new moodle_url('/mod/hippotrack/manage_datasets.php', array('cmid' => $cmid, 'save_data' => 1)));
if ($showform || $editing) {
    if ($mform->is_cancelled()) {
        // Cancel operation
        redirect(new moodle_url('/mod/hippotrack/manage_datasets.php', array('cmid' => $cmid)));
    } else if ($data = $mform->get_data()) {
        try {
            $draftitemid_vue_anterieure = file_get_submitted_draft_itemid('vue_anterieure');
            $draftitemid_vue_laterale = file_get_submitted_draft_itemid('vue_laterale');

            var_dump($data);
            die();

            if ($editing) {
                // Update existing entry
                $dataset = $DB->get_record('hippotrack_datasets', array('id' => $editing), '*', MUST_EXIST);
                // Set existing data to form
                $mform->set_data($dataset);
                $dataset->name = $data->name;
                $dataset->sigle = $data->sigle;
                $dataset->rotation = $data->rotation;
                $dataset->inclinaison = $data->inclinaison;
                // $record->vue_anterieure = $data->vue_anterieure;
                // $record->vue_laterale = $data->vue_laterale;
                $DB->update_record('hippotrack_datasets', $dataset);
                error_log("Update record called for ID: " . $dataset->id);
            } else {
                // Insert new entry
                $record = new stdClass();
                $record->name = $data->name;
                $record->sigle = $data->sigle;
                $record->rotation = $data->rotation;
                $record->inclinaison = $data->inclinaison;
                // $record->vue_anterieure = $data->vue_anterieure;
                // $record->vue_laterale = $data->vue_laterale;
                $newid = $DB->insert_record('hippotrack_datasets', $record);
                error_log("Insert record called for new ID: " . $newid);
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
    }
}


// 📌 Helper Function to Render Datasets Table
function render_datasets_table($datasets, $context, $cmid, $OUTPUT) {
    if (empty($datasets)) {
        return html_writer::tag('p', "Aucune donnée disponible.", array('class' => 'alert alert-warning'));
    }

    $table_html = html_writer::start_tag('table', array('class' => 'table table-striped'));
    $table_html .= html_writer::start_tag('thead') . html_writer::start_tag('tr');
    $table_html .= html_writer::tag('th', 'Nom') . html_writer::tag('th', 'Sigle') . html_writer::tag('th', 'Rotation') . html_writer::tag('th', 'Inclinaison') . html_writer::tag('th', 'Vue Antérieure') . html_writer::tag('th', 'Vue Latérale') . html_writer::tag('th', 'Actions');
    $table_html .= html_writer::end_tag('tr') . html_writer::end_tag('thead');

    $table_html .= html_writer::start_tag('tbody');
    foreach ($datasets as $dataset) {
        $fs = get_file_storage();
        $files_vue_anterieure = $fs->get_area_files($context->id, 'mod_hippotrack', 'vue_anterieure', $dataset->id);
        $files_vue_laterale = $fs->get_area_files($context->id, 'mod_hippotrack', 'vue_laterale', $dataset->id);

        $vue_anterieure_url = !empty($files_vue_anterieure) ? moodle_url::make_pluginfile_url($context->id, 'mod_hippotrack', 'vue_anterieure', $dataset->id, '/', 'vue_anterieure.jpg') : '#';
        $vue_laterale_url = !empty($files_vue_laterale) ? moodle_url::make_pluginfile_url($context->id, 'mod_hippotrack', 'vue_laterale', $dataset->id, '/', 'vue_laterale.jpg') : '#';

        $table_html .= html_writer::start_tag('tr');
        $table_html .= html_writer::tag('td', $dataset->name);
        $table_html .= html_writer::tag('td', $dataset->sigle);
        $table_html .= html_writer::tag('td', $dataset->rotation);
        $table_html .= html_writer::tag('td', $dataset->inclinaison);
        $table_html .= html_writer::tag('td', html_writer::empty_tag('img', array('src' => $vue_anterieure_url, 'width' => 50)));
        $table_html .= html_writer::tag('td', html_writer::empty_tag('img', array('src' => $vue_laterale_url, 'width' => 50)));

        // Actions (Edit and Delete)
        $edit_url = new moodle_url('/mod/hippotrack/manage_datasets.php', array('cmid' => $cmid, 'edit' => $dataset->id));
        $delete_url = new moodle_url('/mod/hippotrack/manage_datasets.php', array('cmid' => $cmid, 'delete' => $dataset->id, 'sesskey' => sesskey()));
        $table_html .= html_writer::tag('td',
            $OUTPUT->single_button($edit_url, 'Modifier', 'get') .
            $OUTPUT->single_button($delete_url, 'Supprimer', 'post')
        );

        $table_html .= html_writer::end_tag('tr');
    }
    $table_html .= html_writer::end_tag('tbody') . html_writer::end_tag('table');

    return $table_html;
}

// 📌 Main Output
echo $OUTPUT->header();

if (!$showform && !$editing) {
    // Display datasets table
    echo html_writer::tag('h2', "Liste des ensembles de données");
    $datasets = $DB->get_records('hippotrack_datasets', array(), 'id ASC');
    echo render_datasets_table($datasets, $context, $cmid, $OUTPUT);

    // Add new entry button
    $addnew_url = new moodle_url('/mod/hippotrack/manage_datasets.php', array('cmid' => $cmid, 'addnew' => 1));
    echo $OUTPUT->single_button($addnew_url, "Ajouter une nouvelle entrée", "get");
} else {
    // Display form for adding/editing
    echo html_writer::tag('h2', $editing ? "Modifier l'entrée" : "Ajouter une nouvelle entrée");
    $mform->display();
}

echo $OUTPUT->footer();