# CFDI Backend - API de Facturación Electrónica

[![PHP Version](https://img.shields.io/badge/PHP-7.4%2B-blue.svg)](https://www.php.net)
[![CFDI Version](https://img.shields.io/badge/CFDI-3.3%20%7C%204.0-green.svg)](https://www.sat.gob.mx)
[![License](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)

API REST completa para la generación, timbrado y gestión de Comprobantes Fiscales Digitales por Internet (CFDI) en México, cumpliendo con todos los estándares del SAT.

## 🚀 Características

- ✅ **Soporte completo CFDI 3.3 y 4.0**
- ✅ **Integración con PAC (Proveedor Autorizado de Certificación)**
- ✅ **Generación automática de PDF**
- ✅ **Envío por correo electrónico**
- ✅ **Cancelación de comprobantes**
- ✅ **Validación con esquemas SAT**
- ✅ **API REST con autenticación OAuth2**
- ✅ **Múltiples formatos de salida (XML, PDF, ZIP, JSON)**

## 📋 Requisitos del Sistema

- **PHP 7.4+** con extensiones:
  - PDO MySQL
  - XML
  - OpenSSL
  - cURL
  - mbstring
- **MySQL 5.7+**
- **Servidor web** (Apache/Nginx)
- **Certificados SAT** (.cer y .key)
- **Cuenta con PAC autorizado**

## 🛠️ Instalación

### 1. Clonar el repositorio
```bash
git clone https://github.com/tu-usuario/cfdi-backend.git
cd cfdi-backend
```

### 2. Instalar dependencias
```bash
composer install
```

### 3. Configurar base de datos
```sql
CREATE DATABASE facturacion_cfdi;
-- Importar esquema de base de datos (schema.sql)
```

### 4. Configurar variables de entorno
```php
// app/core/config.php
final class config {
    // Base de datos
    public static $db_name = "tu_base_datos";
    public static $db_user = "tu_usuario";
    public static $db_host = "localhost";
    public static $db_pass = "tu_password";
    
    // Servidor de correo
    public static $mailHost = "tu_servidor_smtp";
    public static $mailPort = 587;
    public static $mailUser = "tu_email";
    public static $mailPasswd = "tu_password";
}
```

### 5. Configurar servidor web
```apache
# Apache .htaccess
RewriteEngine On
RewriteBase /cfdi/app/
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]
```

## 🔧 Configuración

### Certificados SAT
1. Coloca tus certificados en el directorio seguro
2. Registra la información del emisor en la tabla `EMISORES`
3. Configura la conexión con tu PAC

### OAuth2
El sistema utiliza OAuth2 para autenticación. Configura tus credenciales de cliente en la base de datos.

## 📚 Uso de la API

### Autenticación
```bash
# Obtener token de acceso
curl -X POST "https://tu-dominio.com/cfdi/app/oauth/token" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "grant_type=client_credentials&client_id=tu_client&client_secret=tu_secret"
```

### Crear Comprobante CFDI 4.0
```bash
curl -X POST "https://tu-dominio.com/cfdi/app/v40/comprobantes" \
  -H "Authorization: Bearer tu_token" \
  -H "Content-Type: application/json" \
  -d '{
    "e_Comprobante": {
      "Emisor_Rfc": "TES030201001",
      "Emisor_Nombre": "Test Emisor",
      "Receptor_Rfc": "XAXX010101000",
      "Receptor_Nombre": "PUBLICO EN GENERAL",
      "Total": 116.00,
      "SubTotal": 100.00,
      "Version": "4.0"
    },
    "e_Conceptos": [
      {
        "ClaveProdServ": "01010101",
        "Cantidad": 1,
        "ClaveUnidad": "ACT",
        "Descripcion": "Servicio de prueba",
        "ValorUnitario": 100.00,
        "Importe": 100.00,
        "ObjetoImp": "02"
      }
    ]
  }'
```

### Timbrar Comprobante
```bash
curl -X PUT "https://tu-dominio.com/cfdi/app/v40/comprobantes/{id}/timbrar" \
  -H "Authorization: Bearer tu_token"
```

### Obtener CFDI en diferentes formatos
```bash
# XML
curl "https://tu-dominio.com/cfdi/app/v40/cfdi/{uuid}.xml" \
  -H "Authorization: Bearer tu_token"

# PDF
curl "https://tu-dominio.com/cfdi/app/v40/cfdi/{uuid}.pdf" \
  -H "Authorization: Bearer tu_token"

# ZIP (XML + PDF)
curl "https://tu-dominio.com/cfdi/app/v40/cfdi/{uuid}.zip" \
  -H "Authorization: Bearer tu_token"
```

### Cancelar CFDI
```bash
curl -X PUT "https://tu-dominio.com/cfdi/app/v40/cfdi/{uuid}/cancelar" \
  -H "Authorization: Bearer tu_token" \
  -H "Content-Type: application/json" \
  -d '{
    "Motivo": "02",
    "FolioSustitucion": ""
  }'
```

### Enviar por Email
```bash
curl -X POST "https://tu-dominio.com/cfdi/app/v40/cfdi/{uuid}/email" \
  -H "Authorization: Bearer tu_token" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "cliente@example.com",
    "asunto": "Su comprobante fiscal",
    "mensaje": "Adjunto encontrará su comprobante fiscal"
  }'
```

## 📖 Endpoints Disponibles

### CFDI 4.0 (Recomendado)
| Método | Endpoint | Descripción |
|--------|----------|-------------|
| `POST` | `/v40/comprobantes` | Crear comprobante |
| `PUT` | `/v40/comprobantes/{id}/timbrar` | Timbrar comprobante |
| `PUT` | `/v40/comprobantes/{id}/actualizar` | Actualizar comprobante |
| `GET` | `/v40/cfdi/{uuid}.{format}` | Obtener CFDI |
| `PUT` | `/v40/cfdi/{uuid}/cancelar` | Cancelar CFDI |
| `POST` | `/v40/cfdi/{uuid}/email` | Enviar por email |
| `GET` | `/v40/cfdi/{fechaIni}/{fechaFin}` | Listar CFDIs |
| `GET` | `/v40/cfdi/{fechaIni}/{fechaFin}/descargar` | Descarga masiva |

### CFDI 3.3 (Legacy)
| Método | Endpoint | Descripción |
|--------|----------|-------------|
| `POST` | `/comprobantes33` | Crear comprobante |
| `POST` | `/comprobantes33/timbrar/{id}` | Timbrar comprobante |
| `GET` | `/cfdi33/{uuid}.{format}` | Obtener CFDI |
| `GET` | `/cfdi33/{uuid}/cancelar` | Cancelar CFDI |

### Formatos soportados
- `xml` - Comprobante fiscal oficial
- `pdf` - Representación impresa
- `zip` - Archivos comprimidos (XML + PDF)
- `json` - Datos estructurados

## 🏗️ Arquitectura

```
cfdi-backend/
├── app/
│   ├── core/                    # Núcleo del sistema
│   │   ├── CFDI.php            # Clase principal CFDI
│   │   ├── config.php          # Configuración
│   │   ├── OAuth2/             # Autenticación
│   │   ├── PHPMailer/          # Correo electrónico
│   │   ├── tcpdf/              # Generación PDF
│   │   └── sat/                # Esquemas SAT
│   ├── models/                  # Modelos de datos
│   │   ├── Comprobante40.php   # CFDI 4.0
│   │   ├── Cfdi40.php          # Operaciones CFDI 4.0
│   │   └── ...
│   ├── assets/                  # Recursos estáticos
│   ├── router.php              # Configuración de rutas
│   └── index.php               # Punto de entrada
```

## 🔍 Validaciones

El sistema incluye validaciones completas:

- **Estructura XML**: Validación contra esquemas XSD del SAT
- **Datos fiscales**: RFC, códigos postales, catálogos SAT
- **Cálculos**: Subtotales, impuestos, totales
- **Certificados**: Validación de certificados digitales
- **Sellado**: Generación correcta de sello digital

## 📊 Manejo de Errores

```json
{
  "error": {
    "code": 500,
    "message": "ERROR AL TIMBRAR: CFDI40215 - El campo Importe no coincide",
    "debug": [...]
  }
}
```

El sistema implementa:
- Rollback automático de transacciones
- Logging detallado de errores
- Códigos de estado HTTP apropiados
- Mensajes descriptivos en español

## 🧪 Testing

```bash
# Ejecutar pruebas (cuando estén implementadas)
./vendor/bin/phpunit tests/

# Probar endpoint específico
curl -X GET "https://tu-dominio.com/cfdi/app/facturar" \
  -H "Authorization: Bearer tu_token"
```

## 📈 Monitoreo

El sistema genera logs en:
- `temp/bitacora.txt` - Log principal de errores
- `temp/lastXML.xml` - Último XML generado
- `temp/sello.txt` - Cadena original para debugging

## 🔒 Seguridad

- **OAuth2**: Autenticación robusta
- **HTTPS**: Comunicación segura (recomendado)
- **Validación de entrada**: Sanitización de datos
- **Transacciones**: Consistencia de base de datos
- **Certificados**: Almacenamiento seguro de llaves privadas

## 🚀 Despliegue

### Producción
1. Configurar servidor HTTPS
2. Optimizar configuración PHP
3. Configurar backup de base de datos
4. Monitorear logs de errores
5. Configurar certificados SAT de producción

### Desarrollo
```bash
# Servidor de desarrollo PHP
cd app/
php -S localhost:8000
```

## 🤝 Contribución

1. Fork el proyecto
2. Crea una rama para tu feature (`git checkout -b feature/nueva-funcionalidad`)
3. Commit tus cambios (`git commit -am 'Agrega nueva funcionalidad'`)
4. Push a la rama (`git push origin feature/nueva-funcionalidad`)
5. Crea un Pull Request

## 📄 Licencia

Este proyecto está bajo la Licencia MIT - ver el archivo [LICENSE](LICENSE) para detalles.

## 🆘 Soporte

- **Documentación SAT**: [www.sat.gob.mx](https://www.sat.gob.mx)
- **Issues**: Reporta problemas en GitHub Issues
- **Wiki**: Documentación adicional en GitHub Wiki

## 🔄 Changelog

### v2.0.0 - CFDI 4.0
- ✅ Soporte completo para CFDI 4.0
- ✅ Información Global para facturación consolidada
- ✅ Nuevos campos de exportación
- ✅ Validaciones actualizadas

### v1.0.0 - CFDI 3.3
- ✅ Implementación inicial CFDI 3.3
- ✅ Timbrado y cancelación
- ✅ Generación de PDF
- ✅ API REST con OAuth2

## ⚡ Roadmap

- [ ] **PHP 8+**: Modernización del código
- [ ] **Testing**: Cobertura completa de pruebas
- [ ] **Docker**: Containerización
- [ ] **Webhooks**: Notificaciones automáticas
- [ ] **Cache**: Optimización de consultas
- [ ] **Queue System**: Procesamiento asíncrono

---

**Desarrollado con ❤️ para la comunidad de desarrolladores mexicanos**