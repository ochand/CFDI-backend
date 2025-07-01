<?php
require_once 'core/nusoap/nusoap.php';   
set_time_limit(60); 
/*
*
* Generación de sellos digitales para
* Comprobantes Fiscales Digitales por Internet
* Descripción: Esta clase se utiliza para generar el xml del comprobante fiscal digital y timbrarlo ante el sat, 
*              también tiene un método para cancelar comprobantes, uno para obtener un XML y otro para pasar un XML a un arreglo
*/
class CFDI{
	
    protected $db;
    private $xml;
    private $pem_file;
    private $cer_file; 
    private $noCertificado;
    private $cadenaOriginal = "||";
	
    public function __construct() {
        $this->db =  sPDO::singleton();
        $this->xml = new XMLWriter();
    }
	
    private  function remplazarCaracteres($cadena){
        $cadena = str_replace('&','&amp;',$cadena);
        $cadena = str_replace('"','&quot;',$cadena);
        $cadena = str_replace("\"","&quot;",$cadena);
        $cadena = str_replace('<','&lt;',$cadena);
        $cadena = str_replace('>','&gt;',$cadena);
        $cadena = str_replace("'",'&apos;',$cadena);
        $cadena = str_replace("‘",'&apos;',$cadena);
        return $cadena;
    }	
			
    /*
    *
    * Ingresa atributos a un nodo
    * @param array
    * @return void
    *
    *
    */
	
    private function setAttributo($attr=array()) {	
        foreach ($attr as $key => $val) {
            $val = preg_replace('/\s\s+/', ' ', $val);   // Regla 5a y 5c
            $val = trim($val);   
            if(strlen($val)>0) {   // Regla 6
                $val = utf8_encode(str_replace("|","/",$val)); // Regla 1
                $this->xml->writeAttribute($key,$val);
                $co = array("serie","certificado","folio","sello","noCertificado");
                if(!in_array($key, $co))
                        $this->cadenaOriginal .= $val . "|";
            }
        }	
    }
	
    /**
    *
    *
    *
    */
    private function generarSello(){
        $pkeyid = openssl_get_privatekey($this->pem_file);
        // Lo Firmamos por default utiliza el algoritmo SHA1  OPENSSL_ALGO_SHA1 
        file_put_contents('temp/sello.txt', $this->cadenaOriginal);
        openssl_sign($this->cadenaOriginal, $signature, $pkeyid );
        // borramos de la memoria el OpenSSL Key
        openssl_free_key($pkeyid);
        // lo pasamos a base64 y lo retornamos
        return base64_encode($signature);	
    }
	
	
	
    /*
    *	Obtiene el certificado desde el arivo .pem
    *
    *
    */	
	
    private function Certificado(){
        $this->cer_file = str_replace('-----BEGIN CERTIFICATE-----', '', $this->cer_file);
        $this->cer_file = str_replace('-----END CERTIFICATE-----', '', $this->cer_file);
        $this->cer_file = str_replace(PHP_EOL, '', $this->cer_file);
        $this->cer_file = trim($this->cer_file);
        return $this->cer_file;
    }
	
	
    public function generarXML($serie, $folio, $enc, $det, $impuestos=array()) {
        
        $SQL = "select e.* 
                from EMISORES e 
                WHERE ID=:EMISOR";
        $stmt = $this->db->prepare($SQL);
        $stmt->bindParam(':EMISOR', $enc['EMISOR'], PDO::PARAM_STR);
        $stmt->execute();
        $sat = $stmt->fetch();
        if(!$sat){
            throw new Exception('El emisor no esta dado de alta');
        }
        
        $this->cer_file = $sat['CER'];
        $this->pem_file = $sat['PKEY'];
        $this->noCertificado = $sat['NO_CERTIFICADO'];
        
            
        $this->xml->openMemory();
        $this->xml->setIndent(true);
        $this->xml->startDocument("1.0","UTF-8");

        $this->xml->startElement("cfdi:Comprobante");

        $this->xml->writeAttribute("xmlns:cfdi","http://www.sat.gob.mx/cfd/3");
        $this->xml->writeAttribute("xmlns:xsi", "http://www.w3.org/2001/XMLSchema-instance");
        $this->xml->writeAttribute("xmlns:implocal","http://www.sat.gob.mx/implocal");
        $this->xml->writeAttribute("xsi:schemaLocation", "http://www.sat.gob.mx/cfd/3 http://www.sat.gob.mx/sitio_internet/cfd/3/cfdv32.xsd http://www.sat.gob.mx/implocal http://www.sat.gob.mx/sitio_internet/cfd/implocal/implocal.xsd");

        /**
         * NODO COMPROBANTE
         */
        $this->setAttributo(array(
                "version"           => "3.2",
                "serie"             => $serie,
                "folio"             => $folio,
                "fecha"             => substr(date('c', strtotime($enc['FECHA'])),0,19),
                "tipoDeComprobante" => "ingreso", 			
                "sello"             => "{sello}",
                "formaDePago"       => utf8_decode("Pago en una sola exhibición"),  
                "condicionesDePago" => $enc["CONDICIONESPAGO"], 
                "noCertificado"     => $this->noCertificado,
                "certificado"       => "{certificado}",			
                "subTotal"          => $enc['SUBTOTAL'],
                "descuento"         => $enc['DESCUENTO'],			
                "total"             => $enc['TOTAL'],
                "metodoDePago"      => $enc['METODOPAGO'], 
                "LugarExpedicion"   => utf8_decode("Culiacán, Sinaloa, México"),
                "NumCtaPago"        => ( !empty($enc['BANCOCLIENTE']) && !empty($enc['CUENTABANCO'])) ? $enc['BANCOCLIENTE'] .'-'.substr($enc["CUENTABANCO"],-4) : ''
                ));

        /*
        *
        *   NODO EMISOR
        *
        */

        $this->xml->startElement("cfdi:Emisor");
            $this->setAttributo(array(
                "rfc"    =>$enc['EMISOR_RFC'],
                "nombre" =>$enc['EMISOR_NOMBRE']));

            //NODO DOMICILIO FISCAL
            $this->xml->startElement("cfdi:DomicilioFiscal");
                $this->setAttributo(array(
                        "calle"         =>	utf8_decode($enc['EMISOR_CALLE']),
                        "noExterior"	=> 	utf8_decode($enc['EMISOR_NOEXTERIOR']),
                        "noInterior"	=> 	utf8_decode($enc['EMISOR_NOINTERIOR']),
                        "colonia"       => 	utf8_decode($enc['EMISOR_COLONIA']),
                        "localidad"	=> 	utf8_decode($enc['EMISOR_LOCALIDAD']),
                        "municipio"	=> 	utf8_decode($enc['EMISOR_MUNICIPIO']),
                        "estado"	=> 	utf8_decode($enc['EMISOR_ESTADO']),
                        "pais"		=> 	utf8_decode($enc['EMISOR_PAIS']),
                        "codigoPostal"	=> 	utf8_decode($enc['EMISOR_CP'])
                    ));
            $this->xml->endElement(); // END NODO DOMICILIO FISCAL
            
            //NODO EXPEDIDO EN
            if (isset($enc['EMISOR_EXPEDIDOEN'])) {
                $expedidoEn = json_decode($enc['EMISOR_EXPEDIDOEN'], true);
                $this->xml->startElement("cfdi:ExpedidoEn");
                    $this->setAttributo(array(
                            "calle"         =>	utf8_decode($expedidoEn['CALLE']),
                            "noExterior"    => 	utf8_decode($expedidoEn['NOEXTERIOR']),
                            "noInterior"    => 	utf8_decode($expedidoEn['NOINTERIOR']),
                            "colonia"       => 	utf8_decode($expedidoEn['COLONIA']),
                            "localidad"     => 	utf8_decode($expedidoEn['LOCALIDAD']),
                            "municipio"     => 	utf8_decode($expedidoEn['MUNICIPIO']),
                            "estado"        => 	utf8_decode($expedidoEn['ESTADO']),
                            "pais"          => 	utf8_decode($expedidoEn['PAIS']),
                            "codigoPostal"  => 	utf8_decode($expedidoEn['CODIGOPOSTAL'])
                        ));
                $this->xml->endElement(); // END NODO EXPEDIDO EN
            }

            // NODO REGIMEN DE CONTRIBUYENTE
            $this->xml->startElement("cfdi:RegimenFiscal");
            $this->setAttributo(array("Regimen"=> utf8_decode($enc['EMISOR_REGIMEN']) ));
            $this->xml->endElement(); // END NODO REGIMEN DE CONTRIBUYENTE

        $this->xml->endElement(); // END NODO EMISOR



        /*
        *
        *  NODO RECEPTOR
        *
        */

        $this->xml->startElement("cfdi:Receptor");
                $this->setAttributo(array(
                    "rfc" 	=>	$enc['RECEPTOR_RFC'],
                    "nombre" 	=>	utf8_decode($enc['RECEPTOR_NOMBRE'])
                    ));

                // NODO DOMICILIO RECEPTOR
                $this->xml->startElement("cfdi:Domicilio");
                $this->setAttributo(array(
                    "calle"	=>	utf8_decode($enc['RECEPTOR_CALLE']),
                    "noExterior"=> 	utf8_decode($enc['RECEPTOR_NOEXTERIOR']),
                    "noInterior"=> 	utf8_decode($enc['RECEPTOR_NOINTERIOR']),
                    "colonia"	=> 	utf8_decode($enc['RECEPTOR_COLONIA']),
                    "localidad"	=>      utf8_decode($enc['RECEPTOR_LOCALIDAD']),
                    "municipio"	=> 	utf8_decode($enc['RECEPTOR_MUNICIPIO']),
                    "estado"	=> 	utf8_decode($enc['RECEPTOR_ESTADO']),
                    "pais"	=>      utf8_decode($enc['RECEPTOR_PAIS']),
                    "codigoPostal"=> 	utf8_decode($enc['RECEPTOR_CP'])
                                    ) ); 
                $this->xml->endElement(); // END NODO DOMICILIO

        $this->xml->endElement();// END NODO RECEPTOR

        /*
        *
        * informacion del nodo concepto 
        * repetir por cada concepto (detalle), 		 
        *
        */

        $this->xml->startElement("cfdi:Conceptos");

            foreach ($det as $r){
                $this->xml->startElement("cfdi:Concepto");						
                    $this->setAttributo(array(
                            "cantidad"          => number_format($r['CANTIDAD'],2),
                            "unidad"            => $r['UNIDAD'],
                            "noIdentificacion"  => $r['CODIGOPRODUCTO'],
                            "descripcion"       => utf8_decode($r['CONCEPTO']),
                            "valorUnitario"	=> $r['PRECIOUNITARIO'],
                            "importe"		=> $r['IMPORTE']));						
                $this->xml->endElement();					
            }

        $this->xml->endElement(); // END NODO CONCEPTOS

        /*
        *
        *	IMPUESTOS de translado [IVA]
        *
        */
        if(count($impuestos)==0){
            $this->xml->startElement('cfdi:Impuestos');
            $this->xml->writeAttribute("totalImpuestosTrasladados","0");
            $this->xml->endElement();
            $this->cadenaOriginal .=  "0|";			

        }else{

            $this->xml->startElement('cfdi:Impuestos');
            $this->xml->writeAttribute("totalImpuestosTrasladados","{totalImpuestos}");

                $this->xml->startElement("cfdi:Traslados");
                $totalImp =0;
                foreach($impuestos as $imp){
                    $totalImp += $imp['IMPORTE'];
                    $this->xml->startElement('cfdi:Traslado');
                        $this->setAttributo(
                            array( 
                            "impuesto"	=> $imp['IMPUESTO'],
                            "tasa"	=> $imp['TASAIMPUESTO'],
                            "importe"	=> $imp['IMPORTE'])
                        );
                    $this->xml->endElement();

                }
                
                $totalImp = number_format($totalImp, 2, '.', '');

                $this->cadenaOriginal .= $totalImp . "|";
                $this->xml->endElement();  // traslados 

            $this->xml->endElement();  // impuestos 

        }

        $this->xml->endElement(); // Comprobante
        $this->xml->endDocument();

        $xml =  $this->xml->outputMemory(true);

        $this->cadenaOriginal .= "|";       

        $xml = str_replace("{totalImpuestos}",$totalImp, $xml);

        $xml = str_replace("{sello}",$this->generarSello(), $xml);

        $xml = str_replace("{certificado}", $this->Certificado(), $xml);
        
        return $xml;	
    }	
	
	
	
public function timbrarXML($ws_cfdi, $ws_login, $ws_passwd, 
                        $ws_metodoTimbrado, $ws_metodoTimbradoResult,
                        $xml, $serie, $folio) {  
       
       file_put_contents('temp/lastXML.xml', $xml);
       
        //parametros del web service
        $params = array(
            "usuario"=> $ws_login,
            "password"=> $ws_passwd,
            "cadenaXML"=>$xml,
            "referencia"=>"$serie-$folio"
        );

        $client = new nusoap_client($ws_cfdi, $params);
        $client->soap_defencoding = 'UTF-8';									
        $client->namespaces = array("SOAP-ENV"=>"http://schemas.xmlsoap.org/soap/envelope/","cfdi"=>"https://www.foliosdigitalespac.com/WS-Folios");

        //llamamos al método									
        $result = $client->call($ws_metodoTimbrado, $params);

        if ($client->fault) {
            throw new Exception('Error al timbrar el comprobante: PAC_ERROR: '.$result["faultcode"].' - PAC_ERROR_DESC: '.$result["faultstring"].", ".$result["detail"]);
        }else if($client->getError()){
            throw new Exception('Error al timbrar el comprobrante: ' . $client->getError());
        }
        
        //obtener la respuesta
        list($codError, $msgError, $degError, $xmlTimbrado, $xmlAcuse) = $result[$ws_metodoTimbradoResult]['string'];
        $msgError = utf8_encode($msgError);
        if ($codError != ''){
            throw new Exception('PAC_ERROR: ' . $codError . ' - ' . $msgError .':' . $degError);
        }			
        
        $xmlTimbrado = utf8_encode($xmlTimbrado);
        return array($xmlTimbrado, $xmlAcuse);
        
    }
	
   
	
