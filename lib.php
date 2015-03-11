<?php
/**
 * Library calls for Moodle and BigBlueButton.
 * 
 * @package   mod_bigbluebuttonbn
 * @author    Fred Dixon  (ffdixon [at] blindsidenetworks [dt] com)
 * @author    Jesus Federico  (jesus [at] blindsidenetworks [dt] com)
 * @copyright 2010-2015 Blindside Networks Inc.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v2 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot.'/calendar/lib.php');

function bigbluebuttonbn_supports($feature) {
    switch($feature) {
        case FEATURE_IDNUMBER:                return true;
        case FEATURE_GROUPS:                  return true;
        case FEATURE_GROUPINGS:               return true;
        case FEATURE_GROUPMEMBERSONLY:        return true;
        case FEATURE_MOD_INTRO:               return false;
        case FEATURE_COMPLETION_TRACKS_VIEWS: return true;
        case FEATURE_GRADE_HAS_GRADE:         return false;
        case FEATURE_GRADE_OUTCOMES:          return false;
        // case FEATURE_MOD_ARCHETYPE:           return MOD_ARCHETYPE_RESOURCE;
        case FEATURE_BACKUP_MOODLE2:          return true;

        default: return null;
    }
}

/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will create a new instance and return the id number
 * of the new instance.
 *
 * @param object $bigbluebuttonbn An object from the form in mod_form.php
 * @return int The id of the newly inserted bigbluebuttonbn record
 */
function bigbluebuttonbn_add_instance($data, $mform) {
    global $DB, $CFG;

    $cmid = $data->coursemodule;
    $draftitemid = $data->presentation;
    if ( $CFG->version < '2013111800' ) {
        //This is valid before v2.6
        $context = get_context_instance(CONTEXT_MODULE, $cmid);
    } else {
        //This is valid after v2.6
        $context = context_module::instance($cmid);
    }

    bigbluebuttonbn_process_pre_save($data);

    unset($data->presentation);
    $bigbluebuttonbn_id = $DB->insert_record('bigbluebuttonbn', $data);
    $data->id = $bigbluebuttonbn_id;

    $bigbluebuttonbn = $DB->get_record('bigbluebuttonbn', array('id'=>$bigbluebuttonbn_id), '*', MUST_EXIST);

    bigbluebuttonbn_update_media_file($bigbluebuttonbn_id, $context, $draftitemid);

    bigbluebuttonbn_process_post_save($data);

    return $bigbluebuttonbn->id;
}

/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will update an existing instance with new data.
 *
 * @param object $bigbluebuttonbn An object from the form in mod_form.php
 * @return boolean Success/Fail
 */
function bigbluebuttonbn_update_instance($data, $mform) {
    global $DB, $CFG;

    $data->id = $data->instance;
    $cmid = $data->coursemodule;
    $draftitemid = $data->presentation;
    if ( $CFG->version < '2013111800' ) {
        //This is valid before v2.6
        $context = get_context_instance(CONTEXT_MODULE, $cmid);
    } else {
        //This is valid after v2.6
        $context = context_module::instance($cmid);
    }
    
    bigbluebuttonbn_process_pre_save($data);

    unset($data->presentation);
    $DB->update_record("bigbluebuttonbn", $data);

    bigbluebuttonbn_update_media_file($data->id, $context, $draftitemid);

    bigbluebuttonbn_process_post_save($data);

    return true;
}

function resource_set_presentation($data) {
    $displayoptions = array();
    if ($data->presentation == RESOURCELIB_DISPLAY_POPUP) {
        $displayoptions['popupwidth']  = $data->popupwidth;
        $displayoptions['popupheight'] = $data->popupheight;
    }
    if (in_array($data->display, array(RESOURCELIB_DISPLAY_AUTO, RESOURCELIB_DISPLAY_EMBED, RESOURCELIB_DISPLAY_FRAME))) {
        $displayoptions['printintro']   = (int)!empty($data->printintro);
    }
    if (!empty($data->showsize)) {
        $displayoptions['showsize'] = 1;
    }
    if (!empty($data->showtype)) {
        $displayoptions['showtype'] = 1;
    }
    $data->displayoptions = serialize($displayoptions);
}

/**
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * and any data that depends on it.
 *
 * @param int $id Id of the module instance
 * @return boolean Success/Failure
 */
