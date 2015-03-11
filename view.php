<?php
/**
 * Join a BigBlueButton room
 *
 * @package   mod_bigbluebuttonbn
 * @author    Fred Dixon  (ffdixon [at] blindsidenetworks [dt] com)
 * @author    Jesus Federico  (jesus [at] blindsidenetworks [dt] com)
 * @copyright 2010-2015 Blindside Networks Inc.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v2 or later
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/locallib.php');
require_once($CFG->libdir . '/completionlib.php');

$id = optional_param('id', 0, PARAM_INT); // course_module ID, or
$b  = optional_param('n', 0, PARAM_INT);  // bigbluebuttonbn instance ID
$group  = optional_param('group', 0, PARAM_INT);  // bigbluebuttonbn group ID

if ($id) {
    $cm = get_coursemodule_from_id('bigbluebuttonbn', $id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $bigbluebuttonbn = $DB->get_record('bigbluebuttonbn', array('id' => $cm->instance), '*', MUST_EXIST);
} elseif ($b) {
    $bigbluebuttonbn = $DB->get_record('bigbluebuttonbn', array('id' => $n), '*', MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $bigbluebuttonbn->course), '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('bigbluebuttonbn', $bigbluebuttonbn->id, $course->id, false, MUST_EXIST);
} else {
    print_error('You must specify a course_module ID or an instance ID');
}

require_login($course, true, $cm);

if ( $CFG->version < '2013111800' ) {
    //This is valid before v2.6
    $module = $DB->get_record('modules', array('name' => 'bigbluebuttonbn'));
    $module_version = $module->version;
    $context = get_context_instance(CONTEXT_MODULE, $cm->id);
} else {
    //This is valid after v2.6
    $module_version = get_config('mod_bigbluebuttonbn', 'version');
    $context = context_module::instance($cm->id);
}

if ( $CFG->version < '2014051200' ) {
    //This is valid before v2.7
    add_to_log($course->id, 'bigbluebuttonbn', 'view', 'view.php?id=$cm->id', $bigbluebuttonbn->name, $cm->id);
} else {
    //This is valid after v2.7
    $event = \mod_bigbluebuttonbn\event\bigbluebuttonbn_activity_viewed::create(
            array(
                    'context' => $context,
                    'objectid' => $bigbluebuttonbn->id
            )
    );
    $event->trigger();
}

//User data
$bbbsession['username'] = get_string('fullnamedisplay', 'moodle', $USER);
$bbbsession['userID'] = $USER->id;
$bbbsession['roles'] = get_user_roles($context, $USER->id, true);

if( $bigbluebuttonbn->participants == null || $bigbluebuttonbn->participants == "" ){
    //The room that is being used comes from a previous version
    $moderator = has_capability('mod/bigbluebuttonbn:moderate', $context);
} else {
    $moderator = bigbluebuttonbn_is_moderator($bbbsession['userID'], $bbbsession['roles'], $bigbluebuttonbn->participants);
}
$administrator = has_capability('moodle/category:manage', $context);

//Validates if the BigBlueButton server is running 
//BigBlueButton server data
$bbbsession['url'] = trim(trim($CFG->bigbluebuttonbn_server_url),'/').'/';
$bbbsession['shared_secret'] = trim($CFG->bigbluebuttonbn_shared_secret);

$serverVersion = bigbluebuttonbn_getServerVersion($bbbsession['url']); 
if ( !isset($serverVersion) ) { //Server is not working
    if ( $administrator )
        print_error( 'view_error_unable_join', 'bigbluebuttonbn', $CFG->wwwroot.'/admin/settings.php?section=modsettingbigbluebuttonbn' );
    else if ( $moderator )
        print_error( 'view_error_unable_join_teacher', 'bigbluebuttonbn', $CFG->wwwroot.'/course/view.php?id='.$bigbluebuttonbn->course );
    else
        print_error( 'view_error_unable_join_student', 'bigbluebuttonbn', $CFG->wwwroot.'/course/view.php?id='.$bigbluebuttonbn->course );
} else {
    $xml = bigbluebuttonbn_wrap_simplexml_load_file( bigbluebuttonbn_getMeetingsURL( $bbbsession['url'], $bbbsession['shared_secret'] ) );
    if ( !isset($xml) || !isset($xml->returncode) || $xml->returncode == 'FAILED' ){ // The shared secret is wrong
        if ( $administrator ) 
            print_error( 'view_error_unable_join', 'bigbluebuttonbn', $CFG->wwwroot.'/admin/settings.php?section=modsettingbigbluebuttonbn' );
        else if ( $moderator )
            print_error( 'view_error_unable_join_teacher', 'bigbluebuttonbn', $CFG->wwwroot.'/course/view.php?id='.$bigbluebuttonbn->course );
        else
            print_error( 'view_error_unable_join_student', 'bigbluebuttonbn', $CFG->wwwroot.'/course/view.php?id='.$bigbluebuttonbn->course );
    }
}

//// BigBlueButton Setup Starts

//Server data
$bbbsession['modPW'] = $bigbluebuttonbn->moderatorpass;
$bbbsession['viewerPW'] = $bigbluebuttonbn->viewerpass;
//User roles
$bbbsession['flag']['moderator'] = $moderator;
$bbbsession['textflag']['moderator'] = $moderator? 'true': 'false';
$bbbsession['flag']['administrator'] = $administrator;
$bbbsession['textflag']['administrator'] = $administrator? 'true': 'false';

//Database info related to the activity
$bbbsession['meetingname'] = $bigbluebuttonbn->name;
$bbbsession['welcome'] = $bigbluebuttonbn->welcome;
if( !isset($bbbsession['welcome']) || $bbbsession['welcome'] == '') {
    $bbbsession['welcome'] = get_string('mod_form_field_welcome_default', 'bigbluebuttonbn'); 
}
$bbbsession['presentation'] = bigbluebuttonbn_get_presentation_array($context, $bigbluebuttonbn);
error_log($bbbsession['presentation']['name']);
error_log($bbbsession['presentation']['url']);

$bbbsession['voicebridge'] = 70000 + $bigbluebuttonbn->voicebridge;
$bbbsession['flag']['newwindow'] = $bigbluebuttonbn->newwindow;
$bbbsession['flag']['wait'] = $bigbluebuttonbn->wait;
$bbbsession['flag']['record'] = $bigbluebuttonbn->record;
$bbbsession['textflag']['newwindow'] = $bigbluebuttonbn->newwindow? 'true':'false';
$bbbsession['textflag']['wait'] = $bigbluebuttonbn->wait? 'true': 'false';
$bbbsession['textflag']['record'] = $bigbluebuttonbn->record? 'true': 'false';
if( $bigbluebuttonbn->record )
    $bbbsession['welcome'] .= '<br><br>'.get_string('bbbrecordwarning', 'bigbluebuttonbn');

$bbbsession['openingtime'] = $bigbluebuttonbn->openingtime;
$bbbsession['closingtime'] = $bigbluebuttonbn->closingtime;
$bbbsession['durationtime'] = bigbluebuttonbn_get_duration($bigbluebuttonbn->openingtime, $bigbluebuttonbn->closingtime);
if( $bbbsession['durationtime'] > 0 )
    $bbbsession['welcome'] .= '<br><br>'.str_replace("%duration%", ''.$bbbsession['durationtime'], get_string('bbbdurationwarning', 'bigbluebuttonbn'));

//Additional info related to the course
$bbbsession['coursename'] = $course->fullname;
$bbbsession['courseid'] = $course->id;
$bbbsession['cm'] = $cm;

//Operation URLs
$bbbsession['courseURL'] = $CFG->wwwroot.'/course/view.php?id='.$bigbluebuttonbn->course;
$bbbsession['logoutURL'] = $CFG->wwwroot.'/mod/bigbluebuttonbn/view_end.php?id='.$id;

//Metadata
$bbbsession['origin'] = "Moodle";
$bbbsession['originVersion'] = $CFG->release;
$parsedUrl = parse_url($CFG->wwwroot);
$bbbsession['originServerName'] = $parsedUrl['host'];
$bbbsession['originServerUrl'] = $CFG->wwwroot;
$bbbsession['originServerCommonName'] = '';
$bbbsession['originTag'] = 'moodle-mod_bigbluebuttonbn ('.$module_version.')';
$bbbsession['context'] = $course->fullname;
$bbbsession['contextActivity'] = $bigbluebuttonbn->name;
$bbbsession['contextActivityDescription'] = "";
$bbbsession['contextActivityTagging'] = "";

//// BigBlueButton Setup Ends

// Mark viewed by user (if required)
$completion = new completion_info($course);
$completion->set_module_viewed($cm);

/// Print the page header
$PAGE->set_context($context);
$PAGE->set_url($CFG->wwwroot.'/mod/bigbluebuttonbn/view.php', array('id' => $cm->id));
$PAGE->set_title(format_string($bigbluebuttonbn->name));
$PAGE->set_heading($course->shortname);
$PAGE->set_cacheable(false);
if( $bbbsession['flag']['administrator'] || $bbbsession['flag']['moderator'] || !$bbbsession['flag']['wait'] ) {
    $PAGE->set_pagelayout('incourse');
} else {
    //Disable blocks for layouts which do include pre-post blocks
    $PAGE->blocks->show_only_fake_blocks();
}

// Validate if the user is in a role allowed to join
if ( !has_capability('mod/bigbluebuttonbn:join', $context) ) {
    echo $OUTPUT->header();
    if (isguestuser()) {
        echo $OUTPUT->confirm('<p>'.get_string('view_noguests', 'bigbluebuttonbn').'</p>'.get_string('liketologin'),
            get_login_url(), $CFG->wwwroot.'/course/view.php?id='.$course->id);
    } else { 
        echo $OUTPUT->confirm('<p>'.get_string('view_nojoin', 'bigbluebuttonbn').'</p>'.get_string('liketologin'),
            get_login_url(), $CFG->wwwroot.'/course/view.php?id='.$course->id);
    }

    echo $OUTPUT->footer();
    exit;
}

// Output starts here
echo $OUTPUT->header();

$bbbsession['bigbluebuttonbnid'] = $bigbluebuttonbn->id;
/// find out current groups mode
groups_print_activity_menu($cm, $CFG->wwwroot . '/mod/bigbluebuttonbn/view.php?id=' . $cm->id);
if (groups_get_activity_groupmode($cm) == 0) {  //No groups mode
    $bbbsession['meetingid'] = $bigbluebuttonbn->meetingid.'-'.$bbbsession['courseid'].'-'.$bbbsession['bigbluebuttonbnid'];
} else {                                        // Separate groups mode
    //If doesnt have group
    $bbbsession['group'] = (!$group)?groups_get_activity_group($cm): $group;
    $bbbsession['meetingid'] = $bigbluebuttonbn->meetingid.'-'.$bbbsession['courseid'].'-'.$bbbsession['bigbluebuttonbnid'].'['.$bbbsession['group'].']';
}

if( $bbbsession['flag']['administrator'] || $bbbsession['flag']['moderator'] )
    $bbbsession['joinURL'] = bigbluebuttonbn_getJoinURL($bbbsession['meetingid'], $bbbsession['username'], $bbbsession['modPW'], $bbbsession['shared_secret'], $bbbsession['url'], $bbbsession['userID']);
else
    $bbbsession['joinURL'] = bigbluebuttonbn_getJoinURL($bbbsession['meetingid'], $bbbsession['username'], $bbbsession['viewerPW'], $bbbsession['shared_secret'], $bbbsession['url'], $bbbsession['userID']);


$joining = false;
$bigbluebuttonbn_view = '';
if (!$bigbluebuttonbn->openingtime ) {
    if (!$bigbluebuttonbn->closingtime || time() <= $bigbluebuttonbn->closingtime){
        //GO JOINING
        $bigbluebuttonbn_view = 'join';
        $joining = bigbluebuttonbn_view_joining( $bbbsession, $context, $bigbluebuttonbn );
        
    } else {
        //CALLING AFTER
        $bigbluebuttonbn_view = 'after';
        echo $OUTPUT->heading(get_string('bbbfinished', 'bigbluebuttonbn'));
        echo $OUTPUT->box_start('generalbox boxaligncenter', 'dates');

        bigbluebuttonbn_view_after( $bbbsession );
        
        echo $OUTPUT->box_end();
        
    }
    
} else if ( time() < $bigbluebuttonbn->openingtime ){
    //CALLING BEFORE
    $bigbluebuttonbn_view = 'before';
    echo $OUTPUT->heading(get_string('bbbnotavailableyet', 'bigbluebuttonbn'));
    echo $OUTPUT->box_start('generalbox boxaligncenter', 'dates');

    bigbluebuttonbn_view_before( $bbbsession );
    
    echo $OUTPUT->box_end();
    
} else if (!$bigbluebuttonbn->closingtime || time() <= $bigbluebuttonbn->closingtime ) {
    //GO JOINING
    $bigbluebuttonbn_view = 'join';
    $joining = bigbluebuttonbn_view_joining( $bbbsession, $context, $bigbluebuttonbn );
        
} else {
    //CALLING AFTER
    $bigbluebuttonbn_view = 'after';
    echo $OUTPUT->heading(get_string('bbbfinished', 'bigbluebuttonbn'));
    echo $OUTPUT->box_start('generalbox boxaligncenter', 'dates');

    bigbluebuttonbn_view_after( $bbbsession );
        
    echo $OUTPUT->box_end();
    
}

//JavaScript variables
$jsVars = array(
        'newwindow' => $bbbsession['textflag']['newwindow'],
        'waitformoderator' => $bbbsession['textflag']['wait'],
        'isadministrator' => $bbbsession['textflag']['administrator'],
        'ismoderator' => $bbbsession['textflag']['moderator'],
        'meetingid' => $bbbsession['meetingid'],
        'joinurl' => $bbbsession['joinURL'],
        'joining' => ($joining? 'true':'false'),
        'bigbluebuttonbn_view' => $bigbluebuttonbn_view,
        'bigbluebuttonbnid' => $bbbsession['bigbluebuttonbnid'],
        'ping_interval' => ($CFG->bigbluebuttonbn_waitformoderator_ping_interval > 0? $CFG->bigbluebuttonbn_waitformoderator_ping_interval * 1000: 10000)
);

$jsmodule = array(
        'name'     => 'mod_bigbluebuttonbn',
        'fullpath' => '/mod/bigbluebuttonbn/module.js',
        'requires' => array('datasource-get', 'datasource-jsonschema', 'datasource-polling'),
);
$PAGE->requires->data_for_js('bigbluebuttonbn', $jsVars);
$PAGE->requires->js_init_call('M.mod_bigbluebuttonbn.init_view', array(), false, $jsmodule);

// Finish the page
echo $OUTPUT->footer();


function bigbluebuttonbn_view_joining( $bbbsession, $context, $bigbluebuttonbn ){
    global $CFG, $DB;

    $joining = false;

    // If user is administrator, moderator or if is viewer and no waiting is required
    if( $bbbsession['flag']['administrator'] || $bbbsession['flag']['moderator'] || !$bbbsession['flag']['wait'] ) {
        //
        // Join directly
        //
        $metadata = array("meta_origin" => $bbbsession['origin'],
                "meta_originVersion" => $bbbsession['originVersion'],
                "meta_originServerName" => $bbbsession['originServerName'],
                "meta_originServerCommonName" => $bbbsession['originServerCommonName'],
                "meta_originTag" => $bbbsession['originTag'],
                "meta_context" => $bbbsession['context'],
                "meta_recording_description" => $bbbsession['contextActivityDescription'],
                "meta_recording_tagging" => $bbbsession['contextActivityTagging']);
        $response = bigbluebuttonbn_getCreateMeetingArray(
                $bbbsession['meetingname'],
                $bbbsession['meetingid'],
                $bbbsession['welcome'],
                $bbbsession['modPW'],
                $bbbsession['viewerPW'],
                $bbbsession['shared_secret'],
                $bbbsession['url'],
                $bbbsession['logoutURL'],
                $bbbsession['textflag']['record'],
                $bbbsession['durationtime'],
                $bbbsession['voicebridge'],
                $metadata,
                $bbbsession['presentation']['name'],
                $bbbsession['presentation']['url']
        );

        if (!$response) {
            // If the server is unreachable, then prompts the user of the necessary action
            if ( $bbbsession['flag']['administrator'] ) {
                print_error( 'view_error_unable_join', 'bigbluebuttonbn', $CFG->wwwroot.'/admin/settings.php?section=modsettingbigbluebuttonbn' );
            } else if ( $bbbsession['flag']['moderator'] ) {
                print_error( 'view_error_unable_join_teacher', 'bigbluebuttonbn', $CFG->wwwroot.'/admin/settings.php?section=modsettingbigbluebuttonbn' );
            } else {
                print_error( 'view_error_unable_join_student', 'bigbluebuttonbn', $CFG->wwwroot.'/admin/settings.php?section=modsettingbigbluebuttonbn' );
            }

        } else if( $response['returncode'] == "FAILED" ) {
            // The meeting was not created
            $error_key = bigbluebuttonbn_get_error_key( $response['messageKey'], 'view_error_create' );
            if( !$error_key ) {
                print_error( $response['message'], 'bigbluebuttonbn' );
            } else {
                print_error( $error_key, 'bigbluebuttonbn' );
            }

        } else if ($response['hasBeenForciblyEnded'] == "true"){
            print_error( get_string( 'index_error_forciblyended', 'bigbluebuttonbn' ));

        } else { ///////////////Everything is ok /////////////////////
            /// Moodle event logger: Create an event for meeting created
            if ( $CFG->version < '2014051200' ) {
                //This is valid before v2.7
                add_to_log($bbbsession['courseid'], 'bigbluebuttonbn', 'meeting created', '', $bigbluebuttonbn->name, $bbbsession['cm']->id);
            } else {
                //This is valid after v2.7
                $event = \mod_bigbluebuttonbn\event\bigbluebuttonbn_meeting_created::create(
                        array(
                                'context' => $context,
                                'objectid' => $bigbluebuttonbn->id
                        )
                );
                $event->trigger();
            }

            /// Internal logger: Instert a record with the meeting created
            bigbluebuttonbn_log($bbbsession, 'Create');

            if ( groups_get_activity_groupmode($bbbsession['cm']) > 0 && count(groups_get_activity_allowed_groups($bbbsession['cm'])) > 1 ){
                print "&nbsp;&nbsp;".get_string('view_groups_selection', 'bigbluebuttonbn' )."&nbsp;&nbsp;<input type='button' onClick='M.mod_bigbluebuttonbn.joinURL()' value='".get_string('view_groups_selection_join', 'bigbluebuttonbn' )."'>";
            } else {
                $joining = true;

                if( $bbbsession['flag']['administrator'] || $bbbsession['flag']['moderator'] )
                    print "<br />".get_string('view_login_moderator', 'bigbluebuttonbn' )."<br /><br />";
                else
                    print "<br />".get_string('view_login_viewer', 'bigbluebuttonbn' )."<br /><br />";
                
                print "<center><img src='pix/loading.gif' /></center>";
            }

            /// Moodle event logger: Create an event for meeting joined
            if ( $CFG->version < '2014051200' ) {
                //This is valid before v2.7
                add_to_log($bbbsession['courseid'], 'bigbluebuttonbn', 'meeting joined', '', $bigbluebuttonbn->name, $bbbsession['cm']->id);
            } else {
                //This is valid after v2.7
                $event = \mod_bigbluebuttonbn\event\bigbluebuttonbn_meeting_joined::create(
                        array(
                                'context' => $context,
                                'objectid' => $bigbluebuttonbn->id
                        )
                );
                $event->trigger();
            }
        }
    } else {
        //    
        // "Viewer" && Waiting for moderator is required;
        //
        $joining = true;

        print "<div align='center'>";
        if( bigbluebuttonbn_wrap_simplexml_load_file(bigbluebuttonbn_getIsMeetingRunningURL( $bbbsession['meetingid'], $bbbsession['url'], $bbbsession['shared_secret'] )) == "true" ) {
            /// Since the meeting is already running, we just join the session
            print "<br />".get_string('view_login_viewer', 'bigbluebuttonbn' )."<br /><br />";
            print "<center><img src='pix/loading.gif' /></center>";
            /// Moodle event logger: Create an event for meeting joined
            if ( $CFG->version < '2014051200' ) {
                //This is valid before v2.7
                add_to_log($bbbsession['courseid'], 'bigbluebuttonbn', 'meeting joined', '', $bigbluebuttonbn->name, $bbbsession['cm']->id);
            } else {
                //This is valid after v2.7
                $event = \mod_bigbluebuttonbn\event\bigbluebuttonbn_meeting_joined::create(
                        array(
                                'context' => $context,
                                'objectid' => $bigbluebuttonbn->id
                        )
                );
                $event->trigger();
            }
        } else {
            /// Since the meeting is not running, the spining wheel is shown
            print "<br />".get_string('view_wait', 'bigbluebuttonbn' )."<br /><br />";
            print '<center><img src="pix/polling.gif"></center>';
        }
        print "</div>";
    }
    return $joining;
}

function bigbluebuttonbn_view_before( $bbbsession ){

    echo '<table>';
    if ($bbbsession['openingtime']) {
        echo '<tr><td class="c0">'.get_string('mod_form_field_availabledate','bigbluebuttonbn').':</td>';
        echo '    <td class="c1">'.userdate($bbbsession['openingtime']).'</td></tr>';
    }
    if ($bbbsession['closingtime']) {
        echo '<tr><td class="c0">'.get_string('mod_form_field_duedate','bigbluebuttonbn').':</td>';
        echo '    <td class="c1">'.userdate($bbbsession['closingtime']).'</td></tr>';
    }
    echo '</table>';
}


function bigbluebuttonbn_view_after( $bbbsession ){

    $recordingsArray = bigbluebuttonbn_getRecordingsArray($bbbsession['meetingid'], $bbbsession['url'], $bbbsession['shared_secret']);

    if ( !isset($recordingsArray) || array_key_exists('messageKey', $recordingsArray)) {   // There are no recordings for this meeting
        if ( $bbbsession['flag']['record'] )
            print_string('bbbnorecordings', 'bigbluebuttonbn');
    } else {                                                                                // Actually, there are recordings for this meeting
        echo '    <center>'."\n";
        echo '      <table cellpadding="0" cellspacing="0" border="0" class="display" id="example">'."\n";
        echo '        <thead>'."\n";
        echo '        </thead>'."\n";
        echo '        <tbody>'."\n";
        echo '        </tbody>'."\n";
        echo '        <tfoot>'."\n";
        echo '        </tfoot>'."\n";
        echo '      </table>'."\n";
        echo '    </center>'."\n";
    }
}


?>
