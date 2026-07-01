---
applyTo: "Facades/Elements/**/*.php"
---

# UI5 Element Development Instructions

## Escaping strings in PHP-generated JavaScript and UI5 views

When generating JavaScript or UI5 view code from PHP in element classes, always use the
appropriate escape helper instead of `addslashes()`, `json_encode()` directly, or manual escaping.

### `escapeString($value, $encloseInQuotes = true)`

Defined in `AbstractJqueryElement`. Use this when embedding a PHP value as a **JavaScript
string literal** in regular JS code (controller scripts, `addOnShowViewScript`, etc.).

- By default wraps the result in double quotes: `"escaped value"`.
- Pass `false` as second argument to get the raw escaped content without surrounding quotes —
  useful when you are already inside a quoted JS string in a PHP heredoc, e.g.:

```php
// With quotes (default) — use directly as a JS expression:
$js = "var s = {$this->escapeString($phpValue)};";

// Without quotes — embed inside an already-quoted heredoc string:
$escaped = $this->escapeString($color, false);
$js = "var sColor = '{$escaped}';";
```

Internally uses `json_encode()` with `JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES`, so it
correctly handles quotes, backslashes, unicode, etc.

### `escapeJsTextValue($text)`

Defined in `UI5AbstractElement`. Use this for values placed directly inside **UI5 view
property strings** (e.g. `text: "..."`, `tooltip: "..."`).

Differences from `escapeString()`:
- Additionally escapes curly braces `{` and `}` to `\{` and `\}` so UI5 does not interpret
  them as data binding expressions.
- Does **not** wrap in quotes — the caller is expected to wrap in `"..."`.

```php
// Use in UI5 constructor property strings:
$js = 'text: "' . $this->escapeJsTextValue($widget->getCaption()) . '",';
```

### `escapeBool($value)`

Defined in `AbstractJqueryElement`. Use this when embedding a PHP boolean as a **JavaScript
boolean literal** — returns the string `'true'` or `'false'`.

```php
// Without this helper you'd have to cast manually:
$js = "var bEnabled = {$this->escapeBool($widget->isEnabled())};";
```

### Rule of thumb

| Context | Method |
|---|---|
| Regular JS variable / string literal | `escapeString($val)` or `escapeString($val, false)` |
| UI5 view property value (`text:`, `tooltip:`, etc.) | `escapeJsTextValue($val)` |
| HTML attribute value | `escapeString($val, true, true)` (sets `$forUseInHtml = true`) |
| JS boolean literal | `escapeBool($val)` |

**Never use** `addslashes()`, raw string concatenation, or `htmlspecialchars()` for JS output unless explicitly instructed.
