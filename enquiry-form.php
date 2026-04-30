<?php
header('Content-Type: application/json');
include './conn.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'mailer/PHPMailer.php';
require 'mailer/SMTP.php';
require 'mailer/Exception.php';

$googleScriptUrl = "https://script.google.com/macros/s/AKfycbxtghQ8SHdaeAK3RXpvua4j9rJ7jLysRgiI2J4qGRpWjC4gagPGLQs_ZWKKBoh29y_Q/exec";

/* ---------- GET DATA ---------- */
$name    = trim($_POST['name'] ?? '');
$email   = trim($_POST['email'] ?? '');
$mobile  = trim($_POST['mobile'] ?? '');
$subject = trim($_POST['subject'] ?? '');
$message = trim($_POST['message'] ?? '');

/* ---------- VALIDATION ---------- */
if ($name === '' || $email === '' || $mobile === '') {
    echo json_encode(['status'=>'error','message'=>'Name, Email, Phone are required']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['status'=>'error','message'=>'Invalid email']);
    exit;
}

/* Dubai + International phone support */
$cleanMobile = preg_replace('/[\s\-\(\)]/', '', $mobile);

if (!preg_match('/^\+?[0-9]{7,15}$/', $cleanMobile)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Please enter a valid phone number'
    ]);
    exit;
}

$mobile = $cleanMobile;

/* ---------- FALLBACK ---------- */
$subject = $subject ?: 'General Enquiry';
$message = $message ?: 'Website enquiry';

/* ---------- SAFE OUTPUT FOR EMAIL ---------- */
$safeName    = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
$safeEmail   = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
$safeMobile  = htmlspecialchars($mobile, ENT_QUOTES, 'UTF-8');
$safeSubject = htmlspecialchars($subject, ENT_QUOTES, 'UTF-8');
$safeMessage = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));

/* ---------- DB SAVE ---------- */
$stmt = $conn->prepare("INSERT INTO contact_enquiries (name,email,mobile,subject,message) VALUES (?,?,?,?,?)");

if (!$stmt) {
    echo json_encode(['status'=>'error','message'=>'Database error']);
    exit;
}

$stmt->bind_param("sssss", $name, $email, $mobile, $subject, $message);

if (!$stmt->execute()) {
    echo json_encode(['status'=>'error','message'=>'Failed to save enquiry']);
    exit;
}

/* ---------- GOOGLE SHEET ---------- */
$sheetSaved = false;

$payload = json_encode([
    "name"       => $name,
    "email"      => $email,
    "mobile"     => $mobile,
    "subject"    => $subject,
    "message"    => $message,
    "created_at" => date("Y-m-d H:i:s")
]);

$ch = curl_init($googleScriptUrl);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 8
]);

$response = curl_exec($ch);
$error    = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if (!$error && $httpCode >= 200 && $httpCode < 300) {
    $sheetSaved = true;
}

/* ---------- EMAIL ---------- */
$mailSent = false;

try {
    $mail = new PHPMailer(true);

    $mail->isSMTP();
    $mail->Host       = 'mail.ayatiworks.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'emailsmtp@ayatiworks.com';
    $mail->Password   = 'hYd@W,$nwNjC';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port       = 465;

    $mail->setFrom('emailsmtp@ayatiworks.com', 'Royal Dutch Medical Centre');
    $mail->addAddress('balaji@ayatiworks.com');
    $mail->addReplyTo($email, $name);

    $mail->isHTML(true);
    $mail->Subject = "New Enquiry - Royal Dutch Medical Centre";

    $mail->Body = "
    <!DOCTYPE html>
    <html>
    <head>
      <meta charset='UTF-8'>
    </head>
    <body style='margin:0; padding:0; background:#f5f1f4; font-family:Arial, sans-serif;'>

      <table width='100%' cellpadding='0' cellspacing='0' style='background:#f5f1f4; padding:30px 15px;'>
        <tr>
          <td align='center'>

            <table width='100%' cellpadding='0' cellspacing='0' style='max-width:620px; background:#ffffff; border-radius:18px; overflow:hidden; box-shadow:0 12px 35px rgba(0,0,0,0.14);'>

              <tr>
                <td align='center' style='background:#f5f5f5; padding:30px 20px;border-bottom:3px solid #571248;'>
                  <img src='https://royaldutchclinic.ae/wp-content/uploads/2022/06/300_106.png' alt='Royal Dutch Medical Centre' style='max-width:230px; height:auto; display:block; margin:0 auto;'>
                </td>
              </tr>

              <tr>
                <td style='padding:32px 34px 24px;'>

                  <h2 style='margin:0; color:#571248; font-size:24px; font-weight:700;'>
                    New Enquiry Received
                  </h2>

                  <p style='margin:10px 0 26px; color:#666666; font-size:14px; line-height:1.6;'>
                    A new consultation enquiry has been submitted from the Royal Dutch Medical Centre website.
                  </p>

                  <table width='100%' cellpadding='0' cellspacing='0' style='border-collapse:collapse;'>
                    <tr>
                      <td style='padding:14px 0; border-bottom:1px solid #eeeeee; color:#571248; font-weight:700; width:135px;'>Name</td>
                      <td style='padding:14px 0; border-bottom:1px solid #eeeeee; color:#222222;'>$safeName</td>
                    </tr>

                    <tr>
                      <td style='padding:14px 0; border-bottom:1px solid #eeeeee; color:#571248; font-weight:700;'>Email</td>
                      <td style='padding:14px 0; border-bottom:1px solid #eeeeee; color:#222222;'>$safeEmail</td>
                    </tr>

                    <tr>
                      <td style='padding:14px 0; border-bottom:1px solid #eeeeee; color:#571248; font-weight:700;'>Phone</td>
                      <td style='padding:14px 0; border-bottom:1px solid #eeeeee; color:#222222;'>$safeMobile</td>
                    </tr>

                    <tr>
                      <td style='padding:14px 0; border-bottom:1px solid #eeeeee; color:#571248; font-weight:700;'>Service</td>
                      <td style='padding:14px 0; border-bottom:1px solid #eeeeee; color:#222222;'>$safeSubject</td>
                    </tr>

                    <tr>
                      <td style='padding:14px 0; color:#571248; font-weight:700; vertical-align:top;'>Message</td>
                      <td style='padding:14px 0; color:#222222; line-height:1.7;'>$safeMessage</td>
                    </tr>
                  </table>

                </td>
              </tr>

              <tr>
                <td align='center' style='background:#fafafa; padding:18px 20px; color:#777777; font-size:12px; line-height:1.5;'>
                  © 2026 Royal Dutch Medical Centre<br>All rights Reserved
                </td>
              </tr>

            </table>

          </td>
        </tr>
      </table>

    </body>
    </html>
    ";

    $mail->AltBody = "New Enquiry\n\nName: $name\nEmail: $email\nPhone: $mobile\nService: $subject\nMessage: $message";

    $mail->send();
    $mailSent = true;

} catch (Exception $e) {
    $mailSent = false;
}

/* ---------- FINAL RESPONSE ---------- */
if ($sheetSaved || $mailSent) {
    echo json_encode([
        'status'  => 'success',
        'message' => 'Enquiry submitted successfully'
    ]);
    exit;
}

echo json_encode([
    'status'  => 'error',
    'message' => 'Saved in database, but notification failed'
]);
?>