function bigbluebuttonbn_delete_instance($id) {
    global $CFG, $DB;

    if (! $bigbluebuttonbn = $DB->get_record('bigbluebuttonbn', array('id' => $id))) {
        return false;
    }

    $result = true;

    //
    // End the session associated with this instance (if it's running)
    //
    $meetingID = $bigbluebuttonbn->meetingid.'-'.$bigbluebuttonbn->course.'-'.$bigbluebuttonbn->id;
    
    $modPW = $bigbluebuttonbn->moderatorpass;
    $url = trim(trim($CFG->bigbluebuttonbn_server_url),'/').'/';
    $shared_secret = trim($CFG->bigbluebuttonbn_shared_secret);

    //if( bigbluebuttonbn_isMeetingRunning($meetingID, $url, $shared_secret) )
    //    $getArray = bigbluebuttonbn_doEndMeeting( $meetingID, $modPW, $url, $shared_secret );

    if (! $DB->delete_records('bigbluebuttonbn', array('id' => $bigbluebuttonbn->id))) {
        $result = false;
    }

    if (! $DB->delete_records('event', array('modulename'=>'bigbluebuttonbn', 'instance'=>$bigbluebuttonbn->id))) {
        $result = false;
    }

    return $result;
}

/**
 * Return a small object with summary information about what a
 * user has done with a given particular instance of this module
 * Used for user activity reports.
 * $return->time = the time they did it
 * $return->info = a short text description
 *
 * @return null
 */
function bigbluebuttonbn_user_outline($course, $user, $mod, $bigbluebuttonbn) {
    return true;
}

/**
 * Print a detailed representation of what a user has done with
 * a given particular instance of this module, for user activity reports.
 *
 * @return boolean
 */
function bigbluebuttonbn_user_complete($course, $user, $mod, $bigbluebuttonbn) {
    return true;
}

/**
 * Given a course and a time, this module should find recent activity
 * that has occurred in bigbluebuttonbn activities and print it out.
 * Return true if there was output, or false is there was none.
 *
 * @return boolean
 */
function bigbluebuttonbn_print_recent_activity($course, $isteacher, $timestart) {
    return false;  //  True if anything was printed, otherwise false
}

/**
 * Returns all activity in bigbluebuttonbn since a given time
 *
 * @param array $activities sequentially indexed array of objects
 * @param int $index
 * @param int $timestart
 * @param int $courseid
 * @param int $cmid
 * @param int $userid defaults to 0
 * @param int $groupid defaults to 0
 * @return void adds items into $activities and increases $index
 */
function bigbluebuttonbn_get_recent_mod_activity(&$activities, &$index, $timestart, $courseid, $cmid, $userid=0, $groupid=0) {
}

/**
 * Prints single activity item prepared by {@see recordingsbn_get_recent_mod_activity()}

 * @return void
 */
function bigbluebuttonbn_print_recent_mod_activity($activity, $courseid, $detail, $modnames, $viewfullnames) {
}

/**
 * Function to be run periodically according to the moodle cron
 * This function searches for things that need to be done, such
 * as sending out mail, toggling flags etc ...
 *
 * @return boolean
 **/
function bigbluebuttonbn_cron () {
    return true;
}

/**
 * Must return an array of user records (all data) who are participants
 * for a given instance of bigbluebuttonbn. Must include every user involved
 * in the instance, independient of his role (student, teacher, admin...)
 * See other modules as example.
 *
 * @param int $bigbluebuttonbnid ID of an instance of this module
 * @return mixed boolean/array of students
 */
function bigbluebuttonbn_get_participants($bigbluebuttonbnid) {
    return false;
}

/**
 * Returns all other caps used in module
 * @return array
 */
function bigbluebuttonbn_get_extra_capabilities() {
    return array('moodle/site:accessallgroups');
}

////////////////////////////////////////////////////////////////////////////////
// Gradebook API                                                              //
////////////////////////////////////////////////////////////////////////////////

/**
 * This function returns if a scale is being used by one bigbluebuttonbn
 * if it has support for grading and scales. Commented code should be
 * modified if necessary. See forum, glossary or journal modules
 * as reference.
 *
 * @param int $bigbluebuttonbnid ID of an instance of this module
 * @return mixed
 */
function bigbluebuttonbn_scale_used($bigbluebuttonbnid, $scaleid) {
    $return = false;

    return $return;
}

/**
 * Checks if scale is being used by any instance of bigbluebuttonbn.
 * This function was added in 1.9
 *
 * This is used to find out if scale used anywhere
 * @param $scaleid int
 * @return boolean True if the scale is used by any bigbluebuttonbn
 */
