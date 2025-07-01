<?php
require_once 'core/modelBase.php';
require_once 'core/CFDI.php';
/**
 * Description of CFDi
 *
 * @author godoy
 */
class Solicitud extends modelBase {
    
    
    function facturarGroup(){
        $json = '';
        $raw = fopen("php://input", "r");
        while ($data = fread($raw, 1024)) 
                $json .= $data;
        fclose($raw);
        $data = json_decode($json,true);
        /**
         *  filtro de seguriddad
         */
        if(!is_array($data) || count($data)<=0){
            throw new Exception('Seleccione al menos una factura');
        }

        foreach($data as $row){$payments[] = $row['PAYMENTID'];}
        $payments = "'" . implode("','", $payments) ."'";

        // paso de seguridad
        // por seguridad
        // descartamos los payments que ya se encuentren facturados
        $SQL = "select PAYMENTID from SOLICITUD_ENC 
                where PAYMENTID in ($payments) and SOLICITUD_ESTADO =:estado";
        $stmt = $this->db->prepare($SQL);
        $stmt->bindValue(':estado',0,PDO::PARAM_STR);
        $stmt->execute();
        $payments = $stmt->fetchAll();
        
        
        return $this->solicitud2CFDi($payments);
    }
    
    
    function facturar($data) {
                
        $paymentid = $data['paymentid'];
        
        $SQL = "select PAYMENTID 
                from SOLICITUD_ENC 
                where PAYMENTID in (:paymentid)
                and SOLICITUD_ESTADO =:estado";
        $stmt = $this->db->prepare($SQL);
        $stmt->bindParam(':paymentid', $paymentid, PDO::PARAM_STR);
        $stmt->bindValue(':estado', 0, PDO::PARAM_INT);
        $stmt->execute();
        $payments = $stmt->fetchAll(); 
        
        return $this->solicitud2CFDi($payments);
    }
    
    
    public function insertSol(){
        $data = $this->POST();
        $resp = $this->insert($data);
        return json_encode($resp);
    }
    
    public function listar($params){
        
        /*if( isset($_GET['METODOPAGO']) && isset($_GET['RECEPTOR_RFC']) ) {
            $SQL = "select * 
                    from SOLICITUD_ENC 
                    where SOLICITUD_ESTADO=:estado 
                    and EMISOR_RFC=:emisor_rfc
                    and RECEPTOR_RFC = :receptor_rfc
                    and METODOPAGO = :metodopago 
                    order by FECHA asc";
            $stmt = $this->db->prepare($SQL);
            $stmt->bindValue(':estado',0, PDO::PARAM_INT);
            $stmt->bindValue(':emisor_rfc', $this->emisor_rfc, PDO::PARAM_STR);
            $stmt->bindValue(':receptor_rfc',$_GET['RECEPTOR_RFC'], PDO::PARAM_STR);            
            $stmt->bindValue(':metodopago',$_GET['METODOPAGO'], PDO::PARAM_STR);
            $stmt->execute();
            $result = $stmt->fetchAll();
            httpResp::setHeaders(200);
            return json_encode($result);
        }        
        
        if( isset($_GET['RECEPTOR_RFC']) ) {
            $SQL = "select * 
                    from SOLICITUD_ENC 
                    where SOLICITUD_ESTADO=:estado 
                    and EMISOR_RFC=:emisor_rfc
                    and RECEPTOR_RFC = :receptor_rfc 
                    order by FECHA asc";
            $stmt = $this->db->prepare($SQL);
            $stmt->bindValue(':estado',0, PDO::PARAM_INT);            
            $stmt->bindValue(':emisor_rfc', $this->emisor_rfc, PDO::PARAM_STR);
            $stmt->bindValue(':receptor_rfc', $_GET['RECEPTOR_RFC'], PDO::PARAM_STR);
            $stmt->execute();
            $result = $stmt->fetchAll();
            httpResp::setHeaders(200);
            return json_encode($result);
        }
        
        $SQL = "select * 
                from SOLICITUD_ENC
                where SOLICITUD_ESTADO=:estado 
                and EMISOR_RFC=:emisor_rfc                
                and RECEPTOR_RFC<>:receptor_rfc                 
                order by FECHA asc";
         * 
         */
        
        $SQL = "select * 
                from SOLICITUD_ENC
                where SOLICITUD_ESTADO=:estado
                and EMISOR=:emisor
                order by FECHA asc";
        
        $stmt = $this->db->prepare($SQL);
        $stmt->bindValue(':estado',0, PDO::PARAM_INT);        
        $stmt->bindValue(':emisor', $this->emisor, PDO::PARAM_STR);        
        $stmt->execute();
        $result = $stmt->fetchAll();
        httpResp::setHeaders(200);
        return json_encode($result);
        
    }
    
    
    
