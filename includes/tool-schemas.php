<?php
/**
 * Manifiesto de Herramientas de Chat Bob
 * @package ChatBob
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Devuelve el array completo de esquemas de herramientas disponibles.
 *
 * @return array El array de esquemas de herramientas.
 */
function chat_bob_get_tool_schemas()
{
	return [
		chat_bob_schema_buscar_productos(),
		chat_bob_schema_obtener_historial_pedidos(),
		chat_bob_schema_buscar_cupones(),
		chat_bob_schema_crear_pedido(),
	];
}

/**
 * Define el esquema para la herramienta 'buscar_productos'.
 *
 * @return array
 */
function chat_bob_schema_buscar_productos()
{
	return [
		'type' => 'function',
		'function' => [
			'name' => 'buscar_productos',
			// --- MODIFICADO: Descripción mucho más directiva y enfática ---
			'description' => 'Herramienta principal y obligatoria para responder CUALQUIER pregunta sobre la disponibilidad, precio o detalles de un producto. SIEMPRE debes usar esta herramienta antes de afirmar que un producto no existe en la tienda. No respondas de memoria.',
			'parameters' => [
				'type' => 'object',
				'properties' => [
					'termino_busqueda' => [
						'type' => 'string',
						'description' => 'Las palabras clave o el nombre del producto que busca el cliente. Por ejemplo: "HBO", "suscripción", "ofertas".',
					],
					'categoria' => [
						'type' => 'string',
						'description' => 'La categoría específica del producto, si el cliente la menciona. Por ejemplo: "servicios digitales", "suscripciones".',
					],
				],
				'required' => ['termino_busqueda'],
			],
		],
	];
}

/**
 * Define el esquema para la herramienta 'obtener_historial_pedidos'.
 *
 * @return array
 */
function chat_bob_schema_obtener_historial_pedidos()
{
	return [
		'type' => 'function',
		'function' => [
			'name' => 'obtener_historial_pedidos',
			'description' => 'Consulta el historial de pedidos del cliente actual. Úsala si el cliente pregunta por el estado de un pedido, qué compró en el pasado o quiere detalles de una compra anterior. Puedes filtrar por ID o por estado.',
			'parameters' => [
				'type' => 'object',
				'properties' => [
					'id_pedido' => [
						'type' => 'integer',
						'description' => 'El número de pedido específico si el cliente lo proporciona.',
					],
					'estado' => [
						'type' => 'string',
						'description' => 'Filtra los pedidos por un estado específico. Valores posibles: "processing", "completed", "on-hold", "cancelled", "refunded", "failed".',
					],
				],
			],
		],
	];
}

/**
 * Define el esquema para la herramienta 'buscar_cupones'.
 *
 * @return array
 */
function chat_bob_schema_buscar_cupones()
{
	return [
		'type' => 'function',
		'function' => [
			'name' => 'buscar_cupones',
			'description' => 'Obtiene una lista de TODOS los cupones de descuento actualmente activos en la tienda. Úsala si el cliente pregunta por ofertas, promociones o descuentos disponibles. No requiere parámetros.',
			'parameters' => [
				'type' => 'object',
				// --- CORRECCIÓN: Usar un objeto vacío en lugar de un array vacío ---
				'properties' => new stdClass(),
			],
		],
	];
}

/**
 * Define el esquema para la herramienta 'crear_pedido'.
 *
 * @return array
 */
function chat_bob_schema_crear_pedido()
{
	return [
		'type' => 'function',
		'function' => [
			'name' => 'crear_pedido',
			'description' => 'Añade uno o más productos al carrito de compras del cliente. Devuelve una URL para que el cliente complete el pago. Úsala SOLO cuando el cliente confirme explícitamente que quiere comprar algo.',
			'parameters' => [
				'type' => 'object',
				'properties' => [
					'productos' => [
						'type' => 'array',
						'description' => 'Una lista de los productos que el cliente quiere comprar.',
						'items' => [
							'type' => 'object',
							'properties' => [
								'id_producto' => [
									'type' => 'integer',
									'description' => 'El ID del producto principal.',
								],
								'cantidad' => [
									'type' => 'integer',
									'description' => 'La cantidad de este producto a añadir.',
								],
								'id_variacion' => [
									'type' => 'integer',
									'description' => 'Si es un producto variable, el ID de la variación específica seleccionada. Opcional.',
								],
							],
							'required' => ['id_producto', 'cantidad'],
						],
					],
				],
				'required' => ['productos'],
			],
		],
	];
}