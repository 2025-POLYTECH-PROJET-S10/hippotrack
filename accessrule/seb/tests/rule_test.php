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

namespace hippotrackaccess_seb;

use hippotrackaccess_seb;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/test_helper_trait.php');

/**
 * PHPUnit tests for plugin rule class.
 *
 * @package    hippotrackaccess_seb
 * @author     Andrew Madden <andrewmadden@catalyst-au.net>
 * @copyright  2020 Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \hippotrackaccess_seb
 */
class rule_test extends \advanced_testcase {
    use \hippotrackaccess_seb_test_helper_trait;

    /**
     * Called before every test.
     */
    public function setUp(): void {
        parent::setUp();

        $this->resetAfterTest();
        $this->course = $this->getDataGenerator()->create_course();
    }

    /**
     * Called after every test.
     */
    public function tearDown(): void {
        global $SESSION;

        if (!empty($this->hippotrack)) {
            unset($SESSION->hippotrackaccess_seb_access);
        }
    }

    /**
     * Helper method to get SEB download link for testing.
     *
     * @return string
     */
    private function get_seb_download_link() {
        return 'https://safeexambrowser.org/download_en.html';
    }

    /**
     * Helper method to get SEB launch link for testing.
     *
     * @return string
     */
    private function get_seb_launch_link() {
        return 'sebs://www.example.com/moodle/mod/hippotrack/accessrule/seb/config.php';
    }

    /**
     * Helper method to get SEB config download link for testing.
     *
     * @return string
     */
    private function get_seb_config_download_link() {
        return 'https://www.example.com/moodle/mod/hippotrack/accessrule/seb/config.php';
    }

    /**
     * Provider to return valid form field data when saving settings.
     *
     * @return array
     */
    public static function valid_form_data_provider(): array {
        return [
            'valid seb_requiresafeexambrowser' => ['seb_requiresafeexambrowser', '0'],
            'valid seb_linkquitseb0' => ['seb_linkquitseb', 'http://safeexambrowser.org/macosx'],
            'valid seb_linkquitseb1' => ['seb_linkquitseb', 'safeexambrowser.org/macosx'],
            'valid seb_linkquitseb2' => ['seb_linkquitseb', 'www.safeexambrowser.org/macosx'],
            'valid seb_linkquitseb3' => ['seb_linkquitseb', 'any.type.of.url.looking.thing'],
            'valid seb_linkquitseb4' => ['seb_linkquitseb', 'http://any.type.of.url.looking.thing'],
        ];
    }

    /**
     * Provider to return invalid form field data when saving settings.
     *
     * @return array
     */
    public static function invalid_form_data_provider(): array {
        return [
            'invalid seb_requiresafeexambrowser' => ['seb_requiresafeexambrowser', 'Uh oh!'],
            'invalid seb_linkquitseb0' => ['seb_linkquitseb', '\0'],
            'invalid seb_linkquitseb1' => ['seb_linkquitseb', 'invalid url'],
            'invalid seb_linkquitseb2' => ['seb_linkquitseb', 'http]://safeexambrowser.org/macosx'],
            'invalid seb_linkquitseb3' => ['seb_linkquitseb', '0'],
            'invalid seb_linkquitseb4' => ['seb_linkquitseb', 'seb://any.type.of.url.looking.thing'],
        ];
    }

    /**
     * Test no errors are found with valid data.
     *
     * @param string $setting
     * @param string $data
     *
     * @dataProvider valid_form_data_provider
     */
    public function test_validate_settings_with_valid_data(string $setting, string $data) {
        $this->setAdminUser();
        $this->hippotrack = $this->create_test_hippotrack($this->course, settings_provider::USE_SEB_CONFIG_MANUALLY);

        $form = $this->createMock('mod_hippotrack_mod_form');
        $form->method('get_context')->willReturn(\context_module::instance($this->hippotrack->cmid));

        // Validate settings with a dummy form.
        $errors = hippotrackaccess_seb::validate_settings_form_fields([], [
            'instance' => $this->hippotrack->id,
            'coursemodule' => $this->hippotrack->cmid,
            $setting => $data
        ], [], $form);
        $this->assertEmpty($errors);
    }

    /**
     * Test errors are found with invalid data.
     *
     * @param string $setting
     * @param string $data
     *
     * @dataProvider invalid_form_data_provider
     */
    public function test_validate_settings_with_invalid_data(string $setting, string $data) {
        $this->setAdminUser();

        $this->hippotrack = $this->create_test_hippotrack($this->course, settings_provider::USE_SEB_CONFIG_MANUALLY);
        $form = $this->createMock('mod_hippotrack_mod_form');
        $form->method('get_context')->willReturn(\context_module::instance($this->hippotrack->cmid));

        // Validate settings with a dummy form and hippotrack instance.
        $errors = hippotrackaccess_seb::validate_settings_form_fields([], [
            'instance' => $this->hippotrack->id,
            'coursemodule' => $this->hippotrack->cmid,
            $setting => $data
        ], [], $form);
        $this->assertEquals([$setting => 'Data submitted is invalid'], $errors);
    }

    /**
     * Test settings validation is not run if settings are locked.
     */
    public function test_settings_validation_is_not_run_if_settings_are_locked() {
        $user = $this->getDataGenerator()->create_user();
        $this->hippotrack = $this->create_test_hippotrack($this->course);
        $this->attempt_hippotrack($this->hippotrack, $user);

        $this->setAdminUser();

        $form = $this->createMock('mod_hippotrack_mod_form');
        $form->method('get_context')->willReturn(\context_module::instance($this->hippotrack->cmid));

        // Validate settings with a dummy form and hippotrack instance.
        $errors = hippotrackaccess_seb::validate_settings_form_fields([], [
            'instance' => $this->hippotrack->id,
            'coursemodule' => $this->hippotrack->cmid, 'seb_requiresafeexambrowser' => 'Uh oh!'
        ], [], $form);
        $this->assertEmpty($errors);
    }

    /**
     * Test settings validation is not run if settings are conflicting.
     */
    public function test_settings_validation_is_not_run_if_conflicting_permissions() {
        $this->setAdminUser();
        $this->hippotrack = $this->create_test_hippotrack($this->course, settings_provider::USE_SEB_CONFIG_MANUALLY);

        $form = $this->createMock('mod_hippotrack_mod_form');
        $form->method('get_context')->willReturn(\context_module::instance($this->hippotrack->cmid));

        $user = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();
        $context = \context_module::instance($this->hippotrack->cmid);
        assign_capability('hippotrackaccess/seb:manage_seb_requiresafeexambrowser', CAP_ALLOW, $roleid, $context->id);
        $this->getDataGenerator()->role_assign($roleid, $user->id, $context->id);

        // By default The user won't have permissions to configure manually.
        $this->setUser($user);

        // Validate settings with a dummy form and hippotrack instance.
        $errors = hippotrackaccess_seb::validate_settings_form_fields([], [
            'instance' => $this->hippotrack->id,
            'coursemodule' => $this->hippotrack->cmid,
            'seb_requiresafeexambrowser' => 'Uh oh!'
        ], [], $form);
        $this->assertEmpty($errors);
    }

