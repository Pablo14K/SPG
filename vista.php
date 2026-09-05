<?php
// Dibuja la pantalla del correo con la cuenta SIN cargar, que es el caso nuevo.
$html = view('seguridad.correo_sistema', [
    'personalizado' => false,
    'usuarioActual' => '',
    'desdeActual' => '',
])->render();
foreach (['no está mandando correos', 'Borrar la cuenta y dejar de mandar correos'] as $t) {
    echo (str_contains($html, $t) ? 'OK   ' : 'FALTA') . ' · ' . $t . PHP_EOL;
}
