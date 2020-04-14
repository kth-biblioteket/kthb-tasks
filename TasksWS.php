<?php
/*******************************************************************************
 * 
 * PHP-Script som hanterar TASKS på KTHB. Möjlighet att lägga upp batchjobb som
 *  kan köras dagligen/veckovis etc.
 * 
 *
 *  exvis jobb i Alma, Grupprumsbokningen etc
 * 
 * https://apps.lib.kth.se/tasks/index.php
 * 
 * Scriptet startas var 5:e sekund av service KTHBHandler som finns 
 * installerad på apps.lib.kth.se
 * 
 * 
 * TODO: Felhantering för databasanrop generellt(ej tillgänglig t ex)
 * 
*******************************************************************************/

require('config.php'); //innehåller API-KEY + error reporting
//inkluderar hjälpklass för mailfunktioner
require_once($_SERVER['DOCUMENT_ROOT'] . '/PHPMailer/PHPMailerAutoload.php');
//inkludera funktioner
require_once('functions.php');

/*******************************************************************************
 * 
 * Funktion som hämtar bokningar från MRBS (grupprumsbokningens databas)
 * inom ett tidsintervall och med angiven status och som har påminnelser aktiverat.
 * 
 * areatype: 1 = Grupprum, 2 = Lässtudio, 3 = Handledning, 4 = Öppettider
 * status: 4 = preliminär, 0=kvitterad 
 * bookingtype: "I" = Öppen "C"=Closed'
 * 
*******************************************************************************/
function mrbs_getBookings($mysqli, $mysqli_MRBS, $fromtime, $totime, $areatype, $status, $bookingtype) {
	//TODO IN(bookingtype)
	$query =   "SELECT start_time, end_time, E.name, repeat_id,
				E.id AS entry_id, E.type,
				E.description AS entry_description, E.status,
				E.create_by AS entry_create_by,
				E.lang,
				room_number,
				room_name,
				room_name_english,
				area_map,
				area_map_image,
				mailtext,
				mailtext_en
				FROM mrbs_entry E
				INNER JOIN mrbs_room ON mrbs_room.id = E.room_id
                INNER JOIN mrbs_area ON mrbs_area.id = mrbs_room.area_id
				WHERE E.status IN ($status)
				AND E.type = '$bookingtype'
				AND mrbs_area.reminder_email_enabled = true
				AND start_time >= $fromtime
				AND start_time <= $totime
				AND mrbs_area.area_type = $areatype
				AND (isnull(E.reminded) OR E.reminded = 0)";
				//AND mrbs_area.id IN ($arealist)";
	//InsertLogMessages($mysqli, 0 , 1 , str_replace("'","\'",$query));	
	$result = mysqli_query($mysqli_MRBS, $query);
	return $result;
}

/******************************************
 * 
 * Funktion för att uppdatera en MRBS-bokning att
 * påminnelsemail har skickats
 * 
******************************************/
function mrbs_updateReminded($mysqli, $mysqli_MRBS, $task_id, $id, $reminded) {
	$sql =   "UPDATE mrbs_entry 
				SET reminded = $reminded 
				WHERE id= $id";
	$result = mysqli_query($mysqli_MRBS, $sql);
	
	//Spara info i systemlog
	//InsertLogMessages($mysqli, $task_id, 1 ,$result);
	return $result;
}

/******************************************
 * 
 * Funktion för att uppdatera en MRBS-bokning att
 * påminnelsemail har skickats
 * 
******************************************/
function mrbs_updateConfirmationCode($mysqli, $mysqli_MRBS, $task_id, $id, $confirmation_code) {
	$sql =   "UPDATE mrbs_entry 
				SET confirmation_code = '$confirmation_code'
				WHERE id= $id";
	$result = mysqli_query($mysqli_MRBS, $sql);
	//sendemail("tholind@kth.se", "noreply@lib.kth.se", "KTH Bibilioteket", "test", $sql, "", ""); 
	//Spara info i systemlog
	//InsertLogMessages($mysqli, $task_id, 1 ,$result);
	return $result;
}

