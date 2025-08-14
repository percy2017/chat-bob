<?php
/**
 * Este archivo gestiona la renderización de la página del historial de chats en el admin de WordPress.
 *
 * @package ChatBob
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Renderiza el contenido HTML de la página de historial de chats.
 */
function chat_bob_render_history_page()
{
	?>
	<div class="wrap">
		<h1 class="wp-heading-inline"><?php echo esc_html(get_admin_page_title()); ?></h1>

		<hr class="wp-header-end">

		<p><?php esc_html_e('Desde aquí puedes gestionar, filtrar y revisar todas las conversaciones de los usuarios.', 'chat-bob'); ?>
		</p>

		<!-- 1. Contenedor de Filtros y Acciones -->
		<div class="wp-filter">
			<!-- Acciones en Lote -->
			<div class="alignleft actions bulkactions">
				<label for="chat-bob-bulk-action"
					class="screen-reader-text"><?php esc_html_e('Seleccionar acción en lote', 'chat-bob'); ?></label>
				<select name="action" id="chat-bob-bulk-action">
					<option value="-1"><?php esc_html_e('Acciones en lote', 'chat-bob'); ?></option>
					<option value="delete"><?php esc_html_e('Eliminar', 'chat-bob'); ?></option>
				</select>
				<input type="button" id="chat-bob-bulk-apply" class="button action"
					value="<?php esc_attr_e('Aplicar', 'chat-bob'); ?>">
			</div>

			<!-- Filtros de Fecha -->
			<div class="alignleft actions">
				<input type="date" id="chat-bob-filter-start-date"
					placeholder="<?php esc_attr_e('Fecha inicio', 'chat-bob'); ?>">
				<input type="date" id="chat-bob-filter-end-date"
					placeholder="<?php esc_attr_e('Fecha fin', 'chat-bob'); ?>">
				<input type="button" id="chat-bob-filter-apply" class="button"
					value="<?php esc_attr_e('Filtrar', 'chat-bob'); ?>">
				<button type="button" id="chat-bob-filter-clear"
					class="button-link"><?php esc_html_e('Limpiar', 'chat-bob'); ?></button>
			</div>

			<!-- Búsqueda Principal -->
			<div class="search-form">
				<label for="chat-bob-search-input"
					class="screen-reader-text"><?php esc_html_e('Buscar conversaciones:', 'chat-bob'); ?></label>
				<input type="search" id="chat-bob-search-input" class="wp-filter-search"
					placeholder="<?php esc_attr_e('Buscar usuario...', 'chat-bob'); ?>">
			</div>

			<br class="clear">
		</div>

		<!-- 2. Esqueleto de la tabla para DataTables -->
		<table id="chat-history-table" class="wp-list-table widefat fixed striped" style="width:100%;">
			<thead>
				<tr>
					<td id="cb" class="manage-column column-cb check-column">
						<label class="screen-reader-text"
							for="cb-select-all-1"><?php esc_html_e('Seleccionar todo', 'chat-bob'); ?></label>
						<input id="cb-select-all-1" type="checkbox">
					</td>
					<th scope="col" style="width:5%;"><?php esc_html_e('Avatar', 'chat-bob'); ?></th>
					<th scope="col" style="width:25%;"><?php esc_html_e('Usuario', 'chat-bob'); ?></th>
					<th scope="col" style="width:15%;"><?php esc_html_e('Fecha', 'chat-bob'); ?></th>
					<th scope="col" style="width:10%;"><?php esc_html_e('Mensajes', 'chat-bob'); ?></th>
					<th scope="col" style="width:35%;"><?php esc_html_e('Acciones', 'chat-bob'); ?></th>
				</tr>
			</thead>
			<tbody></tbody>
		</table>
	</div>

	<!-- 3. Esqueleto del Modal -->
	<div id="chat-bob-modal" class="chat-bob-modal-overlay">
		<div class="chat-bob-modal-content">
			<div class="chat-bob-modal-header">
				<h2 id="chat-bob-modal-title"></h2>
				<button type="button" class="button-link chat-bob-modal-close">&times;</button>
			</div>
			<div id="chat-bob-modal-body" class="chat-bob-modal-body">
				<p class="loading-text"><?php esc_html_e('Cargando...', 'chat-bob'); ?></p>
			</div>
		</div>
	</div>
	<?php
}