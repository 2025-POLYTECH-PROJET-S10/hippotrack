<?php
require(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT);
$userid = $USER->id;

$cm = get_coursemodule_from_id('hippotrack', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$instance = $DB->get_record('hippotrack', array('id' => $cm->instance), '*', MUST_EXIST);
$context = context_module::instance($cm->id);

require_login($course, true, $cm);
require_capability('mod/hippotrack:manage', $context);

$PAGE->set_cm($cm);
$PAGE->set_context($context);
$PAGE->set_url('/mod/hippotrack/manage_datasets.php', array('id' => $id));
$PAGE->set_title("Gestion des ensembles de données");
$PAGE->set_heading("Gestion des ensembles de données");

echo $OUTPUT->header();

$delete_id = optional_param('delete', 0, PARAM_INT);
if ($delete_id && confirm_sesskey()) {
    if ($DB->record_exists('hippotrack_datasets', array('id' => $delete_id))) {
        $DB->delete_records('hippotrack_datasets', array('id' => $delete_id));
        redirect(new moodle_url('/mod/hippotrack/manage_datasets.php', array('id' => $id)), "Ensemble de données supprimé avec succès.", null, \core\output\notification::NOTIFY_SUCCESS);
    }
}


// 📌 Vérifier si une soumission a été faite (Ajout ou Modification)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dataset_id = optional_param('dataset_id', 0, PARAM_INT);
    $name = required_param('name', PARAM_TEXT);
    $sigle = required_param('sigle', PARAM_TEXT);
    $rotation = required_param('rotation', PARAM_INT);
    $inclinaison = required_param('inclinaison', PARAM_INT);
    $vue_anterieure = required_param('vue_anterieure', PARAM_TEXT);
    $vue_laterale = required_param('vue_laterale', PARAM_TEXT);

    if ($dataset_id) {
        // 📌 Mise à jour d'un dataset existant
        $dataset = $DB->get_record('hippotrack_datasets', array('id' => $dataset_id), '*', MUST_EXIST);
        $dataset->name = $name;
        $dataset->sigle = $sigle;
        $dataset->rotation = $rotation;
        $dataset->inclinaison = $inclinaison;
        $dataset->vue_anterieure = $vue_anterieure;
        $dataset->vue_laterale = $vue_laterale;
        
        $DB->update_record('hippotrack_datasets', $dataset);
        redirect(new moodle_url('/mod/hippotrack/manage_datasets.php', array('id' => $id)), "Ensemble de données mis à jour avec succès.", null, \core\output\notification::NOTIFY_SUCCESS);
    } else {
        // 📌 Ajout d'un nouvel ensemble de données
        $new_dataset = new stdClass();
        $new_dataset->name = $name;
        $new_dataset->sigle = $sigle;
        $new_dataset->rotation = $rotation;
        $new_dataset->inclinaison = $inclinaison;
        $new_dataset->vue_anterieure = $vue_anterieure;
        $new_dataset->vue_laterale = $vue_laterale;

        $DB->insert_record('hippotrack_datasets', $new_dataset);
        redirect(new moodle_url('/mod/hippotrack/manage_datasets.php', array('id' => $id)), "Nouvel ensemble de données ajouté avec succès.", null, \core\output\notification::NOTIFY_SUCCESS);
    }
}

// 📌 Récupérer les datasets existants
$datasets = $DB->get_records('hippotrack_datasets', array(), 'id ASC');

echo html_writer::tag('h2', "Liste des ensembles de données");

// 📌 Affichage des datasets existants
if (!$datasets) {
    echo html_writer::tag('p', "Aucun ensemble de données enregistré.", array('class' => 'alert alert-warning'));
} else {
    echo html_writer::start_tag('table', array('class' => 'table table-striped'));
    echo html_writer::start_tag('thead');
    echo html_writer::start_tag('tr');
    echo html_writer::tag('th', 'Nom');
    echo html_writer::tag('th', 'Sigle');
    echo html_writer::tag('th', 'Rotation');
    echo html_writer::tag('th', 'Inclinaison');
    echo html_writer::tag('th', 'Vue Antérieure');
    echo html_writer::tag('th', 'Vue Latérale');
    echo html_writer::tag('th', 'Actions');
    echo html_writer::end_tag('tr');
    echo html_writer::end_tag('thead');

    echo html_writer::start_tag('tbody');

    foreach ($datasets as $dataset) {
        echo html_writer::start_tag('tr');
        echo html_writer::tag('td', $dataset->name);
        echo html_writer::tag('td', $dataset->sigle);
        echo html_writer::tag('td', $dataset->rotation);
        echo html_writer::tag('td', $dataset->inclinaison);

        $vue_ante = $dataset->vue_anterieure ? "<img src='pix/{$dataset->vue_anterieure}' width='50'>" : "Aucune";
        $vue_lat = $dataset->vue_laterale ? "<img src='pix/{$dataset->vue_laterale}' width='50'>" : "Aucune";

        echo html_writer::tag('td', $vue_ante);
        echo html_writer::tag('td', $vue_lat);

        $edit_url = new moodle_url('/mod/hippotrack/manage_datasets.php', array('id' => $id, 'edit' => $dataset->id));

        $delete_url = new moodle_url('/mod/hippotrack/manage_datasets.php', array('id' => $id, 'delete' => $dataset->id, 'sesskey' => sesskey()));

echo html_writer::tag('td', 
    $OUTPUT->single_button($edit_url, 'Modifier', 'get') . 
    $OUTPUT->single_button($delete_url, 'Supprimer', 'get')
);


       // echo html_writer::tag('td', $OUTPUT->single_button($edit_url, 'Modifier', 'get'));

        echo html_writer::end_tag('tr');
    }

    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');
}

// 📌 Formulaire d'ajout/modification
$editing = optional_param('edit', 0, PARAM_INT);
$dataset_to_edit = $editing ? $DB->get_record('hippotrack_datasets', array('id' => $editing), '*', MUST_EXIST) : null;

$form_title = $editing ? "Modifier un ensemble de données" : "Ajouter un nouvel ensemble de données";
echo html_writer::tag('h2', $form_title);

echo html_writer::start_tag('form', array('method' => 'post', 'action' => 'manage_datasets.php?id=' . $id));
if ($editing) {
    echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'dataset_id', 'value' => $dataset_to_edit->id));
}

$fields = [
    'name' => 'Nom',
    'sigle' => 'Sigle',
    'rotation' => 'Rotation',
    'inclinaison' => 'Inclinaison',
    'vue_anterieure' => 'Vue Antérieure (Nom de l\'image)',
    'vue_laterale' => 'Vue Latérale (Nom de l\'image)'
];

foreach ($fields as $field => $label) {
    $value = $dataset_to_edit ? $dataset_to_edit->$field : '';

    echo html_writer::start_tag('p');
    echo html_writer::tag('label', $label);
    echo html_writer::empty_tag('input', array('type' => 'text', 'name' => $field, 'value' => $value, 'class' => 'form-control'));

    if (($field === 'vue_anterieure' || $field === 'vue_laterale') && !empty($value)) {
        echo "<br><img src='pix/$value' width='50' style='margin-top:5px;'>";
    }

    echo html_writer::end_tag('p');
}

$btn_label = $editing ? 'Modifier' : 'Ajouter';
echo html_writer::empty_tag('input', array('type' => 'submit', 'value' => $btn_label, 'class' => 'btn btn-primary'));

echo html_writer::end_tag('form');

$back_url = new moodle_url('/mod/hippotrack/view.php', array('id' => $id));
echo $OUTPUT->single_button($back_url, 'Retour', 'get');

echo $OUTPUT->footer();
