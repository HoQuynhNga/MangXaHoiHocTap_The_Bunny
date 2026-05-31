let answered = false;

let waitingInterval = null;

function selectAnswer(answer)
{
    if(answered)
    {
        return;
    }

    answered = true;

    document
        .querySelectorAll(".answer-btn")
        .forEach(btn =>
        {
            btn.disabled = true;
        });

    const roomId =
        document.getElementById(
            "room_id"
        ).value;

    const questionId =
        document.getElementById(
            "question_id"
        ).value;

    const formData =
        new FormData();

    formData.append(
        "room_id",
        roomId
    );

    formData.append(
        "question_id",
        questionId
    );

    formData.append(
        "answer",
        answer
    );

    fetch(
        "../ajax/submit_answer.php",
        {
            method:"POST",
            body:formData
        }
    )
    .then(response => response.json())
    .then(data =>
    {
        if(data.success)
        {
            startWaitingOpponent();
        }
        else
        {
            alert(data.message);
        }
    })
    .catch(error =>
    {
        console.error(error);
    });
}

function startWaitingOpponent()
{
    document.getElementById(
        "result"
    ).innerHTML =
        "Đang chờ đối thủ trả lời...";
    
    waitingInterval =
        setInterval(
            checkOpponentAnswer,
            1000
        );
}

function checkOpponentAnswer()
{
    const roomId =
        document.getElementById(
            "room_id"
        ).value;

    const questionId =
        document.getElementById(
            "question_id"
        ).value;

    fetch(
        "../ajax/check_opponent_answer.php"
        + "?room_id=" + roomId
        + "&question_id=" + questionId
    )
    .then(response => response.json())
    .then(data =>
    {
        if(data.success)
        {
            clearInterval(
                waitingInterval
            );

            showResult(data);

            setTimeout(
                nextQuestion,
                3000
            );
        }
    })
    .catch(error =>
    {
        console.error(error);
    });
}

function showResult(data)
{
    document.getElementById(
        "result"
    ).innerHTML =

        "<h3>Kết quả</h3>"

        + "<p>Bạn: "
        + data.my_answer
        + (
            data.my_correct == 1
            ? " ✓"
            : " ✗"
        )
        + "</p>"

        + "<p>Đối thủ: "
        + data.opponent_answer
        + (
            data.opponent_correct == 1
            ? " ✓"
            : " ✗"
        )
        + "</p>";
}

function nextQuestion()
{
    const nextUrl =
        document.body.dataset.nextQuestion;

    if(nextUrl)
    {
        location.href = nextUrl;
    }
    else
    {
        alert(
            "Đã hoàn thành trận đấu"
        );
    }
}