/*********************************************
 * 
 * Funktion för påminnelsemail till MRBS.
 * 
 * Alla bokningar startar på heltimme. Så hämta
 * nästa heltimme och skicka påminnelse till
 * de bokningar som har startid = nästa heltimme
 * 
 * 
 * TODO Bort med hårdkodning och in med Parametrar för: 
 * - 	areor, 
 * - 	bokningstyper, 
 * - 	hur långt innan starttiden för bokningen 
 * 		påminnelsen ska skickas ut.
 * 			- grupprum = en kvart innan
 * 			- CAS = ett dygn innan
 * 
*********************************************/
function mrbs_mailreminder($mysqli, $mysqli_MRBS, $task_id, $url_MRBS) {
	//init
	$subject = '';
	$html_body = '';
	$fromadress = '';
	$toadress = '';
	$inlineimage = '';
	$inlineimagecid = '';
	
	//sätt status till waiting för jobbet
	UpdateTaskStatus($mysqli, $task_id, "2");
	//Hämta parametrar
	$taskparameters = GetTaskParameters($mysqli,$task_id);
	while($row = mysqli_fetch_array($taskparameters)) {
		$notification = $row["notification"];
		$notificationemails = $row["notificationemails"];
		$payload = $row["payload"];
	}

	$areatype = "";
	//läs in payload för jobbet (JSON i TASK-settings)
	$payloadobject = json_decode($payload, TRUE);
	if (isset($payloadobject['type'])) {
		$areatype = $payloadobject['type'];
	}

	//Hämta bokningar för aktuell areatype och övriga villkor
	//status 4 = preliminär, 0=kvitterad, "I" = Öppen "C"=Closed
	//$mailresponse = sendemail("tholind@kth.se", "noreply@lib.kth.se", "areatype", $subject, $areatype);
	switch ($areatype){
		case "grouproom":
			//hämta de bokningar som har startid = nästa heltimme
			//TODO option i payload?
			$nextHour = date('H')+1;
			$nexthourinseconds = strtotime(date("Y-m-d") . " ". $nextHour . ":00:00");
			$result = mrbs_getBookings($mysqli, $mysqli_MRBS, $nexthourinseconds, $nexthourinseconds, "1", "4", "I");
			break;	
		case "readingstudio": 
			//hämta de bokningar som har startid = nästa heltimme
			//TODO option i payload?
			$nextHour = date('H')+1;
			$nexthourinseconds = strtotime(date("Y-m-d") . " ". $nextHour . ":00:00");
			$result = mrbs_getBookings($mysqli, $mysqli_MRBS, $nexthourinseconds, $nexthourinseconds, "2", "4", "I"); 	
			break;
		case "supervision": 
			//hämta de bokningar som har startid = under morgondagen
			//TODO option i payload?
			$today = date("Y-m-d H:i:s");
			$tomorrow = new DateTime('tomorrow');
			$fromtime = strtotime($tomorrow->format('Y-m-d H:i:s')); //morgondagen 00:00
			$totime = strtotime($tomorrow->format('Y-m-d H:i:s')) + 60*60*24 - 1; //morgondagen 23:59
			$result = mrbs_getBookings($mysqli, $mysqli_MRBS, $fromtime, $totime, "3", "0", "I"); 
			break;
		case "talkingbooks": 
			//hämta de bokningar som har startid = under morgondagen
			//TODO option i payload?
			$today = date("Y-m-d H:i:s");
			$tomorrow = new DateTime('tomorrow');
			$fromtime = strtotime($tomorrow->format('Y-m-d H:i:s')); //morgondagen 00:00
			$totime = strtotime($tomorrow->format('Y-m-d H:i:s')) + 60*60*24 - 1; //morgondagen 23:59
			$result = mrbs_getBookings($mysqli, $mysqli_MRBS, $fromtime, $totime, "5", "0", "I"); 
			break;
		default:
	}
	
	$i=0;
	$users = "";
	while($row = mysqli_fetch_array($result)) {
		//Uppdatera bokningen med en confirmation_code(för att kunna kvittera med ett enda klick på länk/knapp)
		$confirmation_code = strtr(base64_encode(openssl_random_pseudo_bytes(64)),"+/=", "XXX");
		mrbs_updateConfirmationCode($mysqli, $mysqli_MRBS, $task_id, $row["entry_id"], $confirmation_code);
		$bookingdate = date("Y-m-d H:i:s",$row["start_time"]);
		//Sätt till NULL == default windows system's(apps-servern) regional/language settings == Svenska
		//för att få strftime("%A %d %B") att använda rätt språk
		setlocale(LC_ALL, NULL);
		if ($row['lang']=='sv') {
			//Svenska
			switch ($areatype){
				case "grouproom":
					$emailfromname ="KTH Biblioteket";
					$fromadress = '';
					$toadress = '';
					$subject = 'Kvittera ditt grupprum!';
					$html_body = '<div>Hej!</div>
								</br>
								<div>Du har bokat rum ' . $row["room_number"] . ', ' . ' från ' . date("H:i",$row['start_time']) . ' till ' . date("H:i",$row['end_time']) . ', ' . utf8_encode(strftime("%A %d %B",$row['start_time'])) . '. </div>
								</br>
								<div>Kvitteringstiden för din bokning har börjat och pågår från ' . date("H:i",$row['start_time'] - 60*15) . ' till ' . date("H:i",$row['start_time'] + 60*15) . '. </div>
								</br>
								<div>
									<table border="0" cellspacing="0" cellpadding="0">
										<tbody>
											<tr>
												<td align="center" style="border-radius: 30px;" bgcolor="#B0C92B">
													<a style="padding: 15px 25px; border-radius: 30px; border: 1px solid #B0C92B; border-image: none; color: rgb(255, 255, 255); font-family: Arial, Helvetica, sans-serif; font-size: 16px; font-weight: bold;text-decoration: none; display: inline-block;" href="https://apps.lib.kth.se/webservices/mrbs/api/v1/entries/confirm/' . $confirmation_code . '?lang=sv&database=mrbs&appname=mrbs">' . 'Kvittera din bokning' . '</a>
												</td>
											</tr>
										</tbody>
									</table>
								</div>
								<!--div><a href="https://apps.lib.kth.se/webservices/mrbs/api/v1/entries/confirm/' . $confirmation_code . '?lang=sv&database=mrbs&appname=mrbs">' . 'Kvittera rummet' . '</a></div-->
								</br>
								<!--div>
									<table border="0" cellspacing="0" cellpadding="0">
										<tbody>
											<tr>
												<td align="center" style="border-radius: 30px;" bgcolor="#B0C92B">
													<a style="padding: 15px 25px; border-radius: 30px; border: 1px solid #B0C92B; border-image: none; color: rgb(255, 255, 255); font-family: Arial, Helvetica, sans-serif; font-size: 16px; font-weight: bold;text-decoration: none; display: inline-block;" href="' . $url_MRBS . '/edit_entry.php?id=' . $row["entry_id"] . '&lang=sv">' . 'Gå till din bokning' . '</a>
												</td>
											</tr>
										</tbody>
									</table>
								</div-->
								<div><a href="' . $url_MRBS . '/edit_entry.php?id=' . $row["entry_id"] . '&lang=sv">' . 'Gå till din bokning' . '</a></div>
								</br>';
								if($row['area_map']) {
									$html_body .= '<div><img src="cid:map" alt="map"></div>
									</br>';
								}
					$html_body .= '<div>Vänliga hälsningar</div>
								</br>
								<div>KTH Biblioteket</div>
								<div>08 - 790 70 88</div>
								<div>www.kth.se/biblioteket</div>
								';
					$inlineimage = './images/grupprum.jpg';
					$inlineimagecid = 'map';
					break;
				case "readingstudio":
					$emailfromname ="KTH Biblioteket";
					$fromadress = '';
					$toadress = '';
					$subject = 'Kvittera din lässtudio!';
					$html_body = '<div>Hej!</div>
								</br>
								<div>Du har bokat ' . $row["room_name"] . ' från ' . date("H:i",$row['start_time']) . ' till ' . date("H:i",$row['end_time']) . ', ' . utf8_encode(strftime("%A %d %B",$row['start_time'])) . '. </div>
								</br>
								<div>Kvitteringstiden för din bokning har börjat och pågår från ' . date("H:i",$row['start_time'] - 60*15) . ' till ' . date("H:i",$row['start_time'] + 60*15) . '. </div>
								</br>
								<div>
									<table border="0" cellspacing="0" cellpadding="0">
										<tbody>
											<tr>
												<td align="center" style="border-radius: 30px;" bgcolor="#B0C92B">
													<a style="padding: 15px 25px; border-radius: 30px; border: 1px solid #B0C92B; border-image: none; color: rgb(255, 255, 255); font-family: Arial, Helvetica, sans-serif; font-size: 16px;font-weight: bold;text-decoration: none; display: inline-block;" href="https://apps.lib.kth.se/webservices/mrbs/api/v1/entries/confirm/' . $confirmation_code . '?lang=sv&database=mrbs&appname=mrbs">' . 'Kvittera din bokning' . '</a>
												</td>
											</tr>
										</tbody>
									</table>
								</div>
								<!--div><a href="https://apps.lib.kth.se/webservices/mrbs/api/v1/entries/confirm/' . $confirmation_code . '?lang=sv&database=mrbs&appname=mrbs">' . 'Kvittera rummet' . '</a></div-->
								</br>
								<!--div>
									<table border="0" cellspacing="0" cellpadding="0">
										<tbody>
											<tr>
												<td align="center" style="border-radius: 30px;" bgcolor="#B0C92B">
													<a style="padding: 15px 25px; border-radius: 30px; border: 1px solid #B0C92B; border-image: none; color: rgb(255, 255, 255); font-family: Arial, Helvetica, sans-serif; font-size: 16px; font-weight: bold;text-decoration: none; display: inline-block;" href="' . $url_MRBS . '/edit_entry.php?id=' . $row["entry_id"] . '&lang=sv">' . 'Gå till din bokning' . '</a>
												</td>
											</tr>
										</tbody>
									</table>
								</div-->
								<div><a href="' . $url_MRBS . '/edit_entry.php?id=' . $row["entry_id"] . '&lang=sv">' . 'Gå till din bokning' . '</a></div>
								</br>';
								if($row['area_map']) {
									$html_body .= 		'<div><img src="cid:map" alt="map"></div>
									</br>';
								}
					$html_body .=	'<div>Vänliga hälsningar</div>
								</br>
								<div>KTH Biblioteket</div>
								<div>08 - 790 70 88</div>
								<div>www.kth.se/biblioteket</div>
								';
					$inlineimage = './images/grupprum.jpg';
					$inlineimagecid = 'map';
					break;
				case "supervision":
					$emailfromname ="KTH CAS";
					$fromadress = '';
					$toadress = '';
					$subject = 'Påminnelse om handledning'; 
					$html_body = '<div>Hej!</div>
								</br>
								<div>Du har bokat handledning från ' . date("H:i",$row['start_time']) . ' till ' . date("H:i",$row['end_time']) . ', ' . utf8_encode(strftime("%A %d %B", $row['start_time'])) . '. </div>
								</br>
								<div>Denna länk leder till din bokning, där du kan ändra eller avboka den vid behov.</div>
								</br>
								<div>
									<table border="0" cellspacing="0" cellpadding="0">
										<tbody>
											<tr>
												<td align="center" style="border-radius: 30px;" bgcolor="#B0C92B">
													<a style="padding: 15px 25px; border-radius: 30px; border: 1px solid #B0C92B; border-image: none; color: rgb(255, 255, 255); font-family: Arial, Helvetica, sans-serif; font-size: 16px; font-weight: bold;text-decoration: none; display: inline-block;" href="' . $url_MRBS . '/edit_entry.php?id=' . $row["entry_id"] . '&lang=sv">' . 'Gå till din bokning' . '</a>
												</td>
											</tr>
										</tbody>
									</table>
								</div>
								<!--div><a href="' . $url_MRBS . '/edit_entry.php?id=' . $row["entry_id"] . '&lang=sv">' . 'Gå till din bokning' . '</a></div-->
								</br>';
								if($row['area_map']) {
									$html_body .= 		'<div><img src="cid:map" alt="map"></div>
									</br>';
								}
					$html_body .=	'<div>Välkommen!</div>
								</br>
								<div>Vänliga hälsningar</div>
								</br>
								<div>KTH Centrum för akademiskt skrivande</div>
								<div>www.kth.se/cas</div>';
					break;
				case "talkingbooks":
					$emailfromname ="KTH Biblioteket";
					$fromadress = '';
					$toadress = '';
					$subject = 'Påminnelse om talboksintroduktion'; 
					$html_body = '<div>Hej!</div>
								</br>
								<div>' .
									$row['mailtext'] .
								'</div>
								</br>
								<div>' .
									'Din bokning är från ' . date("H:i",$row['start_time']) . ' to ' . date("H:i",$row['end_time']) . ', ' . date("l d F",$row['start_time']) .
								'</div>
								</br>
								<div>Denna länk leder till din bokning, där du kan ändra eller avboka den vid behov.</div>
								</br>
								<div>
									<table border="0" cellspacing="0" cellpadding="0">
										<tbody>
											<tr>
												<td align="center" style="border-radius: 30px;" bgcolor="#B0C92B">
													<a style="padding: 15px 25px; border-radius: 30px; border: 1px solid #B0C92B; border-image: none; color: rgb(255, 255, 255); font-family: Arial, Helvetica, sans-serif; font-size: 16px; font-weight: bold;text-decoration: none; display: inline-block;" href="' . $url_MRBS . '/edit_entry.php?id=' . $row["entry_id"] . '&lang=sv">' . 'Gå till din bokning' . '</a>
												</td>
											</tr>
										</tbody>
									</table>
								</div>
								<!--div><a href="' . $url_MRBS . '/edit_entry.php?id=' . $row["entry_id"] . '&lang=sv">' . 'Gå till din bokning' . '</a></div-->
								</br>';
								if($row['area_map']) {
									$html_body .= 		'<div><img src="cid:map" alt="map"></div>
									</br>';
								}
					$html_body .=	'<div>Välkommen!</div>
								</br>
								<div>Vänliga hälsningar</div>
								</br>
								<div>KTH Biblioteket</div>
								<div>www.kth.se/biblioteket</div>';
					break;
				default:
			}
		} else {
			//Engelska
			switch ($areatype){
				case "grouproom":
					$emailfromname ="KTH Library";
					$fromadress = '';
					$toadress = '';
					$subject = 'Confirm your group study room!';
					$html_body = '<div>Hi!</div>
								</br>
								<div>You have booked room ' . $row["room_number"] . ' from ' . date("H:i",$row['start_time']) . ' to ' . date("H:i",$row['end_time']) . ', ' . date("l d F",$row['start_time']) . '. </div>
								</br>
								<div>The confirmation time for your booking has started and goes on from ' . date("H:i",$row['start_time'] - 60*15) . ' to ' . date("H:i",$row['start_time'] + 60*15) .'. </div>
								</br>
								<div>
									<table border="0" cellspacing="0" cellpadding="0">
										<tbody>
											<tr>
												<td align="center" style="border-radius: 30px;" bgcolor="#B0C92B">
													<a style="padding: 15px 25px; border-radius: 30px; border: 1px solid #B0C92B; border-image: none; color: rgb(255, 255, 255); font-family: Arial, Helvetica, sans-serif; font-size: 16px; font-weight: bold;text-decoration: none; display: inline-block;" href="https://apps.lib.kth.se/webservices/mrbs/api/v1/entries/confirm/' . $confirmation_code . '?lang=en&database=mrbs&appname=mrbs">' . 'Confirm your booking' . '</a>
												</td>
											</tr>
										</tbody>
									</table>
								</div>
								<!--div><a href="https://apps.lib.kth.se/webservices/mrbs/api/v1/entries/confirm/' . $confirmation_code . '?lang=en&database=mrbs&appname=mrbs">' . 'Confirm your booking' . '</a></div-->
								</br>
								<!--div>
									<table border="0" cellspacing="0" cellpadding="0">
										<tbody>
											<tr>
												<td align="center" style="border-radius: 30px;" bgcolor="#B0C92B">
													<a style="padding: 15px 25px; border-radius: 30px; border: 1px solid #B0C92B; border-image: none; color: rgb(255, 255, 255); font-family: Arial, Helvetica, sans-serif; font-size: 16px; font-weight: bold;text-decoration: none; display: inline-block;" href="' . $url_MRBS . '/edit_entry.php?id=' . $row["entry_id"] . '&lang=en">' . 'Go to your booking' . '</a>
												</td>
											</tr>
										</tbody>
									</table>
								</div-->
								<div><a href="' . $url_MRBS . '/edit_entry.php?id=' . $row["entry_id"] . '&lang=en">' . 'Go to your booking' . '</a></div>
								</br>';
								if($row['area_map']) {
									$html_body .= 		'<div><img src="cid:map" alt="map"></div>
									</br>';
								}
					$html_body .=	'<div>Kind regards</div>
								</br>
								<div>KTH Library</div>
								<div>08 - 790 70 88</div>
								<div>www.kth.se/en/biblioteket</div>
								';
					$inlineimage = './images/grupprum.jpg';
					$inlineimagecid = 'map';
					break;
				case "readingstudio": 
					$emailfromname ="KTH Library";
					$fromadress = '';
					$toadress = '';
					$subject = 'Confirm your reading studio!';
					$html_body = '<div>Hi!</div>
								</br>
								<div>You have booked ' . $row["room_name"] . ' from ' . date("H:i",$row['start_time']) . ' to ' . date("H:i",$row['end_time']) . ', ' . date("l d F",$row['start_time']) . '. </div>
								</br>
								<div>The confirmation time for your booking has started and goes on from ' . date("H:i",$row['start_time'] - 60*15) . ' to ' . date("H:i",$row['start_time'] + 60*15) . '. </div>
								</br>
								<div>
									<table border="0" cellspacing="0" cellpadding="0">
										<tbody>
											<tr>
												<td align="center" style="border-radius: 30px;" bgcolor="#B0C92B">
													<a style="padding: 15px 25px; border-radius: 30px; border: 1px solid #B0C92B; border-image: none; color: rgb(255, 255, 255); font-family: Arial, Helvetica, sans-serif; font-size: 16px; font-weight: bold;text-decoration: none; display: inline-block;" href="https://apps.lib.kth.se/webservices/mrbs/api/v1/entries/confirm/' . $confirmation_code . '?lang=en&database=mrbs&appname=mrbs">' . 'Confirm your booking' . '</a>
												</td>
											</tr>
										</tbody>
									</table>
								</div>
								<!--div><a href="https://apps.lib.kth.se/webservices/mrbs/api/v1/entries/confirm/' . $confirmation_code . '?lang=en&database=mrbs&appname=mrbs">' . 'Confirm your booking' . '</a></div-->
								</br>
								<!--div>
									<table border="0" cellspacing="0" cellpadding="0">
										<tbody>
											<tr>
												<td align="center" style="border-radius: 30px;" bgcolor="#B0C92B">
													<a style="padding: 15px 25px; border-radius: 30px; border: 1px solid #B0C92B; border-image: none; color: rgb(255, 255, 255); font-family: Arial, Helvetica, sans-serif; font-size: 16px; font-weight: bold;text-decoration: none; display: inline-block;" href="' . $url_MRBS . '/edit_entry.php?id=' . $row["entry_id"] . '&lang=en">' . 'Go to your booking' . '</a>
												</td>
											</tr>
										</tbody>
									</table>
								</div-->
								<div><a href="' . $url_MRBS . '/edit_entry.php?id=' . $row["entry_id"] . '&lang=en">' . 'Go to your booking' . '</a></div>
								</br>';
								if($row['area_map']) {
									$html_body .= 		'<div><img src="cid:map" alt="map"></div>
									</br>';
								}
					$html_body .=	'<div>Kind regards</div>
								</br>
								<div>KTH Library</div>
								<div>08 - 790 70 88</div>
								<div>www.kth.se/en/biblioteket</div>
								';
					$inlineimage = './images/grupprum.jpg';
					$inlineimagecid = 'map';
					break;
				case "supervision":
					$emailfromname ="KTH CAW"; 
					$fromadress = '';
					$toadress = '';
					$subject = 'Reminder of supervision booking';
					$html_body = '<div>Hi!</div>
								</br>
								<div>You have booked supervision from ' . date("H:i",$row['start_time']) . ' to ' . date("H:i",$row['end_time']) . ', ' . date("l d F",$row['start_time']) . '. </div>
								</br>
								<div>This link leads to your booking where you can change or cancel it if needed.</div>
								</br>
								<div>
									<table border="0" cellspacing="0" cellpadding="0">
										<tbody>
											<tr>
												<td align="center" style="border-radius: 30px;" bgcolor="#B0C92B">
													<a style="padding: 15px 25px; border-radius: 30px; border: 1px solid #B0C92B; border-image: none; color: rgb(255, 255, 255); font-family: Arial, Helvetica, sans-serif; font-size: 16px; font-weight: bold;text-decoration: none; display: inline-block;" href="' . $url_MRBS . '/edit_entry.php?id=' . $row["entry_id"] . '&lang=en">' . 'Go to your booking' . '</a>
												</td>
											</tr>
										</tbody>
									</table>
								</div>
								<!--div><a href="' . $url_MRBS . '/edit_entry.php?id=' . $row["entry_id"] . '&lang=en">' . 'Go to your booking' . '</a></div-->
								</br>';
								if($row['area_map']) {
				$html_body .= 		'<div><img src="cid:map" alt="map"></div>
									</br>';
								}
				$html_body .=	'<div>Welcome!</div>
								</br>
								<div>Kind regards</div>
								</br>
								<div>KTH The Centre for Academic Writing</div>
								<div>www.kth.se/caw</div>';
				case "talkingbooks":
					$emailfromname ="KTH Library"; 
					$fromadress = '';
					$toadress = '';
					$subject = 'Reminder of introduction to talking books';
					$html_body = '<div>Hi!</div>
								</br>
								<div>' .
                                	$row['mailtext_en'] .
								'</div>
								</br>
								<div>' .
									'Your booking is from ' . date("H:i",$row['start_time']) . ' to ' . date("H:i",$row['end_time']) . ', ' . date("l d F",$row['start_time']) .
								'</div>
								</br>
								<div>This link leads to your booking where you can change or cancel it if needed.</div>
								</br>
								<div>
									<table border="0" cellspacing="0" cellpadding="0">
										<tbody>
											<tr>
												<td align="center" style="border-radius: 30px;" bgcolor="#B0C92B">
													<a style="padding: 15px 25px; border-radius: 30px; border: 1px solid #B0C92B; border-image: none; color: rgb(255, 255, 255); font-family: Arial, Helvetica, sans-serif; font-size: 16px; font-weight: bold;text-decoration: none; display: inline-block;" href="' . $url_MRBS . '/edit_entry.php?id=' . $row["entry_id"] . '&lang=en">' . 'Go to your booking' . '</a>
												</td>
											</tr>
										</tbody>
									</table>
								</div>
								<!--div><a href="' . $url_MRBS . '/edit_entry.php?id=' . $row["entry_id"] . '&lang=en">' . 'Go to your booking' . '</a></div-->
								</br>';
								if($row['area_map']) {
				$html_body .= 		'<div><img src="cid:map" alt="map"></div>
									</br>';
								}
				$html_body .=	'<div>Welcome!</div>
								</br>
								<div>Kind regards</div>
								</br>
								<div>KTH Library</div>
								<div>www.kth.se/en/biblioteket</div>';
					break;
				default:
			}
		}
		
		//Skicka mailet till bokaren
		$mailresponse = sendemail($row["entry_create_by"], "noreply@kth.se", "KTH Biblioteket", $subject, $html_body);
		//$mailresponse = sendemail("tholind@kth.se", "noreply@kth.se", $emailfromname, $subject, $html_body, $inlineimage, $inlineimagecid);
		if ($mailresponse == 1) {
			//logga utskicket tillfälligt under införandet
			//error_log("TASKS, Påminnelsemail skickat till: " . $row["entry_create_by"]); 
			//Sätt reminded till 1 på bokningen;
			mrbs_updateReminded($mysqli, $mysqli_MRBS, $task_id, $row["entry_id"], 1);
		} else {
			$mailresponse = sendemail("tholind@kth.se", "noreply@kth.se", $emailfromname, "Error sending mail to user. "  . $subject, $html_body, $inlineimage, $inlineimagecid);
			//Logga error
			InsertLogMessages($mysqli, $task_id, 1 ,$mailresponse);
		}

	}
	//sätt nästa action för task 
	update_task($mysqli, $task_id);
	//Spara info i systemlog
	//InsertLogMessages($mysqli, $task_id, 1 ,"Påminnelsemail skickat");
}

