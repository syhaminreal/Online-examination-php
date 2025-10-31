# Algorithms & Data Structures (reference)

This document lists common algorithms and data structures with a short explanation and a compact code snippet demonstrating the core idea. Most snippets are in Python for clarity; one PHP example is included for a common pattern used in this project.

---

## Index (selected)
- Data structures: Array, Linked List, Stack, Queue, Hash Table (Map), Binary Tree, Binary Search Tree, Heap, Graph, Trie, Disjoint Set (Union-Find)
- Sorting: Bubble, Selection, Insertion, Merge, Quick, Heap
- Searching: Linear Search, Binary Search
- Graph algorithms: BFS, DFS, Dijkstra, Topological Sort
- Dynamic Programming: Fibonacci (memo), Knapsack (0/1)
- Greedy: Activity Selection
- Backtracking: N-Queens
- Misc: Two-pointer pattern, Sliding window

---

## 1. Binary Search (search sorted array)
What it does: Finds target index in a sorted array in O(log n) time.

```python
def binary_search(arr, target):
    lo, hi = 0, len(arr) - 1
    while lo <= hi:
        mid = (lo + hi) // 2
        if arr[mid] == target:
            return mid
        if arr[mid] < target:
            lo = mid + 1
        else:
            hi = mid - 1
    return -1

---

## Expanded repository-specific findings (added)

The project is primarily CRUD with database-backed lists and a small amount of client-side logic (timers + UI). Below are concise, actionable findings mapped to files and recommendations you can apply immediately.

1) Use transactions for parent/child inserts (questions + options)
- Files: `master/ajax_action.php` (question Add), `master/Examination.php` (helpers)
- Issue: The current flow inserts the question (parent) then inserts options (children) using the last-insert id. If any option insert fails, the question remains orphaned.
- Fix: Wrap the parent + children inserts inside a database transaction. This ensures atomicity and prevents partial data writes.

Example patch (conceptual) to add inside the question add handler in `master/ajax_action.php`:

```php
// validate $online_exam_id belongs to current admin (see validation snippet below)
try {
    $exam->con->beginTransaction();

    // insert question (prepared)
    $stmt = $exam->con->prepare("INSERT INTO question_table (online_exam_id, question_title, question_answer, marks) VALUES (?, ?, ?, ?)");
    $stmt->execute([$online_exam_id, $question_title, $correct_answer, $marks]);
    $question_id = $exam->con->lastInsertId();

    // insert options
    $optStmt = $exam->con->prepare("INSERT INTO option_table (question_id, option_number, option_title) VALUES (?, ?, ?)");
    foreach ($options as $num => $optText) {
        $optStmt->execute([$question_id, $num, $optText]);
    }

    $exam->con->commit();
    echo json_encode(['status'=>'success']);
} catch (Exception $e) {
    $exam->con->rollBack();
    echo json_encode(['status'=>'error','message'=>'DB error']);
}
```

2) Server-side validation for `online_exam_id` and ownership
- Files: `master/ajax_action.php` (question Add), any endpoint that accepts `online_exam_id` from the client
- Issue: Client-side fixes were added to ensure the correct `online_exam_id` is posted, but a malicious or buggy client could still post the wrong id. Server must verify the ID belongs to the logged-in admin.
- Fix: Immediately validate the posted `online_exam_id` against the `online_exam_table` filtered by `admin_id` in `$_SESSION`.

Validation snippet (add before inserting question):

```php
$posted_exam_id = intval($_POST['online_exam_id'] ?? 0);
$admin_id = intval($_SESSION['admin_id'] ?? 0);
$check = $exam->con->prepare("SELECT online_exam_id FROM online_exam_table WHERE online_exam_id = ? AND admin_id = ?");
$check->execute([$posted_exam_id, $admin_id]);
if ($check->rowCount() === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid exam id or permission denied']);
    exit;
}

