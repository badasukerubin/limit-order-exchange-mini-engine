# Limit-Order Exchange Mini Engine

This project implements a simplified but concurrency-safe limit-order exchange using Laravel, Vue.js (Composition API), Laravel Fortify + Sanctum, and Reverb (via Laravel Solo) for real-time broadcasting.
It demonstrates atomic balance management, safe asset locking, order matching, and real-time UI updates.

## Key features

-   Atomic Order Matching: Core matching logic is isolated in a scalable job, ensuring atomic execution and concurrency safety during trade settlement (asset/USD transfer) across buyer and seller accounts.

-   Trade Ledger: Successful matches are recorded immutably in a dedicated trades table, ensuring financial auditability and data integrity.

-   Real-Time Integration: Utilizes Laravel Reverb and private channels (private-user.{id}) to deliver instant updates on balance changes and order status to the authenticated users' frontend (Wallet Overview and Order History) upon match completion.

-   TDD Methodology: All critical financial logic (Order Placement, Cancellation, Matching, Commission) is implemented and verified using Pest for robustness.

## How Matching Works

-   User places an order → balance or asset is locked atomically

-   MatchOrderJob runs immediately

-   The engine finds the first valid counter-order, fills it, deducts commission, updates balances safely

-   A real-time OrderMatched event is broadcast using Reverb

-   Frontend listens on private-user.{id} channel and updates the UI instantly

## Setup

The project includes a one-command setup script for easy installation.
Run migrations, seeders, compile frontend, and launch the system in seconds.

```
bash ./install/setup.sh
```