/*********************************************
 * 
 * Funktion för påminnelsemail till MRBS New!
 * 
 * Alla bokningar startar på heltimme. Så hämta
 * nästa heltimme och skicka påminnelse till
 * de bokningar som har startid = nästa heltimme
 * 
 * 
 * TODO Bort med hårdkodning och in med Parametrar för: 
 * - 	areor, 
 * - 	bokningstyper, 
 * - 	hur långt innan starttiden för bokningen 
 * 		påminnelsen ska skickas ut.
 * 			- grupprum = en kvart innan
 * 			- CAS = ett dygn innan
 * 
*********************************************/
function mrbsnew_mailreminder($mysqli, $mysqli_MRBS, $task_id, $url_MRBS) {
	//init
	global $db_host_MRBS, $db_login_MRBS, $db_password_MRBS;
	$subject = '';
	$html_body = '';
	$fromadress = '';
	$toadress = '';
	$inlineimage = '';
	$inlineimagecid = '';
	
	//sätt status till waiting för jobbet
	UpdateTaskStatus($mysqli, $task_id, "2");
	//Hämta parametrar
	$taskparameters = GetTaskParameters($mysqli, $task_id);
	while($row = mysqli_fetch_array($taskparameters)) {
		$notification = $row["notification"];
		$notificationemails = $row["notificationemails"];
		$payload = $row["payload"];
	}

	$areatype = "";
	//läs in payload för jobbet (JSON i TASK-settings)
	$payloadobject = json_decode($payload, TRUE);
	if (isset($payloadobject['type'])) {
		$areatype = $payloadobject['type'];
	}

	if (isset($payloadobject['database'])) {
		$db_database_MRBSnew = $payloadobject['database'];
	}
	InsertLogMessages($mysqli, $task_id, 1 , $db_database_MRBSnew);
	$mysqli_MRBSnew = mysqli_connect($db_host_MRBS, $db_login_MRBS, $db_password_MRBS, $db_database_MRBSnew);

	
	//Hämta bokningar för aktuell areatype och övriga villkor
	//status 4 = preliminär, 0=kvitterad, "I" = Öppen "C"=Closed
	//$mailresponse = sendemail("tholind@kth.se", "noreply@lib.kth.se", "areatype", $subject, $areatype);
	switch ($areatype){
		case "grouproom":
			//hämta de bokningar som har startid = nästa heltimme
			//TODO option i payload?
			$nextHour = date('H')+1;
			$nexthourinseconds = strtotime(date("Y-m-d") . " ". $nextHour . ":00:00");
			$result = mrbs_getBookings($mysqli, $mysqli_MRBSnew, $nexthourinseconds, $nexthourinseconds, "1", "4", "I");
			break;	
		case "readingstudio": 
			//hämta de bokningar som har startid = nästa heltimme
			//TODO option i payload?
			$nextHour = date('H')+1;
			$nexthourinseconds = strtotime(date("Y-m-d") . " ". $nextHour . ":00:00");
			$result = mrbs_getBookings($mysqli, $mysqli_MRBSnew, $nexthourinseconds, $nexthourinseconds, "2", "4", "I"); 	
			break;
		case "supervision": 
			//hämta de bokningar som har startid = under morgondagen
			//TODO option i payload?
			$today = date("Y-m-d H:i:s");
			$tomorrow = new DateTime('tomorrow');
			$fromtime = strtotime($tomorrow->format('Y-m-d H:i:s')); //morgondagen 00:00
			$totime = strtotime($tomorrow->format('Y-m-d H:i:s')) + 60*60*24 - 1; //morgondagen 23:59
			$result = mrbs_getBookings($mysqli, $mysqli_MRBSnew, $fromtime, $totime, "3", "0", "I"); 
			break;
		case "talkingbooks": 
			//hämta de bokningar som har startid = under morgondagen
			//TODO option i payload?
			$today = date("Y-m-d H:i:s");
			$tomorrow = new DateTime('tomorrow');
			$fromtime = strtotime($tomorrow->format('Y-m-d H:i:s')); //morgondagen 00:00
			$totime = strtotime($tomorrow->format('Y-m-d H:i:s')) + 60*60*24 - 1; //morgondagen 23:59
			$result = mrbs_getBookings($mysqli, $mysqli_MRBSnew, $fromtime, $totime, "5", "0", "I"); 
			break;
		default:
	}
	
	$i=0;
	$users = "";
	while($row = mysqli_fetch_array($result)) {
		//Uppdatera bokningen med en confirmation_code(för att kunna kvittera med ett enda klick på länk/knapp)
		$confirmation_code = strtr(base64_encode(openssl_random_pseudo_bytes(64)),"+/=", "XXX");
		mrbs_updateConfirmationCode($mysqli, $mysqli_MRBSnew, $task_id, $row["entry_id"], $confirmation_code);
		$bookingdate = date("Y-m-d H:i:s",$row["start_time"]);
		//Sätt till NULL == default windows system's(apps-servern) regional/language settings == Svenska
		//för att få strftime("%A %d %B") att använda rätt språk
		setlocale(LC_ALL, NULL);
		if ($row['lang']=='sv') {
			//Svenska
			switch ($areatype){
				case "grouproom":
					$emailfromname ="KTH Biblioteket";
					$fromadress = '';
					$toadress = '';
					$subject = 'Kvittera ditt grupprum!';
					$html_body = '<div>Hej!</div>
								</br>
								<div>Du har bokat rum ' . $row["room_number"] . ', ' . ' från ' . date("H:i",$row['start_time']) . ' till ' . date("H:i",$row['end_time']) . ', ' . utf8_encode(strftime("%A %d %B",$row['start_time'])) . '. </div>
								</br>
								<div>Kvitteringstiden för din bokning har börjat och pågår från ' . date("H:i",$row['start_time'] - 60*15) . ' till ' . date("H:i",$row['start_time'] + 60*15) . '. </div>
								</br>
								<div>
									<table border="0" cellspacing="0" cellpadding="0">
										<tbody>
											<tr>
												<td align="center" style="border-radius: 30px;" bgcolor="#B0C92B">
													<a style="padding: 15px 25px; border-radius: 30px; border: 1px solid #B0C92B; border-image: none; color: rgb(255, 255, 255); font-family: Arial, Helvetica, sans-serif; font-size: 16px; font-weight: bold;text-decoration: none; display: inline-block;" href="https://apps.lib.kth.se/webservices/mrbs/api/v1/entries/confirm/' . $confirmation_code . '?lang=sv&database=mrbs&appname=mrbs">' . 'Kvittera din bokning' . '</a>
												</td>
											</tr>
										</tbody>
									</table>
								</div>
								<!--div><a href="https://apps.lib.kth.se/webservices/mrbs/api/v1/entries/confirm/' . $confirmation_code . '?lang=sv&database=mrbs&appname=mrbs">' . 'Kvittera rummet' . '</a></div-->
								</br>
								<!--div>
									<table border="0" cellspacing="0" cellpadding="0">
										<tbody>
											<tr>
												<td align="center" style="border-radius: 30px;" bgcolor="#B0C92B">
													<a style="padding: 15px 25px; border-radius: 30px; border: 1px solid #B0C92B; border-image: none; color: rgb(255, 255, 255); font-family: Arial, Helvetica, sans-serif; font-size: 16px; font-weight: bold;text-decoration: none; display: inline-block;" href="' . $url_MRBS . '/edit_entry.php?id=' . $row["entry_id"] . '&lang=sv">' . 'Gå till din bokning' . '</a>
												</td>
											</tr>
										</tbody>
									</table>
								</div-->
								<div><a href="' . $url_MRBS . '/edit_entry.php?id=' . $row["entry_id"] . '&lang=sv">' . 'Gå till din bokning' . '</a></div>
								</br>';
								if($row['area_map']) {
									$html_body .= '<div><img src="cid:map" alt="map"></div>
									</br>';
								}
					$html_body .= '<div>Vänliga hälsningar</div>
								</br>
								<div>KTH Biblioteket</div>
								<div>08 - 790 70 88</div>
								<div>www.kth.se/biblioteket</div>
								';
					$inlineimage = './images/grupprum.jpg';
					$inlineimagecid = 'map';
					break;
				case "readingstudio":
					$emailfromname ="KTH Biblioteket";
					$fromadress = '';
					$toadress = '';
					$subject = 'Kvittera din lässtudio!';
					$html_body = '<div>Hej!</div>
								</br>
								<div>Du har bokat ' . $row["room_name"] . ' från ' . date("H:i",$row['start_time']) . ' till ' . date("H:i",$row['end_time']) . ', ' . utf8_encode(strftime("%A %d %B",$row['start_time'])) . '. </div>
								</br>
								<div>Kvitteringstiden för din bokning har börjat och pågår från ' . date("H:i",$row['start_time'] - 60*15) . ' till ' . date("H:i",$row['start_time'] + 60*15) . '. </div>
								</br>
								<div>
									<table border="0" cellspacing="0" cellpadding="0">
										<tbody>
											<tr>
												<td align="center" style="border-radius: 30px;" bgcolor="#B0C92B">
													<a style="padding: 15px 25px; border-radius: 30px; border: 1px solid #B0C92B; border-image: none; color: rgb(255, 255, 255); font-family: Arial, Helvetica, sans-serif; font-size: 16px;font-weight: bold;text-decoration: none; display: inline-block;" href="https://apps.lib.kth.se/webservices/mrbs/api/v1/entries/confirm/' . $confirmation_code . '?lang=sv&database=mrbs&appname=mrbs">' . 'Kvittera din bokning' . '</a>
												</td>
											</tr>
										</tbody>
									</table>
								</div>
								<!--div><a href="https://apps.lib.kth.se/webservices/mrbs/api/v1/entries/confirm/' . $confirmation_code . '?lang=sv&database=mrbs&appname=mrbs">' . 'Kvittera rummet' . '</a></div-->
								</br>
								<!--div>
									<table border="0" cellspacing="0" cellpadding="0">
										<tbody>
											<tr>
												<td align="center" style="border-radius: 30px;" bgcolor="#B0C92B">
													<a style="padding: 15px 25px; border-radius: 30px; border: 1px solid #B0C92B; border-image: none; color: rgb(255, 255, 255); font-family: Arial, Helvetica, sans-serif; font-size: 16px; font-weight: bold;text-decoration: none; display: inline-block;" href="' . $url_MRBS . '/edit_entry.php?id=' . $row["entry_id"] . '&lang=sv">' . 'Gå till din bokning' . '</a>
												</td>
											</tr>
										</tbody>
									</table>
								</div-->
								<div><a href="' . $url_MRBS . '/edit_entry.php?id=' . $row["entry_id"] . '&lang=sv">' . 'Gå till din bokning' . '</a></div>
								</br>';
								if($row['area_map']) {
									$html_body .= 		'<div><img src="cid:map" alt="map"></div>
									</br>';
								}
					$html_body .=	'<div>Vänliga hälsningar</div>
								</br>
								<div>KTH Biblioteket</div>
								<div>08 - 790 70 88</div>
								<div>www.kth.se/biblioteket</div>
								';
					$inlineimage = './images/grupprum.jpg';
					$inlineimagecid = 'map';
					break;
				case "supervision":
					$emailfromname ="KTH CAS";
					$fromadress = '';
					$toadress = '';
					$subject = 'Påminnelse om handledning'; 
					$html_body = '<div>Hej!</div>
								</br>
								<div>Du har bokat handledning från ' . date("H:i",$row['start_time']) . ' till ' . date("H:i",$row['end_time']) . ', ' . utf8_encode(strftime("%A %d %B", $row['start_time'])) . '. </div>
								</br>
								<div>Denna länk leder till din bokning, där du kan ändra eller avboka den vid behov.</div>
								</br>
								<div>
									<table border="0" cellspacing="0" cellpadding="0">
										<tbody>
											<tr>
												<td align="center" style="border-radius: 30px;" bgcolor="#B0C92B">
													<a style="padding: 15px 25px; border-radius: 30px; border: 1px solid #B0C92B; border-image: none; color: rgb(255, 255, 255); font-family: Arial, Helvetica, sans-serif; font-size: 16px; font-weight: bold;text-decoration: none; display: inline-block;" href="' . $url_MRBS . '/edit_entry.php?id=' . $row["entry_id"] . '&lang=sv">' . 'Gå till din bokning' . '</a>
												</td>
											</tr>
										</tbody>
									</table>
								</div>
								<!--div><a href="' . $url_MRBS . '/edit_entry.php?id=' . $row["entry_id"] . '&lang=sv">' . 'Gå till din bokning' . '</a></div-->
								</br>';
								if($row['area_map']) {
									$html_body .= 		'<div><img src="cid:map" alt="map"></div>
									</br>';
								}
					$html_body .=	'<div>Välkommen!</div>
								</br>
								<div>Vänliga hälsningar</div>
								</br>
								<div>KTH Centrum för akademiskt skrivande</div>
								<div>www.kth.se/cas</div>';
					break;
				case "talkingbooks":
					$emailfromname ="KTH Biblioteket";
					$fromadress = '';
					$toadress = '';
					$subject = 'Påminnelse om talboksintroduktion'; 
					$html_body = '<div>Hej!</div>
								</br>
								<div>' .
									$row['mailtext'] .
								'</div>
								</br>
								<div>' .
									'Din bokning är från ' . date("H:i",$row['start_time']) . ' to ' . date("H:i",$row['end_time']) . ', ' . date("l d F",$row['start_time']) .
								'</div>
								</br>
								<div>Denna länk leder till din bokning, där du kan ändra eller avboka den vid behov.</div>
								</br>
								<div>
									<table border="0" cellspacing="0" cellpadding="0">
										<tbody>
											<tr>
												<td align="center" style="border-radius: 30px;" bgcolor="#B0C92B">
													<a style="padding: 15px 25px; border-radius: 30px; border: 1px solid #B0C92B; border-image: none; color: rgb(255, 255, 255); font-family: Arial, Helvetica, sans-serif; font-size: 16px; font-weight: bold;text-decoration: none; display: inline-block;" href="' . $url_MRBS . '/edit_entry.php?id=' . $row["entry_id"] . '&lang=sv">' . 'Gå till din bokning' . '</a>
												</td>
											</tr>
										</tbody>
									</table>
								</div>
								<!--div><a href="' . $url_MRBS . '/edit_entry.php?id=' . $row["entry_id"] . '&lang=sv">' . 'Gå till din bokning' . '</a></div-->
								</br>';
								if($row['area_map']) {
									$html_body .= 		'<div><img src="cid:map" alt="map"></div>
									</br>';
								}
					$html_body .=	'<div>Välkommen!</div>
								</br>
								<div>Vänliga hälsningar</div>
								</br>
								<div>KTH Biblioteket</div>
								<div>www.kth.se/biblioteket</div>';
					break;
				default:
			}
		} else {
			//Engelska
			switch ($areatype){
				case "grouproom":
					$emailfromname ="KTH Library";
					$fromadress = '';
					$toadress = '';
					$subject = 'Confirm your group study room!';
					$html_body = '<div>Hi!</div>
								</br>
								<div>You have booked room ' . $row["room_number"] . ' from ' . date("H:i",$row['start_time']) . ' to ' . date("H:i",$row['end_time']) . ', ' . date("l d F",$row['start_time']) . '. </div>
								</br>
								<div>The confirmation time for your booking has started and goes on from ' . date("H:i",$row['start_time'] - 60*15) . ' to ' . date("H:i",$row['start_time'] + 60*15) .'. </div>
								</br>
								<div>
									<table border="0" cellspacing="0" cellpadding="0">
										<tbody>
											<tr>
												<td align="center" style="border-radius: 30px;" bgcolor="#B0C92B">
													<a style="padding: 15px 25px; border-radius: 30px; border: 1px solid #B0C92B; border-image: none; color: rgb(255, 255, 255); font-family: Arial, Helvetica, sans-serif; font-size: 16px; font-weight: bold;text-decoration: none; display: inline-block;" href="https://apps.lib.kth.se/webservices/mrbs/api/v1/entries/confirm/' . $confirmation_code . '?lang=en&database=mrbs&appname=mrbs">' . 'Confirm your booking' . '</a>
												</td>
											</tr>
										</tbody>
									</table>
								</div>
								<!--div><a href="https://apps.lib.kth.se/webservices/mrbs/api/v1/entries/confirm/' . $confirmation_code . '?lang=en&database=mrbs&appname=mrbs">' . 'Confirm your booking' . '</a></div-->
								</br>
								<!--div>
									<table border="0" cellspacing="0" cellpadding="0">
										<tbody>
											<tr>
												<td align="center" style="border-radius: 30px;" bgcolor="#B0C92B">
													<a style="padding: 15px 25px; border-radius: 30px; border: 1px solid #B0C92B; border-image: none; color: rgb(255, 255, 255); font-family: Arial, Helvetica, sans-serif; font-size: 16px; font-weight: bold;text-decoration: none; display: inline-block;" href="' . $url_MRBS . '/edit_entry.php?id=' . $row["entry_id"] . '&lang=en">' . 'Go to your booking' . '</a>
												</td>
											</tr>
										</tbody>
									</table>
								</div-->
								<div><a href="' . $url_MRBS . '/edit_entry.php?id=' . $row["entry_id"] . '&lang=en">' . 'Go to your booking' . '</a></div>
								</br>';
								if($row['area_map']) {
									$html_body .= 		'<div><img src="cid:map" alt="map"></div>
									</br>';
								}
					$html_body .=	'<div>Kind regards</div>
								</br>
								<div>KTH Library</div>
								<div>08 - 790 70 88</div>
								<div>www.kth.se/en/biblioteket</div>
								';
					$inlineimage = './images/grupprum.jpg';
					$inlineimagecid = 'map';
					break;
				case "readingstudio": 
					$emailfromname ="KTH Library";
					$fromadress = '';
					$toadress = '';
					$subject = 'Confirm your reading studio!';
					$html_body = '<div>Hi!</div>
								</br>
								<div>You have booked ' . $row["room_name"] . ' from ' . date("H:i",$row['start_time']) . ' to ' . date("H:i",$row['end_time']) . ', ' . date("l d F",$row['start_time']) . '. </div>
								</br>
								<div>The confirmation time for your booking has started and goes on from ' . date("H:i",$row['start_time'] - 60*15) . ' to ' . date("H:i",$row['start_time'] + 60*15) . '. </div>
								</br>
								<div>
									<table border="0" cellspacing="0" cellpadding="0">
										<tbody>
											<tr>
												<td align="center" style="border-radius: 30px;" bgcolor="#B0C92B">
													<a style="padding: 15px 25px; border-radius: 30px; border: 1px solid #B0C92B; border-image: none; color: rgb(255, 255, 255); font-family: Arial, Helvetica, sans-serif; font-size: 16px; font-weight: bold;text-decoration: none; display: inline-block;" href="https://apps.lib.kth.se/webservices/mrbs/api/v1/entries/confirm/' . $confirmation_code . '?lang=en&database=mrbs&appname=mrbs">' . 'Confirm your booking' . '</a>
												</td>
											</tr>
										</tbody>
									</table>
								</div>
								<!--div><a href="https://apps.lib.kth.se/webservices/mrbs/api/v1/entries/confirm/' . $confirmation_code . '?lang=en&database=mrbs&appname=mrbs">' . 'Confirm your booking' . '</a></div-->
								</br>
								<!--div>
									<table border="0" cellspacing="0" cellpadding="0">
										<tbody>
											<tr>
												<td align="center" style="border-radius: 30px;" bgcolor="#B0C92B">
													<a style="padding: 15px 25px; border-radius: 30px; border: 1px solid #B0C92B; border-image: none; color: rgb(255, 255, 255); font-family: Arial, Helvetica, sans-serif; font-size: 16px; font-weight: bold;text-decoration: none; display: inline-block;" href="' . $url_MRBS . '/edit_entry.php?id=' . $row["entry_id"] . '&lang=en">' . 'Go to your booking' . '</a>
												</td>
											</tr>
										</tbody>
									</table>
								</div-->
								<div><a href="' . $url_MRBS . '/edit_entry.php?id=' . $row["entry_id"] . '&lang=en">' . 'Go to your booking' . '</a></div>
								</br>';
								if($row['area_map']) {
									$html_body .= 		'<div><img src="cid:map" alt="map"></div>
									</br>';
								}
					$html_body .=	'<div>Kind regards</div>
								</br>
								<div>KTH Library</div>
								<div>08 - 790 70 88</div>
								<div>www.kth.se/en/biblioteket</div>
								';
					$inlineimage = './images/grupprum.jpg';
					$inlineimagecid = 'map';
					break;
				case "supervision":
					$emailfromname ="KTH CAW"; 
					$fromadress = '';
					$toadress = '';
					$subject = 'Reminder of supervision booking';
					$html_body = '<div>Hi!</div>
								</br>
								<div>You have booked supervision from ' . date("H:i",$row['start_time']) . ' to ' . date("H:i",$row['end_time']) . ', ' . date("l d F",$row['start_time']) . '. </div>
								</br>
								<div>This link leads to your booking where you can change or cancel it if needed.</div>
								</br>
								<div>
									<table border="0" cellspacing="0" cellpadding="0">
										<tbody>
											<tr>
												<td align="center" style="border-radius: 30px;" bgcolor="#B0C92B">
													<a style="padding: 15px 25px; border-radius: 30px; border: 1px solid #B0C92B; border-image: none; color: rgb(255, 255, 255); font-family: Arial, Helvetica, sans-serif; font-size: 16px; font-weight: bold;text-decoration: none; display: inline-block;" href="' . $url_MRBS . '/edit_entry.php?id=' . $row["entry_id"] . '&lang=en">' . 'Go to your booking' . '</a>
												</td>
											</tr>
										</tbody>
									</table>
								</div>
								<!--div><a href="' . $url_MRBS . '/edit_entry.php?id=' . $row["entry_id"] . '&lang=en">' . 'Go to your booking' . '</a></div-->
								</br>';
								if($row['area_map']) {
				$html_body .= 		'<div><img src="cid:map" alt="map"></div>
									</br>';
								}
				$html_body .=	'<div>Welcome!</div>
								</br>
								<div>Kind regards</div>
								</br>
								<div>KTH The Centre for Academic Writing</div>
								<div>www.kth.se/caw</div>';
				case "talkingbooks":
					$emailfromname ="KTH Library"; 
					$fromadress = '';
					$toadress = '';
					$subject = 'Reminder of introduction to talking books';
					$html_body = '<div>Hi!</div>
								</br>
								<div>' .
                                	$row['mailtext_en'] .
								'</div>
								</br>
								<div>' .
									'Your booking is from ' . date("H:i",$row['start_time']) . ' to ' . date("H:i",$row['end_time']) . ', ' . date("l d F",$row['start_time']) .
								'</div>
								</br>
								<div>This link leads to your booking where you can change or cancel it if needed.</div>
								</br>
								<div>
									<table border="0" cellspacing="0" cellpadding="0">
										<tbody>
											<tr>
												<td align="center" style="border-radius: 30px;" bgcolor="#B0C92B">
													<a style="padding: 15px 25px; border-radius: 30px; border: 1px solid #B0C92B; border-image: none; color: rgb(255, 255, 255); font-family: Arial, Helvetica, sans-serif; font-size: 16px; font-weight: bold;text-decoration: none; display: inline-block;" href="' . $url_MRBS . '/edit_entry.php?id=' . $row["entry_id"] . '&lang=en">' . 'Go to your booking' . '</a>
												</td>
											</tr>
										</tbody>
									</table>
								</div>
								<!--div><a href="' . $url_MRBS . '/edit_entry.php?id=' . $row["entry_id"] . '&lang=en">' . 'Go to your booking' . '</a></div-->
								</br>';
								if($row['area_map']) {
				$html_body .= 		'<div><img src="cid:map" alt="map"></div>
									</br>';
								}
				$html_body .=	'<div>Welcome!</div>
								</br>
								<div>Kind regards</div>
								</br>
								<div>KTH Library</div>
								<div>www.kth.se/en/biblioteket</div>';
					break;
				default:
			}
		}
		
		//Skicka mailet till bokaren
		$mailresponse = sendemail($row["entry_create_by"], "noreply@kth.se", "KTH Biblioteket", $subject, $html_body);
		//$mailresponse = sendemail("tholind@kth.se", "noreply@kth.se", $emailfromname, $subject, $html_body, $inlineimage, $inlineimagecid);
		if ($mailresponse == 1) {
			//logga utskicket tillfälligt under införandet
			//error_log("TASKS, Påminnelsemail skickat till: " . $row["entry_create_by"]); 
			//Sätt reminded till 1 på bokningen;
			mrbs_updateReminded($mysqli, $mysqli_MRBSnew, $task_id, $row["entry_id"], 1);
		} else {
			$mailresponse = sendemail("tholind@kth.se", "noreply@kth.se", $emailfromname, "Error sending mail to user. "  . $subject, $html_body, $inlineimage, $inlineimagecid);
			//Logga error
			InsertLogMessages($mysqli, $task_id, 1 ,$mailresponse);
		}

	}
	//sätt nästa action för task 
	update_task($mysqli, $task_id);
	//Spara info i systemlog
	//InsertLogMessages($mysqli, $task_id, 1 ,"Påminnelsemail skickat");
}