    /**
     * Test bypassing validation if user don't have permissions to manage seb settings.
     */
    public function test_validate_settings_is_not_run_if_a_user_do_not_have_permissions_to_manage_seb_settings() {
        // Set the user who can't change seb settings. So validation should be bypassed.
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $this->hippotrack = $this->create_test_hippotrack($this->course, settings_provider::USE_SEB_CONFIG_MANUALLY);
        $form = $this->createMock('mod_hippotrack_mod_form');
        $form->method('get_context')->willReturn(\context_module::instance($this->hippotrack->cmid));

        // Validate settings with a dummy form and hippotrack instance.
        $errors = hippotrackaccess_seb::validate_settings_form_fields([], [
            'instance' => $this->hippotrack->id,
            'coursemodule' => $this->hippotrack->cmid, 'seb_requiresafeexambrowser' => 'Uh oh!'
        ], [], $form);
        $this->assertEmpty($errors);
    }

    /**
     * Test settings are saved to DB.
     */
    public function test_create_settings_with_existing_hippotrack() {
        global $DB;

        $this->setAdminUser();

        $this->hippotrack = $this->create_test_hippotrack($this->course, settings_provider::USE_SEB_NO);
        $this->assertFalse($DB->record_exists('hippotrackaccess_seb_hippotracksettings', ['hippotrackid' => $this->hippotrack->id]));

        $this->hippotrack->seb_requiresafeexambrowser = settings_provider::USE_SEB_CONFIG_MANUALLY;
        hippotrackaccess_seb::save_settings($this->hippotrack);
        $this->assertNotFalse($DB->record_exists('hippotrackaccess_seb_hippotracksettings', ['hippotrackid' => $this->hippotrack->id]));
    }

    /**
     * Test settings are not saved to DB if settings are locked.
     */
    public function test_settings_are_not_saved_if_settings_are_locked() {
        global $DB;

        $this->setAdminUser();
        $this->hippotrack = $this->create_test_hippotrack($this->course);

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $this->attempt_hippotrack($this->hippotrack, $user);

        $this->setAdminUser();
        $this->hippotrack->seb_requiresafeexambrowser = settings_provider::USE_SEB_CONFIG_MANUALLY;
        hippotrackaccess_seb::save_settings($this->hippotrack);
        $this->assertFalse($DB->record_exists('hippotrackaccess_seb_hippotracksettings', ['hippotrackid' => $this->hippotrack->id]));
    }

    /**
     * Test settings are not saved to DB if conflicting permissions.
     */
    public function test_settings_are_not_saved_if_conflicting_permissions() {
        $this->setAdminUser();
        $this->hippotrack = $this->create_test_hippotrack($this->course, settings_provider::USE_SEB_CONFIG_MANUALLY);

        $user = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();
        $context = \context_module::instance($this->hippotrack->cmid);
        assign_capability('hippotrackaccess/seb:manage_seb_requiresafeexambrowser', CAP_ALLOW, $roleid, $context->id);
        $this->getDataGenerator()->role_assign($roleid, $user->id, $context->id);

        // By default The user won't have permissions to configure manually.
        $this->setUser($user);

        $this->hippotrack->seb_requiresafeexambrowser = settings_provider::USE_SEB_NO;
        hippotrackaccess_seb::save_settings($this->hippotrack);

        $hippotracksettings = hippotrack_settings::get_record(['hippotrackid' => $this->hippotrack->id]);
        $this->assertEquals(settings_provider::USE_SEB_CONFIG_MANUALLY, $hippotracksettings->get('requiresafeexambrowser'));
    }

    /**
     * Test exception thrown if cm could not be found while saving settings.
     */
    public function test_save_settings_throw_an_exception_if_cm_not_found() {
        global $DB;

        $this->expectException(\dml_missing_record_exception::class);
        $this->expectExceptionMessage('Can\'t find data record in database.');

        $this->setAdminUser();

        $this->hippotrack = $this->create_test_hippotrack($this->course, settings_provider::USE_SEB_CONFIG_MANUALLY);
        $DB->delete_records('hippotrack', ['id' => $this->hippotrack->id]);
        $this->hippotrack->seb_requiresafeexambrowser = settings_provider::USE_SEB_NO;
        hippotrackaccess_seb::save_settings($this->hippotrack);
    }

    /**
     * Test nothing happens when deleted is called without settings saved.
     */
    public function test_delete_settings_without_existing_settings() {
        global $DB;
        $this->setAdminUser();

        $hippotrack = new \stdClass();
        $hippotrack->id = 1;
        hippotrackaccess_seb::delete_settings($hippotrack);
        $this->assertFalse($DB->record_exists('hippotrackaccess_seb_hippotracksettings', ['hippotrackid' => $hippotrack->id]));
    }

    /**
     * Test settings are deleted from DB.
     */
    public function test_delete_settings_with_existing_settings() {
        global $DB;
        $this->setAdminUser();

        $this->hippotrack = $this->create_test_hippotrack($this->course, settings_provider::USE_SEB_CONFIG_MANUALLY);

        // Using a generator will create the hippotrack_settings record.
        $this->assertNotFalse($DB->record_exists('hippotrackaccess_seb_hippotracksettings', ['hippotrackid' => $this->hippotrack->id]));
        hippotrackaccess_seb::delete_settings($this->hippotrack);
        $this->assertFalse($DB->record_exists('hippotrackaccess_seb_hippotracksettings', ['hippotrackid' => $this->hippotrack->id]));
    }

    /**
     * A helper method to check invalid config key.
     */
    protected function check_invalid_config_key() {
        // Create an event sink, trigger event and retrieve event.
        $sink = $this->redirectEvents();

        // Check that correct error message is returned.
        $errormsg = $this->make_rule()->prevent_access();
        $this->assertNotEmpty($errormsg);
        $this->assertStringContainsString("The Safe Exam Browser keys could not be validated. "
            . "Check that you're using Safe Exam Browser with the correct configuration file.", $errormsg);
        $this->assertStringContainsString($this->get_seb_download_link(), $errormsg);
        $this->assertStringContainsString($this->get_seb_launch_link(), $errormsg);
        $this->assertStringContainsString($this->get_seb_config_download_link(), $errormsg);

        $events = $sink->get_events();
        $this->assertEquals(1, count($events));
        $event = reset($events);

        // Test that the event data is as expected.
        $this->assertInstanceOf('\hippotrackaccess_seb\event\access_prevented', $event);
        $this->assertEquals('Invalid SEB config key', $event->other['reason']);
    }

