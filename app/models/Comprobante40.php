<?php

require_once 'core/modelBase.php';
require_once 'core/CFDI.php';

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Comprobante40
 *
 * @author oliver
 */
class Comprobante40 extends modelBase{
    
    function insertar() {
        
            $postData = $this->POST();

            $comprobante = $postData['e_Comprobante'];
            $cfdiRelacionados = $postData['e_CfdiRelacionados'];
            $informacionGlobal = $postData['e_InformacionGlobal'];            
            $conceptos = $postData['e_Conceptos'];

            $impuestosTraslados = $postData['e_Impuestos_Traslados'];
            $impuestosRetenciones = $postData['e_Impuestos_Retenciones'];

            $qDecimales_Id = "SELECT Decimales, UUID() AS _Id FROM V33_C_MONEDA WHERE c_Moneda=:c_Moneda";
            $stmt0 = $this->db->prepare($qDecimales_Id);
            $stmt0->bindParam(':c_Moneda', $comprobante['Moneda'], PDO::PARAM_STR);
            $stmt0->execute();
            $res0 = $stmt0->fetch(PDO::FETCH_ASSOC);
            $decimales = $res0['Decimales'];
            $_Id = $res0['_Id'];


            /* 
             * CALCULOS PARA REALIZAR AJUSTES
             */
            $conceptosSubtotal = 0;
            $conceptosDescuento = 0;
            $cantTraslados = 0;
            $conceptosTraslados = 0;
            $cantRetenciones = 0;
            $conceptosRetenciones = 0;
            for($i=0;$i<count($conceptos);$i++) {
                $conceptosSubtotal += $conceptos[$i]['Importe'];
                $conceptosDescuento += $conceptos[$i]['Descuento'];
                for($j=0;$j<count($conceptos[$i]['e_Impuestos']);$j++) {
                    if($conceptos[$i]['e_Impuestos'][$j]['_Tipo']=='T') {
                        $cantTraslados++;
                        $conceptosTraslados += $conceptos[$i]['e_Impuestos'][$j]['Importe'];
                    }
                    if($conceptos[$i]['e_Impuestos'][$j]['_Tipo']=='R') {
                        $cantRetenciones;
                        $conceptosRetenciones += $conceptos[$i]['e_Impuestos'][$j]['Importe'];
                    }
                }
             }
            $totalTraslados = 0;
            for($i=0;$i<count($impuestosTraslados);$i++){
                $totalTraslados += $impuestosTraslados[$i]['Importe'];
            }
            $totalRetenciones = 0;
            for($i=0;$i<count($impuestosRetenciones);$i++){
                $totalRetenciones += $impuestosRetenciones[$i]['Importe'];
            }

            $trasladoAjuste = round($totalTraslados-$conceptosTraslados,2);
            $retencionAjuste = round($totalRetenciones-$conceptosRetenciones,2);

            /*
             * AJUSTES POR CENTAVO EN LOS IMPUESTOS DE LOS CONCEPTOS
             */
            for($i=0;$i<count($conceptos);$i++) {
                if($trasladoAjuste==0&&$retencionAjuste==0) {
                    break;
                }
                for($j=0;$j<count($conceptos[$i]['e_Impuestos']);$j++) {
                    if($conceptos[$i]['e_Impuestos'][$j]['_Tipo']=='T'&&$trasladoAjuste<0) {
                        $conceptos[$i]['e_Impuestos'][$j]['Importe'] = round($conceptos[$i]['e_Impuestos'][$j]['Importe']-0.01,2);
                        $trasladoAjuste=round($trasladoAjuste+0.01,2);
                    }
                    if($conceptos[$i]['e_Impuestos'][$j]['_Tipo']=='T'&&$trasladoAjuste>0) {
                        $conceptos[$i]['e_Impuestos'][$j]['Importe'] = round($conceptos[$i]['e_Impuestos'][$j]['Importe']+0.01,2);
                        $trasladoAjuste=round($trasladoAjuste-0.01,2);
                    }
                    if($conceptos[$i]['e_Impuestos'][$j]['_Tipo']=='R'&&$retencionAjuste<0) {
                        $conceptos[$i]['e_Impuestos'][$j]['Importe'] = round($conceptos[$i]['e_Impuestos'][$j]['Importe']-0.01,2);
                        $retencionAjuste=round($retencionAjuste+0.01,2);
                    }
                    if($conceptos[$i]['e_Impuestos'][$j]['_Tipo']=='R'&&$retencionAjuste>0) {
                        $conceptos[$i]['e_Impuestos'][$j]['Importe'] = round($conceptos[$i]['e_Impuestos'][$j]['Importe']+0.01,2);
                        $retencionAjuste=round($retencionAjuste-0.01,2);
                    }
                }
            }

            /*
             * CALCULOS PARA VERIFICAR CUADRES
             */
            $conceptosSubtotal = 0;
            $conceptosDescuento = 0;
            $conceptosTraslados = 0;
            $conceptosRetenciones = 0;
            for($i=0;$i<count($conceptos);$i++){
                $conceptosSubtotal += $conceptos[$i]['Importe'];
                $conceptosDescuento += $conceptos[$i]['Descuento'];
                for($j=0;$j<count($conceptos[$i]['e_Impuestos']);$j++) {
                    if($conceptos[$i]['e_Impuestos'][$j]['_Tipo']=='T') {
                        $conceptosTraslados += $conceptos[$i]['e_Impuestos'][$j]['Importe'];
                    }
                    if($conceptos[$i]['e_Impuestos'][$j]['_Tipo']=='R') {
                        $conceptosRetenciones += $conceptos[$i]['e_Impuestos'][$j]['Importe'];
                    }
                }
             }
            $totalTraslados = 0;
            for($i=0;$i<count($impuestosTraslados);$i++) {
                $totalTraslados += $impuestosTraslados[$i]['Importe'];
            }
            $totalRetenciones = 0;
            for($i=0;$i<count($impuestosRetenciones);$i++){
                $totalRetenciones += $impuestosRetenciones[$i]['Importe'];
            }


            $subtotal = round($comprobante['SubTotal'], $decimales);
            $descuento = round($comprobante['Descuento'],$decimales);
            //$base = $conceptosSubtotal-$conceptosDescuento;
            $totalTraslados = round($totalTraslados, $decimales);
            $totalRetenciones = round($totalRetenciones, $decimales);
            $total = round($comprobante['Total'], $decimales);

            if( $subtotal!=round($conceptosSubtotal, $decimales) )
                throw new Exception('Subtotal no cuadra');

            if( $descuento!=round($conceptosDescuento, $decimales) )
                throw new Exception('Descuento no cuadra');

            if( $totalTraslados!=round($conceptosTraslados, $decimales) )
                throw new Exception('Traslados no cuadran');

            if( $totalRetenciones!=round($conceptosRetenciones, $decimales) )
                throw new Exception('Retenciones no cuadran');
            
            if( round(($subtotal-$descuento+$totalTraslados-$totalRetenciones),$decimales)!=round($total,$decimales))
                throw new Exception('Total no cuadra');


            $qComprobante = "INSERT INTO V40_COMPROBANTES(
                _Id, _FechaCreacion, _Estado, _Nodo, _TicketId, _Usuario,
                _Emisor, Emisor_Rfc, Emisor_Nombre, Emisor_RegimenFiscal, Emisor_FacAtrAdquirente,
                Receptor_Rfc, Receptor_Nombre, Receptor_DomicilioFiscalReceptor,
                Receptor_ResidenciaFiscal, Receptor_NumRegIdTrib,
                Receptor_RegimenFiscalReceptor, Receptor_UsoCFDI, 
                Version, Serie, Folio, Fecha, Sello, FormaPago, 
                NoCertificado, Certificado, 
                SubTotal, Descuento, Moneda, Total, 
                TipoDeComprobante, Exportacion, MetodoPago, 
                LugarExpedicion, Confirmacion)
                VALUES(
                :_Id, NOW(), :_Estado, :_Nodo, :_TicketId, :_Usuario,
                :_Emisor, :Emisor_Rfc, :Emisor_Nombre, :Emisor_RegimenFiscal, :Emisor_FacAtrAdquirente,
                :Receptor_Rfc, :Receptor_Nombre, :Receptor_DomicilioFiscalReceptor,
                :Receptor_ResidenciaFiscal, :Receptor_NumRegIdTrib,
                :Receptor_RegimenFiscalReceptor, :Receptor_UsoCFDI,
                :Version, :Serie, :Folio, :Fecha, :Sello, :FormaPago, 
                :NoCertificado, :Certificado, 
                :SubTotal, :Descuento, :Moneda, :Total, 
                :TipoDeComprobante, :Exportacion, :MetodoPago, 
                :LugarExpedicion, :Confirmacion)";
            $stmt1 = $this->db->prepare($qComprobante);        
            $stmt1->bindParam(':_Id', $_Id, PDO::PARAM_STR);
            $stmt1->bindValue(':_Estado', 0, PDO::PARAM_INT);
            $stmt1->bindParam(':_Nodo', $comprobante['_Nodo'], PDO::PARAM_STR);
            $stmt1->bindParam(':_TicketId', $comprobante['_TicketId'], PDO::PARAM_STR);
            $stmt1->bindParam(':_Usuario', $comprobante['_Usuario'], PDO::PARAM_STR);
            $stmt1->bindParam(':_Emisor', $comprobante['_Emisor'], PDO::PARAM_STR);
            $stmt1->bindParam(':Emisor_Rfc', $comprobante['Emisor_Rfc'], PDO::PARAM_STR);
            $stmt1->bindParam(':Emisor_Nombre', $comprobante['Emisor_Nombre'], PDO::PARAM_STR);
            $stmt1->bindParam(':Emisor_RegimenFiscal', $comprobante['Emisor_RegimenFiscal'], PDO::PARAM_STR);            
            $stmt1->bindParam(':Emisor_FacAtrAdquirente', $comprobante['Emisor_FacAtrAdquirente'], PDO::PARAM_STR);
            $stmt1->bindParam(':Receptor_Rfc', $comprobante['Receptor_Rfc'], PDO::PARAM_STR);
            $stmt1->bindParam(':Receptor_Nombre', $comprobante['Receptor_Nombre'], PDO::PARAM_STR);
            $stmt1->bindParam(':Receptor_DomicilioFiscalReceptor', $comprobante['Receptor_DomicilioFiscalReceptor'], PDO::PARAM_STR);
            $stmt1->bindParam(':Receptor_ResidenciaFiscal', $comprobante['Receptor_ResidenciaFiscal'], PDO::PARAM_STR);
            $stmt1->bindParam(':Receptor_NumRegIdTrib', $comprobante['Receptor_NumRegIdTrib'], PDO::PARAM_STR);
            $stmt1->bindParam(':Receptor_RegimenFiscalReceptor', $comprobante['Receptor_RegimenFiscalReceptor'], PDO::PARAM_STR);
            $stmt1->bindParam(':Receptor_UsoCFDI', $comprobante['Receptor_UsoCFDI'], PDO::PARAM_STR);
            $stmt1->bindParam(':Version', $comprobante['Version'], PDO::PARAM_STR);
            $stmt1->bindParam(':Serie', $comprobante['Serie'], PDO::PARAM_STR);
            $stmt1->bindParam(':Folio', $comprobante['Folio'], PDO::PARAM_STR);        
            $stmt1->bindParam(':Fecha', $comprobante['Fecha'], PDO::PARAM_STR);
            $stmt1->bindParam(':Sello', $comprobante['Sello'], PDO::PARAM_STR);
            $stmt1->bindParam(':FormaPago', $comprobante['FormaPago'], PDO::PARAM_STR);        
            $stmt1->bindParam(':NoCertificado', $comprobante['NoCertificado'], PDO::PARAM_STR);
            $stmt1->bindParam(':Certificado', $comprobante['Certificado'], PDO::PARAM_STR);
            $stmt1->bindParam(':SubTotal', $subtotal);
            $stmt1->bindParam(':Descuento', $descuento);
            $stmt1->bindParam(':Moneda', $comprobante['Moneda'], PDO::PARAM_STR);
            $stmt1->bindParam(':Total', $total );
            $stmt1->bindParam(':TipoDeComprobante', $comprobante['TipoDeComprobante'], PDO::PARAM_STR);
            $stmt1->bindParam(':Exportacion', $comprobante['Exportacion'], PDO::PARAM_STR);
            $stmt1->bindParam(':MetodoPago', $comprobante['MetodoPago'], PDO::PARAM_STR);
            $stmt1->bindParam(':LugarExpedicion', $comprobante['LugarExpedicion'], PDO::PARAM_STR);
            $stmt1->bindParam(':Confirmacion', $comprobante['Confirmacion'], PDO::PARAM_STR);
            
        