/******************************************
 * 
 * Funktion för att rensa bokningar till 
 * Centrum för Akademiskt Akrivande.
 * 
 * TODO Parametrar för hur gammal data som ska rensas, areor, bokningstyper
 * 
******************************************/
function mrbs_purgebookings($mysqli, $mysqli_MRBS, $task_id) {
	//sätt status till waiting
	UpdateTaskStatus($mysqli, $task_id, "2");
	$taskparameters = GetTaskParameters($mysqli,$task_id);
	while($row = mysqli_fetch_array($taskparameters)) {
		$notification = $row["notification"];
		$notificationemails = $row["notificationemails"];
	}
	$deleteolderthantime = strtotime(date('Y-m-d H:i:s') . "- 1 year"); //äldre än ett år jämfört med tiden just nu
	$sql =   "DELETE
				FROM mrbs_entry E, mrbs_repeat F
				INNER JOIN mrbs_room ON mrbs_room.id = E.room_id
                INNER JOIN mrbs_area ON mrbs_area.id = mrbs_room.area_id
				WHERE start_time < $deleteolderthantime
				AND mrbs_area.id IN (5)";
	//$result = mysqli_query($sql, $mysqli_MRBS); 
	InsertLogMessages($mysqli, $task_id, 1 ,$sql);
	/*while($row = mysqli_fetch_array($result)) {
		
	}
	*/
	//sätt nästa action för task 
	update_task($mysqli, $task_id);
	//Spara info i systemlog
	//InsertLogMessages($mysqli, $task_id, 1 ,"Rensat CAS-bokningar");
}

