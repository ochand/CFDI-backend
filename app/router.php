<?php

require_once 'core/AltoRouter.php';
//require_once '../vendor/autoload.php';
$router = new AltoRouter();

// produccion
$router->setBasePath('/acc/1/cfdi/app');
//desarrollo
//$router->setBasePath('/cfdi4/app');


/**
[i]                  // Match an integer
[i:id]               // Match an integer as 'id'
[a:action]           // Match alphanumeric characters as 'action'
[h:key]              // Match hexadecimal characters as 'key'
[:action]            // Match anything up to the next / or end of the URI as 'action'
[create|edit:action] // Match either 'create' or 'edit' as 'action'
[*]                  // Catch all (lazy, stops at the next trailing slash)
[*:trailing]         // Catch all as 'trailing' (lazy)
[**:trailing]        // Catch all (possessive - will match the rest of the URI)
[:format]?           // Match an optional parameter 'format' - a / or . before the block is also optional

--- Some more complicated examples ---

@/(?[A-Za-z]{2}_[A-Za-z]{2})$ // custom regex, matches language codes like "en_us" etc.
/posts/[*:title][i:id]     // Matches "/posts/this-is-a-title-123"
/output.[xml|json:format]? // Matches "/output", "output.xml", "output.json"
/[:controller]?/[:action]? // Matches the typical /controller/action format

*/

/**
 * 
 * Mas información 
 * https://github.com/dannyvankooten/AltoRouter
 *  
 */



$router->map('GET','/menu', 'getMenu@Usuario','getMenu');
$router->map('GET','/perfil', 'getPerfil@Usuario','getPerfil');

$router->map('GET','/cfdi/[*:uuid].[pdf|xml|json|zip:format]',  'obtenerCFDi@Facturacion', 'obtenerCFDi');
//TRAER DATOS DE UNA FACTURA (EN DESARROLLO)
//$router->map('GET','/cfdi/[*:uuid]',  'obtenerFactura@Facturacion', 'obtenerFactura');

$router->map('GET','/facturar',  'testFactura@Solicitud', 'test');
$router->map('GET','/cfdi/listar/[i:fechaIni]/[i:fechaFin]',  'listarCFDi@Facturacion', 'listarCFDi');

//CONSULTA FISCAL Y DESCARGA DE ZIPS, EXCEL O PDF
$router->map('GET','/cfdi/reporte/[i:fechaIni]/[i:fechaFin]/[html|pdf|json|xslx:format]',  'reporteCFDi@Facturacion', 'reporteCFDi');
$router->map('GET','/cfdi/descargar/[i:fechaIni]/[i:fechaFin]',  'descargarCFDi@Facturacion', 'descargarCFDi');

//SOLICITUD DE CANCELACIÓN
$router->map('POST','/cfdi/solicitarcancelacion',  'solicitarCancelacion@Facturacion', 'solicitarCancelacion');
$router->map('GET','/cfdi/verificarcancelacion/[*:paymentid]',  'verificarCancelacion@Facturacion', 'verificarCancelacion');

//SOLICITUDES DE CANCELACIONES
$router->map('GET','/cfdi/solcancelacion/[i:fechaIni]/[i:fechaFin]',  'listarSolCancelacionCFDi@Facturacion', 'listarSolCancelacionCFDi');

$router->map('GET','/cfdi/cancelar/[*:uuid]',  'cancelarCFDi@Facturacion', 'cancelarCFDi');
// TRAER DEL PAC Y ACTUALIZAR EN BASE DE DATOS
$router->map('PUT','/cfdi/[*:uuid]',  'actualizarCFDi@Facturacion', 'actualizarCFDi');
$router->map('POST','/cfdi/email',      'emailCFDi@Facturacion', 'emailCFDi');

$router->map('POST','/timbrarSolicitud','timbrarSol@Solicitud', 'timbrarSolicitud');

$router->map('POST','/solicitud',       'insertSol@Solicitud', 'guardarSolicitud');
$router->map('GET', '/solicitud',       'listar@Solicitud', 'listarSolicitudes');
$router->map('POST','/solicitud/[*:paymentid]',  'facturar@Solicitud', 'facturarSolicitud');


$router->map('POST','/group',  'facturarGroup@Solicitud', 'groupSolicitud');


