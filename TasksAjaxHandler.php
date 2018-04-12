<?php
header('Content-Type: text/html; charset=utf-8');
require('config.php'); //innehåller API-KEY + error reporting

if(!empty($_POST['getjobinfo'])) {
	if($_POST['getjobinfo'] == 1) {
		$response = get_job_info($_POST['getjobinfourl']);
		if(checkifAlmaerror($response) == "Success") {
			//returnera almas XML till ajaxanropet
			echo $response;
		} else {
			echo $response;
		}
	}
	exit;
}

if(!empty($_POST['getjoblist'])) {
	if($_POST['getjoblist'] == 1) {
		$response = get_job_list($_POST['getjobcategory'],$_POST['getjoblimit'],$_POST['getjoboffset']);
		if(checkifAlmaerror($response) == "Success") {
			//returnera almas XML till ajaxanropet
			echo $response;
		} else {
			echo $response;
		}
	}
	exit;
}

if(!empty($_POST['getsetlist'])) {
	if($_POST['getsetlist'] == 1) {
		$response = get_set_list($_POST['getset_content_type'],$_POST['getset_q'],$_POST['getset_limit'],$_POST['getset_offset']);
		if(checkifAlmaerror($response) == "Success") {
			//returnera almas XML till ajaxanropet
			echo $response;
		} else {
			echo $response;
		}
	}
	exit;
}

if(!empty($_POST['get_tasks'])) {
	if($_POST['task_id'] != "") {
		$response = get_tasks($_POST['task_id'],$mysqli);
		echo $response;
	}
	exit;
}


//Angular http-get
if(!empty($_GET['get_tasks_angular'])) {
	$response = get_tasks_angular($mysqli);
	echo $response;
	exit;
}

if(!empty($_GET['get_task_angular'])) {
	$response = get_task_angular($mysqli, $_GET['task_id']);
	echo $response;
	exit;
}

if(!empty($_GET['get_systemlog_angular'])) {
	$response = get_systemlog_angular($mysqli, $_GET['task_id']);
	echo $response;
	exit;
}

if(!empty($_GET['get_taskoptions_angular'])) {
	$response = get_taskoptions_angular($_GET['task_id'],$mysqli);
	echo $response;
	exit;
}

if(!empty($_GET['get_jobtypes_angular'])) {
	$response = get_jobtypes_angular($mysqli);
	echo $response;
	exit;
}

if(!empty($_GET['get_intervals_angular'])) {
	$response = get_intervals_angular($mysqli);
	echo $response;
	exit;
}

if(!empty($_GET['get_weekdays_angular'])) {
	$response = get_weekdays_angular($mysqli);
	echo $response;
	exit;
}

if(!empty($_GET['get_formats_angular'])) {
	$response = get_formats_angular($mysqli);
	echo $response;
	exit;
}

//Angular http.post och parametrar i jsonformat
$data = json_decode(file_get_contents("php://input"));
$cmd = mysqli_real_escape_string($mysqli, $data->cmd);