    public function timbrarSol(){
        
        $data = $this->POST();
        
        $request = $data['REQUEST'];
        if(count($data['DATA'])==0)
            throw new Exception("No hay solicitudes");
        
        if(count($data['DATA'])!=1)
            throw new Exception('Solo puede timbrar una sola solicitud');
        
        // traemos los datos de logueo del pac
        if (!$this->paclogin($data['DATA'][0]['COMPANY']['EMISOR_ID']) ) 
            throw new Exception("No se pudo iniciar la sesión para el emisor dado.");
        
        list($resp) = $this->insert($data);
        
        if ($resp['ESTADO']=='ACEPTADO') {
            try{
                $r = $this->facturar(array('paymentid'=>$resp['PAYMENTID']));        
                $cfdi = json_decode($r,true);
                $resp['CFDI'] = $cfdi['CFDI_UUID'];
            }catch(Exception $e){
                $resp['ESTADO'] = 'RECHAZADO';
                $resp['RAZON]'] ='Error al solicitar CFDi';
                $resp['CFDI'] = array('error'=>$e->getMessage(),'debug'=>$e->getTraceAsString());
                
            }
        }
        
        return json_encode($resp);
        
    }
    
    
    
    /*
     * 
     * 
     * 
     * 
     * 
     * 
     */
    
    

    private function insert($data){
        
        // encabezado de la solicitud
        $request = $data['REQUEST'];
        
        // solicitudes
        if(count($data['DATA'])==0)
            throw new Exception('No hay solicitudes que procesar o el arreglo de cfdi esta vacío');
        
        
        // query para insertar el encabezado
        $SQLENC = " insert into  SOLICITUD_ENC (
                    ID, PAYMENTID, SOLICITUD_ESTADO, CONDICIONESPAGO, METODOPAGO,
                    BANCOCLIENTE, CUENTABANCO, SUBTOTAL, DESCUENTO, TOTAL, FECHA,
                    EMISOR_RFC, EMISOR_NOMBRE, EMISOR_CALLE, EMISOR_NOEXTERIOR, EMISOR_NOINTERIOR,
                    EMISOR_COLONIA, EMISOR_LOCALIDAD, EMISOR_MUNICIPIO, EMISOR_ESTADO,
                    EMISOR_PAIS, EMISOR_CP, EMISOR_EXPEDIDOEN, EMISOR_REGIMEN, RECEPTOR_RFC, RECEPTOR_NOMBRE,
                    RECEPTOR_CALLE, RECEPTOR_NOEXTERIOR, RECEPTOR_NOINTERIOR, RECEPTOR_COLONIA,
                    RECEPTOR_LOCALIDAD, RECEPTOR_MUNICIPIO, RECEPTOR_ESTADO, RECEPTOR_PAIS,
                    RECEPTOR_CP, RECEPTOR_EMAIL, NODO, FECHA_SOLICITUD, USUARIO, EMISOR)
                    VALUES(
                    :ID, :PAYMENTID, :SOLICITUD_ESTADO, :CONDICIONESPAGO, :METODOPAGO,
                    :BANCOCLIENTE, :CUENTABANCO, :SUBTOTAL, :DESCUENTO, :TOTAL, :FECHA,
                    :EMISOR_RFC, :EMISOR_NOMBRE, :EMISOR_CALLE, :EMISOR_NOEXTERIOR, :EMISOR_NOINTERIOR,
                    :EMISOR_COLONIA, :EMISOR_LOCALIDAD, :EMISOR_MUNICIPIO, :EMISOR_ESTADO,
                    :EMISOR_PAIS, :EMISOR_CP, :EMISOR_EXPEDIDOEN, :EMISOR_REGIMEN, :RECEPTOR_RFC, :RECEPTOR_NOMBRE,
                    :RECEPTOR_CALLE, :RECEPTOR_NOEXTERIOR, :RECEPTOR_NOINTERIOR, :RECEPTOR_COLONIA,
                    :RECEPTOR_LOCALIDAD, :RECEPTOR_MUNICIPIO, :RECEPTOR_ESTADO, :RECEPTOR_PAIS,
                    :RECEPTOR_CP, :RECEPTOR_EMAIL, :NODO, CURRENT_TIMESTAMP(), :USUARIO, :EMISOR)";
        
