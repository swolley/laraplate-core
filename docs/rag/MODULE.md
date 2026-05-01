# Modulo Core — fondamenta dell’applicazione

## In parole semplici

Il modulo **Core** è il **basamento** di Laraplate: tutto ciò che non è “contenuto pubblicabile” o “gestione aziendale specifica” ma serve a far funzionare l’applicazione in modo sicuro, coerente e riusabile sta qui. Se immaginiamo Laraplate come un edificio, Core è impianto elettrico, struttura portante e sistema di chiavi: gli altri moduli sono gli ambienti arredati (CMS, ERP, AI).

## A chi serve e cosa si aspetta

- **Sviluppatore**: qui trovi autenticazione, modello utente di riferimento, permessi, API generiche CRUD, motori di ricerca, integrazione Filament per la gestione piattaforma, job e servizi trasversali.
- **Utente tecnico avanzato**: capisce dove si configurano ruoli, utenti, parametri di sistema e come si accede alle API “metalinguaggio” `/crud/...` che operano sulle entità registrate.
- **Profilo non strettamente IT**: in Core non si “fattura” né si “pubblica un articolo”; si gestiscono **accessi**, **autorizzazioni** e **strumenti di amministrazione** (es. salute code, documentazione API).

## Funzionalità principali

### Identità, accesso e permessi

- Integrazione con **Laravel Fortify** per flussi di login, reset password e profilo nel pannello amministrativo.
- Modello utente e permessi basati su **Spatie Laravel Permission** (ruoli, permessi, middleware).
- Un utente con privilegi di **super amministratore** può bypassare i controlli abituali tramite una `Gate::before` definita nel provider del modulo: va trattato con estrema cautela in produzione.

### Pannello Filament (amministrazione piattaforma)

Il modulo espone risorse Filament per gestire, tra l’altro:

- **Utenti**, **ruoli**, **permessi** e **ACL** (liste di controllo più granulari dove previste).
- **Impostazioni** (`Setting`) chiave-valore o strutturate per il comportamento dell’app.
- **Licenze** e vincoli di utilizzo dove il prodotto lo prevede.
- **Cron job** configurati lato applicazione e tracciamento modifiche (`Modification`) dove usato nel progetto.
- Pagine di servizio come **benvenuto**, **Swagger / OpenAPI** (documentazione API) e **PhpInfo** per diagnostica ambiente (da limitare in produzione).

Queste schermate convivono nel panel principale dell’app (tipicamente percorso `admin`), insieme ai plugin registrati dall’applicazione (vedi `App\Providers\Filament\AdminPanelProvider`).

### API CRUD e griglie dati

Il file di route `Modules/Core/routes/web.php` espone un prefisso **`/crud`** con operazioni standard:

- **Lettura e interrogazione**: elenco (`select`), dettaglio, albero (`tree`), storico (`history`).
- **Scrittura**: inserimento (`insert`), aggiornamento (`update` / `replace`), eliminazione (`delete`).
- **Ciclo di vita record**: blocco ottimistico (`lock` / `unlock`), approvazione / disapprovazione, attivazione / disattivazione, svuotamento cache per entità.

Accanto, sotto `/crud/grid`, il modulo offre endpoint per **configurazioni griglia**, dati tabellari, export e layout: pensati per UI ricche (DataGrid) che consumano la stessa astrazione “entità” senza duplicare controller per ogni tabella.

Dal punto di vista del programmatore, l’`entity` nelle URL è il **nome logico del modello** o della risorsa registrata nel sistema di contenuti dinamici: non è un CRUD “hard-coded” per una sola tabella, ma un **dispatcher** verso i modelli dichiarati.

### Ricerca e contenuti dinamici

- Registrazione di client e motori per **Elasticsearch** e **Typesense** (e integrazione con **Laravel Scout** tramite motori personalizzati), così la ricerca full-text o ibrida può essere scelta a livello di configurazione.
- Servizio **DynamicContentsService** (singleton) per orchestrare contenuti e metadati dinamici collegati alle entità Core (`DynamicEntity` e correlati).

### Altri aspetti trasversali

- **Embedding** (`ModelEmbedding`): supporto polimorfico per vettori di ricerca semantica; la generazione in coda è una convenzione del progetto (non bloccare le richieste HTTP con chiamate LLM/embedder).
- **Versioning** e **modifiche** tracciate dove il dominio lo richiede (`Version`, `Modification`).
- Middleware di contesto: localizzazione, conversione stringhe-booleano per API, anteprima contenuti, abilitazione esplicita delle route CRUD API.

### Requisito importante per gli sviluppatori

All’avvio, il `CoreServiceProvider` verifica che la classe utente configurata nell’applicazione estenda il modello utente del Core (`Modules\Core\Models\User`). Se si personalizza l’utente nell’`App\Models\User`, va mantenuta la **compatibilità di ereditarietà** o l’applicazione non parte: è una scelta architettonica per uniformare permessi, trait e comportamenti.

## Come si usa in pratica

1. **Amministratore di sistema**: crea utenti e ruoli nel pannello Filament, assegna permessi, verifica impostazioni globali e licenze.
2. **Integratore / sviluppatore front-end o mobile**: consuma le route `/crud/...` e `/crud/grid/...` rispettando autenticazione (es. sessione web o token API secondo configurazione del progetto) e le policy applicate ai modelli.
3. **Sviluppatore modulo**: registra nuove entità o estensioni rispettando le convenzioni Core (permessi, policy, eventuali hook CRUD) e, se serve ricerca, configura Scout / Elasticsearch / Typesense.

## Dipendenze e ordine di caricamento

Il file `module.json` del Core imposta **priorità 0**: viene caricato per primo tra i moduli nWidart. CMS, ERP e AI si appoggiano a queste fondamenta (autenticazione, CRUD generico, ricerca, Filament).

## Limiti consapevoli

Il Core **non** sostituisce CMS o ERP: fornisce meccanismi. La “semantica” di cosa sia un documento fiscale o una pagina web sta nei moduli di dominio. Il Core garantisce che chi accede a quei dati sia **identificato**, **autorizzato** e che le operazioni passino da **canali controllati** (HTTP, code, policy).
