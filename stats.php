<?php
require(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT);
$userid = $USER->id;

$cm = get_coursemodule_from_id('hippotrack', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$instance = $DB->get_record('hippotrack', array('id' => $cm->instance), '*', MUST_EXIST);
$context = context_module::instance($cm->id);

require_login($course, true, $cm);
require_capability('mod/hippotrack:viewstats', $context);

$PAGE->set_cm($cm);
$PAGE->set_context($context);
$PAGE->set_url('/mod/hippotrack/stats.php', array('id' => $id));
$PAGE->set_title("Statistiques des exercices");
$PAGE->set_heading("Statistiques des exercices");

echo $OUTPUT->header();

// 📌 Récupérer les statistiques des tentatives
$stats = $DB->get_records_sql("
    SELECT a.input_type, COUNT(a.id) as attempts, 
           SUM(a.is_correct) as correct, 
           ROUND(AVG(s.timespent), 2) as avg_time
    FROM {hippotrack_attempts} a
    JOIN {hippotrack_training_sessions} s ON a.sessionid = s.id
    WHERE s.instanceid = ?
    GROUP BY a.input_type
    ORDER BY attempts DESC", array($instance->id));

echo html_writer::tag('h2', "Difficulté des exercices par type d'input");

// 📌 Affichage des stats sous forme de tableau
echo html_writer::start_tag('table', array('class' => 'table table-bordered', 'style' => 'width:100%; text-align:center;'));
echo html_writer::start_tag('thead');
echo html_writer::start_tag('tr');
echo html_writer::tag('th', 'Type d\'Input');
echo html_writer::tag('th', 'Nombre d\'essais');
echo html_writer::tag('th', 'Réponses correctes');
echo html_writer::tag('th', 'Taux de réussite (%)');
echo html_writer::tag('th', 'Temps moyen (s)');
echo html_writer::end_tag('tr');
echo html_writer::end_tag('thead');
echo html_writer::start_tag('tbody');

foreach ($stats as $stat) {
    $success_rate = ($stat->attempts > 0) ? round(($stat->correct / $stat->attempts) * 100, 2) : 0;
    echo html_writer::start_tag('tr');
    echo html_writer::tag('td', ucfirst(str_replace('_', ' ', $stat->input_type)));
    echo html_writer::tag('td', $stat->attempts);
    echo html_writer::tag('td', $stat->correct);
    echo html_writer::tag('td', "$success_rate%");
    echo html_writer::tag('td', $stat->avg_time);
    echo html_writer::end_tag('tr');
}

echo html_writer::end_tag('tbody');
echo html_writer::end_tag('table');

// 🔙 Bouton retour vers `view.php`
$back_url = new moodle_url('/mod/hippotrack/view.php', array('id' => $id));
echo $OUTPUT->single_button($back_url, 'Retour', 'get');

echo $OUTPUT->footer();
