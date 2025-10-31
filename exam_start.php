<?php
include_once 'header.php';
include_once('master/Examination.php');
$exam = new Examination;

// Start session safely
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// exam id and optional timezone info (tz_name preferred)
$exam_id = isset($_GET['exam_id']) ? intval($_GET['exam_id']) : 1;
$tz_name = isset($_GET['tz_name']) ? trim($_GET['tz_name']) : null;
$tz_offset = isset($_GET['tz_offset']) ? intval($_GET['tz_offset']) : null;

// Fetch exam schedule and validate current date matches exam date
// Use a direct PDO query here to avoid issues with the Examination helper parameter state.
try {
    $pdo = new PDO("mysql:host=localhost;dbname=online_examination", "root", "");
    $stmt = $pdo->prepare("SELECT online_exam_datetime, online_exam_duration, online_exam_status FROM online_exam_table WHERE online_exam_id = :exam_id LIMIT 1");
    $stmt->bindValue(':exam_id', $exam_id, PDO::PARAM_INT);
    $stmt->execute();
    $exam_result = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Fall back to using the Examination helper if direct PDO fails
    $exam->query = "SELECT online_exam_datetime, online_exam_duration, online_exam_status FROM online_exam_table WHERE online_exam_id = :exam_id LIMIT 1";
    $exam->data = array(':exam_id' => $exam_id);
    $exam_result = $exam->query_result();
}

$questions = array();
$allow_start = false;
$scheduled_date = '';
if (!empty($exam_result)) {
    $scheduled = $exam_result[0]['online_exam_datetime'];

    // Prefer IANA tz_name when provided
    if (!empty($tz_name)) {
        try {
            $serverTz = new DateTimeZone(date_default_timezone_get());
            $dt = new DateTime($scheduled, $serverTz);
            $userTz = new DateTimeZone($tz_name);
            $dt->setTimezone($userTz);
            $scheduled_local_date = $dt->format('Y-m-d');

            $now = new DateTime('now', $userTz);
            $current_date = $now->format('Y-m-d');

            if ($scheduled_local_date === $current_date) {
                $allow_start = true;
            }

            // store human-readable scheduled_date for display
            $scheduled_date = $dt->format('Y-m-d');
        } catch (Exception $e) {
            // invalid tz_name — fall back to other checks
            $tz_name = null;
        }
    }

    if (!$allow_start) {
        // fallback to tz_offset if provided
        if ($tz_offset !== null) {
            $user_now_ts = time() - ($tz_offset * 60);
            $current_date = date('Y-m-d', $user_now_ts);

            $scheduled_ts = strtotime($scheduled);
            $scheduled_local_date = date('Y-m-d', $scheduled_ts - ($tz_offset * 60));

            if ($scheduled_local_date === $current_date) {
                $allow_start = true;
            }
            $scheduled_date = $scheduled_local_date;
        } else {
            // last resort: server-local date
            $scheduled_date = date('Y-m-d', strtotime($scheduled));
            $current_date = date('Y-m-d');
            if ($scheduled_date === $current_date) {
                $allow_start = true;
            }
        }
    }
}

// If user's timezone check didn't allow start, also allow when the server-side scheduled date equals server's current date
if (!$allow_start && !empty($scheduled)) {
    $server_scheduled_date = date('Y-m-d', strtotime($scheduled));
    $server_current_date = date('Y-m-d');
    if ($server_scheduled_date === $server_current_date) {
        $allow_start = true;
        // prefer showing the server-local scheduled date
        $scheduled_date = $server_scheduled_date;
    }
}

if ($allow_start) {
    // Fetch unique questions for this exam using direct PDO to avoid parameter binding issues
    try {
        $qstmt = $pdo->prepare("SELECT DISTINCT question_id, question_title FROM question_table WHERE online_exam_id = :exam_id ORDER BY question_id ASC");
        $qstmt->bindValue(':exam_id', $exam_id, PDO::PARAM_INT);
        $qstmt->execute();
        $questions = $qstmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Fallback to Examination helper
        $exam->query = "
            SELECT DISTINCT question_id, question_title 
            FROM question_table 
            WHERE online_exam_id = $exam_id
            ORDER BY question_id ASC
        ";
        $questions = $exam->query_result();
    }
}
?>

