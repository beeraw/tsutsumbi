# Bundled fonts

The beeraw type family is self-hosted here rather than fetched from a CDN — the
Content-Security-Policy allows no remote resource, and a font that arrives from
somewhere else is a font that can fail to arrive at all.

| Files | Typeface | Role | Licence |
|---|---|---|---|
| `beeraw-text.woff2`, `beeraw-text-italic.woff2` | beeraw text, after [Hanken Grotesk](https://github.com/marcologous/hanken-grotesk) | body text, interface | SIL Open Font License 1.1 — `OFL-hanken-grotesk.txt` |
| `beeraw-head.woff2` | beeraw head, after [Manrope](https://github.com/sharanda/manrope) | headings | SIL Open Font License 1.1 — `OFL-manrope.txt` |
| `beeraw-mono.woff2` | beeraw mono, after [JetBrains Mono](https://github.com/JetBrains/JetBrainsMono) | keys, paths, scopes, tokens | SIL Open Font License 1.1 — `OFL-jetbrains-mono.txt` |

All are variable fonts: text 100–900, head 200–800 (upright only), mono
100–800. They cover latin and latin extended, which is enough for the six
languages the interface is served in.

The OFL requires its text to travel with the font files, which is why the
licence files sit in this directory rather than somewhere tidier. They are
copied verbatim from the beeraw-fonts repository (`dist/OFL-*.txt`); do not edit
them, and keep them alongside any font file that is added here later.
