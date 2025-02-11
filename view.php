<?php 
require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

$id = required_param('id', PARAM_INT); // Course module ID

$cm = get_coursemodule_from_id('hippotrack', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$moduleinstance = $DB->get_record('hippotrack', array('id' => $cm->instance), '*', MUST_EXIST);
$context = context_module::instance($cm->id);

require_login($course, true, $cm);

$is_teacher = has_capability('mod/hippotrack:manage', $context);
$is_student = has_capability('mod/hippotrack:attempt', $context);

$PAGE->set_cm($cm);
$PAGE->set_context($context);
$PAGE->set_url('/mod/hippotrack/view.php', array('id' => $id));
$PAGE->set_title(format_string($moduleinstance->name));
$PAGE->set_heading(format_string($course->fullname));

echo $OUTPUT->header();
echo html_writer::tag('h2', format_string($moduleinstance->name), array('class' => 'hippotrack-title'));

// 📌 Fonction pour vérifier si une page existe
function page_exists($page) {
    global $CFG;
    return file_exists($CFG->dirroot . "/mod/hippotrack/$page");
}

// 📌 Interface enseignant
if ($is_teacher) {
    echo html_writer::start_div('hippotrack-teacher-options');

    // 📊 Voir les statistiques
    $stats_url = new moodle_url('/mod/hippotrack/stats.php', array('id' => $id));
    if (page_exists('stats.php')) {
        echo $OUTPUT->single_button($stats_url, '📊 Voir les statistiques', 'get');
    } else {
        echo html_writer::tag('button', '📊 Voir les statistiques (Bientôt dispo)', array('disabled' => 'disabled', 'class' => 'btn btn-secondary'));
    }

    // 📂 Gérer les ensembles
    $manage_url = new moodle_url('/mod/hippotrack/manage_datasets.php', array('id' => $id));
    if (page_exists('manage_datasets.php')) {
        echo $OUTPUT->single_button($manage_url, '➕ Gérer les ensembles', 'get');
    } else {
        echo html_writer::tag('button', '➕ Gérer les ensembles (Bientôt dispo)', array('disabled' => 'disabled', 'class' => 'btn btn-secondary'));
    }

    echo html_writer::end_div();
}

// 📌 Interface étudiant
if ($is_student) {
    echo html_writer::start_div('hippotrack-student-options');

    // 🔍 Vérification des essais
    $existing_attempts = $DB->count_records('hippotrack_training_sessions', array('userid' => $USER->id, 'instanceid' => $moduleinstance->id));
    $history_url = new moodle_url('/mod/hippotrack/history.php', array('id' => $id));

    if (page_exists('history.php')) {
        echo $OUTPUT->single_button($history_url, '📜 Voir les anciennes tentatives', 'get');
    } else {
        echo html_writer::tag('button', '📜 Voir les anciennes tentatives (Bientôt dispo)', array('disabled' => 'disabled', 'class' => 'btn btn-secondary'));
    }

    // ▶️ Lancer une session d'exercice
    $attempt_url = new moodle_url('/mod/hippotrack/attempt.php', array('id' => $id));
    if (page_exists('attempt.php')) {
        echo $OUTPUT->single_button($attempt_url, '🚀 Lancer une session d\'exercice', 'get');
    } else {
        echo html_writer::tag('button', '🚀 Lancer une session d\'exercice (Bientôt dispo)', array('disabled' => 'disabled', 'class' => 'btn btn-secondary'));
    }

    echo html_writer::end_div();
}

echo $OUTPUT->footer();
