console.log("THACH_DAU.JS LOADED");
// JS Xử lý chọn đáp án
function selectOption(element) {
    const options = document.querySelectorAll('.mcq-option');
    options.forEach(opt => opt.classList.remove('selected'));
    element.classList.add('selected');
}

// JS Bắn Toast Thách Đấu
function startBattle() {
    bootstrap.Modal.getInstance(document.getElementById('battleModal')).hide();
    document.getElementById('toastMessage').innerHTML = '<i class="fa-solid fa-paper-plane text-warning me-2"></i> Lời thách đấu đã được gửi đi!';
    new bootstrap.Toast(document.getElementById('systemToast')).show();
}
function inviteUser(userId)
{
    alert("Đã gửi lời mời tới user ID: " + userId);
}
function startBattle()
{
    const opponentId =
        document.getElementById("opponent_id").value;

    const examSetId =
        document.getElementById("exam_set_id").value;

    const formData = new FormData();

    formData.append(
        "opponent_id",
        opponentId
    );

    formData.append(
        "exam_set_id",
        examSetId
    );

    fetch(
        "../ajax/create_battle.php",
        {
            method: "POST",
            body: formData
        }
    )
    .then(response => response.json())
    .then(data => {

        alert(data.message);

        if(data.success)
        {
            location.reload();
        }

    })
    .catch(error => {

        console.error(error);

    });
}

let currentInvite = null;
let inviteModalShown = false;

setInterval(checkInvite,3000);

async function checkInvite()
{
    const response =
        await fetch(
            "../ajax/check_invite.php"
        );

    const data = await response.json();

    if(data.success && !inviteModalShown)
    {
        currentInvite = data.invite;

        document.getElementById(
            "inviteText"
        ).innerHTML =
            data.invite.sender_name +
            " muốn thách đấu bạn";

        const modal =
            new bootstrap.Modal(
                document.getElementById(
                    "battleInviteModal"
                )
            );

        modal.show();

        inviteModalShown = true;
    }
}
async function acceptInvite()
{
    const formData = new FormData();

    formData.append(
        "id",
        currentInvite.id
    );

    const response =
        await fetch(
            "../ajax/accept_invite.php",
            {
                method:"POST",
                body:formData
            }
        );

    const data = await response.json();

    if(data.success)
    {
        inviteModalShown = false;

        alert("Đã tham gia phòng!");

        location.href =
            "battle_room.php?room_id="
            + data.room_id;
    }
}
async function declineInvite()
{
    const formData = new FormData();

    formData.append(
        "id",
        currentInvite.id
    );

    await fetch(
        "../ajax/decline_invite.php",
        {
            method:"POST",
            body:formData
        }
    );

    inviteModalShown = false;
    
    location.reload();
}   