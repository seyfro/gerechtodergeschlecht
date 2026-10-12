/*
 * Komfort-Ebene für die Vorlagenkarten. Ohne JavaScript funktioniert alles genauso: Das
 * Formular wird ganz normal abgeschickt und der Server liefert das PNG als Download.
 *
 * Mit JavaScript kommt dazu:
 *   - das gewählte Foto erscheint sofort in der Vorschau (nur lokal, kein Upload),
 *   - der Download läuft über fetch, die Seite bleibt stehen,
 *   - Fehlermeldungen des Servers stehen neben dem Button statt auf einer eigenen Seite,
 *   - auf iOS zusätzlich ein Teilen-Knopf, weil ein Download dort nur in "Dateien" landet.
 */
(function () {
  'use strict';
  if (!window.fetch || !window.FormData || !window.URL || !URL.createObjectURL) {
    return;
  }

  var ACCEPT = ['image/jpeg', 'image/png', 'image/webp'];

  document.querySelectorAll('.tpl-form').forEach(function (form) {
    var card = form.closest('.tpl-card');
    var input = form.querySelector('input[type=file]');
    var submit = form.querySelector('button[type=submit]');
    var shareBtn = form.querySelector('.tpl-share');
    var status = form.querySelector('.tpl-status');
    var photoBox = card ? card.querySelector('.tpl-photo') : null;
    var preview = photoBox ? photoBox.querySelector('img') : null;
    var hint = photoBox ? photoBox.querySelector('.tpl-photo-hint') : null;
    if (!input || !submit || !status) {
      return;
    }

    var objectUrl = null;
    var lastBlob = null;
    var lastName = '';

    function say(text, isError) {
      status.textContent = text || '';
      status.classList.toggle('is-error', !!isError);
    }

    // Ohne Foto gibt es nichts herunterzuladen. Ohne JS erledigt das required-Attribut dasselbe.
    submit.disabled = true;

    input.addEventListener('change', function () {
      var file = input.files && input.files[0];
      if (shareBtn) {
        shareBtn.hidden = true;
      }
      lastBlob = null;
      if (!file) {
        submit.disabled = true;
        say('');
        return;
      }
      if (ACCEPT.indexOf(file.type) < 0) {
        submit.disabled = true;
        say(form.dataset.errorType, true);
        return;
      }
      var max = parseInt(form.dataset.maxBytes || '0', 10);
      if (max > 0 && file.size > max) {
        submit.disabled = true;
        say(form.dataset.errorTooLarge, true);
        return;
      }
      if (preview) {
        if (objectUrl) {
          URL.revokeObjectURL(objectUrl);
        }
        objectUrl = URL.createObjectURL(file);
        preview.src = objectUrl;
        preview.hidden = false;
        if (hint) {
          hint.hidden = true;
        }
      }
      submit.disabled = false;
      say(form.dataset.statusChosen);
    });

    if (shareBtn) {
      shareBtn.addEventListener('click', function () {
        if (!lastBlob || !navigator.share) {
          return;
        }
        var file = new File([lastBlob], lastName, { type: 'image/png' });
        navigator.share({ files: [file] }).catch(function () { /* Abbruch durch die Person */ });
      });
    }

    form.addEventListener('submit', function (event) {
      if (!input.files || !input.files[0]) {
        return; // ohne Foto den normalen Weg gehen, damit required greift
      }
      event.preventDefault();
      submit.disabled = true;
      if (shareBtn) {
        shareBtn.hidden = true;
      }
      say(form.dataset.statusWorking);

      fetch(form.action, {
        method: 'POST',
        body: new FormData(form),
        headers: { Accept: 'application/json, image/png', 'X-Requested-With': 'fetch' },
        credentials: 'same-origin'
      }).then(function (res) {
        if (res.ok) {
          return res.blob().then(function (blob) {
            var name = filenameFrom(res.headers.get('Content-Disposition'))
              || 'wir-vaeter-gerecht-oder-geschlecht.png';
            download(blob, name);
            lastBlob = blob;
            lastName = name;
            offerShare();
            say(form.dataset.statusDone);
          });
        }
        return errorFrom(res).then(function (message) {
          say(message, true);
        });
      }).catch(function () {
        say(form.dataset.statusError, true);
      }).then(function () {
        submit.disabled = false;
      });
    });

    function offerShare() {
      if (!shareBtn || !lastBlob || !navigator.share || !navigator.canShare) {
        return;
      }
      try {
        var file = new File([lastBlob], lastName, { type: 'image/png' });
        if (navigator.canShare({ files: [file] })) {
          shareBtn.hidden = false;
        }
      } catch (e) {
        /* File-Konstruktor fehlt: dann eben ohne Teilen-Knopf */
      }
    }

    /** Serverfehler als Text. JSON kommt von generate.php, HTML z. B. von nginx (413). */
    function errorFrom(res) {
      var type = res.headers.get('Content-Type') || '';
      if (type.indexOf('application/json') === 0) {
        return res.json().then(function (data) {
          return (data && data.error) || form.dataset.statusError;
        }).catch(function () {
          return form.dataset.statusError;
        });
      }
      if (res.status === 413) {
        return Promise.resolve(form.dataset.errorTooLarge);
      }
      if (res.status === 429) {
        return Promise.resolve(form.dataset.errorRate);
      }
      return Promise.resolve(form.dataset.statusError);
    }
  });

  function filenameFrom(header) {
    if (!header) {
      return '';
    }
    var m = /filename="([^"]+)"/.exec(header);
    return m ? m[1] : '';
  }

  function download(blob, name) {
    var url = URL.createObjectURL(blob);
    var a = document.createElement('a');
    a.href = url;
    a.download = name;
    document.body.appendChild(a);
    a.click();
    a.remove();
    setTimeout(function () { URL.revokeObjectURL(url); }, 60000);
  }
})();
