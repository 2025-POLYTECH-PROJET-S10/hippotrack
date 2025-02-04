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
 * PHPUnit tests for privacy provider.
 *
 * @package    hippotrackaccess_seb
 * @author     Andrew Madden <andrewmadden@catalyst-au.net>
 * @copyright  2020 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace hippotrackaccess_seb\privacy;

use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use core_privacy\tests\request\approved_contextlist;
use core_privacy\tests\provider_testcase;
use hippotrackaccess_seb\privacy\provider;
use hippotrackaccess_seb\hippotrack_settings;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../test_helper_trait.php');

/**
 * PHPUnit tests for privacy provider.
 *
 * @copyright  2020 Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider_test extends provider_testcase {
    use \hippotrackaccess_seb_test_helper_trait;

    /**
     * Setup the user, the hippotrack and ensure that the user is the last user to modify the SEB hippotrack settings.
     */
    public function setup_test_data() {
        $this->resetAfterTest();

        $this->setAdminUser();

        $this->course = $this->getDataGenerator()->create_course();
        $this->hippotrack = $this->create_test_hippotrack($this->course, \hippotrackaccess_seb\settings_provider::USE_SEB_CONFIG_MANUALLY);

        $this->user = $this->getDataGenerator()->create_user();
        $this->setUser($this->user);

        $template = $this->create_template();

        $hippotracksettings = hippotrack_settings::get_record(['hippotrackid' => $this->hippotrack->id]);

        // Modify settings so usermodified is updated. This is the user data we are testing for.
        $hippotracksettings->set('requiresafeexambrowser', \hippotrackaccess_seb\settings_provider::USE_SEB_TEMPLATE);
        $hippotracksettings->set('templateid', $template->get('id'));
        $hippotracksettings->save();

    }

    /**
     * Test that the module context for a user who last modified the module is retrieved.
     */
    public function test_get_contexts_for_userid() {
        $this->setup_test_data();

        $contexts = provider::get_contexts_for_userid($this->user->id);
        $contextids = $contexts->get_contextids();
        $this->assertEquals(\context_module::instance($this->hippotrack->cmid)->id, reset($contextids));
    }

    /**
     * That that no module context is found for a user who has not modified any hippotrack settings.
     */
    public function test_get_no_contexts_for_userid() {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $contexts = provider::get_contexts_for_userid($user->id);
        $contextids = $contexts->get_contextids();
        $this->assertEmpty($contextids);
    }

    /**
     * Test that user data is exported in format expected.
     */
    public function test_export_user_data() {
        $this->setup_test_data();

        $context = \context_module::instance($this->hippotrack->cmid);

        // Add another course_module of a differenty type - doing this lets us
        // test that the data exporter is correctly limiting its selection to
        // the hippotrack and not anything with the same instance id.
        // (note this is only effective with databases not using fed (+1000) sequences
        // per table, like postgres and mysql do, rendering this useless. In any
        // case better to have the situation covered by some DBs,
        // like sqlsrv or oracle than by none).
        $this->getDataGenerator()->create_module('label', array('course' => $this->course->id));

        $contextlist = provider::get_contexts_for_userid($this->user->id);
        $approvedcontextlist = new approved_contextlist(
            $this->user,
            'hippotrackaccess_seb',
            $contextlist->get_contextids()
        );

        writer::reset();
        $writer = writer::with_context($context);
        $this->assertFalse($writer->has_any_data());
        provider::export_user_data($approvedcontextlist);

        $index = '1'; // Get first data returned from the hippotracksettings table metadata.
        $data = $writer->get_data([
            get_string('pluginname', 'hippotrackaccess_seb'),
            hippotrack_settings::TABLE,
            $index,
        ]);
        $this->assertNotEmpty($data);

        $index = '1'; // Get first data returned from the template table metadata.
        $data = $writer->get_data([
            get_string('pluginname', 'hippotrackaccess_seb'),
            \hippotrackaccess_seb\template::TABLE,
            $index,
        ]);
        $this->assertNotEmpty($data);

        $index = '2'; // There should not be more than one instance with data.
        $data = $writer->get_data([
            get_string('pluginname', 'hippotrackaccess_seb'),
            hippotrack_settings::TABLE,
            $index,
        ]);
        $this->assertEmpty($data);

        $index = '2'; // There should not be more than one instance with data.
        $data = $writer->get_data([
            get_string('pluginname', 'hippotrackaccess_seb'),
            \hippotrackaccess_seb\template::TABLE,
            $index,
        ]);
        $this->assertEmpty($data);
    }

    /**
     * Test that a userlist with module context is populated by usermodified user.
     */
    public function test_get_users_in_context() {
        $this->setup_test_data();

        // Create empty userlist with hippotrack module context.
        $userlist = new userlist(\context_module::instance($this->hippotrack->cmid), 'hippotrackaccess_seb');

        // Test that the userlist is populated with expected user/s.
        provider::get_users_in_context($userlist);
        $this->assertEquals($this->user->id, $userlist->get_userids()[0]);
    }

    /**
     * Test that data is deleted for a list of users.
     */
    public function test_delete_data_for_users() {
        $this->setup_test_data();

        $approveduserlist = new approved_userlist(\context_module::instance($this->hippotrack->cmid),
                'hippotrackaccess_seb', [$this->user->id]);

        // Test data exists.
        $this->assertNotEmpty(hippotrack_settings::get_record(['hippotrackid' => $this->hippotrack->id]));

        // Test data is deleted.
        provider::delete_data_for_users($approveduserlist);
        $record = hippotrack_settings::get_record(['hippotrackid' => $this->hippotrack->id]);
        $this->assertEmpty($record->get('usermodified'));

        $template = \hippotrackaccess_seb\template::get_record(['id' => $record->get('templateid')]);
        $this->assertEmpty($template->get('usermodified'));
    }

    /**
     * Test that data is deleted for a list of contexts.
     */
    public function test_delete_data_for_user() {
        $this->setup_test_data();

        $context = \context_module::instance($this->hippotrack->cmid);
        $approvedcontextlist = new approved_contextlist($this->user,
                'hippotrackaccess_seb', [$context->id]);

        // Test data exists.
        $this->assertNotEmpty(hippotrack_settings::get_record(['hippotrackid' => $this->hippotrack->id]));

        // Test data is deleted.
        provider::delete_data_for_user($approvedcontextlist);
        $record = hippotrack_settings::get_record(['hippotrackid' => $this->hippotrack->id]);
        $this->assertEmpty($record->get('usermodified'));

        $template = \hippotrackaccess_seb\template::get_record(['id' => $record->get('templateid')]);
        $this->assertEmpty($template->get('usermodified'));
    }

    /**
     * Test that data is deleted for a single context.
     */
    public function test_delete_data_for_all_users_in_context() {
        $this->setup_test_data();

        $context = \context_module::instance($this->hippotrack->cmid);

        // Test data exists.
        $record = hippotrack_settings::get_record(['hippotrackid' => $this->hippotrack->id]);
        $template = \hippotrackaccess_seb\template::get_record(['id' => $record->get('templateid')]);
        $this->assertNotEmpty($record->get('usermodified'));
        $this->assertNotEmpty($template->get('usermodified'));

        // Test data is deleted.
        provider::delete_data_for_all_users_in_context($context);

        $record = hippotrack_settings::get_record(['hippotrackid' => $this->hippotrack->id]);
        $template = \hippotrackaccess_seb\template::get_record(['id' => $record->get('templateid')]);
        $this->assertEmpty($record->get('usermodified'));
        $this->assertEmpty($template->get('usermodified'));
    }
}
