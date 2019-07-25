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
	$datec=$_POST['datec'];
	$datepo=$_POST['datepo'];
	$wacode=$_POST['wacode'];
};
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
	$idorg=$_GET['idorg'];
	$datec=$_GET['datec'];
	$datepo=$_GET['datepo'];
	$wacode=$_GET['wacode'];
};

$sqltext="EXEC AMUR1_GetListGP :IdOrg, :DateC, :DatePo, :WACode";
	try { $stmt = $db->prepare($sqltext); } catch (PDOException $err) { die('Error: ' . $err->getMessage()); }; 
	$stmt -> bindValue(":IdOrg",		$idorg );
	$stmt -> bindValue(":DateC",		$datec,  PDO::PARAM_STR);
	$stmt -> bindValue(":DatePo",		$datepo, PDO::PARAM_STR);
	$stmt -> bindValue(":WACode",		$wacode );
	try { $stmt->execute(); } catch (PDOException $err) { die('Error: ' . $err->getMessage()); };
	$ticketslist = $stmt->fetchall(); 
	unset($stmt);

//var_dump($ticketslist);	

$p=0;
$arrjson=array();
for ($p=0; $p<count($ticketslist); $p++){
	$arrjson[$p]=array( 
	"IdOrg"=>$ticketslist[$p]["IdOrg"],
	"WACode"=>$ticketslist[$p]["WACode"],
	"DateC"=>$ticketslist[$p]["DateC"],
	"DatePo"=>$ticketslist[$p]["DatePo"],
	"ClassGraf"=>$ticketslist[$p]["ClassGraf"],
	"OrgTicketCode"=>$ticketslist[$p]["OrgTicketCode"],
	"OrgTicketBDID"=>$ticketslist[$p]["OrgTicketBDID"],
	//"OrgParentTicketBDID"=>$ticketslist[$p]["OrgParentTicketBDID"],
	"OrgTicketType"=>$ticketslist[$p]["OrgTicketType"],
	"OrgTicketTypeText"=>$ticketslist[$p]["OrgTicketTypeText"],
	"OrgTicketSubType"=>$ticketslist[$p]["OrgTicketSubType"],
	"OrgTicketSubTypeText"=>$ticketslist[$p]["OrgTicketSubTypeText"],
	"OrgStatusCode"=>$ticketslist[$p]["OrgStatusCode"],
	"OrgStatusCodeText"=>$ticketslist[$p]["OrgStatusCodeText"],
	"OrgTicketText"=>$ticketslist[$p]["OrgTicketText"],

	"OrgHouseCode"=>$ticketslist[$p]["OrgHouseCode"],
	"OrgHouseAddress"=>$ticketslist[$p]["OrgHouseAddress"]
	);
};

$json = json_encode($arrjson, JSON_UNESCAPED_UNICODE); 
echo $json; 			

?>
