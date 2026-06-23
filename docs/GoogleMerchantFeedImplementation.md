# Google Merchant Feed Implementation

This document summarizes the implementation in `app/Console/Commands/GenerateGoogleMerchantFeed.php` so the same feature can be recreated in another Laravel project.

## Purpose

The command generates a Google Merchant Center XML product feed and writes it to:

```text
public/xmlfeed.xml
```

The feed is intended for `bouwbeslag.nl` products and is available publicly as:

```text
/xmlfeed.xml
```

## Artisan Command

The command signature is:

```bash
php artisan app:generate-google-merchant-feed
```

It is scheduled in `routes/console.php`:

```php
Schedule::command('app:generate-google-merchant-feed')->dailyAt('07:00');
```

## Feed Format

The generated file is an RSS 2.0 XML document using Google's Merchant Center namespace:

```xml
<rss xmlns:g="http://base.google.com/ns/1.0" version="2.0">
```

The feed channel contains:

- `title`: `Bouwbeslag.nl`
- `link`: `https://bouwbeslag.nl`
- `description`: `Google Merchant Center product feed - bouwbeslag.nl`

## Product Selection

Only products marked for Bouwbeslag sync are included:

```php
Product::query()
    ->where('sync_to_bouwbeslag', true)
```

Products are processed in chunks of `200` records to avoid loading the full product table into memory.

## Required Product Fields

The implementation depends on these product fields:

| Field | Purpose |
| --- | --- |
| `sku` | Google product ID and MPN |
| `bouwbeslag_title` | Product title |
| `slug` | Product URL path |
| `unit_price` | Base product price |
| `margin_b2c` | B2C margin percentage |
| `brand_name` | Brand name and brand discount lookup |
| `stock` | Supplier stock |
| `own_stock` | Internal stock |
| `supplier` | Used for handling time logic |
| `ean_code` | GTIN |
| `main_picture_url` | Preferred product image URL |
| `main_picture` | Fallback product image URL |
| `meters` | Optional multiplier for meter-based products |
| `product_lengnth` | Product length used for oversized shipping |
| `product_length_unit` | Length unit: `mm`, `cm`, or `m` |
| `sync_to_bouwbeslag` | Determines whether the product is included |

Note: `product_lengnth` appears to be misspelled in the implementation. Check whether the target project uses the same typo or a corrected column such as `product_length`.

## Brand Discounts

Brand discounts are loaded once before processing products:

```php
$brandDiscounts = Brand::query()
    ->pluck('discount', 'name')
    ->map(fn ($value) => (float) $value);
```

The command then matches a product's `brand_name` against the brand name from the `brands` table.

## Price Calculation

The command calculates a base sales price before VAT.

If the product has a `meters` value greater than `0`:

```php
unit_price * meters * margin_multiplier
```

Otherwise:

```php
discounted_unit_price * margin_multiplier
```

Where:

```php
margin_multiplier = 1 + (margin_b2c / 100)
discounted_unit_price = unit_price * (1 - (brand_discount / 100))
```

Products with a calculated price of `0` or less are skipped.

VAT is then added at `21%`:

```php
$priceWithTax = round($price * 1.21, 2);
```

The final Google price is formatted as:

```text
12.34 EUR
```

## Product URL

Each product URL is generated from the Bouwbeslag base URL and the product slug:

```php
$productUrl = $baseUrl.'/'.($product->slug ?? Str::slug((string) $product->name));
```

If `slug` is missing, the product name is converted to a slug.

## Product Image

The command attempts to resolve an image URL in this order:

1. `main_picture_url`
2. `main_picture`

Only absolute URLs starting with `http://` or `https://` are accepted.

If no valid image URL exists, the `g:image_link` field is omitted.

## Product Description

Descriptions come from:

```php
$product->descriptionFieldValues()
```

The command uses the first available value from:

1. `description`
2. `description_1`
3. product title fallback

HTML tags are stripped before writing the description.

## Availability

Availability is based on combined stock:

```php
((float) $product->stock + (float) $product->own_stock) > 0
```

If stock is greater than `0`, the feed value is:

```text
in_stock
```

Otherwise:

```text
out_of_stock
```

## Google Merchant Fields

Each product item writes these fields:

| Google Field | Source |
| --- | --- |
| `g:id` | `sku` |
| `g:title` | `bouwbeslag_title` |
| `g:description` | description field fallback |
| `g:link` | generated Bouwbeslag product URL |
| `g:image_link` | resolved image URL |
| `g:condition` | always `new` |
| `g:availability` | stock-based availability |
| `g:price` | calculated price including VAT |
| `g:gtin` | `ean_code`, when present |
| `g:identifier_exists` | `yes` if GTIN exists, otherwise `no` |
| `g:mpn` | `sku` |
| `g:brand` | `brand_name` |

## Shipping Logic

Shipping is added for both:

- `NL`
- `BE`

The command calculates product length in millimeters:

```php
match (strtolower($unit)) {
    'cm' => $value * 10,
    'm' => $value * 1000,
    default => $value,
};
```

Shipping cost rules:

| Condition | Shipping Cost |
| --- | --- |
| Product length over `1600mm` | `30.00 EUR` |
| Price including VAT is at least `75 EUR` | `0.00 EUR` |
| Otherwise | `5.49 EUR` |

Transit time is always:

```text
1 to 1 day
```

## Handling Time

Handling time depends on supplier:

| Supplier | Min Handling Time | Max Handling Time |
| --- | --- | --- |
| `kok` | `0` | `1` |
| Any other supplier | `2` | `5` |

The feed also writes:

```text
g:handling_cutoff_time = 13:00
g:handling_cutoff_timezone = Europe/Amsterdam
```

## File Writing

The command writes the XML to a temporary file first:

```php
public/xmlfeed.xml.tmp
```

Then it renames the temporary file to:

```php
public/xmlfeed.xml
```

This prevents Google or users from reading a partially-written XML file.

## Logging

After generation, the command logs:

- number of included products
- number of skipped products
- output path
- feed URL

Example log key:

```php
[GoogleMerchantFeed] Feed generated successfully
```

## Porting Checklist

To implement this in another Laravel project:

1. Create an Artisan command for generating the XML feed.
2. Add the command to the scheduler.
3. Confirm the product model has equivalent fields.
4. Map the target project's product fields to the Google feed fields.
5. Implement or remove brand discount logic.
6. Implement the correct price calculation for the new project.
7. Confirm VAT percentage.
8. Confirm shipping countries and shipping cost rules.
9. Confirm handling time rules.
10. Write the XML feed to `public/xmlfeed.xml`.
11. Make sure the file is publicly reachable.
12. Add tests for product inclusion, pricing, availability, shipping, GTIN handling, and skipped products.

## Important Differences To Check In The New Project

- Product URL base domain
- Product title field
- Product description source
- Image storage format
- Whether prices are stored with or without VAT
- Brand discount availability
- Shipping thresholds
- Oversized product length threshold
- Stock fields
- Supplier handling-time rules
- Whether Google requires additional fields for the target product category

