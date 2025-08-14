<?php
/**
 * Gestiona el registro y renderizado de la página de configuración.
 * @package ChatBob
 */

if (!defined('ABSPATH')) {
    exit; // Salida de seguridad si se accede directamente.
}

// =====================================================================
// == 1. REGISTRO DE AJUSTES, SECCIONES Y CAMPOS (API de Ajustes de WP)
// =====================================================================

function chat_bob_register_settings()
{
    register_setting('chat_bob_options_group', 'chat_bob_settings', 'chat_bob_sanitize_settings');
    register_setting('chat_bob_options_group', 'chat_bob_model_capabilities', 'chat_bob_sanitize_capabilities');
    add_settings_section('chat_bob_section_api', __('Conexión con liteLLM', 'chat-bob'), null, 'chat-bob-settings-page');
    add_settings_field('api_url', __('URL del Proxy liteLLM', 'chat-bob'), 'chat_bob_field_callback_text', 'chat-bob-settings-page', 'chat_bob_section_api', ['id' => 'api_url', 'type' => 'url', 'placeholder' => 'https://litellm.tu-dominio.com']);
    add_settings_field('api_key', __('API Key', 'chat-bob'), 'chat_bob_field_callback_text', 'chat-bob-settings-page', 'chat_bob_section_api', ['id' => 'api_key', 'type' => 'password']);
    add_settings_field('model', __('Modelo de IA', 'chat-bob'), 'chat_bob_field_callback_model_select', 'chat-bob-settings-page', 'chat_bob_section_api');
    add_settings_section('chat_bob_section_behavior', __('Comportamiento del Chat', 'chat-bob'), null, 'chat-bob-settings-page');
    add_settings_field('prompt', __('Prompt del Sistema', 'chat-bob'), 'chat_bob_field_callback_textarea', 'chat-bob-settings-page', 'chat_bob_section_behavior', ['id' => 'prompt', 'rows' => 8, 'placeholder' => __('Eres "Bob", un asistente experto...', 'chat-bob')]);
    add_settings_field('welcome_message', __('Mensaje de Bienvenida', 'chat-bob'), 'chat_bob_field_callback_text', 'chat-bob-settings-page', 'chat_bob_section_behavior', ['id' => 'welcome_message', 'placeholder' => __('¡Hola! ¿Cómo puedo ayudarte?', 'chat-bob')]);
    add_settings_field('history_limit', __('Límite de Historial', 'chat-bob'), 'chat_bob_field_callback_number', 'chat-bob-settings-page', 'chat_bob_section_behavior', ['id' => 'history_limit', 'default' => 10, 'description' => __('Número de mensajes previos a enviar en cada petición.', 'chat-bob')]);
    add_settings_section('chat_bob_section_appearance', __('Apariencia del Chat', 'chat-bob'), null, 'chat-bob-settings-page');
    add_settings_field('chat_title', __('Título de la Ventana', 'chat-bob'), 'chat_bob_field_callback_text', 'chat-bob-settings-page', 'chat_bob_section_appearance', ['id' => 'chat_title', 'placeholder' => 'Asistente Virtual']);
    add_settings_field('position', __('Posición del Botón', 'chat-bob'), 'chat_bob_field_callback_select', 'chat-bob-settings-page', 'chat_bob_section_appearance', ['id' => 'position', 'options' => ['bottom-right' => __('Abajo a la Derecha', 'chat-bob'), 'bottom-left' => __('Abajo a la Izquierda', 'chat-bob')]]);
    add_settings_field('primary_color', __('Color de Acento', 'chat-bob'), 'chat_bob_field_callback_color', 'chat-bob-settings-page', 'chat_bob_section_appearance', ['id' => 'primary_color', 'default' => '#2563eb']);
    add_settings_section('chat_bob_section_files', __('Procesamiento de Archivos', 'chat-bob'), null, 'chat-bob-settings-page');
    add_settings_field('tika_url', __('URL del Servicio Tika', 'chat-bob'), 'chat_bob_field_callback_text', 'chat-bob-settings-page', 'chat_bob_section_files', ['id' => 'tika_url', 'type' => 'url', 'placeholder' => 'https://tika.tu-servidor.com/tika', 'description' => __('Opcional. Para procesar archivos si el modelo no lo soporta nativamente.', 'chat-bob')]);
    add_settings_field('file_size_limit', __('Límite Tamaño de Archivo (MB)', 'chat-bob'), 'chat_bob_field_callback_number', 'chat-bob-settings-page', 'chat_bob_section_files', ['id' => 'file_size_limit', 'default' => 10, 'description' => __('Tamaño máximo para los archivos subidos.', 'chat-bob')]);
}
add_action('admin_init', 'chat_bob_register_settings');

