<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    @session_start();
}

// For logged-in users, just close the container div and body/html
if (isset($_SESSION['user_id'])) {
    echo "</div>\n"; // close container-fluid
    echo "</body>\n</html>";
    return;
}

// For non-logged-in users, show the full footer
?>
<footer>
  <div class="footer-grid">
    <div class="footer-col">
      <h4>ExamPro</h4>
      <p>Secure, flexible online examination platform trusted by educators. Create, deliver and grade exams with ease.</p>
      <div class="social" aria-label="social links">
        <a href="#" title="Twitter"><i class="fa-brands fa-twitter"></i></a>
        <a href="#" title="Facebook"><i class="fa-brands fa-facebook-f"></i></a>
        <a href="#" title="LinkedIn"><i class="fa-brands fa-linkedin-in"></i></a>
      </div>
    </div>

    <div class="footer-col">
      <h4>Quick Links</h4>
      <nav class="footer-links" aria-label="Footer navigation">
        <a href="login.php">Login</a>
        <a href="register.php">Register</a>
        <a href="exam.php">Browse Exams</a>
        <a href="#about">About</a>
      </nav>
    </div>

    <div class="footer-col">
      <h4>Contact</h4>
      <p>Phone: +1 (555) 123-4567</p>
      <p>Email: <a href="mailto:support@exampro.example">support@exampro.example</a></p>
      <p style="margin-top:.6rem;color:#6b7280">123 Education Ave, Suite 400<br />City, Country</p>
    </div>

    <div class="footer-col newsletter">
      <h4>Stay informed</h4>
      <p class="muted">Subscribe for product updates and exam tips.</p>
      <form onsubmit="event.preventDefault();alert('Thanks — subscription simulated.');" aria-label="Subscribe form">
        <input type="email" placeholder="Your email" required />
        <button type="submit">Subscribe</button>
      </form>
    </div>
  </div>

  <div style="margin-top:1.4rem;border-top:1px solid rgba(2,6,23,0.04);padding-top:1rem;color:#6b7280;text-align:center">
    &copy; 2025 ExamPro — All rights reserved. &nbsp;|&nbsp; <a href="#">Privacy</a> &nbsp;|&nbsp; <a href="#">Terms</a>
  </div>
</footer>

<!-- Footer scripts -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
<script>
  // small accessible focus for keyboard users
  document.querySelectorAll('a.btn, a.btn.secondary').forEach(function(el){
    el.addEventListener('keydown', function(e){ if(e.key === 'Enter'){ el.click(); } });
  });
</script>
</body>
</html>
