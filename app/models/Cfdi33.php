<?php

require_once 'core/modelBase.php';
require_once 'core/Correo.php';
require_once 'core/CFDI.php';

/**
 * Description of Cfdi33
 *
 * @author oliver
 */
class Cfdi33 extends modelBase {
    
    private function obtenerCfdiEmail($uuid) {
        $q = "SELECT C._Emisor, C.Emisor_Rfc, C.Emisor_Nombre, C.Receptor_Rfc,"
            . " C.Receptor_Nombre, C.Serie, C.Folio, C.Total, F.XML, F.PDF_B64,"
            . " E.LOGO, M.REMITENTE, M.EMAIL_RESPUESTA, M.ASUNTO, M.CUERPO"
            . " FROM V33_COMPROBANTES C"
            . " INNER JOIN V33_CFDIS F ON C._TimbreFiscalDigital_UUID=F._TimbreFiscalDigital_UUID"
            . " INNER JOIN EMISORES E ON C._Emisor=E.ID"
            . " INNER JOIN EMISORES_EMAIL M ON C._Emisor=M.EMISOR"
            . " WHERE C._TimbreFiscalDigital_UUID=:uuid;";
        $stmt = $this->db->prepare($q);
        $stmt->bindParam(':uuid', $uuid, PDO::PARAM_STR);
        $stmt->execute();
        
        return $stmt->fetch();
    }
            
    
    function obtener($params) {
        
        $uuid = $params['uuid'];
        $format = $params['format'];        
        
        $qXML = "SELECT XML, PDF_B64 FROM V33_CFDIS WHERE _TimbreFiscalDigital_UUID=:uuid";                
        $stmt1 = $this->db->prepare($qXML);
        $stmt1->bindParam(':uuid', $uuid, PDO::PARAM_STR);
        $stmt1->execute();
        $result = $stmt1->fetch();
        $xml = $result['XML'];
        $pdfB64 = $result['PDF_B64'];        
        
        if($format=='xml'){
            header("HTTP/1.1 200 OK");
            header("Content-Type: application/xml");
            header( 'Expires: Sat, 26 Jul 1997 05:00:00 GMT' ); 
            header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s' ) . ' GMT' ); 
            header( 'Cache-Control: no-store, no-cache, must-revalidate' ); 
            header( 'Cache-Control: post-check=0, pre-check=0', false ); 
            header( 'Pragma: no-cache' ); 
            return $xml;
        }
        
        if($format=='pdf'){            
            header("HTTP/1.1 200 OK");
            header("Content-Type: application/pdf"); 
            header( 'Expires: Sat, 26 Jul 1997 05:00:00 GMT' ); 
            header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s' ) . ' GMT' ); 
            header( 'Cache-Control: no-store, no-cache, must-revalidate' ); 
            header( 'Cache-Control: post-check=0, pre-check=0', false ); 
            header( 'Pragma: no-cache' );
            return base64_decode($pdfB64);
        }
        
        if($format=='zip'){
            
            header("HTTP/1.1 200 OK");
            header("Content-Type: application/zip"); 
            header('Content-Disposition: attachment; filename="'.$uuid.'.zip";');
            header('Content-Transfer-Encoding: binary');
            header( 'Expires: Sat, 26 Jul 1997 05:00:00 GMT' ); 
            header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s' ) . ' GMT' ); 
            header( 'Cache-Control: no-store, no-cache, must-revalidate' ); 
            header( 'Cache-Control: post-check=0, pre-check=0', false ); 
            header( 'Pragma: no-cache' ); 
            //header('Content-Length: '.$this->bufferlen);
            $zip = new ZipArchive();
            $filename = "temp/$uuid.zip";

            if ($zip->open($filename, ZipArchive::CREATE)!==TRUE) {
                exit("cannot open <$filename>\n");
            }
            
            $pdf = base64_decode($pdfB64);
            $zip->addFromString("$uuid.xml", $xml);
            $zip->addFromString("$uuid.pdf", $pdf);
            $zip->close();
            return file_get_contents($filename);
            
            @unlink($filename);
        }
        
    }
    
