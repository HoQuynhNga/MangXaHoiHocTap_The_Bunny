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