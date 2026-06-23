# Printer and Product Property Sync Design

## Goal

Products are labels. Printers use those labels.

The sync should store product and printer compatibility data in one consistent property system. Product attributes already use Vanilo properties. Printer compatibility attributes should also use Vanilo properties instead of relying on WordPress `post_meta` keys.

This gives the application one shared vocabulary for matching labels with compatible printers.

## Current Problem

Products and printers currently store similar data in different ways.

Products use Vanilo properties:

```text
buiten-diameter
breedte
kern
printmethode
```

Printers use WordPress-style post meta keys:

```text
buiten_diameter
label_breedte
kern
druktype
```

This creates avoidable mapping problems. For example:

```text
Printer post_meta key: buiten_diameter
Product property slug: buiten-diameter
```

The sync layer has to translate between naming styles before it can match products and printers. This makes the matching logic more fragile than it needs to be.

## Design Principle

Use the same Vanilo property slug when the product and printer are describing the same real-world compatibility dimension.

For example:

```text
Product property: buiten-diameter = 101
Printer property: buiten-diameter = 66, 75, 101, 127, 152, 203
```

This should match because the product requires `101` and the printer supports `101`.

Avoid separate slugs like:

```text
printer-buiten-diameter
product-buiten-diameter
```

Those names should only be used when the value means something different and should not be directly compared.

## Slug Naming Rules

Use kebab-case for all Vanilo property slugs.

Good:

```text
buiten-diameter
materiaal-code
label-breedte-min
label-breedte-max
max-buiten-diameter
printer-subtitle
```

Avoid snake_case:

```text
buiten_diameter
materiaal_code
label_breedte_min
label_breedte_max
max_buiten_diameter
printer_subtitle
```

WooCommerce and ACF may continue to send snake_case keys. The Laravel sync layer should normalize those keys into canonical kebab-case Vanilo property slugs.

## Decimal Value Rule

Decimal values are important and must be preserved.

Decimals may arrive with either a comma separator or a dot separator:

```text
25,4
25.4
76,2
76.2
```

The sync should normalize these values internally to dot decimal format:

```text
25.4
76.2
```

The original exact numeric value must not be rounded down or discarded.

For example, this printer value:

```text
Min 25,4 mm, Max 118 mm
```

means:

```text
label-breedte-min = 25.4
label-breedte-max = 118
```

Do not treat the minimum as `25`. A printer that starts at `25.4 mm` does not necessarily support `25.0 mm`.

If expanded supported width values are generated, include the exact decimal boundary:

```text
breedte = 25.4
breedte = 26
breedte = 27
...
breedte = 118
```

The exact min and max properties should remain the source of truth for range-based matching.

## Canonical Shared Properties

These properties should be shared between products and printers.

### printmethode

Meaning: print technology.

Product source:

```text
attributes.pa_printmethode
```

Printer source:

```text
acf.druktype
```

Canonical slug:

```text
printmethode
```

Expected values:

```text
TD
TT
Inkjet
```

Product example:

```text
printmethode = TD
```

Printer example:

```text
printmethode = TD
printmethode = TT
```

Matching rule:

```text
product.printmethode must be included in printer.printmethode
```

### breedte

Meaning: label width in millimeters.

Product source:

```text
attributes.pa_breedte
dimensions.width, only as fallback
```

Printer source:

```text
acf.widths
acf.label_breedte, parsed fallback
```

Canonical slug:

```text
breedte
```

Product example:

```text
breedte = 102
```

Printer example:

```text
breedte = 25.4
breedte = 26
breedte = 27
...
breedte = 118
```

Range support properties:

```text
label-breedte-min
label-breedte-max
```

Matching rule:

```text
product.breedte >= printer.label-breedte-min
AND product.breedte <= printer.label-breedte-max
```

If the printer has explicit `breedte` values, the product width may also be matched by intersection:

```text
product.breedte must be included in printer.breedte
```

The exact range should be preferred when min and max values are available.

### hoogte

Meaning: label height in millimeters.

Product source:

```text
attributes.pa_hoogte
dimensions.height, only as fallback
```

Printer source:

```text
No reliable source in the current printer response
```

Canonical slug:

```text
hoogte
```

Product example:

```text
hoogte = 150
```

Matching rule:

Usually no printer compatibility rule should use `hoogte` unless a future printer source provides a reliable height limitation.

### kern

Meaning: roll core diameter in millimeters.

Product source:

```text
attributes.pa_kern
```

Printer source:

```text
acf.kern_data
acf.kern, parsed fallback
```

Canonical slug:

```text
kern
```

Product example:

```text
kern = 25
```

Printer example:

```text
kern = 38
kern = 76
kern = Fan-fold
```

Decimal note:

Values like `76,2 mm` must normalize to:

```text
76.2
```

Matching rule:

```text
product.kern must be included in printer.kern
```