        $this->db->beginTransaction();

            $stmt1->execute();
            if($stmt1->rowCount()!=1)
                throw new Exception('No se logró insertar en V40_COMPROBANTES');

            if(isset($cfdiRelacionados)) {
                $qUUID = "SELECT UUID() AS miUUID";
                $stmtUUID = $this->db->prepare($qUUID);
                $stmtUUID->execute();
                $relacionUUID = $stmtUUID->fetchColumn(0);

                $qCfdiRelacionados = "INSERT INTO V40_CFDIRELACIONADOS
                    (_Id, _Comprobante, TipoRelacion)
                    VALUES(:_Id, :_Comprobante, :TipoRelacion)";
                $stmt5 = $this->db->prepare($qCfdiRelacionados);        
                $stmt5->bindParam(':_Id', $relacionUUID, PDO::PARAM_STR);
                $stmt5->bindParam(':_Comprobante', $_Id, PDO::PARAM_STR);
                $stmt5->bindParam(':TipoRelacion', $cfdiRelacionados['TipoRelacion'], PDO::PARAM_STR);
                $stmt5->execute();
                if($stmt5->rowCount()!=1)
                    throw new Exception('No se logró insertar en V40_CFDIRELACIONADOS');

                $qCfdiRelacionadosUUID = "INSERT INTO V40_CFDIRELACIONADOS_UUID
                    (_Id, _Relacion, UUID)
                    VALUES(UUID(), :_Relacion, :UUID)";
                for($i=0; $i<count($cfdiRelacionados['CfdiRelacionados']); $i++) {
                    $stmt6 = $this->db->prepare($qCfdiRelacionadosUUID);
                    $stmt6->bindParam(':_Relacion', $relacionUUID, PDO::PARAM_STR);
                    $stmt6->bindParam(':UUID', $cfdiRelacionados['CfdiRelacionados'][$i]['UUID'], PDO::PARAM_STR);
                    $stmt6->execute();
                    if($stmt6->rowCount()!=1)
                        throw new Exception('No se logró insertar en V40_CFDIRELACIONADOS_UUID');
                }

            }

