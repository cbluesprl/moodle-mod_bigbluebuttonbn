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

namespace mod_bigbluebuttonbn\task;

use advanced_testcase;
use core\message\message;
use core\task\adhoc_task;
use mod_bigbluebuttonbn\instance;
use stdClass;

/**
 * Class containing the scheduled task for lti module.
 *
 * @package   mod_bigbluebuttonbn
 * @copyright 2019 onwards, Blindside Networks Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \mod_bigbluebuttonbn\task\send_notification
 * @covers \mod_bigbluebuttonbn\task\send_notification
 * @covers \mod_bigbluebuttonbn\task\send_instance_update_notification
 */
class send_instance_update_notification_test extends advanced_testcase {
    /**
     * Test the sending of messages.
     *
     * @dataProvider update_type_provider
     * @param int $type
     */
    public function test_recipients(int $type): void {
        $this->resetAfterTest();
        $this->preventResetByRollback();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $instancedata = $generator->create_module('bigbluebuttonbn', [
            'course' => $course->id,
        ]);

        // Create some users in the course, and some not.
        $editingteacher = $generator->create_and_enrol($course, 'editingteacher');
        $teacher = $generator->create_and_enrol($course, 'teacher');
        $students = [
            $generator->create_and_enrol($course, 'student'),
            $generator->create_and_enrol($course, 'student'),
            $generator->create_and_enrol($course, 'student'),
            $generator->create_and_enrol($course, 'student'),
            $generator->create_and_enrol($course, 'student'),
        ];

        $recipients = array_map(function($user) {
            return $user->id;
        }, $students);
        $recipients[] = $editingteacher->id;
        $recipients[] = $teacher->id;

        $unrelateduser = $generator->create_user();

        $stub = $this->getMockBuilder(send_instance_update_notification::class)
            ->onlyMethods([])
            ->getMock();
        $stub->set_update_type($type);

        $instancedata = $generator->create_module('bigbluebuttonbn', [
            'course' => $course->id,
        ]);

        $stub->set_instance_id($instancedata->id);

        // Capture events.
        $sink = $this->redirectMessages();

        // Now execute.
        $stub->execute();

        // Check the events.
        $messages = $sink->get_messages();
        $this->assertCount(7, $messages);

        foreach ($messages as $message) {
            $this->assertNotFalse(array_search($message->useridto, $recipients));
            $this->assertNotEquals($unrelateduser->id, $message->useridto);
            $this->assertEquals($editingteacher->id, $message->useridfrom);
        }
    }

    /**
     * Provider to provide a list of update types.
     *
     * @return array
     */
    public function update_type_provider(): array {
        return [
            [send_instance_update_notification::TYPE_CREATED],
            [send_instance_update_notification::TYPE_UPDATED],
        ];
    }
}