### buiten-diameter

Meaning: roll outer diameter in millimeters.

Product source:

```text
attributes.pa_buiten-diameter
```

Printer source:

```text
acf.buiten_diameter
acf.max_buiten_diameter, parsed fallback
```

Canonical slug:

```text
buiten-diameter
```

Product example:

```text
buiten-diameter = 101
```

Printer example:

```text
buiten-diameter = 66
buiten-diameter = 75
buiten-diameter = 101
buiten-diameter = 127
buiten-diameter = 152
buiten-diameter = 203
buiten-diameter = Fan-fold
```

Range support property:

```text
max-buiten-diameter
```

Matching rule with explicit supported values:

```text
product.buiten-diameter must be included in printer.buiten-diameter
```

Matching rule with max-only fallback:

```text
product.buiten-diameter <= printer.max-buiten-diameter
```

If explicit `buiten_diameter` values exist in the printer response, prefer those over the max-only fallback.

### detectie

Meaning: label sensor or detection type.

Product source:

```text
No complete source in the current product response
```

Printer source:

```text
acf.detectie
```

Canonical slug:

```text
detectie
```

Expected values:

```text
GAP
Blackmark
Endless
Sensor notch
Pin feed
```

Printer example:

```text
detectie = GAP
detectie = Blackmark
detectie = Endless
detectie = Sensor notch
```

Matching rule:

```text
If the product has detectie, product.detectie must be included in printer.detectie.
If the product has no detectie, ignore this rule.
```

## Product-Only Properties

These properties describe labels and can be used for filtering, search, display, or future business rules. They are not necessarily printer compatibility rules.

```text
afwerking
lijm
materiaal
materiaal-code
lengte
```

Recommended handling:

```text
ArticleNumber should usually remain product.article_number, not a Vanilo property,
unless it is needed as a filterable/searchable property.
```

## Printer-Only Properties

These properties describe the printer itself or preserve exact source data. They should not replace the shared compatibility properties.

```text
printer-subtitle
printer-url
labeltype
label-breedte-min
label-breedte-max
max-buiten-diameter
```

Printer-only fields can be stored as Vanilo properties if they need to be filterable, searchable, or consistently exposed through APIs.

Otherwise, simple display fields may remain as regular post fields or metadata during the migration period.

## Source Mapping

### Product Attribute Mapping

```text
WooCommerce product attribute       Vanilo slug
pa_printmethode                     printmethode
pa_breedte                          breedte
pa_hoogte                           hoogte
pa_kern                             kern
pa_buiten-diameter                  buiten-diameter
pa_afwerking                        afwerking
pa_lijm                             lijm
pa_materiaal                        materiaal
pa_materiaal-code                   materiaal-code
```

### Printer ACF Mapping

```text
WooCommerce printer ACF key         Vanilo slug
druktype                            printmethode
widths                              breedte
label_breedte                       breedte, label-breedte-min, label-breedte-max
kern_data                           kern
kern                                kern, parsed fallback
buiten_diameter                     buiten-diameter
max_buiten_diameter                 buiten-diameter fallback, max-buiten-diameter
detectie                            detectie
labeltype                           labeltype
printers_sub_title                  printer-subtitle
printer_kopen                       printer-url
```

## Example: Printer Sync

Source:

```json
{
  "druktype": ["TD", "TT"],
  "label_breedte": "Min 25,4 mm, Max 118 mm.",
  "kern": "38 - 76,2 mm",
  "kern_data": ["38", "76", "Fan-fold"],
  "buiten_diameter": ["66", "75", "101", "127", "152", "203", "Fan-fold"],
  "detectie": ["GAP", "Blackmark", "Endless", "Sensor notch"]
}
```

Normalized printer properties:

```text
printmethode = TD
printmethode = TT

label-breedte-min = 25.4
label-breedte-max = 118
breedte = 25.4
breedte = 26
breedte = 27
...
breedte = 118

kern = 38
kern = 76
kern = Fan-fold

buiten-diameter = 66
buiten-diameter = 75
buiten-diameter = 101
buiten-diameter = 127
buiten-diameter = 152
buiten-diameter = 203
buiten-diameter = Fan-fold

detectie = GAP
detectie = Blackmark
detectie = Endless
detectie = Sensor notch
```

## Example: Product Sync

Source:

```json
{
  "attributes": [
    {"slug": "pa_printmethode", "options": ["TD"]},
    {"slug": "pa_breedte", "options": ["102"]},
    {"slug": "pa_hoogte", "options": ["150"]},
    {"slug": "pa_kern", "options": ["25"]},
    {"slug": "pa_buiten-diameter", "options": ["101"]},
    {"slug": "pa_afwerking", "options": ["ECO coated"]},
    {"slug": "pa_lijm", "options": ["Hotmelt"]}
  ]
}
```

Normalized product properties:

