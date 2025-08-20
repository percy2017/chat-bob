<?php
if (!defined('ABSPATH')) {
	exit;
}

require_once plugin_dir_path(__FILE__) . 'admin-page-settings.php';
require_once plugin_dir_path(__FILE__) . 'tool-schemas.php';
require_once plugin_dir_path(__FILE__) . 'tool-executor.php';

function chat_bob_log($message, $level = 'INFO')
{
	if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG === true) {
		$log_message = is_string($message) ? $message : print_r($message, true);
		error_log(sprintf('[Chat Bob][%s] %s', $level, $log_message));
	}
}

function chat_bob_process_placeholders($text)
{
	$contact_page_id = get_option('woocommerce_myaccount_page_id');
	$contact_url = $contact_page_id ? get_permalink($contact_page_id) : get_home_url();

	$placeholders = [
		'[store_name]' => get_bloginfo('name'),
		'[store_tagline]' => get_bloginfo('description'),
		'[store_url]' => home_url('/'),
		'[contact_url]' => $contact_url,
		'[admin_email]' => get_option('admin_email'),
	];

	return str_replace(array_keys($placeholders), array_values($placeholders), $text);
}

function chat_bob_handle_send_message()
{
	check_ajax_referer('chat-bob-nonce', 'nonce');
	if (!is_user_logged_in()) {
		wp_send_json_error(['message' => __('Debes iniciar sesión para chatear.', 'chat-bob')], 403);
	}

	$options = get_option('chat_bob_settings', chat_bob_get_default_options());
	$capabilities = json_decode(get_option('chat_bob_model_capabilities', '{}'), true);
	$current_model = $options['model'] ?? '';
	$model_caps = $capabilities[$current_model] ?? [];

	$user_message_text = isset($_POST['message']) ? sanitize_textarea_field(wp_unslash($_POST['message'])) : '';
	$session_id = chat_bob_get_session_post_id();

	$user_content_for_ai = $user_message_text;

	if (isset($_FILES['attachment']) && $_FILES['attachment']['size'] > 0) {
		$attachment_id = chat_bob_handle_file_upload($options['file_size_limit'] ?? 10);
		if (is_wp_error($attachment_id)) {
			wp_send_json_error(['message' => $attachment_id->get_error_message()], 400);
		}
		$mime_type = get_post_mime_type($attachment_id);
		$can_use_vision = !empty($model_caps['supports_vision']) && str_starts_with($mime_type, 'image/');
		$can_use_pdf = !empty($model_caps['supports_pdf_input']) && $mime_type === 'application/pdf';
		if ($can_use_vision || $can_use_pdf) {
			$file_path = get_attached_file($attachment_id);
			$base64_data = base64_encode(file_get_contents($file_path));
			$multimodal_content = [['type' => 'text', 'text' => $user_message_text]];
			if ($can_use_vision) {
				$multimodal_content[] = ['type' => 'image_url', 'image_url' => ['url' => "data:{$mime_type};base64,{$base64_data}"]];
			}
			$user_content_for_ai = $multimodal_content;
		} else {
			chat_bob_process_with_tika($attachment_id, $options);
			return;
		}
	}

	if (empty($user_message_text) && !is_array($user_content_for_ai)) {
		wp_send_json_error(['message' => __('El mensaje no puede estar vacío.', 'chat-bob')], 400);
	}

	chat_bob_log("--- INICIO DE PETICIÓN ---", "INFO");
	chat_bob_log("Mensaje de Usuario: " . $user_message_text, "DEBUG");

	chat_bob_run_conversation_loop($session_id, $options, $capabilities, $user_content_for_ai);
}

