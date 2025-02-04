<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Administration settings definitions for the hippotrack module.
 *
 * @package   mod_hippotrack
 * @copyright 2010 Petr Skoda
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/hippotrack/lib.php');

// First get a list of hippotrack reports with there own settings pages. If there none,
// we use a simpler overall menu structure.
$reports = core_component::get_plugin_list_with_file('hippotrack', 'settings.php', false);
$reportsbyname = array();
foreach ($reports as $report => $reportdir) {
    $strreportname = get_string($report . 'report', 'hippotrack_'.$report);
    $reportsbyname[$strreportname] = $report;
}
core_collator::ksort($reportsbyname);

// First get a list of hippotrack reports with there own settings pages. If there none,
// we use a simpler overall menu structure.
$rules = core_component::get_plugin_list_with_file('hippotrackaccess', 'settings.php', false);
$rulesbyname = array();
foreach ($rules as $rule => $ruledir) {
    $strrulename = get_string('pluginname', 'hippotrackaccess_' . $rule);
    $rulesbyname[$strrulename] = $rule;
}
core_collator::ksort($rulesbyname);

// Create the hippotrack settings page.
if (empty($reportsbyname) && empty($rulesbyname)) {
    $pagetitle = get_string('modulename', 'hippotrack');
} else {
    $pagetitle = get_string('generalsettings', 'admin');
}
$hippotracksettings = new admin_settingpage('modsettinghippotrack', $pagetitle, 'moodle/site:config');