$router->map('GET', '/enviar',  'enviarCFDi@Facturacion', 'enviarCFDi');

//BUSCAR UUID POR SERIE Y FOLIO
$router->map('GET', '/cfdi_uuid/[*:emisor]/[*:serie]/[*:folio]',  'obtenerUUID@Facturacion', 'obtenerUUID');



//CFDI 3.3
$router->map('POST', '/comprobantes33', 'insertar@Comprobante33', 'insertarComprobante33');
$router->map('POST', '/comprobantes33/timbrar/[*:_id]', 'timbrar@Comprobante33', 'timbrarComprobante33');
$router->map('POST', '/comprobantes33/actualizar/[*:_id]', 'actualizar@Comprobante33', 'actualizarComprobante33');
$router->map('POST', '/cfdi33/[*:uuid]/email', 'email@Cfdi33', 'emailCfdi33');
$router->map('GET', '/cfdi33/[*:uuid].[pdf|xml|zip:format]', 'obtener@Cfdi33', 'obtenerCfdi33');
$router->map('GET', '/cfdi33/[*:uuid]/cancelar', 'cancelar@Cfdi33', 'cancelarCfdi33');
$router->map('GET', '/cfdi33/[*:emisor]/[*:serie]/[*:folio]/buscar',  'buscar@Cfdi33', 'buscarCfdi33');
$router->map('GET', '/cfdi33/[i:fechaIni]/[i:fechaFin]', 'listar@Cfdi33', 'listarCfdi33');
$router->map('GET', '/cfdi33/[*:uuid]/acusecancelacion', 'obtenerAcuseCancelacion@Cfdi33', 'obtenerAcuseCancelacionCfdi33');
$router->map('GET', '/cfdi33/[i:fechaIni]/[i:fechaFin]/descargar', 'descargar@Cfdi33', 'descargarCfdi33');
$router->map('GET', '/cfdi33/varlidarrfc/[*:emisor]/[*:rfc]', 'validarRFC@Cfdi33', 'validarRFCCfdi33');
$router->map('GET', '/cfdi33/[*:uuid]/cancelarconvalidacion', 'cancelarconvalidacion@Cfdi33', 'cancelarconvalidacionCfdi33');

//CFDI 4.0
$router->map('POST', '/v40/comprobantes', 'insertar@Comprobante40', 'insertarComprobante40'); //ok
$router->map('PUT', '/v40/comprobantes/[*:_id]/timbrar', 'timbrar@Comprobante40', 'timbrarComprobante40'); //ok
$router->map('PUT', '/v40/comprobantes/[*:_id]/actualizar', 'actualizar@Comprobante40', 'actualizarComprobante40'); //ok
$router->map('POST', '/v40/cfdi/[*:uuid]/email', 'email@Cfdi40', 'emailCfdi40'); //ok
$router->map('GET', '/v40/cfdi/[*:uuid].[pdf|xml|zip:format]', 'obtener@Cfdi40', 'obtenerCfdi40'); //ok
$router->map('PUT', '/v40/cfdi/[*:uuid]/cancelar', 'cancelar@Cfdi40', 'cancelarCfdi40'); //no funciona quiza por el ambiente de pruebas
$router->map('GET', '/v40/cfdi/[*:emisor]/[*:serie]/[*:folio]/buscar',  'buscar@Cfdi40', 'buscarCfdi40'); //ok
$router->map('GET', '/v40/cfdi/[i:fechaIni]/[i:fechaFin]', 'listar@Cfdi40', 'listarCfdi40'); //ok
$router->map('GET', '/v40/cfdi/[*:uuid]/acusecancelacion', 'obtenerAcuseCancelacion@Cfdi40', 'obtenerAcuseCancelacionCfdi40'); //no hay datos
$router->map('GET', '/v40/cfdi/[i:fechaIni]/[i:fechaFin]/descargar', 'descargar@Cfdi40', 'descargarCfdi40'); //ok
$router->map('GET', '/v40/cfdi/varlidarrfc/[*:emisor]/[*:rfc]', 'validarRFC@Cfdi40', 'validarRFCCfdi40'); //no funciona quiza por el ambiente de pruebas



$match = $router->match();


if(!$match){
    httpResp::setHeaders(404);
    echo json_encode(array('error'=>array('code'=>404,'message'=>"Ruta no encontrada")));
    die();
}
?>