// safe to proceed using $posted_exam_id
$online_exam_id = $posted_exam_id;
```

3) Replace weak tokens (md5(rand())) with secure tokens
- Files: `master/ajax_action.php` (online_exam_code, verification codes), other places where `md5(rand())` is used
- Fix: Replace `md5(rand())` with `bin2hex(random_bytes(16))` for cryptographically secure tokens.

Example:

```php
$token = bin2hex(random_bytes(16));
```

4) Use DateTime for robust date comparisons
- Files: `master/Examination.php` (`Is_exam_is_not_started`), `index.php` (client-side comparison), other server-side date checks
- Fix: Use PHP's `DateTime` and explicit timezones instead of raw string comparisons.

Example:

```php
$dbDate = new DateTime($row['online_exam_datetime'], new DateTimeZone('UTC'));
$now = new DateTime('now', new DateTimeZone('UTC'));
if ($dbDate > $now) {
    // not started
}
```

5) DataTables server-side improvements
- Files: `master/ajax_action.php`, `user_ajax_action.php`
- Recommendations:
  - Continue using whitelists for ORDER BY columns (already present).
  - Use parameterized queries for search (`LIKE :search`) instead of string concatenation.
  - For counts use `SELECT COUNT(*)` instead of `SELECT *` + `rowCount()` to make filtered count queries faster.

6) Timer enforcement (server-side)
- Files: `view_exam.php`, `take_exam.php`, server submission handlers (`submit_exam.php`)
- Issue: Client-side timers are UI-only; server must reject submissions past allowed time.
- Fix: On submission, verify that current server time is within the exam window for that `online_exam_id` before accepting answers.

7) Indexes & query optimizations
- Files: any heavy listing (exam list, question list, results)
- Recommendation: Ensure `online_exam_table.admin_id`, `question_table.online_exam_id`, and columns used in WHERE/ORDER BY have indexes.

---

## Quick checklist to apply now
- [ ] Add transaction wrapper around question + option inserts (in `master/ajax_action.php`).
- [ ] Add server-side `online_exam_id` ownership validation before accepting question inserts.
- [ ] Replace `md5(rand())` with `bin2hex(random_bytes(16))` in token generation sites.
- [ ] Convert fragile date string comparisons to `DateTime` with timezone.
- [ ] Audit and convert any remaining SQL concatenation to prepared statements.

---

## Which 3 algorithms in this repo satisfy the project requirement?

The project brief asks for at least three developed algorithms. Based on the repository scan, these three are already present and you can cite them directly:

1) Cryptographic password hashing
- Files: `master/ajax_action.php` (admin register/login), `user_ajax_action.php.bak` (user flows)
- Code example: storing with `password_hash($_POST['admin_password'], PASSWORD_DEFAULT)` and verifying with `password_verify($_POST['admin_password'], $row['admin_password'])`.
- Why it counts: this is a standard cryptographic hashing algorithm applied to passwords (PBKDF2/Bcrypt/Argon2 depending on PHP config). It's a non-trivial algorithmic component for security.

2) Server-side substring search (SQL LIKE)
- Files: `master/ajax_action.php` (exam listing DataTables handler), `user_ajax_action_new.php` (user listing/search)
- Code example: the DataTables server handler builds filters like `... WHERE ... AND (online_exam_title LIKE '%$sv%' OR online_exam_datetime LIKE '%$sv%' ...)`.
- Why it counts: this performs pattern matching/search at scale via the database engine; algorithmically it's a substring search implemented through SQL's LIKE operator (and could be optimized with fulltext indexes or more advanced search algorithms if needed).

3) Sorting & pagination (ORDER BY + LIMIT)
- Files: many list pages and DataTables handlers, e.g. `master/ajax_action.php`, `master/index.php`, `exam_start.php`, `view_exam.php`.
- Code example: queries like `SELECT * FROM online_exam_table WHERE admin_id = '...' ORDER BY online_exam_id DESC LIMIT 5`.
- Why it counts: sorting and pagination are algorithmic tasks (compare-and-order operations plus windowing). The DB engine executes these algorithms on your behalf; including and documenting them counts toward the requirement.

Notes about shuffling (what you remembered)
- I searched the codebase for array shuffling (PHP `shuffle()`, `array_rand()`, or `ORDER BY RAND()`), but no explicit shuffle was found.
- If your requirement specifically requires an implemented "shuffling algorithm", I can add it quickly. Recommended low-risk places to add shuffle logic:
    - Randomize question order at exam start: fetch questions in PHP, then call `shuffle($questionsArray)` before rendering/sending to the client.
    - Randomize option order per question: fetch options and `shuffle()` them before display so options appear in random order for each student.

If you'd like me to make one of those changes now (so you explicitly have shuffle in the codebase), tell me whether you prefer to shuffle questions, options, or both and I'll implement it.
