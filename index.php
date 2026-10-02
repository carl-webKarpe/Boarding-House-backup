<?php

/**
 * Project entry point. Opening the project's main folder in a browser
 * (http://localhost:8000/ or http://localhost/BHsystem/) always shows the
 * landing page, html/index.html.
 */
header('Location: html/index.html', true, 302);
exit;