    function email($params) {
        
        $postData = $this->POST();
        
        $uuid = $params['uuid'];
        
        $destEmail = $postData['_DestinatarioEmail'];
        $destNombre = $postData['_DestinatarioNombre'];
        
        
        $cfdiEmail = $this->obtenerCfdiEmail($uuid);
        
        
        
        $mail = new Correo($cfdiEmail['REMITENTE']);
        
        $destinatario = array($destEmail, $destNombre);
        
        
            
        $aguja = array('{{recprfc}}', '{{uuid}}', '{{emisrfc}}', '{{serie}}', '{{folio}}', '{{total}}', '{{emailresp}}');
        
        $total = number_format($cfdiEmail['Total'],2,'.',',');
        $reemplazo = array($cfdiEmail['Receptor_Rfc'], $uuid, $cfdiEmail['Emisor_Rfc'], $cfdiEmail['Serie'], $cfdiEmail['Folio'], $total, $cfdiEmail['EMAIL_RESPUESTA']);
            
        $cuerpo = str_replace($aguja, $reemplazo, $cfdiEmail['CUERPO']);     
        
        
        $xmlFile = "temp/$uuid.xml";
        $pdfFile = "temp/$uuid.pdf";
        $adjuntos = array($xmlFile, $pdfFile);
        
        $pdf = base64_decode($cfdiEmail['PDF_B64']);
        
        
        file_put_contents($xmlFile, $cfdiEmail['XML']);
        file_put_contents($pdfFile, $pdf);
        
        
        if($mail->Enviar($cfdiEmail['REMITENTE'], $cfdiEmail['ASUNTO'], $cfdiEmail['EMAIL_RESPUESTA'], $cfdiEmail['REMITENTE'], $destinatario, $adjuntos, $cuerpo)){
            $requestArr = array_merge(array('uuid'=>$uuid),$postData);
            $response = array(
                'error'=>NULL,
                'request'=>$requestArr,
                'data'=>true
            );
            
            @unlink($xmlFile);
            @unlink($pdfFile);
            
        } else {
            @unlink($xmlFile);
            @unlink($pdfFile);
            throw new Exception('Imposible enviar el correo al destinatario: '.$destEmail.', razón' . $mail->ErrorInfo);
        }
        
        return json_encode($response);
        
    }
    
    
    private function guardarCancelacion($UUID, $Tipo, $Estatus, $CodigoResultado, $EsCancelable, $MensajeResultado) {
        
        $qUUID = "SELECT UUID() AS miUUID";
        $stmtUUID = $this->db->prepare($qUUID);
        $stmtUUID->execute();
        $myId = $stmtUUID->fetchColumn(0);
        
        $q = "INSERT INTO V33_CFDIS_CANCELACIONES(_Id, _TimbreFiscalDigital_UUID, _Tipo, _Estatus, _Fecha, CodigoResultado, EsCancelable, MensajeResultado)"
                . " VALUES(:_Id, :_TimbreFiscalDigital_UUID, :_Tipo, :_Estatus, NOW(), :CodigoResultado, :EsCancelable, :MensajeResultado)";
        
        $stmt1 = $this->db->prepare($q);        
        $stmt1->bindParam(':_Id', $myId, PDO::PARAM_STR);
        $stmt1->bindParam(':_TimbreFiscalDigital_UUID', $UUID, PDO::PARAM_STR);
        $stmt1->bindParam(':_Tipo', $Tipo, PDO::PARAM_INT);
        $stmt1->bindParam(':_Estatus', $Estatus, PDO::PARAM_INT);
        $stmt1->bindParam(':CodigoResultado', $CodigoResultado, PDO::PARAM_STR);
        $stmt1->bindParam(':EsCancelable', $EsCancelable, PDO::PARAM_STR);
        $stmt1->bindParam(':MensajeResultado', $MensajeResultado, PDO::PARAM_STR);
        $stmt1->execute();
        if($stmt1->rowCount()!=1) {
            throw new Exception('No se logró insertar en V33_CFDIS_CANCELACIONES');
        }
        
        return $myId;
        
    }
    
    private function guardarAcuseCancelacion($idCancelacion, $pacAcuseCancelacion) {
        
        $qUUID = "SELECT UUID() AS miUUID";
        $stmtUUID = $this->db->prepare($qUUID);
        $stmtUUID->execute();
        $myId = $stmtUUID->fetchColumn(0);
        
        $q = "INSERT INTO V33_CFDIS_CANCELACIONES_ACUSES(_Id, _CfdiCancelacion, Acuse)"
            . " VALUES(:_Id, :_CfdiCancelacion, :Acuse) ";
        $stmt1 = $this->db->prepare($q);        
        $stmt1->bindParam(':_Id', $myId, PDO::PARAM_STR);
        $stmt1->bindParam(':_CfdiCancelacion', $idCancelacion, PDO::PARAM_STR);
        $stmt1->bindParam(':Acuse', $pacAcuseCancelacion, PDO::PARAM_STR);
        
        
        $stmt1->execute();
        if($stmt1->rowCount()!=1) {
            throw new Exception('No se logró insertar en V33_CFDIS_CANCELACIONES_ACUSES');
        }
        
        return true;
        
    }
    