    /**
     * Test access prevented if config key is invalid.
     */
    public function test_access_prevented_if_config_key_invalid() {
        global $FULLME;

        $this->setAdminUser();
        $this->hippotrack = $this->create_test_hippotrack($this->course, settings_provider::USE_SEB_CONFIG_MANUALLY);

        // Set up dummy request.
        $FULLME = 'https://example.com/moodle/mod/hippotrack/attempt.php?attemptid=123&page=4';
        $_SERVER['HTTP_X_SAFEEXAMBROWSER_CONFIGKEYHASH'] = 'Broken config key';

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $this->check_invalid_config_key();
    }

    /**
     * Test access prevented if config keys is invalid and using uploaded config.
     */
    public function test_access_prevented_if_config_key_invalid_uploaded_config() {
        global $FULLME;

        $this->setAdminUser();
        $this->hippotrack = $this->create_test_hippotrack($this->course, settings_provider::USE_SEB_CONFIG_MANUALLY);

        // Set hippotrack setting to require seb and save BEK.
        $hippotracksettings = hippotrack_settings::get_record(['hippotrackid' => $this->hippotrack->id]);
        $hippotracksettings->set('requiresafeexambrowser', settings_provider::USE_SEB_UPLOAD_CONFIG);
        $xml = file_get_contents(__DIR__ . '/fixtures/unencrypted.seb');
        $this->create_module_test_file($xml, $this->hippotrack->cmid);
        $hippotracksettings->save();

        // Set up dummy request.
        $FULLME = 'https://example.com/moodle/mod/hippotrack/attempt.php?attemptid=123&page=4';
        $_SERVER['HTTP_X_SAFEEXAMBROWSER_CONFIGKEYHASH'] = 'Broken config key';

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $this->check_invalid_config_key();
    }

    /**
     * Test access prevented if config keys is invalid and using template.
     */
    public function test_access_prevented_if_config_key_invalid_uploaded_template() {
        global $FULLME;

        $this->setAdminUser();
        $this->hippotrack = $this->create_test_hippotrack($this->course, settings_provider::USE_SEB_CONFIG_MANUALLY);

        // Set hippotrack setting to require seb and save BEK.
        $hippotracksettings = hippotrack_settings::get_record(['hippotrackid' => $this->hippotrack->id]);
        $hippotracksettings->set('requiresafeexambrowser', settings_provider::USE_SEB_TEMPLATE);
        $hippotracksettings->set('templateid', $this->create_template()->get('id'));
        $hippotracksettings->save();

        // Set up dummy request.
        $FULLME = 'https://example.com/moodle/mod/hippotrack/attempt.php?attemptid=123&page=4';
        $_SERVER['HTTP_X_SAFEEXAMBROWSER_CONFIGKEYHASH'] = 'Broken config key';

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $this->check_invalid_config_key();
    }

    /**
     * Test access not prevented if config key matches header.
     */
    public function test_access_allowed_if_config_key_valid() {
        global $FULLME;

        $this->setAdminUser();
        $this->hippotrack = $this->create_test_hippotrack($this->course, settings_provider::USE_SEB_CONFIG_MANUALLY);

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        // Set hippotrack setting to require seb.
        $hippotracksettings = hippotrack_settings::get_record(['hippotrackid' => $this->hippotrack->id]);

        // Set up dummy request.
        $FULLME = 'https://example.com/moodle/mod/hippotrack/attempt.php?attemptid=123&page=4';
        $expectedhash = hash('sha256', $FULLME . $hippotracksettings->get_config_key());
        $_SERVER['HTTP_X_SAFEEXAMBROWSER_CONFIGKEYHASH'] = $expectedhash;

        // Check that correct error message is returned.
        $this->assertFalse($this->make_rule()->prevent_access());
    }

    /**
     * Test access not prevented if config key matches header.
     */
    public function test_access_allowed_if_config_key_valid_uploaded_config() {
        global $FULLME;

        $this->setAdminUser();
        $this->hippotrack = $this->create_test_hippotrack($this->course, settings_provider::USE_SEB_CONFIG_MANUALLY);

        // Set hippotrack setting to require seb and save BEK.
        $hippotracksettings = hippotrack_settings::get_record(['hippotrackid' => $this->hippotrack->id]);
        $hippotracksettings->set('requiresafeexambrowser', settings_provider::USE_SEB_TEMPLATE);
        $hippotracksettings->set('templateid', $this->create_template()->get('id'));
        $hippotracksettings->save();

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        // Set up dummy request.
        $FULLME = 'https://example.com/moodle/mod/hippotrack/attempt.php?attemptid=123&page=4';
        $expectedhash = hash('sha256', $FULLME . $hippotracksettings->get_config_key());
        $_SERVER['HTTP_X_SAFEEXAMBROWSER_CONFIGKEYHASH'] = $expectedhash;

        // Check that correct error message is returned.
        $this->assertFalse($this->make_rule()->prevent_access());
    }

    /**
     * Test access not prevented if config key matches header.
     */
    public function test_access_allowed_if_config_key_valid_template() {
        global $FULLME;

        $this->setAdminUser();
        $this->hippotrack = $this->create_test_hippotrack($this->course, settings_provider::USE_SEB_CONFIG_MANUALLY);

        // Set hippotrack setting to require seb and save BEK.
        $hippotracksettings = hippotrack_settings::get_record(['hippotrackid' => $this->hippotrack->id]);
        $hippotracksettings->set('requiresafeexambrowser', settings_provider::USE_SEB_UPLOAD_CONFIG);
        $xml = file_get_contents(__DIR__ . '/fixtures/unencrypted.seb');
        $this->create_module_test_file($xml, $this->hippotrack->cmid);
        $hippotracksettings->save();

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        // Set up dummy request.
        $FULLME = 'https://example.com/moodle/mod/hippotrack/attempt.php?attemptid=123&page=4';
        $expectedhash = hash('sha256', $FULLME . $hippotracksettings->get_config_key());
        $_SERVER['HTTP_X_SAFEEXAMBROWSER_CONFIGKEYHASH'] = $expectedhash;

        // Check that correct error message is returned.
        $this->assertFalse($this->make_rule()->prevent_access());
    }