// =====================================================================
// == 2. FUNCIONES CALLBACK PARA RENDERIZAR CAMPOS DE FORMULARIO
// =====================================================================

function chat_bob_get_default_options()
{
    return ['api_url' => '', 'api_key' => '', 'model' => '', 'prompt' => 'Eres "Bob", un asistente de ventas experto.', 'welcome_message' => '¡Hola! Soy Bob, tu asistente. ¿En qué te ayudo?', 'history_limit' => 10, 'chat_title' => 'Asistente Virtual', 'position' => 'bottom-right', 'primary_color' => '#2563eb', 'tika_url' => '', 'file_size_limit' => 10,];
}
function chat_bob_field_callback_text($args)
{
    $options = get_option('chat_bob_settings', chat_bob_get_default_options());
    $value = $options[$args['id']] ?? '';
    echo "<input type='{$args['type']}' id='{$args['id']}' name='chat_bob_settings[{$args['id']}]' value='" . esc_attr($value) . "' class='regular-text' placeholder='" . esc_attr($args['placeholder'] ?? '') . "'>";
    if (isset($args['description']))
        echo "<p class='description'>" . esc_html($args['description']) . "</p>";
}
function chat_bob_field_callback_textarea($args)
{
    $options = get_option('chat_bob_settings', chat_bob_get_default_options());
    $value = $options[$args['id']] ?? '';
    echo "<textarea id='{$args['id']}' name='chat_bob_settings[{$args['id']}]' rows='{$args['rows']}' class='large-text code' placeholder='{$args['placeholder']}'>" . esc_textarea($value) . "</textarea>";
}
function chat_bob_field_callback_number($args)
{
    $options = get_option('chat_bob_settings', chat_bob_get_default_options());
    $value = $options[$args['id']] ?? $args['default'];
    echo "<input type='number' id='{$args['id']}' name='chat_bob_settings[{$args['id']}]' value='" . esc_attr($value) . "' class='small-text' min='1'>";
    if (isset($args['description']))
        echo "<p class='description'>" . esc_html($args['description']) . "</p>";
}
function chat_bob_field_callback_select($args)
{
    $options = get_option('chat_bob_settings', chat_bob_get_default_options());
    $selected = $options[$args['id']] ?? '';
    echo "<select id='{$args['id']}' name='chat_bob_settings[{$args['id']}]'>";
    foreach ($args['options'] as $value => $label) {
        echo "<option value='{$value}' " . selected($selected, $value, false) . ">{$label}</option>";
    }
    echo "</select>";
}
function chat_bob_field_callback_color($args)
{
    $options = get_option('chat_bob_settings', chat_bob_get_default_options());
    $value = $options[$args['id']] ?? $args['default'];
    echo "<input type='text' id='{$args['id']}' name='chat_bob_settings[{$args['id']}]' value='" . esc_attr($value) . "' class='chat-bob-color-picker'>";
}
function chat_bob_field_callback_model_select()
{
    $options = get_option('chat_bob_settings', chat_bob_get_default_options());
    $selected_model = $options['model'] ?? ''; ?>
    <select id="model" name="chat_bob_settings[model]">
        <option value=""><?php _e('-- Clic en "Probar" para cargar --', 'chat-bob'); ?></option>
        <?php if (!empty($selected_model)): ?>
            <option value="<?php echo esc_attr($selected_model); ?>" selected><?php echo esc_html($selected_model); ?></option>
        <?php endif; ?>
    </select> <button type="button" id="get_models_button"
        class="button button-primary"><?php _e('Probar y Obtener Modelos', 'chat-bob'); ?></button> <span class="spinner"
        style="float:none;vertical-align:middle;"></span>
    <div id="model-info-display"
        style="margin-top:15px;padding:12px;border:1px solid #ccd0d4;background-color:#fff;border-radius:4px;display:none;max-width:700px;">
    </div> <input type="hidden" id="chat_bob_model_capabilities" name="chat_bob_model_capabilities"
        value='<?php echo esc_attr(get_option('chat_bob_model_capabilities', '{}')); ?>'> <?php }