    private function actualizarEstadoComprobante($uuid) {
        $q = "UPDATE V33_COMPROBANTES SET _Estado=:_Estado, _TicketId=UUID()"
            . " WHERE _TimbreFiscalDigital_UUID=:_TimbreFiscalDigital_UUID";
        $stmt1 = $this->db->prepare($q);        
        $stmt1->bindParam(':_TimbreFiscalDigital_UUID', $uuid, PDO::PARAM_STR);
        $stmt1->bindValue(':_Estado', -1, PDO::PARAM_INT);
        
        $stmt1->execute();
        if($stmt1->rowCount()!=1) {
            throw new Exception('No se logró actualizar estado de V33_COMPROBANTES');
        }
        
        return true;
        
    }
    
    private function verificarEstadoParaCancelacion($uuid) {
        $q = "SELECT _Estado FROM V33_COMPROBANTES WHERE _TimbreFiscalDigital_UUID=:UUID";
        $stmt = $this->db->prepare($q);
        $stmt->bindParam(':UUID', $uuid, PDO::PARAM_STR);
        $stmt->execute();
        $estado = $stmt->fetchColumn(0);
        
        if($estado==1)
            return true;
        else 
            return false;
        
    }
    
    function cancelar($params) {
        
        $uuid = $params['uuid'];
        
        $cfdi = new CFDI();
        
        if(!$this->verificarEstadoParaCancelacion($uuid)) {
            throw new Exception("El folio fiscal $uuid no se encuentra disponible para cancelación");
        }
        
        $emisorPac = $this->obtenerDatosEmisorPAC($uuid);
        
    $this->db->beginTransaction();
    
        $pacCancelacion = $cfdi->cancelarCFDi33($emisorPac['EMISOR']['WS_CFDI'], $emisorPac['PACMETODOS']['ws_metodoCancelarCFDI'], $emisorPac['PACMETODOS']['ws_metodoCancelarCFDI'].'Result', $emisorPac['EMISOR']['WS_LOGIN'], $emisorPac['EMISOR']['WS_PASSWD'], $emisorPac['EMISOR']['RFC'], $uuid, $emisorPac['EMISOR']['PFX'], $emisorPac['EMISOR']['PASSWDPFX']);
        
        $idCancelacion = $this->guardarCancelacion($pacCancelacion['UUID'], 1, 1, $pacCancelacion['CodigoResultado'],'','');
        
    $this->db->commit();
    
    $this->db->beginTransaction();
        
        $pacAcuseCancelacion = $cfdi->obtenerAcuseCancelacion33($emisorPac['EMISOR']['WS_CFDI'], $emisorPac['PACMETODOS']['ws_metodoObtenerAcuseCancelacion'], $emisorPac['PACMETODOS']['ws_metodoObtenerAcuseCancelacion'].'Result', $emisorPac['EMISOR']['WS_LOGIN'], $emisorPac['EMISOR']['WS_PASSWD'], $uuid);
        
        $result1 = $this->guardarAcuseCancelacion($idCancelacion, $pacAcuseCancelacion);
        
        $result2 = $this->actualizarEstadoComprobante($uuid);
        
    
    $this->db->commit();
        
        if($result2) {
            $response = array(
                'error'=>NULL,
                'request'=>$params,
                'data'=>array(
                        'IdCancelacion'=>$idCancelacion
                    )
            );
        }
          
        return json_encode($response);
        
    }
    
