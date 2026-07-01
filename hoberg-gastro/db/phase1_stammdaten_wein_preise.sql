-- Hoberg Gastro – Phase 1: Stammdaten-Kern, Produktdatenbank Wein, Preislisten
-- Siehe ../KONZEPT.md Abschnitt 6/7 fuer die Erklaerung.
-- Zielsystem: PostgreSQL, self-hosted (z.B. Hosttech vServer/Managed Server,
-- Betrieb als Docker-Container gemaess ../KONZEPT.md Abschnitt 11/12).

create extension if not exists pgcrypto;

-- ---------------------------------------------------------------------
-- Schema core: Stammdaten, die von allen Modulen geteilt werden
-- ---------------------------------------------------------------------
create schema if not exists core;
create schema if not exists auth;
create schema if not exists wein;

-- Sparten (Betriebsbereiche): Hotel, Restaurant, Catering, ...
create table core.sparten (
    id          uuid primary key default gen_random_uuid(),
    code        text not null unique,
    name        text not null,
    beschreibung text,
    aktiv       boolean not null default true,
    created_at  timestamptz not null default now(),
    updated_at  timestamptz not null default now()
);

insert into core.sparten (code, name) values
    ('HOTEL', 'Hotel'),
    ('RESTAURANT', 'Restaurant'),
    ('CATERING', 'Catering'),
    ('SPEZIALITAETEN', 'Spezialitaetenproduktion'),
    ('GLACE', 'Glaceherstellung'),
    ('STOERKOCH', 'Stoerkoch');

-- ---------------------------------------------------------------------
-- Schema auth: Benutzer, Rollen, 2FA
-- ---------------------------------------------------------------------

-- Rollen mit Flag, ob 2FA fuer diese Rolle Pflicht ist (Durchsetzung erfolgt
-- im Auth-Dienst beim Login, nicht als DB-Constraint, siehe KONZEPT.md 9.1)
create table auth.rollen (
    id              uuid primary key default gen_random_uuid(),
    code            text not null unique,
    name            text not null,
    erfordert_2fa   boolean not null default false
);

insert into auth.rollen (code, name, erfordert_2fa) values
    ('ADMIN', 'Administration/Stammdaten-Pflege', true),
    ('PREISLISTEN_FREIGABE', 'Preislisten-Freigabe', true),
    ('EINKAUF', 'Einkauf', false),
    ('SPARTEN_LESER', 'Lesezugriff je Sparte', false),
    ('SERVICE_ACCOUNT', 'Technischer Zugriff (Webseite/Kassensystem)', false);

-- Mitarbeitende-Logins. TOTP-Secret NIE im Klartext -> verschluesselt
-- (z.B. via pgcrypto pgp_sym_encrypt mit Schluessel aus Vaultwarden/Env,
-- nicht im gleichen Datensatz).
create table auth.benutzer (
    id                              uuid primary key default gen_random_uuid(),
    email                           text not null unique,
    passwort_hash                   text not null,
    vorname                         text,
    nachname                        text,
    aktiv                           boolean not null default true,
    zwei_faktor_aktiv               boolean not null default false,
    zwei_faktor_secret_verschluesselt bytea,
    zwei_faktor_aktiviert_am        timestamptz,
    created_at                      timestamptz not null default now(),
    updated_at                      timestamptz not null default now()
);

-- Rollenzuweisung, optional eingeschraenkt auf eine Sparte
-- (sparte_id = null -> Rolle gilt fuer alle Sparten)
create table auth.benutzer_rollen (
    id          uuid primary key default gen_random_uuid(),
    benutzer_id uuid not null references auth.benutzer(id) on delete cascade,
    rolle_id    uuid not null references auth.rollen(id),
    sparte_id   uuid references core.sparten(id),
    created_at  timestamptz not null default now(),
    unique (benutzer_id, rolle_id, sparte_id)
);

-- Gehashte Einmal-Codes fuer Geraeteverlust (2FA-Wiederherstellung)
create table auth.zwei_faktor_backup_codes (
    id            uuid primary key default gen_random_uuid(),
    benutzer_id   uuid not null references auth.benutzer(id) on delete cascade,
    code_hash     text not null,
    verwendet_am  timestamptz,
    created_at    timestamptz not null default now()
);

