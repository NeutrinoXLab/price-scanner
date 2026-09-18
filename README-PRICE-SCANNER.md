# Price Scanner — analiză de piață și preț

Modelul separă produse canonice, oferte comerciale, comercianți normalizați și furnizori B2B. Matching-ul păstrează clasificarea `exact`, `probable`, `similar` sau `unknown`, scorul și semnalele; propunerile incerte intră în review manual. Pagina produsului prezintă sellerii și ofertele din România, minimul, maximul, media, mediana, furnizorii și calculatorul economic transparent.

Sunt păstrate RON, EUR, USD și PLN în valoarea originală. Conversia este dezactivată până când există un curs cu furnizor și timestamp. Documentație: [arhitectură](docs/ARCHITECTURE.md), [matching](docs/MATCHING.md), [surse reale](docs/REAL_DATA_SOURCES.md).

## Publicare în producție

Pagina principală, căutarea, ofertele și produsele canonice sunt publice. Sincronizarea surselor, importurile, exportul, uploadul OCR, alertele, urmărirea și review-ul manual necesită autentificare. Nu există signup public. Instrucțiunile complete pentru `scan.novelions.ro`, inclusiv SQLite, permisiuni, build, health check și rollback, sunt în [PRODUCTION_DEPLOYMENT.md](docs/PRODUCTION_DEPLOYMENT.md).

Aplicație Laravel 13.31.0, PHP 8.3.30, Blade fără build JavaScript, SQLite, cache și coadă în baza de date. Proiect nou, separat de Novelion și Tender/SEAP Scanner. Nu folosește bazele lor de date sau codul lor.

## Pornire pe Windows / Laragon

În terminal, din acest director:

```powershell
php artisan serve --host=127.0.0.1 --port=8095
```

Deschide http://127.0.0.1:8095. Alternativ, rulează `start-local.ps1`. Pentru butonul Actualizează sursele, într-un al doilea terminal:

```powershell
php artisan queue:work --sleep=3 --tries=1 --timeout=240
```

Pentru programare, într-un al treilea terminal:

```powershell
php artisan schedule:work
```

Există și `start-worker.ps1` / `start-scheduler.ps1`. Scriptul worker are durată maximă de o oră; relansează-l sau folosește comanda de mai sus pentru o sesiune continuă. Nu sunt instalate servicii și nu este configurată pornirea automată la restart. În Windows Task Scheduler se poate configura ulterior rularea `php artisan schedule:run` în fiecare minut, cu directorul proiectului ca „Start in”, fără instanțe paralele, plus un worker supravegheat. PHP Windows nu are PCNTL: timeout-ul workerului nu garantează oprirea unui proces blocat. Cererile HTTP și procesul OCR au timeout propriu; înainte de producție este necesar un supervisor cu limită de execuție.

Pentru o verificare manuală a cozii:

```powershell
php artisan scanner:sync
php artisan queue:work --stop-when-empty --tries=1
php artisan schedule:list
php artisan test
```

Nu trebuie mutat în Laragon pentru a funcționa. Dacă se configurează ulterior un virtual host, DocumentRoot trebuie să fie `public`, niciodată rădăcina proiectului. Aplicația acceptă numai conexiuni loopback: nu este pregătită pentru mai mulți utilizatori sau acces prin LAN/internet. Orele afișate sunt UTC în configurația livrată.

## Ce este implementat

- Căutare în catalogul local după denumire, model, SKU sau GTIN. Normalizare litere/diacritice/separatori. GTIN-8/12/13/14 cu validarea cifrei de control și reprezentare canonică de 14 cifre. Un GTIN valid diferit sau lipsă nu este declarat identic.
- Matching text explicabil: proporția termenilor găsiți în titlu/model/SKU; prag 50%. Este o euristică, nu probabilitate de identitate. Toate potrivirile text rămân „Similar”, inclusiv denumirile identice. Marca, capacitatea, culoarea, starea și pachetul trebuie confirmate; nu sunt deduse automat.
- Oferte separate după sursă, ID extern și seller. Banii se stochează în bani întregi, numai RON. Livrarea necunoscută rămâne NULL. Comparația minimului exclude lipsa stocului, totalul necunoscut și datele mai vechi de 24 ore; diferențele se calculează numai pentru GTIN identic. Nu se calculează conversii valutare sau livrare în funcție de adresă/coș.
- Istoric: punct nou la schimbarea prețului, livrării sau disponibilității; revenirea la un preț anterior este păstrată. Preț actual, minim, maxim, tabel și grafic SVG cu axa timpului. Statisticile și graficul sunt pentru prețul produsului, fără livrare. Ultima verificare se actualizează și dacă feedul răspunde 304.
- Urmărire pe ofertă, prag strict „sub”, alerte interne o singură dată per trecere sub prag. Totalul trebuie cunoscut, oferta în stoc. Revenirea peste prag rearmează alerta. Notificările pot fi marcate citite; urmărirea se poate opri. Evenimentul `PriceThresholdReached`, emis după commit, permite viitori listeneri email; niciun email nu este trimis acum.
- Job separat per sursă, blocare concurență, tranzacții, rezultate independente, stare sursă, timeout HTTP, interval implicit 6 ore și minim o oră, ETag / Last-Modified. HTTP 429 sau indisponibilitatea nu produc retry imediat. Nu există scraping sau ocolire de protecții.
- Export CSV pentru integrare ulterioară cu Novelion, cu protecție contra formulelor de spreadsheet. Nu există conexiune cu Novelion sau API public. Minimul/mediana pieței și marjele Novelion sunt etapă viitoare.
- Upload JPG/PNG/WebP maximum 5 MB și 6000×6000. Interfață de identificare + adaptor Tesseract local, opțional. Nu este instalat/configurat Tesseract în mediul verificat, deci uploadul explică limitarea. Nu se pretinde recunoaștere. Textul OCR, când există, trebuie confirmat în câmpul de căutare. Fără servicii AI plătite, fără chei externe, fără stocarea permanentă a imaginii. Similaritatea vizuală nu este implementată.