    private function obtenerDatosEmisorPAC($UUID) {
        $qEmisor = "SELECT P.RFC AS PACID, P.WS_CFDI, E.WS_LOGIN, E.WS_PASSWD, E.RFC, E.PFX, E.PASSWDPFX "
            . " FROM V33_COMPROBANTES C "
            . " INNER JOIN EMISORES E ON C._Emisor=E.ID "
            . " INNER JOIN PACS P ON E.PAC=P.RFC "
            . " WHERE C._TimbreFiscalDigital_UUID=:UUID";
        $stmt = $this->db->prepare($qEmisor);
        $stmt->bindParam(':UUID', $UUID, PDO::PARAM_STR);
        $stmt->execute();
        $emisor = $stmt->fetch();
        
        $emisorRes =  array(
            'WS_CFDI'=>$emisor['WS_CFDI'],
            'WS_LOGIN'=>$emisor['WS_LOGIN'],
            'WS_PASSWD'=>$emisor['WS_PASSWD'],
            'RFC'=>$emisor['RFC'],
            'PFX'=>$emisor['PFX'],
            'PASSWDPFX'=>$emisor['PASSWDPFX']);
        
        
        $qPacMetodos = "SELECT VARIABLE, METODO FROM PAC_METODOS WHERE PAC=:PACID";
        $stmt2 = $this->db->prepare($qPacMetodos);
        $stmt2->bindParam(':PACID', $emisor['PACID'], PDO::PARAM_STR);
        $stmt2->execute();
        $metodos = $stmt2->fetchAll(PDO::FETCH_ASSOC);
        
        $pacMetodos = [];
        foreach($metodos as $k=>$v) {
            $pacMetodos[$v['VARIABLE']]=$v['METODO'];
        }
        
        return array('EMISOR'=>$emisorRes, 'PACMETODOS'=>$pacMetodos);
        
    }

    private function obtenerDatosCFDI($UUID) {
        $qComprobantes = "SELECT Receptor_Rfc, Total"
            . " FROM V33_COMPROBANTES"            
            . " WHERE _TimbreFiscalDigital_UUID=:UUID";
        $stmt = $this->db->prepare($qComprobantes);
        $stmt->bindParam(':UUID', $UUID, PDO::PARAM_STR);
        $stmt->execute();
        $comprobante = $stmt->fetch(PDO::FETCH_ASSOC);

        return $comprobante;
    }
    
