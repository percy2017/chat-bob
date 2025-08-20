<?php
if (!defined('ABSPATH')) {
	exit;
}

function chat_bob_execute_tool($tool_name, $args)
{
	switch ($tool_name) {
		case 'buscar_productos':
			return chat_bob_execute_buscar_productos($args);
		case 'obtener_historial_pedidos':
			return chat_bob_execute_obtener_historial_pedidos($args);
		case 'buscar_cupones':
			return chat_bob_execute_buscar_cupones();
		case 'crear_pedido':
			return chat_bob_execute_crear_pedido($args);
		default:
			return ['error' => 'Herramienta desconocida: ' . $tool_name];
	}
}

function chat_bob_execute_buscar_productos($args)
{
	if (!function_exists('wc_get_products')) {
		return ['error' => 'WooCommerce no está activo.'];
	}

	$query_args = [
		'limit' => isset($args['limit']) ? intval($args['limit']) : 5,
		'status' => 'publish',
	];

	if (!empty($args['termino_busqueda'])) {
		$query_args['s'] = sanitize_text_field($args['termino_busqueda']);
	}
	if (!empty($args['categoria'])) {
		$query_args['category'] = [sanitize_text_field($args['categoria'])];
	}

	$products = wc_get_products($query_args);
	$formatted_products = [];

	foreach ($products as $product) {
		$product_data = [
			'id' => $product->get_id(),
			'nombre' => $product->get_name(),
			'tipo' => $product->get_type(),
			'descripcion' => wp_strip_all_tags($product->get_short_description()),
			'stock_estado' => $product->get_stock_status(),
			'url' => $product->get_permalink(),
			'imagen_url' => wp_get_attachment_image_url($product->get_image_id(), 'medium'),
		];

		switch ($product->get_type()) {
			case 'simple':
				$product_data['precio'] = (float) $product->get_price();
				break;
			case 'variable':
				$product_data['precio_minimo'] = (float) $product->get_variation_price('min');
				$product_data['precio_maximo'] = (float) $product->get_variation_price('max');
				
				$product_data['atributos_disponibles'] = $product->get_variation_attributes();

				$variations_data = [];
				foreach ($product->get_children() as $variation_id) {
					$variation = wc_get_product($variation_id);
					if (!$variation)
						continue;
					$variations_data[] = [
						'id_variacion' => $variation->get_id(),
						'atributos' => $variation->get_variation_attributes(),
						'precio' => (float) $variation->get_price(),
						'stock_estado' => $variation->get_stock_status(),
					];
				}
				$product_data['variaciones'] = $variations_data;
				break;
		}
		$formatted_products[] = $product_data;
	}

	return $formatted_products;
}
function chat_bob_execute_obtener_historial_pedidos($args)
{
	$user_id = get_current_user_id();
	if ($user_id === 0 || !function_exists('wc_get_orders')) {
		return ['error' => 'Usuario no conectado o WooCommerce no está activo.'];
	}

	$query_args = [
		'customer_id' => $user_id,
		'limit' => isset($args['limit']) ? intval($args['limit']) : 5,
		'orderby' => 'date',
		'order' => 'DESC',
	];

	if (!empty($args['id_pedido'])) {
		$query_args['post__in'] = [intval($args['id_pedido'])];
	}
	if (!empty($args['estado'])) {
		$query_args['status'] = sanitize_text_field($args['estado']);
	}

	$orders = wc_get_orders($query_args);
	$formatted_orders = [];

	foreach ($orders as $order) {
		$items_data = [];
		foreach ($order->get_items() as $item) {
			$item_data = [
				'nombre_producto' => $item->get_name(),
				'cantidad' => $item->get_quantity(),
				'precio_unitario' => (float) $order->get_item_total($item, false, false),
			];
			$variation_attributes = [];
			foreach ($item->get_meta_data() as $meta) {
				if (taxonomy_exists($meta->key)) {
					$variation_attributes[] = [
						'nombre' => wc_attribute_label($meta->key),
						'valor' => $meta->value,
					];
				}
			}
			if (!empty($variation_attributes)) {
				$item_data['atributos_seleccionados'] = $variation_attributes;
			}
			$items_data[] = $item_data;
		}

		$formatted_orders[] = [
			'id_pedido' => $order->get_id(),
			'fecha_creacion' => $order->get_date_created()->format('c'),
			'estado' => $order->get_status(),
			'total_pedido' => (float) $order->get_total(),
			'moneda' => $order->get_currency(),
			'url_pago' => $order->needs_payment() ? $order->get_checkout_payment_url() : null,
			'items' => $items_data,
		];
	}

	return $formatted_orders;
}

function chat_bob_execute_buscar_cupones()
{
	if (!function_exists('wc_get_coupon_id_by_code')) {
		return ['error' => 'WooCommerce no está activo.'];
	}

	$query_args = [
		'post_type' => 'shop_coupon',
		'post_status' => 'publish',
		'posts_per_page' => -1,
	];
	$coupons = get_posts($query_args);
	$formatted_coupons = [];

	foreach ($coupons as $coupon_post) {
		$coupon = new WC_Coupon($coupon_post->ID);
		if ($coupon->get_date_expires() && $coupon->get_date_expires()->getTimestamp() < time()) {
			continue; // Omitir cupones expirados.
		}
		$formatted_coupons[] = [
			'codigo' => $coupon->get_code(),
			'descripcion' => $coupon->get_description(),
			'tipo_descuento' => $coupon->get_discount_type(),
			'monto' => (float) $coupon->get_amount(),
			'fecha_expiracion' => $coupon->get_date_expires() ? $coupon->get_date_expires()->format('Y-m-d') : null,
		];
	}

	return $formatted_coupons;
}
function chat_bob_execute_crear_pedido($args)
{
	if (get_current_user_id() === 0 || !function_exists('WC') || !isset($args['productos'])) {
		return ['error' => 'Petición inválida.'];
	}

	// Asegurar que tenemos un carrito y una sesión.
	if (is_null(WC()->session)) {
		WC()->session = new WC_Session_Handler();
		WC()->session->init();
	}
	if (is_null(WC()->customer)) {
		WC()->customer = new WC_Customer(get_current_user_id(), true);
	}
	if (is_null(WC()->cart)) {
		wc_load_cart();
	}

	WC()->cart->empty_cart(); // Limpiar el carrito para empezar de cero.

	foreach ($args['productos'] as $product_to_add) {
		$product_id = intval($product_to_add['id_producto']);
		$quantity = intval($product_to_add['cantidad']);
		$variation_id = isset($product_to_add['id_variacion']) ? intval($product_to_add['id_variacion']) : 0;
		WC()->cart->add_to_cart($product_id, $quantity, $variation_id);
	}

	if (WC()->cart->is_empty()) {
		return ['status' => 'error', 'message' => 'No se pudo añadir ningún producto al carrito.'];
	}

	return [
		'status' => 'success',
		'message' => 'Productos añadidos al carrito correctamente.',
		'checkout_url' => wc_get_checkout_url(),
	];
}