            if(isset($informacionGlobal)) {
                $qUUID = "SELECT UUID() AS miUUID";
                $stmtUUID = $this->db->prepare($qUUID);
                $stmtUUID->execute();
                $globalUUID = $stmtUUID->fetchColumn(0);

                $qInformacionGlobal = "INSERT INTO V40_INFORMACIONGLOBAL
                    (_Id, _Comprobante, Periodicidad, Meses, Ano)
                    VALUES(:_Id, :_Comprobante, :Periodicidad, :Meses, :Ano)";
                $stmt8 = $this->db->prepare($qInformacionGlobal);        
                $stmt8->bindParam(':_Id', $globalUUID, PDO::PARAM_STR);
                $stmt8->bindParam(':_Comprobante', $_Id, PDO::PARAM_STR);
                $stmt8->bindParam(':Periodicidad', $informacionGlobal['Periodicidad'], PDO::PARAM_STR);
                $stmt8->bindParam(':Meses', $informacionGlobal['Meses'], PDO::PARAM_STR);
                $stmt8->bindParam(':Ano', $informacionGlobal['Año'], PDO::PARAM_STR);
                $stmt8->execute();
                if($stmt8->rowCount()!=1)
                    throw new Exception('No se logró insertar en V40_INFORMACIONGLOBAL');

                
            }