if(!empty($cmd)) {
	if ($cmd == "insert_task_angular") {
		$name = mysqli_real_escape_string($mysqli, $data->name);
		
		$response = insert_task_angular($mysqli, $name);
		echo $response;
		exit;
	}

	if ($cmd == "update_taskoptions_angular") {
		$task_id = mysqli_real_escape_string($mysqli, $data->task_id);
		$taskoptiontype_id = mysqli_real_escape_string($mysqli, $data->taskoptiontype_id);
		$optionsvalue = mysqli_real_escape_string($mysqli, $data->optionsvalue);
		
		$response = update_taskoptions_angular($mysqli, $task_id, $taskoptiontype_id, $optionsvalue);
		echo $response;
		exit;
	}
	
	if ($cmd == "update_task_angular") {
		$id = mysqli_real_escape_string($mysqli, $data->id);
		$name = mysqli_real_escape_string($mysqli, $data->name);
		$description = mysqli_real_escape_string($mysqli, $data->description);
		$jobtype_id = mysqli_real_escape_string($mysqli, $data->jobtype_id);
		$start_time = mysqli_real_escape_string($mysqli, $data->start_time);
		$weekday_id = mysqli_real_escape_string($mysqli, $data->weekday_id);
		$apikey = mysqli_real_escape_string($mysqli, $data->apikey);
		$payload = mysqli_real_escape_string($mysqli, $data->payload);
		$url = mysqli_real_escape_string($mysqli, $data->url);
		$format_id = mysqli_real_escape_string($mysqli, $data->format_id);
		$zip = mysqli_real_escape_string($mysqli, $data->zip);
		$zip_destination = mysqli_real_escape_string($mysqli, $data->zip_destination);
		$copy = mysqli_real_escape_string($mysqli, $data->copy);
		$copy_destination = mysqli_real_escape_string($mysqli, $data->copy_destination);
		$publishfiletowebserver = mysqli_real_escape_string($mysqli, $data->publishfiletowebserver);
		$webfolder = mysqli_real_escape_string($mysqli, $data->webfolder);
		$webfilename = mysqli_real_escape_string($mysqli, $data->webfilename);
		$ftp = mysqli_real_escape_string($mysqli, $data->ftp);
		$ftp_server = mysqli_real_escape_string($mysqli, $data->ftp_server);
		$ftp_user = mysqli_real_escape_string($mysqli, $data->ftp_user);
		$ftp_password = mysqli_real_escape_string($mysqli, $data->ftp_password);
		$interval_id = mysqli_real_escape_string($mysqli, $data->interval_id);
		$folder = mysqli_real_escape_string($mysqli, $data->folder);
		$filename = mysqli_real_escape_string($mysqli, $data->filename);
		$notification = mysqli_real_escape_string($mysqli, $data->notification);
		$notificationemails = mysqli_real_escape_string($mysqli, $data->notificationemails);
		$enabled = mysqli_real_escape_string($mysqli, $data->enabled);
		
		$response = update_task_angular($mysqli, $id, $name, $description, $jobtype_id, $start_time, 
							 $weekday_id, $apikey, $payload, $url, $format_id, $zip, $zip_destination, 
							 $copy, $copy_destination,$publishfiletowebserver,$webfolder,$webfilename, 
							 $ftp, $ftp_server, $ftp_user, $ftp_password, $interval_id, 
							 $folder, $filename, $notification, $notificationemails,$enabled);
		echo $response;
		exit;
	}
	
	if ($cmd == "delete_task_angular") {
		$id = mysqli_real_escape_string($mysqli, $data->id);
		$response = delete_task_angular($mysqli, $id);
		echo $response;
		exit;
	}
	
	if ($cmd == "reset_task_angular") {
		$id = mysqli_real_escape_string($mysqli, $data->id);
		
		$response = reset_task_angular($mysqli, $id);
		echo $response;
		exit;
	}
	
	if ($cmd == "run_task_angular") {
		$id = mysqli_real_escape_string($mysqli, $data->id);
		
		$response = run_task_angular($mysqli, $id);
		echo $response;
		exit;
	}
}
			
				

/**********

Funktion som hämtar info om ett job.

**********/

function get_job_info($url) {
	global $api_key;
	$ch = curl_init();
	$queryParams = '?' . urlencode('apikey') . '=' . urlencode($api_key);
	curl_setopt($ch, CURLOPT_URL, $url . $queryParams);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	curl_setopt($ch, CURLOPT_HEADER, FALSE);
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
	curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/xml'));
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
	$response = curl_exec($ch);
	curl_close($ch);
	return $response;
}