function bigbluebuttonbn_scale_used_anywhere($scaleid) {
    $return = false;

    return $return;
}

/**
 * This function is used by the reset_course_userdata function in moodlelib.
 * @param $data the data submitted from the reset course.
 * @return array status array
 */
function bigbluebuttonbn_reset_userdata($data) {
    return array();
}

/**
 * List of view style log actions
 * @return array
 */
function bigbluebuttonbn_get_view_actions() {
    return array('view', 'view all');
}

/**
 * List of update style log actions
 * @return array
 */
function bigbluebuttonbn_get_post_actions() {
    return array('update', 'add');
}

/**
 * Given a course_module object, this function returns any
 * "extra" information that may be needed when printing
 * this activity in a course listing.
 * See get_array_of_activities() in course/lib.php
 *
 * @global object
 * @param object $coursemodule
 * @return object|null
 */
function bigbluebuttonbn_get_coursemodule_info($coursemodule) {
    global $CFG, $DB;

    if (! $bigbluebuttonbn = $DB->get_record('bigbluebuttonbn', array('id'=>$coursemodule->instance), 'id, name, newwindow')) {
        return NULL;
    }

    $info = new cached_cm_info();
    $info->name = $bigbluebuttonbn->name;

    if ( $bigbluebuttonbn->newwindow == 1 ){
        $fullurl = "$CFG->wwwroot/mod/bigbluebuttonbn/view.php?id=$coursemodule->id&amp;redirect=1";
        $info->onclick = "window.open('$fullurl'); return false;";
    }

    return $info;
}

/**
 * Runs any processes that must run before
 * a bigbluebuttonbn insert/update
 *
 * @global object
 * @param object $bigbluebuttonbn BigBlueButtonBN form data
 * @return void
 **/
function bigbluebuttonbn_process_pre_save(&$bigbluebuttonbn) {
    global $DB;

    if ( !isset($bigbluebuttonbn->timecreated) || !$bigbluebuttonbn->timecreated ) {
        $bigbluebuttonbn->timecreated = time();

        $bigbluebuttonbn->moderatorpass = bigbluebuttonbn_rand_string();
        $bigbluebuttonbn->viewerpass = bigbluebuttonbn_rand_string();
        $bigbluebuttonbn->meetingid = bigbluebuttonbn_rand_string();
    } else {
        $bigbluebuttonbn->timemodified = time();
    }

    if (! isset($bigbluebuttonbn->newwindow))
        $bigbluebuttonbn->newwindow = 0;
    if (! isset($bigbluebuttonbn->wait))
        $bigbluebuttonbn->wait = 0;
    if (! isset($bigbluebuttonbn->record))
        $bigbluebuttonbn->record = 0;

}

/**
 * Runs any processes that must be run
 * after a bigbluebuttonbn insert/update
 *
 * @global object
 * @param object $bigbluebuttonbn BigBlueButtonBN form data
 * @return void
 **/
function bigbluebuttonbn_process_post_save(&$bigbluebuttonbn) {
    global $DB;

    if ( isset($bigbluebuttonbn->openingtime) && $bigbluebuttonbn->openingtime ){
        $event = new stdClass();
        $event->name        = $bigbluebuttonbn->name;
        $event->courseid    = $bigbluebuttonbn->course;
        $event->groupid     = 0;
        $event->userid      = 0;
        $event->modulename  = 'bigbluebuttonbn';
        $event->instance    = $bigbluebuttonbn->id;
        $event->timestart   = $bigbluebuttonbn->openingtime;

        if ( $bigbluebuttonbn->closingtime ){
            $event->durationtime = $bigbluebuttonbn->closingtime - $bigbluebuttonbn->openingtime;
        } else {
            $event->durationtime = 0;
        }

        if ( $event->id = $DB->get_field('event', 'id', array('modulename'=>'bigbluebuttonbn', 'instance'=>$bigbluebuttonbn->id)) ) {
            $calendarevent = calendar_event::load($event->id);
            $calendarevent->update($event);
        } else {
            calendar_event::create($event);
        }

    } else {
        $DB->delete_records('event', array('modulename'=>'bigbluebuttonbn', 'instance'=>$bigbluebuttonbn->id));
    }

}

/**
 * Update the bigbluebuttonbn activity to include any file
 * that was uploaded, or if there is none, set the
 * presentation field to blank.
 *
 * @param int $bigbluebuttonbn_id the bigbluebuttonbn id
 * @param stdClass $context the context
 * @param int $draftitemid the draft item
 */
