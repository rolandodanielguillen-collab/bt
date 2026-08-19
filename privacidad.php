<?php
// Política de privacidad de la app y el sitio Beach Tennis PY (19-ago-2026).
// Texto alineado con lo que la app hace de verdad (bt_app_social.php, bt_app_api.php):
// si cambia qué datos se guardan o con quién se comparten, cambiar acá también.
require_once __DIR__ . '/legal.inc.php';
legalInicio('Política de privacidad', 'Qué datos recoge la app Beach Tennis PY, para qué y cómo eliminarlos.', '19 de agosto de 2026');
?>
<p>Esta política explica qué datos personales tratamos cuando usás el sitio <strong>bt.com.py</strong> y la aplicación móvil <strong>Beach Tennis PY</strong> (la "app"), para qué los usamos y qué podés hacer con ellos.</p>

<h2>1. Responsable y contacto</h2>
<p>El responsable del tratamiento es el equipo de <strong>Beach Tennis PY (bt.com.py)</strong>. Para cualquier consulta, pedido de acceso, corrección o eliminación de datos escribinos a <a href="mailto:soporte@bt.com.py">soporte@bt.com.py</a>.</p>

<h2>2. Qué datos recogemos</h2>
<ul>
  <li><strong>Cuenta de jugador:</strong> nombre y apellido, número de cédula (CI), correo electrónico, celular, ciudad, fecha de nacimiento (opcional) y foto de perfil (opcional). La cuenta se crea en el sitio; la app usa el mismo usuario.</li>
  <li><strong>Datos deportivos:</strong> inscripciones a torneos, categoría, club, resultados de partidos, puntos de ranking y estadísticas. Nombre, club y resultados son <strong>públicos</strong> en el sitio y en la app, como en cualquier competencia.</li>
  <li><strong>Comunidad (si la usás):</strong> publicaciones, comentarios, "me gusta", búsquedas de dupla, mensajes privados entre jugadores, reportes que hagas y jugadores que bloquees. Tu nombre, ciudad y foto de perfil se muestran junto a lo que publicás.</li>
  <li><strong>Datos técnicos:</strong> un token de sesión guardado en tu dispositivo para mantenerte conectado; registros del servidor (fecha, hora, dirección IP) con fines de seguridad; y, si activás las notificaciones, el identificador del dispositivo que entrega el servicio de notificaciones.</li>
</ul>
<p>No recogemos ubicación, contactos, micrófono ni datos de pago. No hay publicidad ni herramientas de análisis de terceros dentro de la app.</p>

<h2>3. Para qué los usamos</h2>
<ul>
  <li>Gestionar tu cuenta, inscribirte a torneos y mostrarte tus partidos, resultados y ranking.</li>
  <li>Hacer funcionar la comunidad (muro, búsqueda de dupla, chats) y moderarla: revisar reportes, bloquear, suspender o eliminar cuentas que incumplen las <a href="/terminos.php">normas</a>.</li>
  <li>Enviarte notificaciones sobre tus partidos, inscripciones y actividad de la comunidad (podés desactivarlas en el sistema del teléfono).</li>
  <li>Atender tus consultas de soporte y cumplir obligaciones legales.</li>
</ul>

<h2>4. Con quién se comparten</h2>
<ul>
  <li><strong>Otros jugadores:</strong> ven tu nombre, club, ciudad, foto, resultados y lo que publiques en la comunidad. Tu celular y tu correo no se muestran a otros jugadores.</li>
  <li><strong>Organizadores de torneos:</strong> reciben los datos de inscripción (nombre, CI, categoría, contacto) para organizar la competencia.</li>
  <li><strong>Proveedores técnicos:</strong> el servidor donde se aloja bt.com.py, la red de entrega de contenido (Cloudflare) y el servicio de notificaciones (Expo / Firebase Cloud Messaging) cuando esté activo. Sólo procesan datos para prestarnos el servicio.</li>
  <li><strong>Autoridades</strong>, únicamente cuando la ley lo exija.</li>
</ul>
<p><strong>No vendemos ni alquilamos datos personales.</strong></p>

<h2>5. Cuánto tiempo los guardamos</h2>
<p>Mientras tu cuenta esté activa. Los resultados de torneos ya disputados se conservan como registro de la competencia (son parte del historial del torneo y del ranking).</p>

<h2>6. Cómo eliminar tu cuenta</h2>
<p>En la app: <strong>Perfil → Eliminar mi cuenta</strong> (te pedimos la contraseña para confirmar). También podés pedirlo a <a href="mailto:soporte@bt.com.py">soporte@bt.com.py</a>. Al eliminar la cuenta:</p>
<ul>
  <li>se borran tu correo, celular, foto, fecha de nacimiento y sesiones; la cuenta queda inactiva y ya no podés iniciar sesión;</li>
  <li>se borran tus publicaciones, comentarios, "me gusta", búsquedas de dupla, bloqueos y notificaciones; tus mensajes privados se reemplazan por "mensaje de una cuenta eliminada" en los chats de la otra persona;</li>
  <li>se conservan los resultados de los partidos que jugaste (son del torneo, no sólo tuyos), sin datos de contacto.</li>
</ul>

<h2>7. Seguridad</h2>
<p>Toda la comunicación entre la app y el servidor va cifrada (HTTPS). Las contraseñas se guardan con hash, nunca en texto plano. El acceso a la base de datos está restringido al equipo técnico.</p>

<h2>8. Menores de edad</h2>
<p>La app está pensada para jugadores de beach tennis. Si tenés menos de 18 años, usala con el conocimiento de tu madre, padre o tutor. No registramos a sabiendas a menores de 13 años; si detectamos una cuenta así, la eliminamos.</p>

<h2>9. Tus derechos</h2>
<p>Podés acceder a tus datos, corregirlos (Perfil → Editar datos) y eliminarlos (punto 6), así como oponerte a un tratamiento u obtener una copia, conforme a la legislación paraguaya de protección de datos personales. Escribinos a <a href="mailto:soporte@bt.com.py">soporte@bt.com.py</a> y respondemos en un plazo razonable.</p>

<h2>10. Cambios</h2>
<p>Si cambiamos esta política, actualizamos la fecha de vigencia de arriba y, si el cambio es importante, te lo avisamos en la app.</p>
<?php legalFin(); ?>