function get_job_list($category,$limit,$offset) {
	global $api_key;
	$url= "https://api-eu.hosted.exlibrisgroup.com/almaws/v1/conf/jobs";
	$ch = curl_init();
	$queryParams = '?' . urlencode('apikey') . '=' . urlencode($api_key) . '&' . urlencode('category') . '=' . urlencode($category) . '&' . urlencode('limit') . '=' . urlencode($limit) . '&' . urlencode('offset') . '=' . urlencode($offset);
	curl_setopt($ch, CURLOPT_URL, $url . $queryParams);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	curl_setopt($ch, CURLOPT_HEADER, FALSE);
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
	curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/xml'));
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
	$response = curl_exec($ch);
	curl_close($ch);
	return $response;
}

/****
content_type:
	BIB_MMS = "All titles" 
	ITEM = "Physical items"
	IEP = "Physical titles"
	PORTFOLIO = "Electronic portfolios"
q:
created_by~Sand
name~DB-listan
****/
function get_set_list($content_type,$q, $limit,$offset) {
	global $api_key;
	$url= "https://api-eu.hosted.exlibrisgroup.com/almaws/v1/conf/sets";
	$ch = curl_init();
	$queryParams = '?' . urlencode('apikey') . '=' . urlencode($api_key);
	$queryParams .= '&' . urlencode('content_type') . '=' . urlencode($content_type);
	//if ($q != "") {
		$queryParams .= '&' . urlencode('q') . '=' . urlencode($q);
	//}
	$queryParams .= '&' . urlencode('limit') . '=' . urlencode($limit);
	$queryParams .='&' . urlencode('offset') . '=' . urlencode($offset);
	curl_setopt($ch, CURLOPT_URL, $url . $queryParams);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	curl_setopt($ch, CURLOPT_HEADER, FALSE);
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
	curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/xml'));
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
	$response = curl_exec($ch);
	curl_close($ch);
	return $response;
}

function checkifAlmaerror($response) {
	//echo $response;
	$xml = simplexml_load_string($response);
	foreach( $xml as $nodes ) {
		if ($nodes->getName() == 'errorsExist') { 
			$error = 1;
			break;
		}
		else {
			$error = 0;
		}
	}
	if ($error == 1) {
		return "Error";
	}
	else {
		return "Success";
	}
}

/************

Funktion som hämtar actions för task

************/
function get_action($task_id,$mysqli) {
	
	$sql = "SELECT actions.description as actions_description, taskoptiontypes.description as taskoptions_description, taskoptions.optionsvalue
                              FROM tasks
                              INNER JOIN actions on actions.id = tasks.action_id 
                              INNER JOIN taskoptions on taskoptions.task_id =  tasks.id 
                              INNER JOIN taskoptiontypes on taskoptiontypes.id = taskoptions.taskoptiontype_id
                              WHERE tasks.id = $task_id";
	$result = mysqli_query($mysqli,$sql);
	$html = "<table>
			<tr>
			<th>Action desc</th>
			<th>taskoption desc</th>
			<th>optionsvalue</th>
			</tr>";
	while($row = mysqli_fetch_array($result)) {
		$html .= "<tr>";
		$html .= "<td>" . $row['actions_description'] . "</td>";
		$html .= "<td>" . $row['taskoptions_description'] . "</td>";
		$html .= "<td>" . $row['optionsvalue'] . "</td>";
		$html .= "</tr>";
	}
	$html .= "</table>";
	mysqli_close($mysqli);
	return $html;
}

/************

Funktion som hämtar tasks och returnerar dem i en html-tabell

************/
function get_tasks($task_id,$mysqli) {
	
	$sql = "SELECT tasks.name as name, tasks.description as task_description, status.description as status_description, actions.description as action_description FROM tasks
			INNER JOIN actions on actions.id = tasks.action_id
			INNER JOIN status on status.id = tasks.status_id";
	$result = mysqli_query($mysqli,$sql);
	$html = "<table>
			<tr>
			<th>Name</th>
			<th>desc</th>
			<th>status</th>
			<th>action</th>
			</tr>";
	while($row = mysqli_fetch_array($result)) {
		$html .= "<tr>";
		$html .= "<td>" . $row['name'] . "</td>";
		$html .= "<td>" . $row['task_description'] . "</td>";
		$html .= "<td>" . $row['status_description'] . "</td>";
		$html .= "<td>" . $row['action_description'] . "</td>";
		$html .= "</tr>";
	}
	$html .= "</table>";
	mysqli_close($mysqli);
	return $html;
}

