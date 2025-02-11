<?php
require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/sessionlib.php');

$id = required_param('id', PARAM_INT);
$difficulty = optional_param('difficulty', '', PARAM_ALPHA);
$new_question = optional_param('new_question', 0, PARAM_INT);
$submitted = optional_param('submitted', 0, PARAM_INT);
$userid = $USER->id;

$cm = get_coursemodule_from_id('hippotrack', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$instance = $DB->get_record('hippotrack', array('id' => $cm->instance), '*', MUST_EXIST);
$context = context_module::instance($cm->id);

require_login($course, true, $cm);
require_capability('mod/hippotrack:attempt', $context);

$PAGE->set_cm($cm);
$PAGE->set_context($context);
$PAGE->set_url('/mod/hippotrack/attempt.php', array('id' => $id));
$PAGE->set_title("Session d'entraînement");
$PAGE->set_heading("Session d'entraînement");

echo $OUTPUT->header();

// 📌 Étape 1 : Sélection de la difficulté
if (empty($difficulty)) {
    echo html_writer::tag('h3', "Choisissez votre niveau de difficulté");

    $easy_url = new moodle_url('/mod/hippotrack/attempt.php', array('id' => $id, 'difficulty' => 'easy'));
    $hard_url = new moodle_url('/mod/hippotrack/attempt.php', array('id' => $id, 'difficulty' => 'hard'));

    echo html_writer::start_div('difficulty-selection');
    echo $OUTPUT->single_button($easy_url, 'Facile', 'get');
    echo $OUTPUT->single_button($hard_url, 'Difficile', 'get');
    echo html_writer::end_div();

    echo $OUTPUT->footer();
    exit;
}

// 📌 Vérifier s'il existe déjà une session en cours pour l'utilisateur
$session = $DB->get_record('hippotrack_training_sessions', array(
    'userid' => $userid,
    'instanceid' => $instance->id,
    'difficulty' => $difficulty,
    'finished' => 0
));

// 📌 🔥 CORRECTION : Définir toujours $possible_inputs avant toute logique
$possible_inputs = ($difficulty === 'easy') ? 
    ['name', 'sigle', 'partogramme', 'shema_simplifie', 'vue_anterieure', 'vue_laterale'] : 
    ['name', 'sigle', 'partogramme', 'shema_simplifie'];

// 📌 S'assurer qu'on a bien une question dès la première session
if (!$session || $new_question == 1) {
    $random_entry = $DB->get_record_sql("SELECT * FROM {hippotrack_datasets} ORDER BY RAND() LIMIT 1");
    $possible_inputs = ($difficulty === 'easy') ? 
        ['name', 'sigle', 'partogramme', 'shema_simplifie', 'vue_anterieure', 'vue_laterale'] : 
        ['name', 'sigle', 'partogramme', 'shema_simplifie'];

    $random_input = $possible_inputs[array_rand($possible_inputs)];
    

    if (!$session) {
        // 📌 Nouvelle session -> On génère une question dès le départ
        $session = new stdClass();
        $session->userid = $userid;
        $session->instanceid = $instance->id;
        $session->difficulty = $difficulty;
        $session->questionid = $random_entry->id;
        $session->input_type = $random_input;
        $session->timecreated = time();
        $session->finished = 0;

        $session->id = $DB->insert_record('hippotrack_training_sessions', $session);
    } else {
        // 📌 Mise à jour pour une nouvelle question
        $session->questionid = $random_entry->id;
        $session->input_type = $random_input;
        $session->timecreated = time();
        $DB->update_record('hippotrack_training_sessions', $session);
    }
}

// 📌 Récupérer la question de la session actuelle
$random_entry = $DB->get_record('hippotrack_datasets', array('id' => $session->questionid));
$random_input = $session->input_type;
$random_input_label = ucfirst(str_replace('_', ' ', $random_input));
$pre_filled_value = $random_entry->$random_input;

echo html_writer::tag('h3', "Trouvez les bonnes correspondances pour :");

// 📌 Correction après validation
if ($submitted) {
    echo html_writer::tag('h3', "Correction :");
    $is_correct = true;
    $feedback = "Bravo ! Toutes les réponses sont correctes.";

    foreach ($possible_inputs as $field) {
        if ($field === 'partogramme' || $field === 'shema_simplifie') {
            // 🔥 Correction spéciale pour partogramme et schéma simplifié (ils utilisent rotation + inclinaison)
            $student_inclinaison = required_param("inclinaison_$field", PARAM_RAW);
            $student_rotation = required_param("rotation_$field", PARAM_RAW);
            
            $correct_inclinaison = $random_entry->inclinaison;
            $correct_rotation = $random_entry->rotation;
        
            // Vérification des deux valeurs ensemble
            if ($student_inclinaison != $correct_inclinaison || $student_rotation != $correct_rotation) {
                $is_correct = false;
                $feedback = "Oops, certaines réponses sont incorrectes. Vérifiez et essayez encore !";
            }
        
            echo html_writer::tag('p', "<strong>$field :</strong> Votre inclinaison : $student_inclinaison | Rotation : $student_rotation <br> Réponse correcte : Inclinaison $correct_inclinaison | Rotation $correct_rotation");
        } else {
            // 🔥 Cas normal (name, sigle, vue_anterieure, vue_laterale)
            $student_answer = required_param($field, PARAM_RAW);
            $correct_answer = $random_entry->$field;
        
            if ($student_answer != $correct_answer) {
                $is_correct = false;
                $feedback = "Oops, certaines réponses sont incorrectes. Vérifiez et essayez encore !";
            }
        
            echo html_writer::tag('p', "<strong>$field :</strong> Votre réponse : $student_answer | Réponse correcte : $correct_answer");
        }
        

        
    }

    echo html_writer::tag('p', $feedback, array('class' => $is_correct ? 'correct' : 'incorrect'));

    // 📌 Enregistrer les réponses de l'étudiant dans la base de données
foreach ($possible_inputs as $field) {
    $attempt = new stdClass();
    $attempt->sessionid = $session->id;
    $attempt->datasetid = $random_entry->id;
    $attempt->input_type = $field;
    $attempt->timeanswered = time();

    if ($field === 'partogramme' || $field === 'shema_simplifie') {
        // 🔥 Cas spécial : Stocker la rotation et l'inclinaison pour partogramme et schéma
        $student_inclinaison = required_param("inclinaison_$field", PARAM_RAW);
        $student_rotation = required_param("rotation_$field", PARAM_RAW);
        $correct_inclinaison = $random_entry->inclinaison;
        $correct_rotation = $random_entry->rotation;

        $attempt->student_response = "Inclinaison: $student_inclinaison, Rotation: $student_rotation";
        $attempt->is_correct = ($student_inclinaison == $correct_inclinaison && $student_rotation == $correct_rotation) ? 1 : 0;
    } else {
        // 🔥 Cas normal (name, sigle, vue_anterieure, vue_laterale)
        $student_answer = required_param($field, PARAM_RAW);
        $correct_answer = $random_entry->$field;

        $attempt->student_response = $student_answer;
        $attempt->is_correct = ($student_answer == $correct_answer) ? 1 : 0;
    }

    // 📌 Insérer la tentative en base
    $DB->insert_record('hippotrack_attempts', $attempt);
}


    // 📌 Boutons "Nouvelle Question" et "Terminer"
    $new_question_url = new moodle_url('/mod/hippotrack/attempt.php', array('id' => $id, 'difficulty' => $difficulty, 'new_question' => 1));
    $finish_url = new moodle_url('/mod/hippotrack/view.php', array('id' => $id));

    echo $OUTPUT->single_button($new_question_url, 'Nouvelle Question', 'get');
    echo $OUTPUT->single_button($finish_url, 'Terminer', 'get');

    echo $OUTPUT->footer();
    exit;
}

// 📌 Affichage du formulaire d'exercice
echo html_writer::start_tag('form', array('method' => 'post', 'action' => 'attempt.php?id=' . $id . '&difficulty=' . $difficulty . '&submitted=1'));

foreach ($possible_inputs as $field) {
    $label = ucfirst(str_replace('_', ' ', $field));
    $is_given_input = ($field === $random_input);
    $readonly = $is_given_input ? 'readonly' : '';

    if ($field === 'partogramme' || $field === 'shema_simplifie') {
        echo html_writer::tag('h4', $label);
        echo html_writer::tag('label', "Inclinaison", array('for' => "inclinaison_$field"));
        echo html_writer::empty_tag('input', array('type' => 'text', 'name' => "inclinaison_$field", 'id' => "inclinaison_$field", 'value' => $is_given_input ? $random_entry->inclinaison : '', 'required' => true, $readonly => $readonly));
        echo "<br>";

        echo html_writer::tag('label', "Rotation", array('for' => "rotation_$field"));
        echo html_writer::empty_tag('input', array('type' => 'text', 'name' => "rotation_$field", 'id' => "rotation_$field", 'value' => $is_given_input ? $random_entry->rotation : '', 'required' => true, $readonly => $readonly));
        echo "<br>";
    } else {
        echo html_writer::tag('label', $label, array('for' => $field));
        echo html_writer::empty_tag('input', array('type' => 'text', 'name' => $field, 'id' => $field, 'value' => $is_given_input ? $pre_filled_value : '', 'required' => true, $readonly => $readonly));
        echo "<br>";
    }
}

// 📌 Bouton de validation
echo html_writer::empty_tag('input', array('type' => 'submit', 'value' => 'Valider'));

echo html_writer::end_tag('form');
echo $OUTPUT->footer();
