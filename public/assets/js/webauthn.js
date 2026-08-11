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

  async function available() {
    if (!window.PublicKeyCredential || !PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable) return false;
    try { return await PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable(); }
    catch (e) { return false; }
  }

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

  return { available: available, register: register, login: login, recordar: recordar, olvidar: olvidar, guardado: guardado };
})();
