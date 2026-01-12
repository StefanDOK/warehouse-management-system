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
```

## Funcționalități

- **WMS-7**: Implement Goods Receipt Module - Modul de recepție mărfuri
- **WMS-8**: Barcode Scanning at Goods Receipt - Scanare coduri de bare la recepție
- **WMS-9**: Automated Stock Allocation to Shelves - Alocare automată a stocului pe rafturi
- **WMS-10**: Generate Pick List for Orders - Generare liste de picking pentru comenzi
- **WMS-11**: Barcode Scanning during Picking - Scanare coduri de bare în timpul picking-ului
- **WMS-12**: Real-Time Stock Update - Actualizare stoc în timp real
- **WMS-13**: Return Products to Stock - Returnare produse în stoc
- **WMS-14**: Daily Inventory Report - Raport zilnic de inventar
- **WMS-15**: Low Stock Alert - Alertă stoc scăzut
- **WMS-16**: WMS Integration with ERP - Integrare WMS cu ERP

## Structură

```
src/
├── Controller/     # Controllere HTTP
├── Entity/         # Entități Doctrine
├── Repository/     # Repository-uri pentru acces date
├── Service/        # Servicii de business logic
├── DTO/            # Data Transfer Objects
├── Event/          # Evenimente
└── EventSubscriber/# Subscriberi pentru evenimente
```

## Pornire server de dezvoltare

```bash
symfony server:start
# sau
php -S localhost:8000 -t public
```