/************

Funktion som hämtar tasks och returnerar dem i json-format

************/
function get_tasks_angular($mysqli) {
	
	$sql = "SELECT tasks.id, tasks.name as name, tasks.description as task_description,tasks.enabled as enabled, status.status as status_status, 
			status.description as status_description, actions.name as action_name, actions.description as action_description,tasks.start_time, 
			jobtypes.description as jobtype_description, intervals.description as interval_description, tasks.islongrunning
			FROM tasks
			LEFT  JOIN actions on actions.id = tasks.action_id
			LEFT  JOIN status on status.id = tasks.status_id
			LEFT  JOIN jobtypes on jobtypes.id = tasks.jobtype_id
			LEFT  JOIN intervals on intervals.id = tasks.interval_id
			ORDER BY tasks.name";
	$result = mysqli_query($mysqli,$sql);
	$json = "{ \"records\":[";
	$i=0;
	while($row = mysqli_fetch_array($result)) {
		if ($i!=0) {$json .=",";} else {$i=1;}
		$json .= "{\"id\": \"". $row['id'] . "\",";
		$json .= "\"name\": \"". $row['name'] . "\",";
		$json .= "\"task_description\": \"". mysqli_real_escape_string($mysqli,$row['task_description']) . "\",";
		$json .= "\"enabled\": \"". $row['enabled'] . "\",";
		$json .= "\"status_status\": \"". $row['status_status'] . "\",";
		$json .= "\"status_description\": \"". $row['status_description'] . "\",";
		$json .= "\"action_name\": \"". $row['action_name'] . "\",";
		$json .= "\"action_description\": \"". $row['action_description'] . "\",";
		$json .= "\"jobtype_description\": \"". $row['jobtype_description'] . "\",";
		$json .= "\"interval_description\": \"". $row['interval_description'] . "\",";
		$json .= "\"islongrunning\": \"". $row['islongrunning'] . "\",";
		$json .= "\"start_time\": \"". $row['start_time'] . "\"}";
	}
	$json .= "]}";
	mysqli_close($mysqli);
	return $json;
}

/************

Funktion som hämtar en task och returnerar den i json-format

************/
function get_task_angular($mysqli, $task_id) {
	
	$sql = "SELECT * FROM tasks WHERE id = $task_id";
	$result = mysqli_query($mysqli,$sql);
	$i=0;
	while($row = mysqli_fetch_array($result)) {
		//if ($i!=0) {$json .=",";} else {$i=1;}
		$json = "{\"id\": \"". $row['id'] . "\",";
		$json .= "\"name\": \"". $row['name'] . "\",";
		$json .= "\"enabled\": \"". $row['enabled'] . "\",";
		$json .= "\"description\": \"". mysqli_real_escape_string($mysqli,$row['description']) . "\",";
		$json .= "\"jobtype_id\": \"". $row['jobtype_id'] . "\",";
		$json .= "\"start_time\": \"". $row['start_time'] . "\",";
		$json .= "\"weekday_id\": \"". $row['weekday_id'] . "\",";
		$json .= "\"apikey\": \"". $row['apikey'] . "\",";
		$json .= "\"payload\": \"". mysqli_real_escape_string($mysqli,$row['payload']) . "\",";
		$json .= "\"url\": \"". $row['url'] . "\",";
		$json .= "\"format_id\": \"". $row['format_id'] . "\",";
		$json .= "\"zip\": \"". $row['zip'] . "\",";
		$json .= "\"zip_destination\": \"". $row['zip_destination'] . "\",";
		$json .= "\"copy\": \"". $row['copy'] . "\",";
		$json .= "\"copy_destination\": \"". $row['copy_destination'] . "\",";
		$json .= "\"publishfiletowebserver\": \"". $row['publishfiletowebserver'] . "\",";
		$json .= "\"webfolder\": \"". $row['webfolder'] . "\",";
		$json .= "\"webfilename\": \"". $row['webfilename'] . "\",";
		$json .= "\"ftp\": \"". $row['ftp'] . "\",";
		$json .= "\"ftp_server\": \"". $row['ftp_server'] . "\",";
		$json .= "\"ftp_user\": \"". $row['ftp_user'] . "\",";
		$json .= "\"ftp_password\": \"". $row['ftp_password'] . "\",";
		$json .= "\"interval_id\": \"". $row['interval_id'] . "\",";
		$json .= "\"folder\": \"". $row['folder'] . "\",";
		$json .= "\"filename\": \"". $row['filename'] . "\",";
		$json .= "\"notification\": \"". $row['notification'] . "\",";
		$json .= "\"notificationemails\": \"". $row['notificationemails'] . "\"}";
		
	}
	mysqli_close($mysqli);
	return $json;
}

