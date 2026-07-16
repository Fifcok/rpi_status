# RPi5 Admin Dashboard

Panel administracyjny dla Raspberry Pi 5 (PHP 8.3, Bootstrap 5, SQLite, Chart.js —
bez frameworków typu Laravel). Ciemny motyw w stylu Grafana/Proxmox/Home Assistant,
odświeżanie danych AJAX co 5 sekund.

## Wymagania

- Raspberry Pi OS / Debian z Apache 2.4 + `mod_php` lub `php-fpm`
- PHP 8.3 z rozszerzeniami: `pdo_sqlite`, `pdo_mysql` (opcjonalnie), `session`
- Uprawnienia `www-data` do odczytu `/proc`, `/sys/class/thermal`, wykonywania
  poleceń diagnostycznych (patrz sekcja *Sudoers* poniżej)

## Instalacja

```bash
sudo mkdir -p /var/www/rpi_status
sudo cp -r . /var/www/rpi_status
cd /var/www/rpi_status

# Katalogi zapisywalne przez serwer WWW
sudo chown -R www-data:www-data data logs backup
sudo chmod -R 750 data logs backup
```

### Konfiguracja Apache (VirtualHost)

```apache
<VirtualHost *:80>
    ServerName rpi.local
    DocumentRoot /var/www/rpi_status

    <Directory /var/www/rpi_status>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/rpi_status_error.log
    CustomLog ${APACHE_LOG_DIR}/rpi_status_access.log combined
</VirtualHost>
```

```bash
sudo a2enmod headers rewrite
sudo systemctl reload apache2
```

### Pierwsze logowanie

Domyślne konto tworzone automatycznie przy pierwszym uruchomieniu:

- **login:** `admin`
- **hasło:** `admin`

**Zmień hasło natychmiast po pierwszym zalogowaniu** (aktualnie edytując rekord w
`data/auth.sqlite` — `UPDATE users SET password_hash = ... WHERE username='admin'`,
gdzie hash generujesz np. przez `php -r "echo password_hash('nowe_haslo', PASSWORD_DEFAULT);"`).

## Zadanie cron: historia metryk + alarmy

Dashboard zapisuje próbkę CPU/RAM/dysk/temperatury co minutę i na tej podstawie
generuje alarmy (progi w `config.php`, stała `ALERT_THRESHOLDS`).

```bash
sudo crontab -e -u www-data
```

Dodaj:

```
* * * * * php /var/www/rpi_status/cron/collect_history.php >> /var/www/rpi_status/logs/cron.log 2>&1
```

## Sudoers — uprawnienia do poleceń diagnostycznych

Wiele danych (temperatura przez `vcgencmd`, logowania SSH przez `last`/`lastb`,
status firewalla, `fail2ban-client`, pełny `journalctl`) wymaga uprawnień, których
`www-data` domyślnie nie ma. Aplikacja **działa bez nich** (po prostu pokaże "—"
lub "brak danych"), ale aby uzyskać pełną funkcjonalność, utwórz plik
`/etc/sudoers.d/rpi-status` (przez `sudo visudo -f /etc/sudoers.d/rpi-status`):

```
www-data ALL=(root) NOPASSWD: /usr/bin/vcgencmd
www-data ALL=(root) NOPASSWD: /usr/sbin/smartctl
www-data ALL=(root) NOPASSWD: /usr/bin/last
www-data ALL=(root) NOPASSWD: /usr/bin/lastb
www-data ALL=(root) NOPASSWD: /usr/bin/journalctl
www-data ALL=(root) NOPASSWD: /usr/sbin/ufw
www-data ALL=(root) NOPASSWD: /usr/sbin/iptables
www-data ALL=(root) NOPASSWD: /usr/bin/fail2ban-client
www-data ALL=(root) NOPASSWD: /usr/bin/crontab
www-data ALL=(root) NOPASSWD: /usr/bin/tail
```

Aplikacja wywołuje te polecenia przez `sudo -n` (nieinteraktywnie) — jeśli reguła
NOPASSWD nie jest skonfigurowana, komenda po prostu nie zwróci danych, bez
zawieszania żądania.

### Docker

Aby panel mógł zarządzać kontenerami, dodaj `www-data` do grupy `docker`:

```bash
sudo usermod -aG docker www-data
sudo systemctl restart apache2
```

### MySQL / MariaDB (opcjonalnie)

Utwórz konto tylko do odczytu i uzupełnij dane w `config.php` (`DB_MYSQL_*`):

```sql
CREATE USER 'rpi_status_ro'@'localhost' IDENTIFIED BY 'silne-haslo';
GRANT SELECT, PROCESS ON *.* TO 'rpi_status_ro'@'localhost';
FLUSH PRIVILEGES;
```

Do wykonywania backupu (`mysqldump`) to samo konto potrzebuje też `LOCK TABLES`.

## Struktura projektu

```
rpi_status/
├── index.php              # Dashboard
├── login.php / logout.php
├── config.php              # Konfiguracja globalna
├── api/                    # Endpointy JSON (AJAX + REST)
├── includes/                # Logika backendowa (współdzielona)
├── pages/                   # Podstrony panelu
├── assets/css, assets/js    # Frontend
├── cron/collect_history.php # Zbieranie historii + alarmy (crontab)
├── data/                    # Bazy SQLite (poza repo)
├── logs/                    # Logi aplikacji (poza repo)
└── backup/                  # Archiwa backupu (poza repo)
```

## Bezpieczeństwo

- Wszystkie zapytania SQL używają PDO z parametrami przygotowanymi (brak SQL Injection).
- Wszystkie dane wyjściowe przechodzą przez `htmlspecialchars()` (brak XSS).
- Wszystkie wywołania poleceń systemowych używają `escapeshellarg()` i whitelist
  dozwolonych nazw usług/kontenerów/logów (brak Command Injection).
- Formularze i akcje POST (Docker, backup, alarmy) wymagają tokenu CSRF.
- Logowanie ma limit prób (5 nieudanych / 5 minut na login+IP).
- Hasła przechowywane wyłącznie jako hash (`password_hash`/`password_verify`).
- Katalogi `data/`, `logs/`, `backup/`, `includes/`, `cron/` są zablokowane na
  poziomie Apache (`.htaccess`).

## Publikacja na GitHub

```bash
git init
git add .
git commit -m "Initial commit: RPi5 Admin Dashboard"
git branch -M main
git remote add origin git@github.com:<twoj-uzytkownik>/<nazwa-repo>.git
git push -u origin main
```
