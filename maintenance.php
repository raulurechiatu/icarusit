<?php

//  ATTENTION!
//
//  DO NOT MODIFY THIS FILE BECAUSE IT WAS GENERATED AUTOMATICALLY,
//  SO ALL YOUR CHANGES WILL BE LOST THE NEXT TIME THE FILE IS GENERATED.
//  IF YOU REQUIRE TO APPLY CUSTOM MODIFICATIONS, PERFORM THEM IN THE FOLLOWING FILE:
//  /home2/icarusit/public_html/wp-content/maintenance/template.phtml


$protocol = $_SERVER['SERVER_PROTOCOL'];
if ('HTTP/1.1' != $protocol && 'HTTP/1.0' != $protocol) {
    $protocol = 'HTTP/1.0';
}

header("{$protocol} 503 Service Unavailable", true, 503);
header('Content-Type: text/html; charset=utf-8');
header('Retry-After: 600');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width">
    <link rel="icon" href="https://icarusit.ro/wp-content/uploads/2023/03/elementor/thumbs/0a7202e8-339e-40a2-b41d-d02c4c814a36-1-e1747468716429-r5xlz6r9rm60nblb76hz6wnq0l1xvdgpsr4t18u2nc.jpg">
    <link rel="stylesheet" href="https://icarusit.ro/wp-content/maintenance/assets/styles.css">
    <script src="https://icarusit.ro/wp-content/maintenance/assets/timer.js"></script>
    <title>Întreținere programată</title>
</head>

<body>

    <div class="container">

    <header class="header">
        <h1>Site-ul este în cursă de întreținere. Lucrarea este una programată.</h1>
        <h2>Vă rugăm să scuzați deranjul. Reveniți mai târziu, nu mai durează mai mult!</h2>
    </header>

    <!--START_TIMER_BLOCK-->
        <!--END_TIMER_BLOCK-->

    <!--START_SOCIAL_LINKS_BLOCK-->
    <section class="social-links">
                    <a class="social-links__link" href="https://www.facebook.com/cPanel" target="_blank" title="Facebook">
                <span class="icon"><img src="https://icarusit.ro/wp-content/maintenance/assets/images/facebook.svg" alt="Facebook"></span>
            </a>
                    <a class="social-links__link" href="https://x.com/cPanel" target="_blank" title="Twitter">
                <span class="icon"><img src="https://icarusit.ro/wp-content/maintenance/assets/images/twitter.svg" alt="Twitter"></span>
            </a>
                    <a class="social-links__link" href="https://instagram.com/cPanel" target="_blank" title="Instagram">
                <span class="icon"><img src="https://icarusit.ro/wp-content/maintenance/assets/images/instagram.svg" alt="Instagram"></span>
            </a>
            </section>
    <!--END_SOCIAL_LINKS_BLOCK-->

</div>

<footer class="footer">
    <div class="footer__content">
        Pe platformă WP Toolkit    </div>
</footer>

</body>
</html>