    function buscar($params) {
        
        $q = "SELECT _TicketId, _PaymentId, _TimbreFiscalDigital_UUID"
                . " FROM V33_COMPROBANTES"
                . " WHERE _Emisor=:emisor AND Serie=:serie AND Folio=:folio";
        
        $stmt = $this->db->prepare($q);
        $stmt->bindParam(':emisor',$params['emisor'], PDO::PARAM_STR);
        $stmt->bindParam(':serie',$params['serie'], PDO::PARAM_STR);
        $stmt->bindParam(':folio',$params['folio'], PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetch();
        
        httpResp::setHeaders(200);
        return json_encode($result);
        
    }
    
    function listar($params) {
        
        $q = "SELECT C._Id, C._TimbreFiscalDigital_UUID, C._Nodo, C._Estado, C.Fecha,"
            . " CASE C._Estado WHEN -1 THEN 'Cancelado' WHEN 1 THEN 'Timbrado' END AS _Estado_Des, "
            . " C.Emisor_Rfc, C.Serie, C.Folio, C.Receptor_Rfc, C.Receptor_Nombre,"
            . " C.FormaPago, FP.Descripcion AS FormaPago_Des, C.SubTotal, C.Descuento,"
            . " IFNULL(SUM(IT.Importe),0) AS TotalImpuestosTrasladados,"
            . " IFNULL(SUM(IR.Importe),0) AS TotalImpuestosRetenido, C.Total"
            . " FROM V33_COMPROBANTES C"
            . " INNER JOIN V33_C_FORMAPAGO FP ON C.FormaPago=FP.c_FormaPago"
            . " LEFT JOIN V33_IMPUESTOS_TRASLADOS IT ON C._Id=IT._Comprobante"
            . " LEFT JOIN V33_IMPUESTOS_RETENCIONES IR ON C._Id=IR._Comprobante"
            . " WHERE C._Emisor=:emisor"
            . " AND C._Estado IN (-1,1)"
            . " AND C.Fecha BETWEEN :fechaIni AND :fechaFin"
            . " GROUP BY C._Id, C._TimbreFiscalDigital_UUID, C._Nodo, C._Estado,"
            . " C.Emisor_Rfc, C.Serie, C.Folio, C.Receptor_Rfc, C.Receptor_Nombre,"
            . " C.FormaPago, FP.Descripcion, C.SubTotal, C.Descuento, C.Total "
            . " ORDER BY C.Serie, C.Folio ";
        
        $stmt = $this->db->prepare($q);
        $stmt->bindParam(':emisor',$_SESSION['capricho']['emisor'], PDO::PARAM_STR);
        $stmt->bindParam(':fechaIni',$params['fechaIni'], PDO::PARAM_STR);
        $stmt->bindParam(':fechaFin',$params['fechaFin'], PDO::PARAM_STR);
        $stmt->execute();
        
        $result = $stmt->fetchAll();
        httpResp::setHeaders(200);
        return json_encode($result);
        
    }
    
    function obtenerAcuseCancelacion($params) {
        
        $q = "SELECT CA.Acuse FROM V33_CFDIS_CANCELACIONES C"
            . " LEFT JOIN V33_CFDIS_CANCELACIONES_ACUSES CA ON C._Id=CA._CfdiCancelacion"
            . " WHERE C._TimbreFiscalDigital_UUID=:uuid";
        
        $stmt = $this->db->prepare($q);
        $stmt->bindParam(':uuid',$params['uuid'], PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetch();
        $xml = $result['Acuse'];
        
        
        header("HTTP/1.1 200 OK");
        header("Content-Type: application/xml");
        header( 'Expires: Sat, 26 Jul 1997 05:00:00 GMT' ); 
        header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s' ) . ' GMT' ); 
        header( 'Cache-Control: no-store, no-cache, must-revalidate' ); 
        header( 'Cache-Control: post-check=0, pre-check=0', false ); 
        header( 'Pragma: no-cache' ); 
        return $xml;
        
    }
    
    function descargar($params) {
                 
        $q = "SELECT C.Emisor_Rfc, C.Serie, C.Folio, C._TimbreFiscalDigital_UUID,"
            . " F.XML, F.PDF_B64"
            . " FROM V33_COMPROBANTES C"
            . " INNER JOIN V33_CFDIS F ON C._TimbreFiscalDigital_UUID=F._TimbreFiscalDigital_UUID"
            . " WHERE C._Emisor=:emisor"
            . " AND C.Fecha BETWEEN :fechaIni AND :fechaFin";
        
        $stmt = $this->db->prepare($q);
        $stmt->bindParam(':fechaIni',$params['fechaIni'], PDO::PARAM_STR);
        $stmt->bindParam(':fechaFin',$params['fechaFin'], PDO::PARAM_STR);
        $stmt->bindParam(':emisor', $_SESSION['capricho']['emisor'], PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetchAll();
        
        if ( empty($result) )
            throw new Exception('No existe ningún CFDi');
        
        header("HTTP/1.1 200 OK");
        header("Content-Type: application/json"); 
        header( 'Expires: Sat, 26 Jul 1997 05:00:00 GMT' ); 
        header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s' ) . ' GMT' ); 
        header( 'Cache-Control: no-store, no-cache, must-revalidate' ); 
        header( 'Cache-Control: post-check=0, pre-check=0', false ); 
        header( 'Pragma: no-cache' );

        $zip = new ZipArchive();            

        $filename = 'temp/archivos-'.$_SESSION['capricho']['emisor_rfc'].'_'.$_SESSION['capricho']['usuario'].'.zip';
        @unlink($filename);        
        
        if ($zip->open($filename, ZipArchive::CREATE)!==TRUE) {
            throw new Exception('No se puede abrir el archivo <'.$filename.'>\n');
        }
        
        set_time_limit (0);
        foreach ($result as $comprobante) {
            $xml = $comprobante['XML'];
            $pdf = base64_decode($comprobante['PDF_B64']);
            $archivo = $comprobante['Emisor_Rfc'].'_'.$comprobante['Serie'].'-'.$comprobante['Folio'].'_'.$comprobante['_TimbreFiscalDigital_UUID'];
            $zip->addFromString("$archivo.xml", $xml);
            $zip->addFromString("$archivo.pdf", $pdf);
        }            
        $zip->close();
        
        return "app/$filename";

    }
    
    function validarRFC($params) {
        
        
        $cfdi = new CFDI();
        
        $qEmisor = "SELECT P.RFC AS PACID, P.WS_CFDI, E.WS_LOGIN, E.WS_PASSWD"
            . " FROM EMISORES E"
            . " INNER JOIN PACS P ON E.PAC=P.RFC"
            . " WHERE E.ID=:ID";
        $stmt = $this->db->prepare($qEmisor);
        $stmt->bindParam(':ID', $params['emisor'], PDO::PARAM_STR);
        $stmt->execute();
        $emisor = $stmt->fetch();
        
        $qPacMetodos = "SELECT VARIABLE, METODO FROM PAC_METODOS WHERE PAC=:PACID";
        $stmt2 = $this->db->prepare($qPacMetodos);
        $stmt2->bindParam(':PACID', $emisor['PACID'], PDO::PARAM_STR);
        $stmt2->execute();
        $metodos = $stmt2->fetchAll(PDO::FETCH_ASSOC);
        
        $pacMetodos = [];
        foreach($metodos as $k=>$v) {
            $pacMetodos[$v['VARIABLE']]=$v['METODO'];
        }
        
        $result = $cfdi->validarRFC33($emisor['WS_CFDI'], $pacMetodos['ws_metodoValidarRFC'],
                $pacMetodos['ws_metodoValidarRFC'].'Result', $emisor['WS_LOGIN'], $emisor['WS_PASSWD'], $params['rfc']);
        
        
        if ($result['RFCLocalizado']&&!$result['Cancelado']) {
            $response = array(
                'error'=>NULL,
                'request'=>$params,
                'data'=> $result
            );
        } else {
            $response = array(
                'error'=>true,
                'request'=>$params,
                'data'=> $result
            );
        }
        
        httpResp::setHeaders(200);
        return json_encode($response);
        
    }

    function cancelarconvalidacion($params) {
        
        $uuid = $params['uuid'];
        
        $cfdi = new CFDI();
        
        if(!$this->verificarEstadoParaCancelacion($uuid)) {
            throw new Exception("El folio fiscal $uuid no se encuentra disponible para cancelación");
        }
        
        $emisorPac = $this->obtenerDatosEmisorPAC($uuid);

        $datosCFDI = $this->obtenerDatosCFDI($uuid);
        
    $this->db->beginTransaction();
        
        $pacCancelacion = $cfdi->cancelarCFDi33ConValidacion(
            $emisorPac['EMISOR']['WS_CFDI'], 
            $emisorPac['PACMETODOS']['ws_metodoCancelarCFDIConValidacion'], 
            $emisorPac['PACMETODOS']['ws_metodoCancelarCFDIConValidacion'].'Result', 
            $emisorPac['EMISOR']['WS_LOGIN'], 
            $emisorPac['EMISOR']['WS_PASSWD'], 
            $emisorPac['EMISOR']['RFC'], 
            $datosCFDI['Receptor_Rfc'],
            $datosCFDI['Total'],
            $uuid, 
            $emisorPac['EMISOR']['PFX'], 
            $emisorPac['EMISOR']['PASSWDPFX']
            );

        $estatusCancelacion = 0;
        if( $pacCancelacion['CodigoResultado']=='201' && $pacCancelacion['EsCancelable']=='Cancelable sin aceptación' ) {
            $estatusCancelacion = 1;
        }

        $idCancelacion = $this->guardarCancelacion(
            $pacCancelacion['UUID'], 
            2, 
            $estatusCancelacion, 
            $pacCancelacion['CodigoResultado'], 
            $pacCancelacion['EsCancelable'], 
            $pacCancelacion['MensajeResultado']
        );

    $this->db->commit();

    if( $estatusCancelacion==0 ) {
        $response = array(
            'error'=>$pacCancelacion['CodigoResultado'].': '.$pacCancelacion['MensajeResultado'].' '.$pacCancelacion['EsCancelable'],
            'request'=>$params,
            'data'=>NULL
        );
        return json_encode($response);
    }

    
    $this->db->beginTransaction();
        
        $pacAcuseCancelacion = $cfdi->obtenerAcuseCancelacion33(
            $emisorPac['EMISOR']['WS_CFDI'], 
            $emisorPac['PACMETODOS']['ws_metodoObtenerAcuseCancelacion'], 
            $emisorPac['PACMETODOS']['ws_metodoObtenerAcuseCancelacion'].'Result', 
            $emisorPac['EMISOR']['WS_LOGIN'], 
            $emisorPac['EMISOR']['WS_PASSWD'], 
            $uuid
        );
        
        $result1 = $this->guardarAcuseCancelacion($idCancelacion, $pacAcuseCancelacion);
        
        $result2 = $this->actualizarEstadoComprobante($uuid);
    
    $this->db->commit();
        

        if($result2) {
            $response = array(
                'error'=>NULL,
                'request'=>$params,
                'data'=>array(
                        'IdCancelacion'=>$idCancelacion
                    )
            );
        }
          
        return json_encode($response);
        
    }
    
    
}