function bigbluebuttonbn_update_media_file($bigbluebuttonbn_id, $context, $draftitemid) {
    global $DB;

    // Set the filestorage object.
    $fs = get_file_storage();
    // Save the file if it exists that is currently in the draft area.
    file_save_draft_area_files($draftitemid, $context->id, 'mod_bigbluebuttonbn', 'presentation', 0);
    // Get the file if it exists.
    $files = $fs->get_area_files($context->id, 'mod_bigbluebuttonbn', 'presentation', 0, 'itemid, filepath, filename', false);
    // Check that there is a file to process.
    if (count($files) == 1) {
        // Get the first (and only) file.
        $file = reset($files);
        // Set the presentation column in the bigbluebuttonbn table.
        $DB->set_field('bigbluebuttonbn', 'presentation', '/' . $file->get_filename(), array('id' => $bigbluebuttonbn_id));
    } else {
        // Set the presentation column in the bigbluebuttonbn table.
        $DB->set_field('bigbluebuttonbn', 'presentation', '', array('id' => $bigbluebuttonbn_id));
    }

}

/**
 * Serves the bigbluebuttonbn attachments. Implements needed access control ;-)
 *
 * @package mod_bigbluebuttonbn
 * @category files
 * @param stdClass $course course object
 * @param stdClass $cm course module object
 * @param stdClass $context context object
 * @param string $filearea file area
 * @param array $args extra arguments
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 * @return bool false if file not found, does not return if found - justsend the file
 */
function bigbluebuttonbn_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options=array()) {
    global $CFG, $DB;

    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }

    $fileareas = bigbluebuttonbn_get_file_areas();
    if (!array_key_exists($filearea, $fileareas)) {
        return false;
    }

    if (!$bigbluebuttonbn = $DB->get_record('bigbluebuttonbn', array('id'=>$cm->instance))) {
        return false;
    }

    require_course_login($course, true, $cm);

    if ($filearea === 'presentation') {
        $fullpath = "/$context->id/mod_bigbluebuttonbn/$filearea/0/".implode('/', $args);
    } else {
        return false;
    }

    $fs = get_file_storage();
    if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
        return false;
    }

    // finally send the file
    send_stored_file($file, 0, 0, $forcedownload, $options); // download MUST be forced - security!
}

/**
 * Returns an array of file areas
 *
 * @package  mod_bigbluebuttonbn
 * @category files
 * @return array a list of available file areas
 */
function bigbluebuttonbn_get_file_areas() {
    $areas = array();
    $areas['presentation'] = get_string('mod_form_block_presentation', 'bigbluebuttonbn');
    return $areas;
}

function bigbluebuttonbn_pluginfile2($course, $cm, $context, $filearea, $args, $forcedownload, array $options=array()) {
    // Check the contextlevel is as expected - if your plugin is a block, this becomes CONTEXT_BLOCK, etc.
    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }

    // Make sure the filearea is one of those used by the plugin.
    if ($filearea !== 'presentation') {
        return false;
    }
    
    // Make sure the user is logged in and has access to the module (plugins that are not course modules should leave out the 'cm' part).
    require_login($course, true, $cm);

    // Check the relevant capabilities - these may vary depending on the filearea being accessed.
    if (!has_capability('mod/bigbluebuttonbn:view', $context)) {
        return false;
    }

    // Leave this line out if you set the itemid to null in make_pluginfile_url (set $itemid to 0 instead).
    $itemid = array_shift($args); // The first item in the $args array.

    // Use the itemid to retrieve any relevant data records and perform any security checks to see if the
    // user really does have access to the file in question.

    // Extract the filename / filepath from the $args array.
    $filename = array_pop($args); // The last item in the $args array.
    if (!$args) {
        $filepath = '/'; // $args is empty => the path is '/'
    } else {
        $filepath = '/'.implode('/', $args).'/'; // $args contains elements of the filepath
    }

    // Retrieve the file from the Files API.
    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'mod_bigbluebuttonbn', $filearea, $itemid, $filepath, $filename);
    if (!$file) {
        return false; // The file does not exist.
    }

    // We can now send the file back to the browser - in this case with a cache lifetime of 1 day and no filtering.
    // From Moodle 2.3, use send_stored_file instead.
    send_file($file, 86400, 0, $forcedownload, $options);
    error_log("THERE");
}