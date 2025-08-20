<?php
if (!defined('ABSPATH')) {
	exit;
}

function chat_bob_get_tool_schemas()
{
	return [
		chat_bob_schema_buscar_productos(),
		chat_bob_schema_obtener_historial_pedidos(),
		chat_bob_schema_buscar_cupones(),
		chat_bob_schema_crear_pedido(),
	];
}

function chat_bob_schema_buscar_productos()
{
	return [
		'type' => 'function',
		'function' => [
			'name' => 'buscar_productos',
			'description' => 'Busca en el catalogo de productos de la tienda por nombre o categoría. Usala cuando un cliente pregunte por productos específicos, disponibilidad o precios.',
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
					'limit' => [
						'type' => 'integer',
						'description' => 'Número máximo de productos a devolver. El valor por defecto es 5. Usa -1 para devolver TODOS los productos disponibles.'
					]
				],
				'required' => ['termino_busqueda'],
			],
		],
	];
}

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
					'limit' => [
						'type' => 'integer',
						'description' => 'Número máximo de pedidos a devolver. El valor por defecto es 5. Usa -1 para devolver TODOS los pedidos.'
					]
				],
			],
		],
	];
}

function chat_bob_schema_buscar_cupones()
{
	return [
		'type' => 'function',
		'function' => [
			'name' => 'buscar_cupones',
			'description' => 'Obtiene una lista de TODOS los cupones de descuento actualmente activos en la tienda. Úsala si el cliente pregunta por ofertas, promociones o descuentos disponibles. No requiere parámetros.',
			'parameters' => [
				'type' => 'object',
				'properties' => new stdClass(),
			],
		],
	];
}

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