/******************************************
 * 
 * Funktion för att hämta users från UG 
 * som sparas i en ZIP-fil
 * 
******************************************/
function ug_getusers($mysqli,$task_id) {
	//sätt status till waiting
	UpdateTaskStatus($mysqli, $task_id, "2");
	$taskparameters = GetTaskParameters($mysqli,$task_id);
	while($row = mysqli_fetch_array($taskparameters)) {
		$notification = $row["notification"];
		$notificationemails = $row["notificationemails"];
	}
	
	//ta bort alma-users.zip
	
	//kör "almatools"
	//exec('c:\WINDOWS\system32\cmd.exe /c START C:\Program Files\VideoLAN\VLC\vlc.bat');
	//sätt nästa action för task 
	update_task($mysqli, $task_id);
	//Spara info i systemlog
	//InsertLogMessages($mysqli, $task_id, 1 ,"Påminnelsemail skickat till: " . $users);
}

/******************************************
 * 
 * Funktion för att rensa en FTP-mapp
 * 
******************************************/
function tasks_purgeftpfolder($mysqli,$task_id) {
	try {
		//sätt status till waiting
		UpdateTaskStatus($mysqli, $task_id, "2");
		$taskparameters = GetTaskParameters($mysqli,$task_id);
		while($row = mysqli_fetch_array($taskparameters)) {
			$notification = $row["notification"];
			$notificationemails = $row["notificationemails"];
			$payload = $row["payload"];
		}
		$payloadobject = json_decode($payload,TRUE);
		$folder = $payloadobject['folder'];
		//kör rensing
		$systemftpfolder = GetSystemSetting($mysqli, "systemftpfolder");

		//copy($systemftpfolder . "/" . $folder . "/" . $filename)
		//loopa igenom alla mappar och rensa filer äldre än...
		deleteDir($systemftpfolder . "/" . $folder, 60);
		//sätt nästa action för task 
		update_task($mysqli, $task_id);
	}
	catch(Exception $e) {
		//logga felet till databas
		InsertLogMessages($mysqli, $task_id, 2 , $e->getMessage());
		//sätt status till error
        UpdateTaskStatus($mysqli, $task_id, "4");
	}
}


/*******************************************************
 * 
 * Funktion som startar ett KTHBScript
 * 
 * Exempel på Payload: 
 * 	{
 * 		"script": "mrbs_mailreminder",
 * 		"type": "grb"
 * 	}
 * 
 * TODO: Lägg in scripten i en db-tabell så att 
 * payload(scriptnamnet) kan hämtas därifrån i 
 * webgränssnittet för tasks?
 * 
*******************************************************/
function kthbscript($mysqli, $mysqli_MRBS, $task_id, $url_MRBS) {
	//sätt status till waiting
	UpdateTaskStatus($mysqli, $task_id, "2");
	$taskparameters = GetTaskParameters($mysqli,$task_id);
	//Hämta payload som innehåller scriptnamn
	while($row = mysqli_fetch_array($taskparameters)) {
		$payload = $row["payload"];
	}
	$payloadobject = json_decode($payload,TRUE);
	
	//Kör script

	switch ($payloadobject['script'])
		{
			case "mrbs_mailreminder":
				mrbs_mailreminder($mysqli, $mysqli_MRBS, $task_id, $url_MRBS);
				break;
			case "mrbsnew_mailreminder":
				mrbsnew_mailreminder($mysqli, $mysqli_MRBS, $task_id, $url_MRBS);
				break;
			case "mrbs_purgebookings":
				mrbs_purgebookings($mysqli, $mysqli_MRBS, $task_id);
				break;
			case "ug_getusers":
				ug_getusers($mysqli,$task_id);
				break;
			case "tasks_purgeftpfolder":
				tasks_purgeftpfolder($mysqli,$task_id);
				break;
		}
}

