<?php
/**
 * Este archivo contiene los manejadores AJAX exclusivos para el área de administración de Chat Bob.
 *
 * @package ChatBob
 */

if (!defined('ABSPATH')) {
    exit;
}

// =====================================================================
// == MANEJADORES PARA LA PÁGINA DE HISTORIAL
// =====================================================================

function chat_bob_get_history_data()
{
    check_ajax_referer('chat_bob_history_nonce', '_wpnonce');
    if (!current_user_can('manage_options'))
        wp_send_json_error(['message' => 'No tienes permisos.']);

    // Recolección y sanitización de parámetros de DataTables
    $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
    $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
    $length = isset($_POST['length']) ? intval($_POST['length']) : 10;
    $search_value = isset($_POST['search']['value']) ? sanitize_text_field($_POST['search']['value']) : '';
    $order_column_index = isset($_POST['order'][0]['column']) ? intval($_POST['order'][0]['column']) : 3;
    $order_dir = isset($_POST['order'][0]['dir']) && strtolower($_POST['order'][0]['dir']) === 'asc' ? 'ASC' : 'DESC';
    $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : '';
    $end_date = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : '';

    $columns = [3 => 'date'];
    $orderby = $columns[$order_column_index] ?? 'date';

    $query_args = [
        'post_type' => 'chat_session',
        'post_status' => 'publish',
        'posts_per_page' => $length,
        'offset' => $start,
        'orderby' => $orderby,
        'order' => $order_dir,
    ];

    if (!empty($search_value))
        $query_args['s'] = $search_value;

    if (!empty($start_date) || !empty($end_date)) {
        $query_args['date_query'] = ['inclusive' => true];
        if (!empty($start_date))
            $query_args['date_query']['after'] = $start_date . ' 00:00:00';
        if (!empty($end_date))
            $query_args['date_query']['before'] = $end_date . ' 23:59:59';
    }

    $query = new WP_Query($query_args);
    $data = [];

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $session_id = get_the_ID();
            $author_id = get_the_author_meta('ID');
            $user_info = get_userdata($author_id);

            $user_display = [];
            if ($user_info) {
                $user_display[] = '<strong>' . esc_html($user_info->display_name) . '</strong> (ID: ' . esc_html($author_id) . ')';
                $user_display[] = '<a href="mailto:' . esc_attr($user_info->user_email) . '">' . esc_html($user_info->user_email) . '</a>';
                if ($phone = get_user_meta($author_id, 'billing_phone', true))
                    $user_display[] = 'Tel: ' . esc_html($phone);
                $user_display[] = 'Rol: ' . esc_html(implode(', ', $user_info->roles));
            } else {
                $user_display[] = '<em>' . __('Usuario Eliminado', 'chat-bob') . ' (ID: ' . esc_html($author_id) . ')</em>';
            }

            // *** MODIFICADO ***: El botón Eliminar ya no es un enlace de navegación.
            $actions = '<a href="#" class="button button-secondary view-conversation-btn" data-session-id="' . esc_attr($session_id) . '">' . __('Ver Chat', 'chat-bob') . '</a>';
            if ($user_info && function_exists('wc_get_orders'))
                $actions .= ' <a href="#" class="button button-secondary view-orders-btn" data-user-id="' . esc_attr($author_id) . '" data-user-name="' . esc_attr($user_info->display_name) . '">' . __('Ver Pedidos', 'chat-bob') . '</a>';
            $actions .= ' <a href="#" class="button-link-delete delete-chat-btn" data-session-id="' . esc_attr($session_id) . '">' . __('Eliminar', 'chat-bob') . '</a>';

            $data[] = [
                'bulk_select' => '<input type="checkbox" name="session_ids[]" value="' . esc_attr($session_id) . '">',
                'avatar' => get_avatar($author_id, 32, '', '', ['class' => 'user-avatar']),
                'user_info' => implode('<br>', $user_display),
                'date' => get_the_date('Y-m-d H:i:s'),
                'message_count' => count(get_post_meta($session_id, 'chat_message')),
                'actions' => $actions,
            ];
        }
    }
    wp_reset_postdata();

    $total_records = (new WP_Query(['post_type' => 'chat_session', 'post_status' => 'publish', 'posts_per_page' => -1]))->post_count;

    wp_send_json([
        "draw" => $draw,
        "recordsTotal" => $total_records,
        "recordsFiltered" => $query->found_posts,
        "data" => $data,
    ]);
}