if ($ADMIN->fulltree) {
    // Introductory explanation that all the settings are defaults for the add hippotrack form.
    $hippotracksettings->add(new admin_setting_heading('hippotrackintro', '', get_string('configintro', 'hippotrack')));

    // Time limit.
    $setting = new admin_setting_configduration('hippotrack/timelimit',
            get_string('timelimit', 'hippotrack'), get_string('configtimelimitsec', 'hippotrack'),
            '0', 60);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $hippotracksettings->add($setting);

    // Delay to notify graded attempts.
    $hippotracksettings->add(new admin_setting_configduration('hippotrack/notifyattemptgradeddelay',
        get_string('attemptgradeddelay', 'hippotrack'), get_string('attemptgradeddelay_desc', 'hippotrack'), 5 * HOURSECS, HOURSECS));

    // What to do with overdue attempts.
    $setting = new mod_hippotrack_admin_setting_overduehandling('hippotrack/overduehandling',
            get_string('overduehandling', 'hippotrack'), get_string('overduehandling_desc', 'hippotrack'),
            array('value' => 'autosubmit', 'adv' => false), null);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $hippotracksettings->add($setting);

    // Grace period time.
    $setting = new admin_setting_configduration('hippotrack/graceperiod',
            get_string('graceperiod', 'hippotrack'), get_string('graceperiod_desc', 'hippotrack'),
            '86400');
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $hippotracksettings->add($setting);

    // Minimum grace period used behind the scenes.
    $hippotracksettings->add(new admin_setting_configduration('hippotrack/graceperiodmin',
            get_string('graceperiodmin', 'hippotrack'), get_string('graceperiodmin_desc', 'hippotrack'),
            60, 1));

    // Number of attempts.
    $options = array(get_string('unlimited'));
    for ($i = 1; $i <= HIPPOTRACK_MAX_ATTEMPT_OPTION; $i++) {
        $options[$i] = $i;
    }
    $setting = new admin_setting_configselect('hippotrack/attempts',
            get_string('attemptsallowed', 'hippotrack'), get_string('configattemptsallowed', 'hippotrack'),
            0, $options);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $hippotracksettings->add($setting);

    // Grading method.
    $setting = new mod_hippotrack_admin_setting_grademethod('hippotrack/grademethod',
            get_string('grademethod', 'hippotrack'), get_string('configgrademethod', 'hippotrack'),
            array('value' => HIPPOTRACK_GRADEHIGHEST, 'adv' => false), null);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $hippotracksettings->add($setting);

    // Maximum grade.
    $setting = new admin_setting_configtext('hippotrack/maximumgrade',
            get_string('maximumgrade'), get_string('configmaximumgrade', 'hippotrack'), 10, PARAM_INT);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $hippotracksettings->add($setting);

    // Questions per page.
    $perpage = array();
    $perpage[0] = get_string('never');
    $perpage[1] = get_string('aftereachquestion', 'hippotrack');
    for ($i = 2; $i <= HIPPOTRACK_MAX_QPP_OPTION; ++$i) {
        $perpage[$i] = get_string('afternquestions', 'hippotrack', $i);
    }
    $setting = new admin_setting_configselect('hippotrack/questionsperpage',
            get_string('newpageevery', 'hippotrack'), get_string('confignewpageevery', 'hippotrack'),
            1, $perpage);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $hippotracksettings->add($setting);

    // Navigation method.
    $setting = new admin_setting_configselect('hippotrack/navmethod',
            get_string('navmethod', 'hippotrack'), get_string('confignavmethod', 'hippotrack'),
            HIPPOTRACK_NAVMETHOD_FREE, hippotrack_get_navigation_options());
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, true);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $hippotracksettings->add($setting);

    // Shuffle within questions.
    $setting = new admin_setting_configcheckbox('hippotrack/shuffleanswers',
            get_string('shufflewithin', 'hippotrack'), get_string('configshufflewithin', 'hippotrack'),
            1);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $hippotracksettings->add($setting);

    // Preferred behaviour.
    $setting = new admin_setting_question_behaviour('hippotrack/preferredbehaviour',
            get_string('howquestionsbehave', 'question'), get_string('howquestionsbehave_desc', 'hippotrack'),
            'deferredfeedback');
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $hippotracksettings->add($setting);

    // Can redo completed questions.
    $setting = new admin_setting_configselect('hippotrack/canredoquestions',
            get_string('canredoquestions', 'hippotrack'), get_string('canredoquestions_desc', 'hippotrack'),
            0,
            array(0 => get_string('no'), 1 => get_string('canredoquestionsyes', 'hippotrack')));
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, true);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $hippotracksettings->add($setting);

    // Each attempt builds on last.
    $setting = new admin_setting_configcheckbox('hippotrack/attemptonlast',
            get_string('eachattemptbuildsonthelast', 'hippotrack'),
            get_string('configeachattemptbuildsonthelast', 'hippotrack'),
            0);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, true);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $hippotracksettings->add($setting);

    // Review options.
    $hippotracksettings->add(new admin_setting_heading('reviewheading',
            get_string('reviewoptionsheading', 'hippotrack'), ''));
    foreach (mod_hippotrack_admin_review_setting::fields() as $field => $name) {
        $default = mod_hippotrack_admin_review_setting::all_on();
        $forceduring = null;
        if ($field == 'attempt') {
            $forceduring = true;
        } else if ($field == 'overallfeedback') {
            $default = $default ^ mod_hippotrack_admin_review_setting::DURING;
            $forceduring = false;
        }
        $hippotracksettings->add(new mod_hippotrack_admin_review_setting('hippotrack/review' . $field,
                $name, '', $default, $forceduring));
    }

    // Show the user's picture.
    $setting = new mod_hippotrack_admin_setting_user_image('hippotrack/showuserpicture',
            get_string('showuserpicture', 'hippotrack'), get_string('configshowuserpicture', 'hippotrack'),
            array('value' => 0, 'adv' => false), null);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $hippotracksettings->add($setting);

    // Decimal places for overall grades.
    $options = array();
    for ($i = 0; $i <= HIPPOTRACK_MAX_DECIMAL_OPTION; $i++) {
        $options[$i] = $i;
    }
    $setting = new admin_setting_configselect('hippotrack/decimalpoints',
            get_string('decimalplaces', 'hippotrack'), get_string('configdecimalplaces', 'hippotrack'),
            2, $options);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $hippotracksettings->add($setting);

    // Decimal places for question grades.
    $options = array(-1 => get_string('sameasoverall', 'hippotrack'));
    for ($i = 0; $i <= HIPPOTRACK_MAX_Q_DECIMAL_OPTION; $i++) {
        $options[$i] = $i;
    }
    $setting = new admin_setting_configselect('hippotrack/questiondecimalpoints',
            get_string('decimalplacesquestion', 'hippotrack'),
            get_string('configdecimalplacesquestion', 'hippotrack'),
            -1, $options);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $hippotracksettings->add($setting);

    // Show blocks during hippotrack attempts.
    $setting = new admin_setting_configcheckbox('hippotrack/showblocks',
            get_string('showblocks', 'hippotrack'), get_string('configshowblocks', 'hippotrack'),
            0);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, true);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $hippotracksettings->add($setting);

    // Password.
    $setting = new admin_setting_configpasswordunmask('hippotrack/hippotrackpassword',
            get_string('requirepassword', 'hippotrack'), get_string('configrequirepassword', 'hippotrack'),
            '');
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $setting->set_required_flag_options(admin_setting_flag::ENABLED, false);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $hippotracksettings->add($setting);

    // IP restrictions.
    $setting = new admin_setting_configtext('hippotrack/subnet',
            get_string('requiresubnet', 'hippotrack'), get_string('configrequiresubnet', 'hippotrack'),
            '', PARAM_TEXT);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, true);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $hippotracksettings->add($setting);

    // Enforced delay between attempts.
    $setting = new admin_setting_configduration('hippotrack/delay1',
            get_string('delay1st2nd', 'hippotrack'), get_string('configdelay1st2nd', 'hippotrack'),
            0, 60);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, true);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $hippotracksettings->add($setting);
    $setting = new admin_setting_configduration('hippotrack/delay2',
            get_string('delaylater', 'hippotrack'), get_string('configdelaylater', 'hippotrack'),
            0, 60);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, true);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $hippotracksettings->add($setting);

    // Browser security.
    $setting = new mod_hippotrack_admin_setting_browsersecurity('hippotrack/browsersecurity',
            get_string('showinsecurepopup', 'hippotrack'), get_string('configpopup', 'hippotrack'),
            array('value' => '-', 'adv' => true), null);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $hippotracksettings->add($setting);

    $hippotracksettings->add(new admin_setting_configtext('hippotrack/initialnumfeedbacks',
            get_string('initialnumfeedbacks', 'hippotrack'), get_string('initialnumfeedbacks_desc', 'hippotrack'),
            2, PARAM_INT, 5));

    // Allow user to specify if setting outcomes is an advanced setting.
    if (!empty($CFG->enableoutcomes)) {
        $hippotracksettings->add(new admin_setting_configcheckbox('hippotrack/outcomes_adv',
            get_string('outcomesadvanced', 'hippotrack'), get_string('configoutcomesadvanced', 'hippotrack'),
            '0'));
    }

    // Autosave frequency.
    $hippotracksettings->add(new admin_setting_configduration('hippotrack/autosaveperiod',
            get_string('autosaveperiod', 'hippotrack'), get_string('autosaveperiod_desc', 'hippotrack'), 60, 1));
}

