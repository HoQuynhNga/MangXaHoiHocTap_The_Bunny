function toggleSidebar() {
    document.getElementById("sidebarLeft").classList.toggle("open");
    document.getElementById("mobileOverlay").classList.toggle("show");
  }
  
  (function () {
  
    var panel = document.querySelector(".inbox-panel");
    var placeholder = document.getElementById("inboxPlaceholder");
    var conversation = document.getElementById("inboxConversation");
    var listBox = document.querySelector(".inbox-panel__items");
    var headerName = document.getElementById("inboxChatHeaderName");
    var headerAvatar = document.getElementById("inboxChatHeaderAvatar");
    var messagesEl = document.getElementById("inboxChatMessages");
    var form = document.getElementById("inboxChatForm");
    var input = document.getElementById("inboxChatInput");
    var sendBtn = document.getElementById("inboxChatSend");
    var backBtn = document.getElementById("inboxChatBack");
    var toolbarSearch = document.getElementById("inboxToolbarSearch");
    var threadCountEl = document.getElementById("inboxThreadCount");
  
    var activeId = null;
    var draftTimer = null;
  
    function updateThreadCount() {
      var n = document.querySelectorAll(".msg-row[data-chat-id]").length;
      if (threadCountEl) threadCountEl.textContent = "(" + n + ")";
      var badge = document.getElementById("sidebarInboxBadge");
      if (badge) badge.textContent = String(n);
    }
  
    updateThreadCount();
  
    function bubbleHtml(msg) {
      var mine = msg.from === "me";
      return (
        '<div class="inbox-chat__bubble-wrap' +
        (mine ? " inbox-chat__bubble-wrap--mine" : "") +
        '">' +
        '<div class="inbox-chat__bubble">' +
        escapeHtml(msg.text) +
        "</div>" +
        '<span class="inbox-chat__bubble-time">' +
        escapeHtml(msg.time) +
        "</span></div>"
      );
    }
  
    function escapeHtml(s) {
      var d = document.createElement("div");
      d.textContent = s;
      return d.innerHTML;
    }
  
    function renderMessages(id) {
      var data = CHATS[id];
      if (!data) return;
      messagesEl.innerHTML = data.messages.map(bubbleHtml).join("");
      messagesEl.scrollTop = messagesEl.scrollHeight;
    }
  
    function openChat(id) {
      var data = CHATS[id];
      if (!data) return;
      activeId = id;
      document.querySelectorAll(".msg-row[data-chat-id]").forEach(function (row) {
        var sel = row.getAttribute("data-chat-id") === id;
        row.classList.toggle("is-active", sel);
        row.setAttribute("aria-selected", sel ? "true" : "false");
      });
      headerName.textContent = data.name;
      headerAvatar.src = data.avatar;
      headerAvatar.alt = data.name;
      renderMessages(id);
      var draft = sessionStorage.getItem("inbox-draft-" + id);
      input.value = draft || "";
      placeholder.hidden = true;
      placeholder.setAttribute("aria-hidden", "true");
      conversation.hidden = false;
      if (panel) panel.classList.add("inbox-panel--chat-open");
      setTimeout(function () {
        input.focus();
      }, 0);
    }
  
    function closeChat() {
      if (activeId) {
        sessionStorage.setItem("inbox-draft-" + activeId, input.value);
      }
      activeId = null;
      document.querySelectorAll(".msg-row[data-chat-id]").forEach(function (row) {
        row.classList.remove("is-active");
        row.setAttribute("aria-selected", "false");
      });
      conversation.hidden = true;
      placeholder.hidden = false;
      placeholder.setAttribute("aria-hidden", "false");
      if (panel) panel.classList.remove("inbox-panel--chat-open");
      input.value = "";
    }
  
    if (listBox) {
      listBox.addEventListener("click", function (e) {
        var row = e.target.closest(".msg-row[data-chat-id]");
        if (!row) return;
        openChat(row.getAttribute("data-chat-id"));
      });
    }
  
    document.querySelectorAll("a.friend-item[data-peer-id]").forEach(function (a) {
      a.addEventListener("click", function (e) {
        e.preventDefault();
        var id = a.getAttribute("data-open-chat");
        var peerId = a.getAttribute("data-peer-id");
        if (id && CHATS[id]) {
          var row = document.querySelector('.msg-row[data-chat-id="' + id + '"]');
          if (row && row.scrollIntoView) row.scrollIntoView({ behavior: "smooth", block: "nearest" });
          openChat(id);
          return;
        }
        if (!peerId) return;
        startChatWithPeer(peerId, a);
      });
    });
  
    function appendConversationRow(conv) {
      if (!listBox) return;
      var emptyHint = listBox.querySelector("p.text-muted");
      if (emptyHint) emptyHint.remove();
  
      if (document.querySelector('.msg-row[data-chat-id="' + conv.id + '"]')) {
        return;
      }
  
      var btn = document.createElement("button");
      btn.type = "button";
      btn.className = "msg-row";
      btn.setAttribute("role", "option");
      btn.setAttribute("data-chat-id", conv.id);
      btn.setAttribute("aria-selected", "false");
      btn.innerHTML =
        '<img class="msg-row__avatar" src="' +
        escapeHtml(conv.avatar) +
        '" width="40" height="40" alt="" />' +
        '<span class="msg-row__body">' +
        '<span class="msg-row__name">' +
        escapeHtml(conv.name) +
        "</span>" +
        '<span class="msg-row__preview">' +
        escapeHtml(conv.preview || "") +
        "</span>" +
        '<span class="msg-row__time">' +
        escapeHtml(conv.time_ago || "") +
        "</span></span>";
      listBox.insertBefore(btn, listBox.firstChild);
      updateThreadCount();
    }
  
    function startChatWithPeer(peerId, friendLink) {
      if (sendBusy) return;
      sendBusy = true;
  
      var body = new URLSearchParams();
      body.set("action", "start_chat");
      body.set("peer_id", peerId);
  
      fetch("tin-nhan.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: body.toString(),
      })
        .then(function (res) {
          return res.json().then(function (data) {
            if (!res.ok || !data.ok) {
              throw new Error((data && data.error) || "Không mở được cuộc trò chuyện");
            }
            return data;
          });
        })
        .then(function (data) {
          CHATS[data.chat_id] = data.chat;
          appendConversationRow(data.conversation);
          if (friendLink) {
            friendLink.setAttribute("data-open-chat", data.chat_id);
          }
          openChat(data.chat_id);
        })
        .catch(function (err) {
          alert(err.message || "Không mở được cuộc trò chuyện");
        })
        .finally(function () {
          sendBusy = false;
        });
    }
  
    if (toolbarSearch) {
      toolbarSearch.addEventListener("input", function () {
        var q = toolbarSearch.value.trim().toLowerCase();
        document.querySelectorAll(".msg-row[data-chat-id]").forEach(function (row) {
          var show = !q || row.textContent.toLowerCase().indexOf(q) !== -1;
          row.hidden = !show;
        });
      });
    }
  
    document.addEventListener("keydown", function (e) {
      if (e.key !== "Escape") return;
      if (toolbarSearch && document.activeElement === toolbarSearch && toolbarSearch.value) {
        toolbarSearch.value = "";
        toolbarSearch.dispatchEvent(new Event("input", { bubbles: true }));
        return;
      }
      if (conversation.hidden) return;
      closeChat();
    });
  
    input.addEventListener("input", function () {
      if (!activeId) return;
      clearTimeout(draftTimer);
      draftTimer = setTimeout(function () {
        sessionStorage.setItem("inbox-draft-" + activeId, input.value);
      }, 250);
    });
  
    backBtn.addEventListener("click", closeChat);
  
    var sendBusy = false;
  
    function sendMessage() {
      if (!activeId || sendBusy) return;
      var text = input.value.replace(/^\s+|\s+$/g, "");
      if (!text) return;
      sendBusy = true;
  
      var body = new URLSearchParams();
      body.set("action", "send_message");
      body.set("chat_id", activeId);
      body.set("text", text);
  
      fetch("tin-nhan.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: body.toString(),
      })
        .then(function (res) {
          return res.json().then(function (data) {
            if (!res.ok || !data.ok) {
              throw new Error((data && data.error) || "Gửi tin nhắn thất bại");
            }
            return data.message;
          });
        })
        .then(function (msg) {
          if (!CHATS[activeId]) return;
          CHATS[activeId].messages.push(msg);
          input.value = "";
          sessionStorage.removeItem("inbox-draft-" + activeId);
          renderMessages(activeId);
          var row = document.querySelector('.msg-row[data-chat-id="' + activeId + '"]');
          if (row) {
            var preview = row.querySelector(".msg-row__preview");
            if (preview) {
              preview.className = "msg-row__preview msg-row__preview--you";
              preview.innerHTML =
                '<span class="msg-row__you-label">Bạn:</span> ' + escapeHtml(msg.text);
            }
            var timeEl = row.querySelector(".msg-row__time");
            if (timeEl) timeEl.textContent = msg.time;
          }
        })
        .catch(function (err) {
          alert(err.message || "Không gửi được tin nhắn");
        })
        .finally(function () {
          sendBusy = false;
        });
    }
  
    form.addEventListener("submit", function (e) {
      e.preventDefault();
    });
  
    sendBtn.addEventListener("click", function (e) {
      e.preventDefault();
      if (e.detail < 1) return;
      sendMessage();
    });
  
    input.addEventListener("keydown", function (e) {
      if (e.key !== "Enter" || e.shiftKey) return;
      if (e.repeat) return;
      if (e.isComposing || e.keyCode === 229) return;
      e.preventDefault();
      e.stopImmediatePropagation();
      sendMessage();
    });
  
    var urlPeerId = new URLSearchParams(window.location.search).get("peer_id");
    if (urlPeerId) {
      var friendLink = document.querySelector('a.friend-item[data-peer-id="' + urlPeerId + '"]');
      var existingChatId = friendLink ? friendLink.getAttribute("data-open-chat") : "";
      if (existingChatId && CHATS[existingChatId]) {
        openChat(existingChatId);
      } else {
        startChatWithPeer(urlPeerId, friendLink);
      }
    }
  })();
  