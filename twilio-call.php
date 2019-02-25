#!/usr/bin/php
<?php
// **********************************************
// *** External file reading ***
// **********************************************
// Twilio PHP  Library
require_once dirname ( __FILE__ ) . '/lib/twilio-php-latest/Services/Twilio.php';
// Logger クラス
require_once dirname ( __FILE__ ) . '/Logger.php';
// configファイル
require_once dirname ( __FILE__ ) . '/config.php';

// **********************************************
// *** Constant ***
// **********************************************
define ( 'EXIT_OK', 0 );
define ( 'EXIT_INFO', 1 );
define ( 'EXIT_ERROR', -1 );

// **********************************************
// *** Set value ***
// **********************************************
$check_limit = 60; // maximum number of status checks
$check_interval = 1; // status check interval (sec)

// **********************************************
// *** Maine ***
// **********************************************
$log = new Logger ( $LOG_DIR . '/' . basename($argv[0], '.php') . '.log', $DEBUG_FLG );
$log->info ( 'Start' );

// Argument check
if ($argc != 4) {
	$log->error ( "Invalid argument ARGC:$argc ARGV:" . var_export ( $argv, true ) );
	$log->info ( 'End' );
	exit ( EXIT_ERROR );
}

$log->debug ( "ARGC:$argc ARGV:" . var_export ( $argv, true ) );

// Confirm phone number
if (substr ( $argv [1], 0, 1 ) === '0') {
	$to_number = substr_replace ( $argv [1], '+81', 0, 1 ); // Replace leading 0 with + 81
} else if (substr ( $argv [1], 0, 1 ) === '+') {
	$to_number = $argv [1];
} else {
	$log->error ( "The destination phone number is invalid, To:$argv[1]" );
	$log->error ( 'End' );
	exit ( EXIT_ERROR );
}

// Extract eventid
if (preg_match ( "/eventid:(\d+)/i", $argv [3], $eventid ) !== 1) {
	$log->error ( "The eventid is not included ARGC:$argc ARGV:" . var_export ( $argv, true ) );
	$log->error ( 'End' );
	exit ( EXIT_ERROR );
}

// message extraction
if (preg_match ( "/message:(.*)/is", $argv [3], $message ) !== 1) {
	$log->error ( "message: is not included ARGC:$argc ARGV:" . var_export ( $argv, true ) );
	exit ( EXIT_ERROR );
}

// TwiML URL
$URL = $SCRIPT_URL . '?cmd=notice&eventid=' . $eventid [1] . '&name=' . substr_replace ( $to_number, '0', 0, 3 ) . '&message=' . urlencode ( $message [1] );
$log->debug ( "URL:$URL" );

// Client generated
$client = new Services_Twilio ( $ACCOUNT_SID, $AUTH_TOKEN, $TWILIO_API_VERSION );

// Call
$call = $client->account->calls->create ( $TWILIO_NUMBER, $to_number, $URL, array (
		'Timeout' => $CALL_TIME
) );

$log->info ( "CallStart From:$TWILIO_NUMBER To:$to_number" );
$log->debug ( $call );

// Confirm Status
$check_count = 0;
$status_check = true;
while ( $status_check == true or $check_count > $check_limit ) {
	switch ($client->account->calls->get ( $call->sid )->status) {
		case "queued" : //  Call waiting for outgoing call
		case "ringing" : // calling
		case "in-progress" : // the partner is answering, calling
			$log->info ( "CallCheck From:$TWILIO_NUMBER To:$to_number Status:" . $client->account->calls->get ( $call->sid )->status );
			$check_count ++;
			sleep ( $check_interval );
			break;
		case "canceled" : // Call was canceled during queued or ringing
		case "completed" : // the partner responds and the call ends normally
		case "busy" : // receive a busy signal from the other party
		case "failed" : //  The call could not be connected. Normally, the dialed number does not exist
		case "no-answer" : // The partner did not respond and the call ended
			$status_check = false;
			$log->info ( "CallEnd   From:$TWILIO_NUMBER To:$to_number Status:" . $client->account->calls->get ( $call->sid )->status );
			break;
		default :
			$log->info ( "CallCheck From:$TWILIO_NUMBER To:$to_number Status:" . $client->account->calls->get ( $call->sid )->status );
			break;
	}
}

$log->info ( 'End' );
exit ( EXIT_OK );

?>
