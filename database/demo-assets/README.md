# Demo assets

Images the demo seeder needs, kept in the repo because `storage/app/public` is not tracked and a
package that ships without them shows broken thumbnails on the blog.

`smartdesk:demo-seed` copies these into `storage/app/public/` and points the seeded rows at them.
They are drawn, not photographed — stock imagery would need a licence that does not travel with the
package. Replace them with your own before you publish anything.

Regenerate the article covers with `tools/gen-article-covers.mjs` (needs Node and sharp).
