# ERP — proiect de disertație

Aplicație Symfony 8.1 + EasyAdmin 4 pe PostgreSQL 16, cu un serviciu Python
separat pentru recunoaștere facială.

Module: **utilizatori**, **companii** și **adrese** (țări, județe, localități),
plus sistemul de documente, auditul, mailerul și tema vizuală.

## Stack

| Serviciu | Container | Port host | Descriere |
|---|---|---|---|
| nginx | `erp-nginx` | **8090** | aplicația |
| php-fpm 8.4 | `erp-php` | — | runtime |
| PostgreSQL 16 | `erp-postgres` | **5433** | `erp_db` + `erp_audit` + `erp_face` |
| pgAdmin | `erp-pgadmin` | **5051** | administrare DB |
| Mailpit | `erp-mailer` | **8026** (UI), **1026** (SMTP) | capturează emailurile |
| Messenger worker | `erp-messenger` | — | consumă coada `async` |
| Node 22 | `erp-node` | — | `encore dev --watch` |
| Face service | `erp-face` | **8092** | FastAPI, recunoaștere facială ([detalii](face-service/README.md)) |

Porturile sunt alese să nu intre în conflict cu alte proiecte locale.

## Pornire

```bash
# configurarea locala (.env nu este in git)
cp .env.example .env

docker compose -f docker-compose.dev.yaml up -d --build

# dependențe PHP
docker compose -f docker-compose.dev.yaml exec php-service composer install

# schema (două baze: aplicație + audit)
docker compose -f docker-compose.dev.yaml exec php-service \
    php bin/console doctrine:migrations:migrate --no-interaction
docker compose -f docker-compose.dev.yaml exec php-service \
    php bin/console doctrine:migrations:migrate --no-interaction \
    --em=audit --configuration=config/doctrine_migrations_audit.yaml

# date de start (țări, județe, ~16.400 localități, setări, utilizatori demo)
docker compose -f docker-compose.dev.yaml exec php-service \
    php bin/console doctrine:fixtures:load --group=dev
```

Aplicația: <http://localhost:8090> · Emailuri: <http://localhost:8026>

`.env` conține valori implicite de dezvoltare și nu este urmărit de git. Pentru
secrete reale (`APP_SECRET`, parole de producție) folosește `.env.local`, care
are prioritate față de `.env` și este de asemenea ignorat.

### Conturi demo

| Email | Parolă | Rol |
|---|---|---|
| `admin@example.com` | `AdminP@ssw0rd!` | Administrator |
| `manager@example.com` | `ManagerP@ssw0rd!` | Manager |
| `manager2@example.com` | `UserP@ssw0rd!` | Manager |
| `client@example.com` | `ClientP@ssw0rd!` | Client |

Pentru volum mare de test (10.000 companii + 10.000 utilizatori):

```bash
docker compose -f docker-compose.dev.yaml exec -e FIXTURES_MASS=1 php-service \
    php bin/console doctrine:fixtures:load --group=dev
```

## Frontend

`erp-node` rulează `encore dev --watch`: modificările din `assets/` sunt
recompilate în `public/build/` și servite de nginx din aceeași origine.
Numele fișierelor conțin un hash de conținut și în dev, ca browserul să nu
servească un bundle vechi după fiecare modificare.
Build de producție:

```bash
docker compose -f docker-compose.dev.yaml exec node-service yarn encore production
```

## Verificări

```bash
docker compose -f docker-compose.dev.yaml exec php-service vendor/bin/phpstan analyse
docker compose -f docker-compose.dev.yaml exec php-service vendor/bin/php-cs-fixer fix --dry-run --diff
docker compose -f docker-compose.dev.yaml exec php-service php bin/console lint:twig templates
docker compose -f docker-compose.dev.yaml exec php-service php bin/console doctrine:schema:validate
```

## Serviciul de recunoaștere facială

Serviciu Python separat, în `face-service/` — FastAPI + OpenCV (YuNet + SFace),
container propriu, bază de date proprie (`erp_face`). Nu autentifică pe nimeni:
întoarce un scor de similaritate, iar decizia rămâne la Symfony.

```bash
curl localhost:8092/health          # stare
open http://localhost:8092/docs     # documentație interactivă
```

Fiecare utilizator poate avea o **fotografie de referință**, încărcată din
formularul de administrare (Administrare → Utilizatori → editare). Poza devine
avatarul și este asociată automat pentru recunoaștere. Dacă nu conține exact o
față, avatarul se salvează oricum, dar recunoașterea rămâne indisponibilă.

Detalii, endpoint-uri, licențe și ce mai trebuie înainte de producție:
[`face-service/README.md`](face-service/README.md).

## Structură

- `src/Entity/` — `Users`, `Companies`, `CompanyContacts`, `Countries`, `Counties`,
  `Cities`, `Lists`, `Elements`, `Files`, `Folders`, `Notifications`, `Settings`
- `src/Audit/` — entitățile de audit, pe baza de date separată `erp_audit`
- `src/Doctrine/Functions/JsonbContains.php` — funcție DQL pentru containment `jsonb`
  (înlocuiește `JSON_CONTAINS` din MySQL); folosită pentru coloana `users.roles`
- `assets/styles/magnum/` — tema vizuală peste EasyAdmin
- `templates/admin/layout.html.twig` — layout-ul cu sidebar propriu

## Branding

Titlul și numele companiei se configurează din **Administrare → Setări**
(`app_title`, `company_name`). Logo-urile placeholder sunt în
`public/images/logo.svg`, `logo_white.svg` (sidebar, fundal închis) și
`logo.png` (emailuri — clienții de mail nu randează SVG).

## Puncte de extensie

- `FoldersService::CLIENT_ENTITY_MAP` — se populează pe măsură ce se adaugă
  module cu documente (contracte, oferte, testări); până atunci dosarele de
  client afișează documentele proprii ale companiei.
- `NotificationHeaderType` — tipurile de notificări din dropdown-ul din header.