<?php if ($allow_start && !empty($questions)): ?>
<div class="container mt-5">
    <div class="card shadow-lg">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h3 class="mb-0">Online Examination</h3>
            <div>
                <button type="button" id="shuffleBtn" class="btn btn-warning me-2">
                    Shuffle Questions
                </button>
                <button type="button" id="resetOrderBtn" class="btn btn-secondary" disabled>
                    Reset Order
                </button>
            </div>
        </div>
        <div class="card-body">
            <form action="exam_result.php" method="POST" id="examForm">
                <input type="hidden" name="exam_id" value="<?php echo htmlspecialchars($exam_id); ?>">

                <div id="questionsContainer">
                    <?php
                    foreach ($questions as $q) {
                        echo '<div class="mb-4 p-3 border rounded bg-light question-card" data-qid="'.$q['question_id'].'">';
                        echo '<h5>' . htmlspecialchars($q['question_title']) . '</h5>';

                        // Fetch options via PDO to avoid Examination helper parameter issues
                        try {
                            $optStmt = $pdo->prepare("SELECT DISTINCT option_number, option_title FROM option_table WHERE question_id = :qid ORDER BY option_number ASC");
                            $optStmt->bindValue(':qid', intval($q['question_id']), PDO::PARAM_INT);
                            $optStmt->execute();
                            $options = $optStmt->fetchAll(PDO::FETCH_ASSOC);
                        } catch (Exception $e) {
                            $exam->query = "
                                SELECT DISTINCT option_number, option_title 
                                FROM option_table 
                                WHERE question_id = " . intval($q['question_id']) . "
                                ORDER BY option_number ASC
                            ";
                            $options = $exam->query_result();
                        }

                        foreach ($options as $opt) {
                            echo '
                            <div class="form-check">
                                <input class="form-check-input" type="radio" 
                                       name="question_' . $q['question_id'] . '" 
                                       id="q' . $q['question_id'] . '_opt' . $opt['option_number'] . '" 
                                       value="' . $opt['option_number'] . '" required>
                                <label class="form-check-label" for="q' . $q['question_id'] . '_opt' . $opt['option_number'] . '">
                                    ' . htmlspecialchars($opt['option_title']) . '
                                </label>
                            </div>';
                        }

                        echo '</div>';
                    }
                    ?>
                </div>

                <div class="text-center mt-4">
                    <button type="submit" class="btn btn-success px-4 py-2">
                        Submit Exam
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php else: ?>
    <div class="container mt-5">
        <div class="card shadow-sm">
            <div class="card-body text-center">
                <h3 class="text-primary">Exam Not Available</h3>
                <?php if ($scheduled_date): ?>
                    <p class="lead">This exam is scheduled on <strong><?php echo htmlspecialchars(date('F j, Y', strtotime($scheduled_date))); ?></strong>.</p>
                <?php else: ?>
                    <p class="lead">Exam schedule not found. Please contact the administrator.</p>
                <?php endif; ?>
                <a href="view_exam.php?exam_id=<?php echo urlencode($exam_id); ?>" class="btn btn-outline-primary mt-3">View Exam Details</a>
                <a href="enroll_exam.php" class="btn btn-secondary mt-3 ms-2">Back to Exams</a>
            </div>
        </div>
    </div>
<?php endif; ?>

<style>
body { background: #f7f9fb; }
.card { border-radius: 10px; }
.form-check-input:checked {
    background-color: #0d6efd;
    border-color: #0d6efd;
}
/* Shuffle animation styles */
.question-card {
    transition: transform 300ms ease, opacity 300ms ease;
}
.question-card.reorder-anim {
    transform: translateY(10px) scale(0.98);
    opacity: 0.9;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const shuffleBtn = document.getElementById('shuffleBtn');
    const container = document.getElementById('questionsContainer');

    function shuffleArray(arr) {
        for (let i = arr.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [arr[i], arr[j]] = [arr[j], arr[i]];
        }
    }

    if (shuffleBtn && container) {
        const resetBtn = document.getElementById('resetOrderBtn');
        // capture original order of question-card elements (references)
        const originalOrder = Array.from(container.querySelectorAll('.question-card'));
        let animating = false;

        function applyReorderAnim(cards) {
            cards.forEach(c => c.classList.add('reorder-anim'));
            // ensure the animation class is applied before DOM changes
            return new Promise(resolve => setTimeout(resolve, 50));
        }

        function clearReorderAnim(cards) {
            // trigger reflow then remove class to animate to normal
            window.requestAnimationFrame(() => {
                cards.forEach(c => c.classList.remove('reorder-anim'));
            });
        }

        shuffleBtn.addEventListener('click', async function() {
            if (animating) return;
            const currentCards = Array.from(container.querySelectorAll('.question-card'));
            if (currentCards.length <= 1) return;

            animating = true;
            shuffleBtn.disabled = true;

            // Shuffle the current nodes so selections remain attached
            const shuffled = currentCards.slice();
            shuffleArray(shuffled);

            await applyReorderAnim(currentCards);

            container.innerHTML = '';
            shuffled.forEach(card => container.appendChild(card));

            clearReorderAnim(shuffled);

            // Update numbering
            container.querySelectorAll('.question-card').forEach((qCard, index) => {
                const h5 = qCard.querySelector('h5');
                if (!h5) return;
                const text = h5.textContent.replace(/^\d+\.\s*/, '');
                h5.textContent = (index + 1) + '. ' + text;
            });

            // enable reset button
            if (resetBtn) resetBtn.disabled = false;

            shuffleBtn.disabled = false;
            animating = false;
        });

        if (resetBtn) {
            resetBtn.addEventListener('click', async function() {
                if (animating) return;
                const currentCards = Array.from(container.querySelectorAll('.question-card'));
                if (currentCards.length <= 1) return;

                animating = true;
                resetBtn.disabled = true;
                shuffleBtn.disabled = true;

                await applyReorderAnim(currentCards);

                container.innerHTML = '';
                originalOrder.forEach(card => container.appendChild(card));

                clearReorderAnim(originalOrder);

                // Update numbering
                container.querySelectorAll('.question-card').forEach((qCard, index) => {
                    const h5 = qCard.querySelector('h5');
                    if (!h5) return;
                    const text = h5.textContent.replace(/^\d+\.\s*/, '');
                    h5.textContent = (index + 1) + '. ' + text;
                });

                shuffleBtn.disabled = false;
                animating = false;
            });
        }
    }
});
</script>
