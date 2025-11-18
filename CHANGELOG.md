# Changelog

## [1.2.2] - 2025-11-18

### Changed
- **BREAKING CHANGE**: Rimosso `session_start()` - sostituito con WordPress Options API
  - Risolto il warning "Session cannot be started after headers have already been sent"
  - Migliorate le prestazioni e la compatibilità con cache e load balancer
  - Le sessioni PHP sono state completamente sostituite con:
    - `wc_price_changer_viewing` - modalità visualizzazione (products/variations)
    - `wc_price_changer_products` - array dei prodotti selezionati
    - `wc_price_changer_submit_type` - tipo di modifica (unit/percentage)

### Added
- Nuova pagina "WC Cron Manager" per gestire gli eventi schedulati
- Possibilità di eseguire manualmente eventi scaduti
- Possibilità di eliminare singoli eventi o pulire tutti gli eventi
- Interfaccia migliorata con badge colorati per lo stato degli eventi
- Separazione badge tipo evento dalla descrizione in tabelle separate

### Fixed
- Corretta gestione del timezone (UTC vs Europe/Berlin)
- Corretta logica di classificazione eventi (in coda vs attivi)
- Allineamento a sinistra di tutte le colonne delle tabelle
- Risolti problemi di overflow dei badge

## [1.2.1] - Versione precedente
- Versione originale con sessioni PHP
