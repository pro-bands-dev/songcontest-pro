## 3.0.0-dev7.11.1
- Sicherheit: „Löschen“ und „Freigeben“ unter Bewerbungen funktionierten über einfache Links ohne Sicherheitstoken. Ein präparierter Link (z. B. in einer E-Mail oder auf einer fremden Seite) hätte als angemeldeter Admin Bewerbungen löschen können. Jetzt nur noch mit gültigem Token (Nonce); ungültige Links zeigen einen Hinweis und tun nichts.
- Sicherheit: Speichern im Bewerbungs-Editor, Speichern der E-Mail-Texte sowie „Fix in TOP“ / „TOP entfernen“ in der Jury-Vorauswahl ebenfalls nur noch mit Token.
- Neu: Band-Profil-Seite liegt fest unter /contest/band/ (wird vor der Shortcode-Suche geprüft). Alte Links wie /song-contest-2026/band/?band=12 oder /band/?band=12 werden per 301 inkl. ?band= und ?ref= weitergeleitet; eine zweite Seite mit [songcontest_band_profile] ebenfalls.
- Fix: Apostrophe wurden beim Speichern im Bewerbungs-Editor und bei den E-Mail-Texten mit Backslash gespeichert.
- Keine Datenbankänderung.

## 3.0.0-dev7.11.0
- Neu: Shortcode `[scp_home_teaser]` für die Startseite. Wechselt je nach Phase: Bewerbung (Zähler „X Bands im Rennen“ + neueste freigegebene Bewerber), Jury-Vorauswahl, Voting (Live-Charts Top 5 mit Bild, Votes und Balken), Auswertung (Endstand), Finale (Finalisten ohne Punktestand), Gewinner, Pause (letzter Gewinner). Countdown wie beim Phasen-Banner.
  Der wechselnde Teil wird nach dem Laden frisch über `/wp-json/scp/v1/home-teaser` geholt (Seiten-Cache spielt keine Rolle) und in der Voting-Phase alle 45 s aktualisiert. Serverseitig 30 s zwischengespeichert.
  Attribute: `title`, `count` (4), `top` (5), `status` (`approved`|`all`), `refresh` (45), `hide_closed`. Testen mit `?testphase=…`.
- Neu: Bandfotos werden beim Upload nicht mehr auf 400 px verkleinert. Das Original bleibt erhalten (Band-Profil, Gewinnerseite); zusätzlich entstehen `…-scp500.jpg` (500 × 500) und `…-scp250.jpg` (250 × 250), mittig zugeschnitten.
  Voting-Liste, Jury, Band-Grid, Ergebnis-Archiv und Finale nutzen 500 × 500, Backend-Listen und QR-Karten 250 × 250. Fehlt eine Version, wird das Original gezeigt.
