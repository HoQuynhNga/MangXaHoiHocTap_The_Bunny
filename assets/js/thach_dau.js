console.log("THACH_DAU.JS LOADED");

// ==========================================
// 1. SELECTION STYLING FUNCTION
// ==========================================
function selectOption(element) {
    const options = document.querySelectorAll('.mcq-option');
    options.forEach(opt => opt.classList.remove('selected'));
    element.classList.add('selected');
}

function inviteUser(userId) {
    alert("Đã gửi lời mời tới user ID: " + userId);
}

// ==========================================
// 2. startBattle with Double-Submission Prevention
// ==========================================
let isCreatingBattle = false; // Flag to prevent multi-clicking

function startBattle() {
    if (isCreatingBattle) return; // Ignore clicks if already processing

    const opponentId = document.getElementById("opponent_id").value;
    const examSetId = document.getElementById("exam_set_id").value;

    if (!opponentId) {
        alert("Vui lòng chọn đối thủ!");
        return;
    }

    isCreatingBattle = true;

    // Visual loading state on the submit button
    const submitBtn = document.querySelector(".btn-warning[onclick='startBattle()']");
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = 'Đang kết nối... <i class="fa-solid fa-spinner fa-spin"></i>';
    }

    const formData = new FormData();
    formData.append("opponent_id", opponentId);
    formData.append("exam_set_id", examSetId);

    fetch("../ajax/create_battle.php", {
        method: "POST",
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        isCreatingBattle = false;
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = 'Chiến luôn <i class="fa-solid fa-fire text-danger ms-1"></i>';
        }

        if (data.success) {
            const battleModalEl = document.getElementById('battleModal');
            if (battleModalEl) {
                const modalInstance = bootstrap.Modal.getInstance(battleModalEl) || new bootstrap.Modal(battleModalEl);
                modalInstance.hide();
            }

            alert("Lời mời thách đấu đã được gửi thành công!");
            location.href = "battle_room.php?room_id=" + data.room_id;
        } else {
            alert(data.message || "Có lỗi xảy ra khi tạo phòng đấu.");
        }
    })
    .catch(error => {
        isCreatingBattle = false;
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = 'Chiến luôn <i class="fa-solid fa-fire text-danger ms-1"></i>';
        }
        console.error("Error creating battle:", error);
        alert("Không thể kết nối đến máy chủ.");
    });
}

// ==========================================
// 3. Invitation Polling
// ==========================================
let currentInvite = null;
let inviteModalShown = false;
let inviteModalInstance = null;

setInterval(checkInvite, 3000);

async function checkInvite() {
    try {
        const response = await fetch("../ajax/check_invite.php");
        const data = await response.json();

        if (data.success && data.invite) {
            if (currentInvite && currentInvite.id === data.invite.id && inviteModalShown) {
                return;
            }

            currentInvite = data.invite;

            const textEl = document.getElementById("inviteText");
            if (textEl) {
                textEl.innerHTML = `Bạn học <b>${data.invite.sender_name}</b> đang muốn thách đấu bạn!`;
            }

            const modalEl = document.getElementById("battleInviteModal");
            if (modalEl) {
                modalEl.setAttribute('data-bs-backdrop', 'static');
                modalEl.setAttribute('data-bs-keyboard', 'false');

                if (!inviteModalInstance) {
                    inviteModalInstance = new bootstrap.Modal(modalEl);
                }
                inviteModalInstance.show();
                inviteModalShown = true;
            }
        } else {
            if (inviteModalShown && inviteModalInstance) {
                inviteModalInstance.hide();
                inviteModalShown = false;
                currentInvite = null;
            }
        }
    } catch (error) {
        console.error("Error checking invite:", error);
    }
}

async function acceptInvite() {
    if (!currentInvite) return;

    const formData = new FormData();
    formData.append("id", currentInvite.id);

    try {
        const response = await fetch("../ajax/accept_invite.php", {
            method: "POST",
            body: formData
        });
        const data = await response.json();

        if (data.success) {
            inviteModalShown = false;
            currentInvite = null;

            if (inviteModalInstance) {
                inviteModalInstance.hide();
            }

            alert("Đã tham gia phòng!");
            location.href = "battle_room.php?room_id=" + data.room_id;
        }
    } catch (error) {
        console.error("Error accepting invite:", error);
    }
}

async function declineInvite() {
    if (!currentInvite) return;

    const formData = new FormData();
    formData.append("id", currentInvite.id);

    try {
        await fetch("../ajax/decline_invite.php", {
            method: "POST",
            body: formData
        });

        inviteModalShown = false;
        currentInvite = null;

        if (inviteModalInstance) {
            inviteModalInstance.hide();
        }
    } catch (error) {
        console.error("Error declining invite:", error);
    }
}

document.addEventListener("DOMContentLoaded", function() {
    const modalEl = document.getElementById('battleInviteModal');
    if (modalEl) {
        modalEl.addEventListener('hidden.bs.modal', function () {
            if (currentInvite && inviteModalShown) {
                declineInvite();
            }
        });
    }
});

// ==========================================
// 4. Dynamic Profile and Challenge Handlers
// ==========================================
let selectedOpponentId = null;

function showUserInfo(userId, element) {
    // Remove selection styles from all list items and highlight the current one
    document.querySelectorAll('.list-group-item').forEach(item => {
        item.classList.remove('active', 'bg-primary', 'bg-opacity-10');
    });
    element.classList.add('active', 'bg-primary', 'bg-opacity-10');

    // Hide profile content and show loader safely
    const placeholder = document.getElementById('userPlaceholder');
    const detailContent = document.getElementById('userDetailContent');
    const loader = document.getElementById('userLoading');

    if (placeholder) placeholder.classList.add('d-none');
    if (detailContent) detailContent.classList.add('d-none');
    if (loader) loader.classList.remove('d-none');

    // Fetch profile data dynamically using inline router API
    fetch('thach-dau.php?get_user_info=' + userId)
        .then(response => response.json())
        .then(data => {
            if (loader) loader.classList.add('d-none');
            if (data.success) {
                selectedOpponentId = data.user.id;
               
                const avatarImg = document.getElementById('detailAvatar');
                const usernameText = document.getElementById('detailUsername');
                const levelText = document.getElementById('detailLevel');
                const xpText = document.getElementById('detailXp');
                const carrotsText = document.getElementById('detailCarrots');

                if (avatarImg) avatarImg.src = data.user.avatar;
                if (usernameText) usernameText.innerText = data.user.username;
                if (levelText) levelText.innerText = 'Lv.' + data.user.level;
                if (xpText) xpText.innerText = data.user.xp + ' XP';
                if (carrotsText) carrotsText.innerText = data.user.carrots + ' 🥕';
               
                if (detailContent) detailContent.classList.remove('d-none');
            } else {
                if (placeholder) placeholder.classList.remove('d-none');
                alert(data.message || 'Lỗi tải thông tin');
            }
        })
        .catch(error => {
            if (loader) loader.classList.add('d-none');
            if (placeholder) placeholder.classList.remove('d-none');
            console.error("Profile Fetch Error:", error);
        });
}

function challengeFromProfile() {
    if (!selectedOpponentId) return;

    // Sync user selection to match matchmaker dropdown menu value
    const opponentSelect = document.getElementById('opponent_id');
    if (opponentSelect) {
        opponentSelect.value = selectedOpponentId;
    }

    // Display battle modal directly on selected target
    const battleModalEl = document.getElementById('battleModal');
    if (battleModalEl) {
        const modalInstance = new bootstrap.Modal(battleModalEl);
        modalInstance.show();
    }
}