// =====================================================================
// == 3. FUNCIONES DE SANITIZACIÓN Y RENDERIZADO DE PÁGINA
// =====================================================================

function chat_bob_sanitize_settings($input)
{
    $sanitized_input = [];
    $defaults = chat_bob_get_default_options();
    $current_options = get_option('chat_bob_settings', []);
    foreach ($defaults as $field => $default_value) {
        if ('api_key' === $field && empty($input[$field])) {
            $sanitized_input[$field] = $current_options[$field] ?? '';
            continue;
        }
        if (isset($input[$field])) {
            if ($field === 'prompt')
                $sanitized_input[$field] = sanitize_textarea_field($input[$field]);
            elseif (in_array($field, ['tika_url', 'api_url']))
                $sanitized_input[$field] = esc_url_raw($input[$field]);
            else
                $sanitized_input[$field] = sanitize_text_field($input[$field]);
        }
    }
    return $sanitized_input;
}
function chat_bob_sanitize_capabilities($input)
{
    if (empty($input))
        return '{}';
    $decoded = json_decode(stripslashes($input), true);
    return is_array($decoded) ? wp_json_encode($decoded) : '{}';
}

function chat_bob_render_settings_page()
{
    wp_enqueue_style('wp-color-picker');
    wp_enqueue_script('wp-color-picker');
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <form action="options.php" method="post">
            <?php settings_fields('chat_bob_options_group');
            do_settings_sections('chat-bob-settings-page');
            submit_button(__('Guardar Todos los Cambios', 'chat-bob')); ?>
        </form>
    </div>
    <?php
    chat_bob_inject_settings_script();
}

// =====================================================================
// == 4. JAVASCRIPT PARA LA PÁGINA DE CONFIGURACIÓN
// =====================================================================