/*******************************************************
 * 
 * Funktion som kör ett jobb i Alma.
 * 
 * Inparameter är ett jobobject i XML- eller JSONformat.
 * 
 * Alma returnerar en webhook när jobbet är färdigt:
 * 
 * https://app.lib.kth.se/alma/webhooks/webhooks.php
 * 
*******************************************************/
function runalmajob($mysqli,$task_id) {
	try {
		//sätt status till waiting
		UpdateTaskStatus($mysqli, $task_id, "2");
		$taskparameters = GetTaskParameters($mysqli,$task_id);
		while($row = mysqli_fetch_array($taskparameters)) {
			$task_name = $row["task_name"];
			$notification = $row["notification"];
			$notificationemails = $row["notificationemails"];
			$url = $row["url"];
			//lägg till format
			$format = $row["format"];
			$url .= "?format=" . $row["format"];
			//lägg till apikey och payload
			if ($row["jobtype"] == "alma")
			{
				$url .= "&apikey=" . $row["apikey"];
				$payload = $row["payload"];
			}
		}
		//lägg till run (skulle kanske ligga i url som hämtas då det är med på "apiinfo" i run job i almas gränssnitt)
		$url .= "&op=run";
		//InsertLogMessages($mysqli, $task_id, 2 ,"url: " . $url);
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_HEADER, FALSE);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
		curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/' . $format));
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		$response = curl_exec($ch);
		curl_close($ch);
		// InsertLogMessages($mysqli, $task_id, 1 ,"response: " . $response);
		// Fel från alma returneras alltid i XML-format?
		// Kontrollera om det är XML/JSON som returnerats
		// Se till att XML-fel genererar error(inte bara warning som är default)
		set_error_handler(function($errno, $errstr, $errfile, $errline) {  
			throw new Exception($errstr, $errno);  
		});
		$response = str_replace('&', '&amp;', $response);
		try {  
			$simpleXml = simplexml_load_string($response);
			restore_error_handler();
		}
		catch(Exception $e) {  
			restore_error_handler();  
		} 
		
		if (isset($simpleXml)){
			//gör om XML till json
			$json = json_encode($simpleXml);
		} else {
			//finns ingen XML i svaret så är det troligen JSON
			$json = $response;
		}
		
		//gör om json till object/array
		$responsearray = json_decode($json,TRUE);
		//InsertLogMessages($mysqli, $task_id, 2 ,"alma response jsoncoded: " . $json);
		if(!empty($responsearray['errorList'])) {
			$result = "Error";
			foreach($responsearray['errorList']['error'] as $err) {
				$almaerror .= $err . " ";
			}
			//Logga felet och sätt status på jobbet till 4 = error.
			InsertLogMessages($mysqli, $task_id, 2 ,"error från alma:" . $almaerror);
			UpdateTaskStatus($mysqli, $task_id, "4");
			//skicka mail om att jobbet gått fel
			if ($notification == "1") {
				$subject = "KTHB-Jobb. Nåt gick fel med jobbet - $task_name";
				sendemail($notificationemails,"noreply@lib.kth.se","KTH Biblioteket Tasks", $subject , $almaerror);
			}
		}
		else {
			$result = "Success";
			//Spara undan instance id för jobbet så det finns när webhook kommer tillbaks från Alma
			//Svar vid XML:
			//"additional_info":"Job no. 6289114830002456
			//Svar vid JSON:
			//"additional_info":{"value":"Job no. 6289114830002456 triggered on Sat, 21 Jan 2017 01:13:53 GMT","link":"https://api-eu.hosted.exlibrisgroup.com/almaws/v1/conf/jobs/M26710920000011/instances/6289114830002456"}
			if($format=="xml"){
				$link = $responsearray["additional_info"];
			} else {
				$link = $responsearray["additional_info"]["value"];
			}
			$index = strpos($link,"Job no. ");
			$instance_id = "";
			if ($index===false) {
				//finns inte, logga fel? 
				$instance_id = "";
			} else {
				//TODO 
				//Anpassa till dynamisk längd på instance_id??
				//har gått från 16 till 17 tecken långt.
				$instance_id = substr($link,$index+8,17);
			}
			UpdateTaskInstanceID($mysqli, $task_id, $instance_id);	
		}
		return $result;
	}
	catch(Exception $e) {
		//logga felet till databas
		InsertLogMessages($mysqli, $task_id, 2 , $e->getMessage());
		//sätt status till error
        UpdateTaskStatus($mysqli, $task_id, "4");
	}
}

/**********************************************************************
 * 
 * Funktion som kopierar en tasks resultatfil
 * t ex TXT eller XML-filer skapade av körda almajobb
 * 
 * (från KTHB systemftpmappen till systemmappen).
 * 
 * Därifrån kan sedan filen  t ex 
 *  skickas till extern ftp
 * 	publicera på webserver(apps)
 *  övrigt
 * 
 * Det här steget ska alltid ske när resultatet av ett jobb är en fil.
 * 
 * Parametrar hämtas från tasks.
 * 
**********************************************************************/
function copyfile($mysqli,$task_id) {
	try {
		$systemftpfolder = GetSystemSetting($mysqli, "systemftpfolder");
		$systemfolder = GetSystemSetting($mysqli, "systemfolder");
		//sätt status till waiting
		UpdateTaskStatus($mysqli, $task_id, "2");
		$taskparameters = GetTaskParameters($mysqli,$task_id);
		while($row = mysqli_fetch_array($taskparameters)) {
			$task_name = $row["task_name"];
			$folder = $row["folder"];
			$filename = $row["filename"];
			if ($row["copy_destination"]!=null)
			{
				$copy_destination = $row["copy_destination"];
			}
			else
			{
				$copy_destination = "";
			}
			$notification = $row["notification"];
			$notificationemails = $row["notificationemails"];
		}
		if ($copy_destination == "")
		{
			//kopiera fil
			copy($systemftpfolder . "/" . $folder . "/" . $filename, $systemfolder . "/" . $folder . "/" . $filename); // finns filen redan blir den överskriven
		} else
		{
			//kopiera fil
			//InsertLogMessages($mysqli, $task_id, 1 ,  $systemftpfolder . "/" . $folder . "/" . $filename, $systemfolder . "/" . $folder . "/" . $copy_destination);
			copy($systemftpfolder . "/" . $folder . "/" . $filename, $systemfolder . "/" . $folder . "/" . $copy_destination); // finns filen redan blir den överskriven
			
			//sätt filename = copy_destination för att nästa steg ska plocka upp rätt filnamn
			UpdateFileName($mysqli, $task_id, $copy_destination);
		}
		//sätt nästa action för task
		update_task($mysqli, $task_id);
	}
	catch(Exception $e) {
		//logga felet till databas
		InsertLogMessages($mysqli, $task_id, 2 , $e->getMessage());
		//sätt status till error
        UpdateTaskStatus($mysqli, task_id, "4");
	}
}

/*******************************************
 * 
 * Funktion som zippar en tasks resultatfil 
 * som har kopierats till systemmappen
 * 
 * Parametrar hämtas från tasks.
 * 
*******************************************/
function zipfile($mysqli,$task_id) {
	try {
		$systemfolder = GetSystemSetting($mysqli, "systemfolder");
		//sätt status till waiting
		UpdateTaskStatus($mysqli, $task_id, "2");
		$taskparameters = GetTaskParameters($mysqli,$task_id);
		while($row = mysqli_fetch_array($taskparameters)) {
			$task_name = $row["task_name"];
			$folder = $row["folder"];
			$filename = $row["filename"];
			$zipfilename = $row["zip_destination"];
			$notification = $row["notification"];
			$notificationemails = $row["notificationemails"];
		}
		$zip = new ZipArchive();
	
		if ($zip->open($systemfolder . "/" . $folder . "/" . $zipfilename, ZIPARCHIVE::CREATE | ZIPARCHIVE::OVERWRITE)!==TRUE) {
			InsertLogMessages($mysqli, $task_id, 2 , "Error zipping file");
			UpdateTaskStatus($mysqli, task_id, "4");
			//skicka mail om att jobbet gått fel
			if ($notification == "1") {
				$subject = "KTHB-Jobb. Nåt gick fel med jobbet - $task_name";
				$error = "Error zipping file";
				sendemail($notificationemails,"noreply@lib.kth.se","KTH Biblioteket Tasks", $subject , $error);
			}
		} else {
			if ($zip->addFile($systemfolder . "/" . $folder . "/" . $filename,$filename)!==TRUE) {
				InsertLogMessages($mysqli, $task_id, 2 , "Error zipping file, " . $systemfolder . "/" . $folder . "/" . $filename ." does not exist."); //lägg till $filename som local name i zipfilen för att slippa få med fulla sökvägar.
				UpdateTaskStatus($mysqli, task_id, "4");
				//skicka mail om att jobbet gått fel
				if ($notification == "1") {
					$subject = "KTHB-Jobb. Nåt gick fel med jobbet - $task_name";
					$error = "Error zipping file, " . $systemfolder . "/" . $folder . "/" . $filename ." does not exist.";
					sendemail($notificationemails,"noreply@lib.kth.se","KTH Biblioteket Tasks", $subject , $error);
				}
			} 
			$zip->close();
		}
		
		//sätt nästa action för task
		update_task($mysqli, $task_id);
	}
	catch(Exception $e) {
		//logga felet till databas
		InsertLogMessages($mysqli, $task_id, 2 , $e->getMessage());
		//echo 'Message: ' .$e->getMessage();
		//sätt status till error
        UpdateTaskStatus($mysqli, task_id, "4");
	}
}

/******************************************************
 * 
 * Funktion som publicerar en fil från jobbets 
 * definierade mapp i systemmappen 
 * till en mapp på KTHB's webbserver(APPS)
 * 
 * Parametrar hämtas från tasks.
 * 
******************************************************/
function publishfiletowebserver($mysqli,$task_id) {
	try {
		$systemfolder = GetSystemSetting($mysqli, "systemfolder");
		$appsrootfolder = GetSystemSetting($mysqli, "webrootfolder");
		//sätt status till waiting
		UpdateTaskStatus($mysqli, $task_id, "2");
		$taskparameters = GetTaskParameters($mysqli,$task_id);
		while($row = mysqli_fetch_array($taskparameters)) {
			$task_name = $row["task_name"];
			$appsfolder = $row["webfolder"];
			$appsfilename = $row["webfilename"];
			$folder = $row["folder"];
			$filename = $row["filename"];
			$notification = $row["notification"];
			$notificationemails = $row["notificationemails"];
		}
		if (copy($systemfolder . "/" . $folder . "/" . $filename, $appsrootfolder . "/" . $appsfolder . "/" . $appsfilename)!==TRUE) { ; // finns filen redan blir den överskriven
			InsertLogMessages($mysqli, $task_id, 2 , "Error publishfiletowebserver file: " . $systemfolder . "/" . $folder . "/" . $filename . "," . $appsrootfolder . "/" . $appsfolder . "/" . $appsfilename);
			UpdateTaskStatus($mysqli, task_id, "4");
			//skicka mail om att jobbet gått fel
			if ($notification == "1") {
				$subject = "KTHB-Jobb. Nåt gick fel med jobbet - $task_name";
				$error = "Error publishfiletowebserver file: " . $systemfolder . "/" . $folder . "/" . $filename . "," . $appsrootfolder . "/" . $appsfolder . "/" . $appsfilename;
				sendemail($notificationemails,"noreply@lib.kth.se","KTH Biblioteket Tasks", $subject , $error);
			}
		} else {
			//success
		}
		
		
		//sätt nästa action för task
		update_task($mysqli, $task_id);
	}
	catch(Exception $e) {
		//logga felet till databas
		InsertLogMessages($mysqli, $task_id, 2 , $e->getMessage());
		//echo 'Message: ' .$e->getMessage();
		//sätt status till error
        UpdateTaskStatus($mysqli, task_id, "4");
	}
}

