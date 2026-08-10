# Bundled fonts

Two typefaces are self-hosted here rather than fetched from a CDN — the
Content-Security-Policy allows no remote resource, and a font that arrives from
somewhere else is a font that can fail to arrive at all.

| Files | Typeface | Licence |
|---|---|---|
| `nunito-latin.woff2`, `nunito-latin-ext.woff2` | [Nunito](https://github.com/googlefonts/nunito) | SIL Open Font License 1.1 — `nunito-OFL.txt` |
| `jetbrains-mono-latin.woff2`, `jetbrains-mono-latin-ext.woff2` | [JetBrains Mono](https://github.com/JetBrains/JetBrainsMono) | SIL Open Font License 1.1 — `jetbrains-mono-OFL.txt` |

Both are variable subsets, latin and latin extended, which covers the six
languages the interface is served in.

The OFL requires its text to travel with the font files, which is why the two
licence files sit in this directory rather than somewhere tidier. They are
copied verbatim from each project's own repository; do not edit them, and keep
them alongside any font file that is added here later.
