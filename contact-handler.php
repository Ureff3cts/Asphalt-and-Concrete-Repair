<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $to = "tullejohn62@gmail.com"; // actual inbox
    $subject = "New Contact Form Submission - TJ's Asphalt & Concrete Repair";

    $name    = htmlspecialchars($_POST['name']);
    $email   = htmlspecialchars($_POST['email']);
    $address = htmlspecialchars($_POST['address']);
    $phone   = htmlspecialchars($_POST['phone']);
    $message = htmlspecialchars($_POST['message']);

    $body = "You have received a new message from your website contact form:\n\n"
          . "Name: $name\n"
          . "Email: $email\n"
          . "Address: $address\n"
          . "Phone: $phone\n"
          . "Message:\n$message\n";

    $headers = "From: info@tjsasphaltandconcreterepair.com\r\n";
    $headers .= "Reply-To: $email\r\n";

    if (mail($to, $subject, $body, $headers)) {
        echo "Thank you! Your message has been sent.";
    } else {
        echo "Sorry, there was a problem sending your message. Please try again later.";
    }
}
?>