    /**
     * Test access not prevented if browser exam keys match headers.
     */
    public function test_access_allowed_if_browser_exam_keys_valid() {
        global $FULLME;

        $this->setAdminUser();
        $this->hippotrack = $this->create_test_hippotrack($this->course, settings_provider::USE_SEB_CONFIG_MANUALLY);

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        // Set hippotrack setting to require seb and save BEK.
        $browserexamkey = hash('sha256', 'testkey');
        $hippotracksettings = hippotrack_settings::get_record(['hippotrackid' => $this->hippotrack->id]);
        $hippotracksettings->set('requiresafeexambrowser', settings_provider::USE_SEB_CLIENT_CONFIG); // Doesn't check config key.
        $hippotracksettings->set('allowedbrowserexamkeys', $browserexamkey);
        $hippotracksettings->save();

        // Set up dummy request.
        $FULLME = 'https://example.com/moodle/mod/hippotrack/attempt.php?attemptid=123&page=4';
        $expectedhash = hash('sha256', $FULLME . $browserexamkey);
        $_SERVER['HTTP_X_SAFEEXAMBROWSER_REQUESTHASH'] = $expectedhash;
        $_SERVER['HTTP_USER_AGENT'] = 'SEB';

        // Check that correct error message is returned.
        $this->assertFalse($this->make_rule()->prevent_access());
    }

    /**
     * Test access not prevented if browser exam keys match headers.
     */
    public function test_access_allowed_if_browser_exam_keys_valid_use_uploaded_file() {
        global $FULLME;

        $this->setAdminUser();
        $this->hippotrack = $this->create_test_hippotrack($this->course, settings_provider::USE_SEB_CONFIG_MANUALLY);

        // Set hippotrack setting to require seb and save BEK.
        $browserexamkey = hash('sha256', 'testkey');
        $hippotracksettings = hippotrack_settings::get_record(['hippotrackid' => $this->hippotrack->id]);
        $hippotracksettings->set('requiresafeexambrowser', settings_provider::USE_SEB_UPLOAD_CONFIG);
        $hippotracksettings->set('allowedbrowserexamkeys', $browserexamkey);
        $xml = file_get_contents(__DIR__ . '/fixtures/unencrypted.seb');
        $this->create_module_test_file($xml, $this->hippotrack->cmid);
        $hippotracksettings->save();

        // Set up dummy request.
        $FULLME = 'https://example.com/moodle/mod/hippotrack/attempt.php?attemptid=123&page=4';
        $expectedbrowserkey = hash('sha256', $FULLME . $browserexamkey);
        $_SERVER['HTTP_X_SAFEEXAMBROWSER_REQUESTHASH'] = $expectedbrowserkey;
        $expectedconfigkey = hash('sha256', $FULLME . $hippotracksettings->get_config_key());
        $_SERVER['HTTP_X_SAFEEXAMBROWSER_CONFIGKEYHASH'] = $expectedconfigkey;

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        // Check that correct error message is returned.
        $this->assertFalse($this->make_rule()->prevent_access());
    }

    public function test_access_allowed_if_access_state_stored_in_session() {
        global $SESSION;

        $this->setAdminUser();
        $this->hippotrack = $this->create_test_hippotrack($this->course, settings_provider::USE_SEB_CLIENT_CONFIG);

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        // Check that access is prevented.
        $this->check_invalid_basic_header();

        $SESSION->hippotrackaccess_seb_access = [$this->hippotrack->cmid => true];

        // Check access is now not prevented.
        $this->assertFalse($this->make_rule()->prevent_access());
    }

    /**
     * A helper method to check invalid browser key.
     *
     * @param bool $downloadseblink Make sure download SEB link is present.
     * @param bool $launchlink Make sure launch SEB link is present.
     * @param bool $downloadconfiglink Make download config link is present.
     */
    protected function check_invalid_browser_exam_key($downloadseblink = true, $launchlink = true, $downloadconfiglink = true) {
        // Create an event sink, trigger event and retrieve event.
        $sink = $this->redirectEvents();

        // Check that correct error message is returned.
        $errormsg = $this->make_rule()->prevent_access();
        $this->assertNotEmpty($errormsg);
        $this->assertStringContainsString("The Safe Exam Browser keys could not be validated. "
            . "Check that you're using Safe Exam Browser with the correct configuration file.", $errormsg);

        if ($downloadseblink) {
            $this->assertStringContainsString($this->get_seb_download_link(), $errormsg);
        } else {
            $this->assertStringNotContainsString($this->get_seb_download_link(), $errormsg);
        }

        if ($launchlink) {
            $this->assertStringContainsString($this->get_seb_launch_link(), $errormsg);
        } else {
            $this->assertStringNotContainsString($this->get_seb_launch_link(), $errormsg);
        }

        if ($downloadconfiglink) {
            $this->assertStringContainsString($this->get_seb_config_download_link(), $errormsg);
        } else {
            $this->assertStringNotContainsString($this->get_seb_config_download_link(), $errormsg);
        }

        $events = $sink->get_events();
        $this->assertEquals(1, count($events));
        $event = reset($events);

        // Test that the event data is as expected.
        $this->assertInstanceOf('\hippotrackaccess_seb\event\access_prevented', $event);
        $this->assertEquals('Invalid SEB browser key', $event->other['reason']);
    }

    /**
     * Test access prevented if browser exam keys do not match headers.
     */
    public function test_access_prevented_if_browser_exam_keys_are_invalid() {
        $this->setAdminUser();
        $this->hippotrack = $this->create_test_hippotrack($this->course, settings_provider::USE_SEB_CONFIG_MANUALLY);

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        // Set hippotrack setting to require seb and save BEK.
        $browserexamkey = hash('sha256', 'testkey');
        $hippotracksettings = hippotrack_settings::get_record(['hippotrackid' => $this->hippotrack->id]);
        $hippotracksettings->set('requiresafeexambrowser', settings_provider::USE_SEB_CLIENT_CONFIG); // Doesn't check config key.
        $hippotracksettings->set('allowedbrowserexamkeys', $browserexamkey);
        $hippotracksettings->save();

        // Set up dummy request.
        $_SERVER['HTTP_X_SAFEEXAMBROWSER_REQUESTHASH'] = 'Broken browser key';
        $_SERVER['HTTP_USER_AGENT'] = 'SEB';

        $this->check_invalid_browser_exam_key(true, false, false);
    }

    /**
     * Test access prevented if browser exam keys do not match headers and using uploaded config.
     */
    public function test_access_prevented_if_browser_exam_keys_are_invalid_use_uploaded_file() {
        global $FULLME;

        $this->setAdminUser();
        $this->hippotrack = $this->create_test_hippotrack($this->course, settings_provider::USE_SEB_CONFIG_MANUALLY);

        // Set hippotrack setting to require seb and save BEK.
        $browserexamkey = hash('sha256', 'testkey');
        $hippotracksettings = hippotrack_settings::get_record(['hippotrackid' => $this->hippotrack->id]);
        $hippotracksettings->set('requiresafeexambrowser', settings_provider::USE_SEB_UPLOAD_CONFIG);
        $hippotracksettings->set('allowedbrowserexamkeys', $browserexamkey);
        $xml = file_get_contents(__DIR__ . '/fixtures/unencrypted.seb');
        $this->create_module_test_file($xml, $this->hippotrack->cmid);
        $hippotracksettings->save();

        // Set up dummy request.
        $FULLME = 'https://example.com/moodle/mod/hippotrack/attempt.php?attemptid=123&page=4';
        $expectedhash = hash('sha256', $FULLME . $hippotracksettings->get_config_key());
        $_SERVER['HTTP_X_SAFEEXAMBROWSER_CONFIGKEYHASH'] = $expectedhash;

        // Set  up broken browser key.
        $_SERVER['HTTP_X_SAFEEXAMBROWSER_REQUESTHASH'] = 'Broken browser key';

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $this->check_invalid_browser_exam_key();
    }

