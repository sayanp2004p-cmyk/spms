<?php
require_once __DIR__ . '/php/db.php';
include('smtp/PHPMailerAutoload.php');

function smtp_mailer($to, $subject, $msg, $user_id = null, $logo_url = null){
	$mail = new PHPMailer(); 
	$mail->IsSMTP(); 
	$mail->SMTPAuth = true; 
	$mail->SMTPSecure = 'tls'; 
	$mail->Host = "smtp.gmail.com";
	$mail->Port = 587; 
	$mail->IsHTML(true);
	$mail->CharSet = 'UTF-8';
	
	// Default credentials (system account)
	$from_email = "justdreamltd@gmail.com";
	$from_password = "qsacnrcswxecfpuc";
	$from_name = "Yoga Culture";
	$logo = "https://yogaculture.kesug.com/img/logo.png";
	
	// Determine if using user credentials (student emails) or system credentials (OTP emails)
	$use_user_credentials = $user_id && isset($GLOBALS['pdo']);
	$use_system_logo = !$use_user_credentials; // System emails use justltd logo, user emails use yogaculture logo
	
	if ($use_user_credentials) {
		try {
			$stmt = $GLOBALS['pdo']->prepare("SELECT email_for_sending, app_password FROM users WHERE id = ?");
			$stmt->execute([$user_id]);
			$row = $stmt->fetch(PDO::FETCH_ASSOC);
			
			if ($row && !empty($row['email_for_sending']) && !empty($row['app_password'])) {
				$from_email = $row['email_for_sending'];
				$from_password = $row['app_password'];
				$logo = "https://yogaculture.kesug.com/img/logo.png"; // User emails use yoga culture logo
			} else {
				$logo = "https://justltd.kesug.com/img/logo.png"; // Fallback to system logo
			}
		} catch (Exception $e) {
			// Use default credentials if database fetch fails
			$logo = "https://justltd.kesug.com/img/logo.png"; // System email logo
		}
	} else {
		// OTP and profile emails use system logo
		$logo = "https://justltd.kesug.com/img/logo.png";
	}
	
	// Inject logo into email if not already present
	if (strpos($msg, '<img') === false && strpos($msg, 'logo') === false || preg_match('/<img[^>]*src="https:\/\/[^"]*logo[^"]*"/', $msg) === 0) {
		$logo_html = '<div style="text-align: center; margin-bottom: 20px;"><img src="' . $logo . '" alt="Logo" style="max-width: 150px; max-height: 80px;"></div>';
		// Insert logo after body tag or at the beginning
		if (strpos($msg, '<body>') !== false) {
			$msg = str_replace('<body>', '<body>' . $logo_html, $msg);
		} elseif (strpos($msg, '<div class=\'email-container\'') !== false) {
			$msg = str_replace('<div class=\'email-container\'', $logo_html . '<div class=\'email-container\'', $msg);
		} else {
			$msg = $logo_html . $msg;
		}
	}
	
	$mail->Username = $from_email;
	$mail->Password = $from_password;
	$mail->SetFrom($from_email, $from_name);
	$mail->Subject = $subject;
	$mail->Body = $msg;
	$mail->AddAddress($to);
	$mail->SMTPOptions = array('ssl' => array(
		'verify_peer' => false,
		'verify_peer_name' => false,
		'allow_self_signed' => false
	));
	
	try {
		if($mail->Send()){
			return ['success' => true, 'message' => 'Sent'];
		} else {
			return ['success' => false, 'message' => $mail->ErrorInfo];
		}
	} catch (Exception $e) {
		return ['success' => false, 'message' => $e->getMessage()];
	}
}
?>