            $qConceptos = "INSERT INTO V40_CONCEPTOS(
                _Id, _Comprobante, 
                ClaveProdServ, NoIdentificacion, Cantidad, 
                ClaveUnidad, Unidad, Descripcion, 
                ValorUnitario, Importe, Descuento, ObjetoImp)
                VALUES(:_Concepto, :_Comprobante, 
                :ClaveProdServ, :NoIdentificacion, :Cantidad, 
                :ClaveUnidad, :Unidad, :Descripcion, 
                :ValorUnitario, :Importe, :Descuento, :ObjetoImp)";
            $stmt2 = $this->db->prepare($qConceptos);

            $qConceptosImpuestos = "INSERT INTO V40_CONCEPTOS_IMPUESTOS(
                _Id, _Concepto, _Tipo, 
                Base, Impuesto, TipoFactor, 
                TasaOCuota, Importe)
                VALUES(UUID(), :_Concepto, :_Tipo, 
                :Base, :Impuesto, :TipoFactor, 
                :TasaOCuota, :Importe)";
            $stmt2_2 = $this->db->prepare($qConceptosImpuestos);

            for($i=0;$i<count($conceptos);$i++){

                $qUUID = "SELECT UUID() AS miUUID";
                $stmtUUID = $this->db->prepare($qUUID);
                $stmtUUID->execute();
                $miUUID = $stmtUUID->fetchColumn(0);

                $stmt2->bindParam(':_Concepto', $miUUID, PDO::PARAM_STR);
                $stmt2->bindParam(':_Comprobante', $_Id, PDO::PARAM_STR);
                $stmt2->bindParam(':ClaveProdServ', $conceptos[$i]['ClaveProdServ'], PDO::PARAM_STR);
                $stmt2->bindParam(':NoIdentificacion', $conceptos[$i]['NoIdentificacion'], PDO::PARAM_STR);
                $stmt2->bindParam(':Cantidad', $conceptos[$i]['Cantidad']);
                $stmt2->bindParam(':ClaveUnidad', $conceptos[$i]['ClaveUnidad'], PDO::PARAM_STR);
                $stmt2->bindParam(':Unidad', $conceptos[$i]['Unidad'], PDO::PARAM_STR);
                $stmt2->bindParam(':Descripcion', $conceptos[$i]['Descripcion'], PDO::PARAM_STR);
                $stmt2->bindParam(':ValorUnitario', $conceptos[$i]['ValorUnitario'] );
                $stmt2->bindParam(':Importe', $conceptos[$i]['Importe'] );
                $stmt2->bindParam(':Descuento', $conceptos[$i]['Descuento'] );
                $stmt2->bindParam(':ObjetoImp', $conceptos[$i]['ObjetoImp'] );
                $stmt2->execute();
                if($stmt2->rowCount()!=1)
                    throw new Exception('No se logró insertar en V40_CONCEPTOS');
                

                for($j=0;$j<count($conceptos[$i]['e_Impuestos']);$j++){

                    $stmt2_2->bindParam(':_Concepto', $miUUID, PDO::PARAM_STR);
                    $stmt2_2->bindParam(':_Tipo', $conceptos[$i]['e_Impuestos'][$j]['_Tipo'], PDO::PARAM_STR);
                    $stmt2_2->bindParam(':Base', $conceptos[$i]['e_Impuestos'][$j]['Base'] );
                    $stmt2_2->bindParam(':Impuesto', $conceptos[$i]['e_Impuestos'][$j]['Impuesto'], PDO::PARAM_STR);
                    $stmt2_2->bindParam(':TipoFactor', $conceptos[$i]['e_Impuestos'][$j]['TipoFactor'], PDO::PARAM_STR);
                    $stmt2_2->bindParam(':TasaOCuota', $conceptos[$i]['e_Impuestos'][$j]['TasaOCuota']);
                    //$importe = round($conceptos[$i]['e_Impuestos'][$j]['Importe'],$decimales);
                    //$stmt2_2->bindParam(':Importe', $importe );
                    $stmt2_2->bindParam(':Importe', $conceptos[$i]['e_Impuestos'][$j]['Importe'] );
                    $stmt2_2->execute();
                    if($stmt2_2->rowCount()!=1)
                        throw new Exception('No se logró insertar en V40_CONCEPTOS_IMPUESTOS');

                }

            }

            $qTraslados = "INSERT INTO V40_IMPUESTOS_TRASLADOS(
                _Id, _Comprobante, 
                Base, Impuesto, TipoFactor, TasaOCuota, Importe)
                VALUES(UUID(), :_Comprobante, 
                :Base, :Impuesto, :TipoFactor, :TasaOCuota, :Importe)";
            $stmt3 = $this->db->prepare($qTraslados);

            for($i=0;$i<count($impuestosTraslados);$i++){
                $stmt3->bindParam(':_Comprobante', $_Id, PDO::PARAM_STR);
                //$stmt3->bindParam(':Base', $base );
                $stmt3->bindParam(':Base', $impuestosTraslados[$i]['Base'] );
                $stmt3->bindParam(':Impuesto', $impuestosTraslados[$i]['Impuesto'], PDO::PARAM_STR);
                $stmt3->bindParam(':TipoFactor', $impuestosTraslados[$i]['TipoFactor'], PDO::PARAM_STR);
                $stmt3->bindParam(':TasaOCuota', $impuestosTraslados[$i]['TasaOCuota']);
                //$importe = round($impuestosTraslados[$i]['Importe'],$decimales);
                //$stmt3->bindParam(':Importe', $importe );
                $stmt3->bindParam(':Importe', $impuestosTraslados[$i]['Importe'] );
                $stmt3->execute();
                if($stmt3->rowCount()!=1)
                    throw new Exception('No se logró insertar en V40_IMPUESTOS_TRASLADOS');
                
            }

             $qRetenciones = "INSERT INTO V40_IMPUESTOS_RETENCIONES(
                _Id, _Comprobante, 
                Base, Impuesto, Importe)
                VALUES(UUID(), :_Comprobante, :Impuesto, :Importe)";
            $stmt4 = $this->db->prepare($qRetenciones);

            for($i=0;$i<count($impuestosRetenciones);$i++){
                $stmt4->bindParam(':_Comprobante', $_Id, PDO::PARAM_STR);
                $stmt4->bindParam(':Impuesto', $impuestosRetenciones[$i]['Impuesto'], PDO::PARAM_STR);
                $stmt4->bindParam(':Importe', round($impuestosRetenciones[$i]['Importe'],$decimales) );
                $stmt4->execute();
                if($stmt4->rowCount()!=1)
                    throw new Exception('No se logró insertar en V33_IMPUESTOS_RETENCIONES');
            }
            
        
        $this->db->commit();
            
            $response = array(
                'error'=>NULL,
                'request'=>$postData,
                'data'=>array(
                        '_Id'=>$_Id
                    )
            );
       
        return json_encode($response);
            
    }
    
        
    
    private function obtenerDatosEmisor($_emisor) {
        $qEmisor = "SELECT * FROM EMISORES WHERE ID=:_Emisor";
        $stmt6 = $this->db->prepare($qEmisor);
        $stmt6->bindParam(':_Emisor', $_emisor, PDO::PARAM_STR);
        $stmt6->execute();
        $emisor = $stmt6->fetch();
        
        $certificado = str_replace('-----BEGIN CERTIFICATE-----', '', $emisor['CER']);
        $certificado = str_replace('-----END CERTIFICATE-----', '', $certificado);
        $certificado = str_replace(PHP_EOL, '', $certificado);
        $certificado = trim($certificado);
        
        return array('ID'=>$emisor['ID'],'CER'=>$certificado,'NO_CERTIFICADO'=>$emisor['NO_CERTIFICADO'],'PKEY'=>$emisor['PKEY'],'LOGO'=>$emisor['LOGO']);
    }
    
    private function obtenerConceptos($_comprobante, $decimales) {
        $qConceptos = "SELECT * FROM V40_CONCEPTOS WHERE _Comprobante=:_Comprobante";
        $stmt2 = $this->db->prepare($qConceptos);
        $stmt2->bindParam(':_Comprobante', $_comprobante, PDO::PARAM_STR);
        $stmt2->execute();
        $conceptos = $stmt2->fetchAll(PDO::FETCH_ASSOC);
        
        $qConceptosImp = "SELECT * FROM V40_CONCEPTOS_IMPUESTOS WHERE _Concepto=:_Concepto";
        $stmt3 = $this->db->prepare($qConceptosImp);
        $Conceptos_Impuestos = [];
        for($i=0; $i<count($conceptos); $i++) {            
            $stmt3->bindParam(':_Concepto', $conceptos[$i]['_Id'], PDO::PARAM_STR);        
            $stmt3->execute();
            $conceptoImpuestos = $stmt3->fetchAll(PDO::FETCH_ASSOC);            
            $traslados = [];
            $retenciones = [];
            for($j=0; $j<count($conceptoImpuestos); $j++) {
                if($conceptoImpuestos[$j]['_Tipo']=='T') {
                    $traslados[] = array(
                        //'Base'=>round($conceptoImpuestos[$j]['Base'],$decimales),
                        //'Base'=>$conceptoImpuestos[$j]['Base'],
                        'Base'=>number_format($conceptos[$i]['Importe']-round($conceptos[$i]['Descuento'],$decimales),6, '.', ''),
                        'Impuesto'=>$conceptoImpuestos[$j]['Impuesto'],
                        'TipoFactor'=>$conceptoImpuestos[$j]['TipoFactor'],
                        'TasaOCuota'=>$conceptoImpuestos[$j]['TasaOCuota'],
                        //'Importe'=>round($conceptoImpuestos[$j]['Importe'],$decimales)
                        //'Importe'=>$conceptoImpuestos[$j]['Importe']
                        'Importe'=> number_format(($conceptos[$i]['Importe']-round($conceptos[$i]['Descuento'],$decimales))*$conceptoImpuestos[$j]['TasaOCuota'],6, '.', '')
                    );
                }
                if($conceptoImpuestos[$j]['_Tipo']=='R') {
                    $retenciones[] = array(
                        //'Base'=>round($conceptoImpuestos[$j]['Base'],$decimales),
                        //'Base'=>$conceptoImpuestos[$j]['Base'],
                        'Base'=>number_format($conceptos[$i]['Importe']-round($conceptos[$i]['Descuento'],$decimales),6, '.', ''),
                        'Impuesto'=>$conceptoImpuestos[$j]['Impuesto'],
                        'TipoFactor'=>$conceptoImpuestos[$j]['TipoFactor'],
                        'TasaOCuota'=>$conceptoImpuestos[$j]['TasaOCuota'],
                        //'Importe'=>round($conceptoImpuestos[$j]['Importe'],$decimales)
                        //'Importe'=>$conceptoImpuestos[$j]['Importe']
                        'Importe'=> number_format(($conceptos[$i]['Importe']-round($conceptos[$i]['Descuento'],$decimales))*$conceptoImpuestos[$j]['TasaOCuota'],6, '.', '')
                    );
                }
            }
            $Conceptos_Impuestos[] = array(
                'ClaveProdServ'=>$conceptos[$i]['ClaveProdServ'],
                'NoIdentificacion'=>$conceptos[$i]['NoIdentificacion'],
                'Cantidad'=>$conceptos[$i]['Cantidad'],
                'ClaveUnidad'=>$conceptos[$i]['ClaveUnidad'],
                'Unidad'=>$conceptos[$i]['Unidad'],
                'Descripcion'=>$conceptos[$i]['Descripcion'],
                'ValorUnitario'=>$conceptos[$i]['ValorUnitario'],
                'Importe'=>$conceptos[$i]['Importe'],
                //'Descuento'=>round($conceptos[$i]['Descuento'],$decimales),
                'Descuento'=>$conceptos[$i]['Descuento']==0 ? null : round($conceptos[$i]['Descuento'],$decimales),
                'ObjetoImp'=>$conceptos[$i]['ObjetoImp'],
                'Impuestos'=> array(
                    'Traslados'=> $traslados,
                    'Retenciones'=> $retenciones
                )
            );
        
        }
        
        return $Conceptos_Impuestos;
        
    }
    
    private function obtenerTotalImpuestos($conceptosImpuestos, $decimales) {


        $impuestoRetencion = '';
        $totalBaseRetenciones = 0;
        $totalImporteRetenciones = 0;

        $impuestoTraslado = '';
        $tipoFactor = '';
        $tasaOCuota = '';
        $totalBaseTraslados = 0;
        $totalImporteTraslados = 0;        
        for($i=0; $i<count($conceptosImpuestos); $i++) {
            if( !empty($conceptosImpuestos[$i]['Impuestos']['Retenciones']) ) {
                $impuestoRetencion = $conceptosImpuestos[$i]['Impuestos']['Retenciones'][0]['Impuesto'];
                $totalBaseRetenciones += $conceptosImpuestos[$i]['Impuestos']['Retenciones'][0]['Base'];
                $totalImporteRetenciones += $conceptosImpuestos[$i]['Impuestos']['Retenciones'][0]['Importe'];
            }
            if( !empty($conceptosImpuestos[$i]['Impuestos']['Traslados']) ) {
                $impuestoTraslado = $conceptosImpuestos[$i]['Impuestos']['Traslados'][0]['Impuesto'];
                $tipoFactor = $conceptosImpuestos[$i]['Impuestos']['Traslados'][0]['TipoFactor'];
                $tasaOCuota = $conceptosImpuestos[$i]['Impuestos']['Traslados'][0]['TasaOCuota'];
                $totalBaseTraslados += $conceptosImpuestos[$i]['Impuestos']['Traslados'][0]['Base'];
                $totalImporteTraslados += $conceptosImpuestos[$i]['Impuestos']['Traslados'][0]['Importe'];
            }
        }

        $retenciones = [];
        if( $totalBaseRetencione>0 ) {
            $retenciones[] = array(
                'Impuesto'=>$impuestoRetencion,
                'Importe'=>round($totalImporteRetenciones, $decimales)
                );
        }
        $traslados = [];
        if( $totalBaseTraslados>0 ) {
            $traslados[] = array(
                'Base'=>round($totalBaseTraslados, $decimales),
                'Impuesto'=>$impuestoTraslado,
                'TipoFactor'=>$tipoFactor,
                'TasaOCuota'=>$tasaOCuota,
                'Importe'=>round($totalImporteTraslados, $decimales)
            );
        }

        return array(
            'TotalImpuestosRetenidos'=>$totalImporteRetenciones==0 ? null : round($totalImporteRetenciones, $decimales),
            'TotalImpuestosTrasladados'=>$totalImporteTraslados==0 ? null :  round($totalImporteTraslados, $decimales),
            'Retenciones' => $retenciones,
            'Traslados' => $traslados            
        );       


    }

    private function recalcularSubDesc($conceptos_impuestos) {        
        $subtotal = 0;
        $descuento = 0;
        for($i=0; $i<count($conceptos_impuestos); $i++) {
            $subtotal += $conceptos_impuestos[$i]['Importe'];
            $descuento += $conceptos_impuestos[$i]['Descuento'];
        }
        return array(
            'Subtotal' => $subtotal,
            'Descuento' => $descuento
        );
    }

    private function obtenerImpuestosRetenciones($_comprobante, $decimales) {
        $qImpuestosRetenciones = "SELECT * FROM V40_IMPUESTOS_RETENCIONES WHERE _Comprobante=:_Comprobante";
        $stmt4 = $this->db->prepare($qImpuestosRetenciones);
        $stmt4->bindParam(':_Comprobante', $_comprobante, PDO::PARAM_STR);
        $stmt4->execute();
        $impuestosRetenciones = $stmt4->fetchAll(PDO::FETCH_ASSOC);
        
        $retenciones = [];
        $totalImpuestosRetenidos = 0;
        for($i=0; $i<count($impuestosRetenciones); $i++) {
            $retenciones[] = array(
                'Impuesto'=>$impuestosRetenciones[$i]['Impuesto'],
                'Importe'=>round($impuestosRetenciones[$i]['Importe'],$decimales)
                );
            $totalImpuestosRetenidos += $impuestosRetenciones[$i]['Importe'];
        }
        
        return array('ImpuestosRetenciones'=>$retenciones, 'TotalImpuestosRetenidos'=>round($totalImpuestosRetenidos),$decimales);
    }
    
    private function obtenerImpuestosTrasladados($_comprobante, $decimales) {
        $qImpuestosTraslados = "SELECT * FROM V40_IMPUESTOS_TRASLADOS WHERE _Comprobante=:_Comprobante";
        $stmt5 = $this->db->prepare($qImpuestosTraslados);
        $stmt5->bindParam(':_Comprobante', $_comprobante, PDO::PARAM_STR);
        $stmt5->execute();
        $impuestosTraslados = $stmt5->fetchAll(PDO::FETCH_ASSOC);
        
        $traslados = [];
        $totalImpuestosTrasladados = 0;
        for($i=0; $i<count($impuestosTraslados); $i++) {
            $traslados[] = array(
                'Base'=>round($impuestosTraslados[$i]['Base'],$decimales),
                'Impuesto'=>$impuestosTraslados[$i]['Impuesto'],
                'TipoFactor'=>$impuestosTraslados[$i]['TipoFactor'],
                'TasaOCuota'=>$impuestosTraslados[$i]['TasaOCuota'],
                'Importe'=>round($impuestosTraslados[$i]['Importe'],$decimales)
                );
            $totalImpuestosTrasladados += $impuestosTraslados[$i]['Importe'];
        }
        
        return array('ImpuestosTraslados'=>$traslados, 'TotalImpuestosTrasladados'=>round($totalImpuestosTrasladados,$decimales));
        
    }
    
    private function obtenerCfdiRelacionados($_comprobante) {
        $qCfdiRela = "SELECT * FROM V40_CFDIRELACIONADOS WHERE _Comprobante=:_Comprobante";
        $stmt = $this->db->prepare($qCfdiRela);
        $stmt->bindParam(':_Comprobante', $_comprobante, PDO::PARAM_STR);
        $stmt->execute();
        $cfdiRelacionados = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $qCfdiRelaUUID = "SELECT * "
                . " FROM V40_CFDIRELACIONADOS_UUID U"
                . " INNER JOIN V40_CFDIRELACIONADOS R ON U._Relacion=R._Id"
                . " WHERE R._Comprobante=:_Comprobante";
        $stmt2 = $this->db->prepare($qCfdiRelaUUID);
        $stmt2->bindParam(':_Comprobante', $_comprobante, PDO::PARAM_STR);
        $stmt2->execute();
        $cfdiRelacionadosUUID = $stmt2->fetchAll(PDO::FETCH_ASSOC);
        
        $uuids=[];
        for($i=0;$i<count($cfdiRelacionadosUUID);$i++) {
            $uuids[] = array('UUID'=>$cfdiRelacionadosUUID[$i]['UUID']);
        }
        
        $relacionados = array(
            'TipoRelacion'=>$cfdiRelacionados['TipoRelacion'],
            'CfdiRelacionados' => $uuids
            );
        return $relacionados;
    }


    private function obtenerInformacionGlobal($_comprobante) {

        $qCfdiInfoGlobal = "SELECT Periodicidad, Meses, Ano AS Año"
            . " FROM V40_INFORMACIONGLOBAL"
            . " WHERE _Comprobante=:_Comprobante";
        $stmt = $this->db->prepare($qCfdiInfoGlobal);

        $stmt->bindParam(':_Comprobante', $_comprobante, PDO::PARAM_STR);
        $stmt->execute();

        $informacionGlobal = $stmt->fetch(PDO::FETCH_ASSOC);        
        
        return $informacionGlobal;
    }


    private function obtenerEmisorXMLArr($_comprobante, $decimales) {
        
        $comprobante = $this->obtenerComprobante($_comprobante); //cambiado

        $cfdiRelacionados = $this->obtenerCfdiRelacionados($comprobante['_Id']); //cambiado

        $informacionGlobal = $this->obtenerInformacionGlobal($comprobante['_Id']); //cambiado
        
        $emisor = $this->obtenerDatosEmisor($comprobante['_Emisor']); //cambiado
        $certificado = $emisor['CER'];
        $noCertificado = $emisor['NO_CERTIFICADO'];
        
        $Conceptos_Impuestos = $this->obtenerConceptos($comprobante['_Id'], $decimales); //cambiado

        $Impuestos = $this->obtenerTotalImpuestos($Conceptos_Impuestos, $decimales);
        $nuevosTotales = $this->recalcularSubDesc($Conceptos_Impuestos);


        $nuevoSubtotal = round($nuevosTotales['Subtotal'], $decimales);
        $nuevoDescuento =  $nuevosTotales['Descuento']==0 ? null : round($nuevosTotales['Descuento'], $decimales);
        $nuevoTotal = round($nuevoSubtotal-$nuevoDescuento-$Impuestos['TotalImpuestosRetenidos']+$Impuestos['TotalImpuestosTrasladados'],2);

       
        /* $ImpuestosRetenciones = $this->obtenerImpuestosRetenciones($comprobante['_Id'], $decimales); //cambiado
        $retenciones = $ImpuestosRetenciones['ImpuestosRetenciones'];
        $totalImpuestosRetenidos = $ImpuestosRetenciones['TotalImpuestosRetenidos'];
        
        $ImpuestosTraslados = $this->obtenerImpuestosTrasladados($comprobante['_Id'], $decimales); //cambiado
        $traslados = $ImpuestosTraslados['ImpuestosTraslados'];
        $totalImpuestosTrasladados = $ImpuestosTraslados['TotalImpuestosTrasladados'];
        
        $Impuestos = array(
            'TotalImpuestosRetenidos'=>$totalImpuestosRetenidos,
            'TotalImpuestosTrasladados'=>$totalImpuestosTrasladados,
            'Retenciones' => $retenciones,
            'Traslados' => $traslados            
        );
        */
        
        $xmlArr = array (
            'Comprobante'=>array(
                'Version'=>$comprobante['Version'],
                'Serie'=>$comprobante['Serie'],
                'Folio'=>$comprobante['Folio'],
                'Fecha'=> str_replace(' ', 'T', $comprobante['Fecha']),
                'Sello'=>$comprobante['Sello'],
                'FormaPago'=>$comprobante['FormaPago'],
                'NoCertificado'=>$noCertificado,
                'Certificado'=>$certificado,
                'CondicionesDePago'=>$comprobante['CondicionesDePago'],
                //'SubTotal'=>round($comprobante['SubTotal'],$decimales),
                'SubTotal'=>$nuevoSubtotal,
                //'Descuento'=>round($comprobante['Descuento'],$decimales),
                'Descuento'=>$nuevoDescuento,
                'Moneda'=>$comprobante['Moneda'],
                'TipoCambio'=>$comprobante['TipoCambio'],
                //'Total'=>round($comprobante['Total'],$decimales),
                'Total'=>$nuevoTotal,
                'TipoDeComprobante'=>$comprobante['TipoDeComprobante'],
                'Exportacion'=>$comprobante['Exportacion'],
                'MetodoPago'=>$comprobante['MetodoPago'],
                'LugarExpedicion'=>$comprobante['LugarExpedicion'],
                'Confirmacion'=>$comprobante['Confirmacion'],
                'CfdiRelacionados'=>$cfdiRelacionados,
                'InformacionGlobal'=>$informacionGlobal,
                'Emisor'=>array(
                    'Rfc'=>$comprobante['Emisor_Rfc'],
                    'Nombre'=>$comprobante['Emisor_Nombre'],
                    'RegimenFiscal'=>$comprobante['Emisor_RegimenFiscal'],
                    'FacAtrAdquirente'=>$comprobante['FacAtrAdquirente']
                ),
                'Receptor'=>array(
                    'Rfc'=>$comprobante['Receptor_Rfc'],
                    'Nombre'=>$comprobante['Receptor_Nombre'],
                    'DomicilioFiscalReceptor'=>$comprobante['Receptor_DomicilioFiscalReceptor'],
                    'ResidenciaFiscal'=>$comprobante['Receptor_ResidenciaFiscal'],
                    'NumRegIdTrib'=>$comprobante['Receptor_NumRegIdTrib'],
                    'RegimenFiscalReceptor'=>$comprobante['Receptor_RegimenFiscalReceptor'],
                    'UsoCFDI'=>$comprobante['Receptor_UsoCFDI']
                ),
                'Conceptos' => $Conceptos_Impuestos,
                'Impuestos' => $Impuestos
            )
        );
        
        return array('XML'=>$xmlArr,'Emisor'=>$emisor);
        
    }
    
    private function foliarComprobante($serie, $folio, $xmlArr) {
            if(gettype($serie)=='NULL') {
                $xmlArr['Comprobante']['Serie'] = NULL;
            } else {
                $xmlArr['Comprobante']['Serie'] = (string)$serie;
            }
            if(gettype($folio)=='NULL') {
                $xmlArr['Comprobante']['Folio'] = NULL;
            } else {
                $xmlArr['Comprobante']['Folio'] = (string)$folio;
            }
            return $xmlArr;
        }
    
    private function obtenerSerie($_emisor){
        $q = "SELECT SERIE FROM FOLIOS WHERE EMISOR=:emisor";
        $stmt = $this->db->prepare($q);
        $stmt->bindParam(':emisor', $_emisor, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetchColumn();
    }
    
    private function obtenerFolio($_emisor, $serie){
        $q = "SELECT FOLIO + 1 FROM FOLIOS 
            WHERE EMISOR=:emisor AND SERIE=:serie FOR UPDATE";
        $stmt = $this->db->prepare($q);
        $stmt->bindParam(':emisor', $_emisor, PDO::PARAM_STR);
        $stmt->bindParam(':serie', $serie, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetchColumn();
    }
    
    private function establecerFolio($_emisor, $serie, $usuario) {
        $SQL = "UPDATE FOLIOS SET FOLIO = FOLIO + 1, 
                USUARIO_ID = :usuario,
                FECHA = current_timestamp()
                WHERE EMISOR=:emisor
                AND SERIE=:serie";
        $stmt = $this->db->prepare($SQL);
        $stmt->bindParam(':emisor', $_emisor,PDO::PARAM_STR);
        $stmt->bindParam(':serie', $serie, PDO::PARAM_STR);
        $stmt->bindParam(':usuario', $usuario, PDO::PARAM_STR);
        $stmt->execute();
        if($stmt->rowCount()!=1)
            throw new Exception('No se logró actualizar FOLIOS<json>'.json_encode(array('Mensaje'=>'Dummy')));
        
        return true;
    }
    
    private function obtenerPAC($_emisor) {
        $q = "SELECT P.*, E.WS_LOGIN, E.WS_PASSWD FROM PACS P "
            . "INNER JOIN EMISORES E ON P.RFC=E.PAC "
            . "WHERE E.ID=:emisor";
        $stmt = $this->db->prepare($q);
        $stmt->bindParam(':emisor', $_emisor, PDO::PARAM_STR);
        $stmt->execute();
        $pac = $stmt->fetch();
        
        $q2 = "SELECT VARIABLE, METODO FROM PAC_METODOS WHERE PAC=:PAC";
        $stmt2 = $this->db->prepare($q2);
        $stmt2->bindParam(':PAC', $pac['RFC'], PDO::PARAM_STR);
        $stmt2->execute();
        $metodos = $stmt2->fetchAll(PDO::FETCH_ASSOC);
        
        $pacMetodos = [];
        foreach($metodos as $k=>$v) {
            $pacMetodos[$v['VARIABLE']]=$v['METODO'];
        }
        
        return array('PAC'=>$pac, 'METODOS'=>$pacMetodos);
    }
    
    private function guardarTimbrado($timbre, $XMLResultado, $datosAd){
        
        $qTFD = "INSERT INTO V40_TIMBREFISCALDIGITAL_V11 (_Id, UUID, FechaTimbrado, RfcProvCertif, SelloCFD, NoCertificadoSAT, SelloSAT)"
            . " VALUES (UUID(), :UUID, :FechaTimbrado, :RfcProvCertif, :SelloCFD, :NoCertificadoSAT, :SelloSAT)";
        $fechaTimbrado = str_replace('T', ' ', $timbre['FechaTimbrado']);
        $stmt1 = $this->db->prepare($qTFD);
        $stmt1->bindParam(':UUID', $timbre['UUID'], PDO::PARAM_STR);
        $stmt1->bindParam(':FechaTimbrado', $fechaTimbrado);
        $stmt1->bindParam(':RfcProvCertif', $datosAd['RfcProvCertif'], PDO::PARAM_STR);
        $stmt1->bindParam(':SelloCFD', $timbre['SelloCFD'], PDO::PARAM_STR);
        $stmt1->bindParam(':NoCertificadoSAT', $timbre['NumeroCertificadoSAT'], PDO::PARAM_STR);
        $stmt1->bindParam(':SelloSAT', $timbre['SelloSAT'], PDO::PARAM_STR);
        $stmt1->execute();
        if($stmt1->rowCount()!=1)
            throw new Exception('No se logró insertar en V40_TIMBREFISCALDIGITAL_V11<json>'.json_encode(array('Mensaje'=>'Dummy')) );
        
        
        $qCFDIS = "INSERT INTO V40_CFDIS(_Id, _TimbreFiscalDigital_UUID, XML)"
            . " VALUES(UUID(), :_TimbreFiscalDigital_UUID, :XML)";
        $stmt2 = $this->db->prepare($qCFDIS);
        $stmt2->bindParam(':_TimbreFiscalDigital_UUID', $timbre['UUID'], PDO::PARAM_STR);
        $stmt2->bindParam(':XML', $XMLResultado, PDO::PARAM_STR);
        $stmt2->execute();
        if($stmt2->rowCount()!=1)
            throw new Exception('No se logró insertar en V40_CFDIS<json>'.json_encode(array('Mensaje'=>'Dummy')));
        

        $qComp = "UPDATE V40_COMPROBANTES"
                . " SET _Estado=:_Estado, _TimbreFiscalDigital_UUID=:_TimbreFiscalDigital_UUID,"
                . " Serie=:Serie, Folio=:Folio, Sello=:Sello"
                . " WHERE _Id=:_Id";
        $stmt3 = $this->db->prepare($qComp);
        $stmt3->bindValue(':_Estado', 1, PDO::PARAM_INT);
        $stmt3->bindParam(':_TimbreFiscalDigital_UUID', $timbre['UUID'], PDO::PARAM_STR);
        $stmt3->bindParam(':Serie', $datosAd['Serie'], PDO::PARAM_STR);
        $stmt3->bindParam(':Folio', $datosAd['Folio'], PDO::PARAM_STR);
        $stmt3->bindParam(':Sello', $datosAd['Sello'], PDO::PARAM_STR);
        $stmt3->bindParam(':_Id', $datosAd['_Id'], PDO::PARAM_STR);
        $stmt3->execute();
        if($stmt3->rowCount()!=1)
            throw new Exception('No se logró actualizar V40_COMPROBANTES<json>'.json_encode(array('Mensaje'=>'Dummy')));
                
        return true;
    }
    
    private function actualizarPDF($_TimbreFiscalDigital_UUID, $PDF_B64) {
        $qCFDIPDF = "UPDATE V40_CFDIS"
            . " SET PDF_B64=:PDF_B64"
            . " WHERE _TimbreFiscalDigital_UUID=:_TimbreFiscalDigital_UUID";
        $stmt = $this->db->prepare($qCFDIPDF);
        $stmt->bindParam(':_TimbreFiscalDigital_UUID', $_TimbreFiscalDigital_UUID, PDO::PARAM_STR);
        $stmt->bindParam(':PDF_B64', $PDF_B64, PDO::PARAM_STR);
        $stmt->execute();
        if($stmt->rowCount()!=1)
            throw new Exception('No se logró actualizar V40_CFDIS');
        
        return true;
    }
    
    
    
    private function obtenerLogo64($filename) {
        $file='assets/images/'.$filename;
        $logo = file_get_contents($file);
        return base64_encode($logo);
    }
    
    function timbrar($request) {
        
        $cfdi = new CFDI();
        
    $this->db->beginTransaction();
        
        $decimales = $this->obtenerDecimales($request['_id']); //cambiado
        $comprobante = $this->obtenerComprobante($request['_id']); //cambiado
        
        $emisorXMLArr = $this->obtenerEmisorXMLArr($request['_id'], $decimales); //cambiado

        $xmlArr = $emisorXMLArr['XML'];
        $emisor = $emisorXMLArr['Emisor'];
        
        $serie = $this->obtenerSerie($emisor['ID']); //revisado
        $folio = $this->obtenerFolio($emisor['ID'],$serie); //revisado
        
        $xmlArrFoliado = $this->foliarComprobante($serie, $folio, $xmlArr);//revisado

        $xml = $cfdi->generarXML40($xmlArrFoliado);//cambiado

        //$file = 'temp/xml-'.$serie.'-'.$folio;
        //file_put_contents($file, $xml);

        $xmlValidation = $cfdi->validaEsquemaCFDI40($xml);
        if(!$xmlValidation['valid']) {
            $response = array(
                'error'=>array(
                    'message'=>'XML no válido',
                    'debug'=>$xmlValidation['errors']
                ),
                'request'=>$request,
                'data'=>NULL
            );
            return json_encode($response);
        }

        $sello = $cfdi->generarSello40($xml, $emisor['PKEY']);
        
        $xmlSellado = str_replace("{Sello}", $sello, $xml);
        
        $PAC = $this->obtenerPAC($emisor['ID']);
        
        $timbradoResult = $cfdi->timbrarXML40($PAC['PAC']['WS_CFDI'], $PAC['PAC']['WS_LOGIN'], $PAC['PAC']['WS_PASSWD'], $PAC['METODOS']['ws_metodoTimbrarCFDI'], $PAC['METODOS']['ws_metodoTimbrarCFDI'].'Result', $xmlSellado, $request['_id']);
        
        $datosAdd = array('RfcProvCertif'=>$PAC['PAC']['RFCPROVCERTIF'], 'Serie'=>$serie, 'Folio'=>$folio, 'Sello'=>$sello, '_Id'=>$request['_id']);
        $this->guardarTimbrado($timbradoResult['Timbre'], $timbradoResult['XMLResultado'], $datosAdd);
        
        $this->establecerFolio($emisor['ID'], $serie, $comprobante['_Usuario']);
        
    $this->db->commit();
    
    $this->db->beginTransaction();
    
        
        $logoBase64 = $this->obtenerLogo64($emisor['LOGO']);
        $pdfBase64 = $cfdi->obtenerCFDi33PDF($PAC['PAC']['WS_CFDI'], $PAC['PAC']['WS_LOGIN'], $PAC['PAC']['WS_PASSWD'], $PAC['METODOS']['ws_metodoObtenerPDF'], $PAC['METODOS']['ws_metodoObtenerPDF'].'Result', $timbradoResult['Timbre']['UUID'], $logoBase64);
        
        
        $this->actualizarPDF($timbradoResult['Timbre']['UUID'], $pdfBase64);
        
    
    $this->db->commit();
        
        $response = array(
            'error'=>NULL,
            'request'=>$request,
            'data'=>array(
                    'UUID'=>$timbradoResult['Timbre']['UUID']
                )
        );
       
        return json_encode($response);
        
    }

    private function obtenerDecimales($_Id) {
        $qDecimales = "SELECT m.Decimales"
                . " FROM V40_COMPROBANTES c"
                . " INNER JOIN V33_C_MONEDA m ON c.Moneda=m.c_Moneda"
                . " WHERE c._Id=:_Id";
        $stmt0 = $this->db->prepare($qDecimales);
        $stmt0->bindParam(':_Id', $_Id, PDO::PARAM_STR);
        $stmt0->execute();
        return $stmt0->fetchColumn(0);
    }

    private function obtenerComprobante($_id) {
        $qComprobante = "SELECT * FROM V40_COMPROBANTES WHERE _Id=:_Id";
        $stmt1 = $this->db->prepare($qComprobante);
        $stmt1->bindParam(':_Id', $_id, PDO::PARAM_STR);
        $stmt1->execute();
        return $stmt1->fetch(PDO::FETCH_ASSOC);
    }









    
    function actualizar($request) {
        $postData = $this->POST();
        
        $id = $request['_id'];
        $receptorRfc = $postData['Receptor_Rfc'];
        $receptorNombre = $postData['Receptor_Nombre'];
        $fecha = $postData['Fecha'];
        $domicilioFiscalReceptor = $postData['Receptor_DomicilioFiscalReceptor'];
        $regimenFiscalReceptor = $postData['Receptor_RegimenFiscalReceptor'];        
        
        $q1 = "SELECT _Estado, Emisor_Rfc, Serie, Folio FROM V40_COMPROBANTES"
            . " WHERE _Id=:_Id";
        $stmt1 = $this->db->prepare($q1);
        $stmt1->bindParam(':_Id', $id, PDO::PARAM_STR);
        $stmt1->execute();
        $comprobante = $stmt1->fetch();
        if($comprobante['_Estado']==1) {
            $response = array(
                'error'=>array(
                    'message'=>'No se puede actualizar comprobante dado que fue timbrado previamente: '.$comprobante['Emisor_Rfc'].', serie: '.$comprobante['Serie'].', folio: '.$comprobante['Folio'],
                    'debug'=>''
                ),
                'request'=>$request,
                'data'=>false
            );
            return json_encode($response);
        }
        
        
        $q2 = "UPDATE V40_COMPROBANTES SET"
            . " Fecha=:Fecha, Receptor_Rfc=:Receptor_Rfc, Receptor_Nombre=:Receptor_Nombre,"
            . " Receptor_DomicilioFiscalReceptor=:Receptor_DomicilioFiscalReceptor, Receptor_RegimenFiscalReceptor=:Receptor_RegimenFiscalReceptor"
            . " WHERE _Id=:_Id AND _Estado=0";
        $stmt2 = $this->db->prepare($q2);
        
        $stmt2->bindParam(':Fecha', $fecha);
        $stmt2->bindParam(':Receptor_Rfc', $receptorRfc, PDO::PARAM_STR);
        $stmt2->bindParam(':Receptor_Nombre', $receptorNombre, PDO::PARAM_STR);
        $stmt2->bindParam(':Receptor_DomicilioFiscalReceptor', $domicilioFiscalReceptor, PDO::PARAM_STR);
        $stmt2->bindParam(':Receptor_RegimenFiscalReceptor', $regimenFiscalReceptor, PDO::PARAM_STR);        

        $stmt2->bindParam(':_Id', $id, PDO::PARAM_STR);
        $stmt2->execute();
        if($stmt2->rowCount()==1) {
            $response = array(
                'error'=>NULL,
                'request'=>$request,
                'data'=>true
            );
        } else {
            $response = array(
                'error'=>NULL,
                'request'=>$request,
                'data'=>false
            );
        }
        
        return json_encode($response);
    }

    


}