    /**
     * Test access not prevented if browser exam keys do not match headers and using template.
     */
    public function test_access_prevented_if_browser_exam_keys_are_invalid_use_template() {
        global $FULLME;

        $this->setAdminUser();
        $this->hippotrack = $this->create_test_hippotrack($this->course, settings_provider::USE_SEB_CONFIG_MANUALLY);

        // Set hippotrack setting to require seb and save BEK.
        $browserexamkey = hash('sha256', 'testkey');
        $hippotracksettings = hippotrack_settings::get_record(['hippotrackid' => $this->hippotrack->id]);
        $hippotracksettings->set('requiresafeexambrowser', settings_provider::USE_SEB_TEMPLATE);
        $hippotracksettings->set('allowedbrowserexamkeys', $browserexamkey);
        $hippotracksettings->set('templateid', $this->create_template()->get('id'));
        $hippotracksettings->save();

        // Set up dummy request.
        $FULLME = 'https://example.com/moodle/mod/hippotrack/attempt.php?attemptid=123&page=4';
        $expectedhash = hash('sha256', $FULLME . $hippotracksettings->get_config_key());
        $_SERVER['HTTP_X_SAFEEXAMBROWSER_CONFIGKEYHASH'] = $expectedhash;

        // Set  up broken browser key.
        $_SERVER['HTTP_X_SAFEEXAMBROWSER_REQUESTHASH'] = 'Broken browser key';

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        // Check that correct error message is returned.
        $this->assertFalse($this->make_rule()->prevent_access());
    }

    /**
     * Test access allowed if using client configuration and SEB user agent header is valid.
     */
    public function test_access_allowed_if_using_client_config_basic_header_is_valid() {
        $this->setAdminUser();
        $this->hippotrack = $this->create_test_hippotrack($this->course, settings_provider::USE_SEB_CONFIG_MANUALLY);

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        // Set hippotrack setting to require seb.
        $hippotracksettings = hippotrack_settings::get_record(['hippotrackid' => $this->hippotrack->id]);
        $hippotracksettings->set('requiresafeexambrowser', settings_provider::USE_SEB_CLIENT_CONFIG); // Doesn't check config key.
        $hippotracksettings->save();

        // Set up basic dummy request.
        $_SERVER['HTTP_USER_AGENT'] = 'SEB_TEST_SITE';

        // Check that correct error message is returned.
        $this->assertFalse($this->make_rule()->prevent_access());
    }

    /**
     * Test access prevented if using client configuration and SEB user agent header is invalid.
     */
    public function test_access_prevented_if_using_client_configuration_and_basic_head_is_invalid() {
        $this->setAdminUser();
        $this->hippotrack = $this->create_test_hippotrack($this->course, settings_provider::USE_SEB_CONFIG_MANUALLY);

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        // Set hippotrack setting to require seb.
        $hippotracksettings = hippotrack_settings::get_record(['hippotrackid' => $this->hippotrack->id]);
        $hippotracksettings->set('requiresafeexambrowser', settings_provider::USE_SEB_CLIENT_CONFIG); // Doesn't check config key.
        $hippotracksettings->save();

        // Set up basic dummy request.
        $_SERVER['HTTP_USER_AGENT'] = 'WRONG_TEST_SITE';

        // Create an event sink, trigger event and retrieve event.
        $this->check_invalid_basic_header();
    }

    /**
     * A helper method to check invalid basic header.
     */
    protected function check_invalid_basic_header() {
        // Create an event sink, trigger event and retrieve event.
        $sink = $this->redirectEvents();

        // Check that correct error message is returned.
        $this->assertStringContainsString(
            'This hippotrack has been configured to use the Safe Exam Browser with client configuration.',
            $this->make_rule()->prevent_access()
        );

        $events = $sink->get_events();
        $this->assertEquals(1, count($events));
        $event = reset($events);

        // Test that the event data is as expected.
        $this->assertInstanceOf('\hippotrackaccess_seb\event\access_prevented', $event);
        $this->assertEquals('No Safe Exam Browser is being used.', $event->other['reason']);
    }

    /**
     * Test access allowed if using client configuration and SEB user agent header is invalid and use uploaded file.
     */
    public function test_access_allowed_if_using_client_configuration_and_basic_head_is_invalid_use_uploaded_config() {
        global $FULLME;

        $this->setAdminUser();
        $this->hippotrack = $this->create_test_hippotrack($this->course, settings_provider::USE_SEB_CONFIG_MANUALLY);

        // Set hippotrack setting to require seb.
        $hippotracksettings = hippotrack_settings::get_record(['hippotrackid' => $this->hippotrack->id]);
        $hippotracksettings->set('requiresafeexambrowser', settings_provider::USE_SEB_UPLOAD_CONFIG); // Doesn't check basic header.
        $xml = file_get_contents(__DIR__ . '/fixtures/unencrypted.seb');
        $this->create_module_test_file($xml, $this->hippotrack->cmid);
        $hippotracksettings->save();

        // Set up dummy request.
        $FULLME = 'https://example.com/moodle/mod/hippotrack/attempt.php?attemptid=123&page=4';
        $expectedhash = hash('sha256', $FULLME . $hippotracksettings->get_config_key());
        $_SERVER['HTTP_X_SAFEEXAMBROWSER_CONFIGKEYHASH'] = $expectedhash;
        $_SERVER['HTTP_USER_AGENT'] = 'WRONG_TEST_SITE';

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        // Check that correct error message is returned.
        $this->assertFalse($this->make_rule()->prevent_access());
    }