```text
printmethode = TD
breedte = 102
hoogte = 150
kern = 25
buiten-diameter = 101
afwerking = ECO coated
lijm = Hotmelt
```

## How Matching Works

The matching service compares shared product properties with shared printer properties.

Example product:

```text
printmethode = TD
breedte = 102
kern = 25
buiten-diameter = 101
```

Example printer:

```text
printmethode = TD, TT
label-breedte-min = 25.4
label-breedte-max = 118
kern = 38, 76, Fan-fold
buiten-diameter = 66, 75, 101, 127, 152, 203
```

Compatibility checks:

```text
TD is supported by printer printmethode: pass
102 is between 25.4 and 118: pass
25 is supported by printer kern: fail
101 is supported by printer buiten-diameter: pass
```

Final result:

```text
Not compatible because kern does not match.
```

Another product:

```text
printmethode = TD
breedte = 102
kern = 76
buiten-diameter = 101
```

Final result:

```text
Compatible.
```

## Product Indexing and Filtering

The product index should use the same canonical property slugs that the sync and matcher use.

Do not index separate legacy filter fields such as:

```text
druktype
meta_width
meta_height
buitendia
material_code
finishing
glue
```

Instead, index product properties in two structures:

```text
properties
property_numbers
```

Example indexed product payload:

```json
{
  "properties": {
    "printmethode": ["TD"],
    "breedte": ["102"],
    "hoogte": ["150"],
    "kern": ["25"],
    "buiten-diameter": ["101"],
    "afwerking": ["ECO coated"],
    "lijm": ["Hotmelt"]
  },
  "property_numbers": {
    "breedte": [102],
    "hoogte": [150],
    "kern": [25],
    "buiten-diameter": [101]
  },
  "compatibility": {
    "printmethode": "TD",
    "breedte": 102,
    "hoogte": 150,
    "kern": "25",
    "kern_numeric": 25,
    "buiten-diameter": 101
  }
}
```

Filter query names should also use canonical slugs:

```text
printmethode=TD
afwerking=ECO coated
lijm=Hotmelt
breedte_min=25.4
breedte_max=118
buiten-diameter_min=66
buiten-diameter_max=203
```

Decimal filter values may be sent with commas or dots. Both must be accepted:

```text
breedte_min=25,4
breedte_min=25.4
```

Filter option lists should be built from property values attached to products, not all global property values. This prevents printer-only values from appearing as product filters.

## Implementation Phases

### Phase 1: Add Printer Property Support

Add Vanilo property support to the printer model.

Current printers are stored in `posts` with:

```text
post_type = printer
```

The existing `Post` model can use Vanilo's property trait, or a dedicated `Printer` model can be introduced over the `posts` table for cleaner domain logic.

### Phase 2: Create Normalization Service

Create one normalization service that converts WooCommerce and ACF data into canonical property payloads.

Responsibilities:

```text
Normalize slugs to kebab-case
Map source keys to canonical slugs
Normalize comma decimals to dot decimals
Preserve exact decimal values
Parse min/max ranges
Expand supported values only when useful
Ignore empty source values
Log ambiguous values
```

### Phase 3: Update Printer Sync

Update the printer sync so compatibility fields are written to Vanilo properties.

The sync should still be idempotent:

```text
Running it multiple times should not create duplicate property values.
```

### Phase 4: Fresh Data Reset

This project can reset synced data and run the sync again, so no legacy backfill command is required.

Recommended fresh sync order:

```text
Run fresh migrations and seeders
Sync printers first
Sync materials
Sync products
Import or rebuild the product search index
```

### Phase 5: Update Matching

Update the product-printer matcher to use Vanilo properties on both sides.

Because this is a fresh project, the matcher should not keep `post_meta` fallback logic for compatibility attributes.

### Phase 6: Clean Up Legacy Metadata

After validation:

```text
Stop writing compatibility values to post_meta
Remove compatibility reads from post_meta
Keep display-only metadata if still needed
```

## Testing Requirements

Tests should cover:

```text
Comma decimal parsing: 25,4 becomes 25.4
Dot decimal parsing: 25.4 stays 25.4
Range parsing: Min 25,4 mm, Max 118 mm
Width matching using exact decimal min/max
Printer/product printmethode matching
Printer/product kern matching
Printer/product buiten-diameter matching
Fan-fold values
Missing optional product detectie should not block matching
Non-printer posts should not be matched as printers
Product index uses `properties`, `property_numbers`, and `compatibility`
Product filters use canonical kebab-case query names
```

## Final Target State

The final target is:

```text
Products use Vanilo properties for label specs.
Printers use Vanilo properties for supported label specs.
Shared compatibility dimensions use the same slugs.
Decimal values are preserved and normalized.
Matching compares product requirements against printer supported values.
WordPress post_meta is no longer the source of truth for compatibility.
```
