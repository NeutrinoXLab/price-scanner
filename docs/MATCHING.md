# Product matching

- **exact**: verified GTIN, or exact model/MPN without conflicting dimensions or pack quantity.
- **probable**: several independent characteristics support the same physical product without a hard conflict.
- **similar**: useful overlap exists, but identity is not established.
- **unknown**: evidence is insufficient.

Signals stored in `offer_matches` include GTIN, model/MPN, normalized token similarity, dimensions and pack quantity. Exact GTIN is strong evidence. A different or absent GTIN does not reject a generic rebranded product. Conflicting dimensions or pack quantities reduce confidence.

Romanian and English titles are normalized for case, diacritics, punctuation, dimensions, common organizer/clothing synonyms and pack terms. Normalization supports comparison; it does not prove identity.

Probable and similar proposals remain pending until approved or rejected. The decision, note and timestamp are retained.
