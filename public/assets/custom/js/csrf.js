/*!
 * Rangkui CSRF helper
 * Menyisipkan token CSRF ke setiap <form method="post"> dan ke setiap request AJAX
 * non-GET (jQuery & fetch). Token dibaca dari <meta name="csrf-token">.
 */
(function () {
  'use strict';
  var meta = document.querySelector('meta[name="csrf-token"]');
  if (!meta) return;

  var fieldName = meta.getAttribute('data-name');
  var headerName = meta.getAttribute('data-header') || 'X-CSRF-TOKEN';
  var token = function () { return meta.getAttribute('content'); };
  var unsafe = function (method) { return !/^(GET|HEAD|OPTIONS|TRACE)$/i.test(method || 'GET'); };

  function ensureField(form) {
    if (!form || !unsafe(form.getAttribute('method'))) return;
    var input = form.querySelector('input[name="' + fieldName + '"]');
    if (!input) {
      input = document.createElement('input');
      input.type = 'hidden';
      input.name = fieldName;
      form.appendChild(input);
    }
    if (!input.value) input.value = token();
  }

  function scan() {
    Array.prototype.forEach.call(document.querySelectorAll('form'), ensureField);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', scan);
  } else {
    scan();
  }
  // Form yang ditambahkan belakangan (modal, AJAX) tetap tertangani saat disubmit.
  document.addEventListener('submit', function (e) { ensureField(e.target); }, true);

  if (window.jQuery) {
    window.jQuery(document).ajaxSend(function (event, xhr, settings) {
      if (unsafe(settings.type) && !settings.crossDomain) {
        xhr.setRequestHeader(headerName, token());
      }
    });
  }

  if (window.fetch) {
    var originalFetch = window.fetch;
    window.fetch = function (input, init) {
      init = init || {};
      if (unsafe(init.method)) {
        var headers = new Headers(init.headers || {});
        headers.set(headerName, token());
        init.headers = headers;
      }
      return originalFetch(input, init);
    };
  }
})();