    function cancelarCFDi( $UUID, $ws_cfdi, $ws_login, $ws_passwd, 
                        $ws_metodoCancelar, $ws_metodoCancelarResult) {        
        
        $SQL = "SELECT e.*
                FROM SOLICITUD_ENC s
                INNER JOIN EMISORES e on s.EMISOR=e.ID
                WHERE s.CFDI_UUID=:uuid";        
        $stmt = $this->db->prepare($SQL);
        $stmt->bindParam(':uuid', $UUID, PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch();
        if(!$row){
            throw new Exception('El emisor no esta dado de alta');
        }
            
        //parametros del metodo
        $parametros = array(
            "usuario"=> $ws_login,
            "password"=>$ws_passwd,
            "RFCEmisor"=> $row['RFC'],
            "listaCFDI"=> array("string"=>$UUID),
            "certificadoPKCS12_Base64" => $row['PFX'],
            "passwordPKCS12"=>$row['PASSWDPFX']
        );

        $client = new nusoap_client($ws_cfdi, $parametros);
        $client->soap_defencoding = 'UTF-8';
        $client->namespaces = array("SOAP-ENV"=>"http://schemas.xmlsoap.org/soap/envelope/","cfdi"=>"https://www.foliosdigitalespac.com/WS-Folios");

        //llamamos al método del web service							
        $result = $client->call($ws_metodoCancelar, $parametros);

        if ($client->fault) {
            throw new Exception('Error al cancelar el comprobante: PAC_ERROR: '.$result["faultcode"].' - PAC_ERROR_DESC: '.$result["faultstring"].", ".$result["detail"]);
        }else if($client->getError()){
            throw new Exception('Error al cancelar el comprobrante: ' . $client->getError());
        }
                                        
        list($error, $resp, $acuse) = $result[$ws_metodoCancelarResult]['string'];
        $error = utf8_encode($error);
        if ($error != "") {
            throw new Exception('Error al cancelar el comprobrante: ' . $error);
        }
        
        list($uuidCancelado, $codigo, $mensaje) = explode('|', $resp);
        
        //si es 201 se cancelo correctamente
        if ($codigo =="201"){ 
            return array(1, $uuiCancelado." - ".$mensaje, $acuse);
        }else{								
            return array(8, $codigo." - ".$uuiCancelado. " - " .$mensaje,$acuse);								
        }

    }//fin Cacelar CFDi
    
    
    function obtenerCFDi($emisor_rfc, $UUID, $ws_cfdi, $ws_login, $ws_passwd,
                        $ws_metodoObtenerXML, $ws_metodoObtenerXMLResult) {        
            
        //parametros del metodo
        $parametros = array(
            "Usuario"=> $ws_login,
            "Password"=>$ws_passwd,            
            "UUID"=> $UUID,
            "RFCEmisor"=> $emisor_rfc
        );

        $client = new nusoap_client($ws_cfdi, $parametros);
        $client->soap_defencoding = 'UTF-8';
        $client->namespaces = array("SOAP-ENV"=>"http://schemas.xmlsoap.org/soap/envelope/","cfdi"=>"https://www.foliosdigitalespac.com/WS-Folios");

        //llamamos al método del web service							
        $result = $client->call($ws_metodoObtenerXML, $parametros);

        if ($client->fault) {
            throw new Exception('Error al obtener el comprobante: PAC_ERROR: '.$result["faultcode"].' - PAC_ERROR_DESC: '.$result["faultstring"].", ".$result["detail"]);
        }else if($client->getError()){
            throw new Exception('Error al obtener el comprobrante: ' . $client->getError());
        }

        list($existe, $msjerr, $dato3, $xml) = $result[$ws_metodoObtenerXMLResult]['string'];
        $msjerr = utf8_encode($msjerr);
        if (!$existe || $msjerr!="") {            
            return array($existe, $msjerr, null);            
        }
        
        $xmlTimbrado = utf8_encode($xml);
        return array($existe, '', $xmlTimbrado);

    }//fin Obtener CFDi
	
	
   /*+++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
   + 											METODO xmltoarray
   +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
   +	Descripción: Función que recibe un XML y lo recorre metiendo a un arreglo los datos del XML
   +	
   +	Entrada: xmltoarray(xml) 
   +		@xml => string del xml a pasar a un arreglo
   +
   +	Retorno: Un arreglo con los datos del xml
   +                                                    
   ************************************************************************************************************************************/
	function xmltoarray($xml){
		
		$xr = new XMLReader();
		$xr->XML($xml);
		
		while ($xr->read()){
			if (XMLReader::ELEMENT == $xr->nodeType){
			
				switch($xr->localName){
					case "Comprobante" :
						$datos['Comprobante']['serie'] = $xr->getAttribute('serie');
						$datos['Comprobante']['folio'] = $xr->getAttribute('folio');
						$datos['Comprobante']['fecha'] = $xr->getAttribute('fecha');
						$datos['Comprobante']['formaDePago'] = $xr->getAttribute('formaDePago');
						$datos['Comprobante']['condicionesDePago'] = $xr->getAttribute('condicionesDePago');						
						$datos['Comprobante']['metodoDePago'] = $xr->getAttribute('metodoDePago');
						$datos['Comprobante']['LugarExpedicion'] = $xr->getAttribute('LugarExpedicion');
						$datos['Comprobante']['NumCtaPago'] = $xr->getAttribute('NumCtaPago');
						$datos['Comprobante']['noCertificado'] = $xr->getAttribute('noCertificado');
						$datos['Comprobante']['subTotal'] = $xr->getAttribute('subTotal');
						$datos['Comprobante']['TipoCambio'] = $xr->getAttribute('TipoCambio');
						$datos['Comprobante']['Moneda'] = $xr->getAttribute('Moneda');
						$datos['Comprobante']['total'] = $xr->getAttribute('total');
						$datos['Comprobante']['descuento'] = $xr->getAttribute('descuento');
						$datos['Comprobante']['tipoDeComprobante'] = $xr->getAttribute('tipoDeComprobante');
						
						$datos['Comprobante']['sello'] = $xr->getAttribute('sello');
						
						break;
						
					case "Emisor" :
						$datos['emisor']['rfc'] = $xr->getAttribute('rfc');
						$datos['emisor']['nombre'] = $xr->getAttribute('nombre');
						break;
						
					case "DomicilioFiscal" :
						$datos['DomicilioFiscal']['calle'] = $xr->getAttribute('calle');
						$datos['DomicilioFiscal']['noExterior'] = $xr->getAttribute('noExterior');
						$datos['DomicilioFiscal']['noInterior'] = $xr->getAttribute('noInterior');
						$datos['DomicilioFiscal']['colonia'] = $xr->getAttribute('colonia');
						$datos['DomicilioFiscal']['localidad'] = $xr->getAttribute('localidad');
						$datos['DomicilioFiscal']['municipio'] = $xr->getAttribute('municipio');
						$datos['DomicilioFiscal']['estado'] = $xr->getAttribute('estado');
						$datos['DomicilioFiscal']['pais'] = $xr->getAttribute('pais');
						$datos['DomicilioFiscal']['codigoPostal'] = $xr->getAttribute('codigoPostal');
						break;
					case "RegimenFiscal" :
						$datos['RegimenFiscal']['regimen'] = $xr->getAttribute('Regimen');
						break;
					case "Receptor" :
						$datos['receptor']['rfc'] = $xr->getAttribute('rfc');
						$datos['receptor']['nombre'] = $xr->getAttribute('nombre');
						break;
						
					case "Domicilio" :
						$datos['Domicilio']['calle'] = $xr->getAttribute('calle');
						$datos['Domicilio']['noExterior'] = $xr->getAttribute('noExterior');
						$datos['Domicilio']['noInterior'] = $xr->getAttribute('noInterior');
						$datos['Domicilio']['colonia'] = $xr->getAttribute('colonia');
						$datos['Domicilio']['localidad'] = $xr->getAttribute('localidad');
						$datos['Domicilio']['municipio'] = $xr->getAttribute('municipio');
						$datos['Domicilio']['estado'] = $xr->getAttribute('estado');
						$datos['Domicilio']['pais'] = $xr->getAttribute('pais');
						$datos['Domicilio']['codigoPostal'] = $xr->getAttribute('codigoPostal');
						break;
					
					case "Conceptos" :
						while ($xr->read() && $xr->depth >1  )
						{
							if (XMLReader::ELEMENT == $xr->nodeType){
								$datos['detalle'][]= 
									array(	'cantidad' => $xr->getAttribute("cantidad"),
											'unidad' => $xr->getAttribute("unidad"),
											'noIdentificacion' => $xr->getAttribute("noIdentificacion"),
											'descripcion' => $xr->getAttribute("descripcion"),
											'valorUnitario' => $xr->getAttribute("valorUnitario"),
											'importe' =>(float)$xr->getAttribute("cantidad") * (float)$xr->getAttribute("valorUnitario"));
							}
						}
						break;
					
					case "Impuestos" :
						$datos['Impuestos']['totalImpuestosTrasladados'] = $xr->getAttribute('totalImpuestosTrasladados');
						break;
						
					case "Traslados":
						
						while ($xr->read() && $xr->depth >1 ){	
							if ($xr->nodeType == XMLReader::END_ELEMENT ){
								break;
							}else if (XMLReader::ELEMENT == $xr->nodeType){
								$datos['detalleImpuestos'][]= 
									array(	'impuesto' => $xr->getAttribute("impuesto"),
											'tasa' => $xr->getAttribute("tasa"),
											'importe' => $xr->getAttribute("importe"));
							}
						}
						break;
															
					case "ImpuestosLocales": 
						$datos['ImpuestosLocales']['totalImpuestosTrasladadosLocales'] = $xr->getAttribute('TotaldeTraslados');
						break;
						
					case "TrasladosLocales":												
						$datos['detalleImpuestosLocales'][]= 
						array(	'impuesto' => $xr->getAttribute("ImpLocTrasladado"),
								'tasa' => $xr->getAttribute("TasadeTraslado"),
								'importe' => $xr->getAttribute("Importe"));												
						break;
						
					case "TimbreFiscalDigital":
						$datos['TimbreFiscalDigital']['FechaTimbrado'] = $xr->getAttribute("FechaTimbrado");
						$datos['TimbreFiscalDigital']['UUID'] = $xr->getAttribute("UUID");
						$datos['TimbreFiscalDigital']['noCertificadoSAT'] = $xr->getAttribute("noCertificadoSAT");
						$datos['TimbreFiscalDigital']['selloCFD'] = $xr->getAttribute("selloCFD");
						$datos['TimbreFiscalDigital']['selloSAT'] = $xr->getAttribute("selloSAT");
						break;	
						
				}
			}
			
			
		}
		return $datos;
	}//fin xmltoarray
        
    public function generarXML33($xmlArr) {
        
        $this->removerNulos($xmlArr);
            
        $this->xml->openMemory();
        $this->xml->setIndent(true);
        $this->xml->startDocument("1.0","UTF-8");
        $this->xml->startElement("cfdi:Comprobante");

        $this->xml->writeAttribute("xmlns:cfdi","http://www.sat.gob.mx/cfd/3");
        $this->xml->writeAttribute("xmlns:xsi", "http://www.w3.org/2001/XMLSchema-instance");
        //$this->xml->writeAttribute("xmlns:implocal","http://www.sat.gob.mx/implocal");
        $this->xml->writeAttribute("xsi:schemaLocation", "http://www.sat.gob.mx/cfd/3 http://www.sat.gob.mx/sitio_internet/cfd/3/cfdv33.xsd");
        
        $this->setAttributo33($xmlArr['Comprobante']); // COMPROBANTE
        
        if(!empty($xmlArr['Comprobante']['CfdiRelacionados']['CfdiRelacionados'])) {
            $this->xml->startElement("cfdi:CfdiRelacionados"); // COMPROBANTE > CFDIRELACIONADOS
            $this->xml->writeAttribute("TipoRelacion", $xmlArr['Comprobante']['CfdiRelacionados']['TipoRelacion']);
            foreach($xmlArr['Comprobante']['CfdiRelacionados']['CfdiRelacionados'] as $k=>$v) {
                $this->xml->startElement("cfdi:CfdiRelacionado"); // COMPROBANTE > CFDIRELACIONADOS > CFDIRELACIONADO
                $this->xml->writeAttribute("UUID", $v['UUID']);
                $this->xml->endElement(); // END: COMPROBANTE > CFDIRELACIONADOS > CFDIRELACIONADO
            }
            $this->xml->endElement(); // END: COMPROBANTE > CFDIRELACIONADOS
        }
        
        $this->xml->startElement("cfdi:Emisor"); // COMPROBANTE > EMISOR
        $this->setAttributo33($xmlArr['Comprobante']['Emisor']);
        $this->xml->endElement(); // END: COMPROBANTE > EMISOR

        $this->xml->startElement("cfdi:Receptor"); // COMPROBANTE > RECEPTOR
        $this->setAttributo33($xmlArr['Comprobante']['Receptor']);
        $this->xml->endElement();// END: COMPROBANTE > RECEPTOR

        $this->xml->startElement("cfdi:Conceptos"); // COMPROBANTE > CONCEPTOS
            foreach ($xmlArr['Comprobante']['Conceptos'] as $concepto){
                $this->removerNulos($concepto);
                $this->xml->startElement("cfdi:Concepto"); // COMPROBANTE > CONCEPTOS > CONCEPTO
                $this->setAttributo33($concepto);
                    if( !empty($concepto['Impuestos']) && ( !empty($concepto['Impuestos']['Traslados']) || !empty($concepto['Impuestos']['Retenciones']) ) ) {
                        $this->xml->startElement("cfdi:Impuestos"); // COMPROBANTE > CONCEPTOS > CONCEPTO > IMPUESTOS
                            foreach ($concepto['Impuestos']['Traslados'] as $traslado) {
                                $this->xml->startElement("cfdi:Traslados"); // COMPROBANTE > CONCEPTOS > CONCEPTO > IMPUESTOS > TRASLADOS
                                    $this->removerNulos($traslado);                
                                    $this->xml->startElement("cfdi:Traslado");  // COMPROBANTE > CONCEPTOS > CONCEPTO > IMPUESTOS > TRASLADOS > TRASLADO
                                    $this->setAttributo33($traslado);                            
                                    $this->xml->endElement(); // END: COMPROBANTE > CONCEPTOS > CONCEPTO > IMPUESTOS > TRASLADOS > TRASLADO
                                $this->xml->endElement();  // END: COMPROBANTE > CONCEPTOS > CONCEPTO > IMPUESTOS > TRASLADOS                        
                            }
                            foreach ($concepto['Impuestos']['Retenciones'] as $retencion) {
                                $this->xml->startElement("cfdi:Retenciones"); // COMPROBANTE > CONCEPTOS > CONCEPTO > IMPUESTOS > RETENCIONES
                                    $this->removerNulos($retencion);
                                    $this->xml->startElement("cfdi:Retencion");  // COMPROBANTE > CONCEPTOS > CONCEPTO > IMPUESTOS > RETENCIONES > RETENCION
                                    $this->setAttributo33($retencion);                            
                                    $this->xml->endElement(); // END: COMPROBANTE > CONCEPTOS > CONCEPTO > IMPUESTOS > RETENCIONES > RETENCION
                                $this->xml->endElement();  // END: COMPROBANTE > CONCEPTOS > CONCEPTO > IMPUESTOS > RETENCIONES
                            }
                        $this->xml->endElement(); // END: COMPROBANTE > CONCEPTOS > CONCEPTO > IMPUESTOS
                    }
                $this->xml->endElement(); // END: COMPROBANTE > CONCEPTOS > CONCEPTO
            }
        $this->xml->endElement(); // END: COMPROBANTE > CONCEPTOS


        if( !empty($xmlArr['Comprobante']['Impuestos']) && (!empty($xmlArr['Comprobante']['Impuestos']['Retenciones']) || !empty($xmlArr['Comprobante']['Impuestos']['Traslados']) ) ){
            $this->xml->startElement('cfdi:Impuestos'); // COMPROBANTE > IMPUESTOS
            if($xmlArr['Comprobante']['Impuestos']['TotalImpuestosRetenidos']>0) {
                $this->xml->writeAttribute("TotalImpuestosRetenidos",$xmlArr['Comprobante']['Impuestos']['TotalImpuestosRetenidos']);
            }
            if($xmlArr['Comprobante']['Impuestos']['TotalImpuestosTrasladados']>0) {
                $this->xml->writeAttribute("TotalImpuestosTrasladados",$xmlArr['Comprobante']['Impuestos']['TotalImpuestosTrasladados']);
            }
            foreach($xmlArr['Comprobante']['Impuestos']['Retenciones'] as $retencion ) {
                $this->xml->startElement("cfdi:Retenciones"); // COMPROBANTE > IMPUESTOS > RETENCIONES
                    $this->xml->startElement("cfdi:Retencion");  // COMPROBANTE > IMPUESTOS > RETENCIONES > RETENCION
                    $this->removerNulos($retencion);
                    $this->setAttributo33($retencion);
                    $this->xml->endElement(); // END: COMPROBANTE > IMPUESTOS > RETENCIONES > RETENCION
                $this->xml->endElement(); // END: COMPROBANTE > IMPUESTOS > RETENCIONES
            }
            foreach($xmlArr['Comprobante']['Impuestos']['Traslados'] as $traslado ) {
                $this->xml->startElement("cfdi:Traslados"); // COMPROBANTE > IMPUESTOS > TRASLADOS
                    $this->xml->startElement("cfdi:Traslado");  // COMPROBANTE > IMPUESTOS > TRASLADOS > TRASLADO
                    $this->removerNulos($traslado);
                    $this->setAttributo33($traslado);                            
                    $this->xml->endElement(); // END: COMPROBANTE > IMPUESTOS > TRASLADOS > TRASLADO
                $this->xml->endElement(); // END: COMPROBANTE > IMPUESTOS > TRASLADOS             
            }
        }

        $this->xml->endElement(); // END: COMPROBANTE
        
        $this->xml->endDocument();

        $xml = $this->xml->outputMemory(true);
        
        return $xml;	
    }


    private function setAttributo33($attr=array()) {
        foreach ($attr as $key => $val) {
            if( gettype($val)!='array' ) {
                $val = trim($val);
                if( strlen($val)>0 ) {
                    $this->xml->writeAttribute($key,$val);
                }
            }
        }
    }
    
    private function removerNulos($xmlArr) {
        if(isset($xmlArr['Comprobante'])) {
            foreach ($xmlArr['Comprobante'] as $k=>$v) {
                if(gettype($v)=='NULL') {
                    unset($xmlArr['Comprobante'][$k]);
                }
            }
        }
        if(isset($xmlArr['Comprobante']['Emisor'])) {
            foreach ($xmlArr['Comprobante']['Emisor'] as $k=>$v) {
                if(gettype($v)=='NULL') {
                    unset($xmlArr['Comprobante']['Emisor'][$k]);
                }
            }
        }
        if(isset($xmlArr['Comprobante']['Receptor'])) {
            foreach ($xmlArr['Comprobante']['Receptor'] as $k=>$v) {
                if(gettype($v)=='NULL') {
                    unset($xmlArr['Comprobante']['Receptor'][$k]);
                }
            }
        }
        
        if(isset($xmlArr['Comprobante']['Conceptos'])) {
            for($i=0; $i<count($xmlArr['Comprobante']['Conceptos']); $i++) {
                foreach($xmlArr['Comprobante']['Conceptos'][$i] as $k=>$v) {
                    if(gettype($v)=='NULL') {
                        unset($xmlArr['Comprobante']['Conceptos'][$i]);
                    }
                }
                if(isset($xmlArr['Comprobante']['Conceptos'][$i]['Impuestos']['Traslados'])) {
                    for($j=0; $j<count($xmlArr['Comprobante']['Conceptos'][$i]['Impuestos']['Traslados']); $j++) {
                        foreach($xmlArr['Comprobante']['Conceptos'][$i]['Impuestos']['Traslados'][$j] as $k=>$v) {
                            if(gettype($v)=='NULL') {
                                unset($xmlArr['Comprobante']['Conceptos'][$i]['Impuestos']['Traslados'][$j]);
                            }
                        }
                    }
                }
                if(isset($xmlArr['Comprobante']['Conceptos'][$i]['Impuestos']['Retenciones'])) {
                    for($j=0; $j<count($xmlArr['Comprobante']['Conceptos'][$i]['Impuestos']['Retenciones']); $j++) {
                        foreach($xmlArr['Comprobante']['Conceptos'][$i]['Impuestos']['Retenciones'][$j] as $k=>$v) {
                            if(gettype($v)=='NULL') {
                                unset($xmlArr['Comprobante']['Conceptos'][$i]['Impuestos']['Retenciones'][$j]);
                            }
                        }
                    }
                }
            }
        }
        
    }
    
    function generarSello33($xml, $pKey) {
        
        $doc = new DOMDocument("1.0","UTF-8");
        $doc->loadXML($xml);
        
        $xsl = new DOMDocument("1.0","UTF-8");
        $file='core/sat/xslt/cadenaoriginal_3_3.xslt';
        $xsl->load($file);

        $proc = new XSLTProcessor;
        $proc->importStyleSheet($xsl); 

        $cadenaOriginal = $proc->transformToXML($doc);

        $pkeyid = openssl_get_privatekey($pKey);
        openssl_sign($cadenaOriginal, $crypttext, $pkeyid, OPENSSL_ALGO_SHA256);
        openssl_free_key($pkeyid);

        return base64_encode($crypttext);
        
    }
    
    function validaEsquemaCFDI33($xml) {
        $docu = new DOMDocument("1.0","UTF-8");
        $docu->loadXML($xml);
        
        libxml_use_internal_errors(true);
        libxml_clear_errors();
        
        $file='core/sat/xsd/cfdv33.xsd';  
        $ok = $docu->schemaValidate($file);
        $errors = libxml_get_errors();

        return array('valid'=>$ok, 'errors'=>$errors);
    }
    
    public function timbrarXML33($ws_cfdi, $ws_login, $ws_passwd, $ws_metodoTimbrado, $ws_metodoTimbradoResult, $xml, $comprobanteId) {  
       
        $params = array(
            "usuario"=> $ws_login,
            "password"=> $ws_passwd,
            "cadenaXML"=>$xml,
            "referencia"=> $comprobanteId
        );

        $client = new nusoap_client($ws_cfdi, $params);
        $client->soap_defencoding = 'UTF-8';									
        $client->namespaces = array("SOAP-ENV"=>"http://schemas.xmlsoap.org/soap/envelope/","cfdi"=>"https://www.foliosdigitalespac.com/WS-Folios");
        								
        $result = $client->call($ws_metodoTimbrado, $params);

        if ($client->fault) {
            throw new Exception('Error al timbrar el comprobante: PAC_ERROR: '.$result["faultcode"].' - PAC_ERROR_DESC: '.$result["faultstring"].", ".$result["detail"].'<json>'.json_encode($result));
            
        }else if($client->getError()){
            throw new Exception('Error al timbrar el comprobrante: <json>'.json_encode(array('Error'=> $client->getError())));
        }
        
        $CodigoConfirmacion = $result[$ws_metodoTimbradoResult]['CodigoConfirmacion'];
        $CodigoRespuesta = $result[$ws_metodoTimbradoResult]['CodigoRespuesta'];
        $CreditosRestantes = $result[$ws_metodoTimbradoResult]['CreditosRestantes'];
        $MensajeError = utf8_encode($result[$ws_metodoTimbradoResult]['MensajeError']);
        $MensajeErrorDetallado = utf8_encode($result[$ws_metodoTimbradoResult]['MensajeErrorDetallado']);
        $OperacionExitosa = $result[$ws_metodoTimbradoResult]['OperacionExitosa'];
        $PDFResultado = $result[$ws_metodoTimbradoResult]['PDFResultado'];
        $Timbre = $result[$ws_metodoTimbradoResult]['Timbre'];
        $XMLResultado = utf8_encode($result[$ws_metodoTimbradoResult]['XMLResultado']);
        
        if ($OperacionExitosa=='false') {
            $myRes = array(
                'CodigoConfirmacion'=>$CodigoConfirmacion,
                'CodigoRespuesta'=>$CodigoRespuesta,
                'CreditosRestantes'=>$CreditosRestantes,
                'MensajeError'=>$MensajeError,
                'MensajeErrorDetallado'=>$MensajeErrorDetallado,
                'OperacionExitosa'=>$OperacionExitosa,
                'PDFResultado'=>$PDFResultado,
                'Timbre'=>$Timbre,
                'XMLResultado'=>$XMLResultado
            );
            
            $eMsj = 'ERROR AL TIMBRAR: ' . $CodigoRespuesta . ' - ' . $MensajeError .' :' . $MensajeErrorDetallado.'<json>'. json_encode($myRes);
            throw new Exception($eMsj);
        }
        
        return array('Timbre'=>$Timbre, 'XMLResultado'=>$XMLResultado);
    }
    
    
    public function obtenerCFDi33PDF($ws_cfdi, $ws_login, $ws_passwd, $ws_metodo, $ws_metodoResult, $UUID, $logoBase64) {
        $params = array(
            "usuario"=> $ws_login,
            "password"=> $ws_passwd,
            "uUID"=>$UUID,
            "LogoBase64"=> $logoBase64
        );
        
        $client = new nusoap_client($ws_cfdi, $params);
        $client->soap_defencoding = 'UTF-8';									
        $client->namespaces = array("SOAP-ENV"=>"http://schemas.xmlsoap.org/soap/envelope/","cfdi"=>"https://www.foliosdigitalespac.com/WS-Folios");

        $result = $client->call($ws_metodo, $params);

        if ($client->fault) {
            throw new Exception('Error al obtener el PDF: PAC_ERROR: '.$result["faultcode"].' - PAC_ERROR_DESC: '.$result["faultstring"].", ".$result["detail"].'<json>'.json_encode($result));
        }else if($client->getError()){
            throw new Exception('Error al obtener el PDF: <json>'.json_encode(array('Error'=> $client->getError())));
        }
        
        
        $CodigoConfirmacion = $result[$ws_metodoResult]['CodigoConfirmacion'];
        $CodigoRespuesta = $result[$ws_metodoResult]['CodigoRespuesta'];
        $CreditosRestantes = $result[$ws_metodoResult]['CreditosRestantes'];
        $MensajeError = utf8_encode($result[$ws_metodoResult]['MensajeError']);
        $MensajeErrorDetallado = utf8_encode($result[$ws_metodoResult]['MensajeErrorDetallado']);
        $OperacionExitosa = $result[$ws_metodoResult]['OperacionExitosa'];
        $PDFResultado = $result[$ws_metodoResult]['PDFResultado'];
        
        if ($OperacionExitosa=='false') {
            $myRes = array(
                'CodigoConfirmacion'=>$CodigoConfirmacion,
                'CodigoRespuesta'=>$CodigoRespuesta,
                'CreditosRestantes'=>$CreditosRestantes,
                'MensajeError'=>$MensajeError,
                'MensajeErrorDetallado'=>$MensajeErrorDetallado,
                'OperacionExitosa'=>$OperacionExitosa,
                'PDFResultado'=>$PDFResultado
            );
            
            $eMsj = 'ERROR AL OBTENER EL PDF: ' . $CodigoRespuesta . ' - ' . $MensajeError .':' . $MensajeErrorDetallado.'<json>'. json_encode($myRes);
            throw new Exception($eMsj);
        }			
        
        return $PDFResultado;
    }
    
    
    
    public function cancelarCFDi33($ws_cfdi, $ws_metodo, $ws_metodoResult, $ws_login, $ws_passwd, $rfcEmisor, $UUID, $clavePrivada64, $passClavePrivada) {
        
        
        //parametros del metodo
        $parametros = array(
            "usuario"=> $ws_login,
            "password"=>$ws_passwd,
            "rFCEmisor"=> $rfcEmisor,
            "listaCFDI"=> array("string"=>$UUID),
            "clavePrivada_Base64" => $clavePrivada64,
            "passwordClavePrivada"=>$passClavePrivada
        );
        
        
        $options = array(
		'cache_wsdl'=>WSDL_CACHE_NONE,
		'trace'=>true,
		'encoding'=>'UTF-8',
		'exceptions'=>true
	);
        
        
        $client = new SoapClient($ws_cfdi, $options);
        
        $result = $client->$ws_metodo($parametros);
          
        $MensajeError = utf8_encode($result->$ws_metodoResult->MensajeError);
        $MensajeErrorDetallado = utf8_encode($result->$ws_metodoResult->MensajeErrorDetallado);
        $OperacionExitosa = $result->$ws_metodoResult->OperacionExitosa;
        $XMLAcuse = $result->$ws_metodoResult->XMLAcuse;
        
        $CodigoResultado = $result->$ws_metodoResult->DetallesCancelacion->DetalleCancelacion->CodigoResultado;
        $MensajeResultado = $result->$ws_metodoResult->DetallesCancelacion->DetalleCancelacion->MensajeResultado;
        $UUID = $result->$ws_metodoResult->DetallesCancelacion->DetalleCancelacion->UUID;
        
        

        if (!$OperacionExitosa) {
            $myRes = array(
                'MensajeError'=>$MensajeError,
                'MensajeErrorDetallado'=>$MensajeErrorDetallado,
                'OperacionExitosa'=>$OperacionExitosa,
                'XMLAcuse'=>$XMLAcuse,
                'CodigoResultado'=>$CodigoResultado,
                'MensajeResultado'=>$MensajeResultado,
                'UUID'=>$UUID
            );
            
            $eMsj = 'ERROR AL CANCELAR: ' . $MensajeError .' :' . $MensajeErrorDetallado.'<json>'. json_encode($myRes);
            throw new Exception($eMsj);
        }
        
        return array('UUID'=> $UUID,'CodigoResultado'=>$CodigoResultado, 'MensajeResultado'=>$MensajeResultado);
        
    }
    
                
    public function obtenerAcuseCancelacion33($ws_cfdi, $ws_metodo, $ws_metodoResult, $ws_login, $ws_passwd, $UUID) {
        
        $options = array(
		'cache_wsdl'=>WSDL_CACHE_NONE,
		'trace'=>true,
		'encoding'=>'UTF-8',
		'exceptions'=>true
	);
        $client = new SoapClient($ws_cfdi, $options);
        
        //parametros del metodo
        $parametros = array(
            "usuario"=> $ws_login,
            "password"=>$ws_passwd,
            "uUID"=> $UUID
        );
        
        $result = $client->$ws_metodo($parametros);
          
        $CodigoRespuesta = $result->$ws_metodoResult->CodigoRespuesta;
        $MensajeError = utf8_encode($result->$ws_metodoResult->MensajeError);
        $MensajeErrorDetallado = utf8_encode($result->$ws_metodoResult->MensajeErrorDetallado);;
        $OperacionExitosa = $result->$ws_metodoResult->OperacionExitosa;
        $PDFResultado = $result->$ws_metodoResult->PDFResultado;
        $CreditosRestantes = $result->$ws_metodoResult->CreditosRestantes;
        $XMLResultado = $result->$ws_metodoResult->XMLResultado;
        

        if (!$OperacionExitosa) {
            $myRes = array(
                'CodigoRespuesta'=>$CodigoRespuesta,
                'MensajeError'=>$MensajeError,
                'MensajeErrorDetallado'=>$MensajeErrorDetallado,
                'OperacionExitosa'=>$OperacionExitosa,
                'PDFResultado'=>$PDFResultado,
                'CreditosRestantes'=>$CreditosRestantes,
                'XMLResultado'=>$XMLResultado
            );
            
            $eMsj = 'ERROR AL OBTENER ACUSE DE CANCELACION: ' . $MensajeError .' :' . $MensajeErrorDetallado.'<json>'. json_encode($myRes);
            throw new Exception($eMsj);
        }
        
        return $XMLResultado;
        
    }
    
    
    
    public function validarRFC33($ws_cfdi, $ws_metodo, $ws_metodoResult, $ws_login, $ws_passwd, $rfc) {
        
        $options = array(
            'cache_wsdl'=>WSDL_CACHE_NONE,
            'trace'=>true,
            'encoding'=>'UTF-8',
            'exceptions'=>true
        );
        $client = new SoapClient($ws_cfdi, $options);
        
        //parametros del metodo
        $parametros = array(
            "usuario"=>$ws_login,
            "password"=>$ws_passwd,
            "rfc"=> $rfc
        );
        
        $result = $client->$ws_metodo($parametros);
          
        $Cancelado=$result->$ws_metodoResult->Cancelado;
        $MensajeError=$result->$ws_metodoResult->MensajeError;
        $RFC=$result->$ws_metodoResult->RFC;
        $RFCLocalizado=$result->$ws_metodoResult->RFCLocalizado;
        $Subcontratacion=$result->$ws_metodoResult->Subcontratacion;
        $UnidadSNCF=$result->$ws_metodoResult->UnidadSNCF;
        
        return array('RFCLocalizado'=>$RFCLocalizado, 'Cancelado'=>$Cancelado, 'MensajeError'=>$MensajeError);
        
    }

    public function cancelarCFDi33ConValidacion($ws_cfdi, $ws_metodo, $ws_metodoResult, $ws_login, $ws_passwd, $rfcEmisor, $rfcReceptor, $total, $UUID, $clavePrivada64, $passClavePrivada) {
        
        //parametros del metodo
        $parametros = array(
            "usuario"=>$ws_login,
            "password"=>$ws_passwd,
            "rFCEmisor"=>$rfcEmisor,
            "listaCFDI"=> array(
                "DetalleCFDICancelacion" => array(
                    "EsCancelable"=>"Si",
                    "RFCReceptor"=>$rfcReceptor,
                    "Total"=>$total,
                    "UUID"=>$UUID
                ) 
            ),
            "clavePrivada_Base64" => $clavePrivada64,
            "passwordClavePrivada"=>$passClavePrivada
        );
        
        
        $options = array(
            'cache_wsdl'=>WSDL_CACHE_NONE,
            'trace'=>true,
            'encoding'=>'UTF-8',
            'exceptions'=>true
        );
        
        
        $client = new SoapClient($ws_cfdi, $options);
        
        $result = $client->$ws_metodo($parametros);
    

        $MensajeError = utf8_encode($result->$ws_metodoResult->MensajeError);
        $MensajeErrorDetallado = utf8_encode($result->$ws_metodoResult->MensajeErrorDetallado);
        $OperacionExitosa = $result->$ws_metodoResult->OperacionExitosa;
        $XMLAcuse = $result->$ws_metodoResult->XMLAcuse;
        
        $CodigoResultado = $result->$ws_metodoResult->DetallesCancelacion->DetalleCancelacion->CodigoResultado;
        $EsCancelable = $result->$ws_metodoResult->DetallesCancelacion->DetalleCancelacion->EsCancelable;
        $MensajeResultado = $result->$ws_metodoResult->DetallesCancelacion->DetalleCancelacion->MensajeResultado;
        $UUID = $result->$ws_metodoResult->DetallesCancelacion->DetalleCancelacion->UUID;
        
        

        if (!$OperacionExitosa) {
            $myRes = array(
                'MensajeError'=>$MensajeError,
                'MensajeErrorDetallado'=>$MensajeErrorDetallado,
                'OperacionExitosa'=>$OperacionExitosa,
                'XMLAcuse'=>$XMLAcuse,
                'CodigoResultado'=>$CodigoResultado,
                'EsCancelable'=>$EsCancelable,
                'MensajeResultado'=>$MensajeResultado,
                'UUID'=>$UUID
            );
            
            $eMsj = 'ERROR AL CANCELAR: ' . $MensajeError .' :' . $MensajeErrorDetallado.'<json>'. json_encode($myRes);
            throw new Exception($eMsj);
        }
        
        return array('UUID'=> $UUID,'CodigoResultado'=>$CodigoResultado, 'MensajeResultado'=>$MensajeResultado, 'EsCancelable'=>$EsCancelable);
        
    }


    public function generarXML40($xmlArr) {
        
        $this->removerNulos($xmlArr); //revisado
            
        $this->xml->openMemory();
        $this->xml->setIndent(true);
        $this->xml->startDocument("1.0","UTF-8");
        $this->xml->startElement("cfdi:Comprobante");

        $this->xml->writeAttribute("xmlns:cfdi","http://www.sat.gob.mx/cfd/4");
        $this->xml->writeAttribute("xmlns:xsi", "http://www.w3.org/2001/XMLSchema-instance");        
        $this->xml->writeAttribute("xsi:schemaLocation", "http://www.sat.gob.mx/cfd/4 http://www.sat.gob.mx/sitio_internet/cfd/4/cfdv40.xsd");
        
        $this->setAttributo40($xmlArr['Comprobante']); // COMPROBANTE > cambiado


        if(!empty($xmlArr['Comprobante']['InformacionGlobal'])) {
            $this->xml->startElement("cfdi:InformacionGlobal"); // COMPROBANTE > INFORMACIONGLOBAL            
            $this->setAttributo40($xmlArr['Comprobante']['InformacionGlobal']);
            $this->xml->endElement(); // END: COMPROBANTE > INFORMACIONGLOBAL
        }
        
        if(!empty($xmlArr['Comprobante']['CfdiRelacionados']['CfdiRelacionados'])) {
            $this->xml->startElement("cfdi:CfdiRelacionados"); // COMPROBANTE > CFDIRELACIONADOS
            $this->xml->writeAttribute("TipoRelacion", $xmlArr['Comprobante']['CfdiRelacionados']['TipoRelacion']);
            foreach($xmlArr['Comprobante']['CfdiRelacionados']['CfdiRelacionados'] as $k=>$v) {
                $this->xml->startElement("cfdi:CfdiRelacionado"); // COMPROBANTE > CFDIRELACIONADOS > CFDIRELACIONADO
                $this->xml->writeAttribute("UUID", $v['UUID']);
                $this->xml->endElement(); // END: COMPROBANTE > CFDIRELACIONADOS > CFDIRELACIONADO
            }
            $this->xml->endElement(); // END: COMPROBANTE > CFDIRELACIONADOS
        }
        
        $this->xml->startElement("cfdi:Emisor"); // COMPROBANTE > EMISOR
        $this->setAttributo40($xmlArr['Comprobante']['Emisor']);
        $this->xml->endElement(); // END: COMPROBANTE > EMISOR

        $this->xml->startElement("cfdi:Receptor"); // COMPROBANTE > RECEPTOR
        $this->setAttributo40($xmlArr['Comprobante']['Receptor']);
        $this->xml->endElement();// END: COMPROBANTE > RECEPTOR

        $this->xml->startElement("cfdi:Conceptos"); // COMPROBANTE > CONCEPTOS
            foreach ($xmlArr['Comprobante']['Conceptos'] as $concepto){
                $this->removerNulos($concepto);
                $this->xml->startElement("cfdi:Concepto"); // COMPROBANTE > CONCEPTOS > CONCEPTO
                $this->setAttributo40($concepto);
                    if( !empty($concepto['Impuestos']) && ( !empty($concepto['Impuestos']['Traslados']) || !empty($concepto['Impuestos']['Retenciones']) ) ) {
                        $this->xml->startElement("cfdi:Impuestos"); // COMPROBANTE > CONCEPTOS > CONCEPTO > IMPUESTOS
                            foreach ($concepto['Impuestos']['Traslados'] as $traslado) {
                                $this->xml->startElement("cfdi:Traslados"); // COMPROBANTE > CONCEPTOS > CONCEPTO > IMPUESTOS > TRASLADOS
                                    $this->removerNulos($traslado);                
                                    $this->xml->startElement("cfdi:Traslado");  // COMPROBANTE > CONCEPTOS > CONCEPTO > IMPUESTOS > TRASLADOS > TRASLADO
                                    $this->setAttributo40($traslado);                            
                                    $this->xml->endElement(); // END: COMPROBANTE > CONCEPTOS > CONCEPTO > IMPUESTOS > TRASLADOS > TRASLADO
                                $this->xml->endElement();  // END: COMPROBANTE > CONCEPTOS > CONCEPTO > IMPUESTOS > TRASLADOS                        
                            }
                            foreach ($concepto['Impuestos']['Retenciones'] as $retencion) {
                                $this->xml->startElement("cfdi:Retenciones"); // COMPROBANTE > CONCEPTOS > CONCEPTO > IMPUESTOS > RETENCIONES
                                    $this->removerNulos($retencion);
                                    $this->xml->startElement("cfdi:Retencion");  // COMPROBANTE > CONCEPTOS > CONCEPTO > IMPUESTOS > RETENCIONES > RETENCION
                                    $this->setAttributo40($retencion);                            
                                    $this->xml->endElement(); // END: COMPROBANTE > CONCEPTOS > CONCEPTO > IMPUESTOS > RETENCIONES > RETENCION
                                $this->xml->endElement();  // END: COMPROBANTE > CONCEPTOS > CONCEPTO > IMPUESTOS > RETENCIONES
                            }
                        $this->xml->endElement(); // END: COMPROBANTE > CONCEPTOS > CONCEPTO > IMPUESTOS
                    }
                $this->xml->endElement(); // END: COMPROBANTE > CONCEPTOS > CONCEPTO
            }
        $this->xml->endElement(); // END: COMPROBANTE > CONCEPTOS


        if( !empty($xmlArr['Comprobante']['Impuestos']) && (!empty($xmlArr['Comprobante']['Impuestos']['Retenciones']) || !empty($xmlArr['Comprobante']['Impuestos']['Traslados']) ) ){
            $this->xml->startElement('cfdi:Impuestos'); // COMPROBANTE > IMPUESTOS
            if($xmlArr['Comprobante']['Impuestos']['TotalImpuestosRetenidos']>0) {
                $this->xml->writeAttribute("TotalImpuestosRetenidos",$xmlArr['Comprobante']['Impuestos']['TotalImpuestosRetenidos']);
            }
            if($xmlArr['Comprobante']['Impuestos']['TotalImpuestosTrasladados']>0) {
                $this->xml->writeAttribute("TotalImpuestosTrasladados",$xmlArr['Comprobante']['Impuestos']['TotalImpuestosTrasladados']);
            }
            foreach($xmlArr['Comprobante']['Impuestos']['Retenciones'] as $retencion ) {
                $this->xml->startElement("cfdi:Retenciones"); // COMPROBANTE > IMPUESTOS > RETENCIONES
                    $this->xml->startElement("cfdi:Retencion");  // COMPROBANTE > IMPUESTOS > RETENCIONES > RETENCION
                    $this->removerNulos($retencion);
                    $this->setAttributo40($retencion);
                    $this->xml->endElement(); // END: COMPROBANTE > IMPUESTOS > RETENCIONES > RETENCION
                $this->xml->endElement(); // END: COMPROBANTE > IMPUESTOS > RETENCIONES
            }
            foreach($xmlArr['Comprobante']['Impuestos']['Traslados'] as $traslado ) {
                $this->xml->startElement("cfdi:Traslados"); // COMPROBANTE > IMPUESTOS > TRASLADOS
                    $this->xml->startElement("cfdi:Traslado");  // COMPROBANTE > IMPUESTOS > TRASLADOS > TRASLADO
                    $this->removerNulos($traslado);
                    $this->setAttributo40($traslado);                            
                    $this->xml->endElement(); // END: COMPROBANTE > IMPUESTOS > TRASLADOS > TRASLADO
                $this->xml->endElement(); // END: COMPROBANTE > IMPUESTOS > TRASLADOS             
            }
        }

        $this->xml->endElement(); // END: COMPROBANTE
        
        $this->xml->endDocument();

        $xml = $this->xml->outputMemory(true);
        
        return $xml;	
    }

    private function setAttributo40($attr=array()) {
        foreach ($attr as $key => $val) {
            if( gettype($val)!='array' ) {
                $val = trim($val);
                if( strlen($val)>0 ) {
                    $this->xml->writeAttribute($key,$val);
                }
            }
        }
    }

    function validaEsquemaCFDI40($xml) {
        $docu = new DOMDocument("1.0","UTF-8");
        $docu->loadXML($xml);
        
        libxml_use_internal_errors(true);
        libxml_clear_errors();
        
        $file='core/sat/xsd/cfdv40.xsd';  
        $ok = $docu->schemaValidate($file);
        $errors = libxml_get_errors();

        return array('valid'=>$ok, 'errors'=>$errors);
    }

    function generarSello40($xml, $pKey) {
        
        $doc = new DOMDocument("1.0","UTF-8");
        $doc->loadXML($xml);
        
        $xsl = new DOMDocument("1.0","UTF-8");
        $file='core/sat/xslt/cadenaoriginal_4_0.xslt';
        $xsl->load($file);

        $proc = new XSLTProcessor();
        $proc->importStyleSheet($xsl); 

        $cadenaOriginal = $proc->transformToXML($doc);

        $pkeyid = openssl_get_privatekey($pKey);
        openssl_sign($cadenaOriginal, $crypttext, $pkeyid, OPENSSL_ALGO_SHA256);
        openssl_free_key($pkeyid);

        return base64_encode($crypttext);
        
    }

    public function timbrarXML40($ws_cfdi, $ws_login, $ws_passwd, $ws_metodoTimbrado, $ws_metodoTimbradoResult, $xml, $comprobanteId) {  
       
        $params = array(
            "usuario"=> $ws_login,
            "password"=> $ws_passwd,
            "cadenaXML"=>$xml,
            "referencia"=> $comprobanteId
        );

        $client = new nusoap_client($ws_cfdi, $params);
        $client->soap_defencoding = 'UTF-8';									
        $client->namespaces = array("SOAP-ENV"=>"http://schemas.xmlsoap.org/soap/envelope/","cfdi"=>"https://www.foliosdigitalespac.com/WS-Folios");
        								
        $result = $client->call($ws_metodoTimbrado, $params);

        if ($client->fault) {
            throw new Exception('Error al timbrar el comprobante: PAC_ERROR: '.$result["faultcode"].' - PAC_ERROR_DESC: '.$result["faultstring"].", ".$result["detail"].'<json>'.json_encode($result));
            
        }else if($client->getError()){
            throw new Exception('Error al timbrar el comprobrante: <json>'.json_encode(array('Error'=> $client->getError())));
        }
        
        $CodigoConfirmacion = $result[$ws_metodoTimbradoResult]['CodigoConfirmacion'];
        $CodigoRespuesta = $result[$ws_metodoTimbradoResult]['CodigoRespuesta'];
        $CreditosRestantes = $result[$ws_metodoTimbradoResult]['CreditosRestantes'];
        $MensajeError = utf8_encode($result[$ws_metodoTimbradoResult]['MensajeError']);
        $MensajeErrorDetallado = utf8_encode($result[$ws_metodoTimbradoResult]['MensajeErrorDetallado']);
        $OperacionExitosa = $result[$ws_metodoTimbradoResult]['OperacionExitosa'];
        $PDFResultado = $result[$ws_metodoTimbradoResult]['PDFResultado'];
        $Timbre = $result[$ws_metodoTimbradoResult]['Timbre'];
        $XMLResultado = utf8_encode($result[$ws_metodoTimbradoResult]['XMLResultado']);
        
        if ($OperacionExitosa=='false') {
            $myRes = array(
                'CodigoConfirmacion'=>$CodigoConfirmacion,
                'CodigoRespuesta'=>$CodigoRespuesta,
                'CreditosRestantes'=>$CreditosRestantes,
                'MensajeError'=>$MensajeError,
                'MensajeErrorDetallado'=>$MensajeErrorDetallado,
                'OperacionExitosa'=>$OperacionExitosa,
                'PDFResultado'=>$PDFResultado,
                'Timbre'=>$Timbre,
                'XMLResultado'=>$XMLResultado
            );
            
            $eMsj = 'ERROR AL TIMBRAR: ' . $CodigoRespuesta . ' - ' . $MensajeError .' :' . $MensajeErrorDetallado.'<json>'. json_encode($myRes);
            throw new Exception($eMsj);
        }
        
        return array('Timbre'=>$Timbre, 'XMLResultado'=>$XMLResultado);
    }

    public function cancelarCFDi40($ws_cfdi, $ws_metodo, $ws_metodoResult, $ws_login, $ws_passwd, 
        $rfcEmisor, $UUID, $motivoCancela, $RFCReceptor, $total, $clavePrivada64, $passClavePrivada) {
        
        //parametros del metodo
        $parametros = array(
            "usuario"=> $ws_login,
            "password"=>$ws_passwd,
            "rFCEmisor"=> $rfcEmisor,
            "listaCFDI"=> array (
                "DetalleCFDICancelacion" => array (                     
                    "Motivo"=>$motivoCancela,                    
                    "RFCReceptor"=>$RFCReceptor,
                    "Total"=>$total,
                    "UUID"=>$UUID
                )
            ),
            "clavePrivada_Base64" => $clavePrivada64,
            "passwordClavePrivada"=>$passClavePrivada
        );        
        $options = array(
            'cache_wsdl'=>WSDL_CACHE_NONE,
            'trace'=>true,
            'encoding'=>'UTF-8',
            'exceptions'=>true
        );

        
        $client = new SoapClient($ws_cfdi, $options);
        
        $result = $client->$ws_metodo($parametros);
        
          
        $MensajeError = utf8_encode($result->$ws_metodoResult->MensajeError);
        $MensajeErrorDetallado = utf8_encode($result->$ws_metodoResult->MensajeErrorDetallado);
        $OperacionExitosa = $result->$ws_metodoResult->OperacionExitosa;
        $XMLAcuse = $result->$ws_metodoResult->XMLAcuse;
        
        $CodigoResultado = $result->$ws_metodoResult->DetallesCancelacion->DetalleCancelacion->CodigoResultado;
        $MensajeResultado = $result->$ws_metodoResult->DetallesCancelacion->DetalleCancelacion->MensajeResultado;
        $UUID = $result->$ws_metodoResult->DetallesCancelacion->DetalleCancelacion->UUID;
        
        

        if (!$OperacionExitosa) {
            $myRes = array(
                'MensajeError'=>$MensajeError,
                'MensajeErrorDetallado'=>$MensajeErrorDetallado,
                'OperacionExitosa'=>$OperacionExitosa,
                'XMLAcuse'=>$XMLAcuse,
                'CodigoResultado'=>$CodigoResultado,
                'MensajeResultado'=>$MensajeResultado,
                'UUID'=>$UUID
            );
            
            $eMsj = 'ERROR AL CANCELAR: '.$MensajeError.', '.$MensajeErrorDetallado.'. '.$CodigoResultado.' ('.$UUID.'): '.$MensajeResultado;
            throw new Exception($eMsj);
        }
        
        return array('UUID'=> $UUID,'CodigoResultado'=>$CodigoResultado, 'MensajeResultado'=>$MensajeResultado);
        
    }

    public function obtenerAcuseCancelacion40($ws_cfdi, $ws_metodo, $ws_metodoResult, $ws_login, $ws_passwd, $UUID) {
        
        $options = array(
            'cache_wsdl'=>WSDL_CACHE_NONE,
            'trace'=>true,
            'encoding'=>'UTF-8',
            'exceptions'=>true
        );
        $client = new SoapClient($ws_cfdi, $options);
        
        //parametros del metodo
        $parametros = array(
            "usuario"=> $ws_login,
            "password"=>$ws_passwd,
            "uUID"=> $UUID
        );
        
        $result = $client->$ws_metodo($parametros);
          
        $CodigoRespuesta = $result->$ws_metodoResult->CodigoRespuesta;
        $MensajeError = utf8_encode($result->$ws_metodoResult->MensajeError);
        $MensajeErrorDetallado = utf8_encode($result->$ws_metodoResult->MensajeErrorDetallado);;
        $OperacionExitosa = $result->$ws_metodoResult->OperacionExitosa;
        $PDFResultado = $result->$ws_metodoResult->PDFResultado;
        $CreditosRestantes = $result->$ws_metodoResult->CreditosRestantes;
        $XMLResultado = $result->$ws_metodoResult->XMLResultado;
        

        if (!$OperacionExitosa) {
            $myRes = array(
                'CodigoRespuesta'=>$CodigoRespuesta,
                'MensajeError'=>$MensajeError,
                'MensajeErrorDetallado'=>$MensajeErrorDetallado,
                'OperacionExitosa'=>$OperacionExitosa,
                'PDFResultado'=>$PDFResultado,
                'CreditosRestantes'=>$CreditosRestantes,
                'XMLResultado'=>$XMLResultado
            );
            
            $eMsj = 'ERROR AL OBTENER ACUSE DE CANCELACION: ' . $MensajeError .' :' . $MensajeErrorDetallado.'<json>'. json_encode($myRes);
            throw new Exception($eMsj);
        }
        
        return $XMLResultado;
        
    }
    
    
    
    	
} //FIN CLASE
