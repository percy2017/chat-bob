<?php
// Evitar el acceso directo al archivo.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registra el Custom Post Type (CPT) para las sesiones de chat.
 * Cada conversación será un post de este tipo.
 */
function chat_bob_register_cpt() {
    $labels = [
        'name'               => _x( 'Sesiones de Chat', 'post type general name', 'chat-bob' ),
        'singular_name'      => _x( 'Sesión de Chat', 'post type singular name', 'chat-bob' ),
        'menu_name'          => _x( 'Sesiones de Chat', 'admin menu', 'chat-bob' ),
        'name_admin_bar'     => _x( 'Sesión de Chat', 'add new on admin bar', 'chat-bob' ),
        'add_new'            => _x( 'Añadir Nueva', 'sesión de chat', 'chat-bob' ),
        'add_new_item'       => __( 'Añadir Nueva Sesión', 'chat-bob' ),
        'new_item'           => __( 'Nueva Sesión', 'chat-bob' ),
        'edit_item'          => __( 'Editar Sesión', 'chat-bob' ),
        'view_item'          => __( 'Ver Sesión', 'chat-bob' ),
        'all_items'          => __( 'Todas las Sesiones', 'chat-bob' ),
        'search_items'       => __( 'Buscar Sesiones', 'chat-bob' ),
        'not_found'          => __( 'No se encontraron sesiones.', 'chat-bob' ),
        'not_found_in_trash' => __( 'No se encontraron sesiones en la papelera.', 'chat-bob' )
    ];

    $args = [
        'labels'             => $labels,
        'public'             => false,  // No será visible en el frontend del sitio.
        'publicly_queryable' => false,  // No se puede consultar directamente por URL.
        'show_ui'            => true,   // Mostrar en el panel de administración.
        'show_in_menu'       => 'chat-bob-history', // Se mostrará como subpágina de nuestro historial.
        'query_var'          => false,
        'rewrite'            => false,
        'capability_type'    => 'post',
        'has_archive'        => false,
        'hierarchical'       => false,
        'menu_position'      => null,
        'supports'           => [ 'title', 'author' ], // Soportará título y autor.
        'show_in_rest'       => false, // No disponible en la REST API por ahora.
    ];

    register_post_type( 'chat_session', $args );
}