    /**
     * Test access allowed if using client configuration and SEB user agent header is invalid and use template.
     */
    public function test_access_allowed_if_using_client_configuration_and_basic_head_is_invalid_use_template() {
        global $FULLME;

        $this->setAdminUser();
        $this->hippotrack = $this->create_test_hippotrack($this->course, settings_provider::USE_SEB_CONFIG_MANUALLY);

        // Set hippotrack setting to require seb.
        $hippotracksettings = hippotrack_settings::get_record(['hippotrackid' => $this->hippotrack->id]);
        $hippotracksettings->set('requiresafeexambrowser', settings_provider::USE_SEB_TEMPLATE);
        $hippotracksettings->set('templateid', $this->create_template()->get('id'));
        $hippotracksettings->save();

        // Set up dummy request.
        $FULLME = 'https://example.com/moodle/mod/hippotrack/attempt.php?attemptid=123&page=4';
        $expectedhash = hash('sha256', $FULLME . $hippotracksettings->get_config_key());
        $_SERVER['HTTP_X_SAFEEXAMBROWSER_CONFIGKEYHASH'] = $expectedhash;
        $_SERVER['HTTP_USER_AGENT'] = 'WRONG_TEST_SITE';

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        // Check that correct error message is returned.
        $this->assertFalse($this->make_rule()->prevent_access());
    }

    /**
     * Test access not prevented if SEB not required.
     */
    public function test_access_allowed_if_seb_not_required() {
        $this->setAdminUser();
        $this->hippotrack = $this->create_test_hippotrack($this->course, settings_provider::USE_SEB_CONFIG_MANUALLY);

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        // Set hippotrack setting to not require seb.
        $hippotracksettings = hippotrack_settings::get_record(['hippotrackid' => $this->hippotrack->id]);
        $hippotracksettings->set('requiresafeexambrowser', settings_provider::USE_SEB_NO);
        $hippotracksettings->save();

        // The rule will not exist as the settings are not configured for SEB usage.
        $this->assertNull($this->make_rule());
    }

    /**
     * Test access not prevented if USER has bypass capability.
     */
    public function test_access_allowed_if_user_has_bypass_capability() {
        $this->setAdminUser();
        $this->hippotrack = $this->create_test_hippotrack($this->course, settings_provider::USE_SEB_CONFIG_MANUALLY);

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        // Set the bypass SEB check capability to $USER.
        $this->assign_user_capability('hippotrackaccess/seb:bypassseb', \context_module::instance($this->hippotrack->cmid)->id);

        // Check that correct error message is returned.
        $this->assertFalse($this->make_rule()->prevent_access());
    }

    /**
     * Test that hippotrack form cannot be saved if using template, but not actually pick one.
     */
    public function test_mod_hippotrack_form_cannot_be_saved_using_template_and_template_is_not_set() {
        $this->setAdminUser();
        $this->hippotrack = $this->create_test_hippotrack($this->course, settings_provider::USE_SEB_CONFIG_MANUALLY);

        $form = $this->createMock('mod_hippotrack_mod_form');
        $form->method('get_context')->willReturn(\context_module::instance($this->hippotrack->cmid));

        // Validate settings with a dummy form.
        $errors = hippotrackaccess_seb::validate_settings_form_fields([], [
            'instance' => $this->hippotrack->id,
            'coursemodule' => $this->hippotrack->cmid,
            'seb_requiresafeexambrowser' => settings_provider::USE_SEB_TEMPLATE
        ], [], $form);

        $this->assertContains(get_string('invalidtemplate', 'hippotrackaccess_seb'), $errors);
    }

    /**
     * Test that hippotrack form cannot be saved if uploaded invalid file.
     */
    public function test_mod_hippotrack_form_cannot_be_saved_using_uploaded_file_and_file_is_not_valid() {
        $this->setAdminUser();
        $this->hippotrack = $this->create_test_hippotrack($this->course, settings_provider::USE_SEB_CONFIG_MANUALLY);

        $form = $this->createMock('mod_hippotrack_mod_form');
        $form->method('get_context')->willReturn(\context_module::instance($this->hippotrack->cmid));

        // Validate settings with a dummy form.
        $errors = hippotrackaccess_seb::validate_settings_form_fields([], [
            'instance' => $this->hippotrack->id,
            'coursemodule' => $this->hippotrack->cmid,
            'seb_requiresafeexambrowser' => settings_provider::USE_SEB_UPLOAD_CONFIG,
            'filemanager_sebconfigfile' => 0,
        ], [], $form);

        $this->assertContainsEquals(get_string('filenotpresent', 'hippotrackaccess_seb'), $errors);
    }

    /**
     * Test that hippotrack form cannot be saved if the global settings are set to require a password and no password is set.
     */
    public function test_mod_hippotrack_form_cannot_be_saved_if_global_settings_force_hippotrack_password_and_none_is_set() {
        $this->setAdminUser();
        // Set global settings to require hippotrack password but set password to be empty.
        set_config('hippotrackpasswordrequired', '1', 'hippotrackaccess_seb');
        $this->hippotrack = $this->create_test_hippotrack($this->course, settings_provider::USE_SEB_CONFIG_MANUALLY);

        $form = $this->createMock('mod_hippotrack_mod_form');
        $form->method('get_context')->willReturn(\context_module::instance($this->hippotrack->cmid));

        // Validate settings with a dummy form.
        $errors = hippotrackaccess_seb::validate_settings_form_fields([], [
            'instance' => $this->hippotrack->id,
            'coursemodule' => $this->hippotrack->cmid,
            'seb_requiresafeexambrowser' => settings_provider::USE_SEB_CONFIG_MANUALLY,
        ], [], $form);

        $this->assertContains(get_string('passwordnotset', 'hippotrackaccess_seb'), $errors);
    }

    /**
     * Test that access to hippotrack is allowed if global setting is set to restrict hippotrack if no hippotrack password is set, and global hippotrack
     * password is set.
     */
    public function test_mod_hippotrack_form_can_be_saved_if_global_settings_force_hippotrack_password_and_is_set() {
        $this->setAdminUser();
        // Set global settings to require hippotrack password but set password to be empty.
        set_config('hippotrackpasswordrequired', '1', 'hippotrackaccess_seb');

        $this->hippotrack = $this->create_test_hippotrack($this->course, settings_provider::USE_SEB_CONFIG_MANUALLY);

        $form = $this->createMock('mod_hippotrack_mod_form');
        $form->method('get_context')->willReturn(\context_module::instance($this->hippotrack->cmid));

        // Validate settings with a dummy form.
        $errors = hippotrackaccess_seb::validate_settings_form_fields([], [
            'instance' => $this->hippotrack->id,
            'coursemodule' => $this->hippotrack->cmid,
            'hippotrackpassword' => 'set',
            'seb_requiresafeexambrowser' => settings_provider::USE_SEB_CONFIG_MANUALLY,
        ], [], $form);
        $this->assertNotContains(get_string('passwordnotset', 'hippotrackaccess_seb'), $errors);
    }

