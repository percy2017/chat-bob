<?php
/**
 * Plugin Name:       Chat Bob
 * Description:       Un agente de IA proactivo con herramientas, soporte para archivos y una arquitectura resiliente.
 * Version:           1.0
 * Author:            Ing. Percy AlvarezC
 * Author URI:        https://percyalvarez.com/chat-bob
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       chat-bob
 * Domain Path:       /languages
 */

if (!defined('ABSPATH')) {
	exit;
}

final class Chat_Bob_Orchestrator
{	
	private $version = '1.0';
	public function __construct()
	{
		$this->load_dependencies();
		$this->register_hooks();
	}
	private function load_dependencies()
	{
		$plugin_path = plugin_dir_path(__FILE__);
		require_once $plugin_path . 'includes/admin-page-settings.php';
		require_once $plugin_path . 'includes/post-types.php';
		require_once $plugin_path . 'includes/admin-page-history.php';
		require_once $plugin_path . 'includes/ajax-handlers.php';
		require_once $plugin_path . 'includes/admin-ajax-handlers.php';
	}
	private function register_hooks()
	{
		add_action('init', 'chat_bob_register_cpt');
		add_action('admin_menu', [$this, 'create_admin_menu']);
		add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
		add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
		add_action('wp_footer', [$this, 'render_chat_ui_templates']);
		$this->register_ajax_hooks();
		$this->register_admin_ajax_hooks();
	}
	public function create_admin_menu()
	{
		add_menu_page('Chat Bob', 'Chat Bob', 'manage_options', 'chat-bob-settings', 'chat_bob_render_settings_page', 'dashicons-format-chat', 25);
		add_submenu_page('chat-bob-settings', 'Configuración', 'Configuración', 'manage_options', 'chat-bob-settings', 'chat_bob_render_settings_page');
		add_submenu_page('chat-bob-settings', 'Historial de Chats', 'Historial', 'manage_options', 'chat-bob-history', 'chat_bob_render_history_page');
	}

	public function enqueue_frontend_assets()
	{
		$options = get_option('chat_bob_settings', []);
		$defaults = function_exists('chat_bob_get_default_options') ? chat_bob_get_default_options() : [];
		$config = wp_parse_args($options, $defaults);

		$localized_data = [
			'ajax_url' => admin_url('admin-ajax.php'),
			'nonce' => wp_create_nonce('chat-bob-nonce'),
			'settings' => ['position' => $config['position'] ?? 'bottom-right']
		];

		wp_enqueue_style('intl-tel-input-css', 'https://cdn.jsdelivr.net/npm/intl-tel-input@19.2.16/build/css/intlTelInput.css', [], '19.2.16');
		wp_enqueue_script('intl-tel-input-js', 'https://cdn.jsdelivr.net/npm/intl-tel-input@19.2.16/build/js/intlTelInput.min.js', [], '19.2.16', true);
		wp_enqueue_style('chat-bob-style', plugin_dir_url(__FILE__) . 'assets/style.css', ['intl-tel-input-css'], $this->version);
		wp_enqueue_script('marked-js', 'https://cdn.jsdelivr.net/npm/marked/marked.min.js', [], '5.0.1', true);
		
		$custom_css = ":root { --chat-bob-accent-color: " . esc_attr($config['primary_color']) . "; }";
		wp_add_inline_style('chat-bob-style', $custom_css);
		wp_enqueue_script('chat-bob-script', plugin_dir_url(__FILE__) . 'assets/script.js', ['jquery', 'intl-tel-input-js'], $this->version, true);
		wp_localize_script('chat-bob-script', 'chat_bob_data', $localized_data);
	}
	public function render_chat_ui_templates()
	{
		$options = get_option('chat_bob_settings', []);
		$defaults = function_exists('chat_bob_get_default_options') ? chat_bob_get_default_options() : [];
		$config = wp_parse_args($options, $defaults);
		$header_title = $config['chat_title'] ?? 'Asistente Virtual';
		$welcome_message = $config['welcome_message'] ?? '¿Cómo puedo ayudarte?';
		include plugin_dir_path(__FILE__) . 'templates/chat-ui-page.php';
		include plugin_dir_path(__FILE__) . 'templates/chat-ui-button.php';
	}
	public function enqueue_admin_assets($hook_suffix)
	{
		$settings_page_hook = 'toplevel_page_chat-bob-settings';
		$history_page_hook = 'chat-bob_page_chat-bob-history';
		if ($settings_page_hook === $hook_suffix) {
			wp_enqueue_style('wp-color-picker');
			wp_enqueue_script('wp-color-picker');
			wp_enqueue_script('sweetalert2', 'https://cdn.jsdelivr.net/npm/sweetalert2@11', [], null, true);
		}
		if ($history_page_hook === $hook_suffix) {
			wp_enqueue_style('chat-bob-admin-style', plugin_dir_url(__FILE__) . 'assets/admin-style.css', [], $this->version);
			wp_enqueue_style('datatables-css', 'https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css');
			wp_enqueue_script('sweetalert2', 'https://cdn.jsdelivr.net/npm/sweetalert2@11', [], null, true);
			wp_enqueue_script('datatables-js', 'https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js', ['jquery'], null, true);
			wp_enqueue_script('chat-bob-admin-script', plugin_dir_url(__FILE__) . 'assets/admin-script.js', ['jquery', 'sweetalert2', 'datatables-js'], $this->version, true);
			wp_localize_script('chat-bob-admin-script', 'chat_bob_admin_ajax', [
				'ajax_url' => admin_url('admin-ajax.php'),
				'history_nonce' => wp_create_nonce('chat_bob_history_nonce'),
				'view_nonce' => wp_create_nonce('chat_bob_view_nonce'),
				'orders_nonce' => wp_create_nonce('chat_bob_orders_nonce'),
				'delete_nonce' => wp_create_nonce('chat_bob_delete_nonce'),
				'i18n' => [
					'loading' => __('Cargando...', 'chat-bob'),
					'error' => __('Ocurrió un error.', 'chat-bob'),
					'confirm_delete_title' => __('¿Estás seguro?', 'chat-bob'),
					'confirm_delete_text' => __('Esta acción no se puede deshacer.', 'chat-bob'),
					'confirm_button' => __('Sí, eliminar', 'chat-bob'),
					'cancel_button' => __('Cancelar', 'chat-bob'),
					'bulk_confirm_text' => __('Vas a eliminar las conversaciones seleccionadas.', 'chat-bob'),
					'no_selection' => __('Por favor, selecciona al menos una conversación.', 'chat-bob')
				]
			]);
		}
	}
	private function register_ajax_hooks()
	{
		$ajax_actions = ['send_message', 'load_history', 'identify_user'];
		foreach ($ajax_actions as $action) {
			add_action("wp_ajax_chat_bob_{$action}", "chat_bob_handle_{$action}");
			if ('identify_user' === $action || 'send_message' === $action) {
				add_action("wp_ajax_nopriv_chat_bob_{$action}", "chat_bob_handle_{$action}");
			}
		}
	}
	private function register_admin_ajax_hooks()
	{
		$admin_actions = ['get_history_data', 'get_conversation_details', 'get_user_orders', 'handle_delete_chats', 'get_models', 'export_settings', 'import_settings', 'reset_settings', 'dismiss_onboarding'];
		foreach ($admin_actions as $action) {
			add_action("wp_ajax_chat_bob_{$action}", "chat_bob_{$action}");
		}
	}
}

new Chat_Bob_Orchestrator();