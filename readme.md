# Chat Bob: Un Agente de IA Proactivo para WordPress y WooCommerce

## 1. Filosofía y Visión del Proyecto

**Estado:** Versión 10.0 (Arquitectura Híbrida Resiliente)
**Fecha de este Documento:**

### 1.1. Nuestro Objetivo: Un Agente, no un Chatbot

El mercado está saturado de chatbots que actúan como FAQs glorificadas. **Chat Bob** se construye sobre una filosofía fundamentalmente diferente: es un **Agente de Ventas y Soporte Autónomo**. Su propósito no es solo responder preguntas con el contexto que se le da, sino **decidir qué información necesita, buscarla activamente y actuar** para guiar al cliente, mejorar la conversión y maximizar la satisfacción.

### 1.2. Los Pilares de Chat Bob

1.  **Contexto Total y Persistente:** El agente nunca debe estar "ciego". El sistema enriquece cada interacción con un contexto base (perfil de cliente, fecha/hora) y es capaz de **ingestar y recordar** el contenido de documentos subidos por el usuario durante una misma sesión.
2.  **Autonomía Proactiva (Agente con Herramientas):** El LLM tiene a su disposición un conjunto de **herramientas** (`buscar_productos`, `crear_pedido`, etc.). El agente analiza la pregunta del usuario y decide por sí mismo qué herramienta usar, demostrando una autonomía real.
3.  **Integración Nativa con WordPress:** Chat Bob es un ciudadano de primera clase de WordPress. Usa CPTs para las sesiones, se integra con la Biblioteca de Medios y aprovecha las funciones de WooCommerce.
4.  **Independencia Visual Absoluta (Arquitectura Separada):** La interfaz se renderiza en dos partes independientes (ventana y botón) para anular por completo los conflictos de CSS con cualquier tema, garantizando una experiencia de usuario consistente y robusta.

## 2. Arquitectura Detallada del Plugin (V10)

La estructura de archivos ha sido finalizada para soportar la arquitectura híbrida de procesamiento de archivos y la interfaz de usuario separada.

```
/chat-bob/
│
├── includes/
│   ├── ajax-handlers.php         -> Orquestador de IA (Lógica Híbrida de Archivos y Herramientas)
│   ├── admin-ajax-handlers.php   -> Lógica del Panel de Admin (Historial, etc.)
│   ├── tool-schemas.php          -> Manifiesto de Herramientas (Definiciones JSON)
│   ├── tool-executor.php         -> Motor de Ejecución de Herramientas (Código PHP)
│   ├── post-types.php            -> Estructura de Datos (CPT para Sesiones)
│   ├── admin-page-settings.php   -> Panel de Configuración (con capacidades de modelo)
│   └── admin-page-history.php    -> Panel de Historial de Chats
│
├── templates/
│   ├── chat-ui-page.php          -> Plantilla de la Ventana del Chat
│   └── chat-ui-button.php        -> Plantilla del Botón Flotante
│
├── assets/
│   ├── script.js                 -> Lógica del Chat (Frontend con subida de archivos)
│   └── style.css                 -> Estilos del Chat (Frontend)
│
├── chat-bob.php                  -> Director de Orquesta (Carga de assets y plantillas)
└── uninstall.php                 -> Protocolo de Limpieza
```

### 2.1. Desglose de Componentes Clave

*   **`ajax-handlers.php` (El Cerebro Central):** Este es el componente más avanzado. Implementa la lógica de decisión híbrida:
    1.  Recibe los mensajes y los archivos subidos.
    2.  Consulta las capacidades del modelo de IA seleccionado (guardadas en la base de datos).
    3.  Decide si procesar el archivo usando la capacidad nativa del LLM (vía Base64) o si debe delegar la lectura al servicio Tika.
    4.  Gestiona el guardado persistente del contexto extraído por Tika en los metadatos de la sesión.
    5.  Orquesta el bucle de conversación con las herramientas.
*   **`admin-page-settings.php`:** Ha sido rediseñado para ser más inteligente. Al probar la conexión, no solo lista los modelos, sino que también analiza y guarda sus capacidades (`supports_vision`, `supports_pdf_input`) en la base de datos de WordPress para que el resto del plugin las use.
*   **`templates/chat-ui-page.php` y `chat-ui-button.php`:** La interfaz se ha separado en dos archivos para resolver de raíz los conflictos de posicionamiento con los temas de WordPress. El orquestador principal (`chat-bob.php`) se encarga de renderizarlos en el `wp_footer`.


## Los placeholders que existen actualmente son:
[store_name]: Muestra el nombre del sitio (desde la configuración de WordPress).
[store_tagline]: Muestra la descripción corta del sitio.
[store_url]: Muestra la URL principal de la tienda.
[contact_url]: Muestra la URL de la página "Mi Cuenta" de WooCommerce, que sirve como página de contacto.
[admin_email]: Muestra el correo del administrador del sitio.