create index benutzer_rollen_benutzer_idx on auth.benutzer_rollen (benutzer_id);
create index zwei_faktor_backup_codes_benutzer_idx on auth.zwei_faktor_backup_codes (benutzer_id);

-- MwSt-Saetze mit Gueltigkeitszeitraum (Saetze aendern sich ueber die Zeit)
create table core.mwst_saetze (
    id          uuid primary key default gen_random_uuid(),
    code        text not null,
    satz        numeric(5,2) not null,
    gueltig_ab  date not null,
    gueltig_bis date,
    created_at  timestamptz not null default now()
);

-- Partnerkategorien: gruppiert core.partner fachlich (aus realer Hoberg-
-- Lieferantenliste uebernommen: Weine, Getraenke, Lebensmittel, Buchhaltung, ...)
create table core.partner_kategorien (
    id      uuid primary key default gen_random_uuid(),
    code    text not null unique,
    name    text not null
);

insert into core.partner_kategorien (code, name) values
    ('WEINE', 'Weine'),
    ('GETRAENKE', 'Getraenke allgemein'),
    ('LEBENSMITTEL', 'Lebensmittel'),
    ('BUCHHALTUNG', 'Buchhaltung'),
    ('KASSENSYSTEM', 'Kassensystem'),
    ('HOTEL_PLATTFORM', 'Hotel-/Buchungsplattform'),
    ('REINIGUNG_WAESCHE', 'Reinigung/Waescherei'),
    ('DIENSTLEISTUNG', 'Sonstige Dienstleistung');

-- Partner: vereinigt Lieferanten, Kunden und Dienstleister in einem Register
-- (nicht getrennt je Modul), da dieselbe Firma je nach Kontext Lieferant UND
-- Kunde sein kann. Rollenflags statt getrennter Tabellen -> ein einziger
-- Datensatz pro Firma/Kontakt, wiederverwendbar in Einkauf, CRM, Verkauf.
create table core.partner (
    id                          uuid primary key default gen_random_uuid(),
    kurzname                    text not null,
    firma                       text not null,
    kategorie_id                uuid references core.partner_kategorien(id),
    strasse                     text,
    hausnummer                  text,
    plz                         text,
    ort                         text,
    land                        text not null default 'CH',
    ansprechperson_vorname      text,
    ansprechperson_nachname     text,
    telefon                     text,
    mobil                       text,
    email                       text,
    webseite                    text,
    kundennummer_beim_partner   text,
    ist_kunde                   boolean not null default false,
    ist_lieferant                boolean not null default false,
    ist_dienstleister            boolean not null default false,
    bestellkanal                text,
    online_shop_url              text,
    -- Bewusst KEINE Zugangsdaten (Benutzername/Passwort) hier ablegen:
    -- Verweis auf Eintrag in separatem Secrets-Manager (z.B. Vaultwarden,
    -- siehe KONZEPT.md 11/6.5), niemals das Geheimnis selbst in der Business-DB.
    zugangsdaten_hinterlegt      boolean not null default false,
    zugangsdaten_verweis         text,
    notizen                      text,
    aktiv                        boolean not null default true,
    created_at                   timestamptz not null default now(),
    updated_at                   timestamptz not null default now()
);

create index partner_kategorie_idx on core.partner (kategorie_id);

-- Produktkategorien, hierarchisch (Getraenke > Wein > Rotwein)
create table core.produkt_kategorien (
    id          uuid primary key default gen_random_uuid(),
    parent_id   uuid references core.produkt_kategorien(id),
    code        text not null unique,
    name        text not null
);