/************

Funktion som hämtar systemlog för task och returnerar den i json-format

************/
function get_systemlog_angular($mysqli, $task_id) {
	
	$sql = "SELECT id, task_id, logtype_id, message, timestamp
			FROM systemlog
			WHERE task_id = $task_id
			AND logtype_id = 2";

	$result = mysqli_query($mysqli,$sql);
	$json = "{ \"records\":[";
	$i=0;
	
	while($row = mysqli_fetch_array($result)) {
		
		$message = mysqli_real_escape_string($mysqli, $row['message']);
		$message = str_replace('\n', "", $message);
		$message = str_replace('\"', "", $message);
		$message = str_replace('<', "", $message);
		$message = str_replace('/>', "", $message);
		$message = str_replace('http://com/exlibris/urm/general/xmlbeans', "", $message);
		$message = str_replace('>', "", $message);
		$message = str_replace('xmlns=', "", $message);
		if ($i!=0) {$json .=",";} else {$i=1;}
		$json .= "{\"id\": \"". $row['id'] . "\",";
		$json .= "\"task_id\": \"". $row['task_id'] . "\",";
		$json .= "\"logtype_id\": \"". $row['logtype_id'] . "\",";
		$json .= "\"message\": \"". $message . "\",";
		$json .= "\"timestamp\": \"". $row['timestamp'] . "\"}";
	}
	$json .= "]}";
	mysqli_close($mysqli);
	return $json;
}

/************

Funktion som hämtar jobtyper och returnerar dem i json-format

************/
function get_jobtypes_angular($mysqli) {
	
	$sql = "SELECT * FROM jobtypes";
	$result = mysqli_query($mysqli,$sql);
	$json = "{ \"records\":[";
	$i=0;
	while($row = mysqli_fetch_array($result)) {
		if ($i!=0) {$json .=",";} else {$i=1;}
		$json .= "{\"id\": \"". $row['id'] . "\",";
		$json .= "\"name\": \"". $row['name'] . "\",";
		$json .= "\"description\": \"". $row['description'] . "\"}";
	}
	$json .= "]}";
	mysqli_close($mysqli);
	return $json;
}

/************

Funktion som hämtar intervaller och returnerar dem i json-format

************/
function get_intervals_angular($mysqli) {
	
	$sql = "SELECT * FROM intervals";
	$result = mysqli_query($mysqli,$sql);
	$json = "{ \"records\":[";
	$i=0;
	while($row = mysqli_fetch_array($result)) {
		if ($i!=0) {$json .=",";} else {$i=1;}
		$json .= "{\"id\": \"". $row['id'] . "\",";
		$json .= "\"name\": \"". $row['name'] . "\",";
		$json .= "\"description\": \"". $row['description'] . "\",";
		$json .= "\"seconds\": \"". $row['seconds'] . "\"}";
	}
	$json .= "]}";
	mysqli_close($mysqli);
	return $json;
}