        // query para insertar el detalle
        $SQLDET = " INSERT INTO SOLICITUD_DET(
                    ID, PAYMENTID, CANTIDAD, CODIGOPRODUCTO, CONCEPTO, UNIDAD,
                    PRECIOUNITARIO, DESCUENTO, TASAIMPUESTO, IMPUESTO, IMPORTE )
                    VALUES(
                    :ID, :PAYMENTID, :CANTIDAD, :CODIGOPRODUCTO, :CONCEPTO, :UNIDAD,
                    :PRECIOUNITARIO, :DESCUENTO, :TASAIMPUESTO, :IMPUESTO, :IMPORTE )";
        
        $respuesta = array();
        
        foreach ($data['DATA'] as $row) {
            
            try{
                
                $this->db->beginTransaction();
            
            
                $company = $row['COMPANY'];     // emisor
                $customer = $row['CUSTOMER'];   // receptor
                $ticket = $row['TICKET'];      // encabezado ticket
                $detalle = $row['TICKETLINES']; // detalle
                
                // si un valor viene en blanco lo eliminamos del arreglo
                // para que intente insertar valores null en lugar de 
                // un espacio en blanco
                foreach ($company as $k=>$v) if($v=='') unset($company[$k]);
                foreach ($customer as $k=>$v) if($v=='') unset($customer[$k]);
                foreach ($ticket as $k=>$v) if($v=='') unset($ticket[$k]);
                
                $subtotal = 0;
                $descuento = 0;
                $impuesto = 0;
                $total = 0;
                foreach ($detalle as $item){
                    $subtotal   +=  round( ((float)$item['CANTIDAD']*(float)$item['PRECIOUNITARIO']), 2);
                    $descuento  +=  round( (float)$item['DESCUENTO'], 2);
                    $impuesto   +=  round( (float)$item['IMPUESTO'], 2);
                    $total      +=  round( ((float)$item['CANTIDAD']*(float)$item['PRECIOUNITARIO']), 2) - round( (float)$item['DESCUENTO'], 2) + round( (float)$item['IMPUESTO'], 2);
                }
                
                /**
                 * Encabezado de la solicitud
                 */
                $stmt = $this->db->prepare($SQLENC);
                $stmt->bindParam(':ID', $ticket['ID'],PDO::PARAM_STR);
                $stmt->bindParam(':PAYMENTID', $ticket['PAYMENTID'],PDO::PARAM_STR);
                $stmt->bindValue(':SOLICITUD_ESTADO', 0,PDO::PARAM_INT);
                $stmt->bindParam(':CONDICIONESPAGO', $customer['CONDICIONESPAGO'],PDO::PARAM_STR);
                $stmt->bindParam(':METODOPAGO', $customer['METODOPAGO'],PDO::PARAM_STR);
                $stmt->bindParam(':BANCOCLIENTE', $customer['BANCOCLIENTE'],PDO::PARAM_STR);
                $stmt->bindParam(':CUENTABANCO', $customer['CUENTABANCO'],PDO::PARAM_STR);
                $stmt->bindParam(':SUBTOTAL', $subtotal);
                $stmt->bindParam(':DESCUENTO', $descuento);
                $stmt->bindParam(':TOTAL', $total);
                $date_time = str_replace('T',' ',$ticket['FECHA']);
                $stmt->bindParam(':FECHA', $date_time, PDO::PARAM_STR);
                
                if (isset($company['EXPEDIDOEN'])) {
                    $expedidoEn = json_encode($company['EXPEDIDOEN']);
                } else {
                    $expedidoEn = null;
                }
                $stmt->bindParam(':EMISOR_RFC', $company['RFC'],PDO::PARAM_STR);
                $stmt->bindParam(':EMISOR_NOMBRE', $company['NOMBRE'],PDO::PARAM_STR);
                $stmt->bindParam(':EMISOR_CALLE', $company['CALLE'],PDO::PARAM_STR);
                $stmt->bindParam(':EMISOR_NOEXTERIOR', $company['NOEXTERIOR'],PDO::PARAM_STR);
                $stmt->bindParam(':EMISOR_NOINTERIOR', $company['NOINTERIOR'],PDO::PARAM_STR);
                $stmt->bindParam(':EMISOR_COLONIA', $company['COLONIA'],PDO::PARAM_STR);
                $stmt->bindParam(':EMISOR_LOCALIDAD', $company['LOCALIDAD'],PDO::PARAM_STR);
                $stmt->bindParam(':EMISOR_MUNICIPIO', $company['MUNICIPIO'],PDO::PARAM_STR);
                $stmt->bindParam(':EMISOR_ESTADO', $company['ESTADO'],PDO::PARAM_STR);
                $stmt->bindParam(':EMISOR_PAIS', $company['PAIS'],PDO::PARAM_STR);
                $stmt->bindParam(':EMISOR_CP', $company['CODIGOPOSTAL'],PDO::PARAM_STR);
                $stmt->bindParam(':EMISOR_EXPEDIDOEN', $expedidoEn,PDO::PARAM_STR);
                $stmt->bindParam(':EMISOR_REGIMEN', $company['REGIMENFISCAL'],PDO::PARAM_STR);
                $stmt->bindParam(':EMISOR', $company['EMISOR_ID'],PDO::PARAM_STR);

                $stmt->bindParam(':RECEPTOR_RFC', $customer['RFC'],PDO::PARAM_STR);
                $stmt->bindParam(':RECEPTOR_NOMBRE', $customer['NOMBRE'],PDO::PARAM_STR);
                $stmt->bindParam(':RECEPTOR_CALLE', $customer['CALLE'],PDO::PARAM_STR);
                $stmt->bindParam(':RECEPTOR_NOEXTERIOR', $customer['NOEXTERIOR'],PDO::PARAM_STR);
                $stmt->bindParam(':RECEPTOR_NOINTERIOR', $customer['NOINTERIOR'],PDO::PARAM_STR);
                $stmt->bindParam(':RECEPTOR_COLONIA', $customer['COLONIA'],PDO::PARAM_STR);
                $stmt->bindParam(':RECEPTOR_LOCALIDAD', $customer['LOCALIDAD'],PDO::PARAM_STR);
                $stmt->bindParam(':RECEPTOR_MUNICIPIO', $customer['MUNICIPIO'],PDO::PARAM_STR);
                $stmt->bindParam(':RECEPTOR_ESTADO', $customer['ESTADO'],PDO::PARAM_STR);
                $stmt->bindParam(':RECEPTOR_PAIS', $customer['PAIS'],PDO::PARAM_STR);
                $stmt->bindParam(':RECEPTOR_CP', $customer['CODIGOPOSTAL'],PDO::PARAM_STR);
                $stmt->bindParam(':RECEPTOR_EMAIL', $customer['EMAIL'],PDO::PARAM_STR);
                
                $stmt->bindParam(':NODO', $request['NODE'], PDO::PARAM_STR);
                $stmt->bindParam(':USUARIO', $request['PERSON_ID'], PDO::PARAM_STR);

                $stmt->execute();
                if(!$stmt->rowCount())
                    throw new Exception('Imposible guardar en Base de Datos');

                $stmt->closeCursor();


                /**
                 * detallado de la solicitud
                 */
                $stmt = $this->db->prepare($SQLDET);
                foreach ($detalle as $item) {
                    foreach ($item as $k=>$v) if($v=='') unset($item[$k]);
                    $dpru = round( (float)$item['PRECIOUNITARIO'], 2);
                    $ddes = round( (float)$item['DESCUENTO'], 2);
                    $dimo = round( (float)$item['IMPUESTO'], 2);
                    $dime = round( (float)$item['IMPORTE'], 2);
                    $dtsum += $dime-$ddes+$dimo;
                    $stmt->bindParam(':ID', $ticket['ID'],PDO::PARAM_STR);
                    $stmt->bindParam(':PAYMENTID', $ticket['PAYMENTID'],PDO::PARAM_STR);
                    $stmt->bindParam(':CANTIDAD', $item['CANTIDAD'],PDO::PARAM_INT);
                    $stmt->bindParam(':CODIGOPRODUCTO', $item['CODIGOPRODUCTO'],PDO::PARAM_STR);
                    $stmt->bindParam(':CONCEPTO', $item['CONCEPTO'],PDO::PARAM_STR);
                    $stmt->bindParam(':UNIDAD', $item['UNIDAD'],PDO::PARAM_STR);
                    $stmt->bindParam(':PRECIOUNITARIO', $dpru);
                    $stmt->bindParam(':DESCUENTO', $ddes);
                    $stmt->bindParam(':TASAIMPUESTO', $item['TASAIMPUESTO']);
                    $stmt->bindParam(':IMPUESTO', $dimo);
                    $stmt->bindParam(':IMPORTE', $dime);
                    $stmt->execute();
                    if(!$stmt->rowCount())
                        throw new Exception('Imposible guardar en Base de Datos');
                }

                $stmt->closeCursor();

                /*
                 * ajuste por redondeos al subtotal
                 */
                $diferencia = round($total-$dtsum, 2);
                if (abs($diferencia)<0.02 && abs($diferencia)>0.0) {
                    $subtotalaju = round($subtotal+$diferencia, 2);
                    $SQLAJU = " UPDATE SOLICITUD_ENC
                                SET SUBTOTAL=:SUBTOTALAJU
                                WHERE PAYMENTID=:PAYMENTID"; 
                    $stmt = $this->db->prepare($SQLAJU);
                    $stmt->bindParam(':PAYMENTID', $ticket['PAYMENTID'], PDO::PARAM_STR);
                    $stmt->bindParam(':SUBTOTALAJU', $subtotalaju);
                    $stmt->execute();
                    
                    if(!$stmt->rowCount())
                        throw new Exception('Imposible realizar ajuste al subtotal');
                }
                
                $respuesta[] = array(
                    'ID'=>$ticket['ID'],
                    'PAYMENTID'=>$ticket['PAYMENTID'],
                    'ESTADO'=>'ACEPTADO',
                    'RAZON'=>'');
                
                $this->db->commit();
            } catch(Exception $e) {
                
                $respuesta[] = array(
                    'ID'=>$ticket['ID'],
                    'PAYMENTID'=>$ticket['PAYMENTID'],
                    'ESTADO'=>'RECHAZADO',
                    'RAZON'=>$e->getMessage());
                
                $this->db->rollback();
                
            }
            
        
        }
        httpResp::setHeaders(200);
        return $respuesta;
        
    }
    
    
   
    
    
    
    
    
   
    
    /*
    private function getKeyCer($rfc){
        $SQL = "select PKEY, CER, NO_CERTIFICADO 
                from EMISORES where RFC=:rfc";
        $stmt = $this->db->prepare($SQL);
        $stmt->bindParam(':rfc',$rfc,PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetch();
        
    }
     * 
     */
    
    private function getSerie(){
        $SQL = "SELECT SERIE
                FROM FOLIOS 
                WHERE EMISOR=:emisor";
        $stmt = $this->db->prepare($SQL);
        $stmt->bindParam(':emisor', $this->emisor, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetchColumn();
    }
    
    
    private function getFolio($serie){
        $SQL = "SELECT FOLIO + 1 
                FROM FOLIOS 
                WHERE EMISOR=:emisor
                AND SERIE=:serie                 
                FOR UPDATE";
        $stmt = $this->db->prepare($SQL);
        $stmt->bindParam(':emisor', $this->emisor, PDO::PARAM_STR);
        $stmt->bindParam(':serie', $serie, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetchColumn();
    }
    
    private function setFolio($serie) {
        $usuario = $_SESSION['capricho']['id'];
        $SQL = "UPDATE FOLIOS 
                SET FOLIO = FOLIO + 1, 
                USUARIO_ID = :usuario,
                FECHA = current_timestamp()
                WHERE EMISOR=:emisor
                AND SERIE=:serie";
        $stmt = $this->db->prepare($SQL);
        $stmt = $this->db->prepare($SQL);
        $stmt->bindParam(':emisor', $this->emisor, PDO::PARAM_STR);
        $stmt->bindParam(':serie', $serie,PDO::PARAM_STR);
        $stmt->bindParam(':usuario', $this->usuario_id,PDO::PARAM_STR);
        $stmt->execute();
    }
    
    private function getDetalle($payments){
        $SQL = "select d.CODIGOPRODUCTO, d.CONCEPTO, d.UNIDAD, d.PRECIOUNITARIO, d.TASAIMPUESTO,
                sum(d.CANTIDAD) as  CANTIDAD,
                sum(d.DESCUENTO) as DESCUENTO,
                sum(d.IMPUESTO) as  IMPUESTO,
                sum(d.IMPORTE)  as  IMPORTE 
                from SOLICITUD_ENC e
                inner join SOLICITUD_DET d on e.PAYMENTID = d.PAYMENTID
                where e.PAYMENTID in ($payments)
                group by d.CODIGOPRODUCTO, d.PRECIOUNITARIO";
        $stmt =$this->db->prepare($SQL);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    private function getImpuestos($payments){
        $SQL = "select 
                'IVA' AS IMPUESTO,
                TASAIMPUESTO, 
                sum(IMPUESTO) as IMPORTE
                from SOLICITUD_ENC e                 
                inner join SOLICITUD_DET d on e.PAYMENTID = d.PAYMENTID
                where e.PAYMENTID in ($payments)
                group by TASAIMPUESTO";

        $stmt =$this->db->prepare($SQL);
        $stmt->execute();		
        return $stmt->fetchAll();
    }
    
    private function getEncabezado($payments){
        $SQL = "select * 
                from SOLICITUD_ENC 
                where PAYMENTID in ($payments)";
        $stmt =$this->db->prepare($SQL);
        $stmt->execute();		
        $enc = $stmt->fetchAll();
        return $enc[0];
    }
    
    private function getTotales($payments){
        $SQL = "select 
                sum(d.IMPORTE) as SUBTOTAL,
                sum(d.DESCUENTO) as DESCUENTO,
                sum(d.IMPORTE - d.DESCUENTO + d.IMPUESTO) as TOTAL
                from SOLICITUD_ENC e
                inner join SOLICITUD_DET d on e.PAYMENTID = d.PAYMENTID
                where e.PAYMENTID in ($payments)";
        $stmt =$this->db->prepare($SQL);
        $stmt->execute();		
        return $stmt->fetch();
    }
    
    private function guardarCFDi($payments, $xml, $serie, $folio, $UUID, $subtotal, $descuento, $total){
        $SQL = "insert into FACTURAS
                select :cfdi_uuid, :serie, :folio, :estado, :xml, :xml_cancelado,
                CONDICIONESPAGO, METODOPAGO, BANCOCLIENTE, CUENTABANCO, 
                :SUBTOTAL, :DESCUENTO, :TOTAL, FECHA,
                EMISOR_RFC, EMISOR_NOMBRE, EMISOR_CALLE, EMISOR_NOEXTERIOR, EMISOR_NOINTERIOR,
                EMISOR_COLONIA, EMISOR_LOCALIDAD, EMISOR_MUNICIPIO, EMISOR_ESTADO,
                EMISOR_PAIS, EMISOR_CP, EMISOR_EXPEDIDOEN, EMISOR_REGIMEN, RECEPTOR_RFC, RECEPTOR_NOMBRE,
                RECEPTOR_CALLE, RECEPTOR_NOEXTERIOR, RECEPTOR_NOINTERIOR, RECEPTOR_COLONIA,
                RECEPTOR_LOCALIDAD, RECEPTOR_MUNICIPIO, RECEPTOR_ESTADO, RECEPTOR_PAIS, RECEPTOR_CP, 
                RECEPTOR_EMAIL, :envios 
                from SOLICITUD_ENC 
                where PAYMENTID in ($payments) LIMIT 1";
        $stmt = $this->db->prepare($SQL);
        $stmt->bindParam(':cfdi_uuid',$UUID, PDO::PARAM_STR);
        $stmt->bindParam(':serie',$serie, PDO::PARAM_STR);
        $stmt->bindParam(':folio',$folio, PDO::PARAM_INT);
        $stmt->bindValue(':estado',1, PDO::PARAM_INT);
        $stmt->bindValue(':xml',$xml, PDO::PARAM_STR);
        $stmt->bindValue(':SUBTOTAL',$subtotal);
        $stmt->bindValue(':DESCUENTO',$descuento);
        $stmt->bindValue(':TOTAL',$total);
        $stmt->bindValue(':xml_cancelado','');
        $stmt->bindValue(':envios',0);
        
        $stmt->execute();
        $stmt->closeCursor();
        
        
        /**
         * actualizamos todos las
         * solicitudes y marcamos como timbradas
         * y le asignamos un cfdi_uuid
         */
        $SQL = "UPDATE SOLICITUD_ENC SET 
                SOLICITUD_ESTADO = :estado,
                CFDI_UUID = :cfdi_uuid
                WHERE PAYMENTID in ($payments)";
        $stmt = $this->db->prepare($SQL);
        $stmt->bindParam(':cfdi_uuid', $UUID, PDO::PARAM_STR);
        $stmt->bindValue(':estado', 1, PDO::PARAM_INT);
        $stmt->execute();
        $stmt->closeCursor();
        
        return true;
        
        
    }
    
    
    
    
  
    

    
    
    /*
     * 
     */
    private function solicitud2CFDi($paymentIDs){

        if(!is_array($paymentIDs) && count($paymentIDs)==0)
            throw new Exception('Petición incorrecta, seleccione un recibo de pago');
        
        $payments = array();
        foreach($paymentIDs as $row) $payments[] = $row['PAYMENTID'];
        $payments = "'" . implode("','", $payments) ."'";
        
        $this->db->beginTransaction();
        
        // folio por emisor
        //$serie = 'A';
        $serie = $this->getSerie();
        
        $folio = $this->getFolio($serie);
        
        // detalle 
        $det = $this->getDetalle($payments);
        
        // totales del detalle
        $tot = $this->getTotales($payments);
        
        // encabezado
        $enc = $this->getEncabezado($payments);        
        $enc['SUBTOTAL'] = $tot['SUBTOTAL'];
        $enc['DESCUENTO'] = $tot['DESCUENTO'];
        $enc['TOTAL'] = $tot['TOTAL'];
        
        // impuestos transladados
        $imp = $this->getImpuestos($payments);
        
        //-----------------------------------
        $cfdi = new CFDI();
        
        // generamos el xml
        $xml = $cfdi->generarXML($serie, $folio, $enc, $det, $imp);        
        
        // timbramos el xml
        list($xmlTimbrado, $xmlAcuse) = $cfdi->timbrarXML(
                                        $this->ws_cfdi, $this->ws_login, $this->ws_passwd, 
                                        $this->ws_metodoTimbrado, $this->ws_metodoTimbradoResult,
                                        $xml, $serie, $folio);
        
        $datos = $cfdi->xmltoarray($xmlTimbrado);
        $UUID = $datos['TimbreFiscalDigital']['UUID'];
        
        // guardamos en la base de datos
        $this->guardarCFDi($payments, $xmlTimbrado, $serie, $folio, $UUID, $tot['SUBTOTAL'], $tot['DESCUENTO'], $tot['TOTAL']);
        
        $this->setFolio($serie);
        
        $this->db->commit();
        
        httpResp::setHeaders(200);        
        
        $result = array('CFDI_UUID'=>$UUID,'PAYMENTID'=>$paymentIDs);
        
        return json_encode($result);
        
    }
    
    
    
    
    
    
}
