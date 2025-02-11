<?php
require(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT);
$sessionid = required_param('sessionid', PARAM_INT);
$userid = $USER->id;

$cm = get_coursemodule_from_id('hippotrack', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$instance = $DB->get_record('hippotrack', array('id' => $cm->instance), '*', MUST_EXIST);
$context = context_module::instance($cm->id);

require_login($course, true, $cm);
require_capability('mod/hippotrack:attempt', $context);

$PAGE->set_cm($cm);
$PAGE->set_context($context);
$PAGE->set_url('/mod/hippotrack/session_details.php', array('id' => $id, 'sessionid' => $sessionid));
$PAGE->set_title("Détails de la session");
$PAGE->set_heading("Détails de la session");

echo $OUTPUT->header();

// 📌 Récupérer les infos de la session
$session = $DB->get_record('hippotrack_training_sessions', array('id' => $sessionid, 'userid' => $userid), '*', MUST_EXIST);
$date = userdate($session->timecreated, '%d/%m/%Y %H:%M');
$difficulty = ($session->difficulty === 'easy') ? 'Facile' : 'Difficile';
$score = "{$session->correct_answers} / {$session->questions_answered}";

echo html_writer::tag('h2', "Détails de votre session du $date");
echo html_writer::tag('p', "<strong>Difficulté :</strong> $difficulty");
echo html_writer::tag('p', "<strong>Score :</strong> $score");

// 📌 Récupérer toutes les réponses triées par date de réponse
$attempts = $DB->get_records('hippotrack_attempts', array('sessionid' => $sessionid), 'timeanswered ASC');

if (!$attempts) {
    echo html_writer::tag('p', "Aucune tentative enregistrée pour cette session.", array('class' => 'alert alert-warning'));
} else {
    $current_time = null;
    $exercice_num = 0;
    $first_attempt = true;

    foreach ($attempts as $attempt) {
        // 📌 Détecter un nouvel exercice par date de validation (`timeanswered`)
        if ($current_time !== $attempt->timeanswered) {
            if (!$first_attempt) {
                // ✅ Ferme le tableau du précédent exercice
                echo html_writer::end_tag('tbody');
                echo html_writer::end_tag('table');
                echo html_writer::end_div();
            }
            $first_attempt = false;

            $current_time = $attempt->timeanswered;
            $exercice_num++;

            // 📌 Début d’un nouvel exercice
            echo html_writer::start_div('exercise-box', array('style' => 'border:2px solid #ccc; padding:15px; margin-bottom:25px; border-radius:10px; background-color:#f9f9f9;'));
            echo html_writer::tag('h3', "Exercice $exercice_num", array('style' => 'text-decoration:underline; color:#333;'));

            $formatted_date = userdate($current_time, '%d/%m/%Y %H:%M:%S');
            echo html_writer::tag('p', "<strong>Validé le :</strong> <span style='color:#555;'>$formatted_date</span>");

            // 📌 Début du tableau
            echo html_writer::start_tag('table', array('class' => 'table table-bordered', 'style' => 'width:100%; text-align:center;'));
            echo html_writer::start_tag('thead');
            echo html_writer::start_tag('tr');
            echo html_writer::tag('th', 'Question', array('style' => 'background-color:#e9ecef;'));
            echo html_writer::tag('th', 'Votre réponse', array('style' => 'background-color:#e9ecef;'));
            echo html_writer::tag('th', 'Correction', array('style' => 'background-color:#e9ecef;'));
            echo html_writer::tag('th', 'Résultat', array('style' => 'background-color:#e9ecef;'));
            echo html_writer::end_tag('tr');
            echo html_writer::end_tag('thead');
            echo html_writer::start_tag('tbody');
        }

        // 📌 Récupération des informations de l'exercice
        $dataset = $DB->get_record('hippotrack_datasets', array('id' => $attempt->datasetid), '*', MUST_EXIST);
        $question_label = ucfirst(str_replace('_', ' ', $attempt->input_type));
        $student_response = $attempt->student_response;
        $correct_answer = $dataset->{$attempt->input_type};
        $is_correct = ($attempt->is_correct) ? '<span style="color:green;">✅</span>' : '<span style="color:red;">❌</span>';

        if ($attempt->input_type === 'partogramme' || $attempt->input_type === 'shema_simplifie') {
            $correct_answer = "Inclinaison: {$dataset->inclinaison}, Rotation: {$dataset->rotation}";
        }

        // 📌 Affichage de la réponse dans le tableau
        echo html_writer::start_tag('tr');
        echo html_writer::tag('td', "<strong>$question_label</strong>");
        echo html_writer::tag('td', "<span style='color:#333;'>$student_response</span>");
        echo html_writer::tag('td', "<span style='color:blue;'>$correct_answer</span>");
        echo html_writer::tag('td', $is_correct);
        echo html_writer::end_tag('tr');
    }

    // ✅ Fermer le dernier tableau
    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');
    echo html_writer::end_div();
}

// 🔙 Bouton retour vers `history.php`
$back_url = new moodle_url('/mod/hippotrack/history.php', array('id' => $id));
echo $OUTPUT->single_button($back_url, 'Retour', 'get');

echo $OUTPUT->footer();