    /**
     * Test that hippotrack form can be saved if the global settings are set to require a password and no seb usage selected.
     */
    public function test_mod_hippotrack_form_can_be_saved_if_global_settings_force_hippotrack_password_and_none_no_seb() {
        $this->setAdminUser();
        // Set global settings to require hippotrack password but set password to be empty.
        set_config('hippotrackpasswordrequired', '1', 'hippotrackaccess_seb');
        $this->hippotrack = $this->create_test_hippotrack($this->course, settings_provider::USE_SEB_NO);

        $form = $this->createMock('mod_hippotrack_mod_form');
        $form->method('get_context')->willReturn(\context_module::instance($this->hippotrack->cmid));

        // Validate settings with a dummy form.
        $errors = hippotrackaccess_seb::validate_settings_form_fields([], [
            'instance' => $this->hippotrack->id,
            'coursemodule' => $this->hippotrack->cmid,
            'seb_requiresafeexambrowser' => settings_provider::USE_SEB_NO,
        ], [], $form);

        $this->assertNotContains(get_string('passwordnotset', 'hippotrackaccess_seb'), $errors);
    }

    /**
     * Test get_download_seb_button, checks for empty config setting hippotrackaccess_seb/downloadlink.
     */
    public function test_get_download_seb_button() {
        $this->setAdminUser();
        $this->hippotrack = $this->create_test_hippotrack($this->course, settings_provider::USE_SEB_CONFIG_MANUALLY);

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $reflection = new \ReflectionClass('hippotrackaccess_seb');
        $method = $reflection->getMethod('get_download_seb_button');
        $method->setAccessible(true);

        // The current default contents.
        $this->assertStringContainsString($this->get_seb_download_link(), $method->invoke($this->make_rule()));

        set_config('downloadlink', '', 'hippotrackaccess_seb');

        // Will not return any button if the URL is empty.
        $this->assertSame('', $method->invoke($this->make_rule()));
    }

    /**
     * Test get_download_seb_button shows download SEB link when required,
     */
    public function test_get_get_action_buttons_shows_download_seb_link() {
        $this->setAdminUser();
        $this->hippotrack = $this->create_test_hippotrack($this->course, settings_provider::USE_SEB_CONFIG_MANUALLY);

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $reflection = new \ReflectionClass('hippotrackaccess_seb');
        $method = $reflection->getMethod('get_action_buttons');
        $method->setAccessible(true);

        $this->assertStringContainsString($this->get_seb_download_link(), $method->invoke($this->make_rule()));

        $this->hippotrack->seb_showsebdownloadlink = 0;
        $this->assertStringNotContainsString($this->get_seb_download_link(), $method->invoke($this->make_rule()));
    }

    /**
     * Test get_download_seb_button shows SEB config related links when required.
     */
    public function test_get_get_action_buttons_shows_launch_and_download_config_links() {
        $this->setAdminUser();
        $this->hippotrack = $this->create_test_hippotrack($this->course, settings_provider::USE_SEB_CONFIG_MANUALLY);

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $reflection = new \ReflectionClass('hippotrackaccess_seb');
        $method = $reflection->getMethod('get_action_buttons');
        $method->setAccessible(true);

        $hippotracksettings = hippotrack_settings::get_record(['hippotrackid' => $this->hippotrack->id]);

        // Should see link when using manually.
        $this->assertStringContainsString($this->get_seb_launch_link(), $method->invoke($this->make_rule()));
        $this->assertStringContainsString($this->get_seb_config_download_link(), $method->invoke($this->make_rule()));

        // Should see links when using template.
        $hippotracksettings->set('requiresafeexambrowser', settings_provider::USE_SEB_TEMPLATE);
        $hippotracksettings->set('templateid', $this->create_template()->get('id'));
        $hippotracksettings->save();
        $this->assertStringContainsString($this->get_seb_launch_link(), $method->invoke($this->make_rule()));
        $this->assertStringContainsString($this->get_seb_config_download_link(), $method->invoke($this->make_rule()));

        // Should see links when using uploaded config.
        $hippotracksettings->set('requiresafeexambrowser', settings_provider::USE_SEB_UPLOAD_CONFIG);
        $xml = file_get_contents(__DIR__ . '/fixtures/unencrypted.seb');
        $this->create_module_test_file($xml, $this->hippotrack->cmid);
        $hippotracksettings->save();
        $this->assertStringContainsString($this->get_seb_launch_link(), $method->invoke($this->make_rule()));
        $this->assertStringContainsString($this->get_seb_config_download_link(), $method->invoke($this->make_rule()));

        // Shouldn't see links if using client config.
        $hippotracksettings->set('requiresafeexambrowser', settings_provider::USE_SEB_CLIENT_CONFIG);
        $hippotracksettings->save();
        $this->assertStringNotContainsString($this->get_seb_launch_link(), $method->invoke($this->make_rule()));
        $this->assertStringNotContainsString($this->get_seb_config_download_link(), $method->invoke($this->make_rule()));
    }

    /**
     * Test get_download_seb_button shows SEB config related links as configured in "showseblinks".
     */
    public function test_get_get_action_buttons_shows_launch_and_download_config_links_as_configured() {
        $this->setAdminUser();
        $this->hippotrack = $this->create_test_hippotrack($this->course, settings_provider::USE_SEB_CONFIG_MANUALLY);

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $reflection = new \ReflectionClass('hippotrackaccess_seb');
        $method = $reflection->getMethod('get_action_buttons');
        $method->setAccessible(true);

        set_config('showseblinks', 'seb,http', 'hippotrackaccess_seb');
        $this->assertStringContainsString($this->get_seb_launch_link(), $method->invoke($this->make_rule()));
        $this->assertStringContainsString($this->get_seb_config_download_link(), $method->invoke($this->make_rule()));

        set_config('showseblinks', 'http', 'hippotrackaccess_seb');
        $this->assertStringNotContainsString($this->get_seb_launch_link(), $method->invoke($this->make_rule()));
        $this->assertStringContainsString($this->get_seb_config_download_link(), $method->invoke($this->make_rule()));

        set_config('showseblinks', 'seb', 'hippotrackaccess_seb');
        $this->assertStringContainsString($this->get_seb_launch_link(), $method->invoke($this->make_rule()));
        $this->assertStringNotContainsString($this->get_seb_config_download_link(), $method->invoke($this->make_rule()));

        set_config('showseblinks', '', 'hippotrackaccess_seb');
        $this->assertStringNotContainsString($this->get_seb_launch_link(), $method->invoke($this->make_rule()));
        $this->assertStringNotContainsString($this->get_seb_config_download_link(), $method->invoke($this->make_rule()));
    }