// Now, depending on whether any reports have their own settings page, add
// the hippotrack setting page to the appropriate place in the tree.
if (empty($reportsbyname) && empty($rulesbyname)) {
    $ADMIN->add('modsettings', $hippotracksettings);
} else {
    $ADMIN->add('modsettings', new admin_category('modsettingshippotrackcat',
            get_string('modulename', 'hippotrack'), $module->is_enabled() === false));
    $ADMIN->add('modsettingshippotrackcat', $hippotracksettings);

    // Add settings pages for the hippotrack report subplugins.
    foreach ($reportsbyname as $strreportname => $report) {
        $reportname = $report;

        $settings = new admin_settingpage('modsettingshippotrackcat'.$reportname,
                $strreportname, 'moodle/site:config', $module->is_enabled() === false);
        include($CFG->dirroot . "/mod/hippotrack/report/$reportname/settings.php");
        if (!empty($settings)) {
            $ADMIN->add('modsettingshippotrackcat', $settings);
        }
    }

    // Add settings pages for the hippotrack access rule subplugins.
    foreach ($rulesbyname as $strrulename => $rule) {
        $settings = new admin_settingpage('modsettingshippotrackcat' . $rule,
                $strrulename, 'moodle/site:config', $module->is_enabled() === false);
        include($CFG->dirroot . "/mod/hippotrack/accessrule/$rule/settings.php");
        if (!empty($settings)) {
            $ADMIN->add('modsettingshippotrackcat', $settings);
        }
    }
}

$settings = null; // We do not want standard settings link.
