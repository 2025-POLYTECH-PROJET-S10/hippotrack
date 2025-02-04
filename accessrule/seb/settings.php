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
 * Global configuration settings for the hippotrackaccess_seb plugin.
 *
 * @package    hippotrackaccess_seb
 * @author     Andrew Madden <andrewmadden@catalyst-au.net>
 * @author     Dmitrii Metelkin <dmitriim@catalyst-au.net>
 * @copyright  2019 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

global $ADMIN;

if ($hassiteconfig) {

    $settings->add(new admin_setting_heading(
        'hippotrackaccess_seb/supportedversions',
        '',
        $OUTPUT->notification(get_string('setting:supportedversions', 'hippotrackaccess_seb'), 'warning')));

    $settings->add(new admin_setting_configcheckbox('hippotrackaccess_seb/autoreconfigureseb',
        get_string('setting:autoreconfigureseb', 'hippotrackaccess_seb'),
        get_string('setting:autoreconfigureseb_desc', 'hippotrackaccess_seb'),
        '1'));

    $links = [
        'seb' => get_string('setting:showseblink', 'hippotrackaccess_seb'),
        'http' => get_string('setting:showhttplink', 'hippotrackaccess_seb')
    ];
    $settings->add(new admin_setting_configmulticheckbox('hippotrackaccess_seb/showseblinks',
        get_string('setting:showseblinks', 'hippotrackaccess_seb'),
        get_string('setting:showseblinks_desc', 'hippotrackaccess_seb'),
        $links, $links));

    $settings->add(new admin_setting_configtext('hippotrackaccess_seb/downloadlink',
        get_string('setting:downloadlink', 'hippotrackaccess_seb'),
        get_string('setting:downloadlink_desc', 'hippotrackaccess_seb'),
        'https://safeexambrowser.org/download_en.html',
        PARAM_URL));

    $settings->add(new admin_setting_configcheckbox('hippotrackaccess_seb/hippotrackpasswordrequired',
        get_string('setting:hippotrackpasswordrequired', 'hippotrackaccess_seb'),
        get_string('setting:hippotrackpasswordrequired_desc', 'hippotrackaccess_seb'),
        '0'));

    $settings->add(new admin_setting_configcheckbox('hippotrackaccess_seb/displayblocksbeforestart',
        get_string('setting:displayblocksbeforestart', 'hippotrackaccess_seb'),
        get_string('setting:displayblocksbeforestart_desc', 'hippotrackaccess_seb'),
        '0'));

    $settings->add(new admin_setting_configcheckbox('hippotrackaccess_seb/displayblockswhenfinished',
        get_string('setting:displayblockswhenfinished', 'hippotrackaccess_seb'),
        get_string('setting:displayblockswhenfinished_desc', 'hippotrackaccess_seb'),
        '1'));
}

if (has_capability('hippotrackaccess/seb:managetemplates', context_system::instance())) {
    $ADMIN->add('modsettingshippotrackcat',
        new admin_externalpage(
            'hippotrackaccess_seb/template',
            get_string('manage_templates', 'hippotrackaccess_seb'),
            new moodle_url('/mod/hippotrack/accessrule/seb/template.php'),
            'hippotrackaccess/seb:managetemplates'
        )
    );
}
