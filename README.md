# WP Media Cleaner

WordPress-plugin die de uploads-map scant op ongebruikte bestanden.

## Wat doet deze plugin?

WP Media Cleaner doorzoekt je `wp-content/uploads/` map en vergelijkt elk bestand met alle plekken op je website waar het gebruikt kan worden. Bestanden die nergens worden gerefereerd, worden verplaatst naar een veilige tussenmap (`te-beoordelen`). Daar kun je ze bekijken, herstellen, downloaden of definitief verwijderen.

## Kenmerken

- Scant alle bestandstypes in de uploads-map
- Controleert tegen: Media Library, post content, featured images, widgets, customizer, site-instellingen, ACF-velden, WooCommerce en alle wp_options
- Thumbnails worden meegenomen met het originele bestand
- Veilige tussenmap met `.htaccess`-beveiliging
- Herstel bestanden naar hun originele locatie
- Download bestanden als zip-archief
- Definitief verwijderen alleen na bevestiging
- Volledig logboek van alle acties
- Nonce-beveiliging op alle acties
- Alleen toegankelijk voor beheerders (`manage_options`)

## Installatie

1. Upload de map `wp-media-cleaner` naar `/wp-content/plugins/`
2. Activeer de plugin via het Plugins-menu in WordPress
3. Ga naar **Extra > Media Cleaner** om een scan te starten

## Gebruik

1. **Scan starten** — Klik op de knop om de uploads-map te scannen
2. **Beoordelen** — Bekijk de als ongebruikt gemarkeerde bestanden in het tabblad "Te beoordelen"
3. **Actie ondernemen** — Selecteer bestanden en kies: herstellen, downloaden of verwijderen
4. **Logboek** — Bekijk alle uitgevoerde acties in het Logboek-tabblad

## Vereisten

- WordPress 5.0 of hoger
- PHP 7.4 of hoger
- ZipArchive PHP-extensie (voor download als zip)

## Verwijdering

Bij het verwijderen van de plugin via WordPress worden de logtabel en de map `te-beoordelen` inclusief inhoud automatisch opgeruimd.