function chat_bob_run_conversation_loop($session_id, $options, $capabilities, $user_content_for_ai)
{
	add_post_meta($session_id, 'chat_message', ['sender' => 'user', 'content' => $user_content_for_ai, 'timestamp' => current_time('timestamp')]);

	// 1. PREPARAR EL HISTORIAL Y EL PROMPT DEL SISTEMA (el resto del código es casi igual)
	$history_meta = get_post_meta($session_id, 'chat_message');
	$messages_for_ai = [];
	$limit = -1 * abs(intval($options['history_limit'] ?? 10));
	foreach (array_slice($history_meta, $limit) as $msg) {
		$messages_for_ai[] = ['role' => ($msg['sender'] === 'user') ? 'user' : 'assistant', 'content' => $msg['content']];
	}
	$user_info_block = '';
	$user_id = get_post_field('post_author', $session_id);
	if ($user_id && ($user_data = get_userdata($user_id))) {
		$phone = get_user_meta($user_id, 'billing_phone', true);
		$user_info_block .= "\n\n--- INFORMACIÓN DEL USUARIO ---\nNombre: " . esc_html($user_data->display_name) . "\nEmail: " . esc_html($user_data->user_email);
		if ($phone)
			$user_info_block .= "\nTeléfono: " . esc_html($phone);
		$user_info_block .= "\n--- FIN INFORMACIÓN DEL USUARIO ---";
	}
	$raw_prompt = $options['prompt'] ?? 'Eres un asistente.';
	$processed_prompt = chat_bob_process_placeholders($raw_prompt);
	$system_prompt_text = "La fecha y hora actual es: " . date_i18n(get_option('date_format') . ' ' . get_option('time_format')) . $user_info_block . "\n\n" . $processed_prompt;
	$saved_docs = get_post_meta($session_id, 'chat_bob_document_context');
	if (!empty($saved_docs)) {
		$system_prompt_text .= "\n\n--- CONOCIMIENTO ADICIONAL DE ARCHIVOS ---\n";
		foreach ($saved_docs as $doc) {
			$system_prompt_text .= sprintf("\nCONTENIDO DE '%s':\n%s\n", $doc['original_filename'], $doc['extracted_text']);
		}
		$system_prompt_text .= "--- FIN DEL CONOCIMIENTO ADICIONAL ---";
	}
	array_unshift($messages_for_ai, ['role' => 'system', 'content' => $system_prompt_text]);

	// 2. PRIMERA LLAMADA A LA API
	$api_body = ['model' => $options['model'] ?? '', 'messages' => $messages_for_ai];
	$current_model = $options['model'] ?? '';
	$model_caps = $capabilities[$current_model] ?? [];
	if (!empty($model_caps['supports_function_calling'])) {
		$api_body['tools'] = chat_bob_get_tool_schemas();
	}

	chat_bob_log('--- PAYLOAD ENVIADO (1ª LLAMADA) ---', 'DEBUG');
	chat_bob_log(wp_json_encode($api_body, JSON_PRETTY_PRINT), 'DEBUG');

	$response = wp_remote_post(esc_url_raw(trailingslashit($options['api_url'] ?? '') . 'chat/completions'), [
		'method' => 'POST',
		'headers' => ['Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . ($options['api_key'] ?? '')],
		'body' => wp_json_encode($api_body),
		'timeout' => 180,
	]);


	if (is_wp_error($response)) {
		delete_post_meta($session_id, 'chat_message', ['sender' => 'user', 'content' => $user_content_for_ai]);
		wp_send_json_error(['message' => $response->get_error_message()], 500);
	}

	$data = json_decode(wp_remote_retrieve_body($response), true);
	$ai_response_message = $data['choices'][0]['message'] ?? null;

	chat_bob_log('--- RESPUESTA RECIBIDA (1ª LLAMADA) ---', 'DEBUG');
	chat_bob_log($data, 'DEBUG');

	if (!$ai_response_message) {
		delete_post_meta($session_id, 'chat_message', ['sender' => 'user', 'content' => $user_content_for_ai]);
		wp_send_json_error(['message' => $data['error']['message'] ?? 'Respuesta inválida.'], 500);
	}

	// 3. DECIDIR EL SIGUIENTE PASO
	if (empty($ai_response_message['tool_calls'])) {
		$bot_reply = $ai_response_message['content'];
		add_post_meta($session_id, 'chat_message', ['sender' => 'bot', 'content' => $bot_reply, 'timestamp' => current_time('timestamp')]);
		wp_send_json_success(['reply' => $bot_reply]);
		return;
	}

	// CASO B: El LLM usó una herramienta.
	$messages_for_ai[] = $ai_response_message;
	foreach ($ai_response_message['tool_calls'] as $tool_call) {
		$tool_name = $tool_call['function']['name'];
		$tool_args = json_decode($tool_call['function']['arguments'], true);
		chat_bob_log("Ejecutando la herramienta '{$tool_name}'...", 'INFO');
		$tool_result = chat_bob_execute_tool($tool_name, $tool_args);
		chat_bob_log("Resultado de la herramienta: " . wp_json_encode($tool_result), 'DEBUG');
		$messages_for_ai[] = ['role' => 'tool', 'tool_call_id' => $tool_call['id'], 'name' => $tool_name, 'content' => wp_json_encode($tool_result, JSON_UNESCAPED_UNICODE)];
	}

	// 4. SEGUNDA LLAMADA A LA API
	$final_api_body = ['model' => $options['model'] ?? '', 'messages' => $messages_for_ai];

	chat_bob_log('--- PAYLOAD ENVIADO (2ª LLAMADA) ---', 'DEBUG');
	chat_bob_log(wp_json_encode($final_api_body, JSON_PRETTY_PRINT), 'DEBUG');

	$final_response = wp_remote_post(esc_url_raw(trailingslashit($options['api_url'] ?? '') . 'v1/chat/completions'), [
		'method' => 'POST',
		'headers' => ['Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . ($options['api_key'] ?? '')],
		'body' => wp_json_encode($final_api_body),
		'timeout' => 180,
	]);

	if (is_wp_error($final_response)) {
		delete_post_meta($session_id, 'chat_message', ['sender' => 'user', 'content' => $user_content_for_ai]);
		wp_send_json_error(['message' => $final_response->get_error_message()], 500);
	}

	
	$final_data = json_decode(wp_remote_retrieve_body($final_response), true);
	$final_bot_reply = $final_data['choices'][0]['message']['content'] ?? __('Lo siento, ocurrió un error...', 'chat-bob');

	chat_bob_log('--- RESPUESTA RECIBIDA (2ª LLAMADA - FINAL) ---', 'DEBUG');
	chat_bob_log($final_data, 'DEBUG');
	
	// 5. ENVIAR Y GUARDAR RESPUESTA FINAL
	add_post_meta($session_id, 'chat_message', ['sender' => 'bot', 'content' => $final_bot_reply, 'timestamp' => current_time('timestamp')]);
	wp_send_json_success(['reply' => $final_bot_reply]);
}

function chat_bob_handle_file_upload($size_limit_mb)
{
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	if ($_FILES['attachment']['size'] > $size_limit_mb * 1024 * 1024) {
		return new WP_Error('file_too_large', sprintf(__('El archivo es demasiado grande. El límite es de %d MB.', 'chat-bob'), $size_limit_mb));
	}
	$upload_result = wp_handle_upload($_FILES['attachment'], ['test_form' => false]);
	if (!empty($upload_result['error']))
		return new WP_Error('upload_error', $upload_result['error']);
	$attachment_id = wp_insert_attachment(['guid' => $upload_result['url'], 'post_mime_type' => $upload_result['type'], 'post_title' => preg_replace('/\.[^.]+$/', '', basename($upload_result['file'])), 'post_content' => '', 'post_status' => 'inherit'], $upload_result['file']);
	wp_update_attachment_metadata($attachment_id, wp_generate_attachment_metadata($attachment_id, $upload_result['file']));
	return $attachment_id;
}

function chat_bob_process_with_tika($attachment_id, $options)
{
	$tika_url = $options['tika_url'] ?? '';
	if (empty($tika_url)) {
		wp_send_json_error(['message' => 'Este modelo no puede procesar el archivo y no hay un servicio de lectura alternativo configurado.'], 400);
	}
	$response = wp_remote_post($tika_url, ['method' => 'PUT', 'body' => file_get_contents(wp_get_attachment_url($attachment_id)), 'headers' => ['Accept' => 'text/plain'], 'timeout' => 120]);
	if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
		wp_send_json_error(['message' => 'Error al contactar con el servicio de lectura de documentos.'], 500);
	}
	$extracted_text = wp_remote_retrieve_body($response);
	$session_id = chat_bob_get_session_post_id();
	add_post_meta($session_id, 'chat_bob_document_context', ['original_filename' => basename(get_attached_file($attachment_id)), 'attachment_id' => $attachment_id, 'extracted_text' => sanitize_textarea_field($extracted_text), 'timestamp' => current_time('timestamp')]);
	$confirmation_message = sprintf('✅ He leído y guardado el contenido de "%s". Ahora puedes hacerme preguntas sobre él.', basename(get_attached_file($attachment_id)));
	add_post_meta($session_id, 'chat_message', ['sender' => 'bot', 'content' => $confirmation_message, 'timestamp' => current_time('timestamp')]);
	wp_send_json_success(['reply' => $confirmation_message, 'action' => 'context_saved']);
}

function chat_bob_get_session_post_id()
{
	$user_id = get_current_user_id();
	if ($user_id === 0)
		return 0;
	$session_id = chat_bob_get_existing_session_id($user_id);
	if ($session_id)
		return $session_id;
	return wp_insert_post(['post_type' => 'chat_session', 'post_title' => 'Chat con ' . get_userdata($user_id)->display_name, 'post_status' => 'publish', 'post_author' => $user_id]);
}

function chat_bob_get_existing_session_id($user_id)
{
	if ($user_id === 0)
		return 0;
	$existing_posts = get_posts(['post_type' => 'chat_session', 'author' => $user_id, 'posts_per_page' => 1, 'post_status' => 'publish', 'orderby' => 'ID', 'order' => 'DESC']);
	return !empty($existing_posts) ? $existing_posts[0]->ID : 0;
}

function chat_bob_handle_load_history()
{
	check_ajax_referer('chat-bob-nonce', 'nonce');
	if (!is_user_logged_in())
		wp_send_json_error(['message' => 'Debes iniciar sesión.'], 403);

	$session_id = chat_bob_get_existing_session_id(get_current_user_id());
	if (!$session_id) {
		wp_send_json_success([]);
		return;
	}

	$history_meta = get_post_meta($session_id, 'chat_message');
	$formatted_history = [];
	foreach ($history_meta as $msg) {
		$content_to_show = is_array($msg['content']) ? ($msg['content'][0]['text'] ?? '') : $msg['content'];
		$formatted_history[] = ['sender' => $msg['sender'], 'content' => $content_to_show];
	}
	wp_send_json_success($formatted_history);
}

function chat_bob_handle_identify_user()
{
	check_ajax_referer('chat-bob-nonce', 'nonce');

	$email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
	$first_name = isset($_POST['first_name']) ? sanitize_text_field($_POST['first_name']) : '';
	$last_name = isset($_POST['last_name']) ? sanitize_text_field($_POST['last_name']) : '';
	$phone = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';

	if (!is_email($email) || empty($first_name) || empty($last_name) || empty($phone)) {
		wp_send_json_error(['message' => __('Por favor, completa todos los campos requeridos.', 'chat-bob')], 400);
	}

	$user = get_user_by('email', $email);
	if (!$user) {
		$username = sanitize_user(explode('@', $email)[0] . '_' . wp_generate_password(4, false), true);
		$password = wp_generate_password(12, true, true);
		$user_id = wp_create_user($username, $password, $email);
		if (is_wp_error($user_id)) {
			wp_send_json_error(['message' => $user_id->get_error_message()], 500);
		}
		if (function_exists('wp_new_user_notification')) {
			wp_new_user_notification($user_id, null, 'both');
		}
	} else {
		$user_id = $user->ID;
	}

	wp_update_user(['ID' => $user_id, 'first_name' => $first_name, 'last_name' => $last_name, 'display_name' => trim($first_name . ' ' . $last_name)]);
	update_user_meta($user_id, 'billing_phone', $phone);

	wp_set_current_user($user_id);
	wp_set_auth_cookie($user_id, true, is_ssl());

	$session_id = chat_bob_get_session_post_id();
	$history_meta = get_post_meta($session_id, 'chat_message');
	$formatted_history = [];
	foreach ($history_meta as $msg) {
		$content_to_show = is_array($msg['content']) ? ($msg['content'][0]['text'] ?? '') : $msg['content'];
		$formatted_history[] = ['sender' => $msg['sender'], 'content' => $content_to_show];
	}

	wp_send_json_success(['message' => 'Login exitoso.', 'history' => $formatted_history]);
}