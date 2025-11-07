<?php
require_once 'config.php';
requireLogin();

$db = db();
$error = '';
$success = '';

/*Contact form submission code*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject = clean($_POST['subject']);
    $message = clean($_POST['message']);
    
    if ($subject && $message) {
        /*Saves and logs the support request*/
        logActivity($_SESSION['user_id'], 'Support Request', "Subject: $subject");
        
        $success = 'Your message has been sent successfully. Our support team will get back to you soon.';
    } else {
        $error = 'Please fill in all fields';
    }
}

$pageTitle = 'Contact Us';
$pageSubtitle = 'Get in touch with our support team';
include 'includes/header.php';
?>

<div class="main-body">
    <?php if ($error): ?>
        <div class="alert alert-error"><?= $error ?></div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php endif; ?>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
        <div class="card">
            <div class="card-header">
                <h3>Send Us a Message</h3>
                <p>Fill out the form below and we'll get back to you</p>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="form-group">
                        <label>Your Name</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($_SESSION['full_name']) ?>" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label>Your Email</label>
                        <input type="email" class="form-control" value="<?= htmlspecialchars(getUser($_SESSION['user_id'])['email']) ?>" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label>Subject</label>
                        <select name="subject" class="form-control" required>
                            <option value="">Select a subject</option>
                            <option value="Technical Support">Technical Support</option>
                            <option value="Account Issue">Account Issue</option>
                            <option value="Fingerprint Registration">Fingerprint Registration</option>
                            <option value="Attendance Report">Attendance Report</option>
                            <option value="Schedule Issue">Schedule Issue</option>
                            <option value="Feature Request">Feature Request</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Message</label>
                        <textarea name="message" class="form-control" rows="6" placeholder="Describe your issue or question..." required></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Send Message</button>
                </form>
            </div>
        </div>

        <div>
            <div class="card">
                <div class="card-header">
                    <h3>Contact Information</h3>
                </div>
                <div class="card-body">
                    <div style="display: flex; flex-direction: column; gap: 20px;">
                        <div style="display: flex; gap: 16px;">
                            <div style="width: 48px; height: 48px; background: var(--emerald-100); border-radius: 12px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 24px; height: 24px; color: var(--emerald-600);">
                                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                                    <polyline points="22,6 12,13 2,6"/>
                                </svg>
                            </div>
                            <div>
                                <h4 style="font-weight: 600; margin-bottom: 4px;">Email</h4>
                                <p style="color: var(--gray-600);">support@bpc.edu.ph</p>
                            </div>
                        </div>

                        <div style="display: flex; gap: 16px;">
                            <div style="width: 48px; height: 48px; background: var(--emerald-100); border-radius: 12px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 24px; height: 24px; color: var(--emerald-600);">
                                    <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                                </svg>
                            </div>
                            <div>
                                <h4 style="font-weight: 600; margin-bottom: 4px;">Phone</h4>
                                <p style="color: var(--gray-600);">(044) 123-4567</p>
                            </div>
                        </div>

                        <div style="display: flex; gap: 16px;">
                            <div style="width: 48px; height: 48px; background: var(--emerald-100); border-radius: 12px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 24px; height: 24px; color: var(--emerald-600);">
                                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                                    <circle cx="12" cy="10" r="3"/>
                                </svg>
                            </div>
                            <div>
                                <h4 style="font-weight: 600; margin-bottom: 4px;">Location</h4>
                                <p style="color: var(--gray-600);">Bulacan Polytechnic College<br>Bulacan, Philippines</p>
                            </div>
                        </div>

                        <div style="display: flex; gap: 16px;">
                            <div style="width: 48px; height: 48px; background: var(--emerald-100); border-radius: 12px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 24px; height: 24px; color: var(--emerald-600);">
                                    <circle cx="12" cy="12" r="10"/>
                                    <polyline points="12 6 12 12 16 14"/>
                                </svg>
                            </div>
                            <div>
                                <h4 style="font-weight: 600; margin-bottom: 4px;">Office Hours</h4>
                                <p style="color: var(--gray-600);">Monday - Friday<br>8:00 AM - 5:00 PM</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card" style="margin-top: 24px;">
                <div class="card-header">
                    <h3>Quick Support</h3>
                </div>
                <div class="card-body">
                    <p style="color: var(--gray-600); margin-bottom: 16px;">For immediate assistance with common issues:</p>
                    <div style="display: flex; flex-direction: column; gap: 8px;">
                        <a href="change_password.php" style="display: flex; align-items: center; gap: 8px; padding: 12px; background: var(--gray-50); border-radius: 8px; text-decoration: none; color: var(--gray-700);">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 16px; height: 16px; color: var(--emerald-600);">
                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                                <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                            </svg>
                            Password Reset
                        </a>
                        <a href="profile.php" style="display: flex; align-items: center; gap: 8px; padding: 12px; background: var(--gray-50); border-radius: 8px; text-decoration: none; color: var(--gray-700);">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 16px; height: 16px; color: var(--emerald-600);">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                                <circle cx="12" cy="7" r="4"/>
                            </svg>
                            Update Profile
                        </a>
                        <a href="attendance_reports.php" style="display: flex; align-items: center; gap: 8px; padding: 12px; background: var(--gray-50); border-radius: 8px; text-decoration: none; color: var(--gray-700);">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 16px; height: 16px; color: var(--emerald-600);">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                            </svg>
                            View Reports
                        </a>
                    </div>
                </div>
            </div>

            <div class="card" style="margin-top: 24px;">
                <div class="card-body">
                    <div style="background: #DBEAFE; padding: 16px; border-radius: 12px; border: 1px solid #93C5FD;">
                        <h4 style="color: #1E40AF; font-weight: 600; margin-bottom: 8px;">💡 Response Time</h4>
                        <p style="font-size: 14px; color: #1E40AF;">
                            We typically respond to inquiries within 24-48 hours during business days. For urgent matters, please call our office directly.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3>Frequently Asked Questions</h3>
            <p>Common questions and answers</p>
        </div>
        <div class="card-body">
            <div style="display: flex; flex-direction: column; gap: 16px;">
                <div style="padding: 16px; background: var(--gray-50); border-radius: 12px;">
                    <h4 style="font-weight: 600; margin-bottom: 8px;">How do I reset my password?</h4>
                    <p style="font-size: 14px; color: var(--gray-600);">Go to your Profile or use the "Forgot Password" link on the login page to reset your password.</p>
                </div>

                <div style="padding: 16px; background: var(--gray-50); border-radius: 12px;">
                    <h4 style="font-weight: 600; margin-bottom: 8px;">My fingerprint is not registering. What should I do?</h4>
                    <p style="font-size: 14px; color: var(--gray-600);">Make sure your finger is clean and dry. If the issue persists, contact the admin or visit the IT office for assistance.</p>
                </div>

                <div style="padding: 16px; background: var(--gray-50); border-radius: 12px;">
                    <h4 style="font-weight: 600; margin-bottom: 8px;">How can I view my attendance history?</h4>
                    <p style="font-size: 14px; color: var(--gray-600);">Navigate to "Attendance Reports" from the main menu and filter by your name to view your complete attendance history.</p>
                </div>

                <div style="padding: 16px; background: var(--gray-50); border-radius: 12px;">
                    <h4 style="font-weight: 600; margin-bottom: 8px;">Who can I contact for technical issues?</h4>
                    <p style="font-size: 14px; color: var(--gray-600);">For technical issues, please use the contact form above or email support@bpc.edu.ph directly.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
