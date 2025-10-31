// Function to handle question submission
function submitAnswer(exam_id, question_id, answer) {
    $.ajax({
        url: 'user_ajax_action.php',
        method: 'POST',
        data: {
            exam_id: exam_id,
            question_id: question_id,
            answer_option: answer,
            page: 'view_exam',
            action: 'submit_answer'
        },
        success: function(response) {
            // Highlight the question number in navigation to show it's answered
            $('.question_navigation[data-question_id="' + question_id + '"]').removeClass('btn-primary').addClass('btn-success');
        }
    });
}

// When an answer option is selected
$(document).on('change', '.answer_option', function() {
    var question_id = $(this).data('question_id');
    var option_number = $(this).data('option_number');
    submitAnswer(exam_id, question_id, option_number);
});

// Initialize the exam timer
$('#exam_timer').TimeCircles({
    time: {
        Days: { show: false },
        Hours: { show: true },
        Minutes: { show: true },
        Seconds: { show: true }
    }
}).addListener(function(unit, value, total) {
    if(total <= 0) {
        $('#exam_timer').TimeCircles().stop();
        alert('Exam time is over!');
        window.location = 'enroll_exam.php';
    }
});