## Surse reale: situație exactă

**Niciun magazin nu este activat sau verificat cu oferte live.** Cele trei intrări de configurare folosesc momentan un adaptor comun pentru CSV canonic, nu adaptoare native validate pe feeduri reale ale magazinelor.

| Sursă investigată | Cale legitimă identificată | Ce lipsește |
|---|---|---|
| Libris | Program de afiliere listat în 2Performant; feed numai dacă programul aprobat îl oferă | Cont, aprobare pentru proiect/comparator, acces feed, schema și condițiile programului |
| Cărturești | Program de afiliere listat în 2Performant; aceleași condiții | Aprobare, feed concret, schema și condițiile programului |
| eMAG | Program Profitshare investigat; Marketplace API este destinat partenerilor seller | Confirmare explicită a disponibilității și dreptului de utilizare a catalogului, feed/API adecvat și acoperirea sellerilor |

Surse consultate la 10 septembrie 2026:

- [Director programe 2Performant](https://ro.2performant.com/affiliate-programs/): listează Libris și Cărturești.
- [Termeni 2Performant](https://2performant.com/terms-conditions/): relația de afiliere și feedurile depind de programele acceptate.
- [Instrumente pentru afiliați](https://2performant.com/guides/advertisers-guide-in-2performant/articles/promoting-tools-affiliates/): feeduri pentru afișarea/compararea produselor.
- [Program eMAG Profitshare](https://profitshare.ro/affiliate-programs/retail/emag): existența programului nu demonstrează acces la întreg catalogul sau toți sellerii.
- [Documentație Marketplace eMAG](https://marketplace.emag.ro/documentation/api/external) și [prezentare API](https://marketplace.emag.ro/infocenter/emag-academy/cum-se-adauga-un-produs/importul-prin-feed/documentatie-tehnica/): autentificare și operații pentru parteneri; nu este tratat drept catalog public al concurenței.

Nu am acceptat termeni, creat conturi, folosit credențiale sau verificat condiții individuale din conturi private. Înainte de activare trebuie verificate permisiunea pentru comparator, frecvența, retenția datelor, atribuirile și cerințele de link afiliat. Termenii publici nu substituie aprobarea programului.

## Activarea unui catalog aprobat

Se configurează numai în `.env`: `LIBRIS_FEED_URL` / `CARTURESTI_FEED_URL` / `EMAG_FEED_URL` și variabila corespunzătoare `*_FEED_APPROVED=true`. Fără URL și aprobare nu se face nicio cerere. URL-urile sunt setate de operator, nu prin formulare; se folosesc numai endpointuri HTTPS de încredere. Redirecturile sunt dezactivate și maximum 10 MB / 30.000 oferte. URL-ul poate conține un token: nu îl pune în Git, loguri sau capturi.

**Nu introduce direct un feed necunoscut și nu presupune compatibilitatea.** Implementarea curentă cere CSV UTF-8 cu separator virgulă, ghilimele standard, antetul:

```csv
external_id,seller,title,url,price,shipping,currency,availability,ean,model,sku
```

Preț și livrare: număr pozitiv sau zero, maximum două zecimale, fără separator de mii. Livrarea goală = necunoscută. Monedă RON. Disponibilitate: `in_stock`, `out_of_stock`, `preorder`, `unknown`. EAN/model/SKU pot fi goale. Sellerul trebuie furnizat explicit, nu presupus a fi magazinul. Feedul online reprezintă un catalog complet: ofertele dispărute sunt marcate cu disponibilitate necunoscută. Catalogul gol sau orice rând invalid respinge importul complet, păstrând datele anterioare. Un nou format se implementează prin `OfferProvider` și `ProviderRegistry`, după primirea unui exemplu real autorizat.

Un export local autorizat în aceeași schemă se poate importa:

```powershell
php artisan scanner:import libris "C:\cale\catalog.csv" --approved
```

Importul local actualizează numai rândurile furnizate și păstrează proveniența `local_file`; data reprezintă importul, nu verificarea magazinului. Flagul confirmă dreptul de utilizare, nu obține acel drept. Nu există date demonstrative în baza livrată.

## Înainte de producție

Sunt necesare minimum două feeduri reale autorizate, adaptoare native validate și comparații manuale cu paginile magazinelor, condiții de livrare/VAT/variante, autentificare și separarea utilizatorilor, API autentificat, monitorizare, backup, testare la volum și supravegherea proceselor. Căutarea curentă parcurge catalogul local și afișează primele 100 rezultate; pentru cataloage mari trebuie indexare/paginare. Retenția istoricului este nelimitată în această versiune. Pentru HTTP 429 se așteaptă intervalul configurat; eventualele intervale contractuale mai lungi trebuie configurate înainte de activare.

Pe alt calculator: `composer install`, copiere `.env.example` în `.env`, `php artisan key:generate`, creare `database/database.sqlite`, `php artisan migrate`. Nu distribui `.env` cu secretele tale. Pentru OCR ulterior: instalează separat Tesseract dintr-o sursă aprobată și setează `SCANNER_OCR_BINARY` la executabil; limba engleză este folosită pentru coduri/model. Această integrare OCR nu a fost testată cu un executabil real aici.
