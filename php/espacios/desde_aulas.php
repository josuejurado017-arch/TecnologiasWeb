<?php

// Enlaces guardados del antiguo modulo de aulas (reemplazado en db/029).

require dirname(__DIR__, 2) . '/includes/bootstrap.php';

header('Location: ' . app_url('espacios/?message=desde_aulas'), true, 301);
exit;