- Neu: Backend „Song Contest → Bildversionen“ erzeugt die Versionen für vorhandene Bewerbungen. Beim Ändern des Bildes im Bewerbungs-Editor werden sie automatisch erzeugt.
- Neu: WAV-Dateien werden im Browser der Band vor dem Upload in MP3 (192 kbit/s) umgewandelt (LAME, `assets/vendor/lame.min.js`, LGPL). Gespeichert wird nur die MP3. Der Server nimmt nur noch MP3 an; nach einem Fehler bleiben die Formulareingaben erhalten.
- Fix: Apostrophe und Anführungszeichen im Bewerbungsformular wurden mit Backslash gespeichert (z. B. „Guns N\' Roses“).
- Keine Datenbankänderung.

## 3.0.0-dev7.10.1
- Neu: Jury-Vorstellung je Contest (öffentliche Info-Seite für Bands). Auswahl unter „Song Contest → Seiten & Links“ je Contest; automatisch erkannt werden /contest/jury<Jahr>/ und /<contest-slug>/jury/ (z. B. /contest/jury2027/, /song-contest-2026/jury/).
- Neu: Shortcode `[songcontest_jury_link]` – Button zur Jury-Vorstellung des aktiven Contests (Attribute `label`, `class`, `contest_id`); ohne hinterlegte Seite keine Ausgabe.
- Neu: Phasen-Leiste verlinkt im Schritt „Jury-Vorauswahl“ auf die Jury-Vorstellung („Wer ist die Jury? →“).
- Fix: festival.css wurde auf allen Adressen mit „/jury“ geladen und hätte dort Schrift und Divi-Abstände verändert (z. B. /contest/jury2027/, /jury-vorauswahl/). Jetzt nur noch Backend, Festival-Bildschirme und Seiten mit `[songcontest_jury_voting]`.

## 3.0.0-dev7.10.0
- Neu: WordPress-Rolle „Jury“ (`scp_jury`) mit dem Recht `scp_jury`. Administratoren erhalten das Recht automatisch; vorhandene Rollen mit „jury“ im Namen ebenfalls. Die Einrichtung läuft beim ersten Seitenaufruf nach dem Update.
- Neu: Backend-Menü „Jury“ für Jurymitglieder mit „Vorauswahl“ (Bands anhören und auswählen, gleicher Look wie auf der Website) und „Jury Dashboard“. Dashboard, Beiträge, Kommentare und Werkzeuge sind für reine Jury-Konten ausgeblendet.
- Neu: Für Administratoren „Song Contest → Jury-Ansicht“ (so sieht die Jury die Vorauswahl).
- Neu: Jury-Seite unter „Seiten & Links“ (automatisch /jury-vorauswahl/ bzw. Seite mit `[songcontest_jury_preselect]`). Jurymitglieder landen nach dem Login dort; die Seite ist für Suchmaschinen gesperrt (noindex).
- Sicherheit: `[songcontest_jury_preselect]`, `[songcontest_jury_voting]` sowie das Speichern von Auswahl (`scp_toggle_jury_pick`) und Jury-Wertung (`scp_jury_vote`) sind nur noch für Jurymitglieder und Administratoren möglich – vorher genügte ein beliebiges Login. Nicht freigeschaltete Besucher sehen einen Hinweis mit Jury-Login.
- Geändert: „Song Contest → Jury Dashboard“ nur noch für Administratoren (vorher alle ab Rolle Mitarbeiter); Jurymitglieder nutzen das Menü „Jury“.
- Geändert: `[songcontest_jury_preselect]` zeigt ohne `contest_id` den öffentlich aktiven Contest (wie Voting und Charts).
- Final-Voting: Die Rolle „Jury“ erscheint automatisch in der Auswahl der Final-Jury.
- Keine Datenbankänderung; bestehende Auswahlen und Wertungen bleiben erhalten.

## 3.0.0-dev7.9.2
- Neu: Jury-Vorauswahl `[songcontest_jury_preselect]` im dunklen PRO-BANDS-Look (Karten-Raster, Audio, „Auswählen“-Button; gewählte Bands mit Goldrahmen). Gilt auch, wenn festival.css geladen ist.
- Geändert: Seiten mit `[songcontest_jury_preselect]` laden festival.css nicht mehr zusätzlich.

## 3.0.0-dev7.9.1
- Fix: Einwilligungs-Checkbox im Bewerbungsformular wurde durch ältere, allgemeine Formularregeln (Divi/Theme-Optionen) auf volle Breite aufgebläht; der Text rutschte aus der Box. Checkbox, Beschriftung und Absende-Button sind jetzt gegen fremde Regeln abgesichert.

## 3.0.0-dev7.9.0
- Neu: Schlankes Stylesheet für alle öffentlichen Seiten (Bewerbung, Voting, Band-Profil, Charts, Ehemalige, Gewinner, Ergebnis-Archiv, Voting-Anmeldung, Countdown, Phasen-Leiste) im PRO-BANDS-Look. Farben und Schriften kommen aus dem PRO-BANDS Child-Theme (`--pb-*`), mit Ersatzwerten. Rund 25 statt 56 KB, keine Altlasten mehr (u. a. 27 nicht mehr genutzte Klassen entfernt).
- Neu: `assets/festival.css` enthält den bisherigen vollständigen Stand unverändert. Wird nur noch geladen im Backend, auf den Festival-Bildschirmen (`?scp_final_screen=…`) und auf Jury-Seiten (/jury…, `[songcontest_jury_voting]`, `[songcontest_jury_preselect]`). Festival-Cockpit, QR-Wand, Beamer und Jury sehen daher aus wie bisher.
- Geändert: Countdown auf der Bewerbungsseite (vor Start der Bewerbungsphase) nutzt den einheitlichen kompakten Countdown.
- Fix: „Link kopieren“ im Band-Profil kopierte eine falsche Adresse (contest.pro-bands.de). Jetzt wird der echte Voting-Link inkl. ref kopiert.
- Keine Änderung an Formularfeldern, Abläufen oder Datenbank.

## 3.0.0-dev7.8.29
- Neu: Backend-Seite „Seiten & Links“ (Song Contest → Seiten & Links). Hier werden die festen öffentlichen Seiten gewählt: Contest-Seite, Bewerbungsseite, Voting-Seite, Band-Profil-Seite. Ohne Auswahl werden sie automatisch erkannt (Seite mit passendem Shortcode bzw. /contest/, /song-contest-bewerbung/, /voting/).
- Fix: Links wurden aus dem Contest-Slug gebaut (z. B. /song-contest-2027/). Ohne passende WordPress-Seite führte das zu „Seite nicht gefunden“. Banner, Charts, Voting-Liste, Band-Profile und E-Mails nutzen jetzt die festen Seiten. Die Inhalte kommen weiterhin aus dem aktuell öffentlichen Contest.
- Fix: Fest eingetragene Adressen auf /song-contest-2026/ (Voting-Liste, Charts) durch die festen Seiten ersetzt.
- Neu: Alte Contest-Adressen (/<contest-slug>/, …/band/, …/song-contest-voting/) werden per 301 auf die festen Seiten umgeleitet – nur wenn dort keine Seite existiert. Parameter wie ?band= und ?ref= bleiben erhalten.
- Keine Datenbankänderung.

## 3.0.0-dev7.8.28
- Neu: `[songcontest_phase_banner layout="buttons"]` gibt nur die zur Phase passenden Buttons (Bewerben / Abstimmen / Finale …) plus Countdown aus – z. B. für den Hero der Contest-Seite.
- Neu: Attribut `info_label` für die Beschriftung des Info-Buttons (z. B. „So läuft's“ mit `info_url="#ablauf"`).
- Geändert: „noch X Tage“ in der Überzeile entfällt, wenn der Countdown angezeigt wird (keine doppelte Angabe mehr).
- Countdown unter Buttons erhält Abstand (`.scp-phase-buttons`).

## 3.0.0-dev7.8.27
- Neu: Kompakter Countdown (Tage · Std · Min · Sek) im `[songcontest_phase_banner]`, passend zur Phase („Bewerbungsschluss in", „Voting endet in", „Live-Finale in" …). Abschaltbar mit `countdown="0"`.
- Fix: `[songcontest_countdown]` gab bisher nur einen leeren Container aus (kein Skript vorhanden). Er zeigt jetzt denselben kompakten Countdown inkl. Beschriftung; eigener Text per `label="…"`.
- Zeitpunkte werden korrekt in der Zeitzone der Website umgerechnet. Läuft der Countdown ab, lädt die Seite einmal neu und zeigt die neue Phase.
- Kein externes Skript, keine Datenbankänderung.

## 3.0.0-dev7.8.26
- Neu: Shortcode `[songcontest_phase_timeline]` – Phasen-Leiste für die Contest-Seite mit allen sechs Schritten (Bewerbung, Jury-Vorauswahl, Online-Voting, Finalisten, Live-Finale, Gewinner), Zeiträumen und automatischer Markierung „Abgeschlossen / Läuft jetzt / Als Nächstes / Demnächst".
- Nutzt dieselbe Phasen-Erkennung wie `[songcontest_phase_banner]`; zwischen zwei Contests wird der nächste geplante Contest gezeigt.
- Attribute: `contest_id`, `final_text` (eigener Text fürs Live-Finale).
- Gestaltung in `assets/style.css` (`.scp-timeline`), Farben aus dem PRO-BANDS Child-Theme mit Ersatzwerten. Nur Lesezugriffe, keine Datenbankänderung.

## 3.0.0-dev7.8.25
- Neu: Shortcode `[songcontest_phase_banner]` – Contest-Banner, das sich automatisch an die aktuelle Phase anpasst (nächster Contest, Bewerbung, Jury-Vorauswahl, Voting, Auswertung, Live-Finale, Gewinner, Pause). Überzeile, Text und Buttons wechseln je nach Phase, inkl. „noch X Tage".
- Erkennt auch die Lücken zwischen den Phasen (z. B. Jury-Vorauswahl zwischen Bewerbungsende und Voting-Start, Wartezeit vor dem Finale).
- In der Gewinnerphase wird der Gewinner (Status „winner") genannt und, falls geplant, der Start des nächsten Contests angekündigt.
- Standard-Links: Bewerbung `/song-contest-bewerbung/`, Voting `/voting/`.
- Links per Attribut anpassbar: `apply_url`, `voting_url`, `info_url`, `winner_url`, `archive_url`; `hide_closed="1"` blendet das Banner aus, wenn kein Contest geplant ist.
- Gestaltung über die Klassen des PRO-BANDS Child-Themes (`pb-banner`, `pb-eyebrow`, `pb-btn`). Nur Lesezugriffe, keine Datenbankänderung.
- Versionsangaben vereinheitlicht (Plugin-Header, SCP_VERSION, VERSION.txt).

## 3.0.0-dev7.8.18
- Neu: Auf der Voting-Seite erscheint für eingeloggte Administratoren eine Diagnose-Box (angezeigter Contest, erkannte Phase, Phasen-Zeiträume mit „jetzt"-Markierung, Bands je Status, mögliche Ursachen, falls kein Voting-Button erscheint). Besucher sehen sie nicht.

## 3.0.0-dev7.8.17
- Fix: Voting-Liste, Stimmabgabe und Voter-Login nutzten den "öffentlichen" Contest (Status Aktiv bzw. nächster Entwurf), Band-Profil, Countdown und Charts dagegen den im Backend gesetzten "aktuellen" Contest. Bei unterschiedlicher Auswahl zeigten Seiten verschiedene Contests, und "Mehr erfahren" führte zu "Band nicht gefunden". Alle öffentlichen Seiten folgen jetzt derselben Auswahl (`scp_get_frontend_contest_id()`).
- Fix: Band-Profil und Charts berechnen die Phase für den angezeigten Contest und nicht für den Backend-Contest.
- Fix: Teilen-Links im Band-Profil und der Voting-Link in den Charts waren fest auf `/song-contest-2026/` eingestellt und folgen jetzt dem Contest.

## 3.0.0-dev7.8.16
- Neu: Backend-Seite „Datenschutz-Bereinigung" (Song Contest → Datenschutz-Bereinigung) mit Vorschau. Drei Jahre nach Ende eines archivierten Contests können Kontaktdaten und Bewerbungstexte geleert werden. Es werden keine Bewerbungen gelöscht; Band Grid, Ergebnisseiten und Votes bleiben unverändert.
- Bei bestätigter, nicht widerrufener Einwilligung für Infos & Angebote bleiben E-Mail und Ansprechpartner erhalten.
- Die Bereinigung läuft nie automatisch. Ein Hinweis im Backend meldet, wenn etwas fällig ist.
- Neue Spalte `contact_purged_at` in der Bewerbungstabelle.
- Datenschutzhinweis im Formular an das Verfahren angepasst (Rückblick bleibt sichtbar, Löschwunsch jederzeit).

## 3.0.0-dev7.8.15
- Datenschutzhinweis um Speicherdauer ergänzt (nach Contest-Ende längstens drei Jahre; E-Mail für Tipps und Angebote bis zum Widerruf).

## 3.0.0-dev7.8.14
- Neu: Datenschutzhinweis im Bewerbungsformular (Zweck, Speicherung ab Contest-Beginn, Nutzung der E-Mail-Adresse für Benachrichtigungen sowie Hinweise und Tipps rund um den Contest, Link zur Datenschutzerklärung).

## 3.0.0-dev7.8.13
- Neu: Freiwilliges, nicht vorangekreuztes Kästchen im Bewerbungsformular für Tipps & Angebote per E-Mail.
- Neu: Double-Opt-In. Die Einwilligung gilt erst nach Klick auf den Bestätigungslink (14 Tage gültig, Einmal-Link).
- Neu: Zeitpunkt der Anfrage, Zeitpunkt der Bestätigung, Widerruf und exakter Wortlaut werden je Bewerbung gespeichert.
- Neu: Bewerbungsübersicht mit Spalte „Infos & Angebote", Filter und CSV-Export bestätigter Einwilligungen.
- Neu: Widerruf im Backend (Bearbeiten-Ansicht) und Abmelde-Link-Funktion `scp_marketing_unsubscribe_url()` für künftige Mails.
- Neu: `scp_get_marketing_recipients()` liefert nur Kontakte mit bestätigter, nicht widerrufener Einwilligung.
- Neu: E-Mail-Vorlage für die Bestätigungsmail unter Song Contest → E-Mail Einstellungen.
- Fix: Datenbank-Versionsprüfung verglich gegen einen nie geschriebenen Wert, wodurch die Installationsroutine bei jedem Seitenaufruf lief. Jetzt wird `SCP_DB_VERSION` einheitlich geprüft und gespeichert.
- Versionsangaben (Plugin-Header, SCP_VERSION, VERSION.txt) vereinheitlicht.
- Bestehende Bewerbungen bleiben unverändert und gelten als „keine Einwilligung". Contest-Mails sind nicht betroffen.

## 3.0.0-dev7.8.7
- Gewinnerfoto ragt nun oben links über den goldenen Rahmen hinaus.
- Gewinnername erhält deutlich mehr Breite und bleibt bei üblichen Bandnamen einzeilig.
- Reveal-Fotos Platz 5–2 ragen leicht über die linke Kartenkante hinaus.

## 3.0.0-dev7.8.6
- Quadratische Bandfotos in Publikums-QR-Karten und Final-Jury.
- Dynamisch um ca. 17° gedrehte Bandfotos bei Reveal Platz 5–2 und Platz 1.
- Gewinnerfoto prominent am goldenen Gewinnerrahmen.
- Neutraler Initialen-Platzhalter, falls kein Bandfoto vorhanden ist.

## 3.0.0-dev7.8.2
- Redesigned public Final-Voting QR monitor for festival use with Neuendorf Rockt background and branding.
- Added Neuendorf Rockt and PRO-BANDS Song Contest 2027 logos.
- Optimized five QR cards and call-to-action for 16:9 / Full-HD displays while retaining responsive fallback.
- QR generation, 30-second rotation and voting security logic unchanged.

## 3.0.0-dev7.4
- Contest-Termine sind die einzige Quelle für Phasen/Countdown; globale Altoptionen werden bei vorhandenem Contest nicht mehr verwendet.
- Countdown nutzt automatisch den nächsten Termin des aktuellen Contests.
- Voting-Mail ersetzt veraltete 2026-Vorlage durch dynamische Contest-Daten und Contest-URL.

## 3.0.0-dev7.1
- Fix: Verifizierte Votes von gleichzeitig in WordPress angemeldeten Administratoren behalten jetzt die `voter_id`; Admin-Bypass greift nur noch ohne Voter-Session.
- Neu: Nach erfolgreicher E-Mail-Bestaetigung wird der urspruenglich angeforderte Vote automatisch ausgefuehrt.
- Fix: Einmal-Parameter fuer den Auto-Vote werden vor der Stimmabgabe aus der URL entfernt, damit ein Refresh keinen erneuten Auto-Vote ausloest.

# Changelog

## 3.0.0-dev5 - 2026-07-11

### Added
- Bewerbungsseite zeigt einen Countdown bis zur nächsten Bewerbungsphase.
- Bewerbungsseite zeigt phasenabhängige Hinweise, wenn keine Bewerbung möglich ist.
- Instagram-Hinweis, wenn der aktuelle Contest in der Winner-Phase ist und kein neuer Contest geplant ist.

### Changed
- Das Bewerbungsformular wird ausschließlich während einer tatsächlich geöffneten Bewerbungsphase ausgegeben.
- Der nächste geplante Contest wird anhand des frühesten zukünftigen `application_start` ermittelt.

## 3.0.0-dev4 - 2026-07-09

### Fixed
- Bewerbungsformular schreibt nicht mehr automatisch in `contest_id = 1`.
- Bewerbungen werden nur noch einem Contest mit aktuell offener Bewerbungsphase zugeordnet.
- Schreibfehler `archieved` wird auf `archived` normalisiert.
- Gewinnerstatus und Scheduler arbeiten contestbezogen.

### Changed
- `scp_get_winner()`, `scp_set_winner_status()` und `scp_set_finalists()` akzeptieren jetzt optional eine `contest_id`.
- Scheduler verarbeitet Phasen pro Contest mit separaten Prozess-Flags.
- Bewerbungsformular zeigt geschlossen an, wenn kein Bewerbungscontest aktiv ist.


## 3.0.0-dev3 - 2026-07-07

### Added
- Contestbezogene Admin-Auswahl für Dashboard, Bewerbungen, Statistik und Jury-Dashboard.
- Zusammenführung der Phasensteuerung in die Contestverwaltung.
- Felder `max_voting_participants` und `max_finalists` im Installer/Migrationspfad.
- Contestbezogener manueller Versand der Voting-Mails.

### Changed
- Bewerbungen, Votes, Jury-Votes, öffentliche Votingansicht, Charts und Bandprofile berücksichtigen `contest_id`.
- `admin/phases.php` leitet auf die Contestverwaltung weiter.
- IP-Geodaten werden über `scp_ip_cache` statt über alte Vote-Datensätze gecacht.
- Jury-Votes werden pro Contest/Juror/Band aktualisiert statt dupliziert.


## 3.0.0-dev2 - 2026-07-07

### Fixed
- Contestverwaltung verwendet jetzt das Datenbankfeld `year` statt `contest_year`.
- Neuer Contest kann wieder ohne SQL-Fehler angelegt werden.

### Changed
- Contest-Tabelle enthält vollständig die Auswertungsphase `vaus_start` und `vaus_end`.
- Admin-Contestübersicht zeigt die Voting-Auswertung zwischen Top20 und Finale an.
- Installer migriert frühe v3-Dev-Installationen von `contest_year` nach `year`, falls nötig.

## 3.0.0-dev1 - 2026-07-07

### Added
- Contestverwaltung mit neuer Tabelle `scp_contests`.
- `contest_id` in Bewerbungen, Votes, Jury-Votes und Jury-Preselect.
- Eigene IP-Cache-Tabelle `scp_ip_cache`.
- Logging-Tabelle `scp_logs`.
- Admin-Seite "Contestverwaltung".
- Helper-Funktionen für aktiven Contest und Contest-Phasen.

### Changed
- `install.php` erstellt jetzt die vollständige aktuelle Datenbankstruktur für Neuinstallationen.
- Bestehende Datensätze werden bei Aktivierung/Migration automatisch einem Default-Contest zugeordnet.
- Neue Bewerbungen, Votes und Jury-Votes werden mit `contest_id` gespeichert.
- IP-Geodaten werden aus `scp_ip_cache` gelesen und nicht mehr aus der Vote-Tabelle gecacht.
- Kernfunktionen für Gewinner, Finalisten, Top-Bands und Live-Votes sind contest-aware.

### Notes
- Dies ist ein technischer Dev-Release für SongContest Pro 3.0.
- Vor Live-Einsatz Backup von Dateien und Datenbank erstellen.


## 3.0.0-dev7.8.1
- Festival-Cockpit CSS is now loaded in WordPress admin.
- Reworked Final-Voting stage control with 5 visual workflow steps, prominent test mode, human-readable status and jury progress.
- Public QR access is disabled until public voting is running.
- Jury voting cannot be closed in the UI until all jurors have completed all five finalists.
## 3.0.0-dev7.8.10
- Testmodus verwendet jetzt dieselbe gespeicherte Final-Jury wie der Produktivbetrieb.
- Entfernt den alten Test-Fallback auf den startenden Administrator als einzigen Juror.
- Jury-Fortschritt, QR-/E-Mail-Login und Vollständigkeitsprüfung laufen im Test damit mit allen aktivierten Final-Juroren.