/**
 * Manejador AJAX para obtener los detalles de una conversación.
 */
function chat_bob_get_conversation_details()
{
    check_ajax_referer('chat_bob_view_nonce', '_wpnonce');
    if (!current_user_can('manage_options'))
        wp_send_json_error(['message' => __('No tienes permisos.', 'chat-bob')]);
    $session_id = isset($_POST['session_id']) ? intval($_POST['session_id']) : 0;
    if ($session_id <= 0)
        wp_send_json_error(['message' => __('ID de sesión inválido.', 'chat-bob')]);
    $messages = get_post_meta($session_id, 'chat_message');
    if (empty($messages))
        wp_send_json_success(['html' => '<p>' . __('No hay mensajes en esta conversación.', 'chat-bob') . '</p>']);
    ob_start();
    foreach ($messages as $msg) {
        $sender_class = ($msg['sender'] === 'user') ? 'user-message' : 'assistant-message';
        $sender_name = ($msg['sender'] === 'user') ? __('Usuario', 'chat-bob') : __('Asistente', 'chat-bob');
        $timestamp = isset($msg['timestamp']) ? (int) $msg['timestamp'] : time();
        ?>
        <div class="message <?php echo esc_attr($sender_class); ?>"><?php echo wp_kses_post(nl2br($msg['content'])); ?><span
                class="message-meta"><?php echo esc_html($sender_name); ?> -
                <?php echo esc_html(date_i18n('j M Y, H:i', $timestamp)); ?></span></div><?php
    }
    $html = ob_get_clean();
    wp_send_json_success(['html' => $html]);
}

/**
 * Manejador AJAX para obtener los pedidos de un cliente.
 */
function chat_bob_get_user_orders()
{
    check_ajax_referer('chat_bob_orders_nonce', '_wpnonce');
    if (!current_user_can('manage_options') || !function_exists('wc_get_orders'))
        wp_send_json_error(['message' => __('No tienes permisos o WooCommerce no está activo.', 'chat-bob')]);
    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    if ($user_id <= 0)
        wp_send_json_error(['message' => __('ID de usuario inválido.', 'chat-bob')]);

    $orders = wc_get_orders(['customer_id' => $user_id, 'limit' => 20, 'orderby' => 'date', 'order' => 'DESC']);
    if (empty($orders))
        wp_send_json_success(['html' => '<p>' . __('Este cliente no tiene pedidos.', 'chat-bob') . '</p>']);

    ob_start();
    ?>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>Fecha</th>
                <th>Estado</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody><?php foreach ($orders as $order): ?>
                <tr>
                    <td><a href="<?php echo esc_url($order->get_edit_order_url()); ?>"
                            target="_blank">#<?php echo esc_html($order->get_id()); ?></a></td>
                    <td><?php echo esc_html($order->get_date_created()->date_i18n('Y-m-d H:i')); ?></td>
                    <td><?php echo esc_html(wc_get_order_status_name($order->get_status())); ?></td>
                    <td><?php echo wp_kses_post($order->get_formatted_order_total()); ?></td>
                </tr><?php endforeach; ?>
        </tbody>
    </table><?php
    $html = ob_get_clean();
    wp_send_json_success(['html' => $html]);
}

/**
 * Manejador AJAX unificado para eliminar una o varias conversaciones.
 */
function chat_bob_handle_delete_chats()
{
    check_ajax_referer('chat_bob_delete_nonce', '_wpnonce');
    if (!current_user_can('manage_options'))
        wp_send_json_error(['message' => __('No tienes permisos.', 'chat-bob')]);

    $sanitized_ids = [];
    if (isset($_POST['session_ids']) && is_array($_POST['session_ids'])) {
        $sanitized_ids = array_map('intval', $_POST['session_ids']);
    }
    elseif (isset($_POST['session_id'])) {
        $sanitized_ids[] = intval($_POST['session_id']);
    }

    $deleted_count = 0;
    if (empty($sanitized_ids))
        wp_send_json_error(['message' => __('No se seleccionaron conversaciones.', 'chat-bob')]);

    foreach ($sanitized_ids as $id) {
        if ($id > 0 && wp_delete_post($id, true)) {
            $deleted_count++;
        }
    }

    if ($deleted_count > 0) {
        wp_send_json_success(['message' => sprintf(_n('%d conversación eliminada correctamente.', '%d conversaciones eliminadas correctamente.', $deleted_count, 'chat-bob'), $deleted_count)]);
    } else {
        wp_send_json_error(['message' => __('No se pudo eliminar ninguna de las conversaciones seleccionadas.', 'chat-bob')]);
    }
}