function chat_bob_inject_settings_script()
{
    $current_settings = get_option('chat_bob_settings', chat_bob_get_default_options());
    $selected_model = $current_settings['model'] ?? '';
    ?>
    <script type="text/javascript">
        jQuery(document).ready(function ($) {
            let modelDataStore = {};
            $('.chat-bob-color-picker').wpColorPicker();

            $('#get_models_button').on('click', function () {
                var button = $(this), spinner = button.next('.spinner'), modelSelect = $('#model'), apiUrl = $('#api_url').val(), apiKey = $('#api_key').val();
                if (!apiUrl || !apiKey) {
                    Swal.fire({ icon: 'error', title: '<?php echo esc_js(__("Faltan Datos", "chat-bob")); ?>', text: '<?php echo esc_js(__("Por favor, introduce la URL y la API Key de liteLLM.", "chat-bob")); ?>' });
                    return;
                }
                spinner.addClass('is-active'); button.prop('disabled', true);
                const endpoint = apiUrl.replace(/\/$/, "") + '/v1/model/info';

                $.ajax({
                    url: endpoint, type: 'GET', headers: { 'Authorization': 'Bearer ' + apiKey },
                    success: function (response) {
                        const models = response.data;
                        if (models && Array.isArray(models)) {
                            modelDataStore = {}; let capabilities = {};
                            modelSelect.empty().append($('<option>', { value: '', text: '-- <?php echo esc_js(__("Selecciona un modelo", "chat-bob")); ?> --' }));

                            $.each(models, function (index, model) {
                                const modelId = model.model_name;
                                if (!modelId) return;

                                modelDataStore[modelId] = model;
                                modelSelect.append($('<option>', { value: modelId, text: modelId }));

                                const info = model.model_info || {};
                                capabilities[modelId] = {
                                    max_tokens: info.max_tokens || 0,
                                    supports_vision: !!info.supports_vision,
                                    supports_pdf_input: !!info.supports_pdf_input,
                                    supports_function_calling: !!info.supports_function_calling,
                                    supports_tool_choice: !!info.supports_tool_choice,
                                    supports_audio_input: !!info.supports_audio_input,
                                    input_cost_per_token: info.input_cost_per_token || 0,
                                    output_cost_per_token: info.output_cost_per_token || 0,
                                };
                            });

                            $('#chat_bob_model_capabilities').val(JSON.stringify(capabilities));
                            modelSelect.val('<?php echo esc_js($selected_model); ?>').trigger('change');
                            Swal.fire({ icon: 'success', title: '<?php echo esc_js(__("¡Éxito!", "chat-bob")); ?>', text: '<?php echo esc_js(__("Modelos y capacidades cargados.", "chat-bob")); ?>', toast: true, position: 'top-end', showConfirmButton: false, timer: 3000 });
                        } else {
                            Swal.fire({ icon: 'error', title: '<?php echo esc_js(__("Respuesta Inválida", "chat-bob")); ?>', text: '<?php echo esc_js(__("La API no devolvió una lista de modelos válida.", "chat-bob")); ?>' });
                        }
                    },
                    error: function (jqXHR) {
                        let errorMsg = jqXHR.responseJSON?.error?.message || jqXHR.statusText || '<?php echo esc_js(__("Error desconocido.", "chat-bob")); ?>';
                        Swal.fire({ icon: 'error', title: '<?php echo esc_js(__("Error de Conexión", "chat-bob")); ?>', text: errorMsg });
                    },
                    complete: function () { spinner.removeClass('is-active'); button.prop('disabled', false); }
                });
            });

            $('#model').on('change', function () {
                const modelId = $(this).val();
                const infoDiv = $('#model-info-display');

                if (modelId && modelDataStore[modelId]) {
                    const capabilities = JSON.parse($('#chat_bob_model_capabilities').val())[modelId] || {};

                    const formatCost = (cost) => cost ? `$${(cost * 1000000).toFixed(4)}` : 'N/A';
                    const formatBool = (val) => val ? '<span style="color:green;font-weight:bold;"><?php echo esc_js(__("Sí", "chat-bob")); ?></span>' : '<span style="color:red;"><?php echo esc_js(__("No", "chat-bob")); ?></span>';
                    const formatTokens = (tokens) => tokens ? parseInt(tokens).toLocaleString() : 'N/A';

                    let infoHtml = `
                    <style>
                        #model-info-display table { width: 100%; border-collapse: collapse; }
                        #model-info-display th, #model-info-display td { text-align: left; padding: 6px 4px; border-bottom: 1px solid #eee; }
                        #model-info-display th { font-weight: 600; width: 40%; }
                    </style>
                    <h4>Detalles del modelo: ${modelId}</h4>
                    <table>
                        <tr><th>Contexto Máximo</th><td>${formatTokens(capabilities.max_tokens)} tokens</td></tr>
                        <tr><th>Precio Entrada / 1M tokens</th><td>${formatCost(capabilities.input_cost_per_token)}</td></tr>
                        <tr><th>Precio Salida / 1M tokens</th><td>${formatCost(capabilities.output_cost_per_token)}</td></tr>
                        <tr><td colspan="2"><hr style="margin: 5px 0; border-top: 1px solid #ddd;"></td></tr>
                        <tr><th>Soporta Herramientas (Agente)</th><td>${formatBool(capabilities.supports_function_calling)}</td></tr>
                        <tr><th>Soporta Visión (Imágenes)</th><td>${formatBool(capabilities.supports_vision)}</td></tr>
                        <tr><th>Soporta PDF (Nativo)</th><td>${formatBool(capabilities.supports_pdf_input)}</td></tr>
                        <tr><th>Soporta Audio (Entrada)</th><td>${formatBool(capabilities.supports_audio_input)}</td></tr>
                    </table>`;

                infoDiv.html(infoHtml).show();
            } else {
                infoDiv.hide().empty();
            }
        });

        // Si ya hay credenciales, intentamos cargar los modelos automáticamente al abrir la página.
        if ($('#api_url').val() && $('#api_key').val()) {
            $('#get_models_button').trigger('click');
        }
    });
</script>
<?php
}