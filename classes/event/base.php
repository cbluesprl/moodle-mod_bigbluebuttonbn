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

namespace mod_bigbluebuttonbn\event;

/**
 * The mod_bigbluebuttonbn abstract base event class. Most mod_bigbluebuttonbn events can extend this class.
 *
 * @package   mod_bigbluebuttonbn
 * @copyright 2010 onwards, Blindside Networks Inc
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class base extends \core\event\base {

    /** @var $bigbluebuttonbn */
    protected $bigbluebuttonbn;

    /**
     * Description.
     *
     * @var string
     */
    protected $description;

    /**
     * Object Id Mapping.
     *
     * @var array
     */
    protected static $objectidmapping = array('db' => 'bigbluebuttonbn', 'restore' => 'bigbluebuttonbn');

    /**
     * Legacy log data.
     *
     * @var array
     */
    protected $legacylogdata;

    /**
     * Init method.
     * @param string $crud
     * @param integer $edulevel
     */
    protected function init($crud = 'r', $edulevel = self::LEVEL_PARTICIPATING) {
        $this->data['crud'] = $crud;
        $this->data['edulevel'] = $edulevel;
        $this->data['objecttable'] = 'bigbluebuttonbn';
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        $vars = array(
            'userid' => $this->userid,
            'courseid' => $this->courseid,
            'objectid' => $this->objectid,
            'contextinstanceid' => $this->contextinstanceid,
            'other' => $this->other
          );
        $string = $this->description;
        foreach ($vars as $key => $value) {
            $string = str_replace("##" . $key, $value, $string);
        }
        return $string;
    }

    /**
     * Returns relevant URL.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/mod/bigbluebuttonbn/view.php', array('id' => $this->contextinstanceid));
    }

    /**
     * Sets the legacy event log data.
     *
     * @param string $action The current action
     * @param string $info A detailed description of the change. But no more than 255 characters.
     * @param string $url The url to the assign module instance.
     */
    public function set_legacy_logdata($action = '', $info = '', $url = '') {
        $fullurl = 'view.php?id=' . $this->contextinstanceid;
        if ($url != '') {
            $fullurl .= '&' . $url;
        }

        $this->legacylogdata = array($this->courseid, 'bigbluebuttonbn', $action, $fullurl, $info, $this->contextinstanceid);
    }

    /**
     * Return legacy data for add_to_log().
     *
     * @return array
     */
    protected function get_legacy_logdata() {
        if (isset($this->legacylogdata)) {
            return $this->legacylogdata;
        }

        return null;
    }

    /**
     * Custom validation.
     *
     * @throws \coding_exception
     */
    protected function validate_data() {
        parent::validate_data();

        if ($this->contextlevel != CONTEXT_MODULE) {
            throw new \coding_exception('Context level must be CONTEXT_MODULE.');
        }
    }
}
