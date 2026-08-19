<?php
// Página de eliminación de cuenta que exige Google Play (Data safety → "URL de
// eliminación de cuentas"): pasos, qué se borra y qué se conserva. Refleja lo que
// hace `eliminar_cuenta` en bt_app_social.php — si eso cambia, cambiar acá.
require_once __DIR__ . '/legal.inc.php';
legalInicio('Eliminar tu cuenta', 'Cómo eliminar tu cuenta de la app Beach Tennis PY y qué datos se borran.', '19 de agosto de 2026');
?>
<p>Esta página explica cómo eliminar tu cuenta de la app <strong>Beach Tennis PY</strong> (desarrollada por Beach Tennis PY · bt.com.py) y qué pasa con tus datos.</p>

<h2>Opción 1 — desde la app (inmediato)</h2>
<ol>
  <li>Abrí la app e iniciá sesión.</li>
  <li>Tocá el ícono de <strong>Perfil</strong> (arriba a la derecha).</li>
  <li>Al final de la pantalla, tocá <strong>"Eliminar mi cuenta"</strong>.</li>
  <li>Escribí tu contraseña para confirmar y tocá <strong>Eliminar</strong>. La sesión se cierra y la cuenta queda eliminada al instante.</li>
</ol>

<h2>Opción 2 — por correo</h2>
<p>Escribí a <a href="mailto:soporte@bt.com.py?subject=Eliminar%20mi%20cuenta">soporte@bt.com.py</a> desde el correo registrado en tu cuenta, con asunto "Eliminar mi cuenta" y tu número de cédula. La procesamos en un plazo máximo de <strong>7 días</strong> y te confirmamos por correo.</p>

<h2>Qué se elimina</h2>
<ul>
  <li>Datos de contacto y perfil: correo electrónico, celular, teléfono, foto de perfil, fecha de nacimiento y contraseña. La cuenta queda inactiva y no se puede volver a iniciar sesión.</li>
  <li>Todo tu contenido de la comunidad: publicaciones, comentarios, "me gusta", búsquedas de dupla, notificaciones, reportes y bloqueos.</li>
  <li>Tus mensajes privados se reemplazan por "mensaje de una cuenta eliminada" en los chats de la otra persona.</li>
  <li>Las sesiones abiertas en todos los dispositivos.</li>
</ul>

<h2>Qué se conserva y por cuánto tiempo</h2>
<ul>
  <li>Los <strong>resultados de los partidos y torneos que ya jugaste</strong> (nombre y resultado), porque son parte del registro de la competencia y del ranking de los demás jugadores. Se conservan sin datos de contacto, de forma indefinida como historial deportivo.</li>
  <li>Registros técnicos del servidor (fecha, hora, IP) por hasta 90 días, por seguridad.</li>
</ul>

<h2>Borrar sólo algunos datos, sin eliminar la cuenta</h2>
<p>En la app, <strong>Perfil → Editar datos</strong> podés borrar o cambiar tu celular, ciudad, fecha de nacimiento y foto de perfil. Para borrar publicaciones, comentarios o mensajes puntuales escribinos a <a href="mailto:soporte@bt.com.py">soporte@bt.com.py</a>.</p>

<p>Más detalle en la <a href="/privacidad.php">Política de privacidad</a>.</p>
<?php legalFin(); ?>