/************

Funktion som hämtar veckodagar och returnerar dem i json-format

************/
function get_weekdays_angular($mysqli) {
	
	$sql = "SELECT * FROM weekdays";
	$result = mysqli_query($mysqli,$sql);
	$json = "{ \"records\":[";
	$i=0;
	while($row = mysqli_fetch_array($result)) {
		if ($i!=0) {$json .=",";} else {$i=1;}
		$json .= "{\"id\": \"". $row['id'] . "\",";
		$json .= "\"name\": \"". $row['name'] . "\",";
		$json .= "\"description\": \"". $row['description'] . "\"}";
	}
	$json .= "]}";
	mysqli_close($mysqli);
	return $json;
}

/************

Funktion som hämtar formattyper och returnerar dem i json-format

************/
function get_formats_angular($mysqli) {
	
	$sql = "SELECT * FROM formats";
	$result = mysqli_query($mysqli,$sql);
	$json = "{ \"records\":[";
	$i=0;
	while($row = mysqli_fetch_array($result)) {
		if ($i!=0) {$json .=",";} else {$i=1;}
		$json .= "{\"id\": \"". $row['id'] . "\",";
		$json .= "\"name\": \"". $row['name'] . "\",";
		$json .= "\"description\": \"". $row['description'] . "\"}";
	}
	$json .= "]}";
	mysqli_close($mysqli);
	return $json;
}

/************

Funktion som hämtar jobinställningar (används inte)

************/
function get_taskoptions_angular($task_id, $mysqli) {
	
	$sql = "SELECT tasks.name as task_name,taskoptiontypes.id as taskoptiontype_id, taskoptiontypes.name as taskoptions_name,taskoptiontypes.description as taskoptions_description, 
			taskoptions.optionsvalue,taskoptiontypes.format
			FROM taskoptiontypes
			LEFT JOIN taskoptions on taskoptiontypes.id = taskoptions.taskoptiontype_id
			AND taskoptions.task_id=$task_id
			LEFT JOIN tasks on taskoptions.task_id = tasks.id";
	$result = mysqli_query($mysqli,$sql);
	$json = "{ \"records\":[";
	$i=0;
	while($row = mysqli_fetch_array($result)) {
		if ($i!=0) {$json .=",";} else {$i=1;}
		$json .= "{\"task_name\": \"". $row['task_name'] . "\",";
		$json .= "\"taskoptiontype_id\": \"". $row['taskoptiontype_id'] . "\",";
		$json .= "\"taskoptions_name\": \"". $row['taskoptions_name'] . "\",";
		$json .= "\"taskoptions_description\": \"". $row['taskoptions_description'] . "\",";
		$json .= "\"optionsvalue\": \"". mysqli_real_escape_string($mysqli,$row['optionsvalue']) . "\",";
		$json .= "\"format\": \"". $row['format'] . "\"}";
	}
	$json .= "]}";
	mysqli_close($mysqli);
	return $json;
}

/************

Funktion som skapar en ny task(med defaultvärden från databasen)

************/
function insert_task_angular($mysqli, $name) {
	$sql = "INSERT INTO tasks
		    (name)
		    VALUES('$name')";
	$result = mysqli_query($mysqli,$sql);
	mysqli_close($mysqli);
	return $result;
}

/************

Funktion som hämtar uppdaterar jobbinställningar(används inte)

************/
function update_taskoptions_angular($mysqli, $task_id, $taskoptiontype_id, $optionsvalue) {
	$sql = "UPDATE taskoptions 
			set optionsvalue = '$optionsvalue'
			WHERE taskoptiontype_id = $taskoptiontype_id
			AND task_id = $task_id";
	$result = mysqli_query($mysqli,$sql);
	mysqli_close($mysqli);
	return $result;
}

