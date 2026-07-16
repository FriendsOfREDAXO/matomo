(function () {
  'use strict';

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

  document.addEventListener('DOMContentLoaded', function () {
    var endpointNode = document.getElementById('matomo-config-endpoints');
    if (!endpointNode) {
      return;
    }

    var testConnectionBtn = document.getElementById('test-connection');
    var testConnectionResult = document.getElementById('test-result');
    var testProxyBtn = document.getElementById('test-proxy');
    var testProxyResult = document.getElementById('test-proxy-result');

    if (testConnectionBtn) {
      testConnectionBtn.addEventListener('click', function () {
        var input = document.getElementById('matomo_url');
        var matomoUrl = input ? String(input.value || '').trim() : '';

        if (!matomoUrl) {
          setResult(testConnectionResult, 'warning', '<i class="fas fa-exclamation-triangle"></i> Bitte eine URL eingeben');
          return;
        }

        var endpoint = endpointNode.dataset.testConnectionUrl || '';
        if (!endpoint) {
          setResult(testConnectionResult, 'danger', 'Test-Endpoint nicht gefunden.');
          return;
        }

        testConnectionBtn.disabled = true;
        testConnectionBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Teste...';
        setResult(testConnectionResult, 'info', '<i class="fas fa-info-circle"></i> Verbindung wird geprüft...');

        fetch(endpoint + '&matomo_url=' + encodeURIComponent(matomoUrl), {
          credentials: 'same-origin'
        })
          .then(function (response) {
            return response.json();
          })
          .then(function (json) {
            if (json.success) {
              setResult(
                testConnectionResult,
                'success',
                '<i class="fas fa-check-circle"></i> ' + json.message +
                  '<br><small>URL: <code>' + (json.url || '') + '</code> | Größe: ' + formatSize(Number(json.size || 0)) + '</small>'
              );
            } else {
              setResult(
                testConnectionResult,
                'danger',
                '<i class="fas fa-times-circle"></i> ' + (json.message || 'Verbindung fehlgeschlagen') +
                  '<br><small>URL: <code>' + (json.url || '') + '</code></small>'
              );
            }
          })
          .catch(function (err) {
            setResult(testConnectionResult, 'danger', '<i class="fas fa-times-circle"></i> Fehler: ' + err.message);
          })
          .finally(function () {
            testConnectionBtn.disabled = false;
            testConnectionBtn.innerHTML = '<i class="fas fa-plug"></i> Test';
          });
      });
    }

    if (testProxyBtn) {
      testProxyBtn.addEventListener('click', function () {
        var endpoint = endpointNode.dataset.testProxyUrl || '';
        if (!endpoint) {
          setResult(testProxyResult, 'danger', 'Proxy-Test-Endpoint nicht gefunden.');
          return;
        }

        testProxyBtn.disabled = true;
        testProxyBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Teste...';
        setResult(testProxyResult, 'info', '<i class="fas fa-info-circle"></i> Proxy wird geprüft...');

        fetch(endpoint, {
          credentials: 'same-origin'
        })
          .then(function (response) {
            return response.json();
          })
          .then(function (json) {
            if (json.success) {
              setResult(
                testProxyResult,
                'success',
                '<i class="fas fa-check-circle"></i> ' + json.message +
                  '<br><small>URL: <code>' + (json.url || '') + '</code> | Größe: ' + formatSize(Number(json.size || 0)) + '</small>'
              );
            } else {
              setResult(
                testProxyResult,
                'danger',
                '<i class="fas fa-times-circle"></i> ' + (json.message || 'Proxy-Test fehlgeschlagen') +
                  '<br><small>URL: <code>' + (json.url || '') + '</code></small>'
              );
            }
          })
          .catch(function (err) {
            setResult(testProxyResult, 'danger', '<i class="fas fa-times-circle"></i> Fehler: ' + err.message);
          })
          .finally(function () {
            testProxyBtn.disabled = false;
            testProxyBtn.innerHTML = '<i class="fas fa-shield-alt"></i> Proxy testen';
          });
      });
    }
  });
})();
