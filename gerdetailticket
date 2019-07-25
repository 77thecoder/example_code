<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
include("../dbconfig.php");

set_time_limit(0);
date_default_timezone_set('Europe/Moscow'); 
ini_set('error_reporting', E_ALL);
ini_set('log_errors', '1');
ini_set('display_errors', '1');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$idorg=$_POST['idorg'];
	$orgticketcode=$_POST['orgticketcode'];
};
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
	$idorg=$_GET['idorg'];
	$orgticketcode=$_GET['orgticketcode'];
};

$sqltext="EXEC TICKETS_GetWorkByOrgTicketCode :IdOrg, :OrgTicketCode";
	try { $stmt = $db->prepare($sqltext); } catch (PDOException $err) { die('Error: ' . $err->getMessage()); }; 
	$stmt -> bindValue(":IdOrg",			$idorg );
	$stmt -> bindValue(":OrgTicketCode",	$orgticketcode);
	try { $stmt->execute(); } catch (PDOException $err) { die('Error: ' . $err->getMessage()); };
	$workdetail = $stmt->fetchall(); 
	unset($stmt);
	
$arrjson=array( 
	"JobCode"=>$workdetail[0]["JobCode"],
	"WorkCode"=>$workdetail[0]["WorkCode"],
	"OrgTicketCode"=>$workdetail[0]["OrgTicketCode"],
	"OrgTicketBDID"=>$workdetail[0]["OrgTicketBDID"],
	"OrgStatusCodeText"=>$workdetail[0]["OrgStatusCodeText"],
	"OrgStatusDateAct"=>$workdetail[0]["OrgStatusDateAct"],
	"NVWorkName"=>$workdetail[0]["NVWorkName"],
	"CstWorkName"=>$workdetail[0]["CstWorkName"],
	"OrgHouseCode"=>$workdetail[0]["OrgHouseCode"],
	"IdOrg"=>$idorg,
	"OrgClientFIO"=>$workdetail[0]["OrgClientFIO"]
	);
	
$json = json_encode($arrjson, JSON_UNESCAPED_UNICODE); 
echo $json; 		


?>
