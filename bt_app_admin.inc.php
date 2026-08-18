<?php
/**
 * bt_app_admin.inc.php — páginas "Moderación" y "Avisos app" de tvt_admin_v2.php
 * (18-ago-2026). Se incluye dentro de #content, junto a las otras .page; el JS
 * se engancha en DOMContentLoaded (goPage ya existe para entonces) y usa el
 * mismo api() → tvt_api.php (acciones en bt_app_admin.actions.php).
 */
?>
    <div class="page" id="pg-moderacion">
      <div class="pg-row">
        <div><div class="pg-title">Moderación de la app</div><div class="pg-sub">Denuncias del muro, comentarios, chats y avisos de dupla. Google Play y App Store exigen que se resuelvan; el jugador denunciado no ve nada hasta que actúes.</div></div>
      </div>
      <div class="fbar">
        <select class="fs" id="fModEstado" onchange="loadModeracion()">
          <option value="pendiente">Pendientes</option>
          <option value="resuelta">Resueltas</option>
          <option value="descartada">Descartadas</option>
          <option value="todas">Todas</option>
        </select>
        <input class="fs" type="text" id="fModCi" placeholder="Suspender / levantar por CI…" style="min-width:200px;">
        <button class="btn btn-gh btn-sm" onclick="modSuspenderCi('7')">Suspender 7 días</button>
        <button class="btn btn-gh btn-sm" onclick="modSuspenderCi('30')">30 días</button>
        <button class="btn btn-warn btn-sm" onclick="modSuspenderCi('perm')">Permanente</button>
        <button class="btn btn-ok btn-sm" onclick="modLevantarCi()">Levantar suspensión</button>
      </div>
      <div class="tbl-card">
        <div class="tbl-hdr"><span class="tbl-title" id="modCount">Denuncias</span></div>
        <div class="tbl-wrap">
          <table>
            <thead><tr><th style="white-space:nowrap;">Fecha</th><th>Qué</th><th>Denunciado</th><th>Motivo</th><th>Contenido</th><th>Denunciante</th><th>Acción</th></tr></thead>
            <tbody id="tbMod"></tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="page" id="pg-avisos">
      <div class="pg-row">
        <div><div class="pg-title">Avisos de la app</div><div class="pg-sub">Qué notificaciones manda la app y difusiones a los jugadores. Los avisos automáticos (partidos, resultados, inscripciones, ranking) se envían por push cuando se active el servicio; mientras tanto las difusiones llegan a la campana de la app.</div></div>
      </div>
      <div class="kpi-grid" style="margin-bottom:16px;">
        <div class="kpi"><div class="kpi-ct"><div class="kpi-val" id="avJug">—</div><div class="kpi-lb">Jugadores usando la app</div></div></div>
        <div class="kpi"><div class="kpi-ct"><div class="kpi-val" id="avDisp">—</div><div class="kpi-lb">Teléfonos con push</div></div></div>
      </div>
      <div class="tbl-card" style="margin-bottom:16px;">
        <div class="tbl-hdr"><span class="tbl-title">Avisos automáticos</span></div>
        <div class="tbl-wrap"><table><thead><tr><th>Aviso</th><th style="width:120px;">Activo</th></tr></thead><tbody id="tbAvisos"></tbody></table></div>
      </div>
      <div class="tbl-card">
        <div class="tbl-hdr"><span class="tbl-title">Difusión a los jugadores</span></div>
        <div style="padding:16px;display:grid;gap:10px;grid-template-columns:1fr 1fr;">
          <div class="fg" style="grid-column:1/3;"><label>Título</label><input class="fs" id="difTitulo" maxlength="120" placeholder="Ej.: Se abrieron las inscripciones de la 12ma. FECHA" style="width:100%;"></div>
          <div class="fg" style="grid-column:1/3;"><label>Texto</label><textarea class="fs" id="difCuerpo" maxlength="500" rows="3" placeholder="Lo que va a leer el jugador" style="width:100%;"></textarea></div>
          <div class="fg"><label>A quién</label>
            <select class="fs" id="difFiltro" onchange="difFiltroChange()" style="width:100%;">
              <option value="todos">Todos los que usan la app</option>
              <option value="evento">Inscriptos de un evento</option>
              <option value="circuito">Inscriptos del circuito</option>
            </select></div>
          <div class="fg"><label>Evento / circuito (id)</label><input class="fs" id="difFiltroId" type="number" placeholder="id" disabled style="width:100%;"></div>
          <div class="fg" style="grid-column:1/3;"><label>Al tocar abre (opcional, ruta de la app: /torneo/12, /ranking, /comunidad)</label><input class="fs" id="difDestino" maxlength="160" placeholder="/torneo/12" style="width:100%;"></div>
          <div style="grid-column:1/3;display:flex;gap:8px;align-items:center;">
            <button class="btn btn-p" onclick="difEnviar()"><i class="fas fa-paper-plane"></i> Enviar difusión</button>
            <span id="difMsg" style="font-size:12px;color:var(--text-muted);"></span>
          </div>
        </div>
        <div class="tbl-wrap"><table><thead><tr><th>Fecha</th><th>Título</th><th>A quién</th><th>Push</th><th>Por</th></tr></thead><tbody id="tbDif"></tbody></table></div>
      </div>
    </div>