/************************************************************
 * 
 * Funktion som skickar en zippad fil från jobbets 
 * definierade mapp i systemmappen till extern FTP-server
 * 
 * Parametrar hämtas från tasks.
 * 
************************************************************/
function sendzipfiletoftp($mysqli,$task_id) {
	try {
		$systemfolder = GetSystemSetting($mysqli, "systemfolder");
		//sätt status till waiting
		UpdateTaskStatus($mysqli, $task_id, "2");
		$taskparameters = GetTaskParameters($mysqli,$task_id);
		while($row = mysqli_fetch_array($taskparameters)) {
			$task_name = $row["task_name"];
			$folder = $row["folder"];
			$filename = $row["filename"];
			$zipfilename = $row["zip_destination"];
			$ftpserver = $row["ftp_server"];
			$ftpuser = $row["ftp_user"];
			$ftppassword = $row["ftp_password"];
			$notification = $row["notification"];
			$notificationemails = $row["notificationemails"];
		}
		$conn_id = ftp_connect($ftpserver);
		$login_result = ftp_login($conn_id, $ftpuser, $ftppassword);
		if (ftp_put($conn_id, $zipfilename, $systemfolder . "/" . $folder . "/" . $zipfilename, FTP_BINARY)) { //(connection, serverfil, fil att skicka, filtyp) FTP_BINARY = zipfiler t ex.
			ftp_close($conn_id);
		} else {
			//sätt status till error
			InsertLogMessages($mysqli, $task_id, 2 , "Error in file FTP");
			UpdateTaskStatus($mysqli, task_id, "4");
			//skicka mail om att jobbet gått fel
			if ($notification == "1") {
				$subject = "KTHB-Jobb. Nåt gick fel med jobbet - $task_name";
				$error = "Error in file FTP";
				sendemail($notificationemails,"noreply@kth.se","KTH Biblioteket Tasks", $subject , $error);
			}
			ftp_close($conn_id);
		}
		
		//sätt nästa action för task
		update_task($mysqli, $task_id);
	}
	catch(Exception $e) {
		//logga felet till databas
		InsertLogMessages($mysqli, $task_id, 2 , $e->getMessage());
		//echo 'Message: ' .$e->getMessage();
		//sätt status till error
        UpdateTaskStatus($mysqli, task_id, "4");
	}
}

/*****************************************************
 * 
 * Funktion som sätter nästa action och status i tasks.
 * 
 * Inparameter: task_id
 * 
*****************************************************/
function update_task($mysqli, $task_id) {
	//hämta alla actions som aktuell task ska utföra
	$sql = "SELECT zip,copy,ftp,publishfiletowebserver
            FROM tasks 
            WHERE tasks.id = $task_id";
	$result = mysqli_query($mysqli,$sql);
	while($row = mysqli_fetch_array($result)) {
		$zip = $row['zip'];
		$copy = $row['copy'];
		$ftp = $row['ftp'];
		$publishfiletowebserver = $row['publishfiletowebserver'];
	}
	
	//hämta aktuell action och instance_id
                
	$sql = "SELECT actions.name, tasks.instance_id
			FROM tasks
			INNER JOIN actions on actions.id = tasks.action_id 
			WHERE tasks.id = $task_id";
	$result = mysqli_query($mysqli,$sql);
	while($row = mysqli_fetch_array($result)) {
		$currentactionname = $row['name'];
		$instance_id = $row['instance_id'];
	}
	
	$nextaction = "";
	//definiera next action enligt flöde:
	// alma/webscript --> copy --> zip --> publishfiletowebserver --> ftp
	switch ($currentactionname)
	{
		case "alma": case "webscript":
			if ($copy=="1")
			{
				$nextaction = "copy";
				break;
			}
			else
			if ($zip=="1")
			{
				$nextaction = "zip";
				break;
			}
			else
			if ($publishfiletowebserver=="1")
			{
				$nextaction = "publishfiletowebserver";
				break;
			}
			else
			if ($ftp=="1")
			{
				$nextaction = "ftp";
				break;
			}
			else { break; }

		case "copy":
			if ($zip=="1")
			{
				$nextaction = "zip";
				break;
			}
			else
			if ($publishfiletowebserver=="1")
			{
				$nextaction = "publishfiletowebserver";
				break;
			}
			else
			if ($ftp=="1")
			{
				$nextaction = "ftp";
				break;
			}
			else { break; }
		case "zip":
			if ($publishfiletowebserver=="1")
			{
				$nextaction = "publishfiletowebserver";
				break;
			}
			else
			if ($ftp=="1")
			{
				$nextaction = "ftp";
				break;
			}
			else { break; }
		case "publishfiletowebserver":
			if ($ftp=="1")
			{
				$nextaction = "ftp";
				break;
			}
			else { break; }
	}
	$status_id = 0;
	if ($nextaction=="") {
		//finns ingen nästa action så anses jobbet klart, sätt action till init, sätt status till finished, återställ eventuell "islongrunning"
		$nextaction = "init";
		$status_id = 3;
		UpdateTaskislongrunning($mysqli, $task_id, 0);
		//sätt sluttid
        $finishedtime=date('Y-m-d H:i:s');
		
		// skicka notifications
		$taskparameters = GetTaskParameters($mysqli,$task_id);
		while($row = mysqli_fetch_array($taskparameters)) {
			$task_name = $row["task_name"];
			$notification = $row["notification"];
			$notificationemails = $row["notificationemails"];
			$jobtype = $row["jobtype"];
		}
		if ($notification == "1") {
			$webhookspayload = "";
			$webhooksinfo = "";
			$subject = "KTHB-Jobb. Jobb färdigt - $task_name";
			$message = "<div>$task_name - $finishedtime</div>";
			// ta med svar från webhooks om det är t ex almajobb
			if($jobtype == "alma") {
				$webhookspayload = GetWebhooks($mysqli, $instance_id);
			
				$json_data = json_decode($webhookspayload);
				$id = $json_data->id;
				$action = $json_data->action; //ex: "JOB_END"
				$time = $json_data->time;
				$job_instance_name = $json_data->job_instance->name;
				$job_instance_submitted_by_value = $json_data->job_instance->submitted_by->value; //email(primaryid?)
				$job_instance_submit_time = $json_data->job_instance->submit_time;
				$job_instance_start_time = $json_data->job_instance->start_time;
				$job_instance_end_time = $json_data->job_instance->end_time;
				$job_instance_progress = $json_data->job_instance->progress;
				$job_instance_status_value = $json_data->job_instance->status->value; //ex: "COMPLETED_SUCCESS"
				$job_instance_status_desc = $json_data->job_instance->status->desc;
				$job_instance_status_date = $json_data->job_instance->status_date;
				$job_instance_job_info_id = $json_data->job_instance->job_info->id;
				$job_instance_link = $json_data->job_instance->link;
				
				//loopa alert
				foreach ($json_data->job_instance->alert as $alert) {
					$webhooksinfo .= '<div>' . $alert->desc . '</div>';
				}
				//loopa counter
				foreach ($json_data->job_instance->counter as $counter) {
					$webhooksinfo .= '<div>' . $counter->type->desc . ': ' . $counter->value .  '</div>';
				}
				
				$message = $message . $webhooksinfo;
			}
			sendemail($notificationemails,"noreply@lib.kth.se","KTH Biblioteket Tasks", $subject , $message);
		}
		
	} else {
		//status = running
		$status_id = 1;
		$finishedtime = "1000-01-01";
	}
	$sql = "UPDATE tasks, actions 
			SET tasks.action_id = actions.id, 
			status_id = $status_id, 
			finished_time = '$finishedtime'
			WHERE tasks.id = $task_id
			AND actions.name='$nextaction'";
	$result = mysqli_query($mysqli,$sql);
	return "Zip: $zip, Copy: $copy, Ftp: $ftp, SQL: $sql , Result: $result ";
}

/************************************************************************
 * 
 * Funktion som hämtar senaste webhookspayload för en task via instance_id
 * 
************************************************************************/
function GetWebhooks($mysqli, $instance_id) {
	$query = "SELECT *
			  FROM webhooks
			  WHERE instance_id = '$instance_id '
			  ORDER BY id DESC LIMIT 1";
	$result = mysqli_query($mysqli,$query);
	while($row = mysqli_fetch_array($result)) {
		return $row["payload"];
	}
}


/***********************************************
 * 
 * Funktion som hämtar systeminställningar
 * 
***********************************************/
function GetSystemSetting($mysqli, $name) {
	$query = "SELECT *
			  FROM systemsettings
			  WHERE name = '$name'";
	$result = mysqli_query($mysqli,$query);
	while($row = mysqli_fetch_array($result)) {
		return $row["value"];
	}
}


/***********************************************
 * 
 * Funktion som hämtar systemlog för viss task
 * 
***********************************************/
function GetSystemLog($mysqli, $task_id) {
	$query = "SELECT *
			  FROM systemlog
			  WHERE task_id = '$task_id'";
	$result = mysqli_query($mysqli,$query);
	return $result;
}

/***********************************************
 * 
 * Funktion som hämtar vilka jobb som ska startas
 * utifrån vilken tid det är vid anropet. 
 * 
 * Alla som har en starttid mindre än nuvarande tid 
 * ska startas.
 * 
 * TODO: Lägg till ett intervall inom vilket
 * jobbet får startas
 * 
***********************************************/
function GetScheduledTasks($mysqli) {
	$currenttime = date('Y-m-d H:i:s');
	$query =   "SELECT start_time, tasks.id, tasks.name, actions.name, status.status,jobtypes.name as jobtype_name
				FROM tasks
				INNER JOIN actions on actions.id = tasks.action_id
				INNER JOIN status on status.id = tasks.status_id
				INNER JOIN jobtypes on jobtypes.id = tasks.jobtype_id
				WHERE actions.name = 'init'
				AND status.status = 'finished'
				AND start_time < '$currenttime'
				AND enabled = 1";
				/*
				AND running_hours_start <= $currenttime hour
				AND running_hours_end >= $currenttime hour

				*/
	$result = mysqli_query($mysqli,$query);
	return $result;
}

/***********************************************
 * 
 * Funktion som hämtar vilka jobb som ska startas
 * utifrån att action = "runnow"
 * 
***********************************************/
function GetRunNowTasks($mysqli) {
	$query =   "SELECT start_time, tasks.id, tasks.name, actions.name, status.status,jobtypes.name as jobtype_name
				FROM tasks
				INNER JOIN actions on actions.id = tasks.action_id
				INNER JOIN status on status.id = tasks.status_id
				INNER JOIN jobtypes on jobtypes.id = tasks.jobtype_id
				WHERE actions.name = 'runnow'
				AND status.status = 'finished'
				AND enabled = 1";
	$result = mysqli_query($mysqli,$query);
	return $result;
}

/***********************************************
 * 
 * Funktion som hämtar aktuella actions 
 * för alla jobb 
 * 
***********************************************/
function GetAllTaskActions($mysqli) {
	$query = "SELECT tasks.id, actions.name, tasks.status_id, tasks.started_time, status.description as statusdescription, tasks.islongrunning FROM tasks
			  INNER JOIN actions on actions.id = tasks.action_id 
			  INNER JOIN status on status.id = tasks.status_id";
	$result = mysqli_query($mysqli,$query);
	return $result;
}

