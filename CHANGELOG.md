# Changelog

## 1.3.0 — 2026-03-25

### Toegevoegd
- Configureerbare uitgesloten mappen: voeg eigen submappen van uploads/ toe die de scanner moet overslaan
- Instellingen-sectie op de Scan-pagina met tekstveld voor extra mappen
- Standaard uitgesloten mappen worden getoond als inklapbare lijst
- Input wordt gesanitized tegen path traversal (geen slashes, punten of speciale tekens)

## 1.2.0 — 2026-03-22

### Beveiligd
- Path traversal preventie: alle bestandsoperaties valideren nu dat paden binnen de verwachte directory blijven
- Zip-bestanden gebruiken nu onvoorspelbare bestandsnamen (voorkomt race condition)
- GET-parameters gevalideerd tegen allowlists (tab, log_action)
- Uninstall bewaart de te-beoordelen map als deze niet leeg is

## 1.1.0 — 2026-03-22

### Verbeterd
- Scanner filtert nu alleen op mediabestanden (afbeeldingen, video, audio, documenten) — plugin-bestanden zoals .json, .log, .php, .dat worden overgeslagen
- Bekende plugin-mappen in uploads worden uitgesloten (wc-logs, sucuri, woocommerce_uploads, etc.)
- Detectie van ongebruikte Media Library items (geregistreerd maar nergens gebruikt)
- Totale omvang (MB) van gevonden rommel wordt getoond bij scanresultaat en samenvatting

## 1.0.0 — 2026-03-22

### Toegevoegd
- Scan van de uploads-map op ongebruikte bestanden
- Controle tegen: Media Library, post content, featured images, widgets, customizer, site-instellingen, ACF-velden, WooCommerce en wp_options
- Verplaatsen van ongebruikte bestanden naar `te-beoordelen` map
- Herstel van bestanden naar originele locatie
- Download van bestanden als zip-archief
- Definitief verwijderen met bevestiging
- Logboek met filtering en paginering
- Nonce-beveiliging op alle acties
- `.htaccess`-beveiliging van de `te-beoordelen` map
- Automatische opruiming bij verwijdering van de plugin