<script>
(function(){
  const esc = s => { const d=document.createElement('div'); d.textContent = s==null?'':String(s); return d.innerHTML; };
  const fecha = s => s ? String(s).slice(0,16).replace('T',' ') : '—';

  // ── Moderación ──
  window.loadModeracion = async function(){
    const est = document.getElementById('fModEstado').value;
    const r = await api({action:'mod_denuncias', estado: est});
    const tb = document.getElementById('tbMod');
    if(!r.success){ tb.innerHTML = `<tr><td colspan="7" style="text-align:center;padding:24px;color:var(--danger);">${esc(r.error||'Error')}</td></tr>`; return; }
    document.getElementById('modCount').textContent = `Denuncias (${r.denuncias.length})`;
    if(!r.denuncias.length){ tb.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:24px;color:var(--text-muted);">Nada pendiente 🎉</td></tr>'; return; }
    tb.innerHTML = r.denuncias.map(d => {
      const susp = d.suspendidoHasta ? `<div style="font-size:11px;color:var(--danger);">Suspendido hasta ${esc(d.suspendidoHasta.startsWith('9999')?'siempre':d.suspendidoHasta.slice(0,10))}</div>` : '';
      const acciones = d.estado === 'pendiente' ? `
        <select class="fs" id="modAcc${d.id}" style="min-width:210px;">
          <option value="descartar">Descartar (no incumple)</option>
          <option value="borrar">Borrar el contenido</option>
          <option value="borrar_suspender_7">Borrar + suspender 7 días</option>
          <option value="borrar_suspender_30">Borrar + suspender 30 días</option>
          <option value="borrar_suspender_perm">Borrar + suspender permanente</option>
          <option value="suspender_7">Sólo suspender 7 días</option>
        </select>
        <button class="btn btn-p btn-sm" onclick="modResolver(${d.id})">Aplicar</button>`
        : `<span class="badge badge-i">${esc(d.estado)} · ${esc(d.accion||'')}</span><div style="font-size:11px;color:var(--text-muted);">${esc(d.resueltoPor||'')} ${fecha(d.resueltoEn)}</div>`;
      return `<tr>
        <td style="white-space:nowrap;font-size:12px;">${fecha(d.creado)}</td>
        <td><span class="badge badge-a">${esc(d.tipo)}${d.refId?' #'+d.refId:''}</span></td>
        <td style="font-weight:600;">${esc(d.denunciado)}${susp}</td>
        <td>${esc(d.motivo)}${d.detalle?`<div style="font-size:11px;color:var(--text-muted);">${esc(d.detalle)}</div>`:''}</td>
        <td style="font-size:12px;max-width:360px;word-break:break-word;">${esc(d.texto||'—')}</td>
        <td style="font-size:12px;">${esc(d.denunciante)}</td>
        <td style="white-space:nowrap;">${acciones}</td>
      </tr>`;
    }).join('');
  };
  window.modResolver = async function(id){
    const acc = document.getElementById('modAcc'+id).value;
    let motivo = 'Incumplimiento de las normas de la comunidad';
    if(acc.includes('suspender')){ const m = prompt('Motivo que va a ver el jugador:', motivo); if(m===null) return; motivo = m || motivo; }
    if(!confirm('¿Aplicar "'+acc.replace(/_/g,' ')+'"?')) return;
    const r = await api({action:'mod_resolver', id, accion: acc, motivo});
    if(!r.success){ alert(r.error||'Error'); return; }
    loadModeracion(); modBadge();
  };
  window.modSuspenderCi = async function(dias){
    const ci = document.getElementById('fModCi').value.replace(/\D/g,''); if(!ci){ alert('Poné el CI'); return; }
    const motivo = prompt('Motivo que va a ver el jugador:', 'Incumplimiento de las normas de la comunidad'); if(motivo===null) return;
    const r = await api({action:'mod_suspender', ci, dias, motivo}); alert(r.success ? 'Suspendido hasta '+r.hasta : (r.error||'Error')); loadModeracion();
  };
  window.modLevantarCi = async function(){
    const ci = document.getElementById('fModCi').value.replace(/\D/g,''); if(!ci){ alert('Poné el CI'); return; }
    const r = await api({action:'mod_levantar', ci}); alert(r.success ? 'Suspensión levantada' : (r.error||'Error')); loadModeracion();
  };
  window.modBadge = async function(){
    const r = await api({action:'mod_pendientes'}); const b = document.getElementById('navModBadge');
    if(b && r.success){ b.textContent = r.pendientes; b.style.display = r.pendientes ? '' : 'none'; }
  };

  // ── Avisos app ──
  window.loadAvisos = async function(){
    const r = await api({action:'avisos_config'});
    if(r.success){
      document.getElementById('avJug').textContent = r.jugadoresApp; document.getElementById('avDisp').textContent = r.dispositivos;
      document.getElementById('tbAvisos').innerHTML = r.config.map(c => `<tr><td>${esc(c.titulo)} <span style="font-size:11px;color:var(--text-muted);">(${esc(c.tipo)})</span></td>
        <td><label style="display:flex;align-items:center;gap:8px;cursor:pointer;"><input type="checkbox" ${c.activo?'checked':''} onchange="avisoToggle('${esc(c.tipo)}', this.checked)"> ${c.activo?'Sí':'No'}</label></td></tr>`).join('');
    }
    const d = await api({action:'difusiones'});
    const tb = document.getElementById('tbDif');
    if(!d.success || !d.difusiones.length){ tb.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:18px;color:var(--text-muted);">Todavía no se mandó ninguna difusión</td></tr>'; return; }
    tb.innerHTML = d.difusiones.map(x => `<tr><td style="white-space:nowrap;font-size:12px;">${fecha(x.creado)}</td><td><strong>${esc(x.titulo)}</strong><div style="font-size:12px;color:var(--text-muted);">${esc(x.cuerpo)}</div></td>
      <td>${esc(x.filtro)}${x.filtro_id?' #'+x.filtro_id:''}</td><td>${x.enviado_en ? esc(x.enviados)+' enviados' : '<span class="badge badge-pend">en campana; push pendiente</span>'}</td><td>${esc(x.creado_por)}</td></tr>`).join('');
  };
  window.avisoToggle = async function(tipo, on){ const r = await api({action:'avisos_toggle', tipo, activo: on?'1':'0'}); if(!r.success) alert(r.error||'Error'); loadAvisos(); };
  window.difFiltroChange = function(){ document.getElementById('difFiltroId').disabled = document.getElementById('difFiltro').value === 'todos'; };
  window.difEnviar = async function(){
    const titulo = document.getElementById('difTitulo').value.trim(), cuerpo = document.getElementById('difCuerpo').value.trim();
    const filtro = document.getElementById('difFiltro').value, filtro_id = document.getElementById('difFiltroId').value, destino = document.getElementById('difDestino').value.trim();
    if(!titulo || !cuerpo){ alert('Falta título o texto'); return; }
    if(filtro !== 'todos' && !filtro_id){ alert('Poné el id del evento/circuito'); return; }
    if(!confirm('¿Enviar la difusión a los jugadores?')) return;
    const r = await api({action:'difusion_crear', titulo, cuerpo, filtro, filtro_id, destino});
    document.getElementById('difMsg').textContent = r.success ? `Listo: llegó a la campana de ${r.inApp} jugador(es).` : (r.error||'Error');
    if(r.success){ document.getElementById('difTitulo').value=''; document.getElementById('difCuerpo').value=''; document.getElementById('difDestino').value=''; loadAvisos(); }
  };

  // Enganche a goPage (definido en el script principal, ya existe al cargar el DOM).
  window.addEventListener('DOMContentLoaded', () => {
    const original = window.goPage;
    window.goPage = function(id){
      original(id);
      if(id === 'moderacion'){ document.getElementById('topTitle').textContent = 'Moderación de la app'; loadModeracion(); }
      if(id === 'avisos'){ document.getElementById('topTitle').textContent = 'Avisos de la app'; loadAvisos(); }
    };
    modBadge();
  });
})();
</script>
