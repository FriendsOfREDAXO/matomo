(function () {
  'use strict';

  var isBound = false;

  function setResult(el, type, html) {
    if (!el) {
      return;
    }
    el.innerHTML = '<div class="alert alert-' + type + '" style="margin-top:8px;">' + html + '</div>';
  }

  function formatSize(bytes) {
    if (!Number.isFinite(bytes) || bytes <= 0) {
      return '0 KB';
    }
    return (bytes / 1024).toFixed(1) + ' KB';
  }

  function getEndpointNode() {
    return document.getElementById('matomo-config-endpoints');
  }

  function withButtonBusyState(button, busyHtml, callback) {
    var originalHtml = button.innerHTML;
    button.disabled = true;
    button.innerHTML = busyHtml;

    callback(function restore() {
      button.disabled = false;
      button.innerHTML = originalHtml;
    });
  }

  function handleJsonResponse(json, resultNode, successFallback, errorFallback) {
    if (json.success) {
      setResult(
        resultNode,
        'success',
        '<i class="fas fa-check-circle"></i> ' + (json.message || successFallback) +
          '<br><small>URL: <code>' + (json.url || '') + '</code> | Größe: ' + formatSize(Number(json.size || 0)) + '</small>'
      );
      return;
    }

    setResult(
      resultNode,
      'danger',
      '<i class="fas fa-times-circle"></i> ' + (json.message || errorFallback) +
        '<br><small>URL: <code>' + (json.url || '') + '</code></small>'
    );
  }

  jQuery(document).on('rex:ready', function () {
    if (isBound) {
      return;
    }

    isBound = true;

    jQuery(document).on('click.matomoConfig', '#test-connection', function () {
      var endpointNode = getEndpointNode();
      var resultNode = document.getElementById('test-result');
      var input = document.getElementById('matomo_url');
      var matomoUrl = input ? String(input.value || '').trim() : '';

      if (!matomoUrl) {
        setResult(resultNode, 'warning', '<i class="fas fa-exclamation-triangle"></i> Bitte eine URL eingeben');
        return;
      }

      if (!endpointNode) {
        setResult(resultNode, 'danger', 'Test-Endpoint nicht gefunden.');
        return;
      }

      var endpoint = endpointNode.getAttribute('data-test-connection-url') || '';
      if (!endpoint) {
        setResult(resultNode, 'danger', 'Test-Endpoint nicht gefunden.');
        return;
      }

      withButtonBusyState(this, '<i class="fas fa-spinner fa-spin"></i> Teste...', function (restore) {
        setResult(resultNode, 'info', '<i class="fas fa-info-circle"></i> Verbindung wird geprüft...');

        jQuery.getJSON(endpoint, { matomo_url: matomoUrl })
          .done(function (json) {
            handleJsonResponse(json, resultNode, 'Verbindung erfolgreich', 'Verbindung fehlgeschlagen');
          })
          .fail(function (xhr, textStatus, errorThrown) {
            var message = errorThrown || textStatus || 'Unbekannter Fehler';
            setResult(resultNode, 'danger', '<i class="fas fa-times-circle"></i> Fehler: ' + message);
          })
          .always(restore);
      });
    });

    jQuery(document).on('click.matomoConfig', '#test-proxy', function () {
      var endpointNode = getEndpointNode();
      var resultNode = document.getElementById('test-proxy-result');

      if (!endpointNode) {
        setResult(resultNode, 'danger', 'Proxy-Test-Endpoint nicht gefunden.');
        return;
      }

      var endpoint = endpointNode.getAttribute('data-test-proxy-url') || '';
      if (!endpoint) {
        setResult(resultNode, 'danger', 'Proxy-Test-Endpoint nicht gefunden.');
        return;
      }

      withButtonBusyState(this, '<i class="fas fa-spinner fa-spin"></i> Teste...', function (restore) {
        setResult(resultNode, 'info', '<i class="fas fa-info-circle"></i> Proxy wird geprüft...');

        jQuery.getJSON(endpoint)
          .done(function (json) {
            handleJsonResponse(json, resultNode, 'Proxy funktioniert', 'Proxy-Test fehlgeschlagen');
          })
          .fail(function (xhr, textStatus, errorThrown) {
            var message = errorThrown || textStatus || 'Unbekannter Fehler';
            setResult(resultNode, 'danger', '<i class="fas fa-times-circle"></i> Fehler: ' + message);
          })
          .always(restore);
      });
    });
  });
})();
