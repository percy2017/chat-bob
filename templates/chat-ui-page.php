<?php
/**
 * Plantilla para la ventana del chat.
 * @package Chat_Bob
 */

if (!defined('ABSPATH')) {
	exit;
}

// Determina la visibilidad inicial de las vistas de login y chat.
$is_logged_in = is_user_logged_in();
$login_style = !$is_logged_in ? 'display: flex;' : 'display: none;';
$chat_style = $is_logged_in ? 'display: flex;' : 'display: none;';

// Obtener los valores pasados desde el orquestador, con valores por defecto seguros.
$header_title = isset($header_title) ? $header_title : __('Asistente Virtual', 'chat-bob');
$welcome_message = isset($welcome_message) ? $welcome_message : __('¿Cómo puedo ayudarte?', 'chat-bob');
$site_logo_url = function_exists('get_site_icon_url') && get_site_icon_url() ? get_site_icon_url(128) : '';

?>
<div id="chat-bob-window" class="chat-bob-window is-closed">

	<!-- Encabezado: Título y botón de cierre. -->
	<header class="chat-bob-header">
		<div class="chat-bob-header-avatar icon-chat-default"></div>
		<h2 class="chat-bob-header-title"><?php echo esc_html($header_title); ?></h2>
		<button id="chat-bob-close-button" class="chat-bob-header-button icon-close"
			aria-label="<?php esc_attr_e('Cerrar chat', 'chat-bob'); ?>"></button>
	</header>

	<!-- Contenido principal: Alterna entre la vista de login y la de chat. -->
	<div class="chat-bob-main-content">

		<!-- Vista de Identificación (para usuarios no logueados) -->
		<div class="chat-bob-login-wrapper" style="<?php echo esc_attr($login_style); ?>">
			<div class="chat-bob-login-content">

				<!-- El logo del sitio se mostrará aquí si está configurado en WordPress. -->
				<?php if (!empty($site_logo_url)): ?>
					<div id="chat-bob-site-logo" class="chat-bob-login-logo">
						<img src="<?php echo esc_url($site_logo_url); ?>"
							alt="<?php esc_attr_e('Logo del Sitio', 'chat-bob'); ?>" />
					</div>
				<?php endif; ?>

				<p><?php esc_html_e('Para darte una mejor asistencia, por favor, identifícate.', 'chat-bob'); ?></p>

				<form id="chat-bob-identify-form">

					<!-- Fila para Nombre y Apellido -->
					<div class="chat-bob-form-row">
						<div class="chat-bob-form-field">
							<label for="cb_first_name"><?php esc_html_e('Nombre', 'chat-bob'); ?></label>
							<input type="text" id="cb_first_name" name="cb_first_name" required>
						</div>
						<div class="chat-bob-form-field">
							<label for="cb_last_name"><?php esc_html_e('Apellido', 'chat-bob'); ?></label>
							<input type="text" id="cb_last_name" name="cb_last_name" required>
						</div>
					</div>

					<!-- Campo para Correo Electrónico -->
					<div class="chat-bob-form-field">
						<label for="cb_email"><?php esc_html_e('Correo Electrónico', 'chat-bob'); ?></label>
						<input type="email" id="cb_email" name="cb_email" required>
					</div>

					<!-- Campo para Teléfono (intl-tel-input se adjuntará aquí) -->
					<div class="chat-bob-form-field">
						<label for="cb_phone"><?php esc_html_e('Teléfono (Opcional)', 'chat-bob'); ?></label>
						<input type="tel" id="cb_phone" name="cb_phone">
					</div>

					<div id="chat-bob-login-error" class="chat-bob-form-error" style="display: none;"></div>
					<button type="submit"
						class="chat-bob-submit-button"><?php esc_html_e('Iniciar Chat', 'chat-bob'); ?></button>
				</form>
			</div>
		</div>

		<!-- Vista de Chat (para usuarios identificados) -->
		<div class="chat-bob-chat-wrapper" style="<?php echo esc_attr($chat_style); ?>">

			<!-- Aquí es donde JavaScript inyectará las burbujas de chat. -->
			<div id="chat-bob-messages" class="chat-bob-messages-container"></div>

			<!-- Elemento oculto que JS usará como plantilla para el mensaje de bienvenida. -->
			<div id="chat-bob-welcome-message" style="display: none;"><?php echo esc_html($welcome_message); ?></div>

			<!-- Pie de página: Área de entrada de texto y botones de acción. -->
			<footer class="chat-bob-footer">
				<div id="chat-bob-attachment-preview" class="chat-bob-attachment-preview" style="display: none;"></div>

				<div class="chat-bob-footer-controls">
					<button id="chat-bob-attach-button" class="chat-bob-icon-button icon-attach"
						aria-label="<?php esc_attr_e('Adjuntar archivo', 'chat-bob'); ?>"></button>
					<textarea id="chat-bob-input"
						placeholder="<?php esc_attr_e('Escribe tu mensaje...', 'chat-bob'); ?>" rows="1"></textarea>
					<button id="chat-bob-send-button" class="chat-bob-icon-button icon-send" disabled
						aria-label="<?php esc_attr_e('Enviar mensaje', 'chat-bob'); ?>"></button>
				</div>

				<input type="file" id="chat-bob-file-input" style="display: none;"
					accept="image/jpeg,image/png,image/gif,application/pdf,.doc,.docx">
			</footer>
		</div>
	</div>
</div>