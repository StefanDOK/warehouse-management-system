# WMS - Warehouse Management System

Sistem de management al depozitului dezvoltat în PHP 8.2 / Symfony 7.2.


## Cerințe

- PHP 8.2+
- Composer 2.x
- SQLite (sau alt SGBD compatibil Doctrine)

## Instalare

```bash
composer install
php bin/console doctrine:database:create
php bin/console doctrine:schema:create
php bin/console doctrine:database:migrate
```

## Pornire server de dezvoltare

```bash
symfony server:start
# sau
php -S localhost:8000 -t public
```

## Funcționalități Implementate

| Branch | Funcționalitate | Descriere |
|--------|-----------------|-----------|
| WMS-7 | Goods Receipt Module | Modul de recepție mărfuri |
| WMS-8 | Barcode Scanning at Goods Receipt | Scanare coduri de bare la recepție |
| WMS-9 | Automated Stock Allocation to Shelves | Alocare automată a stocului pe rafturi |
| WMS-10 | Generate Pick List for Orders | Generare liste de picking pentru comenzi |
| WMS-11 | Barcode Scanning during Picking | Scanare coduri de bare în timpul picking-ului |
| WMS-12 | Real-Time Stock Update | Actualizare stoc în timp real |
| WMS-13 | Return Products to Stock | Returnare produse în stoc |
| WMS-14 | Daily Inventory Report | Raport zilnic de inventar |
| WMS-15 | Low Stock Alert | Alertă stoc scăzut |
| WMS-16 | WMS Integration with ERP | Integrare WMS cu ERP |

## Structură Proiect

```
src/
├── Controller/          # Controllere HTTP (API endpoints)
│   ├── AlertController.php
│   ├── BarcodeScanController.php
│   ├── ErpIntegrationController.php
│   ├── GoodsReceiptController.php
│   ├── PickingScanController.php
│   ├── PickListController.php
│   ├── ProductReturnController.php
│   ├── ReportController.php
│   ├── StockAllocationController.php
│   └── StockController.php
├── DTO/                 # Data Transfer Objects
├── Entity/              # Entități Doctrine
├── Repository/          # Repository-uri pentru acces date
├── Service/             # Servicii de business logic
├── Event/               # Evenimente
└── EventSubscriber/     # Subscriberi pentru evenimente
```

## API Endpoints

### Goods Receipt (Recepție mărfuri)
- `GET /api/goods-receipts` - Lista recepții
- `GET /api/goods-receipts/pending` - Recepții în așteptare
- `POST /api/goods-receipts` - Creare recepție nouă
- `GET /api/goods-receipts/{id}` - Detalii recepție
- `POST /api/goods-receipts/{id}/items` - Adăugare produs la recepție
- `POST /api/goods-receipts/{id}/start` - Pornire procesare
- `POST /api/goods-receipts/{id}/complete` - Finalizare recepție

### Barcode Scanning (Scanare coduri de bare)
- `POST /api/barcode/scan` - Scanare cod de bare
- `POST /api/barcode/batch-scan` - Scanare în lot
- `POST /api/barcode/validate` - Validare cod de bare
- `POST /api/barcode/goods-receipt/{id}/items/{itemId}/scan` - Scanare la receptie

### Stock Allocation (Alocare stoc)
- `POST /api/stock-allocation/allocate` - Alocare automată
- `POST /api/stock-allocation/allocate-to-shelf` - Alocare pe raft specific
- `POST /api/stock-allocation/suggest` - Sugestie raft
- `POST /api/stock-allocation/from-goods-receipt/{id}` - Alocare din recepție
- `GET /api/stock-allocation/shelves/available` - Rafturi disponibile

### Pick Lists (Liste de picking)
- `GET /api/pick-lists` - Lista pick lists
- `POST /api/pick-lists/generate` - Generare pick list
- `POST /api/pick-lists/generate-batch` - Generare în lot
- `GET /api/pick-lists/{id}` - Detalii pick list
- `GET /api/pick-lists/{id}/optimized-path` - Rută optimizată
- `POST /api/pick-lists/{id}/start` - Pornire picking
- `POST /api/pick-lists/{id}/complete` - Finalizare picking

### Picking (Procesare picking)
- `POST /api/picking/{pickListId}/scan` - Scanare rapidă
- `POST /api/picking/{pickListId}/items/{itemId}/scan` - Scanare item
- `POST /api/picking/{pickListId}/items/{itemId}/pick-all` - Pick total
- `GET /api/picking/{pickListId}/progress` - Progres picking

### Stock Management (Gestiune stoc)
- `GET /api/stock/product/{id}` - Stoc produs
- `GET /api/stock/product/sku/{sku}` - Stoc după SKU
- `POST /api/stock/update` - Actualizare stoc
- `POST /api/stock/adjust` - Ajustare stoc
- `POST /api/stock/transfer` - Transfer între locații
- `GET /api/stock/summary` - Sumar stoc
- `GET /api/stock/movements` - Mișcări stoc

### Product Returns (Returnări)
- `GET /api/returns` - Lista returnări
- `GET /api/returns/pending` - Returnări în așteptare
- `POST /api/returns` - Creare returnare
- `GET /api/returns/{id}` - Detalii returnare
- `POST /api/returns/{id}/items` - Adăugare produs
- `POST /api/returns/{id}/inspect` - Pornire inspecție
- `POST /api/returns/{id}/complete` - Finalizare și restocking
- `POST /api/returns/{id}/reject` - Respingere

### Reports (Rapoarte)
- `GET /api/reports/daily` - Raport zilnic
- `GET /api/reports/inventory-summary` - Sumar inventar
- `GET /api/reports/low-stock` - Produse stoc scăzut
- `GET /api/reports/valuation` - Raport valoare
- `GET /api/reports/stock-by-location` - Stoc după locație
- `GET /api/reports/movements` - Sumar mișcări
- `GET /api/reports/top-products` - Produse cele mai vândute

### Alerts (Alerte)
- `GET /api/alerts` - Lista alerte active
- `GET /api/alerts/summary` - Sumar alerte
- `POST /api/alerts/check` - Verificare produse
- `GET /api/alerts/{id}` - Detalii alertă
- `POST /api/alerts/{id}/acknowledge` - Confirmare alertă
- `POST /api/alerts/{id}/resolve` - Rezolvare alertă

### ERP Integration (Integrare ERP)
- `POST /api/erp/products/sync` - Sincronizare produs
- `POST /api/erp/products/sync-batch` - Sincronizare în lot
- `POST /api/erp/orders` - Creare comandă din ERP
- `GET /api/erp/orders/{externalOrderId}/status` - Status comandă
- `POST /api/erp/orders/{externalOrderId}/ship` - Marcare expediată
- `GET /api/erp/stock-levels` - Niveluri stoc pentru ERP
- `GET /api/erp/health` - Health check

## Entități Principale

- **Product** - Produse cu SKU, cod de bare, preț
- **Shelf** - Locații/rafturi în depozit
- **Stock** - Stoc pe locație
- **GoodsReceipt** - Recepții mărfuri
- **Order** - Comenzi
- **PickList** - Liste de picking
- **ProductReturn** - Returnări produse
- **StockMovement** - Mișcări stoc
- **LowStockAlert** - Alerte stoc scăzut

## Licență

Proiect intern - toate drepturile rezervate.
