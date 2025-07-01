<?php
require_once 'core/modelBase.php';
//require_once 'core/CFDI.php';
require_once 'PDF/XML2PDF.php';
require_once 'core/Correo.php';
require_once 'core/CFDI.php';
/**
 * Description of Facturacion
 *
 * @author godoy
 */
class Facturacion extends modelBase{
    

    function listarCFDi($params) {                
        $SQL = "    select  f.SERIE, f.FOLIO, f.ENVIOS, f.FECHA, f.RECEPTOR_EMAIL, f.RECEPTOR_RFC, 
                            f.METODOPAGO, f.SUBTOTAL, f.DESCUENTO, f.TOTAL, f.CFDI_UUID 
                    from        FACTURAS f 
                    inner join	SOLICITUD_ENC s on f.CFDI_UUID=s.CFDI_UUID
                    where       f.FECHA between :fechaIni and :fechaFin
                    and         s.EMISOR=:emisor
                    order       by SERIE, FOLIO";
        
        $stmt = $this->db->prepare($SQL);
        $stmt->bindParam(':fechaIni',$params['fechaIni'], PDO::PARAM_INT);
        $stmt->bindParam(':fechaFin',$params['fechaFin'], PDO::PARAM_INT);
        $stmt->bindParam(':emisor', $this->emisor, PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetchAll();
        httpResp::setHeaders(200);
        return json_encode($result);
        
    }

    function emailCFDi(){
        
        $data = $this->POST();
        
        $dataExito = array();
        
        foreach($data as $row){
            
            $uuid = $row['CFDI_UUID'];
            $destinatario = $row['RECEPTOR_EMAIL'];
            $nombre = $row['RECEPTOR_NOMBRE'];
            
            $SQL = "select S.EMISOR, F.RECEPTOR_RFC, F.EMISOR_RFC, F.SERIE, F.FOLIO, 
                    F.XML, F.TOTAL, E.LOGO, IFNULL(M.NAME,'') AS METODOPAGO 
                    from FACTURAS F
                    inner join SOLICITUD_ENC S on F.CFDI_UUID=S.CFDI_UUID
                    inner join EMISORES E on S.EMISOR=E.ID
                    left join METODOSPAGO M on F.METODOPAGO=M.KEY
                    where F.CFDI_UUID=:uuid";
            $stmt = $this->db->prepare($SQL);
            $stmt->bindParam(':uuid', $uuid, PDO::PARAM_STR);
            $stmt->execute();
            $result = $stmt->fetch();
            
            $emisor = $result['EMISOR'];
            $recprfc = $result['RECEPTOR_RFC'];
            $emisrfc = $result['EMISOR_RFC'];
            $serie = $result['SERIE'];
            $folio = $result['FOLIO'];
            $xml = $result['XML'];
            $total = $result['TOTAL'];
            $logo = $result['LOGO'];
            $metodopago = $result['METODOPAGO'];
            
            $SQL = "select REMITENTE, EMAIL_RESPUESTA, ASUNTO, CUERPO
                    from EMISORES_EMAIL                    
                    where EMISOR=:emisor";
            $stmt = $this->db->prepare($SQL);
            $stmt->bindParam(':emisor', $emisor, PDO::PARAM_STR);
            $stmt->execute();
            $result = $stmt->fetch();
            
            $remitente = $result['REMITENTE'];
            $emailResp = $result['EMAIL_RESPUESTA'];
            $asunto = $result['ASUNTO'];
            $cuerpoPlantilla = $result['CUERPO'];
            
            $xmlFile = "temp/$uuid.xml";
            $pdfFile = "temp/$uuid.pdf";
            $files = array($xmlFile, $pdfFile);
            
            $xml2pdf = new XML2PDF($logo, $metodopago);
            $pdf = $xml2pdf->xmltopdf($xml,'S');
            
            file_put_contents($xmlFile, $xml);
            file_put_contents($pdfFile, $pdf);
                        
            $mail = new Correo($remitente);            
            $dest = array($destinatario, $nombre);
            
            $aguja = array('{{recprfc}}', '{{uuid}}', '{{emisrfc}}', '{{serie}}', '{{folio}}', '{{total}}', '{{emailresp}}');
            $reemplazo = array($recprfc, $uuid, $emisrfc, $serie, $folio, $total, $emailResp);
            
            $cuerpo = str_replace($aguja, $reemplazo, $cuerpoPlantilla);            
                    
            if($mail->Enviar($remitente, $asunto, $emailResp, $remitente, $dest, $files, $cuerpo)){
                $dataExito[] = $row;
                $SQL = "UPDATE FACTURAS SET ENVIOS = ENVIOS+1 
                        WHERE CFDI_UUID = :uuid";
                $stmt = $this->db->prepare($SQL);
                $stmt->bindParam(':uuid',$uuid,PDO::PARAM_STR);
                $stmt->execute();
            }else{
                throw new Exception('Imposible enviar el correo al destinatario, ' . $destinatario. ', razón' . $mail->ErrorInfo);
            }
            
            @unlink($xmlFile);
            @unlink($pdfFile);
        }
        
        httpResp::setHeaders(200);
        return json_encode($dataExito);
        
    }
    
    
    function cancelarCFDi($params) {
        
        $UUID = $params['uuid'];
        
        $cfdi = new CFDI();
        
        list($codigo, $msg, $acuse) = $cfdi->cancelarCFDi(
                $UUID, $this->ws_cfdi, $this->ws_login, $this->ws_passwd, 
                $this->ws_metodoCancelar, $this->ws_metodoCancelarResult);
        
        if ( $codigo != 1 )
            return false;        
        
        file_put_contents('temp/acuse.xml', $acuse);
        file_put_contents('temp/codigo.txt', $codigo);
        file_put_contents('temp/msg.txt', $msg);        
        
        $SQL = "UPDATE FACTURAS SET 
                XML_CANCELADO = :XML,
                ESTADO = :ESTADO
                WHERE CFDI_UUID = :UUID";
        $stmt = $this->db->prepare($SQL);
        $stmt->bindParam(':XML',$acuse,PDO::PARAM_STR);
        $stmt->bindValue(':ESTADO',0,PDO::PARAM_INT);
        $stmt->bindParam(':UUID',$UUID,PDO::PARAM_STR);
        $stmt->execute();
        
        $SQL = "UPDATE SOLICITUD_ENC SET 
                PAYMENTID = UUID(),
                SOLICITUD_ESTADO = :ESTADO
                WHERE CFDI_UUID = :UUID";
        $stmt = $this->db->prepare($SQL);
        $stmt->bindValue(':ESTADO',-1,PDO::PARAM_INT);
        $stmt->bindParam(':UUID',$UUID,PDO::PARAM_STR);
        $stmt->execute();

        return true;

    } 
    
    function obtenerCFDi($params){
        $uuid = $params['uuid'];
        $format = $params['format'];        
        $SQL = "select F.XML, E.LOGO, IFNULL(M.NAME,'') AS METODOPAGO 
                    from FACTURAS F 
                    inner join SOLICITUD_ENC S on F.CFDI_UUID=S.CFDI_UUID
                    inner join EMISORES E on S.EMISOR=E.ID
                    left join METODOSPAGO M on F.METODOPAGO=M.KEY
                    where F.CFDI_UUID=:uuid";                
        $stmt = $this->db->prepare($SQL);
        $stmt->bindParam(':uuid',$uuid,PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetch();
        $xml = $result['XML'];        
        
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
        
        $xml2pdf = new XML2PDF($result['LOGO'],$result['METODOPAGO']);
        
        if($format=='json'){
            header("HTTP/1.1 200 OK");
            header("Content-Type: application/json"); 
            header( 'Expires: Sat, 26 Jul 1997 05:00:00 GMT' ); 
            header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s' ) . ' GMT' ); 
            header( 'Cache-Control: no-store, no-cache, must-revalidate' ); 
            header( 'Cache-Control: post-check=0, pre-check=0', false ); 
            header( 'Pragma: no-cache' ); 
            return json_encode($xml2pdf->xmltoarray($xml));
        }
            
        
        if($format=='pdf'){            
            header("HTTP/1.1 200 OK");
            header("Content-Type: application/pdf"); 
            header( 'Expires: Sat, 26 Jul 1997 05:00:00 GMT' ); 
            header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s' ) . ' GMT' ); 
            header( 'Cache-Control: no-store, no-cache, must-revalidate' ); 
            header( 'Cache-Control: post-check=0, pre-check=0', false ); 
            header( 'Pragma: no-cache' );
            $xml2pdf->xmltopdf($xml,'I');
            return;
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
            
            $pdf = $xml2pdf->xmltopdf($xml,'S');
            $zip->addFromString("$uuid.xml", $xml);
            $zip->addFromString("$uuid.pdf", $pdf);
            $zip->close();
            return file_get_contents($filename);
            @unlink($filename);
        }
        
    }
    
    
    function actualizarCFDi($params) {
        
        $UUID = $params['uuid'];
        
        $SQL = "select XML from FACTURAS where CFDI_UUID=:uuid";
        $stmt = $this->db->prepare($SQL);
        $stmt->bindParam(':uuid', $UUID, PDO::PARAM_STR);
        $stmt->execute();
        $xml = $stmt->fetch();                        
        if(!$xml)
            throw new Exception('El UUID no existe');
                
        $cfdi = new CFDI();
        
        list($res, $msj, $xml) = $cfdi->obtenerCFDi(
                $this->emisor_rfc, $UUID, $this->ws_cfdi, $this->ws_login, $this->ws_passwd,
                $this->ws_metodoObtenerXML, $this->ws_metodoObtenerXMLResult);
        
        if(!$res)
            throw new Exception($msj);
        
        $SQL = "UPDATE FACTURAS SET 
                XML = :XML
                WHERE CFDI_UUID = :UUID";
        $stmt = $this->db->prepare($SQL);
        $stmt->bindParam(':XML', $xml, PDO::PARAM_STR);
        $stmt->bindParam(':UUID', $UUID, PDO::PARAM_STR);
        $stmt->execute();
                
        return true;
    }
    
    
    function obtenerFactura($params){

        $uuid = $params['uuid'];
        $SQL = "select
                    S.PAYMENTID, S.ID,
                    F.CFDI_UUID, 
                    F.EMISOR_RFC,
                    F.SERIE, F.FOLIO,
                    F.ESTADO,
                    IF(F.XML_CANCELADO<>'',1,0) AS CANCELADO,
                    F.SUBTOTAL, F.DESCUENTO, F.TOTAL,
                    F.FECHA,
                    F.RECEPTOR_RFC, F.RECEPTOR_NOMBRE,
                    F.RECEPTOR_EMAIL
                from FACTURAS F
                inner join SOLICITUD_ENC S ON F.CFDI_UUID=S.CFDI_UUID
                where F.CFDI_UUID=:uuid";
        $stmt = $this->db->prepare($SQL);
        $stmt->bindParam(':uuid',$uuid,PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetch();
        httpResp::setHeaders(200);
        return json_encode($result);
    }
    
    function reporteCFDi($params) {
        
        $SQL = "SELECT  F.SERIE, F.FOLIO, IF(F.XML_CANCELADO<>'','SI', '') AS CANCELADO, 
                        F.FECHA, F.TOTAL, 
                        SUM( IF(D.TASAIMPUESTO=16, IMPORTE, 0) ) AS BASE16, 
                        SUM( IF(D.TASAIMPUESTO=16, IMPUESTO, 0) ) AS IMP16, 
                        SUM( IF(D.TASAIMPUESTO=0, IMPORTE, 0) ) AS BASE0, 
                        SUM( IF(D.TASAIMPUESTO=0, IMPUESTO, 0) ) AS IMP0
                FROM FACTURAS F
                INNER JOIN SOLICITUD_ENC S ON F.CFDI_UUID=S.CFDI_UUID
                INNER JOIN SOLICITUD_DET D ON S.PAYMENTID=D.PAYMENTID
                WHERE   S.EMISOR=:emisor
                        AND F.FECHA BETWEEN :fechaIni AND :fechaFin
                GROUP BY F.SERIE, F.FOLIO, F.FECHA, F.SUBTOTAL, F.TOTAL
                ORDER BY F.SERIE, F.FOLIO";
        
        $stmt = $this->db->prepare($SQL);
        $stmt->bindParam(':fechaIni',$params['fechaIni'], PDO::PARAM_INT);
        $stmt->bindParam(':fechaFin',$params['fechaFin'], PDO::PARAM_INT);
        $stmt->bindParam(':emisor', $this->emisor, PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetchAll();
        httpResp::setHeaders(200);
        return json_encode($result);
        
    }
    
    function descargarCFDi($params) {
                                        
        $SQL = "select      F.EMISOR_RFC, F.SERIE, F.FOLIO, F.CFDI_UUID, F.XML, IFNULL(M.NAME,'') AS METODOPAGO 
                from        FACTURAS F
                inner join  SOLICITUD_ENC S ON F.CFDI_UUID=S.CFDI_UUID
                left join   METODOSPAGO M on F.METODOPAGO=M.KEY
                where       S.EMISOR = :emisor
                and         F.FECHA BETWEEN :fechaIni AND :fechaFin
                order by    F.SERIE, F.FOLIO";
        
        $stmt = $this->db->prepare($SQL);
        $stmt->bindParam(':fechaIni',$params['fechaIni'], PDO::PARAM_INT);
        $stmt->bindParam(':fechaFin',$params['fechaFin'], PDO::PARAM_INT);
        $stmt->bindParam(':emisor', $this->emisor, PDO::PARAM_STR);
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

        $filename = 'temp/archivos.zip';
        @unlink($filename);        
        
        if ($zip->open($filename, ZipArchive::CREATE)!==TRUE) {
            exit("cannot open <$filename>\n");
        }
        
        set_time_limit ( 0 );
        foreach ($result as $factura) {                                
            $xml = $factura['XML'];                
            $archivo = $factura['EMISOR_RFC'].'_'.$factura['SERIE'].'-'.$factura['FOLIO'].'_'.$factura['CFDI_UUID'];                
            $xml2pdf = null;                
            $xml2pdf = new XML2PDF($this->logo, $factura['METODOPAGO']);                                
            $pdf = $xml2pdf->xmltopdf($xml,'S');                                
            $zip->addFromString("$archivo.xml", $xml);
            $zip->addFromString("$archivo.pdf", $pdf);
        }            
        $zip->close();
        
        return "app/$filename";

    }
    
    public function solicitarCancelacion(){
        $data = $this->POST();
                
        // encabezado de la solicitud
        $request = $data['REQUEST'];
        
        // detalle de la solicitudes
        if(count($data['DATA'])==0)
            throw new Exception('No hay CFDi en la solicitud de cancelación');
        
        $SQL = "    INSERT INTO SOLICITUD_CAN(PAYMENTID, NODO, FECHA_SOLICITUD, USUARIO, PAYMENTID_CUR, FECHA_MODIFICADO, OBSERVACIONES)
                    VALUES (:PAYMENTID, :NODO, NOW(), :USUARIO, :PAYMENTID, NOW(), :OBSERVACIONES)";
                
        foreach($data['DATA'] as $key=>$val) {
            
            $stmt = $this->db->prepare($SQL);
            $stmt->bindParam(':PAYMENTID', $val['PAYMENTID'], PDO::PARAM_STR);
            $stmt->bindParam(':NODO', $request['NODE'], PDO::PARAM_STR);
            $stmt->bindParam(':USUARIO', $request['PERSON_ID'], PDO::PARAM_STR);
            $stmt->bindParam(':OBSERVACIONES', $val['REASON'], PDO::PARAM_STR);
            $stmt->execute();
            if(!$stmt->rowCount())
                throw new Exception('Imposible guardar en Base de Datos');
            
        }        
        httpResp::setHeaders(200);
        return json_encode($data['DATA']);
    }
        
    public function verificarCancelacion($data) {
        
        $paymentid = $data['paymentid'];
        
        $SQL = "select PAYMENTID, PAYMENTID_CUR
                from SOLICITUD_CAN
                where PAYMENTID = :paymentid";
        $stmt = $this->db->prepare($SQL);
        $stmt->bindParam(':paymentid', $paymentid, PDO::PARAM_STR);
        $stmt->execute();
        return json_encode($stmt->fetch());
        
    }
    
    function listarSolCancelacionCFDi($params) {
        
        $SQL = "select      F.CFDI_UUID, U.NAME, S.FECHA_SOLICITUD, F.SERIE, F.FOLIO,
                            F.RECEPTOR_RFC, F.FECHA, F.SUBTOTAL, F.DESCUENTO, F.TOTAL,
                            S.OBSERVACIONES
                from        SOLICITUD_CAN S
                inner join  SOLICITUD_ENC E ON S.PAYMENTID_CUR=E.PAYMENTID
                inner join  FACTURAS F ON E.CFDI_UUID=F.CFDI_UUID
                left join   USUARIOS U ON S.USUARIO=U.ID
                where       F.ESTADO=1
                and         E.EMISOR=:emisor
                and         S.FECHA_SOLICITUD BETWEEN :fechaIni and :fechaFin";
        
        $stmt = $this->db->prepare($SQL);
        $stmt->bindParam(':fechaIni',$params['fechaIni'], PDO::PARAM_INT);
        $stmt->bindParam(':fechaFin',$params['fechaFin'], PDO::PARAM_INT);
        $stmt->bindParam(':emisor', $this->emisor, PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetchAll();
        httpResp::setHeaders(200);
        return json_encode($result);
        
    }
    
    function obtenerUUID($params) {
        
        $SQL = "    select      f.CFDI_UUID, s.ID, s.PAYMENTID
                    from        FACTURAS f 
                    inner join	SOLICITUD_ENC s on f.CFDI_UUID=s.CFDI_UUID
                    where       s.EMISOR=:emisor
                    and         f.SERIE=:serie
                    and         f.FOLIO=:folio";
        
        $stmt = $this->db->prepare($SQL);
        $stmt->bindParam(':emisor',$params['emisor'], PDO::PARAM_STR);
        $stmt->bindParam(':serie',$params['serie'], PDO::PARAM_STR);
        $stmt->bindParam(':folio',$params['folio'], PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetch();
        
        httpResp::setHeaders(200);
        return json_encode($result);
        
    }
    
    
}

?>
