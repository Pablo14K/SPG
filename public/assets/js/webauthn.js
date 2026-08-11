// Ayudas para el login biométrico (WebAuthn) del sistema
window.SPGBio = (function () {
  function b64urlToBuf(s) {
    s = s.replace(/-/g, '+').replace(/_/g, '/');
    var pad = s.length % 4; if (pad) s += '='.repeat(4 - pad);
    var bin = atob(s), buf = new Uint8Array(bin.length);
    for (var i = 0; i < bin.length; i++) buf[i] = bin.charCodeAt(i);
    return buf.buffer;
  }
  function bufToB64url(buf) {
    var bytes = new Uint8Array(buf), bin = '';
    for (var i = 0; i < bytes.length; i++) bin += String.fromCharCode(bytes[i]);
    return btoa(bin).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
  }
  function form(csrf, extra) {
    // `_token` es el nombre que espera Laravel para el token CSRF
    var b = '_token=' + encodeURIComponent(csrf);
    if (extra) b += '&payload=' + encodeURIComponent(JSON.stringify(extra));
    return { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: b };
  }

  // ¿Se puede usar la huella acá? Devuelve el MOTIVO, no un sí o un no, porque
  // cada "no" se resuelve de una forma distinta: decirle a alguien que su
  // equipo no tiene lector cuando en realidad falta HTTPS lo manda a buscar un
  // problema de hardware que no existe. Pasó con el celular entrando por la IP
  // de la red.
  async function estado() {
    // WebAuthn sólo existe en contexto seguro: HTTPS, o localhost / 127.0.0.1.
    // Entrando por http://192.168.x.x:8000 el navegador ni siquiera define la
    // API, por más sensor que tenga el teléfono.
    if (!window.isSecureContext || !window.PublicKeyCredential) {
      return { ok: false, motivo: 'inseguro' };
    }
    // El rpId sale del dominio, y la especificación no admite direcciones IP:
    // aunque se sirviera por HTTPS, el navegador rechazaría el registro.
    if (/^\d{1,3}(\.\d{1,3}){3}$/.test(location.hostname)) {
      return { ok: false, motivo: 'ip' };
    }
    if (!PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable) {
      return { ok: false, motivo: 'navegador' };
    }
    try {
      var hay = await PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable();
      return hay ? { ok: true, motivo: '' } : { ok: false, motivo: 'sin_sensor' };
    } catch (e) {
      return { ok: false, motivo: 'sin_sensor' };
    }
  }

  // Lo que hay que decirle a la persona en cada caso, en su idioma y sin
  // mandarla a revisar lo que no es.
  var MOTIVOS = {
    inseguro: 'El ingreso con huella necesita una conexión segura (HTTPS). Estás entrando por la '
            + 'dirección de red del equipo, y por ahí el navegador no habilita el lector aunque tu '
            + 'dispositivo lo tenga. Publicado en el servidor, con HTTPS, va a funcionar.',
    ip: 'El ingreso con huella no funciona entrando por una dirección IP: necesita un dominio. '
      + 'Publicado en el servidor, con su subdominio y HTTPS, va a funcionar.',
    navegador: 'Este navegador no admite el ingreso con huella. Probá con Chrome, Edge, Firefox o '
             + 'Safari actualizados.',
    sin_sensor: 'Este dispositivo no tiene lector de huella ni reconocimiento facial disponible '
              + 'para el navegador.'
  };

  function motivoTexto(motivo) {
    return MOTIVOS[motivo] || MOTIVOS.sin_sensor;
  }

  async function available() { return (await estado()).ok; }

  // Registro (activar huella). urls = {options, verify}
  async function register(urls, csrf) {
    var r = await fetch(urls.options, form(csrf));
    var opt = await r.json();
    if (!opt.ok) throw new Error(opt.error || 'No se pudieron obtener las opciones.');
    var pk = opt.publicKey;
    pk.challenge = b64urlToBuf(pk.challenge);
    pk.user.id = b64urlToBuf(pk.user.id);
    (pk.excludeCredentials || []).forEach(function (c) { c.id = b64urlToBuf(c.id); });
    var cred = await navigator.credentials.create({ publicKey: pk });
    var payload = {
      clientDataJSON: bufToB64url(cred.response.clientDataJSON),
      attestationObject: bufToB64url(cred.response.attestationObject)
    };
    var r2 = await fetch(urls.verify, form(csrf, payload));
    return await r2.json();
  }

  // Login con huella. urls = {options, verify}
  async function login(urls, loginId, csrf) {
    var r = await fetch(urls.options, form(csrf, { login: loginId }));
    var opt = await r.json();
    if (!opt.ok) throw new Error(opt.error || 'Sin credenciales.');
    var pk = opt.publicKey;
    pk.challenge = b64urlToBuf(pk.challenge);
    (pk.allowCredentials || []).forEach(function (c) { c.id = b64urlToBuf(c.id); });
    var as = await navigator.credentials.get({ publicKey: pk });
    var payload = {
      credentialId: bufToB64url(as.rawId),
      clientDataJSON: bufToB64url(as.response.clientDataJSON),
      authenticatorData: bufToB64url(as.response.authenticatorData),
      signature: bufToB64url(as.response.signature)
    };
    var r2 = await fetch(urls.verify, form(csrf, payload));
    return await r2.json();
  }

  // Recordar/olvidar en el navegador el usuario con huella activa
  function recordar(login, email) { try { localStorage.setItem('spg_bio', JSON.stringify({ login: login, email: email })); } catch (e) {} }
  function olvidar() { try { localStorage.removeItem('spg_bio'); } catch (e) {} }
  function guardado() { try { return JSON.parse(localStorage.getItem('spg_bio') || 'null'); } catch (e) { return null; } }

  return { available: available, estado: estado, motivoTexto: motivoTexto, register: register,
           login: login, recordar: recordar, olvidar: olvidar, guardado: guardado };
})();
