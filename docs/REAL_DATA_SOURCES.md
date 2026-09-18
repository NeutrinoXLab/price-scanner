# Real data sources investigated

Research date: 2026-09-18. A service being available does not mean Price Scanner has access.

| Source | Coverage and data | Access needed | Format / public limits | Current status |
|---|---|---|---|---|
| 2Performant product feeds | Romanian advertisers; title, prices, merchant, product ID, URLs, images, category, brand, active state, GTIN and custom data | Affiliate account and acceptance into each advertiser program | User-created CSV/XML feed URL; no universal public request limit found | Placeholder configured; no approved URL |
| Profitshare feeds/API | Romanian advertisers; products, prices, descriptions, images and stock where supplied | Affiliate account, advertiser/feed access, API user/key for API | CSV/XML feeds; official affiliate API PDF exists but is old and must be reconfirmed | Placeholder configured; no credentials |
| Awin publisher feeds | EU/international catalogs; links, names, descriptions, prices, shipping, images and vertical attributes | Publisher account, publisher ID, bearer token and feed permission | Feed download; enhanced endpoint returns JSONL; no public limit found on reviewed page | Placeholder configured; no credentials |
| Merchant-owned CSV/XML/JSON | RO/EU merchant catalogs | Written permission and stable specification | Merchant-specific | Canonical CSV adapter works |
| Google Merchant API | Products in an authenticated Merchant Center account | Merchant Center and OAuth/service account | REST | Not a general competitor catalog |
| eMAG Marketplace API | Seller-side marketplace operations | Marketplace seller/partner access and contractual confirmation | Partner API | Not treated as a public competitor catalog |

Official references:

- https://support.2performant.com/how-to-add-product-feeds-to-your-website
- https://w.profitshare.ro/affiliate/resources
- https://app.profitshare.ro/files/pdf/api_affiliate.pdf
- https://help.awin.com/developers/docs/product-feed-publisher-guide-intro
- https://help.awin.com/apidocs/retail-publisher-productapidocumentation-1
- https://developers.google.com/merchant/api/overview

## First recommended connection: 2Performant

1. Create or use a 2Performant affiliate account.
2. Apply to relevant Romanian advertiser programs and wait for acceptance.
3. In Tools > My feeds, create one product feed per accepted advertiser.
4. Include title, product ID, merchant, current/old price, product URL, brand, active state, GTIN, description, image and useful custom fields.
5. Obtain the CSV URL and confirm that internal comparison use is permitted.
6. Supply one real sample for field mapping and fixture tests, then set `TWOPERFORMANT_FEED_URL` and `TWOPERFORMANT_FEED_APPROVED=true` locally.

Do not commit tokens or secret-bearing feed URLs.
