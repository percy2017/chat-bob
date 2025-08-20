(function ($) {
  $(document).ready(function () {
    // =====================================================================
    // == 1. CACHING DE SELECTORES Y VARIABLES DE ESTADO                 ==
    // =====================================================================

    const chatWindow = $("#chat-bob-window");
    const toggleButton = $("#chat-bob-toggle-button");
    if (chatWindow.length === 0 || toggleButton.length === 0) return;

    const messagesContainer = $("#chat-bob-messages"),
      messageInput = $("#chat-bob-input"),
      sendButton = $("#chat-bob-send-button"),
      loginWrapper = $(".chat-bob-login-wrapper"),
      chatWrapper = $(".chat-bob-chat-wrapper"),
      identifyForm = $("#chat-bob-identify-form"),
      loginErrorDiv = $("#chat-bob-login-error");

    const attachButton = $("#chat-bob-attach-button"),
      fileInput = $("#chat-bob-file-input"),
      attachmentPreview = $("#chat-bob-attachment-preview");

    let historyLoaded = false,
      isSubmitting = false,
      iti;

    // =====================================================================
    // == 2. LÓGICA DE UI Y EVENTOS PRINCIPALES                         ==
    // =====================================================================

    const toggleChat = () => {
      const isWindowClosed = chatWindow.hasClass("is-closed");
      chatWindow
        .toggleClass("is-closed", !isWindowClosed)
        .toggleClass("is-open", isWindowClosed);
      toggleButton
        .toggleClass("is-closed", isWindowClosed)
        .toggleClass("is-open", !isWindowClosed);
      if (isWindowClosed && !historyLoaded && chatWrapper.is(":visible")) {
        loadChatHistory();
      }
      if (isWindowClosed) setTimeout(() => messageInput.focus(), 300);
    };

    const transitionToChatView = () => {
      loginWrapper.fadeOut(200, () => chatWrapper.fadeIn(200));
    };

    // =====================================================================
    // == 3. LÓGICA PARA ADJUNTAR ARCHIVOS (VISTA PREVIA EXTERNA)       ==
    // =====================================================================

    attachButton.on("click", () => fileInput.trigger("click"));

    fileInput.on("change", function () {
      if (this.files && this.files.length > 0) {
        const fileName = this.files[0].name;
        const filePreviewHtml = `<span>${$("<div>")
          .text(fileName)
          .html()}</span><button type="button" id="chat-bob-cancel-attachment">&times;</button>`;
        attachmentPreview.html(filePreviewHtml).show();
        sendButton.prop("disabled", false);
      }
    });

    $(document).on("click", "#chat-bob-cancel-attachment", function () {
      fileInput.val("");
      attachmentPreview.hide().empty();
      if (messageInput.val().trim() === "") {
        sendButton.prop("disabled", true);
      }
    });

    // =====================================================================
    // == 4. LÓGICA AJAX (Comunicación con WordPress)                   ==
    // =====================================================================

    function sendMessage() {
      if (isSubmitting) return;
      const text = messageInput.val().trim();
      const file = fileInput[0].files[0];
      if (text === "" && !file) return;

      isSubmitting = true;

      // --- IMPLEMENTACIÓN DE VISTA PREVIA EN EL CHAT ---
      if (file && file.type.startsWith("image/")) {
        const reader = new FileReader();
        reader.onload = function (e) {
          const imageUrl = e.target.result;
          // Sanitizamos el texto del pie de foto por si acaso
          const caption = $("<div>").text(text).html();
          const imageMessageHtml = `
                        <div class="chat-bob-message message-user message-with-image">
                            <img src="${imageUrl}" class="chat-bob-chat-image" alt="Imagen adjunta">
                            ${
                              caption
                                ? `<p class="image-caption">${caption.replace(
                                    /\n/g,
                                    "<br>"
                                  )}</p>`
                                : ""
                            }
                        </div>`;
          messagesContainer.append(imageMessageHtml);
          scrollToBottom();
        };
        reader.readAsDataURL(file);
      } else if (file) {
        addMessage(`Adjunto: ${file.name}\n\n${text}`, "user");
      } else {
        addMessage(text, "user");
      }

      // Preparar y enviar los datos
      const formData = new FormData();
      formData.append("action", "chat_bob_send_message");
      formData.append("nonce", chat_bob_data.nonce);
      formData.append("message", text);
      if (file) formData.append("attachment", file);

      // Limpiar interfaz
      messageInput.val("").prop("disabled", true);
      sendButton.prop("disabled", true);
      fileInput.val("");
      attachmentPreview.hide().empty();
      autoResizeTextarea();
      showTyping();

      $.ajax({
        url: chat_bob_data.ajax_url,
        type: "POST",
        data: formData,
        processData: false,
        contentType: false,
        success: (res) =>
          addMessage(
            res.success ? res.data.reply : res.data.message || "Error",
            res.success ? "bot" : "error"
          ),
        error: (jqXHR) =>
          addMessage(
            jqXHR.responseJSON?.data?.message || "Error de comunicación.",
            "error"
          ),
        complete: () => {
          hideTyping();
          messageInput.prop("disabled", false).focus();
          isSubmitting = false;
        },
      });
    }

    function handleIdentifySubmit(e) {
      e.preventDefault();
      if (isSubmitting) return;
      if (iti && !iti.isValidNumber()) {
        loginErrorDiv
          .text("Por favor, introduce un número de teléfono válido.")
          .show();
        return;
      }
      isSubmitting = true;
      loginErrorDiv.hide();
      $(this).find('button[type="submit"]').prop("disabled", true);
      const identifyData = {
        action: "chat_bob_identify_user",
        nonce: chat_bob_data.nonce,
        first_name: $("#cb_first_name").val(),
        last_name: $("#cb_last_name").val(),
        email: $("#cb_email").val(),
        phone: iti ? iti.getNumber() : $("#cb_phone").val(),
      };

      $.ajax({
        url: chat_bob_data.ajax_url,
        type: "POST",
        data: identifyData,
        success: (res) => {
          if (res.success) {
            if (res.data.history && res.data.history.length > 0) {
              res.data.history.forEach((m) =>
                addMessage(m.content, m.sender, false)
              );
            } else {
              addMessage($("#chat-bob-welcome-message").text(), "bot", false);
            }
            historyLoaded = true;
            transitionToChatView();
          } else {
            loginErrorDiv.text(res.data.message || "Error").show();
          }
        },
        error: () => loginErrorDiv.text("Error de conexión.").show(),
        complete: () => {
          isSubmitting = false;
          $(this).find('button[type="submit"]').prop("disabled", false);
        },
      });
    }

    function loadChatHistory() {
      if (historyLoaded) return;
      historyLoaded = true;
      showTyping();
      $.ajax({
        url: chat_bob_data.ajax_url,
        type: "POST",
        data: { action: "chat_bob_load_history", nonce: chat_bob_data.nonce },
        success: (res) => {
          if (res.success && res.data.length > 0) {
            res.data.forEach((m) => addMessage(m.content, m.sender, false));
          } else {
            addMessage($("#chat-bob-welcome-message").text(), "bot", false);
          }
        },
        error: () =>
          addMessage("No se pudo cargar el historial.", "error", false),
        complete: () => {
          hideTyping();
          scrollToBottom();
        },
      });
    }

    // =====================================================================
    // == 5. FUNCIONES AUXILIARES Y EJECUCIÓN                           ==
    // =====================================================================
    const addMessage = (text, sender, animate = true) => {
      let messageContentHtml;
      if (sender === "bot" && typeof marked !== "undefined") {
        messageContentHtml = marked.parse(text);
      } else {
        messageContentHtml = $("<div>")
          .text(text)
          .html()
          .replace(/\n/g, "<br>");
      }

      const messageDiv = $(
        `<div class="chat-bob-message message-${sender}"></div>`
      ).html(messageContentHtml);

      if (animate) messageDiv.hide();
      messagesContainer.append(messageDiv);
      if (animate) messageDiv.fadeIn(300);
      scrollToBottom();
    };

    const showTyping = () => {
      if ($(".message-typing").length === 0)
        messagesContainer.append(
          '<div class="chat-bob-message message-bot message-typing"><span></span><span></span><span></span></div>'
        );
      scrollToBottom();
    };

    const hideTyping = () => $(".message-typing").remove();
    const scrollToBottom = () => {
      if (messagesContainer.length)
        messagesContainer
          .stop()
          .animate({ scrollTop: messagesContainer[0].scrollHeight }, 300);
    };
    
    const autoResizeTextarea = () => {
      const ta = messageInput[0];
      ta.style.height = "auto";
      ta.style.height = ta.scrollHeight + "px";
    };

    const initializePhoneInput = () => {
      if (
        $("#cb_phone").length > 0 &&
        typeof window.intlTelInput !== "undefined"
      ) {
        iti = window.intlTelInput($("#cb_phone")[0], {
          utilsScript:
            "https://cdn.jsdelivr.net/npm/intl-tel-input@19.2.16/build/js/utils.js",
          initialCountry: "auto",
          geoIpLookup: (cb) => {
            $.get(
              "https://ipapi.co/json",
              (res) => cb(res.country_code || "us"),
              "json"
            );
          },
        });
      }
    };

    // Asignación de eventos e inicialización
    initializePhoneInput();
    chatWindow.css({
      position: "fixed",
      zIndex: 2147483647,
      bottom: "20px",
      right: "20px",
    });
    toggleButton.css({
      position: "fixed",
      zIndex: 2147483647,
      bottom: "20px",
      right: "20px",
    });
    toggleButton.on("click", toggleChat);
    $("#chat-bob-close-button").on("click", toggleChat);
    identifyForm.on("submit", handleIdentifySubmit);
    sendButton.on("click", sendMessage);
    messageInput.on("keydown", (e) => {
      if (e.key === "Enter" && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
      }
    });
    messageInput.on("input", function () {
      sendButton.prop(
        "disabled",
        $(this).val().trim().length === 0 && fileInput.get(0).files.length === 0
      );
      autoResizeTextarea();
    });
  });
})(jQuery);
