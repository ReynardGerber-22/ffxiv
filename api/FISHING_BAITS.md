# Recommended fishing bait

Run from the project root:

```sh
docker compose exec -T php php artisan migrate
docker compose exec -T php php artisan fishing:import-baits
```

For a targeted refresh:

```sh
docker compose exec -T php php artisan fishing:import-baits --item=4930 --item=4776 --item=28937
```

The command downloads Teamcraft's dataset once, validates it, batches missing
item metadata through ItemSyncService/XIVAPI, and creates missing fishing spots
through FishingSyncService. Normal API enrichment reads the database only.
Existing item and spot metadata is reused.

`fishing_baits` references the fish Item, FishingSpot and immediate bait Item.
Each distinct source record is retained as JSON. A source-content hash extends
the fish/spot/bait unique key so records with different conditions survive;
identical duplicate records are collapsed. This hash is for deduplication, not
source-version tracking.

A successful fish import replaces its previous recommendations transactionally.
A metadata failure preserves that fish's old recommendations and the command
reports failure. Retrying is safe. Fish completely absent from the upstream
dataset are not automatically deleted. Existing fish/spot links are retained.

The existing expanded-materials endpoint adds `baits: [{id, name}]` to every
known fishing location, with an empty array when no recommendation is cached.
All distinct bait Items are returned; their order does not imply ranking.
Immediate mooch fish are returned as Items too. Full mooch chains and condition
presentation are intentionally deferred. Original conditions remain in
`source_data` for future use.

Data attribution and the complete Teamcraft MIT license are in
`../THIRD_PARTY_NOTICES.md`. No frontend changes are included.
