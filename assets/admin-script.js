/**
 * Script para la página de historial de chats de Chat Bob.
 */

jQuery(document).ready(function ($) {
  if (typeof chat_bob_admin_ajax === "undefined") {
    console.error(
      "Chat Bob: Faltan las variables de AJAX. Revisa `wp_localize_script`."
    );
    return;
  }

  const i18n = chat_bob_admin_ajax.i18n;
  let historyTable;

  // --- Inicialización de DataTables ---
  historyTable = $("#chat-history-table").DataTable({
    processing: true,
    serverSide: true,
    ajax: {
      url: chat_bob_admin_ajax.ajax_url,
      type: "POST",
      data: function (d) {
        d.action = "chat_bob_get_history_data";
        d._wpnonce = chat_bob_admin_ajax.history_nonce;
        d.start_date = $("#chat-bob-filter-start-date").val();
        d.end_date = $("#chat-bob-filter-end-date").val();
      },
    },
    columns: [
      { data: "bulk_select", orderable: false, searchable: false },
      { data: "avatar", orderable: false, searchable: false },
      { data: "user_info", searchable: true },
      { data: "date", searchable: false },
      { data: "message_count", searchable: false },
      { data: "actions", orderable: false, searchable: false },
    ],
    language: { url: "//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json" },
    order: [[3, "desc"]],
    dom: '<"top"l>rt<"bottom"ip><"clear">',
  });

  // --- Manejo de Filtros y Búsqueda ---
  $("#chat-bob-filter-apply").on("click", () => historyTable.ajax.reload());
  $("#chat-bob-filter-clear").on("click", () => {
    $("#chat-bob-filter-start-date, #chat-bob-filter-end-date").val("");
    historyTable.ajax.reload();
  });
  $("#chat-bob-search-input").on("keyup", function () {
    historyTable.search($(this).val()).draw();
  });

  // --- Delegación de Eventos para Acciones en la Tabla ---
  $("#chat-history-table tbody").on("click", "a", function (e) {
    e.preventDefault();

    // Modal "Ver Chat"
    if ($(this).hasClass("view-conversation-btn")) {
      openModal("Conversación ID: " + $(this).data("session-id"), {
        action: "chat_bob_get_conversation_details",
        session_id: $(this).data("session-id"),
        _wpnonce: chat_bob_admin_ajax.view_nonce,
      });
    }

    // Modal "Ver Pedidos"
    if ($(this).hasClass("view-orders-btn")) {
      openModal("Pedidos de " + $(this).data("user-name"), {
        action: "chat_bob_get_user_orders",
        user_id: $(this).data("user-id"),
        _wpnonce: chat_bob_admin_ajax.orders_nonce,
      });
    }

    // *** MODIFICADO ***: Eliminación Individual 100% AJAX
    if ($(this).hasClass("delete-chat-btn")) {
      const sessionId = $(this).data("session-id");
      handleDeleteAction([sessionId], i18n.confirm_delete_text);
    }
  });

  // --- Lógica para Acciones en Lote ---
  $("#chat-history-table thead").on(
    "change",
    'input[type="checkbox"]',
    function () {
      $('#chat-history-table tbody input[type="checkbox"]').prop(
        "checked",
        this.checked
      );
    }
  );

  $("#chat-bob-bulk-apply").on("click", function () {
    if ($("#chat-bob-bulk-action").val() !== "delete") return;

    const selectedIds = $(
      '#chat-history-table tbody input[type="checkbox"]:checked'
    )
      .map(function () {
        return $(this).val();
      })
      .get();

    if (selectedIds.length === 0) {
      Swal.fire({ icon: "error", title: i18n.no_selection });
      return;
    }
    handleDeleteAction(selectedIds, i18n.bulk_confirm_text);
  });

  // --- Función Unificada de Eliminación ---
  function handleDeleteAction(ids, confirmationText) {
    Swal.fire({
      title: i18n.confirm_delete_title,
      text: confirmationText,
      icon: "warning",
      showCancelButton: true,
      confirmButtonColor: "#d33",
      cancelButtonColor: "#3085d6",
      confirmButtonText: i18n.confirm_button,
      cancelButtonText: i18n.cancel_button,
    }).then((result) => {
      if (result.isConfirmed) {
        $.ajax({
          url: chat_bob_admin_ajax.ajax_url,
          type: "POST",
          data: {
            action: "chat_bob_handle_delete_chats",
            session_ids: ids,
            _wpnonce: chat_bob_admin_ajax.delete_nonce,
          },
          success: function (response) {
            if (response.success) {
              Swal.fire({
                toast: true,
                position: "top-end",
                icon: "success",
                title: response.data.message,
                showConfirmButton: false,
                timer: 3000,
              });
              historyTable.ajax.reload(null, false);
            } else {
              Swal.fire({
                icon: "error",
                title: "Error",
                text: response.data.message,
              });
            }
          },
          error: function () {
            Swal.fire({ icon: "error", title: "Error", text: i18n.error });
          },
        });
      }
    });
  }

  // --- Funciones del Modal ---
  function openModal(title, ajaxData) {
    $("#chat-bob-modal-title").text(title);
    $("#chat-bob-modal-body").html(
      '<p class="loading-text">' + i18n.loading + "</p>"
    );
    $("#chat-bob-modal").fadeIn();

    $.ajax({
      url: chat_bob_admin_ajax.ajax_url,
      type: "POST",
      data: ajaxData,
      success: function (response) {
        if (response.success)
          $("#chat-bob-modal-body").html(response.data.html);
        else
          $("#chat-bob-modal-body").html(
            '<p style="color:red;">' +
              (response.data.message || i18n.error) +
              "</p>"
          );
      },
      error: function () {
        $("#chat-bob-modal-body").html(
          '<p style="color:red;">' + i18n.error + "</p>"
        );
      },
    });
  }

  function closeModal() {
    $("#chat-bob-modal").fadeOut();
  }
  $(".chat-bob-modal-close").on("click", closeModal);
  $("#chat-bob-modal").on("click", (e) => {
    if ($(e.target).is("#chat-bob-modal")) closeModal();
  });
  $(document).on("keyup", (e) => {
    if (e.key === "Escape") closeModal();
  });
});
