# XPSR website

The site at [xpsr.crysto.name.ng](https://xpsr.crysto.name.ng): the home page and the documentation of [XPSR](https://github.com/crystoline/xpsr).

It is an XPSR application, created with `composer create-project crystoline/xpsr`. It renders every page on request, like any XPSR application, and `php xpsr site:build` exports them all as static HTML for hosting.

## Where the content comes from

The documentation is written in the framework repository, in `docs/*.md` and the package READMEs, and nowhere else. This repository holds only the home page, the templates, and the code that renders the docs.

- **Versions.** Each minor release from 0.3 on gets its own documentation, taken from its latest patch tag (`/docs/0.3/` is `v0.3.0`). The development branch is `/docs/next/`. `/docs/` leads to the latest release. A new release appears at the next build; nothing here needs to change.
- **Sidebar.** `docs/README.md` in the framework repository lists the pages: each `##` heading is a section and each `- [Title](FILE.md)` item a page. Releases without that file (0.3) use [resources/navigation.md](resources/navigation.md), without the pages they lack.
- **Links.** A relative link to a page becomes the page's URL (`ROUTING.md#named-urls` becomes `/docs/0.3/routing/#named-urls`). Links to other repository files, such as source code, open them on GitHub at the same tag or branch. Heading anchors are the ones GitHub makes, so the same links work in both places.
- **Code.** [Phiki](https://github.com/phikiphp/phiki) highlights code when the page is rendered, with a light and a dark colour per token, so pages need no highlighting script.
- **Search.** [Pagefind](https://pagefind.app) indexes the exported pages. Search runs in the browser and looks only in the version being read.

## Develop

Requires PHP 8.4 with the `dom` and `phar` extensions, Composer 2, and git. Building with search also needs Node.js for `npx`.

```sh
composer install
cp .env.example .env
php xpsr site:fetch     # mirror crystoline/xpsr into var/sources and extract each version
php xpsr serve          # http://localhost:3001, pages rendered on each request
```

To preview changes to the documentation before pushing them, point `DOCS_NEXT_PATH` in `.env` at a checkout of the framework repository. `/docs/next/` then reads that checkout's files on every request.

Search works only in a build: `php xpsr serve` says so if you try it.

## Build

```sh
composer build          # php xpsr site:build, then Pagefind
```

`site:build` fetches the documentation again, sends a request for every page through the application, and writes the responses to `build/` (`/docs/0.3/routing/` becomes `build/docs/0.3/routing/index.html`), with `404.html`, `sitemap.xml`, `robots.txt`, `CNAME`, and the files in `public/`. Then it checks every link and anchor between the pages and lists the broken ones.

| Option | Effect |
|---|---|
| `--no-fetch` | Build from the documentation fetched before. |
| `--output=DIR` | Write somewhere other than `build/`. A directory that an earlier build did not write is left alone. |
| `--strict` | Exit with an error if a link is broken. Broken links in released versions only warn, because their tags cannot change. |

`APP_URL` sets the address used in canonical links, the sitemap, and `CNAME`; it defaults to `https://xpsr.crysto.name.ng`. Pages link to each other from the site root, so the site must be served at the root of its domain.

## Deploy

[.github/workflows/site.yml](.github/workflows/site.yml) runs the checks on every push and pull request. On `master` it then builds the site and publishes it with GitHub Pages. It also runs every day, so documentation pushed to the framework repository appears within a day, and on demand from the Actions tab.

One-time setup:

1. **Pages.** In this repository's settings, under Pages, set the source to **GitHub Actions**.
2. **Domain.** Add a DNS record `xpsr.crysto.name.ng CNAME crystoline.github.io`. Then enter `xpsr.crysto.name.ng` as the custom domain under Pages, and turn on **Enforce HTTPS** once the certificate is issued.
3. **Faster updates (optional).** To rebuild as soon as the docs change, add a step to the framework repository's CI that sends a `docs` event with a token allowed to run this repository's workflows:

   ```sh
   gh api repos/crystoline/xpsr-website/dispatches -f event_type=docs
   ```

The `build/` directory is a plain static site, so any web server can host it instead.

## Checks

```sh
composer check          # PHPUnit, PHPStan, and PHP-CS-Fixer
```

The tests use the documentation in [tests/Fixtures/sources](tests/Fixtures/sources), never the network.
