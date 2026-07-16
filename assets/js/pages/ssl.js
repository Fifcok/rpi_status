/** Odświeżanie listy certyfikatów SSL. */

const SSL_STATUS_CLASS = { green: 'text-bg-success', yellow: 'text-bg-warning', red: 'text-bg-danger' };
const SSL_STATUS_LABEL = { green: 'OK', yellow: 'Wygasa wkrótce', red: 'Krytyczne' };

function renderCertificates(certs) {
    const tbody = document.querySelector('#sslTable tbody');
    if (!tbody) return;

    if (certs.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">Nie znaleziono certyfikatów.</td></tr>';
        return;
    }

    tbody.innerHTML = certs.map((cert) => `
        <tr>
            <td>${escapeHtml(cert.domain)}</td>
            <td class="text-muted small">${escapeHtml(cert.issuer)}</td>
            <td>${escapeHtml(cert.valid_until)}</td>
            <td>${cert.days_left}</td>
            <td><span class="badge ${SSL_STATUS_CLASS[cert.status]}">${SSL_STATUS_LABEL[cert.status]}</span></td>
        </tr>
    `).join('');
}

function loadCertificates() {
    apiFetch('/api/ssl_list.php').then((data) => renderCertificates(data.certificates)).catch(() => {});
}

document.getElementById('refreshSsl')?.addEventListener('click', loadCertificates);
