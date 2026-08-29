# Changelog

Toate modificările notabile ale aplicației sunt documentate în acest fișier.

## [0.5.0] - 2026-08-29

### Modificat

- **Formularul de utilizator** folosește acum tema Magnum, ca pagina de profil,
  în locul temei implicite EasyAdmin: secțiuni cu iconițe (identificare,
  fotografie, acces, parolă), grilă pe două coloane și acțiuni în subsol.
  Implementat cu `UserFormType` + `admin/users/user_form.html.twig`, același
  tipar folosit deja pentru companii.

### Adăugat

- **Previzualizarea fotografiei** în formularul de utilizator, actualizată în
  timp real la selectarea unui fișier, cu buton roșu de ștergere lângă imagine.
  Ștergerea elimină fișierul și datele de recunoaștere facială, dar se aplică
  doar la salvare — până atunci poate fi anulată. O poză nouă o înlocuiește
  oricum pe cea veche.

- **Fotografie de referință pe utilizator** — câmp în formularul de administrare.
  La salvare, poza devine avatarul utilizatorului și este trimisă serviciului de
  recunoaștere facială pentru asociere. O poză fără față (sau cu mai multe) este
  păstrată ca avatar, dar apare un avertisment că autentificarea facială nu e
  disponibilă — salvarea utilizatorului nu eșuează niciodată din cauza asocierii.
- `FaceRecognitionService` — client HTTP către serviciul Python (înrolare,
  verificare 1:1, stare, ștergere).
- Datele biometrice se șterg automat odată cu utilizatorul (GDPR Art. 17).

### Corectat

- `Users::__serialize()` exclude starea tranzitorie a formularului. `Users` este
  entitatea de securitate și ajunge serializată în sesiune, iar un `UploadedFile`
  nu poate fi serializat: fără asta, un administrator care își încărca propria
  fotografie primea „Serialization of UploadedFile is not allowed".

## [0.4.0] - 2026-08-29

### Adăugat

- **Serviciu de recunoaștere facială** (`face-service/`) — FastAPI + OpenCV
  (YuNet pentru detecție, SFace pentru embeddings de 128 dimensiuni), container
  și bază de date proprii (`erp_face`). Endpoint-uri de înrolare, identificare
  1:N, verificare 1:1 și ștergere. Modelele sunt MIT / Apache 2.0, deci pot fi
  folosite comercial, spre deosebire de InsightFace.
  Serviciul întoarce doar scoruri de similaritate — autentificarea rămâne în ERP.

## [0.3.0] - 2026-08-29

### Modificat

- **Roluri** — setul s-a redus la **Administrator**, **Manager** și **Client**.
  `ROLE_CONSULTANT` era rolul de bază al personalului intern (moștenit de manager),
  deci a fost promovat la `ROLE_MANAGER`, care preia acum acel rol în ierarhie;
  `ROLE_COMPANY_MANAGER` moștenea `ROLE_CLIENT` și a fost coborât la `ROLE_CLIENT`.
  Migrarea remapează rolurile utilizatorilor existenți, fără pierdere de acces.

### Corectat

- Paginile de securitate (autentificare, recuperare și schimbare parolă) încărcau
  doar bundle-ul `login`, fără modulul care completează token-ul CSRF stateless,
  așa că orice POST era respins cu „Token CSRF este invalid". Modulul este acum
  importat explicit în `assets/js/login.js`.
- Asset-urile sunt versionate și în dev, altfel browserul păstra bundle-ul vechi
  după fiecare modificare (regresie din trecerea pe `encore dev --watch`).

## [0.2.0] - 2026-08-29

### Modificat

- **Companii** — eliminată noțiunea de tip companie (client / furnizor / subcontractor).
  Rămâne o singură listă de companii; coloana `companies.types` a fost ștearsă,
  împreună cu vederile dedicate, filtrul și graficul aferent.

### Corectat

- Selectorul de responsabil intern folosea `LIKE` pe coloana JSON `users.roles`,
  ceea ce PostgreSQL respinge; interogarea trece acum prin `JSONB_CONTAINS`.

## [0.1.0] - 2026-08-29

Versiune inițială: nucleul de utilizatori, companii și adrese.

### Adăugat

- **Utilizatori și autentificare** — conturi, roluri, schimbare parolă, recuperare parolă, verificare email.
- **Companii** — clienți, furnizori și subcontractori, contacte companie, vizualizări dedicate pe tip de companie.
- **Adrese** — nomenclator țări, județe și localități, cu datele pentru România.
- **Nomenclatoare** — liste și elemente configurabile (tip client, industrie, dimensiune companie).
- **Documente** — foldere de sistem și pe client, încărcare și servire fișiere.
- **Audit** — jurnal de autentificare, acces și modificări de entități, pe bază de date separată.
- **Setări** — configurare generală și financiară a aplicației.
- **Interfață** — tema Magnum peste EasyAdmin, dashboard pe roluri, notificări.
