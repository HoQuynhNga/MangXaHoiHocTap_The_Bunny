function toggleSidebar() {
  document.getElementById("sidebarLeft").classList.toggle("open");
  document.getElementById("mobileOverlay").classList.toggle("show");
}

(function () {
  var CHATS = {
    meagan: {
      name: "Meagan McLaughlin",
      avatar: "https://i.pravatar.cc/150?img=32",
      messages: [{ from: "them", text: "This is a sample massage!", time: "2 giờ trước" }],
    },
    reba: {
      name: "Reba Reynolds",
      avatar: "https://i.pravatar.cc/150?img=45",
      messages: [
        { from: "me", text: "Hi", time: "2 giờ trước" },
        { from: "them", text: "Chào bạn! Học nhóm chiều nay nhé?", time: "2 giờ trước" },
      ],
    },
    "hoang-oanh": {
      name: "Hoàng Oanh",
      avatar: "https://i.pravatar.cc/150?img=9",
      messages: [{ from: "them", text: "Nhóm mình học 8h tối nhé!", time: "Hôm qua" }],
    },
    "tran-phong": {
      name: "Trần Phong",
      avatar: "https://i.pravatar.cc/150?img=11",
      messages: [
        { from: "me", text: "Ok nhé", time: "3 ngày trước" },
        { from: "them", text: "Tài liệu mình gửi link drive rồi đó.", time: "3 ngày trước" },
      ],
    },
    "chi-le": {
      name: "Chi Lê",
      avatar: "https://i.pravatar.cc/150?img=4",
      messages: [{ from: "them", text: "Flashcard Lý gửi bạn nè", time: "Tuần trước" }],
    },
  };

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

  document.querySelectorAll("a.friend-item[data-open-chat]").forEach(function (a) {
    a.addEventListener("click", function (e) {
      var id = a.getAttribute("data-open-chat");
      if (!id || !CHATS[id]) return;
      e.preventDefault();
      var row = document.querySelector('.msg-row[data-chat-id="' + id + '"]');
      if (row && row.scrollIntoView) row.scrollIntoView({ behavior: "smooth", block: "nearest" });
      openChat(id);
    });
  });

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
    setTimeout(function () {
      sendBusy = false;
    }, 0);
    var now = "Vừa xong";
    CHATS[activeId].messages.push({ from: "me", text: text, time: now });
    input.value = "";
    sessionStorage.removeItem("inbox-draft-" + activeId);
    renderMessages(activeId);
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
})();
