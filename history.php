<?php
require(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT);
$userid = $USER->id;

$cm = get_coursemodule_from_id('hippotrack', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$instance = $DB->get_record('hippotrack', array('id' => $cm->instance), '*', MUST_EXIST);
$context = context_module::instance($cm->id);

require_login($course, true, $cm);
require_capability('mod/hippotrack:attempt', $context);

$PAGE->set_cm($cm);
$PAGE->set_context($context);
$PAGE->set_url('/mod/hippotrack/history.php', array('id' => $id));
$PAGE->set_title("Historique des sessions");
$PAGE->set_heading("Historique des sessions");

echo $OUTPUT->header();

echo html_writer::tag('h2', "Vos anciennes tentatives");

// 📌 Récupérer les sessions de l'étudiant classées par `sessionid`
$sessions = $DB->get_records('hippotrack_training_sessions', array('userid' => $userid, 'instanceid' => $instance->id), 'id DESC');

if (!$sessions) {
    echo html_writer::tag('p', "Vous n'avez encore aucune session enregistrée.", array('class' => 'alert alert-info'));
} else {
    foreach ($sessions as $session) {
        $date = userdate($session->timecreated, '%d/%m/%Y %H:%M');
        $difficulty = ($session->difficulty === 'easy') ? 'Facile' : 'Difficile';
        $score = "{$session->correct_answers} / {$session->questions_answered}";

        // 📌 Affichage d'un bloc distinct pour chaque session
        echo html_writer::start_div('session-box', array('style' => 'border:2px solid #ccc; padding:15px; margin-bottom:20px; border-radius:10px; background-color:#f9f9f9;'));
        echo html_writer::tag('h3', "Session du $date", array('style' => 'color:#333; text-decoration:underline;'));
        echo html_writer::tag('p', "<strong>Difficulté :</strong> $difficulty");
        echo html_writer::tag('p', "<strong>Nombre de questions répondues :</strong> {$session->questions_answered}");
        echo html_writer::tag('p', "<strong>Score :</strong> $score");

        // 🔍 Bouton "Voir Détails"
        $details_url = new moodle_url('/mod/hippotrack/session_details.php', array('id' => $id, 'sessionid' => $session->id));
        echo $OUTPUT->single_button($details_url, 'Voir Détails', 'get');

        echo html_writer::end_div();
    }
}

// 🔙 Bouton de retour vers `view.php`
$back_url = new moodle_url('/mod/hippotrack/view.php', array('id' => $id));
echo $OUTPUT->single_button($back_url, 'Retour', 'get');

echo $OUTPUT->footer();
