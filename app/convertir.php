<?php
require_once 'core/config.php';
require_once 'core/sPDO.php';


$db =  sPDO::singleton();
$SQL = "update EMISORES SET PFX=:pfx WHERE RFC = :rfc";


$rfc= 'CADM751111JJA';
$pfx = base64_encode(file_get_contents('temp/pfx.pfx'));

$stmt = $db->prepare($SQL);
$stmt->bindParam(':pfx',$pfx,PDO::PARAM_STR);
$stmt->bindParam(':rfc',$rfc,PDO::PARAM_STR);
$stmt->execute();


?>