-- Generische Produkt-Stammdaten fuer ALLE Artikeltypen.
-- produkt_typ steuert, welche Zusatztabelle (analog wein.weine) ergaenzend gilt.
create table core.produkte (
    id              uuid primary key default gen_random_uuid(),
    artikelnummer   text not null unique,
    ean             text,
    produkt_typ     text not null check (produkt_typ in
                        ('WEIN', 'SPEISE', 'ZUTAT', 'HANDELSWARE', 'GLACE')),
    kategorie_id    uuid references core.produkt_kategorien(id),
    bezeichnung     text not null,
    kurzbeschreibung text,
    lieferant_id    uuid references core.partner(id),
    einkaufspreis   numeric(10,2) not null default 0,
    einheit         text not null default 'Stk',
    mwst_satz_id    uuid references core.mwst_saetze(id),
    bild_url        text,
    aktiv           boolean not null default true,
    created_at      timestamptz not null default now(),
    updated_at      timestamptz not null default now(),
    created_by      uuid,
    updated_by      uuid
);

create index produkte_typ_idx on core.produkte (produkt_typ);
create index produkte_kategorie_idx on core.produkte (kategorie_id);

-- Preislisten: eine Preisliste gilt fuer genau eine Sparte
create table core.preislisten (
    id          uuid primary key default gen_random_uuid(),
    sparte_id   uuid not null references core.sparten(id),
    name        text not null,
    gueltig_ab  date not null,
    gueltig_bis date,
    waehrung    text not null default 'CHF',
    status      text not null default 'ENTWURF'
                    check (status in ('ENTWURF', 'AKTIV', 'ARCHIVIERT')),
    created_at  timestamptz not null default now(),
    updated_at  timestamptz not null default now()
);

create index preislisten_sparte_idx on core.preislisten (sparte_id);

-- Preislisten-Positionen: Verkaufspreis eines Produkts in einer Preisliste,
-- mit eigenem Gueltigkeitszeitraum (Mutation = neue Zeile statt Ueberschreiben)
create table core.preislisten_positionen (
    id              uuid primary key default gen_random_uuid(),
    preisliste_id   uuid not null references core.preislisten(id) on delete cascade,
    produkt_id      uuid not null references core.produkte(id),
    verkaufspreis   numeric(10,2) not null,
    mwst_satz_id    uuid references core.mwst_saetze(id),
    rabatt_prozent  numeric(5,2) not null default 0,
    gueltig_ab      date not null default current_date,
    gueltig_bis     date,
    created_at      timestamptz not null default now(),
    updated_at      timestamptz not null default now(),
    unique (preisliste_id, produkt_id, gueltig_ab)
);

create index preislistenpos_produkt_idx on core.preislisten_positionen (produkt_id);
create index preislistenpos_preisliste_idx on core.preislisten_positionen (preisliste_id);

-- Preis-Historie: Audit-Log jeder Preisaenderung (Einkauf UND Verkauf)
create table core.preis_historie (
    id                  uuid primary key default gen_random_uuid(),
    produkt_id          uuid not null references core.produkte(id),
    preisliste_id       uuid references core.preislisten(id),
    quelle              text not null check (quelle in ('EINKAUFSPREIS', 'VERKAUFSPREIS')),
    alter_preis         numeric(10,2),
    neuer_preis         numeric(10,2) not null,
    geaendert_von       uuid,
    geaendert_am        timestamptz not null default now(),
    grund               text
);

create index preishistorie_produkt_idx on core.preis_historie (produkt_id);

-- ---------------------------------------------------------------------
-- Trigger: jede Preismutation wird automatisch in core.preis_historie erfasst
-- ---------------------------------------------------------------------
create or replace function core.log_einkaufspreis_mutation()
returns trigger
language plpgsql
as $$
begin
    if new.einkaufspreis is distinct from old.einkaufspreis then
        insert into core.preis_historie (produkt_id, quelle, alter_preis, neuer_preis, geaendert_von)
        values (new.id, 'EINKAUFSPREIS', old.einkaufspreis, new.einkaufspreis, new.updated_by);
    end if;
    return new;
end;
$$;

create trigger produkte_einkaufspreis_mutation
    after update on core.produkte
    for each row
    execute function core.log_einkaufspreis_mutation();

create or replace function core.log_verkaufspreis_mutation()
returns trigger
language plpgsql
as $$
begin
    if new.verkaufspreis is distinct from old.verkaufspreis then
        insert into core.preis_historie (produkt_id, preisliste_id, quelle, alter_preis, neuer_preis)
        values (new.produkt_id, new.preisliste_id, 'VERKAUFSPREIS', old.verkaufspreis, new.verkaufspreis);
    end if;
    return new;
