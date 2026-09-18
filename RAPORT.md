# Raport Price Scanner — 10 septembrie 2026

Am creat o aplicație locală Laravel separată, cu nucleul implementat și verificat. **Nu este încă un comparator conectat la magazine live.** Novelion și Tender/SEAP Scanner nu au fost modificate.

Proiectul se află în:

`C:\Users\NeutrinoX\Documents\Codex\2026-09-10\referenced-chatgpt-conversation-this-is-an-3\outputs\price-scanner`

Adresa locală: [Price Scanner](http://127.0.0.1:8095). Serverul și un worker au fost pornite pentru sesiunea curentă; workerul se oprește după maximum o oră. Nu este instalat un serviciu permanent. Instrucțiunile de relansare sunt în [ghidul proiectului](README-PRICE-SCANNER.md).

| Componentă | Stadiu verificat |
|---|---|
| Denumire / model / SKU | Căutare normalizată în catalogul local; scor explicabil, rezultate etichetate similare |
| EAN / GTIN | Validare check digit și potrivire canonică exactă; lipsa identificatorului nu confirmă identitatea |
| Comparare | Selleri separați, livrare necunoscută distinctă de zero, totaluri și diferențe pentru GTIN identic |
| Istoric | Puncte numai la schimbare, minim/maxim de preț produs, tabel și grafic |
| Alerte | Prag strict sub total, numai în stoc, deduplicare per trecere, marcaj citit și oprire urmărire |
| Scanări | Jobs separate, locks, scheduler pregătit, interval de acces și răspunsuri parțiale |
| Novelion | Export CSV pregătit; fără modificări sau conexiuni către Novelion |
| Imagine | Upload și interfață OCR locală; Tesseract neconfigurat, identificarea reală și similaritatea vizuală indisponibile |

**Surse live funcționale: 0.** Libris și Cărturești au programe listate în 2Performant, iar eMAG are program Profitshare investigat. Nu există în acest proiect acces aprobat, URL de feed sau credențiale. Nu am presupus că eMAG Marketplace API oferă catalogul concurenței. Configurațiile celor trei magazine folosesc un adaptor CSV canonic comun; ele nu sunt adaptoare native validate. Activarea necesită acces autorizat și validarea/adaptarea schemei reale. Ghidul conține linkurile oficiale consultate și pașii exacți.

Nu s-a făcut scraping, nu s-au ocolit protecții, nu s-au creat conturi și nu s-au trimis mesaje terților. Nu există oferte fictive în baza principală. Importul local de CSV autorizat este disponibil, cu proveniență afișată distinct față de verificarea online.

## Verificări efectuate

- Suita finală: **20 teste trecute, 68 assertions**, după formatarea codului. Acoperă matching, istoric, alerte, selleri, CSV invalid, HTTP 429, HTTP 304, limitarea frecvenței, surse dezactivate, izolarea eșecului, export, upload și restricția la acces local.
- Toate cele cinci migrations sunt aplicate. Cele opt rute ale aplicației sunt înregistrate.
- Coada reală a executat cele trei jobs fără feed configurat și a păstrat corect statusul inactiv. Schedulerul listează comanda de sincronizare la fiecare oră; accesul surselor are implicit interval de șase ore.
- Browser, baza principală: pagina se încarcă și căutarea EAN întoarce corect zero oferte, fără date inventate.
- Browser, bază separată de verificare: două oferte „TEST ONLY”, comparație 100 vs. 130 lei, diferență de 30 lei, istoric 100 → 90 lei pentru produs, salvare prag 110 lei și apariția unei alerte la total 100 lei. Serverul acestei baze de test a fost oprit.
- Aspectul desktop a fost inspectat vizual. Layoutul include reguli pentru ecrane înguste, dar emularea solicitată de 390 px nu a fost aplicată de browserul disponibil (a rămas la 1280 px); verificarea vizuală mobilă rămâne de făcut.
- Testele HTTP folosesc răspunsuri simulate. Ele **nu demonstrează funcționarea vreunui magazin live**. OCR-ul real nu a fost testat, deoarece executabilul nu este configurat.

## Ce mai trebuie

Primul pas este accesul la două cataloage autorizate și un exemplu al schemei lor, fără transmiterea secretelor în conversație. Apoi se adaptează providerii, se validează oferte reale și acoperirea variantelor/sellerilor/livrării. Pentru producție mai sunt necesare autentificare, indexare și paginare pentru cataloage mari, backup, monitorizare, procese supravegheate, condiții de retenție/atribuire ale feedurilor și teste la volum. API-ul Novelion și căutarea vizuală sunt extensii viitoare.
