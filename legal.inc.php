<?php
/**
 * legal.inc.php — plantilla mínima de las páginas legales de la app
 * (privacidad.php, terminos.php). Sin base de datos, sin sesión: son
 * páginas públicas que exigen Google Play / App Store y que la app enlaza
 * desde Perfil y desde las Normas de la comunidad.
 *
 *   legalInicio('Título', 'descripción corta');  ... HTML ...  legalFin();
 */
function legalInicio(string $titulo, string $descripcion, string $vigencia): void {
    $h = fn($s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>', $h($titulo), ' · Beach Tennis PY</title><meta name="description" content="', $h($descripcion), '">';
    echo '<link rel="icon" href="/favicon.ico">';
    echo '<style>
      :root{--navy:#091426;--azul:#316bf3;--gris:#64748b;--borde:#c5c6cd}
      *{box-sizing:border-box}body{margin:0;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;background:#eef2f7;color:#0f172a;line-height:1.55}
      header{background:var(--navy);padding:16px;text-align:center}header img{height:40px}
      main{max-width:760px;margin:0 auto;padding:22px 18px 48px}
      article{background:#fff;border:1px solid var(--borde);border-radius:16px;padding:22px 20px}
      h1{font-size:22px;margin:0 0 4px}h2{font-size:16px;margin:26px 0 8px;color:var(--navy)}
      p,li{font-size:15px;color:#1e293b}ul{padding-left:20px}.meta{color:var(--gris);font-size:13px;margin:0 0 18px}
      a{color:var(--azul)}footer{text-align:center;color:var(--gris);font-size:12px;padding:20px}
    </style></head><body>';
    echo '<header><a href="/"><img src="/logo-bt.com.png" alt="Beach Tennis PY"></a></header><main><article>';
    echo '<h1>', $h($titulo), '</h1><p class="meta">Beach Tennis PY · bt.com.py · vigente desde el ', $h($vigencia), '</p>';
}

function legalFin(): void {
    echo '</article></main><footer>Beach Tennis PY · <a href="/privacidad.php">Privacidad</a> · <a href="/terminos.php">Términos de uso</a> · <a href="mailto:soporte@bt.com.py">soporte@bt.com.py</a></footer></body></html>';
}