end;
$$;

create trigger preislistenpos_verkaufspreis_mutation
    after update on core.preislisten_positionen
    for each row
    execute function core.log_verkaufspreis_mutation();

-- ---------------------------------------------------------------------
-- Schema wein: Zusatzattribute fuer Produkte vom Typ 'WEIN'
-- Muster fuer alle weiteren Produktarten (speisen, glace_artikel, ...)
-- ---------------------------------------------------------------------
create table wein.weine (
    produkt_id          uuid primary key references core.produkte(id) on delete cascade,
    rebsorten           text[],
    jahrgang            int,
    land                text,
    region              text,
    appellation         text,
    winzer              text,
    farbe               text check (farbe in ('Weiss', 'Rot', 'Rose', 'Schaumwein', 'Suesswein', 'Orange')),
    flascheninhalt_ml   int not null default 750,
    alkoholgehalt       numeric(4,1),
    suesse              text check (suesse in ('trocken', 'halbtrocken', 'lieblich', 'suess')),
    bio_zertifiziert    boolean not null default false,
    allergene           text,
    verkostungsnotiz    text,
    trinktemperatur_c   text,
    lagerfaehig_bis     int,
    created_at          timestamptz not null default now(),
    updated_at          timestamptz not null default now(),
    constraint weine_produkt_typ_check
        check (produkt_id is not null)
);

-- Sicherstellen, dass nur Produkte vom Typ WEIN einen Eintrag in wein.weine haben
create or replace function wein.check_produkt_typ_wein()
returns trigger
language plpgsql
as $$
declare
    v_typ text;
begin
    select produkt_typ into v_typ from core.produkte where id = new.produkt_id;
    if v_typ is distinct from 'WEIN' then
        raise exception 'produkt_id % hat produkt_typ % statt WEIN', new.produkt_id, v_typ;
    end if;
    return new;
end;
$$;

create trigger weine_produkt_typ_guard
    before insert or update on wein.weine
    for each row
    execute function wein.check_produkt_typ_wein();

-- ---------------------------------------------------------------------
-- Beispiel-View: aktueller Verkaufspreis eines Weins je Sparte
-- Basis fuer Menuekarten-Modul, Webseite, Kassensystem
-- ---------------------------------------------------------------------
create view core.aktuelle_verkaufspreise as
select
    p.id as produkt_id,
    p.artikelnummer,
    p.bezeichnung,
    s.code as sparte_code,
    s.name as sparte_name,
    pos.verkaufspreis,
    pos.rabatt_prozent,
    pl.waehrung,
    pos.gueltig_ab
from core.produkte p
join core.preislisten_positionen pos on pos.produkt_id = p.id
join core.preislisten pl on pl.id = pos.preisliste_id and pl.status = 'AKTIV'
join core.sparten s on s.id = pl.sparte_id
where (pos.gueltig_bis is null or pos.gueltig_bis >= current_date)
  and pos.gueltig_ab <= current_date;

-- ---------------------------------------------------------------------
-- Beispieldaten: ein Wein mit unterschiedlichen Preisen in Restaurant/Catering
-- ---------------------------------------------------------------------
-- insert into core.produkte (artikelnummer, produkt_typ, bezeichnung, einkaufspreis, einheit)
--     values ('WEIN-0001', 'WEIN', 'Pinot Noir Reserve 2022', 14.50, 'Flasche 75cl')
--     returning id; -- id merken als :produkt_id
--
-- insert into wein.weine (produkt_id, rebsorten, jahrgang, land, region, winzer, farbe, flascheninhalt_ml, alkoholgehalt, suesse)
--     values (:'produkt_id', array['Pinot Noir'], 2022, 'Schweiz', 'Graubuenden', 'Weingut Beispiel', 'Rot', 750, 13.0, 'trocken');
--
-- Preisliste Restaurant + Catering anlegen, dann je eine Position mit
-- unterschiedlichem verkaufspreis in core.preislisten_positionen einfuegen.