    /**
     * Test get_quit_button. If attempt count is greater than 0
     */
    public function test_get_quit_button() {
        $this->setAdminUser();
        $this->hippotrack = $this->create_test_hippotrack($this->course, settings_provider::USE_SEB_CLIENT_CONFIG);
        $this->hippotrack->seb_linkquitseb = "http://test.quit.link";

        $user = $this->getDataGenerator()->create_user();
        $this->attempt_hippotrack($this->hippotrack, $user);
        $this->setUser($user);

        // Set-up the button to be called.
        $reflection = new \ReflectionClass('hippotrackaccess_seb');
        $method = $reflection->getMethod('get_quit_button');
        $method->setAccessible(true);

        $button = $method->invoke($this->make_rule());
        $this->assertStringContainsString("http://test.quit.link", $button);
    }

    /**
     * Test description, checks for a valid SEB session and attempt count .
     */
    public function test_description() {
        $this->setAdminUser();
        $this->hippotrack = $this->create_test_hippotrack($this->course, settings_provider::USE_SEB_CLIENT_CONFIG);

        $this->hippotrack->seb_linkquitseb = "http://test.quit.link";

        // Set up basic dummy request.
        $_SERVER['HTTP_USER_AGENT'] = 'SEB_TEST_SITE';

        $user = $this->getDataGenerator()->create_user();
        $this->attempt_hippotrack($this->hippotrack, $user);

        $description = $this->make_rule()->description();
        $this->assertCount(2, $description);
        $this->assertEquals($description[0], get_string('sebrequired', 'hippotrackaccess_seb'));
        $this->assertEquals($description[1], '');

        // Set the user as display_quit_button() uses the global $USER.
        $this->setUser($user);
        $description = $this->make_rule()->description();
        $this->assertCount(2, $description);
        $this->assertEquals($description[0], get_string('sebrequired', 'hippotrackaccess_seb'));

        // The button is contained in the description when a hippotrack attempt is finished.
        $this->assertStringContainsString("http://test.quit.link", $description[1]);
    }

    /**
     * Test description displays download SEB config button when required.
     */
    public function test_description_shows_download_config_link_when_required() {
        $this->setAdminUser();
        $this->hippotrack = $this->create_test_hippotrack($this->course, settings_provider::USE_SEB_CONFIG_MANUALLY);

        $hippotracksettings = hippotrack_settings::get_record(['hippotrackid' => $this->hippotrack->id]);

        $user = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();
        $context = \context_module::instance($this->hippotrack->cmid);
        assign_capability('hippotrackaccess/seb:bypassseb', CAP_ALLOW, $roleid, $context->id);

        $this->setUser($user);

        // Can see just basic description with standard perms.
        $description = $this->make_rule()->description();
        $this->assertCount(1, $description);
        $this->assertEquals($description[0], get_string('sebrequired', 'hippotrackaccess_seb'));

        // Can see download config link as have bypass SEB permissions.
        $this->getDataGenerator()->role_assign($roleid, $user->id, $context->id);
        $description = $this->make_rule()->description();
        $this->assertCount(3, $description);
        $this->assertEquals($description[0], get_string('sebrequired', 'hippotrackaccess_seb'));
        $this->assertStringContainsString($this->get_seb_config_download_link(), $description[1]);

        // Can't see download config link as usage method doesn't have SEB config to download.
        $hippotracksettings->set('requiresafeexambrowser', settings_provider::USE_SEB_CLIENT_CONFIG);
        $hippotracksettings->save();
        $description = $this->make_rule()->description();
        $this->assertCount(2, $description);
        $this->assertEquals($description[0], get_string('sebrequired', 'hippotrackaccess_seb'));
    }

    /**
     * Test block display before a hippotrack started.
     */
    public function test_blocks_display_before_attempt_started() {
        global $PAGE;

        $this->setAdminUser();
        $this->hippotrack = $this->create_test_hippotrack($this->course, settings_provider::USE_SEB_CLIENT_CONFIG);

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        // We will check if we show only fake blocks. Which means no other blocks on a page.
        $reflection = new \ReflectionClass('block_manager');
        $property = $reflection->getProperty('fakeblocksonly');
        $property->setAccessible(true);

        $this->assertFalse($property->getValue($PAGE->blocks));

        // Don't display blocks before start.
        set_config('displayblocksbeforestart', 0, 'hippotrackaccess_seb');
        $this->set_up_hippotrack_view_page();
        $this->make_rule()->prevent_access();
        $this->assertEquals('secure', $PAGE->pagelayout);
        $this->assertTrue($property->getValue($PAGE->blocks));

        // Display blocks before start.
        set_config('displayblocksbeforestart', 1, 'hippotrackaccess_seb');
        $this->set_up_hippotrack_view_page();
        $this->make_rule()->prevent_access();
        $this->assertEquals('secure', $PAGE->pagelayout);
        $this->assertFalse($property->getValue($PAGE->blocks));
    }

    /**
     * Test block display after a hippotrack completed.
     */
    public function test_blocks_display_after_attempt_finished() {
        global $PAGE;

        $this->setAdminUser();
        $this->hippotrack = $this->create_test_hippotrack($this->course, settings_provider::USE_SEB_CLIENT_CONFIG);

        // Finish the hippotrack.
        $user = $this->getDataGenerator()->create_user();
        $this->attempt_hippotrack($this->hippotrack, $user);
        $this->setUser($user);

        // We will check if we show only fake blocks. Which means no other blocks on a page.
        $reflection = new \ReflectionClass('block_manager');
        $property = $reflection->getProperty('fakeblocksonly');
        $property->setAccessible(true);

        $this->assertFalse($property->getValue($PAGE->blocks));

        // Don't display blocks after finish.
        set_config('displayblockswhenfinished', 0, 'hippotrackaccess_seb');
        $this->set_up_hippotrack_view_page();
        $this->make_rule()->prevent_access();
        $this->assertEquals('secure', $PAGE->pagelayout);
        $this->assertTrue($property->getValue($PAGE->blocks));

        // Display blocks after finish.
        set_config('displayblockswhenfinished', 1, 'hippotrackaccess_seb');
        $this->set_up_hippotrack_view_page();
        $this->make_rule()->prevent_access();
        $this->assertEquals('secure', $PAGE->pagelayout);
        $this->assertFalse($property->getValue($PAGE->blocks));
    }

    /**
     * Test cleanup when hippotrack is completed.
     */
    public function test_current_attempt_finished() {
        global $SESSION;
        $this->setAdminUser();

        $this->hippotrack = $this->create_test_hippotrack($this->course, settings_provider::USE_SEB_CONFIG_MANUALLY);
        $hippotracksettings = hippotrack_settings::get_record(['hippotrackid' => $this->hippotrack->id]);
        $hippotracksettings->save();
        // Set access for Moodle session.
        $SESSION->hippotrackaccess_seb_access = [$this->hippotrack->cmid => true];
        $this->make_rule()->current_attempt_finished();

        $this->assertTrue(empty($SESSION->hippotrackaccess_seb_access[$this->hippotrack->cmid]));
    }
}