/************

Funktion som uppdaterar ett jobb

************/
function update_task_angular($mysqli, $id, $name, $description, $jobtype_id, $start_time, 
							 $weekday_id, $apikey, $payload, $url, $format_id, $zip, $zip_destination, 
							 $copy, $copy_destination,$publishfiletowebserver,$webfolder,$webfilename, 
							 $ftp, $ftp_server, $ftp_user, $ftp_password, $interval_id, 
							 $folder, $filename, $notification, $notificationemails,$enabled) {
	$sql = "UPDATE tasks
			set name = '$name',
			description = '$description',
			jobtype_id = $jobtype_id,";
			if($start_time == "") {
				$sql .= "start_time = null,";
			} else {
				$sql .= "start_time = '$start_time',";
			}
			$sql .= "weekday_id = $weekday_id,
			apikey = '$apikey',
			payload = '$payload',
			url = '$url',
			format_id = $format_id,
			zip = $zip,
			zip_destination = '$zip_destination',
			publishfiletowebserver = $publishfiletowebserver,
			webfolder = '$webfolder',
			webfilename = '$webfilename',
			copy = $copy,
			copy_destination = '$copy_destination',
			ftp = $ftp,
			ftp_server = '$ftp_server',
			ftp_user = '$ftp_user',
			ftp_password = '$ftp_password',
			interval_id = $interval_id,
			folder = '$folder',
			filename = '$filename',
			notification = $notification,
			notificationemails = '$notificationemails',
			enabled = $enabled
			WHERE id = $id";
	$result = mysqli_query($mysqli,$sql);
	mysqli_close($mysqli);
	//echo $sql;
	return $result;
}

/************

Funktion som tar bort ett jobb

************/
function delete_task_angular($mysqli, $id) {
	$sql = "DELETE FROM tasks
			WHERE id = $id";
	$result = mysqli_query($mysqli,$sql);
	mysqli_close($mysqli);
	return $result;
}

/************

Funktion som återställer ett jobb så det kan köras igen (t ex när ett jobb gått fel på nåt sätt)
TODO: Sätt nästa starttid så att inte jobbet körs upprepade gånger
	Om Interval = Every Hour (1)
		Sätt starttid lika med Datum och timme för nutid + minuter från tasks.start_time
		SELECT CONCAT(DATE_FORMAT(now(), '%Y-%m-%d %H') , ':', DATE_FORMAT(start_time, '%i')) FROM tasks
	Om Interval = Daily (2)
		Sätt starttid lika med Datum för nutid + timme och minuter från tasks.start_time
		SELECT CONCAT(DATE_FORMAT(now(), '%Y-%m-%d') , ' ', DATE_FORMAT(start_time, '%H:%i')) FROM tasks
	Om Interval = Weekly (3)
		Sätt starttid lika med Datum för nästa veckodag som är lika med tasks.weekday_id utifrån nutid + timme och minuter från tasks.start_time

************/
function reset_task_angular($mysqli, $id) {
	$sql = "UPDATE tasks
			set status_id = 3,
			action_id = 6,
			islongrunning = 0
			WHERE id = $id";
	/*
		UPDATE tasks
			set status_id = 3,
			action_id = 6,
			islongrunning = 0,
			tasks.start_time = CONCAT(DATE_FORMAT(now(), '%Y-%m-%d %H') , ':', DATE_FORMAT(start_time, '%i'))
			WHERE id = $id
	*/
	$result = mysqli_query($mysqli,$sql);
	mysqli_close($mysqli);
	return $result;
}

/************

Funktion som kör ett jobb genom att sätta action till "runnow"

************/
function run_task_angular($mysqli, $id) {
	$sql = "UPDATE tasks
			set status_id = 3,
			action_id = 11
			WHERE id = $id";
	$result = mysqli_query($mysqli,$sql);
	mysqli_close($mysqli);
	return $result;
}

?>