# NPC gil vendors

From the project root:

```sh
docker compose exec -T php php artisan migrate
docker compose exec -T php php artisan vendors:sync
```

The default sync covers all Items currently cached in the planner. Run it again
after importing new materials, or request explicit IDs (including uncached Items):

```sh
docker compose exec -T php php artisan vendors:sync \
  --item=5106 --item=5111 --item=5291 --item=5530
```

Only NQ offers from `GilShopItem` joined to `GilShop` qualify. `Item.PriceMid`
provides the unit price only after an offer is found. HQ offers, SpecialShop and
all other exchange systems are excluded. A positive Item price alone does not
create an offer. The command raises a finite CLI memory limit below 512 MB to
512 MB for decoding Teamcraft's full NPC dataset; API memory settings are unchanged.

The resolver follows direct ENpcBase links, PreHandler targets and TopicSelect
menus, with cycle protection. It stores NPC/shop links once and retains handler
paths and available unlock metadata. CustomTalk/script-only links are not yet
supported. The NPC title is displayed verbatim; guild membership is not inferred.

The five tables are `vendor_npcs`, `gil_shops`, `gil_shop_items`,
`vendor_shop_links` and `vendor_locations`. Inventory uniqueness is shop/subrow;
NPC/shop links are many-to-many; each NPC can have multiple placements. World
X/Z are converted using the placement Map's scale/offsets. World Y is elevation.
Valid XIVAPI Level placements take precedence over Teamcraft map coordinates.
The fallback supplies only its known position, not a guarantee of all positions.
No position is invented for housing or other unlocated vendors.

The import fetches and validates remote data before replacing the selected
items' cached offers in a database transaction. Repeats update existing records,
remove stale offers for selected items and replace resolved locations/links.
Unselected items are retained. An upstream failure leaves the prior vendor
snapshot intact. Unused shop/NPC metadata is retained. A cache lock prevents
overlapping sync commands; an abruptly killed command can leave its lock until
the two-hour expiry.

MaterialSourceService appends `vendors` independently of gathering, fishing and
mob drops. VendorService uses four eager-loaded queries regardless of material
count, and performs no network requests. Each entry has the NPC ID/name/title,
unit price, territory, optional area/map ID, coordinates and `locationResolved`.
Unresolved locations return null geography and false `locationResolved`.
Identical display entries across shop menus are deduplicated; distinct NPC IDs,
prices and displayed locations remain separate.

The frontend initially shows two vendors, preferring resolved locations. A shared
unit price is displayed once, while differing prices are shown per vendor. The
expansion control includes unresolved vendors with “Location unavailable”.
Access-condition metadata is stored but not evaluated against player progress or
presented yet; quest/event-gated offers must not be interpreted as always available.

Teamcraft attribution and the MIT license are in `../THIRD_PARTY_NOTICES.md`.
