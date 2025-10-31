<?php
// We don't need to start session here as it's already started in Examination.php
// Include site header (loads Bootstrap, styles and scripts)
include_once 'header.php';

// Include required class and initialize
include_once('master/Examination.php');
$exam = new Examination;

$exam_id = null;

// Read and normalize exam code from GET to avoid repeating raw accesses
$code = '';
if (isset($_GET['code'])) {
    $code = trim($_GET['code']);
}

// Identify which exam is being accessed
if (isset($_GET['id'])) {
    $exam_id = $_GET['id'];
} elseif (isset($_GET['exam_id'])) {
    $exam_id = $_GET['exam_id'];
} elseif ($code !== '') {
    // Lookup exam_id using exam code (escape to avoid breaking query)
    $escaped_code = addslashes($code);
    $exam->query = "
        SELECT online_exam_id 
        FROM online_exam_table 
        WHERE online_exam_code = '$escaped_code' 
        LIMIT 1
    ";
    $result = $exam->query_result();
    if (!empty($result)) {
        $exam_id = $result[0]['online_exam_id'];
    }
}

// Initialize exam status and time details
$exam_status = '';
$exam_datetime = '';
$exam_duration = 0;
$current_time = time();
$message = '';
$message_type = 'warning';
$can_take_exam = false;

if ($exam_id) {
    // Get exam details including datetime and duration
    $exam->query = "
        SELECT online_exam_datetime, online_exam_duration, online_exam_status
        FROM online_exam_table 
        WHERE online_exam_id = '$exam_id'
    ";
    $result = $exam->query_result();
    if (!empty($result)) {
        $exam_status = $result[0]['online_exam_status'];
        $exam_datetime = strtotime($result[0]['online_exam_datetime']);
        $exam_duration = intval($result[0]['online_exam_duration']) * 60; // Convert minutes to seconds
        
        // Get dates for comparison
        $exam_date = date('Y-m-d', $exam_datetime);
        $current_date = date('Y-m-d', $current_time);
        
        // If same day, allow exam anytime during the day
        if ($exam_date === $current_date) {
            $message = "Exam is available today! You can start anytime.";
            $message_type = 'success';
            $can_take_exam = true;
        }
        // If future date
        elseif ($exam_date > $current_date) {
            $days_until = floor(($exam_datetime - $current_time) / (60 * 60 * 24));
            if ($days_until == 1) {
                $message = "This exam starts tomorrow on " . date('F j, Y', $exam_datetime);
            } else {
                $message = "This exam starts in $days_until days on " . date('F j, Y', $exam_datetime);
            }
            $message_type = 'info';
        }
        // If past date
        else {
            $message = "This exam was scheduled for " . date('F j, Y', $exam_datetime);
            $message_type = 'danger';
        }
    }
}

