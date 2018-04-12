<?php
require('config.php'); //innehåller API-KEY + error reporting

/**********
Funktion som hanterar felmeddelanden från ALMA

***********/
function checkifAlmaerror($response,$format) {
	//JSON
	$responsearray = json_decode($response,TRUE);
	if(!empty($responsearray['errorList'])) {
		$result = "Error";
		$data = array(
		  "result"  => $result,
		  "message" => $responsearray['errorList']['error'][0]['errorMessage']
		);
	}
	else {
		$result = "Success";
		$data = array(
		  "result"  => $result,
		  "message" => "No Errors"
		);
	}
	$json_data = json_encode($data);
	$error = $json_data;	
	return $error ;
}
/********** 

Funktion som hämtar användarinformation från alma utifrån angivet ID 

**********/
function getuser($user_id) {
	global $api_key;
	$ch = curl_init();
	$url = 'https://api-eu.hosted.exlibrisgroup.com/almaws/v1/users/' . $user_id;
	$queryParams = '?' . urlencode('user_id_type') . '=' . urlencode('all_unique') . '&' . urlencode('view') . '=' . urlencode('full') . '&' . urlencode('apikey') . '=' . urlencode($api_key) . '&' . urlencode('format') . '=' . urlencode('json');
	curl_setopt($ch, CURLOPT_URL, $url . $queryParams);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	curl_setopt($ch, CURLOPT_HEADER, FALSE);
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
	$response = curl_exec($ch);
	curl_close($ch);
	return $response;
}
$activecataloger = false;
$loggedin = false;
setcookie("cas", "1", time() + (86400 * 30), "/");
session_name("KTHB_TASKS_SESSID");
if (!isset($_SESSION)) {
	session_start();
	
}
//print_r($_SESSION);
$username = "";
//om inte inloggad så visa länk till inloggning i toolbar
if(!isset($_SESSION['kth_id'])) {
			//header("location: /tandem/general_login.php?formlanguage=" . $formlanguage) ; //Bara Tandemmappen är auktoriserad att logga in via CAS(anmäl "aktiverapatron" till ITA?)
} else {
	if($_SESSION['kth_id']!="")
	{
		$currentuser = getuser($_SESSION['kth_id']);
		$almaresponse = checkifAlmaerror($currentuser,"json");
		$jsonalmaresponse = json_decode($almaresponse);
		if ($jsonalmaresponse->result == "Error") {
			print $almaresponse;
		} else {
			$source = json_decode($currentuser,TRUE);
			$username = $source['full_name'];
			$loggedin = true;
			//loopa igenom roller och hitta "204" (cataloger)
			$index = 0;
			foreach ($source['user_role'] as $value) {
				if($value['role_type']['value'] == "204") {
					if($value['status']['value'] == "ACTIVE") {
						$activecataloger = true;
					}
				}
				$index++;
			}
		}
	}
}
?>
<!doctype html>
		<html lang="en">
			<head>
				<meta charset="UTF-8">
				<title>Document</title>
				<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
				<link rel="stylesheet" href="http://ajax.googleapis.com/ajax/libs/angular_material/1.1.0/angular-material.min.css">
				<link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Roboto:300,400,500,700,400italic">
				<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
				<style>
					body {
						    font-size: 16px;
							text-rendering: optimizeLegibility;
							line-height: 1.5em;
							color: #444;
							width: 100%;
							margin: 0;
							padding: 0;
							background: #f6f6f6;
					}
					dsiv {
						padding: 10px;
					}
					buttosn {
						border-radius: 10px;
						-moz-border-radius: 10px;
						-webkit-border-radius: 10px;
						color: #ffffff;
						background-color: #b0c92b !important; /* motverka hover visited från KTH-css */
						min-width: 60px;
						font-weight: bold;
						height: 25px;
						border: 0px;
						cursor: pointer;
					}
					
					table {
						margin-bottom: 1.5em;
						margin-top: 1.5em;
						width: 100%;
						max-width: 100%;
						margin-bottom: 1.5em;
						border-collapse: collapse;
						border-spacing: 0;
					}
					
					table colgroup + thead tr:first-child th, table colgroup + thead tr:first-child td, table thead:first-child tr:first-child th, table thead:first-child tr:first-child td, .row:first-child {
						border-top: 0;
					}

					table thead th {
						vertical-align: bottom;
					}

					table th, table td {
						padding: 5px;
					}
					table th{
						background-color: #e9e9e9;
						color: #5a5b5b;
						font-size: 13px;
						font-weight: bold;
						text-align: center;
					}
					
					.header {
						background-color: #e9e9e9;
						color: #5a5b5b;
						font-size: 13px;
						font-weight: bold;
						text-align: center;
					}
					
					table td {
						padding: 5px;
						1vertical-align: top;
						border-top: 1px solid #eee;
						font-size: 1em;
						line-height: 1.5em;
						text-align: left;
					}
					
					.row {
						padding: 5px;
						1vertical-align: top;
						font-size: 1em;
						line-height: 1.5em;
						text-align: left;
					}
					
					table, th, td, .header, .row {
						border: 1px solid #c0bfc0;
						direction: ltr;
					}
					
					.material-icons {
					  font-family: 'Material Icons';
					  font-weight: normal;
					  font-style: normal;
					  font-size: 24px;  /* Preferred icon size */
					  display: inline-block;
					  line-height: 1;
					  text-transform: none;
					  letter-spacing: normal;
					  word-wrap: normal;
					  white-space: nowrap;
					  direction: ltr;

					  /* Support for all WebKit browsers. */
					  -webkit-font-smoothing: antialiased;
					  /* Support for Safari and Chrome. */
					  text-rendering: optimizeLegibility;

					  /* Support for Firefox. */
					  -moz-osx-font-smoothing: grayscale;

					  /* Support for IE. */
					  font-feature-settings: 'liga';
					  
					  
					}
					md-fab-toolbar.md-right md-fab-trigger.align-with-text {
						left: 7px; }
					md-fab-toolbar.md-is-open {
						z-index: 1;
						1transition: z-index 0s linear .1s,opacity 0s ease 0s;
					}
					md-fab-toolbar{
						position: absolute;
						top: 0px;
						right: 100px;
						background-color: rgb(63,81,181);
						z-index: -1;
						transition: z-index 0s linear .1s,opacity 0s ease 0s;
					}
					
					.greenstyle {
						background-color: #8bf68b;
					}
					
					.redstyle {
						background-color: #f67373;
					}

					.orangestyle {
						background-color: #f59f34;
					}
					

					.rwd-table {
					  margin: 1em 0;
					  min-width: 300px;
					}
					.rwd-table tr {
					  border-top: 1px solid #ddd;
					  border-bottom: 1px solid #ddd;
					}
					.rwd-table th {
					  display: none;
					}
					.rwd-table td {
					  display: block;
					}
					.rwd-table td:first-child {
					  padding-top: .5em;
					  background-color: rgb(63,81,181);
					  color: rgba(255,255,255,0.87);
					}
					.rwd-table td:last-child {
					  padding-bottom: .5em;
					}
					.rwd-table td:before {
					  content: attr(data-th) ": ";
					  font-weight: bold;
					  width: 6.5em;
					  display: inline-block;
					}
					@media (min-width: 600px) {
					  .rwd-table td:before {
						display: none;
					  }
					}
					.rwd-table th, .rwd-table td {
					  text-align: left;
					}
					@media (min-width: 600px) {
					  .rwd-table th, .rwd-table td {
						display: table-cell;
						padding: .25em .5em;
					  }
					  .rwd-table th:first-child, .rwd-table td:first-child {
						padding-left: 0;
						background-color: #f6f6f6;
						color: #444;
						
						
					  }
					  .rwd-table th:first-child{
						background-color: #e9e9e9;	
					  }
					  
					  .rwd-table th:last-child, .rwd-table td:last-child {
						padding-right: 0;
					  }
					}
				</style>
				<!-- -->
				<script src="js/vkbeautify.0.99.00.beta.js"></script>
				<!-- Angular Material requires Angular.js Libraries -->
				<script src="http://ajax.googleapis.com/ajax/libs/angularjs/1.5.5/angular.min.js"></script>
				<script src="http://ajax.googleapis.com/ajax/libs/angularjs/1.5.5/angular-animate.min.js"></script>
				<script src="http://ajax.googleapis.com/ajax/libs/angularjs/1.5.5/angular-aria.min.js"></script>
				<script src="http://ajax.googleapis.com/ajax/libs/angularjs/1.5.5/angular-messages.min.js"></script>

				<!-- Angular Material Library -->
				<script src="http://ajax.googleapis.com/ajax/libs/angular_material/1.1.0/angular-material.min.js"></script>
				  
				
				<script>
					var app = angular.module('BlankApp', ['ngMaterial']);
					
					/*app.config(function($mdThemingProvider) {
						  $mdThemingProvider.theme('default')
							.primaryPalette('light-green')
							.accentPalette('blue');
						});
					*/
					app.config(function($mdThemingProvider) {

						// Configure a dark theme with primary foreground yellow

						$mdThemingProvider.theme('docs-dark', 'default')
						  .primaryPalette('yellow')
						  .dark();

					  });
					  
					app.controller('toolbarController', toolbarController);
						function toolbarController ($scope) {
							$scope.title1 = 'Logga in';
							$scope.title2 = 'Logga ut';
							$scope.isOpen = false;
							$scope.count = 0;
							$scope.selectedDirection = 'right'; 
							$scope.action = function($event) {
								$event.stopImmediatePropagation();
								//alert('clicked');
							};
							$scope.openmenu = function() {
							  $scope.isOpen = true;
							  $scope.menuzindex = 1;
							};
							$scope.closemenu = function() {
							  $scope.isOpen = false;
							  $scope.menuzindex = 0;
							};
							<?php if ($activecataloger) { ?>
							$scope.authorized = true;
							$scope.username = "<?php echo $username ?>";
							<?php } else { ?>
							$scope.authorized = false;
							<?php } ?>
							
						 }; 
					 
					
					app.controller('customersCtrl', function($scope, $http, $timeout) {
						$scope.headers = ["Servicestatus"];
						(function tick() {
							var parameters = {
								get_tasks_angular: "1"
							};
							var config = {
								params: parameters
							};
							$http.get("checkservice.aspx").then(function (response) 
							{
								$scope.names = response.data.records;
								$timeout(tick,10000);
							});
						})();
					});

					app.controller('tasks', function($scope, $http, $timeout, $mdDialog) {
						$scope.optionheaders = ["Name","Description", "Value"];
						$scope.myFunc = function(task_id) {
							var parameters = {
								get_taskoptions_angular: "1",
								task_id: task_id
							};
							var config = {
								params: parameters
							};
							$http.get("TasksAjaxHandler.php",config).then(function (response) 
							{
								$scope.taskoptions = response.data.records;
							});
						};
						
						$scope.resetTask = function(task_id){
							console.log(task_id);
							$http.post('TasksAjaxHandler.php',
								{
									"cmd":"reset_task_angular",
									"id":task_id,
								})
								.success(function(data){
									if (data == true) {
										console.log("update result: " + data);
									}
								})
								.error(function(data){
									
										console.log("update result: " + data);
									
								});
						};
						
						$scope.runTask = function(task_id){
							console.log("runTask, id: " + task_id);
							var confirm = $mdDialog.confirm()
								.title('Vill du starta jobbet nu?')
								.textContent('')
								.ariaLabel('')
								//.targetEvent(ev)
								.cancel('Nej')
								.ok('Ja');
							$mdDialog.show(confirm).then(
								function() {
									$http.post('TasksAjaxHandler.php',
										{
											"cmd":"run_task_angular",
											"id":task_id,
										})
										.success(function(data){
											if (data == true) {
												console.log("update result: " + data);
												gettasks();
											}
										})
										.error(function(data){
											
												console.log("update result: " + data);
											
										});
								}, 
								function() {
								}
							);
							
						};
						
						$scope.headers = ["Namn", "Jobbtyp", "Status", "Nästa händelse","Nästa starttid","Intervall","Aktivt","Kört länge","Inställningar","Återställ","Kör nu"];
						
						
						function gettasks() {
							var parameters = {
								get_tasks_angular: "1"
							};
							var config = {
								params: parameters
							};
							$http.get("TasksAjaxHandler.php",config).then(function (response) 
							{
								$scope.names = response.data.records;
							});
							//alert("sdfsf");
						}
						
						(function tock() {
							var parameters = {
								get_tasks_angular: "1"
							};
							var config = {
								params: parameters
							};
							$http.get("TasksAjaxHandler.php",config).then(function (response) 
							{
								$scope.names = response.data.records;
								$timeout(tock, 5000);
							});
							//alert("sdfsf");
						})();
						
						$scope.status = '  ';
						$scope.customFullscreen = false;
						
						//Öppnar formulär för att uppdatera/skapa en task
						$scope.showAdvanced = function(ev,task_id, task_name) {
							$mdDialog.show({
							locals:{task_id: task_id, task_name: task_name},
							  controller: DialogController,
							  templateUrl: 'insertform2.html',
							  parent: angular.element(document.body),
							  targetEvent: ev,
							  clickOutsideToClose:true,
							  fullscreen: $scope.customFullscreen // Only for -xs, -sm breakpoints.
							})
							.then(function(answer) {
							  $scope.status = 'You said the information was "' + answer + '".';
							}, function() {
							  $scope.status = 'You cancelled the dialog.';
							});
						};
					
						//Kontroller för formuläret
						function DialogController($scope, $mdDialog, task_id, task_name) {
							$scope.showConfirm = function(ev,task) {
								var confirm = $mdDialog.confirm()
									.title('Vill du verkligen ta bort jobbet?')
									.textContent('')
									.ariaLabel('')
									.targetEvent(ev)
									.ok('Ja')
									.cancel('Nej');
								$mdDialog.show(confirm).then(
									function() {
										$scope.deleteTask(task)
									}, 
									function() {
									}
								);
							};
							var parameters = {
									get_jobtypes_angular: "1"
								};
								var config = {
									params: parameters
								};
								$http.get("TasksAjaxHandler.php",config).then(function (response) 
								{
									$scope.jobtypes = response.data.records;
								});
							
							var parameters = {
									get_intervals_angular: "1"
								};
								var config = {
									params: parameters
								};
								$http.get("TasksAjaxHandler.php",config).then(function (response) 
								{
									$scope.intervals = response.data.records;
								});
							
							var parameters = {
									get_weekdays_angular: "1"
								};
								var config = {
									params: parameters
								};
								$http.get("TasksAjaxHandler.php",config).then(function (response) 
								{
									$scope.weekdays = response.data.records;
								});
							var parameters = {
									get_formats_angular: "1"
								};
								var config = {
									params: parameters
								};
								$http.get("TasksAjaxHandler.php",config).then(function (response) 
								{
									$scope.formats = response.data.records;
								});
								
							
							//Hämta bara om task_id har ett värde
							if (task_id != "") {
								var parameters = {
									get_task_angular: "1",
									task_id: task_id
								};
								var config = {
									params: parameters
								};
								$http.get("TasksAjaxHandler.php",config).then(function (response) 
								{
									//Se till att payload visas snyggt(med radbrytningar etc)
									if (response.data.payload != "" && response.data.payload!=null) {
										if (response.data.format_id == 2) {
											response.data.payload = vkbeautify.xml(response.data.payload);
										} else {
											response.data.payload = vkbeautify.json(response.data.payload);
										}
									}
									$scope.task = response.data;
									console.log(response.data.payload);
								});
							} else {
							}
							
							$scope.task_id = task_id;
							$scope.task_name = task_name;
							
							$scope.hide = function() {
							  $mdDialog.hide();
							};
							
							$scope.updateTaskoption = function(optionsvalue,taskoptiontype_id,task_id){
								console.log(optionsvalue + " " + taskoptiontype_id + " " + task_id);

								$http.post('TasksAjaxHandler.php',{"cmd":"update_taskoptions_angular","optionsvalue":optionsvalue,"taskoptiontype_id":taskoptiontype_id,"task_id":task_id})
									.success(function(data){
										if (data == true) {
											console.log("update result: " + data);
										}
									})
									.error(function(data){
										
											console.log("update result: " + data);
										
									});
							}
							
							$scope.saveTask = function(task){
								
								if (typeof task == "undefined") {
									console.log("undefined task");
								} else {
									if (typeof task.id == "undefined") {
										//Ny task
										$scope.insertTask(task);
									} else {
										//Befintlig task
										$scope.updateTask(task);
									}
								}
							};
							
							$scope.updateTask = function(task){
								//Se till att payload sparas minifierad(inga radbrytningar etc)
								console.log(task);
								if (task.payload != "" && task.payload!=null) {
									if (task.format_id == 2) {
										task.payload = vkbeautify.xmlmin(task.payload , true)
									} else {
										task.payload = vkbeautify.jsonmin(task.payload)
									}
								}
								$http.post('TasksAjaxHandler.php',
									{
										"cmd":"update_task_angular",
										"id":task.id,
										"name":task.name,
										"description":task.description,
										"jobtype_id":task.jobtype_id,
										"start_time":task.start_time,
										"weekday_id":task.weekday_id,
										"apikey":task.apikey,
										"payload":task.payload,
										"url":task.url,
										"format_id":task.format_id,
										"zip":task.zip,
										"zip_destination":task.zip_destination,
										"copy":task.copy,
										"copy_destination":task.copy_destination,
										"publishfiletowebserver":task.publishfiletowebserver,
										"webfolder":task.webfolder,
										"webfilename":task.webfilename,
										"ftp":task.ftp,
										"ftp_server":task.ftp_server,
										"ftp_user":task.ftp_user,
										"ftp_password":task.ftp_password,
										"interval_id":task.interval_id,
										"folder": task.folder,
										"filename": task.filename,
										"notification": task.notification,
										"notificationemails": task.notificationemails,
										"enabled": task.enabled
									})
									.success(function(data){
										if (data == true) {
											console.log("update result: " + data);
										}
									})
									.error(function(data){
										
											console.log("update result: " + data);
										
									});
								
								$scope.cancel();
							};
							
							$scope.insertTask = function(task){
								console.log(task.jobtype_id + ", " + task.name + ", " + task.zip);
								$http.post('TasksAjaxHandler.php',
									{
										"cmd":"insert_task_angular",
										"name":task.name,
										"description":task.description,
										"jobtype_id":task.jobtype_id,
										"start_time":task.start_time,
										"weekday_id":task.weekday_id,
										"apikey":task.apikey,
										"payload":task.payload,
										"url":task.url,
										"format_id":task.format_id,
										"zip":task.zip,
										"zip_destination":task.zip_destination,
										"copy":task.copy,
										"copy_destination":task.copy_destination,
										"publishfiletowebserver":task.publishfiletowebserver,
										"webfolder":task.webfolder,
										"webfilename":task.webfilename,
										"ftp":task.ftp,
										"ftp_server":task.ftp_server,
										"ftp_user":task.ftp_user,
										"ftp_password":task.ftp_password,
										"interval_id":task.interval_id,
										"folder": task.folder,
										"filename": task.filename,
										"notification": task.notification,
										"notificationemails": task.notificationemails,
										"enabled": task.enabled
									})
									.success(function(data){
										if (data == true) {
											console.log("insert result: " + data);
										}
									})
									.error(function(data){
										
											console.log("insert result: " + data);
										
									});
								
								$scope.cancel();
							};
							
							$scope.deleteTask = function(task){
								console.log(task.jobtype_id + ", " + task.name + ", " + task.zip);
								$http.post('TasksAjaxHandler.php',
									{
										"cmd":"delete_task_angular",
										"id":task.id
									})
									.success(function(data){
										if (data == true) {
											console.log("delete result: " + data);
										}
									})
									.error(function(data){
										
											console.log("delete result: " + data);
										
									});
								
								$scope.cancel();
							};
							
							$scope.cancel = function() {
							  $mdDialog.cancel();
							};

							$scope.answer = function(answer) {
							  $mdDialog.hide(answer);
							};
						}
						
						//Öppnar formulär för att uppdatera/skapa en task
						$scope.showSystemLog = function(ev,task_id, task_name) {
							$mdDialog.show({
							locals:{task_id: task_id, task_name: task_name},
							  controller: SystemLogDialogController,
							  templateUrl: 'systemlog.html',
							  parent: angular.element(document.body),
							  targetEvent: ev,
							  clickOutsideToClose:true,
							  fullscreen: $scope.customFullscreen // Only for -xs, -sm breakpoints.
							})
							.then(function(answer) {
							  $scope.status = 'You said the information was "' + answer + '".';
							}, function() {
							  $scope.status = 'You cancelled the dialog.';
							});
						};
					
						//Kontroller för formuläret
						function SystemLogDialogController($scope, $mdDialog, task_id, task_name) {
							$scope.showConfirm = function(ev,task) {
								/*var confirm = $mdDialog.confirm()
									.title('Vill du verkligen ta bort jobbet?')
									.textContent('')
									.ariaLabel('')
									.targetEvent(ev)
									.ok('Ja')
									.cancel('Nej');
									*/
								$mdDialog.show(confirm).then(
									function() {
										//$scope.deleteTask(task)
									}, 
									function() {
									}
								);
							};
							
							//Hämta bara om task_id har ett värde
							if (task_id != "") {
								var parameters = {
									get_systemlog_angular: "1",
									task_id: task_id
								};
								var config = {
									params: parameters
								};
								$http.get("TasksAjaxHandler.php",config).then(function (response) 
								{
									//Se till att payload visas snyggt(med radbrytningar etc)
									if (response.data.message != "" && response.data.message!=null) {
										//if (response.data.format_id == 2) {
											//response.data.message = vkbeautify.xml(response.data.message);
										//} else {
											//response.data.message = vkbeautify.json(response.data.message);
										//}
									}
									$scope.systemlog = response.data.records;
									console.log(response.data.records);
								});
							} else {
							}
							
							$scope.task_id = task_id;
							$scope.task_name = task_name;
							
							$scope.cancel = function() {
							  $mdDialog.cancel();
							};
							
							$scope.hide = function() {
							  $mdDialog.hide();
							};
						}
					});
					
					app.controller('AppCtrl', function($scope) {
							$scope.title1 = 'Logga in';
							$scope.title2 = 'Logga ut';
							$scope.isDisabled = true;
							$scope.googleUrl = 'http://google.com';
						});
						
					app.controller('SwitchDemoCtrl', function($scope) {
						$scope.data = {
							cb1: true,
							cb4: true,
							cb5: false
						};

						$scope.message = 'false';

						$scope.onChange = function(cbState) {
							$scope.message = cbState;
							};
						});
						
				</script>
			</head>

			<body ng-app="BlankApp">
				<div id="toolbarContainer" ng-controller="toolbarController" ng-cloak>
					<md-toolbar layout="row" class="md-fab md-primary" style="height:68px;z-index:0;background-color: #1954a6;color: rgba(255,255,255,0.87);">
						<md-icon style="width: 65px;height: 65px;" md-svg-src="images/KTH_Logotyp_RGB_2013-2.svg"></md-icon>
						<span flex></span>
						<md-button aria-label="menu" class="" ng-click="isOpen=true" ng-mouseenter='isOpen=true' ng-mouseleave='isOpen=false' style="1width:200px;font-size: 12px">
							<?php if ($loggedin) { ?>
							  <span><?php echo $username?></span>
							<?php } else { ?>
							  <md-icon class="material-icons md-light" style="color: rgba(255,255,255,0.87);">menu</md-icon>
							<?php } ?>  
						</md-button>
					</md-toolbar>
					<md-fab-toolbar md-open="isOpen" md-direction="right" count="count" ng-mouseenter='isOpen=true' ng-mouseleave='isOpen=false'>
						<md-fab-trigger class="align-with-text">
							<md-button aria-label="menu" class="md-primary">
								<md-icon class="material-icons">menu</md-icon>
							</md-button>
						</md-fab-trigger>

						<md-toolbar class="md-primary md-default">
							<md-fab-actions class="md-toolbar-tools">
								<?php if ($loggedin) { ?>
								<md-button class="md-raised md-mini md-primary" onclick="location.href='/tandem/general_login.php?logout=true&sessionname=KTHB_TASKS_SESSID'">
									{{title2}} <md-icon class="material-icons" aria-label="Insert Link">account_circle</md-icon>
								</md-button>
								<?php } else { ?>
								<md-button class="md-raised md-mini md-primary" onclick="location.href='/tandem/general_login.php?returl=/tasks/index.php&sessionname=KTHB_TASKS_SESSID'">
									{{title1}} <md-icon class="material-icons" aria-label="Insert Link">account_circle</md-icon>
								</md-button>
								<?php } ?>
							  <!--md-button aria-label="Insert Link" class="md-fab md-raised md-mini md-primary">
								 <md-icon class="material-icons" aria-label="Insert Link">insert_link</md-icon>
							  </md-button>
							  <md-button aria-label="Edit" class="md-fab md-raised md-mini md-primary">
								 <md-icon class="material-icons" aria-label="Edit">mode_edit</md-icon>
							  </md-button-->
						   </md-fab-actions>
						</md-toolbar>
					</md-fab-toolbar>
				</div>
				<div layout="row">
				<div flex="10"></div>
				<div flex>
				<div layout="row" layout-xs="column">
					<div ng-controller="AppCtrl" ng-cloak>
						<h1>KTHB Tasks</h1>
						<!--div>
							Inloggad som: <?php //echo $source['full_name']?>
						</div>
						<div>
							<md-button class="md-raised md-primary md-hue-1" onclick="location.href='/tandem/general_login.php?logout=true'">{{title2}}</md-button>
						</div-->
					</div>
					<div flex></div>
					<div ng-controller="customersCtrl">
							<div class="header" ng-repeat= "header in headers">
								<a> {{headers[$index]}} </a>
							</div>
							<div class="row" ng-repeat="x in names" ng-class="{greenstyle: x.servicestatus=='OK', redstyle: x.servicestatus!='OK'}">
									<div layout="row" layout-wrap layout-align="center">
										{{ x.servicestatus }}
									</div>
							</div>
					</div>
				</div>
				<div ng-controller="tasks">
					<div layout="row" layout-wrap layout-align="left">
						<md-button class="md-primary md-raised" ng-click="showAdvanced($event,'','')">
							<md-icon> add_box </md-icon> Lägg till
						</md-button>
					</div>
					<table class="rwd-table">
						<th ng-repeat= "header in headers">
							<a> {{headers[$index]}} </a>
						</th>
						<tr ng-repeat="x in names | orderBy:'-enabled'">{{ x.name }}</a></td-->
							<td data-th="Namn">{{ x.name }}</td>
							<td data-th="Jobbtyp">{{ x.jobtype_description }}</td>
							<td ng-class="{redstyle: x.status_description=='Fel inträffade'}" data-th="Status">{{ x.status_description }}<span ng-if="x.status_description=='Fel inträffade'" ng-click="showSystemLog($event,x.id,x.name)">Log</span></td>
							<td ng-class="{greenstyle: x.action_description!='Väntar på att bli startat'}" data-th="Nästa händelse">{{ x.action_description }}</td>
							<td data-th="Nästa starttid">{{ x.start_time }}</td>
							<td data-th="Intervall">{{ x.interval_description }}</td>
							<td data-th="Aktivt">{{x.enabled == "1" ? "Ja" : "Nej"}}</td>
							<td ng-class="{orangestyle: x.islongrunning=='1'}" data-th="Kört länge">{{x.islongrunning == "1" ? "Ja" : "Nej"}}</td>
							<?php if ($activecataloger) { ?>
							<td data-th="Inställningar">
								<div layout="row" layout-wrap layout-align="center">
									<md-button class="md-primary md-raised" ng-click="showAdvanced($event,x.id,x.name)">
										<md-icon> settings </md-icon>
									</md-button>
								</div>
							 </td>
							 <td data-th="Återställ">
								<div layout="row" layout-wrap layout-align="center">
									<md-button class="md-warn md-raised" ng-click="resetTask(x.id)" ng-disabled="x.action_name=='init'||x.enabled==false">
										<md-icon> cached </md-icon>
									</md-button>
								</div>
							 </td>
							 <td data-th="Kör nu">
								<div layout="row" layout-wrap layout-align="center">
									<md-button class="md-accent md-raised" ng-click="runTask(x.id)" ng-disabled="x.status_description!='Avslutat'||x.enabled==false">
										<md-icon> play_circle_filled </md-icon>
									</md-button>
								</div>
							 </td>
							 <?php } else { ?>
							 <td data-th="Inställningar">
								<div layout="row" layout-wrap layout-align="center">
									<md-button class="md-primary md-raised" ng-disabled="true">
										<md-icon> settings </md-icon>
									</md-button>
								</div>
							 </td>
							 <td data-th="Återställ">
								<div layout="row" layout-wrap layout-align="center">
									<md-button class="md-warn md-raised" ng-disabled="true">
										<md-icon> cached </md-icon>
									</md-button>
								</div>
							 </td>
							 <td data-th="Kör nu">
								<div layout="row" layout-wrap layout-align="center">
									<md-button class="md-accent md-raised" ng-disabled="true">
										<md-icon> play_circle_filled </md-icon>
									</md-button>
								</div>
							 </td>
							<?php } ?>
						</tr>
						
					</table>
				</div>
				</div>
				<div flex="10"></div>
				</div>
				<!--div ng-controller="dialogCtrl" class="md-padding" id="popupContainer" ng-cloak>
				  <div class="dialog-demo-content" layout="row" layout-wrap layout-margin layout-align="center">
					<md-button class="md-primary md-raised" ng-click="showAdvanced($event,)">
					  Custom Dialog
					</md-button>
				  </div>
				</div-->
				
			</body>
		</html>