// =====================================================================
// == MANEJADORES PARA LA PÁGINA DE CONFIGURACIÓN Y HERRAMIENTAS
// =====================================================================
function chat_bob_get_models()
{
    check_ajax_referer('chat_bob_get_models_nonce', '_wpnonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('No tienes permisos.', 'chat-bob')]);
    }

    $api_url = isset($_POST['api_url']) ? esc_url_raw(wp_unslash($_POST['api_url'])) : '';
    $api_key = isset($_POST['api_key']) ? sanitize_text_field(wp_unslash($_POST['api_key'])) : '';

    if (empty($api_url) || empty($api_key)) {
        wp_send_json_error(['message' => __('URL o API Key vacías.', 'chat-bob')]);
    }

    $response = wp_remote_get(
        trailingslashit($api_url) . 'v1/model/info',
        ['headers' => ['Authorization' => 'Bearer ' . $api_key], 'timeout' => 20]
    );

    if (is_wp_error($response)) {
        wp_send_json_error(['message' => __('Error de conexión: ', 'chat-bob') . $response->get_error_message()]);
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (isset($data['data'])) {
        wp_send_json_success($data['data']);
    } else {
        $error_message = $data['error']['message'] ?? __('Respuesta inválida de la API. Verifica el endpoint y la API Key.', 'chat-bob');
        wp_send_json_error(['message' => $error_message]);
    }
}

/**
 * Exporta la configuración actual como un archivo JSON.
 */
function chat_bob_export_settings()
{
    check_ajax_referer('chat_bob_export_nonce', '_wpnonce');
    if (!current_user_can('manage_options')) {
        wp_die(__('No tienes permisos.', 'chat-bob'));
    }

    $settings = get_option('chat_bob_settings', chat_bob_get_default_options());

    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename=chat-bob-settings-' . date('Y-m-d') . '.json');
    header('Pragma: no-cache');

    echo wp_json_encode($settings, JSON_PRETTY_PRINT);
    exit;
}

/**
 * Importa la configuración desde un archivo JSON.
 */
function chat_bob_import_settings()
{
    check_ajax_referer('chat_bob_import_nonce', '_wpnonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('No tienes permisos.', 'chat-bob')]);
    }

    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        wp_send_json_error(['message' => __('Error al subir el archivo.', 'chat-bob')]);
    }

    $file_path = $_FILES['file']['tmp_name'];
    $file_ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));

    if ($file_ext !== 'json') {
        wp_send_json_error(['message' => __('El archivo debe ser de tipo .json.', 'chat-bob')]);
    }

    $content = file_get_contents($file_path);
    $data = json_decode($content, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        wp_send_json_error(['message' => __('El archivo JSON es inválido.', 'chat-bob')]);
    }

    // Usar la misma función de sanitización para asegurar la integridad de los datos importados.
    $sanitized_data = chat_bob_sanitize_settings($data);
    update_option('chat_bob_settings', $sanitized_data);

    wp_send_json_success(['message' => __('Configuración importada correctamente.', 'chat-bob')]);
}

/**
 * Restablece todas las opciones del plugin a sus valores por defecto.
 */
function chat_bob_reset_settings()
{
    check_ajax_referer('chat_bob_reset_nonce', '_wpnonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('No tienes permisos.', 'chat-bob')]);
    }

    delete_option('chat_bob_settings');
    wp_send_json_success(['message' => __('Configuración restablecida correctamente.', 'chat-bob')]);
}

/**
 * Guarda la preferencia del usuario para no volver a mostrar la guía de onboarding.
 */
function chat_bob_dismiss_onboarding()
{
    check_ajax_referer('chat_bob_dismiss_nonce', '_wpnonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('No tienes permisos.', 'chat-bob')]);
    }

    update_user_meta(get_current_user_id(), 'chat_bob_hide_onboarding_notice', true);
    wp_send_json_success();
}