/***********************************************
 * 
 * Funktion som hämtar parametrar för jobbet
 * 
***********************************************/
function GetTaskParameters($mysqli,$task_id) {
	$query = "SELECT tasks.name as task_name, url,apikey,payload,formats.name as format, jobtypes.name as jobtype,
			 zip,folder,filename,zip_destination,ftp_server,ftp_user,
			 ftp_password,webfolder,webfilename,copy_destination,
			 notification, notificationemails
			 FROM tasks
			 INNER JOIN formats ON tasks.format_id = formats.id 
			 INNER JOIN jobtypes ON tasks.jobtype_id = jobtypes.id
			 WHERE tasks.id = $task_id";
	$result = mysqli_query($mysqli,$query);	
	return $result;
}

/***********************************************
 * 
 * Funktion som sätter InstanceID för jobbet, som 
 * används som referens till specifik instans 
 * av almajobb.
 * 
***********************************************/
function UpdateTaskInstanceID($mysqli, $task_id, $instance_id) {
	$query = "UPDATE tasks
			  SET instance_id = $instance_id 
			  WHERE tasks.id= $task_id";
	$result = mysqli_query($mysqli,$query);
	return $result;
}

/***********************************************
 * 
 * Funktion som sätter action för jobbet
 * 
***********************************************/
function UpdateTaskAction($mysqli, $task_id, $name) {
	$query = "UPDATE tasks, actions 
			  SET tasks.action_id = actions.id 
			  WHERE tasks.id= $task_id AND actions.name='$name'";
	$result = mysqli_query($mysqli,$query);
	return $result;
}

/***********************************************
 * 
 * Funktion som sätter statusen för jobbet
 * 
***********************************************/
function UpdateTaskStatus($mysqli, $task_id, $status_id) {
	$query = "UPDATE tasks
			  SET status_id = $status_id
			  WHERE tasks.id= $task_id";
	$result = mysqli_query($mysqli,$query);
	return $result;
}

/***********************************************
 * 
 * Funktion som sätter nästa starttid för jobbet.
 * Lägger på det intervall som jobbet har som
 * inställning(interval) till senaste starttid.
 * 
 * Om nästa starttid är mindre än nuvarande tid 
 * sätt starttidens datum till nuvarande datum 
 * och starttidens timme till nuvarande timme + 1.
 * 
 * TODO
 * Anpassa efter vilken veckodag ett "weekly"
 * jobb har som parameter.
 * Kräver lite designförändring.
 * 
***********************************************/
function UpdateTaskStartTime($mysqli, $task_id) {
	
	$query =   "SELECT start_time, seconds 
				FROM tasks, intervals
				WHERE tasks.id = $task_id
				AND tasks.interval_id = intervals.id";
	$result = mysqli_query($mysqli,$query);

	while($row = mysqli_fetch_array($result)) {
		$currentstarttime = $row["start_time"];
		$currentinterval = $row["seconds"];
	}

	$currentstarttimeinsec=strtotime($currentstarttime);
	
	if (date('Y-m-d H:i:s',$currentstarttimeinsec+$currentinterval) < date('Y-m-d H:i:s')) {
		$nextHour = date('H')+1;
		$adjustedstarttime = date('Y-m-d') . ' ' . $nextHour . ':' . date('i:s',$currentstarttimeinsec);
		$query = "UPDATE tasks, intervals 
			  SET tasks.start_time = '$adjustedstarttime'
			  WHERE tasks.id = $task_id
			  AND tasks.interval_id = intervals.id";
	} else {
		$query = "UPDATE tasks, intervals 
			  SET tasks.start_time = DATE_ADD(tasks.start_time,INTERVAL intervals.seconds SECOND) 
			  WHERE tasks.id = $task_id
			  AND tasks.interval_id = intervals.id";
	}
	
	$result = mysqli_query($mysqli,$query);
	return $result;
}

/***********************************************
 * 
 * Funktion som sätter starttiden för jobbet
 * 
***********************************************/
function UpdateTaskstartedTime($mysqli, $task_id) {
	$query = "UPDATE tasks
			  SET tasks.started_time = now()
			  WHERE tasks.id = $task_id";
	$result = mysqli_query($mysqli,$query);
	return $result;
}

/***********************************************
 * 
 * Funktion som uppdaterar filnamn för jobbet
 * 
***********************************************/
function UpdateFileName($mysqli, $task_id, $filename) {
	$query = "UPDATE tasks
			  SET tasks.filename = '$filename'
			  WHERE tasks.id = $task_id";
	$result = mysqli_query($mysqli,$query);
	return $result;
}

/***********************************************
 * 
 * Funktion som sätter sluttiden för jobbet
 * 
***********************************************/
function UpdateTaskfinishedTime($mysqli, $task_id,$reset) {
	if ($reset == 1) {
		$query = "UPDATE tasks
				  SET tasks.finished_time = '1000-01-01'
				  WHERE tasks.id = $task_id";
	} else {
		$query = "UPDATE tasks
				  SET tasks.finished_time = now()
				  WHERE tasks.id = $task_id";	
		}
	$result = mysqli_query($mysqli,$query);
	return $result;
}

/***********************************************
 * 
 * Funktion som sätter/återställer om jobbet 
 * har körts länge(gränsvärde? 1 timme?)
 * 
***********************************************/
function UpdateTaskislongrunning($mysqli, $task_id, $islongrunning) {
	$query = "UPDATE tasks
			  SET tasks.islongrunning = $islongrunning
			  WHERE tasks.id = $task_id";
	$result = mysqli_query($mysqli,$query);
	return $result;
}

/***********************************************
 * 
 * Funktion som sparar meddelanden i systemloggen
 * 
***********************************************/
function InsertLogMessages($mysqli, $task_id, $logtype_id, $message) {
	$query = "INSERT INTO systemlog(task_id,logtype_id,message)
			  VALUES($task_id,$logtype_id,'$message')";
	$result = mysqli_query($mysqli,$query);
	return $result;
}

/******************************************
 * 
 * Funktion som skickar mail.
 * 
 * Inparametrar: Tilladress, frånadress, frånnamn, ämne och innehåll
 * 
 * Dela upp en anropet om $notificationemails är en sträng
 * 	sendemail("tholind@kth.se,msandgre@kth.se,patja@kth.se", $from, $fromname, $subject, $message)
 * 	$addresses = explode(",",$notificationemails);
 
******************************************/
function sendemail($to, $from, $fromname, $subject, $bodytext, $inlineimage = '', $inlingeimagecid = '') {
	$mail = new PHPMailer();

	$mail->isSMTP();
	$mail->Host = "smtp.kth.se";
	$mail->SMTPAuth   = FALSE;
	$mail->SMTPSecure = "tls";
	
	$mail->CharSet = 'UTF-8';
	$mail->From      = $from;
	$mail->FromName  = $fromname;
	$mail->Subject   = $subject;
	$mail->Body = $bodytext;
	$mail->msgHTML($bodytext);
	$addresses = explode(",",$to);
	
	if(!empty($addresses)){
		foreach ($addresses as $address) {
			$mail->AddAddress($address);
		}
	} else {
		//Ska inte hända!
		throw new Exception('No emails found!');
	}

	if($inlineimage != '' && $inlingeimagecid != '') {
		$mail->addEmbeddedImage($inlineimage, $inlingeimagecid);	
	}
	$mailresponse = $mail->Send();
	return $mailresponse;
}

/**********************************************************
 * 
 * Huvudkod som körs när sidan anropas
 * 
**********************************************************/
if ( $_GET['token'] == $token ) {
	// Hämta vilka tasks som har action = "runnow" (dvs startats manuellt, "kör nu", via https://apps.lib.kth.se/tasks/index.php)
	$result = GetRunNowTasks($mysqli);
	while($row = mysqli_fetch_array($result)) {
		//sätt action = (alma/webscript)
		UpdateTaskAction($mysqli, $row["id"], $row["jobtype_name"]);
		//sätt status till running
		UpdateTaskStatus($mysqli, $row["id"], "1");
		//sätt starttid
		UpdateTaskstartedTime($mysqli, $row["id"]);
		//nollställ sluttid
		UpdateTaskfinishedTime($mysqli, $row["id"],1);
		//sätt INTE nästa starttid
		//UpdateTaskStartTime($mysqli, $row["id"]);
	}

	// Hämta vilka tasks som är schedulerade och ska startas utfrån aktuell tid
	$result = GetScheduledTasks($mysqli);
	while($row = mysqli_fetch_array($result)) {
		//sätt action = (alma/webscript)
		UpdateTaskAction($mysqli, $row["id"], $row["jobtype_name"]);
		//sätt status till running
		UpdateTaskStatus($mysqli, $row["id"], "1");
		//sätt starttid
		UpdateTaskstartedTime($mysqli, $row["id"]);
		//nollställ sluttid
		UpdateTaskfinishedTime($mysqli, $row["id"],1);
		//sätt nästa starttid
		UpdateTaskStartTime($mysqli, $row["id"]);
	}

	//Hämta alla tasks och deras nästa action
	$result = GetAllTaskActions($mysqli);
	while($row = mysqli_fetch_array($result)) {
		$task_id = $row["id"];
		//om status = "running"
		if ($row["status_id"]== "1")
		{
			switch ($row["name"])
			{
				case "alma":
					runalmajob($mysqli, $task_id);
					break;
				case "copy":
					copyfile($mysqli, $task_id);
					break;
				case "zip":
					zipfile($mysqli, $task_id);
					break;
				case "publishfiletowebserver":
					publishfiletowebserver($mysqli, $task_id);
					break;
				case "ftp":
					sendzipfiletoftp($mysqli, $task_id);
					break;
				case "webscript":
					//MakeRequest($mysqli, $task_id);
					break;
				case "KTHBScript":
					KTHBScript($mysqli, $mysqli_MRBS, $task_id, $url_MRBS);
					break;
			}
		}
	}

	//Kontrollera om ett jobb "hängt" sig och meddela sysadmins(mail till jobbets uppsatta emailadresser)
	//TODO. Sätt timeouttider på jobbet i databasen i st f hårkodat som nedan.
	$result = GetAllTaskActions($mysqli);
	while($row = mysqli_fetch_array($result)) {
		$task_id = $row["id"];
		//om status = "running"/"waiting"
		if ($row["status_id"]== "1" || $row["status_id"]== "2") {
			if (strtotime('now') > strtotime('+1 hours', strtotime($row["started_time"])) && $row["islongrunning"] != "1") {
			//if (strtotime('now') > strtotime('+7 minutes', strtotime($row["started_time"])) && $row["islongrunning"] != "1") {
				// skicka notifications
				$taskparameters = GetTaskParameters($mysqli, $task_id);
				while($taskparameterrow = mysqli_fetch_array($taskparameters)) {
					$task_name = $taskparameterrow["task_name"];
					$notification = $taskparameterrow["notification"];
					$notificationemails = $taskparameterrow["notificationemails"];
					$jobtype = $taskparameterrow["jobtype"];
				}
				if ($notification == "1") {
					$subject = "KTHB-Jobb. Nåt kanske är fel med jobbet - $task_name";
					$error = "Jobbet startades " . $row["started_time"] . " och har pågått i över en timme, status: " . $row["statusdescription"];
					UpdateTaskislongrunning($mysqli, $task_id, 1);
					sendemail("tholind@kth.se","noreply@lib.kth.se","KTH Biblioteket Tasks", $subject , $error);
				}
				break;
			}
		}

	}

	//avsluta
	mysqli_close($mysqli);
	mysqli_close($mysqli_MRBS);
} else {
	InsertLogMessages($mysqli, 0, 2 ,"Error, not authorized. Wrong or missing token.");
	echo "Error, not authorized. Wrong or missing token.";
}
?>