// Handle different exam states
if ($exam_status == 'Completed') {

    // Fetch question and user answer data
    $exam->query = "
        SELECT * FROM question_table 
        INNER JOIN user_exam_question_answer 
        ON user_exam_question_answer.question_id = question_table.question_id 
        WHERE question_table.online_exam_id = '$exam_id' 
        AND user_exam_question_answer.user_id = '" . $_SESSION["user_id"] . "'
    ";
    $result = $exam->query_result();
    ?>

    <div class="card">
        <div class="card-header">
            <div class="row">
                <div class="col-md-8">
                    <h4 class="mb-0">Online Exam Questions</h4>
                </div>
                <div class="col-md-4 text-end">
                    <div id="exam_timer" class="text-danger h4"></div>
                </div>
            </div>
        </div>

        <div class="card-body">
            <!-- Progress Bar -->
            <div class="progress mb-4" style="height: 10px;">
                <div class="progress-bar" role="progressbar" style="width: 0%" 
                     aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" id="examProgress"></div>
            </div>
            
            <form id="exam_form" method="post" action="exam_result.php">
                <input type="hidden" name="exam_id" value="<?php echo $exam_id; ?>">
                <div id="exam_questions">
                <?php
                $question_number = 1;
                $questions_per_page = 4;
                $total_questions = count($result);
                $total_pages = ceil($total_questions / $questions_per_page);
                
                foreach ($result as $row) {
                    // Get options for this question
                    $exam->query = "
                        SELECT * FROM option_table 
                        WHERE question_id = '" . $row["question_id"] . "'
                        ORDER BY option_number
                    ";
                    $options = $exam->query_result();
                    
                    // Calculate page number for this question
                    $page_number = ceil($question_number / $questions_per_page);
                    
                    // Start a new page container if this is the first question of a page
                    if (($question_number - 1) % $questions_per_page === 0) {
                        echo '<div class="question-page" data-page="' . $page_number . '" ' .
                             'style="display: ' . ($page_number === 1 ? 'block' : 'none') . ';">';
                    }
                    ?>
                    <div class="question-container mb-4 p-4 border rounded bg-light">
                        <h5 class="card-title border-bottom pb-2">Question <?php echo $question_number; ?></h5>
                        <p class="question-text h5 mb-4"><?php echo htmlspecialchars($row['question_title']); ?></p>
                        
                        <div class="options-container">
                            <?php foreach ($options as $option): ?>
                            <div class="form-check mb-3">
                                <input class="form-check-input answer-input" type="radio" 
                                       name="question_<?php echo $row['question_id']; ?>" 
                                       id="option_<?php echo $row['question_id'].'_'.$option['option_number']; ?>"
                                       value="<?php echo $option['option_number']; ?>"
                                       data-question="<?php echo $question_number; ?>"
                                       required>
                                <label class="form-check-label h6" 
                                       for="option_<?php echo $row['question_id'].'_'.$option['option_number']; ?>">
                                    <?php echo htmlspecialchars($option['option_title']); ?>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                    </div>
                    <?php
                    // Close the page container if this is the last question of a page or the last question overall
                    if ($question_number % $questions_per_page === 0 || $question_number === $total_questions) {
                        ?>
                        <div class="page-nav d-flex justify-content-between align-items-center mt-4 pt-3 border-top">
                            <button type="button" class="btn btn-secondary prev-page" 
                                    <?php echo $page_number === 1 ? 'disabled' : ''; ?>>
                                <i class="fas fa-arrow-left me-2"></i> Previous Page
                            </button>
                            <div class="text-center">
                                <span class="badge bg-primary px-3 py-2">
                                    Page <?php echo $page_number; ?> of <?php echo $total_pages; ?>
                                </span>
                            </div>
                            <?php if ($page_number === $total_pages): ?>
                            <button type="submit" class="btn btn-success submit-exam">
                                <i class="fas fa-paper-plane me-2"></i> Submit Exam
                            </button>
                            <?php else: ?>
                            <button type="button" class="btn btn-primary next-page">
                                Next Page <i class="fas fa-arrow-right ms-2"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                        </div><!-- Close page container -->
                        <?php
                    }
                    $question_number++;
                }
                ?>
                </div>
                
                <div class="d-grid gap-2 col-6 mx-auto mt-4">
                    <button type="submit" class="btn btn-primary btn-lg shadow">
                        <i class="fas fa-paper-plane me-2"></i> Submit Answers
                    </button>
                </div>
            </form>

            </div>
        </div>
    </div>

<?php
} elseif ($exam_status == 'Started') {
    // If exam is ongoing
    echo '
    <div class="card">
        <div class="card-header">Exam In Progress</div>
        <div class="card-body">
            The exam is currently active. Please complete it before checking the result.
        </div>
    </div>';
} else {
    // Show exam availability status
    ?>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow-lg border-0">
                    <div class="card-body text-center p-5">
                        <div class="display-1 text-<?php echo $message_type; ?> mb-4">
                            <?php if ($can_take_exam): ?>
                                <i class="fas fa-play-circle"></i>
                            <?php elseif ($message_type == 'info'): ?>
                                <i class="fas fa-clock"></i>
                            <?php else: ?>
                                <i class="fas fa-calendar-times"></i>
                            <?php endif; ?>
                        </div>
                        
                        <h2 class="card-title mb-4 text-primary">
                            <?php echo $can_take_exam ? 'Exam Ready to Start' : 'Exam Not Available'; ?>
                        </h2>
                        
                        <div class="alert alert-<?php echo $message_type; ?> py-3 px-4 mb-4 mx-auto" style="max-width: 80%;">
                            <p class="lead mb-2"><?php echo htmlspecialchars($message); ?></p>
                            <p class="mb-0">
                                Scheduled Time: <br>
                                <strong><?php echo date('F j, Y, g:i a', $exam_datetime); ?></strong>
                                <br>
                                Duration: <strong><?php echo floor($exam_duration / 60); ?> minutes</strong>
                            </p>
                        </div>
                        
                        <?php if ($can_take_exam): ?>
                        <div class="d-grid gap-2 col-8 mx-auto mb-3">
                            <button onclick="startExam()" class="btn btn-success btn-lg shadow">
                                <i class="fas fa-play-circle me-2"></i> Start Exam Now
                            </button>
                        </div>
                        <?php endif; ?>
                        
                        <div class="d-grid gap-2 col-6 mx-auto">
                            <a href="enroll_exam.php" class="btn <?php echo $can_take_exam ? 'btn-outline-primary' : 'btn-primary'; ?> btn-lg shadow">
                                <i class="fas fa-arrow-left me-2"></i> Return to Exam List
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Exam Info Modal (always shown) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <div class="modal fade" id="examModal" tabindex="-1" aria-labelledby="examModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header bg-<?php echo $message_type; ?> text-white">
            <h5 class="modal-title" id="examModalLabel">
              <i class="fas fa-info-circle me-2"></i> Exam Information
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body text-center">
            <p class="lead mb-2"><?php echo htmlspecialchars($message); ?></p>
            <p class="mb-0">
              Scheduled Date: <strong><?php echo date('F j, Y', $exam_datetime); ?></strong><br>
              Duration: <strong><?php echo floor($exam_duration / 60); ?> minutes</strong>
            </p>
          </div>
          <div class="modal-footer justify-content-center">
            <?php if ($can_take_exam): ?>
            <button type="button" class="btn btn-success btn-lg" onclick="startExam()">
              <i class="fas fa-play-circle me-2"></i> Take Exam
            </button>
            <?php else: ?>
            <button type="button" class="btn btn-secondary btn-lg" disabled title="You cannot take this exam now">
              <i class="fas fa-ban me-2"></i> Take Exam
            </button>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
    <script>
      // Add Chart.js
      document.addEventListener('DOMContentLoaded', function() {
        // Initialize exam timer if needed
        if (document.getElementById('exam_timer')) {
            var duration = <?php echo $exam_duration; ?>;
            startTimer(duration, document.getElementById('exam_timer'));
        }
        
        // Handle form submission
        const examForm = document.getElementById('exam_form');
        if (examForm) {
            examForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Collect all answers
                const formData = new FormData(examForm);
                const answers = {};
                for (let pair of formData.entries()) {
                    answers[pair[0]] = pair[1];
                }
                
                // Send answers to server
                fetch('submit_exam.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        exam_id: '<?php echo $exam_id; ?>',
                        answers: answers,
                        tz_name: Intl.DateTimeFormat().resolvedOptions().timeZone
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showResults(data.results);
                    } else {
                        alert('Error submitting exam: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error submitting exam. Please try again.');
                });
            });
        }
      });

      function startTimer(duration, display) {
          var timer = duration, minutes, seconds;
          var countdown = setInterval(function () {
              minutes = parseInt(timer / 60, 10);
              seconds = parseInt(timer % 60, 10);

              minutes = minutes < 10 ? "0" + minutes : minutes;
              seconds = seconds < 10 ? "0" + seconds : seconds;

              display.textContent = minutes + ":" + seconds;

              if (--timer < 0) {
                  clearInterval(countdown);
                  document.getElementById('exam_form').submit();
              }
          }, 1000);
      }

      function showResults(results) {
          // Create results container
          const resultsDiv = document.createElement('div');
          resultsDiv.innerHTML = `
              <div class="card mt-4">
                  <div class="card-header">
                      <h4>Exam Results</h4>
                  </div>
                  <div class="card-body">
                      <div class="row">
                          <div class="col-md-6">
                              <canvas id="resultsChart"></canvas>
                          </div>
                          <div class="col-md-6">
                              <div class="results-summary">
                                  <h5>Summary</h5>
                                  <p>Total Questions: ${results.total}</p>
                                  <p>Correct Answers: ${results.correct}</p>
                                  <p>Score: ${results.percentage}%</p>
                              </div>
                          </div>
                      </div>
                  </div>
              </div>
          `;
          
          // Replace form with results
          document.getElementById('exam_form').replaceWith(resultsDiv);
          
          // Create chart
          const ctx = document.getElementById('resultsChart').getContext('2d');
          new Chart(ctx, {
              type: 'pie',
              data: {
                  labels: ['Correct', 'Incorrect'],
                  datasets: [{
                      data: [results.correct, results.total - results.correct],
                      backgroundColor: ['#28a745', '#dc3545']
                  }]
              },
              options: {
                  responsive: true,
                  plugins: {
                      legend: {
                          position: 'bottom'
                      }
                  }
              }
          });
      }
    </script>
    <!-- Add Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .question-container {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        .question-container:hover {
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        .form-check-input:checked {
            background-color: #0d6efd;
            border-color: #0d6efd;
        }
        .form-check-input {
            width: 1.2em;
            height: 1.2em;
            margin-top: 0.25em;
        }
        .form-check-label {
            padding-left: 0.5em;
        }
        #exam_timer {
            font-size: 1.5rem;
            font-weight: bold;
            padding: 0.5rem;
            border-radius: 4px;
            background-color: #f8d7da;
        }
        .question-nav {
            border-top: 1px solid #dee2e6;
            padding-top: 1rem;
        }
        .progress {
            height: 10px;
            border-radius: 5px;
            background-color: #e9ecef;
        }
        .progress-bar {
            transition: width 0.3s ease;
            background-color: #28a745;
        }
        .question-container {
            display: none;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .question-container.active {
            display: block;
            opacity: 1;
        }
        .badge {
            font-size: 0.9rem;
        }
        .next-question, .prev-question {
            transition: all 0.2s ease;
        }
        .next-question:not(:disabled):hover, 
        .prev-question:not(:disabled):hover {
            transform: scale(1.05);
        }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const pages = document.querySelectorAll('.question-page');
            const progressBar = document.getElementById('examProgress');
            const totalQuestions = document.querySelectorAll('.question-container').length;
            let currentPage = 1;
            let answeredQuestions = new Set();
            
            // Update progress bar
            function updateProgress() {
                const progress = (answeredQuestions.size / totalQuestions) * 100;
                progressBar.style.width = progress + '%';
                progressBar.setAttribute('aria-valuenow', progress);
                
                // Update color based on progress
                if (progress < 33) {
                    progressBar.className = 'progress-bar bg-danger';
                } else if (progress < 66) {
                    progressBar.className = 'progress-bar bg-warning';
                } else {
                    progressBar.className = 'progress-bar bg-success';
                }
            }
            
            // Check if question is answered
            function checkAnswer(questionNumber) {
                const inputs = document.querySelectorAll(`[data-question="${questionNumber}"].answer-input`);
                for (let input of inputs) {
                    if (input.checked) {
                        answeredQuestions.add(questionNumber);
                        updateProgress();
                        return true;
                    }
                }
                return false;
            }
            
            // Handle navigation
            document.querySelectorAll('.next-question').forEach(button => {
                button.addEventListener('click', () => {
                    if (currentQuestion < totalQuestions) {
                        document.querySelector(`[data-question="${currentQuestion}"]`).style.display = 'none';
                        currentQuestion++;
                        document.querySelector(`[data-question="${currentQuestion}"]`).style.display = 'block';
                        updateNavButtons();
                    }
                });
            });
            
            document.querySelectorAll('.prev-question').forEach(button => {
                button.addEventListener('click', () => {
                    if (currentQuestion > 1) {
                        document.querySelector(`[data-question="${currentQuestion}"]`).style.display = 'none';
                        currentQuestion--;
                        document.querySelector(`[data-question="${currentQuestion}"]`).style.display = 'block';
                        updateNavButtons();
                    }
                });
            });
            
            // Update navigation button states
            function updateNavButtons() {
                document.querySelectorAll('.prev-question').forEach(button => {
                    button.disabled = currentQuestion === 1;
                });
                
                document.querySelectorAll('.next-question').forEach(button => {
                    if (currentQuestion === totalQuestions) {
                        button.style.display = 'none';
                    } else {
                        button.style.display = 'block';
                    }
                });
            }
            
            // Track answered questions
            document.querySelectorAll('.answer-input').forEach(input => {
                input.addEventListener('change', () => {
                    const questionNumber = parseInt(input.getAttribute('data-question'));
                    checkAnswer(questionNumber);
                });
            });
            
            // Initialize
            updateProgress();
            updateNavButtons();
            
            // Add form submission validation
            document.getElementById('exam_form').addEventListener('submit', function(e) {
                if (answeredQuestions.size < totalQuestions) {
                    e.preventDefault();
                    const unanswered = totalQuestions - answeredQuestions.size;
                    if (!confirm(`You have ${unanswered} unanswered question(s). Are you sure you want to submit?`)) {
                        return false;
                    }
                }
            });
        });
    </script>
    <?php
}
?>
